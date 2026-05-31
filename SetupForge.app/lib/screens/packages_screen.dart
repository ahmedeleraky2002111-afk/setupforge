import 'package:flutter/material.dart';
import '../services/api_service.dart';
import 'dart:convert';

class PackagesScreen extends StatefulWidget {
  const PackagesScreen({super.key});

  @override
  State<PackagesScreen> createState() => _PackagesScreenState();
}

class _PackagesScreenState extends State<PackagesScreen> {
  static const Color sfBlue = Color(0xFF004CAC);
  static const Color sfBg = Color(0xFFF5F7FB);
  static const Color sfText = Color(0xFF111827);
  static const Color sfMuted = Color(0xFF6B7280);

  final api = ApiService();
  bool _loading = true;
  bool _confirming = false;
  Map<String, dynamic> _data = {};
  String _activeModule = '';
  Map<String, List<Map<String, dynamic>>> _localItems = {};

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    final res = await api.getPackages();
    if (!mounted) return;
    if (res["ok"] == true) {
      final carts = Map<String, dynamic>.from(res["carts"] ?? {});
      final modules = List<String>.from(res["modules"] ?? []);
      final localItems = <String, List<Map<String, dynamic>>>{};
      for (final m in modules) {
        final cart = carts[m] as Map<String, dynamic>?;
        localItems[m] = List<Map<String, dynamic>>.from(cart?["items"] ?? []);
      }
      setState(() {
        _data = res;
        _activeModule = modules.isNotEmpty ? modules.first : '';
        _localItems = localItems;
        _loading = false;
      });
    } else {
      setState(() {
        _data = res;
        _loading = false;
      });
    }
  }

  List<Map<String, dynamic>> get _activeItems =>
      _localItems[_activeModule] ?? [];

  Map<String, dynamic>? get _activeCart {
    final carts = Map<String, dynamic>.from(_data["carts"] ?? {});
    final c = carts[_activeModule];
    if (c == null) return null;
    return Map<String, dynamic>.from(c);
  }

  int get _activeTotal {
    return _activeItems.fold(0, (sum, item) {
      if (item["is_notice"] == true) return sum;
      return sum + (item["qty"] as int? ?? 0) * (item["unit"] as int? ?? 0);
    });
  }

  int get _grandTotal {
    int total = 0;
    for (final m in _localItems.keys) {
      for (final item in _localItems[m]!) {
        if (item["is_notice"] == true) continue;
        total += (item["qty"] as int? ?? 0) * (item["unit"] as int? ?? 0);
      }
    }
    return total;
  }

  Future<void> _updateQty(String type, int newQty) async {
    setState(() {
      final items = _localItems[_activeModule]!;
      for (final item in items) {
        if (item["type"] == type) {
          item["qty"] = newQty;
          break;
        }
      }
    });
    await api.packagesAction(
      action: "update_qty",
      module: _activeModule,
      type: type,
      qty: newQty,
    );
  }

  Future<void> _replaceProduct(String type, String productId) async {
    await api.packagesAction(
      action: "replace_product",
      module: _activeModule,
      type: type,
      productId: productId,
    );
    await _load();
  }

  Future<void> _addProduct(String type, Map<String, dynamic> product) async {
    setState(() {
      final items = _localItems[_activeModule]!;
      // Check if type already exists
      final existing = items.where((i) => i["type"] == type).toList();
      if (existing.isEmpty) {
        items.add({
          "type": type,
          "product_id": product["id"],
          "name": product["name"],
          "unit": product["price"],
          "qty": 1,
          "image_url": product["image_url"],
          "brand": product["brand"],
          "vendor_name": product["vendor_name"],
          "avg_rating": product["avg_rating"],
          "alternatives": [],
        });
      }
    });
    await api.packagesAction(
      action: "add_product",
      module: _activeModule,
      type: type,
      productId: product["id"].toString(),
    );
  }

  void _showAddProductSheet(String type) {
    showModalBottomSheet(
      context: context,
      isScrollControlled: true,
      backgroundColor: Colors.white,
      shape: const RoundedRectangleBorder(),
      builder: (ctx) => _AddProductSheet(
        api: api,
        module: _activeModule,
        type: type,
        onAdd: (product) {
          Navigator.pop(ctx);
          _addProduct(type, product);
        },
      ),
    );
  }

  Future<void> _confirmSetup() async {
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
                _data["error"]?.toString() ?? "Failed to load packages",
                style: const TextStyle(color: sfMuted),
                textAlign: TextAlign.center,
              ),
              const SizedBox(height: 16),
              GestureDetector(
                onTap: _load,
                child: Container(
                  padding: const EdgeInsets.symmetric(
                    horizontal: 24,
                    vertical: 12,
                  ),
                  color: sfBlue,
                  child: const Text(
                    'Retry',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w700,
                    ),
                  ),
                ),
              ),
            ],
          ),
        ),
      );
    }

    final modules = List<String>.from(_data["modules"] ?? []);
    final carts = Map<String, dynamic>.from(_data["carts"] ?? {});
    final tier = _data["tier"]?.toString() ?? "";

    const moduleLabels = {
      "kitchen": "Kitchen",
      "pos": "POS & Tech",
      "furniture": "Dining Area",
      "ac": "AC",
    };

    return Scaffold(
      backgroundColor: sfBg,
      appBar: AppBar(
        backgroundColor: sfBlue,
        foregroundColor: Colors.white,
        title: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'My Packages',
              style: TextStyle(fontWeight: FontWeight.w900, fontSize: 17),
            ),
            Text(
              '$tier Tier · ${_formatEgp(_data["budget"] as int? ?? 0)} budget',
              style: TextStyle(
                color: Colors.white.withOpacity(0.8),
                fontSize: 12,
                fontWeight: FontWeight.w500,
              ),
            ),
          ],
        ),
        elevation: 0,
        shape: const RoundedRectangleBorder(),
      ),
      body: Column(
        children: [
          // Module tabs
          Container(
            color: sfBlue,
            child: SingleChildScrollView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.fromLTRB(12, 0, 12, 12),
              child: Row(
                children: modules.map((m) {
                  final isActive = m == _activeModule;
                  final cart = carts[m] as Map<String, dynamic>?;
                  final total = (cart?["total"] as int?) ?? 0;
                  final cap = (cart?["cap"] as int?) ?? 0;
                  final over = total > cap;

                  return GestureDetector(
                    onTap: () => setState(() => _activeModule = m),
                    child: Container(
                      margin: const EdgeInsets.only(right: 8),
                      padding: const EdgeInsets.symmetric(
                        horizontal: 16,
                        vertical: 8,
                      ),
                      decoration: BoxDecoration(
                        color: isActive
                            ? Colors.white
                            : Colors.white.withOpacity(0.15),
                        border: Border.all(
                          color: isActive
                              ? Colors.white
                              : Colors.white.withOpacity(0.3),
                        ),
                      ),
                      child: Column(
                        children: [
                          Text(
                            moduleLabels[m] ?? m,
                            style: TextStyle(
                              fontSize: 12.5,
                              fontWeight: FontWeight.w800,
                              color: isActive ? sfBlue : Colors.white,
                            ),
                          ),
                          if (total > 0)
                            Text(
                              _formatEgp(total),
                              style: TextStyle(
                                fontSize: 10,
                                fontWeight: FontWeight.w600,
                                color: isActive
                                    ? (over ? Colors.red : sfBlue)
                                    : Colors.white.withOpacity(0.8),
                              ),
                            ),
                        ],
                      ),
                    ),
                  );
                }).toList(),
              ),
            ),
          ),

          // Content
          Expanded(
            child: RefreshIndicator(
              color: sfBlue,
              onRefresh: _load,
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                child: Column(
                  children: [
                    // Budget progress bar
                    if (_activeCart != null) _budgetBar(_activeCart!),

                    // AC notice
                    if (_activeModule == "ac" &&
                        _activeItems.any((i) => i["is_notice"] == true))
                      _acNotice(),

                    // Sections
                    if (_activeItems
                        .where((i) => i["is_notice"] != true)
                        .isEmpty)
                      Padding(
                        padding: const EdgeInsets.all(40),
                        child: Column(
                          children: const [
                            Icon(
                              Icons.inventory_2_outlined,
                              color: sfMuted,
                              size: 48,
                            ),
                            SizedBox(height: 12),
                            Text(
                              'No products yet',
                              style: TextStyle(
                                color: sfMuted,
                                fontSize: 15,
                                fontWeight: FontWeight.w600,
                              ),
                            ),
                          ],
                        ),
                      )
                    else
                      ..._activeItems
                          .where((i) => i["is_notice"] != true)
                          .map((item) => _sectionCard(item)),

                    // Add new section button
                    Padding(
                      padding: const EdgeInsets.fromLTRB(16, 8, 16, 16),
                      child: GestureDetector(
                        onTap: () => _showAddProductSheet(''),
                        child: Container(
                          width: double.infinity,
                          padding: const EdgeInsets.symmetric(vertical: 14),
                          decoration: BoxDecoration(
                            color: Colors.white,
                            border: Border.all(
                              color: sfBlue,
                              style: BorderStyle.solid,
                            ),
                          ),
                          child: const Row(
                            mainAxisAlignment: MainAxisAlignment.center,
                            children: [
                              Icon(Icons.add_rounded, color: sfBlue, size: 18),
                              SizedBox(width: 6),
                              Text(
                                'Add Product',
                                style: TextStyle(
                                  color: sfBlue,
                                  fontWeight: FontWeight.w700,
                                  fontSize: 13.5,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ),
                    ),

                    const SizedBox(height: 80),
                  ],
                ),
              ),
            ),
          ),

          // Bottom bar
          _bottomBar(),
        ],
      ),
    );
  }

  Widget _budgetBar(Map<String, dynamic> cart) {
    final total = (cart["total"] as int?) ?? 0;
    final cap = (cart["cap"] as int?) ?? 0;
    final over = total > cap;
    final pct = cap > 0 ? (total / cap).clamp(0.0, 1.0) : 0.0;

    return Container(
      color: Colors.white,
      padding: const EdgeInsets.all(16),
      margin: const EdgeInsets.only(bottom: 8),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Row(
            mainAxisAlignment: MainAxisAlignment.spaceBetween,
            children: [
              Text(
                _formatEgp(total),
                style: const TextStyle(
                  fontSize: 15,
                  fontWeight: FontWeight.w900,
                  color: sfText,
                ),
              ),
              Text(
                over
                    ? '${_formatEgp(total - cap)} over'
                    : '${_formatEgp(cap - total)} left',
                style: TextStyle(
                  fontSize: 12,
                  fontWeight: FontWeight.w600,
                  color: over ? Colors.red : sfMuted,
                ),
              ),
            ],
          ),
          const SizedBox(height: 6),
          ClipRect(
            child: LinearProgressIndicator(
              value: pct,
              backgroundColor: const Color(0xFFE5E7EB),
              valueColor: AlwaysStoppedAnimation<Color>(
                over ? Colors.red : sfBlue,
              ),
              minHeight: 6,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            'Budget: ${_formatEgp(cap)}',
            style: const TextStyle(fontSize: 11, color: sfMuted),
          ),
        ],
      ),
    );
  }

  Widget _acNotice() {
    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 8),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: const Color(0xFFFFFBEB),
        border: Border.all(color: const Color(0xFFFCD34D)),
      ),
      child: Row(
        children: [
          const Icon(
            Icons.warning_amber_rounded,
            color: Color(0xFFF59E0B),
            size: 20,
          ),
          const SizedBox(width: 10),
          const Expanded(
            child: Text(
              'Your space requires a central AC system. Contact an HVAC company for proper assessment.',
              style: TextStyle(
                fontSize: 12.5,
                color: Color(0xFF92400E),
                height: 1.4,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _sectionCard(Map<String, dynamic> item) {
    final type = item["type"] as String? ?? "";
    final qty = item["qty"] as int? ?? 1;
    final unit = item["unit"] as int? ?? 0;
    final alts = List<Map<String, dynamic>>.from(item["alternatives"] ?? []);
    final imageUrl = item["image_url"]?.toString();
    final rating = (item["avg_rating"] as num?)?.toDouble();

    const sectionLabels = {
      "terminal": "POS Terminals",
      "printer": "Receipt Printers",
      "drawer": "Cash Drawers",
      "software": "POS Software",
      "scanner": "Barcode Scanners",
      "kds": "Kitchen Display",
      "oven": "Ovens",
      "fryer": "Fryers",
      "microwave": "Microwaves",
      "fridge": "Fridges",
      "freezer": "Freezers",
      "blender": "Blenders",
      "grill": "Grills",
      "mixer": "Mixers",
      "coffee": "Coffee Machines",
      "dining_set_2": "2-Seater Dining Sets",
      "dining_set_4": "4-Seater Dining Sets",
      "dining_set_6": "6-Seater Dining Sets",
      "dining_set_8": "8-Seater Dining Sets",
      "dining_set_10": "10-Seater Dining Sets",
      "dining_set_12": "12-Seater Dining Sets",
      "tv": "TVs",
      "ac": "AC Units",
    };

    final label =
        sectionLabels[type] ??
        type
            .replaceAll("_", " ")
            .split(" ")
            .map((w) => w.isNotEmpty ? w[0].toUpperCase() + w.substring(1) : w)
            .join(" ");

    return Container(
      margin: const EdgeInsets.fromLTRB(16, 0, 16, 12),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Section header
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 0),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    label,
                    style: const TextStyle(
                      fontSize: 13,
                      fontWeight: FontWeight.w800,
                      color: sfText,
                    ),
                  ),
                ),
                Text(
                  '${alts.length + 1} option${alts.length + 1 != 1 ? "s" : ""}',
                  style: const TextStyle(fontSize: 11.5, color: sfMuted),
                ),
              ],
            ),
          ),

          const SizedBox(height: 10),

          // Horizontal cards slider
          SizedBox(
            height: 220,
            child: ListView(
              scrollDirection: Axis.horizontal,
              padding: const EdgeInsets.fromLTRB(14, 0, 14, 0),
              children: [
                // Recommended card
                _productCard(
                  item: item,
                  isRecommended: true,
                  onQtyChange: (delta) {
                    final newQty = (qty + delta).clamp(0, 99);
                    _updateQty(type, newQty);
                  },
                  onReplace: null,
                ),
                // Alternative cards
                ...alts.map(
                  (alt) => _productCard(
                    item: alt,
                    isRecommended: false,
                    onQtyChange: null,
                    onReplace: () =>
                        _replaceProduct(type, alt["id"].toString()),
                  ),
                ),
                // Add card
                GestureDetector(
                  onTap: () => _showAddProductSheet(type),
                  child: Container(
                    width: 120,
                    margin: const EdgeInsets.only(left: 10),
                    decoration: BoxDecoration(
                      color: const Color(0xFFEFF6FF),
                      border: Border.all(
                        color: sfBlue,
                        style: BorderStyle.solid,
                      ),
                    ),
                    child: const Column(
                      mainAxisAlignment: MainAxisAlignment.center,
                      children: [
                        Icon(Icons.add_circle_outline, color: sfBlue, size: 28),
                        SizedBox(height: 8),
                        Text(
                          'Add',
                          style: TextStyle(
                            color: sfBlue,
                            fontWeight: FontWeight.w700,
                            fontSize: 13,
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              ],
            ),
          ),

          const SizedBox(height: 12),

          // Line total
          Padding(
            padding: const EdgeInsets.fromLTRB(14, 0, 14, 12),
            child: Row(
              mainAxisAlignment: MainAxisAlignment.spaceBetween,
              children: [
                Text(
                  '$qty × ${_formatEgp(unit)}',
                  style: const TextStyle(fontSize: 12, color: sfMuted),
                ),
                Text(
                  _formatEgp(qty * unit),
                  style: const TextStyle(
                    fontSize: 14,
                    fontWeight: FontWeight.w900,
                    color: sfText,
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _productCard({
    required Map<String, dynamic> item,
    required bool isRecommended,
    required void Function(int)? onQtyChange,
    required VoidCallback? onReplace,
  }) {
    final name = item["name"]?.toString() ?? "—";
    final price = (item["unit"] ?? item["price"]) as int? ?? 0;
    final qty = item["qty"] as int? ?? 1;
    final imageUrl = item["image_url"]?.toString();
    final brand = item["brand"]?.toString() ?? "";
    final rating = (item["avg_rating"] as num?)?.toDouble();

    return Container(
      width: 150,
      margin: EdgeInsets.only(right: isRecommended ? 10 : 10),
      decoration: BoxDecoration(
        color: isRecommended ? const Color(0xFFEFF6FF) : Colors.white,
        border: Border.all(
          color: isRecommended ? sfBlue : const Color(0xFFE5E7EB),
          width: isRecommended ? 2 : 1,
        ),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Recommended badge
          if (isRecommended)
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 3),
              color: sfBlue,
              child: const Text(
                'Recommended',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 9.5,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),

          // Image
          Container(
            height: 80,
            color: const Color(0xFFF9FAFB),
            child: imageUrl != null && imageUrl.isNotEmpty
                ? Image.network(
                    imageUrl,
                    fit: BoxFit.cover,
                    width: double.infinity,
                    errorBuilder: (_, __, ___) =>
                        const Icon(Icons.inventory_2_outlined, color: sfMuted),
                  )
                : const Center(
                    child: Icon(
                      Icons.inventory_2_outlined,
                      color: sfMuted,
                      size: 28,
                    ),
                  ),
          ),

          Expanded(
            child: Padding(
              padding: const EdgeInsets.all(8),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    name,
                    style: const TextStyle(
                      fontSize: 11.5,
                      fontWeight: FontWeight.w700,
                      color: sfText,
                    ),
                    maxLines: 2,
                    overflow: TextOverflow.ellipsis,
                  ),
                  if (brand.isNotEmpty)
                    Text(
                      brand,
                      style: const TextStyle(fontSize: 10, color: sfMuted),
                      maxLines: 1,
                      overflow: TextOverflow.ellipsis,
                    ),
                  const Spacer(),
                  Text(
                    _formatEgp(price),
                    style: const TextStyle(
                      fontSize: 12,
                      fontWeight: FontWeight.w900,
                      color: sfBlue,
                    ),
                  ),
                  if (rating != null)
                    Row(
                      children: [
                        const Icon(
                          Icons.star_rounded,
                          size: 10,
                          color: Color(0xFFF59E0B),
                        ),
                        Text(
                          rating.toStringAsFixed(1),
                          style: const TextStyle(fontSize: 10, color: sfMuted),
                        ),
                      ],
                    ),
                  const SizedBox(height: 4),
                  // Action button
                  if (isRecommended && onQtyChange != null)
                    Row(
                      children: [
                        _qtyBtn(
                          Icons.remove,
                          qty > 0 ? () => onQtyChange(-1) : null,
                        ),
                        Expanded(
                          child: Text(
                            '$qty',
                            textAlign: TextAlign.center,
                            style: const TextStyle(
                              fontSize: 13,
                              fontWeight: FontWeight.w800,
                              color: sfText,
                            ),
                          ),
                        ),
                        _qtyBtn(Icons.add, () => onQtyChange(1)),
                      ],
                    )
                  else if (!isRecommended && onReplace != null)
                    GestureDetector(
                      onTap: onReplace,
                      child: Container(
                        width: double.infinity,
                        padding: const EdgeInsets.symmetric(vertical: 5),
                        color: sfBlue,
                        child: const Text(
                          'Select',
                          textAlign: TextAlign.center,
                          style: TextStyle(
                            color: Colors.white,
                            fontSize: 11,
                            fontWeight: FontWeight.w700,
                          ),
                        ),
                      ),
                    ),
                ],
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _qtyBtn(IconData icon, VoidCallback? onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 26,
        height: 26,
        decoration: BoxDecoration(
          color: onTap != null ? sfBlue : const Color(0xFFF3F4F6),
        ),
        child: Icon(
          icon,
          size: 13,
          color: onTap != null ? Colors.white : const Color(0xFFD1D5DB),
        ),
      ),
    );
  }

  Widget _bottomBar() {
    final hasItems = _localItems.values.any((items) => items.isNotEmpty);

    return Container(
      padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
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
          Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              const Text(
                'Grand Total',
                style: TextStyle(
                  fontSize: 11,
                  color: sfMuted,
                  fontWeight: FontWeight.w600,
                ),
              ),
              Text(
                _formatEgp(_grandTotal),
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                  color: sfText,
                ),
              ),
            ],
          ),
          const SizedBox(width: 16),
          Expanded(
            child: ElevatedButton(
              onPressed: hasItems && !_confirming ? _confirmSetup : null,
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
                      'Confirm Setup →',
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
    );
  }

  String _formatEgp(int n) =>
      '${n.toString().replaceAllMapped(RegExp(r'(\d)(?=(\d{3})+$)'), (m) => '${m[1]},')} EGP';
}

// ─── ADD PRODUCT BOTTOM SHEET ────────────────────────────────────────────────

class _AddProductSheet extends StatefulWidget {
  final ApiService api;
  final String module;
  final String type;
  final void Function(Map<String, dynamic>) onAdd;

  const _AddProductSheet({
    required this.api,
    required this.module,
    required this.type,
    required this.onAdd,
  });

  @override
  State<_AddProductSheet> createState() => _AddProductSheetState();
}

class _AddProductSheetState extends State<_AddProductSheet> {
  static const Color sfBlue = Color(0xFF004CAC);
  static const Color sfBg = Color(0xFFF5F7FB);
  static const Color sfText = Color(0xFF111827);
  static const Color sfMuted = Color(0xFF6B7280);

  final _searchC = TextEditingController();
  final _minC = TextEditingController();
  final _maxC = TextEditingController();

  bool _loading = false;
  List<Map<String, dynamic>> _products = [];

  @override
  void initState() {
    super.initState();
    _search();
  }

  @override
  void dispose() {
    _searchC.dispose();
    _minC.dispose();
    _maxC.dispose();
    super.dispose();
  }

  Future<void> _search() async {
    setState(() => _loading = true);
    final res = await widget.api.searchPackageProducts(
      module: widget.module,
      type: widget.type,
      search: _searchC.text.trim(),
      minPrice: int.tryParse(_minC.text.trim()),
      maxPrice: int.tryParse(_maxC.text.trim()),
    );
    if (!mounted) return;
    setState(() {
      _products = List<Map<String, dynamic>>.from(res["products"] ?? []);
      _loading = false;
    });
  }

  String _formatEgp(int n) =>
      '${n.toString().replaceAllMapped(RegExp(r'(\d)(?=(\d{3})+$)'), (m) => '${m[1]},')} EGP';

  @override
  Widget build(BuildContext context) {
    return DraggableScrollableSheet(
      initialChildSize: 0.92,
      maxChildSize: 0.96,
      minChildSize: 0.5,
      expand: false,
      builder: (ctx, scroll) => Column(
        children: [
          // Handle
          Container(
            width: 40,
            height: 4,
            margin: const EdgeInsets.symmetric(vertical: 10),
            decoration: BoxDecoration(
              color: const Color(0xFFE5E7EB),
              borderRadius: BorderRadius.circular(2),
            ),
          ),

          // Header
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 12),
            child: Row(
              children: [
                const Expanded(
                  child: Text(
                    'Add Product',
                    style: TextStyle(
                      fontSize: 16,
                      fontWeight: FontWeight.w900,
                      color: sfText,
                    ),
                  ),
                ),
                GestureDetector(
                  onTap: () => Navigator.pop(ctx),
                  child: const Icon(Icons.close, color: sfMuted),
                ),
              ],
            ),
          ),

          // Search + filters
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
            child: Column(
              children: [
                // Search
                TextField(
                  controller: _searchC,
                  onSubmitted: (_) => _search(),
                  decoration: InputDecoration(
                    hintText: 'Search products...',
                    hintStyle: const TextStyle(color: sfMuted, fontSize: 13.5),
                    filled: true,
                    fillColor: Colors.white,
                    prefixIcon: const Icon(
                      Icons.search,
                      color: sfMuted,
                      size: 18,
                    ),
                    suffixIcon: IconButton(
                      onPressed: _search,
                      icon: const Icon(
                        Icons.search_rounded,
                        color: sfBlue,
                        size: 18,
                      ),
                    ),
                    border: const OutlineInputBorder(
                      borderRadius: BorderRadius.zero,
                      borderSide: BorderSide(color: Color(0xFFE5E7EB)),
                    ),
                    enabledBorder: const OutlineInputBorder(
                      borderRadius: BorderRadius.zero,
                      borderSide: BorderSide(color: Color(0xFFE5E7EB)),
                    ),
                    focusedBorder: const OutlineInputBorder(
                      borderRadius: BorderRadius.zero,
                      borderSide: BorderSide(color: sfBlue, width: 1.5),
                    ),
                    contentPadding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 10,
                    ),
                  ),
                ),
                const SizedBox(height: 8),
                // Price filters
                Row(
                  children: [
                    Expanded(
                      child: TextField(
                        controller: _minC,
                        keyboardType: TextInputType.number,
                        onSubmitted: (_) => _search(),
                        decoration: const InputDecoration(
                          hintText: 'Min price',
                          hintStyle: TextStyle(color: sfMuted, fontSize: 12),
                          filled: true,
                          fillColor: Colors.white,
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.zero,
                            borderSide: BorderSide(color: Color(0xFFE5E7EB)),
                          ),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.zero,
                            borderSide: BorderSide(color: Color(0xFFE5E7EB)),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.zero,
                            borderSide: BorderSide(color: sfBlue, width: 1.5),
                          ),
                          contentPadding: EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 8,
                          ),
                        ),
                      ),
                    ),
                    const Padding(
                      padding: EdgeInsets.symmetric(horizontal: 8),
                      child: Text('—', style: TextStyle(color: sfMuted)),
                    ),
                    Expanded(
                      child: TextField(
                        controller: _maxC,
                        keyboardType: TextInputType.number,
                        onSubmitted: (_) => _search(),
                        decoration: const InputDecoration(
                          hintText: 'Max price',
                          hintStyle: TextStyle(color: sfMuted, fontSize: 12),
                          filled: true,
                          fillColor: Colors.white,
                          border: OutlineInputBorder(
                            borderRadius: BorderRadius.zero,
                            borderSide: BorderSide(color: Color(0xFFE5E7EB)),
                          ),
                          enabledBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.zero,
                            borderSide: BorderSide(color: Color(0xFFE5E7EB)),
                          ),
                          focusedBorder: OutlineInputBorder(
                            borderRadius: BorderRadius.zero,
                            borderSide: BorderSide(color: sfBlue, width: 1.5),
                          ),
                          contentPadding: EdgeInsets.symmetric(
                            horizontal: 10,
                            vertical: 8,
                          ),
                        ),
                      ),
                    ),
                    const SizedBox(width: 8),
                    GestureDetector(
                      onTap: _search,
                      child: Container(
                        padding: const EdgeInsets.symmetric(
                          horizontal: 14,
                          vertical: 10,
                        ),
                        color: sfBlue,
                        child: const Text(
                          'Go',
                          style: TextStyle(
                            color: Colors.white,
                            fontWeight: FontWeight.w700,
                            fontSize: 13,
                          ),
                        ),
                      ),
                    ),
                  ],
                ),
              ],
            ),
          ),

          const Divider(height: 1),

          // Product list
          Expanded(
            child: _loading
                ? const Center(child: CircularProgressIndicator(color: sfBlue))
                : _products.isEmpty
                ? const Center(
                    child: Text(
                      'No products found',
                      style: TextStyle(color: sfMuted, fontSize: 14),
                    ),
                  )
                : ListView.builder(
                    controller: scroll,
                    padding: const EdgeInsets.all(12),
                    itemCount: _products.length,
                    itemBuilder: (ctx, i) {
                      final p = _products[i];
                      final price = p["price"] as int? ?? 0;
                      final rating = (p["avg_rating"] as num?)?.toDouble();
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
                              decoration: const BoxDecoration(
                                color: Color(0xFFF3F4F6),
                              ),
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
                                    p["name"] ?? "—",
                                    style: const TextStyle(
                                      fontSize: 13,
                                      fontWeight: FontWeight.w700,
                                      color: sfText,
                                    ),
                                    maxLines: 2,
                                    overflow: TextOverflow.ellipsis,
                                  ),
                                  if ((p["brand"] ?? "").toString().isNotEmpty)
                                    Text(
                                      p["brand"].toString(),
                                      style: const TextStyle(
                                        fontSize: 11,
                                        color: sfMuted,
                                      ),
                                    ),
                                  Row(
                                    children: [
                                      Text(
                                        _formatEgp(price),
                                        style: const TextStyle(
                                          fontSize: 13,
                                          fontWeight: FontWeight.w900,
                                          color: sfBlue,
                                        ),
                                      ),
                                      if (rating != null) ...[
                                        const SizedBox(width: 8),
                                        const Icon(
                                          Icons.star_rounded,
                                          size: 11,
                                          color: Color(0xFFF59E0B),
                                        ),
                                        Text(
                                          rating.toStringAsFixed(1),
                                          style: const TextStyle(
                                            fontSize: 10.5,
                                            color: sfMuted,
                                          ),
                                        ),
                                      ],
                                    ],
                                  ),
                                  if ((p["tier"] ?? "").toString().isNotEmpty)
                                    Container(
                                      margin: const EdgeInsets.only(top: 3),
                                      padding: const EdgeInsets.symmetric(
                                        horizontal: 6,
                                        vertical: 2,
                                      ),
                                      color: const Color(0xFFEFF6FF),
                                      child: Text(
                                        p["tier"].toString(),
                                        style: const TextStyle(
                                          fontSize: 9.5,
                                          fontWeight: FontWeight.w700,
                                          color: sfBlue,
                                        ),
                                      ),
                                    ),
                                ],
                              ),
                            ),
                            const SizedBox(width: 10),
                            // Add button
                            GestureDetector(
                              onTap: () => widget.onAdd(p),
                              child: Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 14,
                                  vertical: 10,
                                ),
                                color: sfBlue,
                                child: const Text(
                                  'Add',
                                  style: TextStyle(
                                    color: Colors.white,
                                    fontWeight: FontWeight.w700,
                                    fontSize: 12,
                                  ),
                                ),
                              ),
                            ),
                          ],
                        ),
                      );
                    },
                  ),
          ),
        ],
      ),
    );
  }
}
