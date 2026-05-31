import 'package:flutter/material.dart';

class RoleSelectScreen extends StatelessWidget {
  const RoleSelectScreen({super.key});

  static const Color sfBlue = Color(0xFF004CAC);
  static const Color sfBg = Color(0xFFF5F7FB);
  static const Color sfText = Color(0xFF111827);
  static const Color sfMuted = Color(0xFF6B7280);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: sfBg,
      appBar: AppBar(
        backgroundColor: sfBlue,
        foregroundColor: Colors.white,
        title: const Text(
          'Join SetupForge',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        elevation: 0,
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 32),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.center,
            children: [
              const Text(
                'Who are you joining as?',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 24,
                  fontWeight: FontWeight.w900,
                  color: sfText,
                ),
              ),
              const SizedBox(height: 10),
              const Text(
                'Choose your role so we can tailor your experience on SetupForge.',
                textAlign: TextAlign.center,
                style: TextStyle(fontSize: 14, color: sfMuted, height: 1.5),
              ),
              const SizedBox(height: 32),

              _roleCard(
                context,
                icon: Icons.storefront_rounded,
                title: 'Business Owner',
                desc: 'Setting up a restaurant or commercial space',
                route: '/signup',
              ),
              const SizedBox(height: 14),
              _roleCard(
                context,
                icon: Icons.engineering_rounded,
                title: 'Labor Worker',
                desc: 'Offering skilled work — chefs, waiters, staff',
                route: '/labor-signup',
              ),
              const SizedBox(height: 14),
              _roleCard(
                context,
                icon: Icons.explore_rounded,
                title: 'Just Browsing',
                desc: 'Exploring products and services as a customer',
                route: '/signup',
              ),

              const SizedBox(height: 32),
              Row(
                mainAxisAlignment: MainAxisAlignment.center,
                children: [
                  const Text(
                    'Already have an account? ',
                    style: TextStyle(color: sfMuted, fontSize: 14),
                  ),
                  GestureDetector(
                    onTap: () =>
                        Navigator.pushReplacementNamed(context, '/login'),
                    child: const Text(
                      'Sign in',
                      style: TextStyle(
                        color: sfBlue,
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _roleCard(
    BuildContext context, {
    required IconData icon,
    required String title,
    required String desc,
    required String route,
  }) {
    return GestureDetector(
      onTap: () => Navigator.pushNamed(context, route),
      child: Container(
        width: double.infinity,
        padding: const EdgeInsets.all(20),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFFE5E7EB)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.04),
              blurRadius: 12,
              offset: const Offset(0, 4),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              width: 52,
              height: 52,
              decoration: BoxDecoration(
                color: const Color(0xFF004CAC).withOpacity(0.08),
                borderRadius: BorderRadius.circular(14),
              ),
              child: Icon(icon, color: sfBlue, size: 26),
            ),
            const SizedBox(width: 16),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w800,
                      color: sfText,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    desc,
                    style: const TextStyle(
                      fontSize: 13,
                      color: sfMuted,
                      height: 1.4,
                    ),
                  ),
                ],
              ),
            ),
            const Icon(
              Icons.arrow_forward_ios_rounded,
              size: 16,
              color: sfMuted,
            ),
          ],
        ),
      ),
    );
  }
}
