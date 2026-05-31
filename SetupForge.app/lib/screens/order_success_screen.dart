import 'package:flutter/material.dart';

class OrderSuccessScreen extends StatelessWidget {
  const OrderSuccessScreen({super.key});

  static const Color sfBlue = Color(0xFF004CAC);
  static const Color sfBg = Color(0xFFF5F7FB);
  static const Color sfText = Color(0xFF111827);
  static const Color sfMuted = Color(0xFF6B7280);

  @override
  Widget build(BuildContext context) {
    final args =
        ModalRoute.of(context)?.settings.arguments as Map<String, dynamic>?;
    final orderId = args?["order_id"];
    final total = args?["total"];

    return Scaffold(
      backgroundColor: sfBg,
      body: SafeArea(
        child: Center(
          child: Padding(
            padding: const EdgeInsets.all(32),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 80,
                  height: 80,
                  decoration: const BoxDecoration(
                    color: Color(0xFFDCFCE7),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(
                    Icons.check_rounded,
                    color: Color(0xFF16A34A),
                    size: 44,
                  ),
                ),
                const SizedBox(height: 20),
                const Text(
                  'Order Placed!',
                  style: TextStyle(
                    fontSize: 24,
                    fontWeight: FontWeight.w900,
                    color: sfText,
                  ),
                ),
                const SizedBox(height: 8),
                if (orderId != null)
                  Text(
                    'Order #$orderId',
                    style: const TextStyle(
                      fontSize: 14,
                      color: sfMuted,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                if (total != null) ...[
                  const SizedBox(height: 4),
                  Text(
                    '${(total as num).toStringAsFixed(0)} EGP',
                    style: const TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w900,
                      color: sfBlue,
                    ),
                  ),
                ],
                const SizedBox(height: 12),
                const Text(
                  'Your order has been placed successfully. We\'ll process it shortly.',
                  textAlign: TextAlign.center,
                  style: TextStyle(color: sfMuted, fontSize: 14, height: 1.5),
                ),
                const SizedBox(height: 32),
                GestureDetector(
                  onTap: () => Navigator.pushNamedAndRemoveUntil(
                    context,
                    '/app-shell',
                    (route) => false,
                  ),
                  child: Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    color: sfBlue,
                    child: const Text(
                      'Back to Home',
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
                    arguments: {'initialIndex': 2},
                  ),
                  child: Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    decoration: BoxDecoration(
                      border: Border.all(color: sfBlue),
                    ),
                    child: const Text(
                      'Continue Shopping',
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
      ),
    );
  }
}
