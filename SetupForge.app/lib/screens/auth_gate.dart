import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../services/api_service.dart';

class AuthGate extends StatefulWidget {
  const AuthGate({super.key});

  @override
  State<AuthGate> createState() => _AuthGateState();
}

class _AuthGateState extends State<AuthGate> {
  @override
  void initState() {
    super.initState();
    _checkAuth();
  }

  Future<void> _checkAuth() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('auth_token');

    if (token == null || token.isEmpty) {
      if (!mounted) return;
      Navigator.pushReplacementNamed(context, '/login');
      return;
    }

    // Token exists — verify it and get user_type
    final api = ApiService();
    final result = await api.me();

    if (!mounted) return;

    if (result["ok"] != true) {
      // Token invalid or expired — clear and go to login
      await api.clearToken();
      Navigator.pushReplacementNamed(context, '/login');
      return;
    }

    final userType = result["user_type"]?.toString() ?? "";

    if (userType == "labor") {
      Navigator.pushReplacementNamed(context, '/labor-shell');
    } else {
      Navigator.pushReplacementNamed(context, '/app-shell');
    }
  }

  @override
  Widget build(BuildContext context) {
    return const Scaffold(
      backgroundColor: Color(0xFFF5F7FB),
      body: Center(child: CircularProgressIndicator(color: Color(0xFF004CAC))),
    );
  }
}
