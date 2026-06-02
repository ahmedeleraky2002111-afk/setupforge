import 'package:flutter/material.dart';
import '../services/api_service.dart';

class OrderSummaryScreen extends StatefulWidget {
  const OrderSummaryScreen({super.key});

  @override
  State<OrderSummaryScreen> createState() => _OrderSummaryScreenState();
}

class _OrderSummaryScreenState extends State<OrderSummaryScreen> {
  static const Color sfBlue = Color(0xFF004CAC);
  static const Color sfBg = Color(0xFFF5F7FB);
  static const Color sfText = Color(0xFF111827);
  static const Color sfMuted = Color(0xFF6B7280);

  final api = ApiService();
  bool _confirming = false;

  // Passed from packages screen via arguments
  Map<String, dynamic> _data = {};
  Map<String, List<Map<String, dynamic>>> _localItems = {};
  int _grandTotal = 0;

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final args = ModalRoute.of(context)?.settings.arguments as Map?;
    if (args != null) {
      _data = Map<String, dynamic>.from(args['data'] ?? {});
      _localItems = Map<String, List<Map<String, dynamic>>>.from(
        (args['localItems'] as Map? ?? {}).map(
          (k, v) =>
              MapEntry(k as String, List<Map<String, dynamic>>.from(v as List)),
        ),
      );
      _grandTotal = args['grandTotal'] as int? ?? 0;
    }
  }

  Future<void> _placeOrder() async {
    setState(() => _confirming = true);
    try {
      final res = await api.placeSetupOrder();
      if (!mounted) return;
      if (res["ok"] == true) {
        Navigator.pushNamed(
          context,
          '/setup-payment',
          arguments: {
            'iframe_url': res["iframe_url"].toString(),
            'order_id': res["order_id"] as int,
          },
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(res["error"]?.toString() ?? "Failed to place order"),
            backgroundColor: Colors.red,
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text("Something went wrong. Please try again."),
          backgroundColor: Colors.red,
        ),
      );
    } finally {
      if (mounted) setState(() => _confirming = false);
    }
  }

  int _itemTotal(Map<String, dynamic> item) {
    final unit = item["unit"];
    final qty = item["qty"];
    final u = unit is int ? unit : int.tryParse(unit.toString()) ?? 0;
    final q = qty is int ? qty : int.tryParse(qty.toString()) ?? 0;
    return u * q;
  }

  String _fmt(int n) =>
      '${n.toString().replaceAllMapped(RegExp(r'(\d)(?=(\d{3})+$)'), (m) => '${m[1]},')} EGP';

  @override
  Widget build(BuildContext context) {
    final modules = List<String>.from(_data["modules"] ?? []);
    final tier = _data["tier"]?.toString() ?? "";
    final budget = _data["budget"] as int? ?? 0;

    const moduleLabels = {
      "kitchen": "Kitchen",
      "pos": "POS & Tech",
      "furniture": "Dining Area",
      "ac": "Climate",
    };

    const sectionLabels = {
      "terminal": "POS Terminals",
      "printer": "Receipt Printers",
      "drawer": "Cash Drawers",
      "software": "POS Software",
      "scanner": "Barcode Scanners",
      "kds": "Kitchen Display",
      "tablet": "Ordering Tablets",
      "oven": "Ovens",
      "fryer": "Fryers",
      "microwave": "Microwaves",
      "fridge": "Fridges",
      "freezer": "Freezers",
      "blender": "Blenders",
      "grill": "Grills",
      "mixer": "Mixers",
      "coffee": "Coffee Machines",
      "dining_set_2": "2-Seat Sets",
      "dining_set_4": "4-Seat Sets",
      "dining_set_6": "6-Seat Sets",
      "dining_set_8": "8-Seat Sets",
      "dining_set_10": "10-Seat Sets",
      "dining_set_12": "12-Seat Sets",
      "tv": "TVs",
      "ac": "AC Units",
      "exhaust_fan": "Exhaust Fans",
      "ceiling_fan": "Ceiling Fans",
      "air_curtain": "Air Curtains",
    };

    return Scaffold(
      backgroundColor: sfBg,
      appBar: AppBar(
        backgroundColor: sfBlue,
        foregroundColor: Colors.white,
        title: const Text(
          'Order Summary',
          style: TextStyle(fontWeight: FontWeight.w900, fontSize: 17),
        ),
        elevation: 0,
        shape: const RoundedRectangleBorder(),
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.fromLTRB(16, 16, 16, 100),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // ── Header card ──
            Container(
              width: double.infinity,
              padding: const EdgeInsets.all(20),
              color: sfBlue,
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    '$tier Tier · ${_fmt(budget)} budget',
                    style: TextStyle(
                      color: Colors.white.withOpacity(0.8),
                      fontSize: 12,
                      fontWeight: FontWeight.w600,
                    ),
                  ),
                  const SizedBox(height: 8),
                  const Text(
                    'Review your setup before payment',
                    style: TextStyle(
                      color: Colors.white,
                      fontSize: 15,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                  const SizedBox(height: 16),
                  Row(
                    mainAxisAlignment: MainAxisAlignment.spaceBetween,
                    children: [
                      const Text(
                        'Grand Total',
                        style: TextStyle(
                          color: Colors.white70,
                          fontSize: 12,
                          fontWeight: FontWeight.w600,
                        ),
                      ),
                      Text(
                        _fmt(_grandTotal),
                        style: const TextStyle(
                          color: Colors.white,
                          fontSize: 22,
                          fontWeight: FontWeight.w900,
                        ),
                      ),
                    ],
                  ),
                ],
              ),
            ),

            const SizedBox(height: 16),

            // ── Module sections ──
            ...modules.map((module) {
              final items = (_localItems[module] ?? [])
                  .where((i) => i["is_notice"] != true)
                  .toList();
              if (items.isEmpty) return const SizedBox.shrink();

              int moduleTotal = 0;
              for (final item in items) moduleTotal += _itemTotal(item);

              return Container(
                margin: const EdgeInsets.only(bottom: 12),
                decoration: BoxDecoration(
                  color: Colors.white,
                  border: Border.all(color: const Color(0xFFE5E7EB)),
                ),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    // Module header
                    Container(
                      padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
                      decoration: const BoxDecoration(
                        border: Border(
                          bottom: BorderSide(color: Color(0xFFE5E7EB)),
                        ),
                      ),
                      child: Row(
                        children: [
                          Expanded(
                            child: Text(
                              moduleLabels[module] ?? module,
                              style: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w800,
                                color: sfBlue,
                              ),
                            ),
                          ),
                          Text(
                            _fmt(moduleTotal),
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w900,
                              color: sfText,
                            ),
                          ),
                        ],
                      ),
                    ),

                    // Items
                    ...items.map((item) {
                      final type = item["type"] as String? ?? "";
                      final name = item["name"]?.toString() ?? "—";
                      final brand = item["brand"]?.toString() ?? "";
                      final unit = item["unit"] is int
                          ? item["unit"] as int
                          : int.tryParse(item["unit"].toString()) ?? 0;
                      final qty = item["qty"] is int
                          ? item["qty"] as int
                          : int.tryParse(item["qty"].toString()) ?? 0;
                      final imageUrl = item["image_url"]?.toString();
                      final label =
                          sectionLabels[type] ??
                          type
                              .replaceAll("_", " ")
                              .split(" ")
                              .map(
                                (w) => w.isNotEmpty
                                    ? w[0].toUpperCase() + w.substring(1)
                                    : w,
                              )
                              .join(" ");

                      return Container(
                        padding: const EdgeInsets.fromLTRB(14, 12, 14, 12),
                        decoration: const BoxDecoration(
                          border: Border(
                            bottom: BorderSide(color: Color(0xFFF3F4F6)),
                          ),
                        ),
                        child: Row(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            // Image
                            Container(
                              width: 56,
                              height: 56,
                              color: const Color(0xFFF3F4F6),
                              child: imageUrl != null && imageUrl.isNotEmpty
                                  ? Image.network(
                                      imageUrl,
                                      fit: BoxFit.contain,
                                      errorBuilder: (_, __, ___) => const Icon(
                                        Icons.inventory_2_outlined,
                                        color: sfMuted,
                                        size: 22,
                                      ),
                                    )
                                  : const Icon(
                                      Icons.inventory_2_outlined,
                                      color: sfMuted,
                                      size: 22,
                                    ),
                            ),
                            const SizedBox(width: 12),
                            // Info
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    label,
                                    style: const TextStyle(
                                      fontSize: 11,
                                      fontWeight: FontWeight.w600,
                                      color: sfMuted,
                                    ),
                                  ),
                                  const SizedBox(height: 2),
                                  Text(
                                    name,
                                    style: const TextStyle(
                                      fontSize: 13,
                                      fontWeight: FontWeight.w700,
                                      color: sfText,
                                    ),
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                  if (brand.isNotEmpty)
                                    Text(
                                      brand,
                                      style: const TextStyle(
                                        fontSize: 11,
                                        color: sfMuted,
                                      ),
                                    ),
                                  const SizedBox(height: 4),
                                  Text(
                                    '$qty × ${_fmt(unit)}',
                                    style: const TextStyle(
                                      fontSize: 11,
                                      color: sfMuted,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(width: 8),
                            // Line total
                            Text(
                              _fmt(_itemTotal(item)),
                              style: const TextStyle(
                                fontSize: 13,
                                fontWeight: FontWeight.w900,
                                color: sfText,
                              ),
                            ),
                          ],
                        ),
                      );
                    }),
                  ],
                ),
              );
            }),

            // ── Grand total row ──
            Container(
              padding: const EdgeInsets.all(16),
              color: Colors.white,
              child: Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Grand Total',
                    style: TextStyle(
                      fontSize: 15,
                      fontWeight: FontWeight.w900,
                      color: sfText,
                    ),
                  ),
                  Text(
                    _fmt(_grandTotal),
                    style: const TextStyle(
                      fontSize: 18,
                      fontWeight: FontWeight.w900,
                      color: sfBlue,
                    ),
                  ),
                ],
              ),
            ),

            const SizedBox(height: 8),

            // ── Notice ──
            Container(
              padding: const EdgeInsets.all(14),
              decoration: BoxDecoration(
                color: const Color(0xFFFFFBEB),
                border: Border.all(color: const Color(0xFFFCD34D)),
              ),
              child: const Row(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Icon(
                    Icons.info_outline_rounded,
                    color: Color(0xFFF59E0B),
                    size: 18,
                  ),
                  SizedBox(width: 10),
                  Expanded(
                    child: Text(
                      'You will be redirected to a secure payment page. Your order will be confirmed after payment.',
                      style: TextStyle(
                        fontSize: 12.5,
                        color: Color(0xFF92400E),
                        height: 1.4,
                      ),
                    ),
                  ),
                ],
              ),
            ),
          ],
        ),
      ),

      // ── Bottom bar ──
      bottomNavigationBar: Container(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 28),
        decoration: BoxDecoration(
          color: Colors.white,
          border: const Border(top: BorderSide(color: Color(0xFFE5E7EB))),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.05),
              blurRadius: 10,
              offset: const Offset(0, -4),
            ),
          ],
        ),
        child: Row(
          children: [
            OutlinedButton(
              onPressed: () => Navigator.pop(context),
              style: OutlinedButton.styleFrom(
                foregroundColor: sfMuted,
                side: const BorderSide(color: Color(0xFFE5E7EB)),
                shape: const RoundedRectangleBorder(),
                padding: const EdgeInsets.symmetric(
                  horizontal: 24,
                  vertical: 14,
                ),
              ),
              child: const Text(
                'Back',
                style: TextStyle(fontWeight: FontWeight.w700),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: ElevatedButton(
                onPressed: _confirming ? null : _placeOrder,
                style: ElevatedButton.styleFrom(
                  backgroundColor: sfBlue,
                  padding: const EdgeInsets.symmetric(vertical: 14),
                  shape: const RoundedRectangleBorder(),
                ),
                child: _confirming
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          color: Colors.white,
                          strokeWidth: 2,
                        ),
                      )
                    : const Text(
                        'Proceed to Payment →',
                        style: TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w800,
                          fontSize: 14,
                        ),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }
}
