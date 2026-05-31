import 'package:flutter/material.dart';
import '../services/api_service.dart';

class LaborHomeScreen extends StatefulWidget {
  const LaborHomeScreen({super.key});

  @override
  State<LaborHomeScreen> createState() => _LaborHomeScreenState();
}

class _LaborHomeScreenState extends State<LaborHomeScreen> {
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
    final res = await api.getLaborDashboard();
    if (mounted) {
      setState(() {
        _data = res;
        _loading = false;
      });
    }
  }

  String _timeAgo(String? dateStr) {
    if (dateStr == null || dateStr.isEmpty) return 'Unknown';
    final dt = DateTime.tryParse(dateStr);
    if (dt == null) return 'Unknown';
    final diff = DateTime.now().difference(dt);
    if (diff.inMinutes < 1) return 'Just now';
    if (diff.inHours < 1) return '${diff.inMinutes} min ago';
    if (diff.inDays < 1)
      return '${diff.inHours} hour${diff.inHours > 1 ? 's' : ''} ago';
    if (diff.inDays < 2) return 'Yesterday';
    if (diff.inDays < 30)
      return '${diff.inDays} day${diff.inDays > 1 ? 's' : ''} ago';
    return '${dt.day} ${_monthName(dt.month)} ${dt.year}';
  }

  String _monthName(int m) => const [
    '',
    'Jan',
    'Feb',
    'Mar',
    'Apr',
    'May',
    'Jun',
    'Jul',
    'Aug',
    'Sep',
    'Oct',
    'Nov',
    'Dec',
  ][m];

  Color _statusColor(String status) {
    switch (status) {
      case 'completed':
        return const Color(0xFF16A34A);
      case 'active':
      case 'assigned':
      case 'processing':
        return sfBlue;
      case 'available':
        return const Color(0xFF0891B2);
      default:
        return sfMuted;
    }
  }

  Color _statusBg(String status) {
    switch (status) {
      case 'completed':
        return const Color(0xFFDCFCE7);
      case 'active':
      case 'assigned':
      case 'processing':
        return const Color(0xFFDBEAFE);
      case 'available':
        return const Color(0xFFCFFAFE);
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

    final userName = _data["user_name"] ?? "Worker";
    final providerType = _data["provider_type"] ?? "labor";
    final availability = _data["availability_status"] ?? "available";
    final availableJobs = _data["available_jobs"] ?? 0;
    final completedJobs = _data["completed_jobs"] ?? 0;
    final pendingJobs = _data["pending_jobs"] ?? 0;
    final totalAssigned = _data["total_assigned"] ?? 0;
    final completionRate = _data["completion_rate"] ?? 0;
    final activeJobs = List<Map<String, dynamic>>.from(
      _data["active_jobs"] ?? [],
    );
    final recentJobs = List<Map<String, dynamic>>.from(
      _data["recent_jobs"] ?? [],
    );
    final notifications = List<String>.from(_data["notifications"] ?? []);

    return Scaffold(
      backgroundColor: sfBg,
      body: RefreshIndicator(
        color: sfBlue,
        onRefresh: _load,
        child: CustomScrollView(
          slivers: [
            // Top bar
            SliverAppBar(
              backgroundColor: sfBlue,
              expandedHeight: 130,
              pinned: true,
              automaticallyImplyLeading: false,
              flexibleSpace: FlexibleSpaceBar(
                background: Container(
                  color: sfBlue,
                  padding: const EdgeInsets.fromLTRB(20, 56, 20, 16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      Text(
                        'Welcome, $userName',
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 20,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          _chip(
                            providerType.isNotEmpty
                                ? providerType[0].toUpperCase() +
                                      providerType.substring(1)
                                : 'Labor',
                          ),
                          const SizedBox(width: 8),
                          _chip(
                            'Status: ${availability[0].toUpperCase()}${availability.substring(1)}',
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
                  // Stats grid
                  GridView.count(
                    crossAxisCount: 2,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    crossAxisSpacing: 12,
                    mainAxisSpacing: 12,
                    childAspectRatio: 1.5,
                    children: [
                      _statCard(
                        'Available Jobs',
                        availableJobs.toString(),
                        'Open for your role',
                        Icons.work_outline_rounded,
                      ),
                      _statCard(
                        'Completed',
                        completedJobs.toString(),
                        'All completed work',
                        Icons.check_circle_outline_rounded,
                      ),
                      _statCard(
                        'Pending',
                        pendingJobs.toString(),
                        'Currently in progress',
                        Icons.pending_outlined,
                      ),
                      _statCard(
                        'Total Assigned',
                        totalAssigned.toString(),
                        'All jobs assigned',
                        Icons.assignment_outlined,
                      ),
                      _statCard(
                        'Completion Rate',
                        '$completionRate%',
                        '$completedJobs of $totalAssigned jobs',
                        Icons.trending_up_rounded,
                      ),
                      _statCard(
                        'Availability',
                        availability[0].toUpperCase() +
                            availability.substring(1),
                        'Current working status',
                        Icons.circle_outlined,
                      ),
                    ],
                  ),

                  const SizedBox(height: 20),

                  // Notifications
                  if (notifications.isNotEmpty) ...[
                    _sectionHeader(
                      'Notifications',
                      Icons.notifications_outlined,
                    ),
                    const SizedBox(height: 10),
                    ...notifications.map((n) => _noticeItem(n)),
                    const SizedBox(height: 20),
                  ],

                  // Active Jobs
                  _sectionHeader('Active Jobs', Icons.work_rounded),
                  const SizedBox(height: 10),
                  if (activeJobs.isEmpty)
                    _emptyBox('No active jobs right now.')
                  else
                    ...activeJobs.map((job) => _activeJobCard(job)),

                  const SizedBox(height: 20),

                  // Recent Jobs
                  _sectionHeader('Recent Jobs', Icons.history_rounded),
                  const SizedBox(height: 10),
                  if (recentJobs.isEmpty)
                    _emptyBox('No recent jobs found.')
                  else
                    Container(
                      decoration: BoxDecoration(
                        color: Colors.white,
                        borderRadius: BorderRadius.circular(16),
                        border: Border.all(color: const Color(0xFFE5E7EB)),
                      ),
                      child: Column(
                        children: recentJobs.asMap().entries.map((e) {
                          final i = e.key;
                          final job = e.value;
                          return Column(
                            children: [
                              _recentJobRow(job),
                              if (i < recentJobs.length - 1)
                                const Divider(
                                  height: 1,
                                  color: Color(0xFFF3F4F6),
                                ),
                            ],
                          );
                        }).toList(),
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

  Widget _statCard(String title, String value, String sub, IconData icon) {
    return Container(
      padding: const EdgeInsets.all(14),
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
        crossAxisAlignment: CrossAxisAlignment.start,
        mainAxisAlignment: MainAxisAlignment.spaceBetween,
        children: [
          Row(
            children: [
              Icon(icon, color: sfBlue, size: 16),
              const SizedBox(width: 6),
              Expanded(
                child: Text(
                  title,
                  style: const TextStyle(
                    fontSize: 11.5,
                    fontWeight: FontWeight.w700,
                    color: sfMuted,
                  ),
                  overflow: TextOverflow.ellipsis,
                ),
              ),
            ],
          ),
          Text(
            value,
            style: const TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w900,
              color: sfText,
            ),
          ),
          Text(
            sub,
            style: const TextStyle(fontSize: 10.5, color: sfMuted),
            overflow: TextOverflow.ellipsis,
          ),
        ],
      ),
    );
  }

  Widget _sectionHeader(String title, IconData icon) {
    return Row(
      children: [
        Icon(icon, color: sfBlue, size: 18),
        const SizedBox(width: 8),
        Text(
          title,
          style: const TextStyle(
            fontSize: 15,
            fontWeight: FontWeight.w800,
            color: sfText,
          ),
        ),
      ],
    );
  }

  Widget _noticeItem(String text) {
    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFEFF6FF),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: const Color(0xFFBFDBFE)),
      ),
      child: Row(
        children: [
          const Icon(Icons.notifications_outlined, color: sfBlue, size: 16),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              text,
              style: const TextStyle(
                fontSize: 13,
                color: sfText,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _activeJobCard(Map<String, dynamic> job) {
    final status = job["status"] ?? "";
    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(16),
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
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  job["title"] ?? "—",
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w800,
                    color: sfText,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(
                  horizontal: 10,
                  vertical: 4,
                ),
                decoration: BoxDecoration(
                  color: _statusBg(status),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  status[0].toUpperCase() + status.substring(1),
                  style: TextStyle(
                    color: _statusColor(status),
                    fontSize: 11,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Row(
            children: [
              const Icon(Icons.location_on_outlined, size: 14, color: sfMuted),
              const SizedBox(width: 4),
              Text(
                job["location"] ?? "—",
                style: const TextStyle(fontSize: 12.5, color: sfMuted),
              ),
              const SizedBox(width: 14),
              const Icon(Icons.payments_outlined, size: 14, color: sfMuted),
              const SizedBox(width: 4),
              Text(
                '${double.tryParse(job["salary_amount"]?.toString() ?? "0")?.toStringAsFixed(0) ?? "0"} EGP',
                style: const TextStyle(fontSize: 12.5, color: sfMuted),
              ),
            ],
          ),
          const SizedBox(height: 8),
          Text(
            'Started: ${_timeAgo(job["created_at"])}',
            style: const TextStyle(fontSize: 11.5, color: sfMuted),
          ),
        ],
      ),
    );
  }

  Widget _recentJobRow(Map<String, dynamic> job) {
    final status = job["status"] ?? "";
    return Padding(
      padding: const EdgeInsets.symmetric(horizontal: 16, vertical: 12),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  job["title"] ?? "—",
                  style: const TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w700,
                    color: sfText,
                  ),
                ),
                const SizedBox(height: 3),
                Text(
                  job["location"] ?? "—",
                  style: const TextStyle(fontSize: 12, color: sfMuted),
                ),
              ],
            ),
          ),
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: _statusBg(status),
                  borderRadius: BorderRadius.circular(999),
                ),
                child: Text(
                  status[0].toUpperCase() + status.substring(1),
                  style: TextStyle(
                    color: _statusColor(status),
                    fontSize: 10.5,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ),
              const SizedBox(height: 4),
              Text(
                _timeAgo(job["created_at"]),
                style: const TextStyle(fontSize: 11, color: sfMuted),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _emptyBox(String text) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(20),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Text(
        text,
        textAlign: TextAlign.center,
        style: const TextStyle(color: sfMuted, fontSize: 13.5),
      ),
    );
  }
}
