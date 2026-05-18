import 'package:flutter/material.dart';

class ServicesScreen extends StatelessWidget {
  const ServicesScreen({super.key});

  static const Color sfBlue = Color(0xFF004CAC);
  static const Color sfTeal = Color(0xFF009994);
  static const Color bg = Color(0xFFF5F7FB);
  static const Color textDark = Color(0xFF121212);
  static const Color muted = Color(0xFF6C757D);
  static const Color border = Color(0x1A000000);

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: bg,
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(18, 12, 18, 24),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Services',
                style: TextStyle(
                  fontSize: 26,
                  fontWeight: FontWeight.w900,
                  color: textDark,
                ),
              ),
              const SizedBox(height: 4),
              const Text(
                'Installation, hiring, and business services.',
                style: TextStyle(
                  fontSize: 13.5,
                  color: muted,
                  fontWeight: FontWeight.w500,
                ),
              ),
              const SizedBox(height: 28),

              // Preview tiles — what's coming
              _serviceTile(
                icon: Icons.electrical_services_rounded,
                title: 'Installation Companies',
                subtitle: 'AC, electrical, kitchen, POS, and network setup.',
              ),
              const SizedBox(height: 12),
              _serviceTile(
                icon: Icons.people_rounded,
                title: 'Hire Staff',
                subtitle: 'Waiters, chefs, cashiers, security, and more.',
              ),
              const SizedBox(height: 12),
              _serviceTile(
                icon: Icons.support_agent_rounded,
                title: 'Support & Maintenance',
                subtitle: 'Post-setup support and equipment maintenance.',
              ),

              const Spacer(),
              Center(
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 20,
                    vertical: 12,
                  ),
                  decoration: BoxDecoration(
                    color: sfBlue.withOpacity(0.08),
                    borderRadius: BorderRadius.circular(14),
                  ),
                  child: const Text(
                    'Full services coming soon',
                    style: TextStyle(
                      fontSize: 13.5,
                      fontWeight: FontWeight.w700,
                      color: sfBlue,
                    ),
                  ),
                ),
              ),
              const Spacer(),
            ],
          ),
        ),
      ),
    );
  }

  Widget _serviceTile({
    required IconData icon,
    required String title,
    required String subtitle,
  }) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: border),
      ),
      child: Row(
        children: [
          Container(
            width: 46,
            height: 46,
            decoration: BoxDecoration(
              color: sfBlue.withOpacity(0.08),
              borderRadius: BorderRadius.circular(13),
            ),
            child: Icon(icon, color: sfBlue, size: 22),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 14.5,
                    fontWeight: FontWeight.w800,
                    color: textDark,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  subtitle,
                  style: const TextStyle(
                    fontSize: 12.5,
                    height: 1.4,
                    color: muted,
                    fontWeight: FontWeight.w500,
                  ),
                ),
              ],
            ),
          ),
          const Icon(Icons.lock_outline_rounded, color: muted, size: 18),
        ],
      ),
    );
  }
}
