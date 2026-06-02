import 'package:flutter/material.dart';
import '../services/api_service.dart';

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

  int get _grandTotal {
    int total = 0;
    for (final m in _localItems.keys) {
      for (final item in _localItems[m]!) {
        if (item["is_notice"] == true) continue;
        final unit = item["unit"];
        final qty = item["qty"];
        total +=
            ((qty is int ? qty : int.tryParse(qty.toString()) ?? 0)) *
            ((unit is int ? unit : int.tryParse(unit.toString()) ?? 0));
      }
    }
    return total;
  }

  int _itemTotal(Map<String, dynamic> item) {
    final unit = item["unit"];
    final qty = item["qty"];
    return ((qty is int ? qty : int.tryParse(qty.toString()) ?? 0)) *
        ((unit is int ? unit : int.tryParse(unit.toString()) ?? 0));
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
    await api.packagesAction(
      action: "add_product",
      module: _activeModule,
      type: type,
      productId: product["id"].toString(),
    );
    await _load();
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
    Navigator.pushNamed(
      context,
      '/order-summary',
      arguments: {
        'data': _data,
        'localItems': _localItems,
        'grandTotal': _grandTotal,
      },
    );
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
      "ac": "Climate",
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
          // ── Module tabs ──
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
                  final over = total > cap && cap > 0;

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

          // ── Content ──
          Expanded(
            child: RefreshIndicator(
              color: sfBlue,
              onRefresh: _load,
              child: SingleChildScrollView(
                physics: const AlwaysScrollableScrollPhysics(),
                child: Column(
                  children: [
                    if (_activeCart != null) _budgetBar(_activeCart!),
                    if (_activeModule == "ac" &&
                        _activeItems.any((i) => i["is_notice"] == true))
                      _acNotice(),
                    if (_activeItems
                        .where((i) => i["is_notice"] != true)
                        .isEmpty)
                      const Padding(
                        padding: EdgeInsets.all(40),
                        child: Column(
                          children: [
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
                    const SizedBox(height: 80),
                  ],
                ),
              ),
            ),
          ),

          // ── Bottom bar ──
          _bottomBar(),
        ],
      ),
    );
  }

  Widget _budgetBar(Map<String, dynamic> cart) {
    // Recalculate total from local items (reflects qty changes)
    int total = 0;
    for (final item in _activeItems) {
      if (item["is_notice"] == true) continue;
      total += _itemTotal(item);
    }
    final cap = (cart["cap"] as int?) ?? 0;
    final over = cap > 0 && total > cap;
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
      child: const Row(
        children: [
          Icon(Icons.warning_amber_rounded, color: Color(0xFFF59E0B), size: 20),
          SizedBox(width: 10),
          Expanded(
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
    final qty = item["qty"] is int
        ? item["qty"] as int
        : int.tryParse(item["qty"].toString()) ?? 1;
    final unit = item["unit"] is int
        ? item["unit"] as int
        : int.tryParse(item["unit"].toString()) ?? 0;
    final alts = List<Map<String, dynamic>>.from(item["alternatives"] ?? []);

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
      "dining_set_2": "2-Seat Dining Sets",
      "dining_set_4": "4-Seat Dining Sets",
      "dining_set_6": "6-Seat Dining Sets",
      "dining_set_8": "8-Seat Dining Sets",
      "dining_set_10": "10-Seat Dining Sets",
      "dining_set_12": "12-Seat Dining Sets",
      "tv": "TVs",
      "ac": "AC Units",
      "exhaust_fan": "Exhaust Fans",
      "ceiling_fan": "Ceiling Fans",
      "air_curtain": "Air Curtains",
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
            padding: const EdgeInsets.fromLTRB(14, 12, 14, 10),
            child: Row(
              children: [
                Expanded(
                  child: Text(
                    label,
                    style: const TextStyle(
                      fontSize: 14,
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

          // Recommended card (full width, prominent)
          _recCard(item: item, qty: qty, unit: unit, type: type),

          // Alternatives row
          if (alts.isNotEmpty) ...[
            const Padding(
              padding: EdgeInsets.fromLTRB(14, 10, 14, 6),
              child: Text(
                'Alternatives',
                style: TextStyle(
                  fontSize: 11.5,
                  fontWeight: FontWeight.w700,
                  color: sfMuted,
                ),
              ),
            ),
            SizedBox(
              height: 160,
              child: ListView(
                scrollDirection: Axis.horizontal,
                padding: const EdgeInsets.fromLTRB(14, 0, 14, 0),
                children: [
                  ...alts.map((alt) => _altCard(alt: alt, type: type)),
                  // Add card
                  GestureDetector(
                    onTap: () => _showAddProductSheet(type),
                    child: Container(
                      width: 90,
                      margin: const EdgeInsets.only(left: 8),
                      decoration: BoxDecoration(
                        color: const Color(0xFFEFF6FF),
                        border: Border.all(color: sfBlue),
                      ),
                      child: const Column(
                        mainAxisAlignment: MainAxisAlignment.center,
                        children: [
                          Icon(
                            Icons.add_circle_outline,
                            color: sfBlue,
                            size: 22,
                          ),
                          SizedBox(height: 6),
                          Text(
                            'Add\nOther',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              color: sfBlue,
                              fontWeight: FontWeight.w700,
                              fontSize: 11,
                              height: 1.3,
                            ),
                          ),
                        ],
                      ),
                    ),
                  ),
                ],
              ),
            ),
            const SizedBox(height: 10),
          ] else ...[
            // No alts — just show add button inline
            Padding(
              padding: const EdgeInsets.fromLTRB(14, 6, 14, 12),
              child: GestureDetector(
                onTap: () => _showAddProductSheet(type),
                child: Container(
                  padding: const EdgeInsets.symmetric(vertical: 9),
                  decoration: BoxDecoration(
                    color: const Color(0xFFEFF6FF),
                    border: Border.all(color: sfBlue),
                  ),
                  child: const Row(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(Icons.add, color: sfBlue, size: 16),
                      SizedBox(width: 6),
                      Text(
                        'Add Alternative',
                        style: TextStyle(
                          color: sfBlue,
                          fontWeight: FontWeight.w700,
                          fontSize: 12,
                        ),
                      ),
                    ],
                  ),
                ),
              ),
            ),
          ],

          // Line total
          Container(
            padding: const EdgeInsets.fromLTRB(14, 8, 14, 12),
            decoration: const BoxDecoration(
              border: Border(top: BorderSide(color: Color(0xFFF3F4F6))),
            ),
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

  // ── Full-width recommended card ──
  Widget _recCard({
    required Map<String, dynamic> item,
    required int qty,
    required int unit,
    required String type,
  }) {
    final name = item["name"]?.toString() ?? "—";
    final imageUrl = item["image_url"]?.toString();
    final brand = item["brand"]?.toString() ?? "";
    final rating = (item["avg_rating"] as num?)?.toDouble();
    final productId = item["product_id"]?.toString();

    return Container(
      margin: const EdgeInsets.fromLTRB(14, 0, 14, 0),
      decoration: BoxDecoration(
        color: const Color(0xFFEFF6FF),
        border: Border.all(color: sfBlue, width: 2),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Badge
          Container(
            width: double.infinity,
            padding: const EdgeInsets.symmetric(vertical: 4),
            color: sfBlue,
            child: const Text(
              '✓ Recommended',
              textAlign: TextAlign.center,
              style: TextStyle(
                color: Colors.white,
                fontSize: 11,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          Row(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              // Image
              GestureDetector(
                onTap: productId != null
                    ? () => Navigator.pushNamed(
                        context,
                        '/product-detail',
                        arguments: {'product_id': productId},
                      )
                    : null,
                child: Container(
                  width: 110,
                  height: 110,
                  color: Colors.white,
                  child: imageUrl != null && imageUrl.isNotEmpty
                      ? Image.network(
                          imageUrl,
                          fit: BoxFit.contain,
                          errorBuilder: (_, __, ___) => const Center(
                            child: Icon(
                              Icons.inventory_2_outlined,
                              color: sfMuted,
                              size: 32,
                            ),
                          ),
                        )
                      : const Center(
                          child: Icon(
                            Icons.inventory_2_outlined,
                            color: sfMuted,
                            size: 32,
                          ),
                        ),
                ),
              ),
              // Info
              Expanded(
                child: Padding(
                  padding: const EdgeInsets.all(12),
                  child: Column(
                    crossAxisAlignment: CrossAxisAlignment.start,
                    children: [
                      Text(
                        name,
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w800,
                          color: sfText,
                          height: 1.3,
                        ),
                        maxLines: 3,
                        overflow: TextOverflow.ellipsis,
                      ),
                      if (brand.isNotEmpty) ...[
                        const SizedBox(height: 2),
                        Text(
                          brand,
                          style: const TextStyle(fontSize: 11, color: sfMuted),
                        ),
                      ],
                      const SizedBox(height: 6),
                      Text(
                        _formatEgp(unit),
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w900,
                          color: sfBlue,
                        ),
                      ),
                      if (rating != null)
                        Row(
                          children: [
                            const Icon(
                              Icons.star_rounded,
                              size: 12,
                              color: Color(0xFFF59E0B),
                            ),
                            const SizedBox(width: 2),
                            Text(
                              rating.toStringAsFixed(1),
                              style: const TextStyle(
                                fontSize: 11,
                                color: sfMuted,
                              ),
                            ),
                          ],
                        ),
                      const SizedBox(height: 8),
                      // Qty controls
                      Row(
                        children: [
                          _qtyBtn(
                            Icons.remove,
                            qty > 0 ? () => _updateQty(type, qty - 1) : null,
                          ),
                          Container(
                            width: 36,
                            alignment: Alignment.center,
                            child: Text(
                              '$qty',
                              style: const TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w800,
                                color: sfText,
                              ),
                            ),
                          ),
                          _qtyBtn(Icons.add, () => _updateQty(type, qty + 1)),
                          const Spacer(),
                          if (productId != null)
                            GestureDetector(
                              onTap: () => Navigator.pushNamed(
                                context,
                                '/product-detail',
                                arguments: {'product_id': productId},
                              ),
                              child: Container(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 10,
                                  vertical: 6,
                                ),
                                decoration: BoxDecoration(
                                  border: Border.all(color: sfBlue),
                                ),
                                child: const Text(
                                  'Details',
                                  style: TextStyle(
                                    color: sfBlue,
                                    fontSize: 11,
                                    fontWeight: FontWeight.w700,
                                  ),
                                ),
                              ),
                            ),
                        ],
                      ),
                    ],
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  // ── Small alternative card ──
  Widget _altCard({required Map<String, dynamic> alt, required String type}) {
    final name = alt["name"]?.toString() ?? "—";
    final price = (alt["price"] ?? alt["unit"]);
    final priceInt = price is int ? price : int.tryParse(price.toString()) ?? 0;
    final imageUrl = alt["image_url"]?.toString();
    final productId = alt["id"]?.toString();

    return GestureDetector(
      onTap: () => _showReplaceDialog(type, alt),
      child: Container(
        width: 110,
        margin: const EdgeInsets.only(right: 8),
        decoration: BoxDecoration(
          color: Colors.white,
          border: Border.all(color: const Color(0xFFE5E7EB)),
        ),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            // Image
            Container(
              height: 70,
              width: double.infinity,
              color: const Color(0xFFF9FAFB),
              child: imageUrl != null && imageUrl.isNotEmpty
                  ? Image.network(
                      imageUrl,
                      fit: BoxFit.contain,
                      errorBuilder: (_, __, ___) => const Center(
                        child: Icon(
                          Icons.inventory_2_outlined,
                          color: sfMuted,
                          size: 20,
                        ),
                      ),
                    )
                  : const Center(
                      child: Icon(
                        Icons.inventory_2_outlined,
                        color: sfMuted,
                        size: 20,
                      ),
                    ),
            ),
            Expanded(
              child: Padding(
                padding: const EdgeInsets.all(6),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  children: [
                    Text(
                      name,
                      style: const TextStyle(
                        fontSize: 10,
                        fontWeight: FontWeight.w600,
                        color: sfText,
                      ),
                      maxLines: 2,
                      overflow: TextOverflow.ellipsis,
                    ),
                    const Spacer(),
                    Text(
                      _formatEgp(priceInt),
                      style: const TextStyle(
                        fontSize: 10.5,
                        fontWeight: FontWeight.w900,
                        color: sfBlue,
                      ),
                    ),
                  ],
                ),
              ),
            ),
            Container(
              width: double.infinity,
              padding: const EdgeInsets.symmetric(vertical: 5),
              color: sfBlue,
              child: const Text(
                'Select',
                textAlign: TextAlign.center,
                style: TextStyle(
                  color: Colors.white,
                  fontSize: 10,
                  fontWeight: FontWeight.w700,
                ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  void _showReplaceDialog(String type, Map<String, dynamic> alt) {
    final name = alt["name"]?.toString() ?? "this product";
    showDialog(
      context: context,
      builder: (ctx) => AlertDialog(
        shape: const RoundedRectangleBorder(),
        title: const Text(
          'Replace Product',
          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
        ),
        content: Text('Replace the recommended item with "$name"?'),
        actions: [
          TextButton(
            onPressed: () => Navigator.pop(ctx),
            child: const Text('Cancel', style: TextStyle(color: sfMuted)),
          ),
          ElevatedButton(
            onPressed: () {
              Navigator.pop(ctx);
              _replaceProduct(type, alt["id"].toString());
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: sfBlue,
              shape: const RoundedRectangleBorder(),
            ),
            child: const Text(
              'Replace',
              style: TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w700,
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
        width: 30,
        height: 30,
        decoration: BoxDecoration(
          color: onTap != null ? sfBlue : const Color(0xFFF3F4F6),
        ),
        child: Icon(
          icon,
          size: 14,
          color: onTap != null ? Colors.white : const Color(0xFFD1D5DB),
        ),
      ),
    );
  }

  Widget _bottomBar() {
    final hasItems = _localItems.values.any(
      (items) => items.any((i) => i["is_notice"] != true),
    );

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
            mainAxisSize: MainAxisSize.min,
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

// ─── ADD PRODUCT BOTTOM SHEET ─────────────────────────────────────────────────

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

  String _fmt(int n) =>
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
          Container(
            width: 40,
            height: 4,
            margin: const EdgeInsets.symmetric(vertical: 10),
            decoration: BoxDecoration(
              color: const Color(0xFFE5E7EB),
              borderRadius: BorderRadius.circular(2),
            ),
          ),
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
          Padding(
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 10),
            child: Column(
              children: [
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
                            Container(
                              width: 64,
                              height: 64,
                              color: const Color(0xFFF3F4F6),
                              child: imageUrl != null && imageUrl.isNotEmpty
                                  ? Image.network(
                                      imageUrl,
                                      fit: BoxFit.contain,
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
                                        _fmt(price),
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
