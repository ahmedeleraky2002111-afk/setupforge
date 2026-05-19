import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class ApiService {
  static const String baseUrl = "http://10.39.22.177/setupforge/APIs";

  // ─── Token & User Storage ───────────────────────────────────────────────────

  Future<void> saveToken(String token) async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.setString("auth_token", token); // fixed: was "token"
  }

  Future<String?> getToken() async {
    final prefs = await SharedPreferences.getInstance();
    return prefs.getString("auth_token"); // fixed: was "token"
  }

  Future<void> clearToken() async {
    final prefs = await SharedPreferences.getInstance();
    await prefs.remove("auth_token");
    await prefs.remove("user_name");
    await prefs.remove("user_email");
    await prefs.remove("user_type");
    await prefs.remove("signup_intent");
  }

  Future<void> _saveUserInfo(Map<String, dynamic> data) async {
    final prefs = await SharedPreferences.getInstance();
    if (data["name"] != null) {
      await prefs.setString("user_name", data["name"].toString());
    }
    if (data["email"] != null) {
      await prefs.setString("user_email", data["email"].toString());
    }
    if (data["user_type"] != null) {
      await prefs.setString("user_type", data["user_type"].toString());
    }
  }

  // ─── Auth ────────────────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> login({
    required String email,
    required String password,
  }) async {
    final uri = Uri.parse("$baseUrl/api_login.php");

    final res = await http.post(
      uri,
      body: {"email": email, "password": password},
    );

    Map<String, dynamic> data;
    try {
      data = jsonDecode(res.body) as Map<String, dynamic>;
    } catch (_) {
      return {"ok": false, "error": "Invalid server response", "raw": res.body};
    }

    if (data["ok"] == true && data["token"] != null) {
      await saveToken(data["token"].toString());
      await _saveUserInfo(data);
    }

    return data;
  }

  Future<Map<String, dynamic>> signupFull({
    required String name,
    required String email,
    required String password,
    String? phone,
    String? country,
    String? city,
    String? street,
    String userType = "customer", // "business" if came from wizard
    String? businessType,
    String? size,
    int? budget,
  }) async {
    final uri = Uri.parse("$baseUrl/api_signup.php");

    final body = <String, String>{
      "name": name,
      "email": email,
      "password": password,
      "user_type": userType,
    };

    if (phone != null && phone.trim().isNotEmpty) body["phone"] = phone.trim();
    if (country != null && country.trim().isNotEmpty) {
      body["country"] = country.trim();
    }
    if (city != null && city.trim().isNotEmpty) body["city"] = city.trim();
    if (street != null && street.trim().isNotEmpty) {
      body["street"] = street.trim();
    }
    if (businessType != null && businessType.trim().isNotEmpty) {
      body["business_type"] = businessType.trim();
    }
    if (size != null && size.trim().isNotEmpty) body["size"] = size.trim();
    if (budget != null && budget > 0) body["budget"] = budget.toString();

    final res = await http.post(uri, body: body);

    Map<String, dynamic> data;
    try {
      data = jsonDecode(res.body) as Map<String, dynamic>;
    } catch (_) {
      return {"ok": false, "error": "Invalid server response", "raw": res.body};
    }

    if (data["ok"] == true && data["token"] != null) {
      await saveToken(data["token"].toString());
      // Save user info — merge what we sent since API may not return it all
      final prefs = await SharedPreferences.getInstance();
      await prefs.setString("user_name", name);
      await prefs.setString("user_email", email);
      await prefs.setString("user_type", userType);
    }

    return data;
  }

  Future<Map<String, dynamic>> me() async {
    final token = await getToken();
    if (token == null || token.isEmpty) {
      return {"ok": false, "error": "No token"};
    }

    final uri = Uri.parse("$baseUrl/api_me.php");

    final res = await http.get(
      uri,
      headers: {"Authorization": "Bearer $token"},
    );

    Map<String, dynamic> data;
    try {
      data = jsonDecode(res.body) as Map<String, dynamic>;
    } catch (_) {
      return {"ok": false, "error": "Invalid server response"};
    }

    // Keep local user info fresh
    if (data["ok"] == true) {
      await _saveUserInfo(data);
    }

    return data;
  }

  Future<void> logout() async {
    await clearToken();
  }

  // ─── Packages ────────────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> generatePackages({
    required String businessType,
    required String size,
    required int budget,
    required List<String> modules,
    required Map<String, String> moduleTiers,
    String restaurantType = 'standard_dining',
    int indoorTables = 0,
    int outdoorTables = 0,
    int areaSqm = 0,
    String budgetRange = '',
  }) async {
    final uri = Uri.parse("$baseUrl/api_generate_packages.php");

    final safeModules = modules
        .map((e) => e.trim().toLowerCase())
        .where((e) => ["kitchen", "furniture", "pos", "ac"].contains(e))
        .toSet()
        .toList();

    final payload = {
      "business_type": businessType,
      "size": size,
      "budget": budget,
      "modules": safeModules,
      "module_tiers": moduleTiers,
      "restaurant_type": restaurantType,
      "indoor_tables": indoorTables,
      "outdoor_tables": outdoorTables,
      "area_sqm": areaSqm,
      "budget_range": budgetRange,
    };

    try {
      final res = await http.post(
        uri,
        headers: {"Content-Type": "application/json"},
        body: jsonEncode(payload),
      );

      print("URL: $uri");
      print("Status: ${res.statusCode}");
      print("Body: ${res.body}");

      try {
        return jsonDecode(res.body) as Map<String, dynamic>;
      } catch (_) {
        return {
          "ok": false,
          "error": "Invalid server response",
          "raw": res.body,
          "status_code": res.statusCode,
        };
      }
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
  }

  // ─── Orders ──────────────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> placeOrder({
    required String token,
    required List<Map<String, dynamic>> items,
    required String businessName,
    required String phone,
    required String address,
    String notes = '',
    String businessType = '',
    String restaurantType = '',
    int indoorTables = 0,
    int outdoorTables = 0,
    int areaSqm = 0,
    String budgetRange = '',
    List<String> installationServices = const [],
    Map<String, int> staffCounts = const {},
    String paymentMethod = 'cash',
    String preferredDeliveryDate = '',
  }) async {
    final uri = Uri.parse("$baseUrl/api_place_order.php");

    final payload = {
      "items": items,
      "business_name": businessName,
      "phone": phone,
      "address": address,
      "notes": notes,
      "business_type": businessType,
      "restaurant_type": restaurantType,
      "indoor_tables": indoorTables,
      "outdoor_tables": outdoorTables,
      "area_sqm": areaSqm,
      "budget_range": budgetRange,
      "installation_services": installationServices,
      "staff_counts": staffCounts,
      "payment_method": paymentMethod,
      "preferred_delivery_date": preferredDeliveryDate,
    };

    print('[placeOrder] payload: ${jsonEncode(payload)}');

    final res = await http
        .post(
          uri,
          headers: {
            "Content-Type": "application/json",
            "Authorization": "Bearer $token",
          },
          body: jsonEncode(payload),
        )
        .timeout(const Duration(seconds: 30));

    print('[placeOrder] status: ${res.statusCode}  body: ${res.body}');

    try {
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (_) {
      return {
        "ok": false,
        "error": "Invalid server response",
        "raw": res.body,
        "status_code": res.statusCode,
      };
    }
  }
}
