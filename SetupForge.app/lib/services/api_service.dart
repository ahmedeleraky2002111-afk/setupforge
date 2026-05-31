import 'dart:async';
import 'dart:convert';
import 'package:http/http.dart' as http;
import 'package:shared_preferences/shared_preferences.dart';

class ApiService {
  static const String baseUrl =
      "https://setupforge-production.up.railway.app/APIs";

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
    if (country != null && country.trim().isNotEmpty)
      body["country"] = country.trim();
    if (city != null && city.trim().isNotEmpty) body["city"] = city.trim();
    if (street != null && street.trim().isNotEmpty)
      body["street"] = street.trim();
    if (businessType != null && businessType.trim().isNotEmpty)
      body["business_type"] = businessType.trim();
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

  // ─── Labor ───────────────────────────────────────────────────────────────────
  Future<Map<String, dynamic>> getHomeData() async {
    final token = await getToken();
    if (token == null || token.isEmpty)
      return {"ok": false, "error": "No token"};
    final uri = Uri.parse("$baseUrl/api_home.php");
    try {
      final res = await http
          .get(uri, headers: {"Authorization": "Bearer $token"})
          .timeout(const Duration(seconds: 15));
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
  }

  Future<Map<String, dynamic>> saveWizardStep({
    required int step,
    required Map<String, dynamic> wizard,
  }) async {
    final token = await getToken();
    if (token == null) return {"ok": false, "error": "No token"};
    final uri = Uri.parse("$baseUrl/api_wizard_save.php");
    try {
      final res = await http
          .post(
            uri,
            headers: {
              "Authorization": "Bearer $token",
              "Content-Type": "application/json",
            },
            body: jsonEncode({"step": step, "wizard": wizard}),
          )
          .timeout(const Duration(seconds: 15));
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
  }

  Future<Map<String, dynamic>> resumeWizard() async {
    final token = await getToken();
    if (token == null) return {"ok": false, "error": "No token"};
    final uri = Uri.parse("$baseUrl/api_wizard_resume.php");
    try {
      final res = await http
          .get(uri, headers: {"Authorization": "Bearer $token"})
          .timeout(const Duration(seconds: 15));
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
  }

  Future<Map<String, dynamic>> getBusinessOverview() async {
    final token = await getToken();
    if (token == null || token.isEmpty)
      return {"ok": false, "error": "No token"};
    final uri = Uri.parse("$baseUrl/api_business_overview.php");
    try {
      final res = await http
          .get(uri, headers: {"Authorization": "Bearer $token"})
          .timeout(const Duration(seconds: 15));
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
  }

  Future<Map<String, dynamic>> getPackages() async {
    final token = await getToken();
    if (token == null) return {"ok": false, "error": "No token"};
    final uri = Uri.parse("$baseUrl/api_packages.php");
    try {
      final res = await http
          .get(uri, headers: {"Authorization": "Bearer $token"})
          .timeout(const Duration(seconds: 15));
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
  }

  Future<Map<String, dynamic>> packagesAction({
    required String action,
    required String module,
    required String type,
    int? qty,
    String? productId,
  }) async {
    final token = await getToken();
    if (token == null) return {"ok": false, "error": "No token"};
    final uri = Uri.parse("$baseUrl/api_packages_action.php");
    try {
      final res = await http
          .post(
            uri,
            headers: {
              "Authorization": "Bearer $token",
              "Content-Type": "application/json",
            },
            body: jsonEncode({
              "action": action,
              "module": module,
              "type": type,
              if (qty != null) "qty": qty,
              if (productId != null) "product_id": productId,
            }),
          )
          .timeout(const Duration(seconds: 15));
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
  }

  Future<Map<String, dynamic>> searchPackageProducts({
    required String module,
    String type = '',
    String search = '',
    int? minPrice,
    int? maxPrice,
  }) async {
    final token = await getToken();
    if (token == null) return {"ok": false, "error": "No token"};
    final uri = Uri.parse("$baseUrl/api_packages_search.php").replace(
      queryParameters: {
        "module": module,
        if (type.isNotEmpty) "type": type,
        if (search.isNotEmpty) "search": search,
        if (minPrice != null) "min_price": minPrice.toString(),
        if (maxPrice != null) "max_price": maxPrice.toString(),
      },
    );
    try {
      final res = await http
          .get(uri, headers: {"Authorization": "Bearer $token"})
          .timeout(const Duration(seconds: 15));
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
  }

  Future<Map<String, dynamic>> getProducts({
    String category = '',
    String brand = '',
    String module = '',
    String minPrice = '',
    String maxPrice = '',
    String sort = '',
    String search = '',
  }) async {
    final token = await getToken();
    final uri = Uri.parse("$baseUrl/api_products.php").replace(
      queryParameters: {
        if (category.isNotEmpty) 'category': category,
        if (brand.isNotEmpty) 'brand': brand,
        if (module.isNotEmpty) 'module': module,
        if (minPrice.isNotEmpty) 'min_price': minPrice,
        if (maxPrice.isNotEmpty) 'max_price': maxPrice,
        if (sort.isNotEmpty) 'sort': sort,
        if (search.isNotEmpty) 'search': search,
      },
    );
    try {
      final res = await http
          .get(
            uri,
            headers: token != null ? {"Authorization": "Bearer $token"} : {},
          )
          .timeout(const Duration(seconds: 15));
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
  }

  Future<Map<String, dynamic>> cartAdd(int productId, {int qty = 1}) async {
    final token = await getToken();
    if (token == null) return {"ok": false, "error": "Login required"};
    final uri = Uri.parse("$baseUrl/api_products.php");
    try {
      final res = await http
          .post(
            uri,
            headers: {"Authorization": "Bearer $token"},
            body: {
              "action": "cart_add",
              "product_id": productId.toString(),
              "qty": qty.toString(),
            },
          )
          .timeout(const Duration(seconds: 15));
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
  }

  Future<Map<String, dynamic>> cartRemove(int productId) async {
    final token = await getToken();
    if (token == null) return {"ok": false, "error": "Login required"};
    final uri = Uri.parse("$baseUrl/api_products.php");
    try {
      final res = await http
          .post(
            uri,
            headers: {"Authorization": "Bearer $token"},
            body: {"action": "cart_remove", "product_id": productId.toString()},
          )
          .timeout(const Duration(seconds: 15));
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
  }

  Future<Map<String, dynamic>> cartUpdate(int productId, int qty) async {
    final token = await getToken();
    if (token == null) return {"ok": false, "error": "Login required"};
    final uri = Uri.parse("$baseUrl/api_products.php");
    try {
      final res = await http
          .post(
            uri,
            headers: {"Authorization": "Bearer $token"},
            body: {
              "action": "cart_update",
              "product_id": productId.toString(),
              "qty": qty.toString(),
            },
          )
          .timeout(const Duration(seconds: 15));
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
  }

  Future<Map<String, dynamic>> cartList() async {
    final token = await getToken();
    if (token == null) return {"ok": false, "error": "Login required"};
    final uri = Uri.parse("$baseUrl/api_products.php?action=cart_list");
    try {
      final res = await http
          .get(uri, headers: {"Authorization": "Bearer $token"})
          .timeout(const Duration(seconds: 15));
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
  }

  Future<Map<String, dynamic>> placeShopOrder({
    required String deliveryName,
    required String deliveryPhone,
    required String deliveryLocation,
    String orderNotes = '',
  }) async {
    final token = await getToken();
    if (token == null) return {"ok": false, "error": "Login required"};
    final uri = Uri.parse("$baseUrl/api_shop_place_order.php");
    try {
      final res = await http
          .post(
            uri,
            headers: {
              "Authorization": "Bearer $token",
              "Content-Type": "application/json",
            },
            body: jsonEncode({
              "delivery_name": deliveryName,
              "delivery_phone": deliveryPhone,
              "delivery_location": deliveryLocation,
              "order_notes": orderNotes,
            }),
          )
          .timeout(const Duration(seconds: 15));
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
  }

  Future<Map<String, dynamic>> getServiceJobs() async {
    final token = await getToken();
    if (token == null || token.isEmpty)
      return {"ok": false, "error": "No token"};
    final uri = Uri.parse("$baseUrl/api_service_jobs.php");
    try {
      final res = await http
          .get(uri, headers: {"Authorization": "Bearer $token"})
          .timeout(const Duration(seconds: 15));
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
  }

  Future<Map<String, dynamic>> getLaborDashboard() async {
    final token = await getToken();
    if (token == null || token.isEmpty) {
      return {"ok": false, "error": "No token"};
    }
    final uri = Uri.parse("$baseUrl/api_labor_dashboard.php");
    try {
      final res = await http
          .get(uri, headers: {"Authorization": "Bearer $token"})
          .timeout(const Duration(seconds: 15));
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
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
      final res = await http
          .post(
            uri,
            headers: {"Content-Type": "application/json"},
            body: jsonEncode(payload),
          )
          .timeout(const Duration(seconds: 15));

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

  // ─── Labor ───────────────────────────────────────────────────────────────────

  Future<Map<String, dynamic>> laborSignup({
    required String name,
    required String email,
    required String password,
    String phone = '',
    String country = '',
    String city = '',
    String street = '',
    String nationalId = '',
    String skills = '',
    String laborRole = '',
    double hourlyRate = 0,
    String experienceLevel = 'junior',
    String providerType = 'waiter',
    String militaryStatus = 'n/a',
  }) async {
    final uri = Uri.parse("$baseUrl/api_labor_signup.php");

    final body = <String, String>{
      "name": name,
      "email": email,
      "password": password,
      "phone": phone,
      "country": country,
      "city": city,
      "street": street,
      "national_id": nationalId,
      "skills": skills,
      "labor_role": laborRole,
      "hourly_rate": hourlyRate.toString(),
      "experience_level": experienceLevel,
      "provider_type": providerType,
      "military_status": militaryStatus,
    };

    try {
      final res = await http
          .post(uri, body: body)
          .timeout(const Duration(seconds: 15));
      try {
        final data = jsonDecode(res.body) as Map<String, dynamic>;
        if (data["ok"] == true && data["token"] != null) {
          await saveToken(data["token"].toString());
          final prefs = await SharedPreferences.getInstance();
          await prefs.setString("user_name", name);
          await prefs.setString("user_email", email);
          await prefs.setString("user_type", "labor");
        }
        return data;
      } catch (_) {
        return {
          "ok": false,
          "error": "Invalid server response",
          "raw": res.body,
        };
      }
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
  }

  Future<Map<String, dynamic>> getLaborJobs() async {
    final token = await getToken();
    if (token == null || token.isEmpty)
      return {"ok": false, "error": "No token"};
    final uri = Uri.parse("$baseUrl/api_labor_jobs.php");
    try {
      final res = await http
          .get(uri, headers: {"Authorization": "Bearer $token"})
          .timeout(const Duration(seconds: 15));
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
  }

  Future<Map<String, dynamic>> completeJob(int jobId) async {
    final token = await getToken();
    if (token == null || token.isEmpty)
      return {"ok": false, "error": "No token"};
    final uri = Uri.parse("$baseUrl/api_labor_jobs.php");
    try {
      final res = await http
          .post(
            uri,
            headers: {
              "Authorization": "Bearer $token",
              "Content-Type": "application/json",
            },
            body: jsonEncode({"job_id": jobId}),
          )
          .timeout(const Duration(seconds: 15));
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
  }

  Future<Map<String, dynamic>> getLaborProfile() async {
    final token = await getToken();
    if (token == null || token.isEmpty)
      return {"ok": false, "error": "No token"};
    final uri = Uri.parse("$baseUrl/api_labor_profile.php");
    try {
      final res = await http
          .get(uri, headers: {"Authorization": "Bearer $token"})
          .timeout(const Duration(seconds: 15));
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
  }

  Future<Map<String, dynamic>> updateLaborProfile({
    required String name,
    String phone = '',
    String country = '',
    String city = '',
    String street = '',
    String skills = '',
    double hourlyRate = 0,
    String laborRole = '',
    String availabilityStatus = 'available',
  }) async {
    final token = await getToken();
    if (token == null || token.isEmpty)
      return {"ok": false, "error": "No token"};
    final uri = Uri.parse("$baseUrl/api_labor_edit_profile.php");
    try {
      final res = await http
          .post(
            uri,
            headers: {
              "Authorization": "Bearer $token",
              "Content-Type": "application/json",
            },
            body: jsonEncode({
              "name": name,
              "phone": phone,
              "country": country,
              "city": city,
              "street": street,
              "skills": skills,
              "hourly_rate": hourlyRate,
              "labor_role": laborRole,
              "availability_status": availabilityStatus,
            }),
          )
          .timeout(const Duration(seconds: 15));
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
  }

  Future<Map<String, dynamic>> placeSetupOrder() async {
    final token = await getToken();
    if (token == null) return {"ok": false, "error": "Login required"};
    final uri = Uri.parse("$baseUrl/api_place_setup_order.php");
    try {
      final res = await http
          .post(
            uri,
            headers: {
              "Authorization": "Bearer $token",
              "Content-Type": "application/json",
            },
            body: jsonEncode({}),
          )
          .timeout(const Duration(seconds: 15));
      return jsonDecode(res.body) as Map<String, dynamic>;
    } catch (e) {
      return {"ok": false, "error": "Request failed: $e"};
    }
  }
}
