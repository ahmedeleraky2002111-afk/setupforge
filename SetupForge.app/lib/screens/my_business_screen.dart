import 'package:flutter/material.dart';

import 'setup_screen.dart';

class MyBusinessScreen extends StatelessWidget {
  const MyBusinessScreen({super.key});

  static const Color sfBlue = Color(0xFF004CAC);
  static const Color sfTeal = Color(0xFF009994);
  static const Color bg = Color(0xFFF5F7FB);
  static const Color textDark = Color(0xFF121212);
  static const Color muted = Color(0xFF6C757D);
  static const Color border = Color(0x1A000000);

  @override
  Widget build(BuildContext context) {
    // TODO: replace with real API call to check setup_status
    // For now shows the "no setup yet" state
    // When api_me.php returns setup_status = 'completed' → show BusinessOverviewScreen
    // When setup_status = 'in_progress' → show resume card
    // When no businesses row → show start prompt

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        foregroundColor: textDark,
        title: const Text(
          'My Business',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: Center(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 80,
                height: 80,
                decoration: BoxDecoration(
                  color: sfBlue.withOpacity(0.08),
                  shape: BoxShape.circle,
                ),
                child: const Icon(
                  Icons.storefront_rounded,
                  color: sfBlue,
                  size: 38,
                ),
              ),
              const SizedBox(height: 20),
              const Text(
                "No setup yet",
                style: TextStyle(
                  fontSize: 22,
                  fontWeight: FontWeight.w900,
                  color: textDark,
                ),
              ),
              const SizedBox(height: 10),
              const Text(
                "You haven't started a business setup yet. Go through the wizard to get your personalized package recommendations.",
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 14,
                  height: 1.5,
                  color: muted,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 28),
              SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton(
                  onPressed: () {
                    Navigator.push(
                      context,
                      MaterialPageRoute(builder: (_) => const SetupScreen()),
                    );
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: sfBlue,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(16),
                    ),
                  ),
                  child: const Text(
                    'Start Your Setup',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                      fontSize: 15,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }
}
