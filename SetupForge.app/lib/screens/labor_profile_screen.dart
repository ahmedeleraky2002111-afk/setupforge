import 'package:flutter/material.dart';
import '../services/api_service.dart';
import 'labor_edit_profile_screen.dart';

class LaborProfileScreen extends StatefulWidget {
  const LaborProfileScreen({super.key});

  @override
  State<LaborProfileScreen> createState() => _LaborProfileScreenState();
}

class _LaborProfileScreenState extends State<LaborProfileScreen> {
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
    final res = await api.getLaborProfile();
    if (mounted)
      setState(() {
        _data = res;
        _loading = false;
      });
  }

  Future<void> _logout() async {
    await api.logout();
    if (!mounted) return;
    Navigator.pushNamedAndRemoveUntil(context, '/login', (route) => false);
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

    final name = _data["name"] ?? "—";
    final email = _data["email"] ?? "—";
    final phone = _data["phone"] ?? "—";
    final skills = _data["skills"] ?? "No skills added yet.";
    final providerType = _data["provider_type"] ?? "labor";
    final avgRating =
        (double.tryParse(_data["avg_rating"]?.toString() ?? "0") ?? 0);
    final balance = _data["balance"] ?? "0.00";
    final laborRole = _data["labor_role"] ?? "—";
    final hourlyRate = _data["hourly_rate"] ?? 0;
    final availability = _data["availability_status"] ?? "available";
    final totalEarnings = _data["total_earnings"] ?? "0.00";
    final activeJobs = _data["active_jobs"] ?? 0;
    final initials = name.isNotEmpty ? name[0].toUpperCase() : "?";

    return Scaffold(
      backgroundColor: sfBg,
      body: RefreshIndicator(
        color: sfBlue,
        onRefresh: _load,
        child: CustomScrollView(
          slivers: [
            SliverAppBar(
              backgroundColor: sfBlue,
              expandedHeight: 200,
              pinned: true,
              automaticallyImplyLeading: false,
              actions: [
                IconButton(
                  onPressed: _logout,
                  icon: const Icon(Icons.logout_rounded, color: Colors.white),
                  tooltip: 'Logout',
                ),
              ],
              flexibleSpace: FlexibleSpaceBar(
                background: Container(
                  color: sfBlue,
                  padding: const EdgeInsets.fromLTRB(20, 60, 20, 20),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      // Avatar
                      Container(
                        width: 72,
                        height: 72,
                        decoration: BoxDecoration(
                          color: Colors.white.withOpacity(0.2),
                          shape: BoxShape.circle,
                          border: Border.all(
                            color: Colors.white.withOpacity(0.4),
                            width: 2,
                          ),
                        ),
                        child: Center(
                          child: Text(
                            initials,
                            style: const TextStyle(
                              color: Colors.white,
                              fontSize: 28,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                      ),
                      const SizedBox(height: 10),
                      Text(
                        name,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 18,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          _chip(
                            providerType[0].toUpperCase() +
                                providerType.substring(1),
                          ),
                          const SizedBox(width: 8),
                          _chip(
                            availability[0].toUpperCase() +
                                availability.substring(1),
                          ),
                        ],
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
                  // Stats row
                  Row(
                    children: [
                      Expanded(
                        child: _statCard(
                          activeJobs.toString(),
                          'Active Jobs',
                          Icons.work_outline_rounded,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _statCard(
                          avgRating.toStringAsFixed(1),
                          'Rating',
                          Icons.star_outline_rounded,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _statCard(
                          '$totalEarnings EGP',
                          'Earnings',
                          Icons.payments_outlined,
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 16),

                  // Info card
                  _infoCard([
                    _infoSection('Personal Information', [
                      _infoRow(Icons.person_outline, 'Name', name),
                      _infoRow(Icons.email_outlined, 'Email', email),
                      _infoRow(
                        Icons.phone_outlined,
                        'Phone',
                        phone.isNotEmpty ? phone : '—',
                      ),
                    ]),
                    const Divider(height: 24, color: Color(0xFFF3F4F6)),
                    _infoSection('Work Profile', [
                      _infoRow(Icons.badge_outlined, 'Role', laborRole),
                      _infoRow(
                        Icons.payments_outlined,
                        'Hourly Rate',
                        '$hourlyRate EGP/hr',
                      ),
                      _infoRow(
                        Icons.account_balance_wallet_outlined,
                        'Balance',
                        '$balance EGP',
                      ),
                    ]),
                    const Divider(height: 24, color: Color(0xFFF3F4F6)),
                    _infoSection('Skills', []),
                    Text(
                      skills.isNotEmpty ? skills : 'No skills added yet.',
                      style: TextStyle(
                        fontSize: 13.5,
                        color: skills.isNotEmpty ? sfText : sfMuted,
                        height: 1.5,
                      ),
                    ),
                  ]),

                  const SizedBox(height: 16),

                  // Edit profile button
                  SizedBox(
                    width: double.infinity,
                    height: 52,
                    child: ElevatedButton.icon(
                      onPressed: () async {
                        await Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => LaborEditProfileScreen(data: _data),
                          ),
                        );
                        _load(); // Refresh after edit
                      },
                      icon: const Icon(
                        Icons.edit_outlined,
                        size: 18,
                        color: Colors.white,
                      ),
                      label: const Text(
                        'Edit Profile',
                        style: TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w800,
                          fontSize: 15,
                        ),
                      ),
                      style: ElevatedButton.styleFrom(
                        backgroundColor: sfBlue,
                        elevation: 0,
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                    ),
                  ),

                  const SizedBox(height: 12),

                  // Logout button
                  SizedBox(
                    width: double.infinity,
                    height: 52,
                    child: OutlinedButton.icon(
                      onPressed: _logout,
                      icon: const Icon(Icons.logout_rounded, size: 18),
                      label: const Text(
                        'Logout',
                        style: TextStyle(
                          fontWeight: FontWeight.w800,
                          fontSize: 15,
                        ),
                      ),
                      style: OutlinedButton.styleFrom(
                        foregroundColor: Colors.red,
                        side: const BorderSide(color: Colors.red),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(14),
                        ),
                      ),
                    ),
                  ),

                  const SizedBox(height: 32),
                ]),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _chip(String label) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.15),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withOpacity(0.25)),
      ),
      child: Text(
        label,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 11.5,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  Widget _statCard(String value, String label, IconData icon) {
    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE5E7EB)),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.03),
            blurRadius: 8,
            offset: const Offset(0, 2),
          ),
        ],
      ),
      child: Column(
        children: [
          Icon(icon, color: sfBlue, size: 18),
          const SizedBox(height: 6),
          Text(
            value,
            style: const TextStyle(
              fontSize: 14,
              fontWeight: FontWeight.w900,
              color: Color(0xFF111827),
            ),
            textAlign: TextAlign.center,
            maxLines: 1,
            overflow: TextOverflow.ellipsis,
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: const TextStyle(fontSize: 10.5, color: sfMuted),
            textAlign: TextAlign.center,
          ),
        ],
      ),
    );
  }

  Widget _infoCard(List<Widget> children) {
    return Container(
      padding: const EdgeInsets.all(18),
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
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: children,
      ),
    );
  }

  Widget _infoSection(String title, List<Widget> rows) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontSize: 11,
            fontWeight: FontWeight.w800,
            color: sfBlue,
            letterSpacing: 0.5,
          ),
        ),
        const SizedBox(height: 10),
        ...rows,
      ],
    );
  }

  Widget _infoRow(IconData icon, String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: [
          Icon(icon, size: 15, color: sfMuted),
          const SizedBox(width: 8),
          Text(
            '$label: ',
            style: const TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w700,
              color: sfMuted,
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(
                fontSize: 13,
                fontWeight: FontWeight.w600,
                color: Color(0xFF111827),
              ),
              overflow: TextOverflow.ellipsis,
            ),
          ),
        ],
      ),
    );
  }
}
