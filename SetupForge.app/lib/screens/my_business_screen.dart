import 'package:flutter/material.dart';
import '../services/api_service.dart';

class MyBusinessScreen extends StatefulWidget {
  const MyBusinessScreen({super.key});

  @override
  State<MyBusinessScreen> createState() => _MyBusinessScreenState();
}

class _MyBusinessScreenState extends State<MyBusinessScreen> {
  static const Color sfBlue = Color(0xFF004CAC);
  static const Color sfBg = Color(0xFFF5F7FB);
  static const Color sfText = Color(0xFF111827);
  static const Color sfMuted = Color(0xFF6B7280);

  final api = ApiService();
  bool _loading = true;
  Map<String, dynamic> _data = {};
  String _setupState = "none";

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    final homeRes = await api.getHomeData();
    final setupState = homeRes["setup_state"]?.toString() ?? "none";
    if (setupState == "completed") {
      final res = await api.getBusinessOverview();
      if (mounted) {
        setState(() {
          _setupState = setupState;
          _data = res;
          _loading = false;
        });
      }
    } else {
      if (mounted) {
        setState(() {
          _setupState = setupState;
          _loading = false;
        });
      }
    }
  }

  String _fmtDate(String? d) {
    if (d == null || d.isEmpty) return '';
    try {
      final dt = DateTime.parse(d);
      const months = [
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
      ];
      return '${months[dt.month - 1]} ${dt.day}, ${dt.year}';
    } catch (_) {
      return d;
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

    if (_setupState != "completed") {
      return Scaffold(
        backgroundColor: sfBg,
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(32),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Container(
                  width: 80,
                  height: 80,
                  decoration: const BoxDecoration(
                    color: Color(0xFFEFF6FF),
                    shape: BoxShape.circle,
                  ),
                  child: const Icon(Icons.store_outlined, color: sfBlue, size: 40),
                ),
                const SizedBox(height: 20),
                const Text(
                  'No Setup Yet',
                  style: TextStyle(
                    fontSize: 20,
                    fontWeight: FontWeight.w900,
                    color: sfText,
                  ),
                ),
                const SizedBox(height: 8),
                const Text(
                  'Start your business setup to get product recommendations, hire staff, and manage installations.',
                  textAlign: TextAlign.center,
                  style: TextStyle(fontSize: 13.5, color: sfMuted, height: 1.5),
                ),
                const SizedBox(height: 28),
                GestureDetector(
                  onTap: () => Navigator.pushNamed(context, '/service-select'),
                  child: Container(
                    width: double.infinity,
                    padding: const EdgeInsets.symmetric(vertical: 15),
                    color: sfBlue,
                    child: const Text(
                      'Start Setup',
                      textAlign: TextAlign.center,
                      style: TextStyle(
                        color: Colors.white,
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

    final businessName = _data["business_name"] ?? "My Business";
    final orderId = _data["order_id"] ?? 0;
    final orderTotal =
        (double.tryParse(_data["order_total"]?.toString() ?? "0") ?? 0);
    final paidAt = _data["paid_at"] ?? "";
    final productsCount = _data["products_count"] ?? 0;
    final totalLabor = _data["total_labor_positions"] ?? 0;
    final installCount = _data["installation_count"] ?? 0;
    final tracker = List<Map<String, dynamic>>.from(_data["tracker"] ?? []);
    final productsByModule = Map<String, dynamic>.from(
      _data["products_by_module"] ?? {},
    );
    final laborJobs = List<Map<String, dynamic>>.from(
      _data["labor_jobs"] ?? [],
    );
    final installationRequests = List<Map<String, dynamic>>.from(
      _data["installation_requests"] ?? [],
    );

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
              pinned: true,
              automaticallyImplyLeading: false,
              expandedHeight: 100,
              flexibleSpace: FlexibleSpaceBar(
                background: Container(
                  color: sfBlue,
                  padding: const EdgeInsets.fromLTRB(20, 50, 20, 16),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    mainAxisAlignment: MainAxisAlignment.end,
                    children: [
                      Text(
                        businessName,
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 20,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                      if (orderId > 0 && paidAt.isNotEmpty)
                        Text(
                          'Paid ${_fmtDate(paidAt)}',
                          style: TextStyle(
                            color: Colors.white.withOpacity(0.7),
                            fontSize: 12,
                            fontWeight: FontWeight.w600,
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
                  // Progress tracker
                  _sectionTitle('Setup Progress'),
                  const SizedBox(height: 12),
                  _progressTracker(tracker),

                  const SizedBox(height: 20),

                  // Stats
                  _sectionTitle('Overview'),
                  const SizedBox(height: 12),
                  GridView.count(
                    crossAxisCount: 2,
                    shrinkWrap: true,
                    physics: const NeverScrollableScrollPhysics(),
                    crossAxisSpacing: 12,
                    mainAxisSpacing: 12,
                    childAspectRatio: 1.6,
                    children: [
                      _statCard(
                        'Setup Total',
                        orderTotal > 0
                            ? '${orderTotal.toStringAsFixed(0)} EGP'
                            : '—',
                        Icons.shopping_bag_outlined,
                      ),
                      _statCard(
                        'Products',
                        productsCount.toString(),
                        Icons.inventory_2_outlined,
                      ),
                      _statCard(
                        'Staff Positions',
                        totalLabor.toString(),
                        Icons.people_outline_rounded,
                      ),
                      _statCard(
                        'Installation',
                        installCount.toString(),
                        Icons.build_outlined,
                      ),
                    ],
                  ),

                  const SizedBox(height: 20),

                  // Action buttons
                  Row(
                    children: [
                      Expanded(
                        child: ElevatedButton.icon(
                          onPressed: () =>
                              Navigator.pushNamed(context, '/services'),
                          icon: const Icon(
                            Icons.people_rounded,
                            size: 16,
                            color: Colors.white,
                          ),
                          label: const Text(
                            'Staff & Installation',
                            style: TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w800,
                              fontSize: 13,
                            ),
                          ),
                          style: ElevatedButton.styleFrom(
                            backgroundColor: sfBlue,
                            elevation: 0,
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            shape: const RoundedRectangleBorder(),
                          ),
                        ),
                      ),
                      const SizedBox(width: 10),
                      Expanded(
                        child: OutlinedButton.icon(
                          onPressed: () {},
                          icon: const Icon(
                            Icons.edit_outlined,
                            size: 16,
                            color: sfBlue,
                          ),
                          label: const Text(
                            'Edit Setup',
                            style: TextStyle(
                              color: sfBlue,
                              fontWeight: FontWeight.w800,
                              fontSize: 13,
                            ),
                          ),
                          style: OutlinedButton.styleFrom(
                            padding: const EdgeInsets.symmetric(vertical: 14),
                            side: const BorderSide(color: sfBlue),
                            shape: const RoundedRectangleBorder(),
                          ),
                        ),
                      ),
                    ],
                  ),

                  const SizedBox(height: 24),

                  // Ordered products
                  _sectionTitle('Ordered Products'),
                  const SizedBox(height: 12),
                  if (productsByModule.isEmpty)
                    _emptyBox(
                      'No products ordered yet',
                      Icons.inventory_2_outlined,
                    )
                  else
                    ...productsByModule.entries.map((entry) {
                      final modKey = entry.key;
                      final modProducts = List<Map<String, dynamic>>.from(
                        entry.value,
                      );
                      return _moduleSection(modKey, modProducts);
                    }),

                  const SizedBox(height: 20),

                  // Labor summary
                  _sectionTitle('Labor Summary'),
                  const SizedBox(height: 12),
                  if (laborJobs.isEmpty)
                    _emptyBox('No labor jobs yet', Icons.people_outline)
                  else
                    ...laborJobs.map((job) => _laborJobCard(job)),

                  const SizedBox(height: 20),

                  // Installation services
                  _sectionTitle('Installation Services'),
                  const SizedBox(height: 12),
                  if (installationRequests.isEmpty)
                    _emptyBox(
                      'No installation requests yet',
                      Icons.build_outlined,
                    )
                  else
                    ...installationRequests.map((ir) => _installationCard(ir)),

                  const SizedBox(height: 32),
                ]),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _sectionTitle(String title) {
    return Text(
      title,
      style: const TextStyle(
        fontSize: 15,
        fontWeight: FontWeight.w800,
        color: sfText,
      ),
    );
  }

  Widget _progressTracker(List<Map<String, dynamic>> steps) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: SingleChildScrollView(
        scrollDirection: Axis.horizontal,
        child: Row(
          children: steps.asMap().entries.map((e) {
            final i = e.key;
            final step = e.value;
            final status = step["status"] ?? "pending";
            final isDone = status == "done";
            final isProgress = status == "progress";
            final nodeBg = isDone
                ? sfBlue
                : isProgress
                ? const Color(0xFFF59E0B)
                : const Color(0xFFE5E7EB);
            final nodeColor = (isDone || isProgress) ? Colors.white : sfMuted;
            final labelColor = (isDone || isProgress) ? sfText : sfMuted;

            return Row(
              children: [
                Column(
                  children: [
                    Container(
                      width: 40,
                      height: 40,
                      decoration: BoxDecoration(
                        color: nodeBg,
                        shape: BoxShape.circle,
                      ),
                      child: Icon(
                        isDone
                            ? Icons.check_rounded
                            : isProgress
                            ? Icons.access_time_rounded
                            : Icons.radio_button_unchecked_rounded,
                        color: nodeColor,
                        size: 18,
                      ),
                    ),
                    const SizedBox(height: 6),
                    SizedBox(
                      width: 72,
                      child: Text(
                        step["label"] ?? "",
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w700,
                          color: labelColor,
                          height: 1.3,
                        ),
                      ),
                    ),
                    Text(
                      isDone
                          ? 'Done'
                          : isProgress
                          ? 'In Progress'
                          : 'Pending',
                      style: TextStyle(
                        fontSize: 9.5,
                        fontWeight: FontWeight.w600,
                        color: isDone
                            ? sfBlue
                            : isProgress
                            ? const Color(0xFFF59E0B)
                            : const Color(0xFFD1D5DB),
                      ),
                    ),
                  ],
                ),
                if (i < steps.length - 1)
                  Container(
                    width: 32,
                    height: 2,
                    margin: const EdgeInsets.only(bottom: 32),
                    color: isDone ? sfBlue : const Color(0xFFE5E7EB),
                  ),
              ],
            );
          }).toList(),
        ),
      ),
    );
  }

  Widget _statCard(String label, String value, IconData icon) {
    return Container(
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
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
          Icon(icon, color: sfBlue, size: 18),
          const SizedBox(height: 8),
          Text(
            value,
            style: const TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w900,
              color: sfText,
            ),
          ),
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

  Widget _moduleSection(String modKey, List<Map<String, dynamic>> products) {
    final moduleLabels = {
      'kitchen': 'Kitchen Equipment',
      'furniture': 'Dining Area',
      'pos': 'POS System',
      'ac': 'AC & Climate',
      'electronics': 'Electronics',
    };
    final moduleColors = {
      'kitchen': const Color(0xFFF97316),
      'furniture': const Color(0xFF22C55E),
      'pos': const Color(0xFF3B82F6),
      'ac': const Color(0xFF06B6D4),
    };
    final label =
        moduleLabels[modKey] ?? modKey[0].toUpperCase() + modKey.substring(1);
    final color = moduleColors[modKey] ?? sfMuted;

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Container(
          margin: const EdgeInsets.only(bottom: 8),
          padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 4),
          decoration: BoxDecoration(color: color.withOpacity(0.1)),
          child: Text(
            label,
            style: TextStyle(
              fontSize: 11.5,
              fontWeight: FontWeight.w800,
              color: color,
            ),
          ),
        ),
        ...products.map((p) => _productCard(p)),
        const SizedBox(height: 12),
      ],
    );
  }

  Widget _productCard(Map<String, dynamic> p) {
    final deliveryStatus = p["delivery_status"] ?? "pending";
    Color dColor;
    String dLabel;
    switch (deliveryStatus) {
      case 'delivered':
        dColor = const Color(0xFF16A34A);
        dLabel = 'Delivered';
        break;
      case 'processing':
        dColor = sfBlue;
        dLabel = 'Processing';
        break;
      default:
        dColor = const Color(0xFFB45309);
        dLabel = 'Pending';
    }

    final qty = int.tryParse(p["quantity"]?.toString() ?? "1") ?? 1;
    final unitPrice = double.tryParse(p["unit_price"]?.toString() ?? "0") ?? 0;
    final lineTotal = qty * unitPrice;
    final imageUrl = p["image_url"]?.toString();

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Row(
        children: [
          // Image
          Container(
            width: 56,
            height: 56,
            decoration: const BoxDecoration(color: Color(0xFFF3F4F6)),
            child: imageUrl != null && imageUrl.isNotEmpty
                ? Image.network(
                    imageUrl,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) => const Icon(
                      Icons.inventory_2_outlined,
                      color: sfMuted,
                      size: 24,
                    ),
                  )
                : const Icon(
                    Icons.inventory_2_outlined,
                    color: sfMuted,
                    size: 24,
                  ),
          ),
          const SizedBox(width: 12),
          // Info
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  p["product_name"] ?? "—",
                  style: const TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w700,
                    color: sfText,
                  ),
                ),
                if ((p["brand"] ?? "").toString().isNotEmpty)
                  Text(
                    p["brand"].toString(),
                    style: const TextStyle(fontSize: 11.5, color: sfMuted),
                  ),
                Text(
                  '×$qty · ${lineTotal.toStringAsFixed(0)} EGP',
                  style: const TextStyle(
                    fontSize: 12,
                    color: sfMuted,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),
          // Delivery badge
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(color: dColor.withOpacity(0.1)),
            child: Text(
              dLabel,
              style: TextStyle(
                fontSize: 10.5,
                fontWeight: FontWeight.w700,
                color: dColor,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _laborJobCard(Map<String, dynamic> job) {
    final total = int.tryParse(job["total_openings"]?.toString() ?? "1") ?? 1;
    final filled = int.tryParse(job["filled_openings"]?.toString() ?? "0") ?? 0;
    final pct = total > 0 ? filled / total : 0.0;
    final isFull = filled >= total;

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: const Color(0xFFE5E7EB)),
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
                    fontSize: 13.5,
                    fontWeight: FontWeight.w700,
                    color: sfText,
                  ),
                ),
              ),
              Container(
                padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 3),
                decoration: BoxDecoration(
                  color: isFull
                      ? const Color(0xFFDCFCE7)
                      : const Color(0xFFDBEAFE),
                ),
                child: Text(
                  isFull ? 'Full' : 'Hiring',
                  style: TextStyle(
                    fontSize: 10.5,
                    fontWeight: FontWeight.w700,
                    color: isFull ? const Color(0xFF16A34A) : sfBlue,
                  ),
                ),
              ),
            ],
          ),
          if ((job["location"] ?? "").toString().isNotEmpty) ...[
            const SizedBox(height: 4),
            Text(
              job["location"].toString(),
              style: const TextStyle(fontSize: 12, color: sfMuted),
            ),
          ],
          const SizedBox(height: 10),
          ClipRRect(
            child: LinearProgressIndicator(
              value: pct.toDouble(),
              backgroundColor: const Color(0xFFE5E7EB),
              valueColor: const AlwaysStoppedAnimation<Color>(sfBlue),
              minHeight: 6,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            '$filled of $total filled',
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

  Widget _installationCard(Map<String, dynamic> ir) {
    final status = (ir["status"] ?? "pending").toString().toLowerCase();
    Color stColor;
    String stLabel;
    switch (status) {
      case 'accepted':
        stColor = const Color(0xFF16A34A);
        stLabel = 'Accepted';
        break;
      case 'rejected':
        stColor = const Color(0xFFDC2626);
        stLabel = 'Rejected';
        break;
      default:
        stColor = sfMuted;
        stLabel = status[0].toUpperCase() + status.substring(1);
    }

    final services = (ir["services"] ?? "")
        .toString()
        .replaceAll(RegExp(r'[{}]'), '')
        .split(',')
        .map((s) => s.trim())
        .where((s) => s.isNotEmpty)
        .map((s) {
          const labels = {
            'electrical': 'Electrical',
            'ac': 'AC & Climate',
            'kitchen': 'Kitchen Install',
            'pos': 'POS Setup',
            'network': 'Network & WiFi',
          };
          return labels[s] ?? s[0].toUpperCase() + s.substring(1);
        })
        .join(', ');

    return Container(
      margin: const EdgeInsets.only(bottom: 8),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Row(
        children: [
          Container(
            width: 42,
            height: 42,
            decoration: const BoxDecoration(color: Color(0xFFEFF6FF)),
            child: const Icon(Icons.build_outlined, color: sfBlue, size: 20),
          ),
          const SizedBox(width: 12),
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  services.isNotEmpty ? services : 'Installation',
                  style: const TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w700,
                    color: sfText,
                  ),
                ),
                if ((ir["company_name"] ?? "").toString().isNotEmpty)
                  Text(
                    ir["company_name"].toString(),
                    style: const TextStyle(fontSize: 12, color: sfMuted),
                  ),
                if ((ir["scheduled_date"] ?? "").toString().isNotEmpty)
                  Text(
                    'Scheduled: ${_fmtDate(ir["scheduled_date"])}',
                    style: const TextStyle(
                      fontSize: 11.5,
                      fontWeight: FontWeight.w700,
                      color: sfBlue,
                    ),
                  ),
              ],
            ),
          ),
          Container(
            padding: const EdgeInsets.symmetric(horizontal: 8, vertical: 4),
            decoration: BoxDecoration(color: stColor.withOpacity(0.1)),
            child: Text(
              stLabel,
              style: TextStyle(
                fontSize: 10.5,
                fontWeight: FontWeight.w700,
                color: stColor,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _emptyBox(String text, IconData icon) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(24),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        children: [
          Icon(icon, size: 36, color: sfMuted),
          const SizedBox(height: 8),
          Text(
            text,
            style: const TextStyle(
              fontSize: 13.5,
              color: sfMuted,
              fontWeight: FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }
}
