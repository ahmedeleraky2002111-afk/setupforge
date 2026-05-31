import 'package:flutter/material.dart';
import '../services/api_service.dart';

class HomeScreen extends StatefulWidget {
  const HomeScreen({super.key});

  @override
  State<HomeScreen> createState() => _HomeScreenState();
}

class _HomeScreenState extends State<HomeScreen> {
  static const Color sfBlue = Color(0xFF004CAC);
  static const Color sfBg = Color(0xFFF5F7FB);
  static const Color sfText = Color(0xFF111827);
  static const Color sfMuted = Color(0xFF6B7280);

  final api = ApiService();
  bool _loading = true;
  Map<String, dynamic> _data = {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    final res = await api.getHomeData();
    if (mounted)
      setState(() {
        _data = res;
        _loading = false;
      });
  }

  void _handleHeroBtn(String route) {
    switch (route) {
      case 'my-business':
        Navigator.pushNamed(context, '/my-business');
        break;
      case 'packages':
        Navigator.pushNamed(context, '/packages');
        break;
      case 'setup':
      default:
        Navigator.pushNamed(context, '/service-select');
        break;
    }
  }

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(
        backgroundColor: sfBg,
        body: Center(child: CircularProgressIndicator(color: sfBlue)),
      );
    }

    if (_data["ok"] != true) {
      return Scaffold(
        backgroundColor: sfBg,
        body: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: [
              const Icon(Icons.error_outline, color: sfMuted, size: 48),
              const SizedBox(height: 12),
              Text(
                _data["error"]?.toString() ?? "Failed to load",
                style: const TextStyle(color: sfMuted),
              ),
              const SizedBox(height: 16),
              ElevatedButton(
                onPressed: _load,
                style: ElevatedButton.styleFrom(backgroundColor: sfBlue),
                child: const Text(
                  "Retry",
                  style: TextStyle(color: Colors.white),
                ),
              ),
            ],
          ),
        ),
      );
    }

    final name = _data["name"] ?? "";
    final btnText = _data["btn_text"] ?? "Start Your Setup";
    final btnRoute = _data["btn_route"] ?? "setup";
    final setupComplete = _data["setup_complete"] == true;

    return Scaffold(
      backgroundColor: sfBg,
      body: RefreshIndicator(
        color: sfBlue,
        onRefresh: _load,
        child: CustomScrollView(
          slivers: [
            // Top bar
            SliverAppBar(
              backgroundColor: Colors.white,
              elevation: 0,
              pinned: true,
              automaticallyImplyLeading: false,
              titleSpacing: 0,
              title: Padding(
                padding: const EdgeInsets.symmetric(horizontal: 18),
                child: Row(
                  children: [
                    // Logo
                    Container(
                      width: 32,
                      height: 32,
                      decoration: BoxDecoration(
                        color: sfBlue,
                        borderRadius: BorderRadius.circular(8),
                      ),
                      child: const Icon(
                        Icons.bolt_rounded,
                        color: Colors.white,
                        size: 20,
                      ),
                    ),
                    const SizedBox(width: 10),
                    const Text(
                      'SetupForge',
                      style: TextStyle(
                        color: Color(0xFF111827),
                        fontSize: 17,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const Spacer(),
                    // Cart icon
                    IconButton(
                      onPressed: () => Navigator.pushNamed(context, '/cart'),
                      icon: const Icon(
                        Icons.shopping_cart_outlined,
                        color: Color(0xFF111827),
                        size: 24,
                      ),
                      padding: EdgeInsets.zero,
                    ),
                  ],
                ),
              ),
              bottom: PreferredSize(
                preferredSize: const Size.fromHeight(1),
                child: Container(height: 1, color: const Color(0xFFE5E7EB)),
              ),
            ),

            SliverPadding(
              padding: const EdgeInsets.fromLTRB(18, 20, 18, 32),
              sliver: SliverList(
                delegate: SliverChildListDelegate([
                  // Welcome text
                  Text(
                    name.isNotEmpty
                        ? 'Welcome back, $name 👋'
                        : 'Welcome to SetupForge',
                    style: const TextStyle(
                      fontSize: 13.5,
                      fontWeight: FontWeight.w600,
                      color: sfMuted,
                    ),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Build your business,\nfaster.',
                    style: TextStyle(
                      fontSize: 26,
                      height: 1.2,
                      fontWeight: FontWeight.w900,
                      color: sfText,
                    ),
                  ),

                  const SizedBox(height: 20),

                  // Hero card
                  Container(
                    width: double.infinity,
                    padding: const EdgeInsets.all(22),
                    decoration: BoxDecoration(
                      color: sfBlue,
                      borderRadius: BorderRadius.circular(20),
                      boxShadow: [
                        BoxShadow(
                          color: sfBlue.withOpacity(0.2),
                          blurRadius: 20,
                          offset: const Offset(0, 8),
                        ),
                      ],
                    ),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        const Text(
                          'We build your setup.\nYou build your business.',
                          style: TextStyle(
                            fontSize: 20,
                            height: 1.3,
                            fontWeight: FontWeight.w900,
                            color: Colors.white,
                          ),
                        ),
                        const SizedBox(height: 10),
                        const Text(
                          'Buy the right equipment and we\'ll deliver, install, and prepare your place so you can open faster.',
                          style: TextStyle(
                            fontSize: 13,
                            height: 1.5,
                            color: Colors.white70,
                            fontWeight: FontWeight.w500,
                          ),
                        ),
                        const SizedBox(height: 18),
                        SizedBox(
                          width: double.infinity,
                          height: 48,
                          child: ElevatedButton(
                            onPressed: () => _handleHeroBtn(btnRoute),
                            style: ElevatedButton.styleFrom(
                              backgroundColor: Colors.white,
                              foregroundColor: sfBlue,
                              elevation: 0,
                              shape: RoundedRectangleBorder(
                                borderRadius: BorderRadius.circular(12),
                              ),
                            ),
                            child: Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Text(
                                  btnText,
                                  style: const TextStyle(
                                    fontWeight: FontWeight.w900,
                                    fontSize: 14.5,
                                  ),
                                ),
                                const SizedBox(width: 8),
                                const Icon(
                                  Icons.arrow_forward_rounded,
                                  size: 16,
                                ),
                              ],
                            ),
                          ),
                        ),
                      ],
                    ),
                  ),

                  const SizedBox(height: 28),

                  // Our Services
                  const Text(
                    'Our Services',
                    style: TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w900,
                      color: sfText,
                    ),
                  ),
                  const SizedBox(height: 4),
                  const Text(
                    'Everything you need to plan, build, and operate your setup.',
                    style: TextStyle(fontSize: 13, color: sfMuted, height: 1.4),
                  ),
                  const SizedBox(height: 14),

                  _serviceCard(
                    icon: Icons.inventory_2_outlined,
                    title: 'Smart Setup Packages',
                    desc: 'Generate tailored setup packages...',
                    onTap: () => Navigator.pushNamed(
                      context,
                      '/service-select',
                      arguments: {'preselect': 'equipment'},
                    ),
                  ),
                  const SizedBox(height: 12),
                  _serviceCard(
                    icon: Icons.build_outlined,
                    title: 'Installation & Setup',
                    desc: 'Get professional installation...',
                    onTap: () => Navigator.pushNamed(
                      context,
                      '/service-select',
                      arguments: {'preselect': 'installation'},
                    ),
                  ),
                  const SizedBox(height: 12),
                  _serviceCard(
                    icon: Icons.people_outline_rounded,
                    title: 'Staffing',
                    desc: 'Hire workers to support...',
                    onTap: () => Navigator.pushNamed(
                      context,
                      '/service-select',
                      arguments: {'preselect': 'staff'},
                    ),
                  ),

                  // How it works — only if setup not complete
                  if (!setupComplete) ...[
                    const SizedBox(height: 28),
                    const Text(
                      'How it works',
                      style: TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                        color: sfText,
                      ),
                    ),
                    const SizedBox(height: 14),
                    _stepTile(
                      '01',
                      'Tell us about your business',
                      'Type, size, budget, and what services you need.',
                    ),
                    const SizedBox(height: 10),
                    _stepTile(
                      '02',
                      'Get smart recommendations',
                      'We generate packages tailored to your exact needs.',
                    ),
                    const SizedBox(height: 10),
                    _stepTile(
                      '03',
                      'Place your order',
                      'Review, confirm, and pay — all from your phone.',
                    ),
                  ],
                ]),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _serviceCard({
    required IconData icon,
    required String title,
    required String desc,
    required VoidCallback onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          borderRadius: BorderRadius.circular(16),
          border: Border.all(color: const Color(0xFFE5E7EB)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.03),
              blurRadius: 8,
              offset: const Offset(0, 2),
            ),
          ],
        ),
        child: Row(
          children: [
            Container(
              width: 48,
              height: 48,
              decoration: BoxDecoration(
                color: sfBlue.withOpacity(0.08),
                borderRadius: BorderRadius.circular(12),
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
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],
              ),
            ),
            const Icon(
              Icons.arrow_forward_ios_rounded,
              size: 14,
              color: sfMuted,
            ),
          ],
        ),
      ),
    );
  }

  Widget _stepTile(String number, String title, String subtitle) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Container(
            width: 40,
            height: 40,
            decoration: BoxDecoration(
              color: sfBlue.withOpacity(0.08),
              borderRadius: BorderRadius.circular(10),
            ),
            child: Center(
              child: Text(
                number,
                style: const TextStyle(
                  color: sfBlue,
                  fontWeight: FontWeight.w900,
                  fontSize: 13,
                ),
              ),
            ),
          ),
          const SizedBox(width: 14),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  title,
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: sfText,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  subtitle,
                  style: const TextStyle(
                    fontSize: 12.5,
                    height: 1.4,
                    color: sfMuted,
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
