import 'package:flutter/material.dart';

const Color sfBlue = Color(0xFF004CAC);
const Color sfBg = Color(0xFFF5F7FB);

class SetupSuccessScreen extends StatelessWidget {
  const SetupSuccessScreen({super.key});

  @override
  Widget build(BuildContext context) {
    final args = ModalRoute.of(context)?.settings.arguments as Map?;
    final orderId = args?['order_id'] ?? '';

    return Scaffold(
      backgroundColor: sfBg,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.all(24),
          child: Column(
            mainAxisAlignment: MainAxisAlignment.center,
            children: [
              Container(
                width: 96,
                height: 96,
                decoration: const BoxDecoration(
                  color: Color(0xFFE8F0FE),
                  shape: BoxShape.circle,
                ),
                child: const Icon(Icons.check_rounded, color: sfBlue, size: 52),
              ),
              const SizedBox(height: 28),
              const Text(
                'Setup Complete!',
                style: TextStyle(
                  fontSize: 26,
                  fontWeight: FontWeight.w900,
                  color: Color(0xFF0D1B2A),
                ),
              ),
              const SizedBox(height: 12),
              const Text(
                'Your payment was successful. Your business setup is confirmed and your services are now active.',
                textAlign: TextAlign.center,
                style: TextStyle(
                  fontSize: 14,
                  color: Color(0xFF6B7280),
                  height: 1.6,
                ),
              ),
              if (orderId != '') ...[
                const SizedBox(height: 16),
                Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 16,
                    vertical: 10,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    border: Border.all(color: Color(0xFFE5E7EB)),
                  ),
                  child: Text(
                    'Order #$orderId',
                    style: const TextStyle(
                      fontSize: 13,
                      color: Color(0xFF6B7280),
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                ),
              ],
              const SizedBox(height: 48),
              GestureDetector(
                onTap: () => Navigator.pushNamedAndRemoveUntil(
                  context,
                  '/app-shell',
                  (route) => false,
                  arguments: {'initialIndex': 3},
                ),
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  color: sfBlue,
                  child: const Text(
                    'Go to My Services',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                      fontSize: 15,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 12),
              GestureDetector(
                onTap: () => Navigator.pushNamedAndRemoveUntil(
                  context,
                  '/app-shell',
                  (route) => false,
                ),
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.symmetric(vertical: 16),
                  decoration: BoxDecoration(border: Border.all(color: sfBlue)),
                  child: const Text(
                    'Back to Home',
                    textAlign: TextAlign.center,
                    style: TextStyle(
                      color: sfBlue,
                      fontWeight: FontWeight.w800,
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
