import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../services/api_service.dart';
import 'app_shell.dart';

class SignupScreen extends StatefulWidget {
  const SignupScreen({super.key});

  @override
  State<SignupScreen> createState() => _SignupScreenState();
}

class _SignupScreenState extends State<SignupScreen> {
  static const Color sfBlue = Color(0xFF004CAC);
  static const Color sfBg = Color(0xFFF5F7FB);
  static const Color sfText = Color(0xFF121212);
  static const Color sfMuted = Color(0xFF6C757D);

  final api = ApiService();

  final nameC = TextEditingController();
  final emailC = TextEditingController();
  final phoneC = TextEditingController();
  final passC = TextEditingController();

  bool loading = false;
  bool obscurePass = true;

  // Set by setup_screen.dart before pushing to this screen
  // If true → user came from end of wizard → signup as business
  // If false → user signed up directly → signup as customer
  bool comingFromWizard = false;

  @override
  void initState() {
    super.initState();
    _checkWizardIntent();
  }

  Future<void> _checkWizardIntent() async {
    final prefs = await SharedPreferences.getInstance();
    final intent = prefs.getString('signup_intent');
    if (mounted) {
      setState(() => comingFromWizard = intent == 'business');
    }
  }

  Future<void> _signup() async {
    FocusScope.of(context).unfocus();
    setState(() => loading = true);

    try {
      final userType = comingFromWizard ? 'business' : 'customer';

      final res = await api.signupFull(
        name: nameC.text.trim(),
        email: emailC.text.trim(),
        phone: phoneC.text.trim(),
        password: passC.text,
        userType: userType,
      );

      if (!mounted) return;

      if (res["ok"] == true) {
        // Clear the signup intent
        final prefs = await SharedPreferences.getInstance();
        await prefs.remove('signup_intent');

        if (!mounted) return;

        if (comingFromWizard) {
          // Came from wizard → go to packages
          Navigator.pushNamedAndRemoveUntil(
            context,
            '/packages',
            (route) => false,
          );
        } else {
          // Regular signup → go to app shell
          Navigator.pushAndRemoveUntil(
            context,
            MaterialPageRoute(builder: (_) => const AppShell(initialIndex: 0)),
            (route) => false,
          );
        }
      } else {
        final msg = (res["error"] ?? "Signup failed").toString();
        ScaffoldMessenger.of(
          context,
        ).showSnackBar(SnackBar(content: Text(msg)));
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text("Error: $e")));
    } finally {
      if (mounted) setState(() => loading = false);
    }
  }

  @override
  void dispose() {
    nameC.dispose();
    emailC.dispose();
    phoneC.dispose();
    passC.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: sfBg,
      body: SafeArea(
        child: Center(
          child: SingleChildScrollView(
            padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 24),
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 460),
              child: Container(
                padding: const EdgeInsets.all(24),
                decoration: BoxDecoration(
                  color: Colors.white,
                  borderRadius: BorderRadius.circular(28),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withOpacity(0.06),
                      blurRadius: 24,
                      offset: const Offset(0, 10),
                    ),
                  ],
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Center(
                      child: Image.asset(
                        'assets/logo.png',
                        height: 108,
                        fit: BoxFit.contain,
                      ),
                    ),
                    const SizedBox(height: 18),

                    const Center(
                      child: Text(
                        "Create your account",
                        style: TextStyle(
                          fontSize: 26,
                          fontWeight: FontWeight.w900,
                          color: sfText,
                        ),
                      ),
                    ),

                    const SizedBox(height: 8),

                    Center(
                      child: Text(
                        comingFromWizard
                            ? "Sign up to save your setup and view your generated packages."
                            : "Create an account to get started with SetupForge.",
                        textAlign: TextAlign.center,
                        style: const TextStyle(
                          fontSize: 13.5,
                          height: 1.5,
                          color: sfMuted,
                        ),
                      ),
                    ),

                    const SizedBox(height: 28),

                    _field(
                      controller: nameC,
                      hint: "Full Name",
                      prefix: const Icon(Icons.person_outline),
                    ),
                    const SizedBox(height: 16),
                    _field(
                      controller: emailC,
                      hint: "Email Address",
                      keyboardType: TextInputType.emailAddress,
                      prefix: const Icon(Icons.email_outlined),
                    ),
                    const SizedBox(height: 16),
                    _field(
                      controller: phoneC,
                      hint: "Phone Number (optional)",
                      keyboardType: TextInputType.phone,
                      prefix: const Icon(Icons.phone_outlined),
                    ),
                    const SizedBox(height: 16),
                    _field(
                      controller: passC,
                      hint: "Password",
                      obscureText: obscurePass,
                      prefix: const Icon(Icons.lock_outline),
                      suffix: IconButton(
                        onPressed: () =>
                            setState(() => obscurePass = !obscurePass),
                        icon: Icon(
                          obscurePass ? Icons.visibility_off : Icons.visibility,
                        ),
                      ),
                    ),

                    const SizedBox(height: 24),

                    SizedBox(
                      width: double.infinity,
                      height: 54,
                      child: ElevatedButton(
                        onPressed: loading ? null : _signup,
                        style: ElevatedButton.styleFrom(
                          backgroundColor: sfBlue,
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(16),
                          ),
                        ),
                        child: loading
                            ? const CircularProgressIndicator(
                                color: Colors.white,
                              )
                            : Text(
                                comingFromWizard
                                    ? "Create Account & View Packages"
                                    : "Create Account",
                                style: const TextStyle(
                                  fontWeight: FontWeight.w800,
                                  color: Colors.white,
                                ),
                              ),
                      ),
                    ),

                    const SizedBox(height: 16),

                    SizedBox(
                      width: double.infinity,
                      height: 52,
                      child: OutlinedButton(
                        onPressed: () =>
                            Navigator.pushReplacementNamed(context, '/login'),
                        child: const Text("Already have an account? Sign In"),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ),
      ),
    );
  }

  Widget _field({
    required TextEditingController controller,
    required String hint,
    TextInputType? keyboardType,
    bool obscureText = false,
    Widget? prefix,
    Widget? suffix,
  }) {
    return SizedBox(
      height: 56,
      child: TextField(
        controller: controller,
        keyboardType: keyboardType,
        obscureText: obscureText,
        decoration: InputDecoration(
          hintText: hint,
          filled: true,
          fillColor: const Color(0xFFF8FAFF),
          prefixIcon: prefix,
          suffixIcon: suffix,
          border: OutlineInputBorder(borderRadius: BorderRadius.circular(16)),
        ),
      ),
    );
  }
}
