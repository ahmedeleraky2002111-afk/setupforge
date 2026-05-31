import 'package:flutter/material.dart';
import '../services/api_service.dart';

class LaborJobsScreen extends StatefulWidget {
  const LaborJobsScreen({super.key});

  @override
  State<LaborJobsScreen> createState() => _LaborJobsScreenState();
}

class _LaborJobsScreenState extends State<LaborJobsScreen> {
  static const Color sfBlue = Color(0xFF004CAC);
  static const Color sfBg = Color(0xFFF5F7FB);
  static const Color sfText = Color(0xFF111827);
  static const Color sfMuted = Color(0xFF6B7280);

  final api = ApiService();
  bool _loading = true;
  Map<String, dynamic> _data = {};
  String? _successMsg;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() {
      _loading = true;
      _successMsg = null;
    });
    final res = await api.getLaborJobs();
    if (mounted) {
      setState(() {
        _data = res;
        _loading = false;
      });
    }
  }

  Future<void> _completeJob(int jobId) async {
    final res = await api.completeJob(jobId);
    if (!mounted) return;
    if (res["ok"] == true) {
      setState(() => _successMsg = res["message"]?.toString());
      await _load();
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res["error"]?.toString() ?? "Failed")),
      );
    }
  }

  Color _statusColor(String status) {
    switch (status) {
      case 'completed':
        return const Color(0xFF16A34A);
      case 'active':
      case 'assigned':
      case 'processing':
        return sfBlue;
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

    final providerType = _data["provider_type"] ?? "labor";
    final balance = _data["balance"] ?? "0.00";
    final totalJobs = _data["total_jobs"] ?? 0;
    final activeJobs = _data["active_jobs"] ?? 0;
    final completedJobs = _data["completed_jobs"] ?? 0;
    final jobs = List<Map<String, dynamic>>.from(_data["jobs"] ?? []);

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
              expandedHeight: 120,
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
                      const Text(
                        'My Jobs',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 20,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      const SizedBox(height: 4),
                      Row(
                        children: [
                          _chip(
                            providerType[0].toUpperCase() +
                                providerType.substring(1),
                          ),
                          const SizedBox(width: 8),
                          _chip('Balance: $balance EGP'),
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
                  // Success message
                  if (_successMsg != null) ...[
                    Container(
                      padding: const EdgeInsets.all(14),
                      decoration: BoxDecoration(
                        color: const Color(0xFFDCFCE7),
                        borderRadius: BorderRadius.circular(12),
                        border: Border.all(color: const Color(0xFFBBF7D0)),
                      ),
                      child: Row(
                        children: [
                          const Icon(
                            Icons.check_circle_outline,
                            color: Color(0xFF16A34A),
                            size: 18,
                          ),
                          const SizedBox(width: 10),
                          Expanded(
                            child: Text(
                              _successMsg!,
                              style: const TextStyle(
                                color: Color(0xFF166534),
                                fontWeight: FontWeight.w700,
                                fontSize: 13,
                              ),
                            ),
                          ),
                        ],
                      ),
                    ),
                    const SizedBox(height: 16),
                  ],

                  // Stats row
                  Row(
                    children: [
                      Expanded(
                        child: _statCard(
                          'Total',
                          totalJobs.toString(),
                          Icons.assignment_outlined,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _statCard(
                          'Active',
                          activeJobs.toString(),
                          Icons.work_outline_rounded,
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: _statCard(
                          'Completed',
                          completedJobs.toString(),
                          Icons.check_circle_outline_rounded,
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 20),

                  // Jobs list
                  if (jobs.isEmpty)
                    _emptyBox()
                  else
                    ...jobs.map((job) => _jobCard(job)),

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

  Widget _statCard(String label, String value, IconData icon) {
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
        children: [
          Icon(icon, color: sfBlue, size: 16),
          const SizedBox(height: 8),
          Text(
            value,
            style: const TextStyle(
              fontSize: 22,
              fontWeight: FontWeight.w900,
              color: sfText,
            ),
          ),
          const SizedBox(height: 2),
          Text(
            label,
            style: const TextStyle(
              fontSize: 11,
              color: sfMuted,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  Widget _jobCard(Map<String, dynamic> job) {
    final status = job["status"] ?? "";
    final isActive =
        status == "active" || status == "assigned" || status == "processing";
    final jobId = int.tryParse(job["job_id"]?.toString() ?? "") ?? 0;

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
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
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            children: [
              Expanded(
                child: Text(
                  job["title"] ?? "—",
                  style: const TextStyle(
                    fontSize: 15,
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
          const SizedBox(height: 10),
          _infoRow(Icons.location_on_outlined, job["location"] ?? "—"),
          const SizedBox(height: 6),
          _infoRow(
            Icons.payments_outlined,
            '${double.tryParse(job["salary_amount"]?.toString() ?? job["price"]?.toString() ?? "0")?.toStringAsFixed(0) ?? "0"} EGP',
          ),
          if ((job["description"] ?? "").toString().isNotEmpty) ...[
            const SizedBox(height: 8),
            Text(
              job["description"].toString(),
              style: const TextStyle(
                fontSize: 12.5,
                color: sfMuted,
                height: 1.4,
              ),
              maxLines: 2,
              overflow: TextOverflow.ellipsis,
            ),
          ],
          if (isActive) ...[
            const SizedBox(height: 14),
            SizedBox(
              width: double.infinity,
              height: 44,
              child: ElevatedButton(
                onPressed: () => _completeJob(jobId),
                style: ElevatedButton.styleFrom(
                  backgroundColor: sfBlue,
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                ),
                child: const Text(
                  'Mark as Completed',
                  style: TextStyle(
                    color: Colors.white,
                    fontWeight: FontWeight.w800,
                    fontSize: 13.5,
                  ),
                ),
              ),
            ),
          ],
        ],
      ),
    );
  }

  Widget _infoRow(IconData icon, String text) {
    return Row(
      children: [
        Icon(icon, size: 14, color: sfMuted),
        const SizedBox(width: 6),
        Text(text, style: const TextStyle(fontSize: 13, color: sfMuted)),
      ],
    );
  }

  Widget _emptyBox() {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(36),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        children: const [
          Icon(Icons.work_off_outlined, size: 48, color: sfMuted),
          SizedBox(height: 12),
          Text(
            'No Jobs Yet',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              color: sfText,
            ),
          ),
          SizedBox(height: 6),
          Text(
            'Go to Available Jobs and accept your first job.',
            textAlign: TextAlign.center,
            style: TextStyle(color: sfMuted, fontSize: 13.5),
          ),
        ],
      ),
    );
  }
}
