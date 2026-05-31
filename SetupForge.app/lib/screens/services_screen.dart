import 'package:flutter/material.dart';
import '../services/api_service.dart';
import 'dart:convert';
import 'package:http/http.dart' as http;

class ServicesScreen extends StatefulWidget {
  const ServicesScreen({super.key});

  @override
  State<ServicesScreen> createState() => _ServicesScreenState();
}

class _ServicesScreenState extends State<ServicesScreen>
    with TickerProviderStateMixin {
  static const Color sfBlue = Color(0xFF004CAC);
  static const Color sfBg = Color(0xFFF5F7FB);
  static const Color sfText = Color(0xFF111827);
  static const Color sfMuted = Color(0xFF6B7280);

  final api = ApiService();
  bool _loading = true;
  Map<String, dynamic> _data = {};
  TabController? _tabController;

  // Labor state
  int _selectedRoleIndex = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _tabController?.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    final res = await api.getServiceJobs();
    if (!mounted) return;

    // Build tab controller based on visible tabs
    final showLabor = res["show_labor"] == true;
    final showInstallation = res["show_installation"] == true;
    final showFinishing = res["show_finishing"] == true;
    final showAdvertising = res["show_advertising"] == true;

    final tabCount = [
      showLabor,
      showInstallation,
      showFinishing,
      showAdvertising,
    ].where((v) => v).length;

    _tabController?.dispose();
    _tabController = TabController(length: tabCount, vsync: this);

    setState(() {
      _data = res;
      _loading = false;
      _selectedRoleIndex = 0;
    });
  }

  List<String> _buildTabLabels() {
    final labels = <String>[];
    if (_data["show_labor"] == true) labels.add('Labor');
    if (_data["show_installation"] == true) labels.add('Installation');
    if (_data["show_finishing"] == true) labels.add('Finishing');
    if (_data["show_advertising"] == true) labels.add('Advertising');
    return labels;
  }

  List<Widget> _buildTabViews() {
    final views = <Widget>[];
    if (_data["show_labor"] == true) views.add(_laborTab());
    if (_data["show_installation"] == true) views.add(_installationTab());
    if (_data["show_finishing"] == true) views.add(_finishingTab());
    if (_data["show_advertising"] == true) views.add(_advertisingTab());
    return views;
  }

  Color _statusColor(String status) {
    switch (status.toLowerCase()) {
      case 'accepted':
        return const Color(0xFF16A34A);
      case 'rejected':
        return const Color(0xFFDC2626);
      case 'pending':
        return const Color(0xFFB45309);
      default:
        return sfMuted;
    }
  }

  Color _statusBg(String status) {
    switch (status.toLowerCase()) {
      case 'accepted':
        return const Color(0xFFDCFCE7);
      case 'rejected':
        return const Color(0xFFFEE2E2);
      case 'pending':
        return const Color(0xFFFEF9C3);
      default:
        return const Color(0xFFF3F4F6);
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
                style: ElevatedButton.styleFrom(
                  backgroundColor: sfBlue,
                  shape: const RoundedRectangleBorder(),
                ),
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

    final tabLabels = _buildTabLabels();
    final tabViews = _buildTabViews();

    if (tabLabels.isEmpty) {
      return Scaffold(
        backgroundColor: sfBg,
        body: Center(
          child: Column(
            mainAxisSize: MainAxisSize.min,
            children: const [
              Icon(Icons.build_outlined, color: sfMuted, size: 48),
              SizedBox(height: 12),
              Text(
                'No services selected',
                style: TextStyle(color: sfMuted, fontSize: 15),
              ),
            ],
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: sfBg,
      body: NestedScrollView(
        headerSliverBuilder: (context, innerBoxIsScrolled) => [
          SliverAppBar(
            backgroundColor: sfBlue,
            pinned: true,
            automaticallyImplyLeading: false,
            expandedHeight: 100,
            flexibleSpace: FlexibleSpaceBar(
              background: Container(
                color: sfBlue,
                padding: const EdgeInsets.fromLTRB(20, 50, 20, 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    const Text(
                      'Services',
                      style: TextStyle(
                        color: Colors.white,
                        fontSize: 20,
                        fontWeight: FontWeight.w900,
                      ),
                    ),
                    const SizedBox(height: 6),
                    Row(
                      children: [
                        if (_data["show_labor"] == true)
                          _statChip(
                            '${_data["total_applicants"] ?? 0} applicants',
                          ),
                        if (_data["show_installation"] == true) ...[
                          const SizedBox(width: 8),
                          _statChip(
                            '${_data["total_installation"] ?? 0} installation',
                          ),
                        ],
                        if (_data["show_finishing"] == true) ...[
                          const SizedBox(width: 8),
                          _statChip(
                            '${_data["total_finishing"] ?? 0} finishing',
                          ),
                        ],
                      ],
                    ),
                  ],
                ),
              ),
            ),
            bottom: TabBar(
              controller: _tabController,
              isScrollable: true,
              tabAlignment: TabAlignment.start,
              labelColor: Colors.white,
              unselectedLabelColor: Colors.white60,
              labelStyle: const TextStyle(
                fontWeight: FontWeight.w800,
                fontSize: 13.5,
              ),
              unselectedLabelStyle: const TextStyle(
                fontWeight: FontWeight.w600,
                fontSize: 13.5,
              ),
              indicator: const UnderlineTabIndicator(
                borderSide: BorderSide(color: Colors.white, width: 3),
                borderRadius: BorderRadius.zero,
              ),
              dividerColor: Colors.transparent,
              tabs: tabLabels.map((l) => Tab(text: l)).toList(),
            ),
          ),
        ],
        body: TabBarView(controller: _tabController, children: tabViews),
      ),
    );
  }

  Widget _statChip(String label) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 3),
      decoration: BoxDecoration(
        color: Colors.white.withOpacity(0.15),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: Colors.white.withOpacity(0.25)),
      ),
      child: Text(
        label,
        style: const TextStyle(
          color: Colors.white,
          fontSize: 11,
          fontWeight: FontWeight.w700,
        ),
      ),
    );
  }

  // ─── LABOR TAB ──────────────────────────────────────────────────────────────

  Widget _laborTab() {
    final laborRoles = List<Map<String, dynamic>>.from(
      _data["labor_roles"] ?? [],
    );

    if (laborRoles.isEmpty) {
      return _emptyState('No labor jobs yet', Icons.people_outline);
    }

    final role = laborRoles[_selectedRoleIndex];
    final applicants = List<Map<String, dynamic>>.from(
      role["applicants"] ?? [],
    );
    final hired = List<Map<String, dynamic>>.from(role["hired"] ?? []);
    final total = int.tryParse(role["total_openings"]?.toString() ?? "0") ?? 0;
    final filled =
        int.tryParse(role["filled_openings"]?.toString() ?? "0") ?? 0;
    final salary = int.tryParse(role["salary_amount"]?.toString() ?? "0") ?? 0;
    final compType = role["compensation_type"] ?? "monthly";

    return RefreshIndicator(
      color: sfBlue,
      onRefresh: _load,
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Role pills
            SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              child: Row(
                children: laborRoles.asMap().entries.map((e) {
                  final i = e.key;
                  final r = e.value;
                  final isSelected = i == _selectedRoleIndex;
                  return GestureDetector(
                    onTap: () => setState(() => _selectedRoleIndex = i),
                    child: Container(
                      margin: const EdgeInsets.only(right: 8),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 14,
                        vertical: 8,
                      ),
                      decoration: BoxDecoration(
                        color: isSelected ? sfBlue : Colors.white,
                        border: Border.all(
                          color: isSelected ? sfBlue : const Color(0xFFE5E7EB),
                        ),
                      ),
                      child: Text(
                        r["title"] ?? "—",
                        style: TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: isSelected ? Colors.white : sfText,
                        ),
                      ),
                    ),
                  );
                }).toList(),
              ),
            ),

            const SizedBox(height: 16),

            // Role info bar
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: Colors.white,
                border: Border.all(color: const Color(0xFFE5E7EB)),
              ),
              child: Row(
                children: [
                  Expanded(
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        Text(
                          role["title"] ?? "—",
                          style: const TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                            color: sfText,
                          ),
                        ),
                        Text(
                          '${role["location"] ?? "—"} · $total openings · $filled filled',
                          style: const TextStyle(fontSize: 12, color: sfMuted),
                        ),
                      ],
                    ),
                  ),
                  // Salary
                  if (salary > 0)
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 4,
                      ),
                      decoration: const BoxDecoration(color: Color(0xFFDCFCE7)),
                      child: Text(
                        '${salary.toString()} EGP / $compType',
                        style: const TextStyle(
                          fontSize: 11.5,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFF15803D),
                        ),
                      ),
                    )
                  else
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 4,
                      ),
                      decoration: const BoxDecoration(color: Color(0xFFFEF9C3)),
                      child: const Text(
                        'No salary set',
                        style: TextStyle(
                          fontSize: 11.5,
                          fontWeight: FontWeight.w700,
                          color: Color(0xFFB45309),
                        ),
                      ),
                    ),
                  const SizedBox(width: 8),
                  // Set salary button
                  GestureDetector(
                    onTap: () => _showSalaryModal(role),
                    child: Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 12,
                        vertical: 8,
                      ),
                      decoration: const BoxDecoration(color: sfBlue),
                      child: const Text(
                        'Set Salary',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 16),

            // Kanban board
            Row(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Applied column
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _kanbanHeader(
                        'Applied',
                        applicants.length,
                        const Color(0xFF3B82F6),
                      ),
                      const SizedBox(height: 8),
                      if (applicants.isEmpty)
                        _kanbanEmpty('No applicants yet')
                      else
                        ...applicants
                            .map((a) => _applicantCard(a, false))
                            .toList(),
                    ],
                  ),
                ),
                const SizedBox(width: 12),
                // Hired column
                Expanded(
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      _kanbanHeader(
                        'Hired',
                        hired.length,
                        const Color(0xFF22C55E),
                      ),
                      const SizedBox(height: 8),
                      if (hired.isEmpty)
                        _kanbanEmpty('Empty')
                      else
                        ...hired.map((a) => _applicantCard(a, true)).toList(),
                    ],
                  ),
                ),
              ],
            ),
          ],
        ),
      ),
    );
  }

  Widget _kanbanHeader(String title, int count, Color color) {
    return Row(
      children: [
        Container(
          width: 10,
          height: 10,
          decoration: BoxDecoration(color: color, shape: BoxShape.circle),
        ),
        const SizedBox(width: 6),
        Text(
          title,
          style: const TextStyle(
            fontSize: 13,
            fontWeight: FontWeight.w800,
            color: sfText,
          ),
        ),
        const SizedBox(width: 6),
        Container(
          padding: const EdgeInsets.symmetric(horizontal: 7, vertical: 2),
          decoration: BoxDecoration(color: color.withOpacity(0.1)),
          child: Text(
            '$count',
            style: TextStyle(
              fontSize: 11,
              fontWeight: FontWeight.w700,
              color: color,
            ),
          ),
        ),
      ],
    );
  }

  Widget _applicantCard(Map<String, dynamic> app, bool isHired) {
    final skills = (app["skills"] ?? "")
        .toString()
        .split(',')
        .where((s) => s.trim().isNotEmpty)
        .take(2)
        .toList();
    final rating = double.tryParse(app["avg_rating"]?.toString() ?? "0") ?? 0;
    final rate = double.tryParse(app["hourly_rate"]?.toString() ?? "0") ?? 0;
    final initials = (app["worker_name"] ?? "?")
        .toString()
        .substring(0, 1)
        .toUpperCase();

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Container(
                width: 36,
                height: 36,
                decoration: BoxDecoration(
                  color: isHired
                      ? const Color(0xFFDCFCE7)
                      : const Color(0xFFDBEAFE),
                  shape: BoxShape.circle,
                ),
                child: Center(
                  child: Text(
                    initials,
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: isHired ? const Color(0xFF15803D) : sfBlue,
                    ),
                  ),
                ),
              ),
              const SizedBox(width: 8),
              Expanded(
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      app["worker_name"] ?? "—",
                      style: const TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: sfText,
                      ),
                    ),
                    Text(
                      app["experience_level"] ?? "—",
                      style: const TextStyle(fontSize: 11, color: sfMuted),
                    ),
                  ],
                ),
              ),
            ],
          ),
          if (skills.isNotEmpty) ...[
            const SizedBox(height: 6),
            Wrap(
              spacing: 4,
              children: skills
                  .map(
                    (s) => Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 6,
                        vertical: 2,
                      ),
                      decoration: const BoxDecoration(color: Color(0xFFF3F4F6)),
                      child: Text(
                        s.trim(),
                        style: const TextStyle(
                          fontSize: 10,
                          fontWeight: FontWeight.w600,
                          color: sfMuted,
                        ),
                      ),
                    ),
                  )
                  .toList(),
            ),
          ],
          const SizedBox(height: 6),
          Row(
            children: [
              const Icon(
                Icons.star_rounded,
                size: 12,
                color: Color(0xFFF59E0B),
              ),
              const SizedBox(width: 3),
              Text(
                rating.toStringAsFixed(1),
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                  color: sfText,
                ),
              ),
              const Spacer(),
              Text(
                '${rate.toStringAsFixed(0)} EGP/hr',
                style: const TextStyle(
                  fontSize: 11,
                  fontWeight: FontWeight.w600,
                  color: sfMuted,
                ),
              ),
            ],
          ),
          if (!isHired) ...[
            const SizedBox(height: 8),
            GestureDetector(
              onTap: () => _showApplicantModal(app),
              child: Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(vertical: 8),
                decoration: BoxDecoration(border: Border.all(color: sfBlue)),
                child: const Text(
                  'View',
                  textAlign: TextAlign.center,
                  style: TextStyle(
                    fontSize: 12,
                    fontWeight: FontWeight.w700,
                    color: sfBlue,
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  // ─── INSTALLATION TAB ───────────────────────────────────────────────────────

  Widget _installationTab() {
    final rows = List<Map<String, dynamic>>.from(
      _data["installation_rows"] ?? [],
    );

    if (rows.isEmpty) {
      return _emptyState('No installation services yet', Icons.build_outlined);
    }

    return RefreshIndicator(
      color: sfBlue,
      onRefresh: _load,
      child: ListView.builder(
        padding: const EdgeInsets.all(16),
        itemCount: rows.length,
        itemBuilder: (ctx, i) {
          final req = rows[i];
          final companies = List<Map<String, dynamic>>.from(
            req["companies"] ?? [],
          );
          final serviceKey = req["service_key"] ?? "";
          final status = req["status"] ?? "pending";
          final scheduledDate = req["scheduled_date"] ?? "";

          final serviceLabels = {
            'pos': 'POS System',
            'electrical': 'Electrical Wiring',
            'network': 'Network & WiFi',
            'ac': 'AC Installation',
            'kitchen': 'Kitchen Setup',
          };
          final serviceLabel =
              serviceLabels[serviceKey] ?? serviceKey.toUpperCase();

          return Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Service header
              Row(
                children: [
                  const Icon(Icons.build_outlined, color: sfBlue, size: 16),
                  const SizedBox(width: 8),
                  Text(
                    serviceLabel,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: sfText,
                    ),
                  ),
                  const SizedBox(width: 8),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 8,
                      vertical: 3,
                    ),
                    decoration: BoxDecoration(color: _statusBg(status)),
                    child: Text(
                      status[0].toUpperCase() + status.substring(1),
                      style: TextStyle(
                        fontSize: 10.5,
                        fontWeight: FontWeight.w700,
                        color: _statusColor(status),
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),

              if (companies.isEmpty)
                _emptyState('No companies available', Icons.business_outlined)
              else
                ...companies.asMap().entries.map((e) {
                  final idx = e.key;
                  final co = e.value;
                  return _installationCompanyCard(
                    co,
                    idx == 0,
                    req,
                    scheduledDate,
                  );
                }),

              const SizedBox(height: 20),
            ],
          );
        },
      ),
    );
  }

  Widget _installationCompanyCard(
    Map<String, dynamic> co,
    bool isFirst,
    Map<String, dynamic> req,
    String scheduledDate,
  ) {
    final hasQuote = co["quote_id"] != null;
    final quoteStatus = co["quote_status"]?.toString() ?? "";
    final quoteAccepted = quoteStatus == 'accepted';
    final quoteRejected = quoteStatus == 'rejected';
    final displayPrice = co["display_price"] ?? 0;
    final avgRating = double.tryParse(co["avg_rating"]?.toString() ?? "0") ?? 0;

    String badgeText;
    Color badgeColor;
    Color badgeBg;

    if (quoteAccepted) {
      badgeText = 'Accepted';
      badgeColor = const Color(0xFF16A34A);
      badgeBg = const Color(0xFFDCFCE7);
    } else if (hasQuote && !quoteRejected) {
      badgeText = 'Quote Received';
      badgeColor = sfBlue;
      badgeBg = const Color(0xFFDBEAFE);
    } else if (quoteRejected) {
      badgeText = 'Not Selected';
      badgeColor = const Color(0xFFDC2626);
      badgeBg = const Color(0xFFFEE2E2);
    } else {
      badgeText = 'Awaiting Quote';
      badgeColor = sfMuted;
      badgeBg = const Color(0xFFF3F4F6);
    }

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(
          color: quoteAccepted
              ? const Color(0xFF86EFAC)
              : const Color(0xFFE5E7EB),
        ),
      ),
      child: Column(
        children: [
          if (isFirst)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
              color: sfBlue,
              child: const Text(
                'Recommended',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    // Company avatar
                    Container(
                      width: 44,
                      height: 44,
                      decoration: const BoxDecoration(color: Color(0xFFEFF6FF)),
                      child: Center(
                        child: Text(
                          (co["company_name"] ?? "?")
                              .toString()
                              .substring(0, 2)
                              .toUpperCase(),
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w800,
                            color: sfBlue,
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
                            co["company_name"] ?? "—",
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w800,
                              color: sfText,
                            ),
                          ),
                          Row(
                            children: [
                              const Icon(
                                Icons.star_rounded,
                                size: 12,
                                color: Color(0xFFF59E0B),
                              ),
                              const SizedBox(width: 3),
                              Text(
                                avgRating.toStringAsFixed(1),
                                style: const TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                  color: sfText,
                                ),
                              ),
                              const SizedBox(width: 8),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 7,
                                  vertical: 2,
                                ),
                                decoration: BoxDecoration(color: badgeBg),
                                child: Text(
                                  badgeText,
                                  style: TextStyle(
                                    fontSize: 10.5,
                                    fontWeight: FontWeight.w700,
                                    color: badgeColor,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),

                // Price
                Text(
                  'Est. Installation Cost',
                  style: const TextStyle(
                    fontSize: 11,
                    color: sfMuted,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                Text(
                  '$displayPrice EGP',
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                    color: sfText,
                  ),
                ),

                if (scheduledDate.isNotEmpty) ...[
                  const SizedBox(height: 8),
                  Row(
                    children: [
                      const Icon(
                        Icons.calendar_today_outlined,
                        size: 13,
                        color: sfBlue,
                      ),
                      const SizedBox(width: 6),
                      Text(
                        'Scheduled: $scheduledDate',
                        style: const TextStyle(
                          fontSize: 12,
                          fontWeight: FontWeight.w700,
                          color: sfBlue,
                        ),
                      ),
                    ],
                  ),
                ],

                const SizedBox(height: 12),

                // Actions
                Row(
                  children: [
                    if ((co["website"] ?? "").toString().isNotEmpty)
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () {},
                          style: OutlinedButton.styleFrom(
                            padding: const EdgeInsets.symmetric(vertical: 10),
                            side: const BorderSide(color: Color(0xFFE5E7EB)),
                            shape: const RoundedRectangleBorder(),
                          ),
                          child: const Text(
                            'Website',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ),
                    if (hasQuote && !quoteAccepted && !quoteRejected) ...[
                      const SizedBox(width: 8),
                      Expanded(
                        child: ElevatedButton(
                          onPressed: () => _acceptQuote(co, req),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: sfBlue,
                            padding: const EdgeInsets.symmetric(vertical: 10),
                            shape: const RoundedRectangleBorder(),
                          ),
                          child: const Text(
                            'Accept Quote',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ),
                    ],
                    if (quoteAccepted && scheduledDate.isEmpty) ...[
                      const SizedBox(width: 8),
                      Expanded(
                        child: ElevatedButton(
                          onPressed: () => _showScheduleModal(req),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: sfBlue,
                            padding: const EdgeInsets.symmetric(vertical: 10),
                            shape: const RoundedRectangleBorder(),
                          ),
                          child: const Text(
                            'Schedule',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ─── FINISHING TAB ──────────────────────────────────────────────────────────

  Widget _finishingTab() {
    final finishingReq = _data["finishing_req"] as Map<String, dynamic>?;
    final finishingList = List<Map<String, dynamic>>.from(
      _data["finishing_list"] ?? [],
    );

    if (finishingReq == null) {
      return _emptyState('No finishing request yet', Icons.brush_outlined);
    }

    final rawTypes =
        finishingReq["finishing_types"]?.toString().replaceAll(
          RegExp(r'[{}]'),
          '',
        ) ??
        "";
    final selectedTypes = rawTypes
        .split(',')
        .map((s) => s.trim())
        .where((s) => s.isNotEmpty)
        .toList();

    const typeLabels = {
      'painting': 'Painting',
      'flooring': 'Flooring',
      'gypsum': 'Gypsum & Ceilings',
      'decor': 'Decor',
      'facades': 'Facades',
    };

    return RefreshIndicator(
      color: sfBlue,
      onRefresh: _load,
      child: SingleChildScrollView(
        physics: const AlwaysScrollableScrollPhysics(),
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Type selector
            Container(
              padding: const EdgeInsets.all(16),
              decoration: BoxDecoration(
                color: Colors.white,
                border: Border.all(color: const Color(0xFFE5E7EB)),
              ),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'What needs finishing?',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: sfText,
                    ),
                  ),
                  const SizedBox(height: 12),
                  Wrap(
                    spacing: 8,
                    runSpacing: 8,
                    children: typeLabels.entries.map((e) {
                      final isSelected = selectedTypes.contains(e.key);
                      return GestureDetector(
                        onTap: () => _toggleFinishingType(
                          e.key,
                          selectedTypes,
                          finishingReq,
                        ),
                        child: Container(
                          padding: const EdgeInsets.symmetric(
                            horizontal: 14,
                            vertical: 8,
                          ),
                          decoration: BoxDecoration(
                            color: isSelected ? sfBlue : Colors.white,
                            border: Border.all(
                              color: isSelected
                                  ? sfBlue
                                  : const Color(0xFFE5E7EB),
                            ),
                          ),
                          child: Text(
                            e.value,
                            style: TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w700,
                              color: isSelected ? Colors.white : sfText,
                            ),
                          ),
                        ),
                      );
                    }).toList(),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 16),

            if (finishingList.isEmpty)
              _emptyState('No finishing companies yet', Icons.brush_outlined)
            else
              ...finishingList.asMap().entries.map(
                (e) => _finishingCompanyCard(e.value, e.key == 0),
              ),
          ],
        ),
      ),
    );
  }

  Widget _finishingCompanyCard(Map<String, dynamic> co, bool isFirst) {
    final hasQuote = co["quote_id"] != null;
    final quoteStatus = co["quote_status"]?.toString() ?? "";
    final quoteAccepted = quoteStatus == 'accepted';
    final quoteRejected = quoteStatus == 'rejected';
    final avgRating = double.tryParse(co["avg_rating"]?.toString() ?? "0") ?? 0;

    String badgeText;
    Color badgeColor;
    Color badgeBg;

    if (quoteAccepted) {
      badgeText = 'Accepted';
      badgeColor = const Color(0xFF16A34A);
      badgeBg = const Color(0xFFDCFCE7);
    } else if (hasQuote && !quoteRejected) {
      badgeText = 'Quote Received';
      badgeColor = sfBlue;
      badgeBg = const Color(0xFFDBEAFE);
    } else if (quoteRejected) {
      badgeText = 'Not Selected';
      badgeColor = const Color(0xFFDC2626);
      badgeBg = const Color(0xFFFEE2E2);
    } else {
      badgeText = 'Awaiting Quote';
      badgeColor = sfMuted;
      badgeBg = const Color(0xFFF3F4F6);
    }

    final price = hasQuote && !quoteRejected
        ? '${double.tryParse(co["price"]?.toString() ?? "0")?.toStringAsFixed(0) ?? "0"} EGP'
        : co["starting_from"] != null
        ? '${co["starting_from"]} EGP'
        : 'TBD';

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(
          color: quoteAccepted
              ? const Color(0xFF86EFAC)
              : const Color(0xFFE5E7EB),
        ),
      ),
      child: Column(
        children: [
          if (isFirst)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 4),
              color: sfBlue,
              child: const Text(
                'Recommended',
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 11,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          Padding(
            padding: const EdgeInsets.all(14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Row(
                  children: [
                    Container(
                      width: 44,
                      height: 44,
                      decoration: const BoxDecoration(color: Color(0xFFEFF6FF)),
                      child: Center(
                        child: Text(
                          (co["company_name"] ?? "?")
                              .toString()
                              .substring(0, 2)
                              .toUpperCase(),
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w800,
                            color: sfBlue,
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
                            co["company_name"] ?? "—",
                            style: const TextStyle(
                              fontSize: 14,
                              fontWeight: FontWeight.w800,
                              color: sfText,
                            ),
                          ),
                          Row(
                            children: [
                              const Icon(
                                Icons.star_rounded,
                                size: 12,
                                color: Color(0xFFF59E0B),
                              ),
                              const SizedBox(width: 3),
                              Text(
                                avgRating.toStringAsFixed(1),
                                style: const TextStyle(
                                  fontSize: 11,
                                  fontWeight: FontWeight.w700,
                                  color: sfText,
                                ),
                              ),
                              const SizedBox(width: 8),
                              Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 7,
                                  vertical: 2,
                                ),
                                decoration: BoxDecoration(color: badgeBg),
                                child: Text(
                                  badgeText,
                                  style: TextStyle(
                                    fontSize: 10.5,
                                    fontWeight: FontWeight.w700,
                                    color: badgeColor,
                                  ),
                                ),
                              ),
                            ],
                          ),
                        ],
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 12),
                Text(
                  'Starting From',
                  style: const TextStyle(
                    fontSize: 11,
                    color: sfMuted,
                    fontWeight: FontWeight.w600,
                  ),
                ),
                Text(
                  price,
                  style: const TextStyle(
                    fontSize: 18,
                    fontWeight: FontWeight.w900,
                    color: sfText,
                  ),
                ),
                const SizedBox(height: 12),
                Row(
                  children: [
                    if ((co["website"] ?? "").toString().isNotEmpty)
                      Expanded(
                        child: OutlinedButton(
                          onPressed: () {},
                          style: OutlinedButton.styleFrom(
                            padding: const EdgeInsets.symmetric(vertical: 10),
                            side: const BorderSide(color: Color(0xFFE5E7EB)),
                            shape: const RoundedRectangleBorder(),
                          ),
                          child: const Text(
                            'Website',
                            style: TextStyle(
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ),
                    if (hasQuote && !quoteAccepted && !quoteRejected) ...[
                      const SizedBox(width: 8),
                      Expanded(
                        child: ElevatedButton(
                          onPressed: () => _acceptFinishingQuote(co),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: sfBlue,
                            padding: const EdgeInsets.symmetric(vertical: 10),
                            shape: const RoundedRectangleBorder(),
                          ),
                          child: const Text(
                            'Accept Quote',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 12,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ),
                    ],
                  ],
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  // ─── ADVERTISING TAB ────────────────────────────────────────────────────────

  Widget _advertisingTab() {
    final list = List<Map<String, dynamic>>.from(
      _data["advertising_list"] ?? [],
    );

    if (list.isEmpty) {
      return _emptyState(
        'No advertising companies yet',
        Icons.campaign_outlined,
      );
    }

    return ListView.builder(
      padding: const EdgeInsets.all(16),
      itemCount: list.length,
      itemBuilder: (ctx, i) {
        final co = list[i];
        final avgRating =
            double.tryParse(co["avg_rating"]?.toString() ?? "0") ?? 0;

        return Container(
          margin: const EdgeInsets.only(bottom: 10),
          padding: const EdgeInsets.all(14),
          decoration: BoxDecoration(
            color: Colors.white,
            border: Border.all(color: const Color(0xFFE5E7EB)),
          ),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              if (i == 0)
                Container(
                  margin: const EdgeInsets.only(bottom: 8),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 8,
                    vertical: 3,
                  ),
                  color: sfBlue,
                  child: const Text(
                    'Recommended',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 11,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              Row(
                children: [
                  Container(
                    width: 44,
                    height: 44,
                    decoration: const BoxDecoration(color: Color(0xFFEFF6FF)),
                    child: Center(
                      child: Text(
                        (co["company_name"] ?? "?")
                            .toString()
                            .substring(0, 2)
                            .toUpperCase(),
                        style: const TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                          color: sfBlue,
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
                          co["company_name"] ?? "—",
                          style: const TextStyle(
                            fontSize: 14,
                            fontWeight: FontWeight.w800,
                            color: sfText,
                          ),
                        ),
                        Row(
                          children: [
                            const Icon(
                              Icons.star_rounded,
                              size: 12,
                              color: Color(0xFFF59E0B),
                            ),
                            const SizedBox(width: 3),
                            Text(
                              avgRating.toStringAsFixed(1),
                              style: const TextStyle(
                                fontSize: 11,
                                fontWeight: FontWeight.w700,
                                color: sfText,
                              ),
                            ),
                          ],
                        ),
                      ],
                    ),
                  ),
                ],
              ),
              if ((co["description"] ?? "").toString().isNotEmpty) ...[
                const SizedBox(height: 8),
                Text(
                  co["description"].toString(),
                  style: const TextStyle(
                    fontSize: 12.5,
                    color: sfMuted,
                    height: 1.4,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
              ],
              const SizedBox(height: 8),
              Text(
                co["starting_from"] != null
                    ? 'Starting from ${co["starting_from"]} EGP'
                    : 'Contact for pricing',
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w700,
                  color: sfText,
                ),
              ),
              if ((co["website"] ?? "").toString().isNotEmpty) ...[
                const SizedBox(height: 10),
                SizedBox(
                  width: double.infinity,
                  child: OutlinedButton(
                    onPressed: () {},
                    style: OutlinedButton.styleFrom(
                      padding: const EdgeInsets.symmetric(vertical: 10),
                      side: const BorderSide(color: Color(0xFFE5E7EB)),
                      shape: const RoundedRectangleBorder(),
                    ),
                    child: const Text(
                      'Visit Website',
                      style: TextStyle(
                        fontSize: 12,
                        fontWeight: FontWeight.w700,
                      ),
                    ),
                  ),
                ),
              ],
            ],
          ),
        );
      },
    );
  }

  // ─── MODALS ─────────────────────────────────────────────────────────────────

  void _showSalaryModal(Map<String, dynamic> role) {
    final amountC = TextEditingController(
      text: role["salary_amount"]?.toString() ?? "",
    );
    String compType = role["compensation_type"] ?? "monthly";

    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setModalState) => Padding(
          padding: EdgeInsets.only(
            left: 20,
            right: 20,
            top: 20,
            bottom: MediaQuery.of(ctx).viewInsets.bottom + 20,
          ),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Text(
                'Set Salary — ${role["title"]}',
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                  color: sfText,
                ),
              ),
              const SizedBox(height: 16),
              TextField(
                controller: amountC,
                keyboardType: TextInputType.number,
                decoration: const InputDecoration(
                  labelText: 'Amount (EGP)',
                  border: OutlineInputBorder(borderRadius: BorderRadius.zero),
                ),
              ),
              const SizedBox(height: 12),
              DropdownButtonFormField<String>(
                value: compType,
                decoration: const InputDecoration(
                  labelText: 'Per',
                  border: OutlineInputBorder(borderRadius: BorderRadius.zero),
                ),
                items: const [
                  DropdownMenuItem(value: 'monthly', child: Text('Month')),
                  DropdownMenuItem(value: 'daily', child: Text('Day')),
                  DropdownMenuItem(value: 'hourly', child: Text('Hour')),
                ],
                onChanged: (v) => setModalState(() => compType = v!),
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () async {
                    Navigator.pop(ctx);
                    await _saveSalary(
                      role["title"],
                      role["location"],
                      int.tryParse(amountC.text) ?? 0,
                      compType,
                    );
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: sfBlue,
                    shape: const RoundedRectangleBorder(),
                  ),
                  child: const Text(
                    'Save',
                    style: TextStyle(color: Colors.white),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showApplicantModal(Map<String, dynamic> app) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(),
      builder: (ctx) => DraggableScrollableSheet(
        initialChildSize: 0.7,
        maxChildSize: 0.95,
        minChildSize: 0.4,
        expand: false,
        builder: (ctx, scroll) => SingleChildScrollView(
          controller: scroll,
          padding: const EdgeInsets.all(20),
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Center(
                child: Container(
                  width: 40,
                  height: 4,
                  decoration: BoxDecoration(
                    color: const Color(0xFFE5E7EB),
                    borderRadius: BorderRadius.circular(2),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Text(
                app["worker_name"] ?? "—",
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                  color: sfText,
                ),
              ),
              Text(
                app["labor_role"] ?? app["title"] ?? "—",
                style: const TextStyle(fontSize: 13, color: sfMuted),
              ),
              const SizedBox(height: 16),
              _infoRow('Experience', app["experience_level"] ?? "—"),
              _infoRow(
                'Rating',
                '${double.tryParse(app["avg_rating"]?.toString() ?? "0")?.toStringAsFixed(1) ?? "0"} ⭐',
              ),
              _infoRow('Hourly Rate', '${app["hourly_rate"] ?? 0} EGP/hr'),
              _infoRow('Availability', app["availability_status"] ?? "—"),
              _infoRow('Military', app["military_status"] ?? "—"),
              const SizedBox(height: 16),
              const Text(
                'Skills',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w800,
                  color: sfBlue,
                ),
              ),
              const SizedBox(height: 8),
              Text(
                app["skills"] ?? "No skills listed",
                style: const TextStyle(
                  fontSize: 13,
                  color: sfText,
                  height: 1.5,
                ),
              ),
              const SizedBox(height: 20),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: () async {
                    Navigator.pop(ctx);
                    await _hireApplicant(app);
                  },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: sfBlue,
                    shape: const RoundedRectangleBorder(),
                  ),
                  child: const Text(
                    'Hire',
                    style: TextStyle(color: Colors.white),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  void _showScheduleModal(Map<String, dynamic> req) {
    DateTime? selectedDate;
    showModalBottomSheet(
      context: context,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(),
      builder: (ctx) => StatefulBuilder(
        builder: (ctx, setModalState) => Padding(
          padding: const EdgeInsets.all(20),
          child: Column(
            mainAxisSize: MainAxisSize.min,
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Schedule Installation',
                style: TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                  color: sfText,
                ),
              ),
              const SizedBox(height: 16),
              GestureDetector(
                onTap: () async {
                  final picked = await showDatePicker(
                    context: ctx,
                    initialDate: DateTime.now().add(const Duration(days: 1)),
                    firstDate: DateTime.now(),
                    lastDate: DateTime.now().add(const Duration(days: 365)),
                  );
                  if (picked != null)
                    setModalState(() => selectedDate = picked);
                },
                child: Container(
                  width: double.infinity,
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    border: Border.all(color: const Color(0xFFE5E7EB)),
                  ),
                  child: Text(
                    selectedDate != null
                        ? '${selectedDate!.day}/${selectedDate!.month}/${selectedDate!.year}'
                        : 'Pick a date',
                    style: TextStyle(
                      fontSize: 14,
                      color: selectedDate != null ? sfText : sfMuted,
                    ),
                  ),
                ),
              ),
              const SizedBox(height: 16),
              SizedBox(
                width: double.infinity,
                child: ElevatedButton(
                  onPressed: selectedDate == null
                      ? null
                      : () async {
                          Navigator.pop(ctx);
                          await _scheduleInstallation(req, selectedDate!);
                        },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: sfBlue,
                    shape: const RoundedRectangleBorder(),
                  ),
                  child: const Text(
                    'Confirm Date',
                    style: TextStyle(color: Colors.white),
                  ),
                ),
              ),
            ],
          ),
        ),
      ),
    );
  }

  // ─── ACTIONS ────────────────────────────────────────────────────────────────

  Future<void> _saveSalary(
    String title,
    String location,
    int amount,
    String compType,
  ) async {
    final token = await api.getToken();
    if (token == null) return;
    final uri = Uri.parse("${ApiService.baseUrl}/set_job_salary.php");
    try {
      await http.post(
        uri,
        headers: {
          "Content-Type": "application/json",
          "Authorization": "Bearer $token",
        },
        body: jsonEncode({
          "title": title,
          "location": location,
          "salary_amount": amount,
          "compensation_type": compType,
        }),
      );
      await _load();
    } catch (_) {}
  }

  Future<void> _hireApplicant(Map<String, dynamic> app) async {
    final token = await api.getToken();
    if (token == null) return;
    final uri = Uri.parse("${ApiService.baseUrl}/hire_applicant.php");
    try {
      await http.post(
        uri,
        headers: {
          "Content-Type": "application/json",
          "Authorization": "Bearer $token",
        },
        body: jsonEncode({
          "application_id": app["application_id"],
          "labor_user_id": app["labor_user_id"],
          "job_id": app["job_id"],
        }),
      );
      await _load();
    } catch (_) {}
  }

  Future<void> _acceptQuote(
    Map<String, dynamic> co,
    Map<String, dynamic> req,
  ) async {
    final token = await api.getToken();
    if (token == null) return;
    final uri = Uri.parse("${ApiService.baseUrl}/accept_quote.php");
    try {
      await http.post(
        uri,
        headers: {
          "Content-Type": "application/json",
          "Authorization": "Bearer $token",
        },
        body: jsonEncode({
          "quote_id": co["quote_id"],
          "request_id": req["request_id"],
        }),
      );
      await _load();
    } catch (_) {}
  }

  Future<void> _acceptFinishingQuote(Map<String, dynamic> co) async {
    final token = await api.getToken();
    if (token == null) return;
    final finishingReq = _data["finishing_req"] as Map<String, dynamic>?;
    if (finishingReq == null) return;
    final uri = Uri.parse("${ApiService.baseUrl}/service_jobs.php");
    try {
      await http.post(
        uri,
        headers: {"Authorization": "Bearer $token"},
        body: {
          "action": "accept_finishing_quote",
          "quote_id": co["quote_id"].toString(),
          "request_id": finishingReq["request_id"].toString(),
        },
      );
      await _load();
    } catch (_) {}
  }

  Future<void> _scheduleInstallation(
    Map<String, dynamic> req,
    DateTime date,
  ) async {
    final token = await api.getToken();
    if (token == null) return;
    final uri = Uri.parse("${ApiService.baseUrl}/service_jobs.php");
    final dateStr =
        '${date.year}-${date.month.toString().padLeft(2, '0')}-${date.day.toString().padLeft(2, '0')}';
    try {
      await http.post(
        uri,
        headers: {"Authorization": "Bearer $token"},
        body: {
          "action": "schedule_installation",
          "request_id": req["request_id"].toString(),
          "scheduled_date": dateStr,
        },
      );
      await _load();
    } catch (_) {}
  }

  // ─── HELPERS ────────────────────────────────────────────────────────────────

  Future<void> _toggleFinishingType(
    String type,
    List<String> current,
    Map<String, dynamic> req,
  ) async {
    final updated = List<String>.from(current);
    if (updated.contains(type)) {
      updated.remove(type);
    } else {
      updated.add(type);
    }
    final token = await api.getToken();
    if (token == null) return;
    final uri = Uri.parse("${ApiService.baseUrl}/service_jobs.php");
    try {
      await http.post(
        uri,
        headers: {"Authorization": "Bearer $token"},
        body: {"action": "save_finishing_types", "finishing_types[]": updated},
      );
      await _load();
    } catch (_) {}
  }

  Widget _infoRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 8),
      child: Row(
        children: [
          SizedBox(
            width: 100,
            child: Text(
              label,
              style: const TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w700,
                color: sfMuted,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w600,
                color: sfText,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _kanbanEmpty(String text) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Text(
        text,
        textAlign: TextAlign.center,
        style: const TextStyle(
          fontSize: 12.5,
          color: sfMuted,
          fontWeight: FontWeight.w600,
        ),
      ),
    );
  }

  Widget _emptyState(String text, IconData icon) {
    return Center(
      child: Padding(
        padding: const EdgeInsets.all(40),
        child: Column(
          mainAxisSize: MainAxisSize.min,
          children: [
            Icon(icon, size: 48, color: sfMuted),
            const SizedBox(height: 12),
            Text(
              text,
              textAlign: TextAlign.center,
              style: const TextStyle(
                fontSize: 14,
                color: sfMuted,
                fontWeight: FontWeight.w600,
              ),
            ),
          ],
        ),
      ),
    );
  }
}
