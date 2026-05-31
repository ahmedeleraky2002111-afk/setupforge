import 'package:flutter/material.dart';

class ServicesInfoScreen extends StatelessWidget {
  final String setupState; // "none", "in_progress", "completed"
  const ServicesInfoScreen({super.key, required this.setupState});

  static const Color sfBlue = Color(0xFF004CAC);
  static const Color sfBg = Color(0xFFF5F7FB);
  static const Color sfText = Color(0xFF111827);
  static const Color sfMuted = Color(0xFF6B7280);

  @override
  Widget build(BuildContext context) {
    final btnText = setupState == "in_progress"
        ? "Resume Your Setup"
        : "Start Your Setup";

    return Scaffold(
      backgroundColor: sfBg,
      body: CustomScrollView(
        slivers: [
          SliverAppBar(
            backgroundColor: sfBlue,
            pinned: true,
            automaticallyImplyLeading: false,
            expandedHeight: 160,
            flexibleSpace: FlexibleSpaceBar(
              background: Container(
                color: sfBlue,
                padding: const EdgeInsets.fromLTRB(20, 56, 20, 20),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    const Text(
                      'Services',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 24,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Text(
                      setupState == "none" || setupState == "in_progress"
                          ? 'Complete your setup to unlock all services'
                          : 'Your services dashboard',
                      style: TextStyle(
                        color: Colors.white.withOpacity(0.8),
                        fontSize: 13.5,
                        fontWeight: FontWeight.w500,
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),

          SliverPadding(
            padding: const EdgeInsets.all(16),
            sliver: SliverList(
              delegate: SliverChildListDelegate([
                // Why not showing banner
                Container(
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: const Color(0xFFEFF6FF),
                    border: Border.all(color: const Color(0xFFBFDBFE)),
                  ),
                  child: Row(
                    children: [
                      const Icon(Icons.info_outline, color: sfBlue, size: 20),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Text(
                          setupState == "in_progress"
                              ? 'You\'re almost there! Complete your setup and payment to unlock staffing, installation, finishing, and advertising services.'
                              : 'Services become available after you complete your business setup. Start your setup to get access.',
                          style: const TextStyle(
                            fontSize: 13,
                            color: sfBlue,
                            fontWeight: FontWeight.w600,
                            height: 1.4,
                          ),
                        ),
                      ),
                    ],
                  ),
                ),

                const SizedBox(height: 20),

                // CTA button
                GestureDetector(
                  onTap: () {
                    if (setupState == "in_progress") {
                      Navigator.pushNamed(context, '/setup');
                    } else {
                      Navigator.pushNamed(context, '/setup');
                    }
                  },
                  child: Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(vertical: 14),
                    color: sfBlue,
                    child: Text(
                      btnText,
                      textAlign: TextAlign.center,
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                        fontSize: 15,
                      ),
                    ),
                  ),
                ),

                const SizedBox(height: 28),

                // What you'll unlock
                const Text(
                  'What you\'ll unlock',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                    color: sfText,
                  ),
                ),
                const SizedBox(height: 14),

                _serviceCard(
                  icon: Icons.people_outline_rounded,
                  title: 'Staffing',
                  desc:
                      'Post jobs and hire skilled workers — chefs, waiters, cashiers, and more. Review applicants and manage your team from one place.',
                  color: const Color(0xFF3B82F6),
                ),
                const SizedBox(height: 12),
                _serviceCard(
                  icon: Icons.build_outlined,
                  title: 'Installation',
                  desc:
                      'Get professional companies to install your kitchen equipment, POS systems, AC units, electrical wiring, and network setup.',
                  color: const Color(0xFFF97316),
                ),
                const SizedBox(height: 12),
                _serviceCard(
                  icon: Icons.brush_outlined,
                  title: 'Finishing',
                  desc:
                      'Find companies for painting, flooring, gypsum ceilings, interior decor, and facade work — all matched to your space.',
                  color: const Color(0xFF22C55E),
                ),
                const SizedBox(height: 12),
                _serviceCard(
                  icon: Icons.campaign_outlined,
                  title: 'Advertising',
                  desc:
                      'Connect with advertising companies to promote your business before and after launch. Boost visibility and attract customers.',
                  color: const Color(0xFF8B5CF6),
                ),

                const SizedBox(height: 28),

                // How it works
                const Text(
                  'How it works',
                  style: TextStyle(
                    fontSize: 16,
                    fontWeight: FontWeight.w900,
                    color: sfText,
                  ),
                ),
                const SizedBox(height: 14),

                _stepCard(
                  '01',
                  'Complete your setup',
                  'Go through the wizard, select your services, and place your order.',
                ),
                const SizedBox(height: 10),
                _stepCard(
                  '02',
                  'Select your services',
                  'Choose which services you need — staffing, installation, finishing, or advertising.',
                ),
                const SizedBox(height: 10),
                _stepCard(
                  '03',
                  'Get matched',
                  'We connect you with the right companies and workers based on your business needs.',
                ),
                const SizedBox(height: 10),
                _stepCard(
                  '04',
                  'Manage everything here',
                  'Track applicants, accept quotes, schedule installations, and rate companies.',
                ),

                const SizedBox(height: 32),
              ]),
            ),
          ),
        ],
      ),
    );
  }

  Widget _serviceCard({
    required IconData icon,
    required String title,
    required String desc,
    required Color color,
  }) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border(
          top: const BorderSide(color: Color(0xFFE5E7EB)),
          right: const BorderSide(color: Color(0xFFE5E7EB)),
          bottom: const BorderSide(color: Color(0xFFE5E7EB)),
          left: BorderSide(color: color, width: 4),
        ),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 44,
            height: 44,
            decoration: BoxDecoration(color: color.withOpacity(0.1)),
            child: Icon(icon, color: color, size: 22),
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
                    color: sfText,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  desc,
                  style: const TextStyle(
                    fontSize: 12.5,
                    color: sfMuted,
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _stepCard(String number, String title, String desc) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 36,
            height: 36,
            decoration: const BoxDecoration(color: Color(0xFFEFF6FF)),
            child: Center(
              child: Text(
                number,
                style: const TextStyle(
                  color: sfBlue,
                  fontWeight: FontWeight.w900,
                  fontSize: 12,
                ),
              ),
            ),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w800,
                    color: sfText,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  desc,
                  style: const TextStyle(
                    fontSize: 12.5,
                    color: sfMuted,
                    height: 1.4,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }
}
