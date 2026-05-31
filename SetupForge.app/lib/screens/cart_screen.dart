import 'package:flutter/material.dart';
import '../services/api_service.dart';

class CartScreen extends StatefulWidget {
  const CartScreen({super.key});

  @override
  State<CartScreen> createState() => _CartScreenState();
}

class _CartScreenState extends State<CartScreen> {
  static const Color sfBlue = Color(0xFF004CAC);
  static const Color sfBg = Color(0xFFF5F7FB);
  static const Color sfText = Color(0xFF111827);
  static const Color sfMuted = Color(0xFF6B7280);

  final api = ApiService();
  bool _loading = true;
  List<Map<String, dynamic>> _items = [];
  double _total = 0;

  @override
  void initState() {
    super.initState();
    _load();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    final res = await api.cartList();
    if (!mounted) return;
    setState(() {
      _items = List<Map<String, dynamic>>.from(res["items"] ?? []);
      _total = (res["total"] as num?)?.toDouble() ?? 0;
      _loading = false;
    });
  }

  Future<void> _updateQty(int productId, int delta) async {
    final item = _items.firstWhere(
      (i) => i["product_id"] == productId,
      orElse: () => {},
    );
    if (item.isEmpty) return;

    final currentQty = item["quantity"] as int;
    final newQty = currentQty + delta;

    if (newQty <= 0) {
      await _removeItem(productId);
      return;
    }

    await api.cartUpdate(productId, newQty);
    await _load();
  }

  Future<void> _removeItem(int productId) async {
    await api.cartRemove(productId);
    await _load();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: sfBg,
      appBar: AppBar(
        backgroundColor: sfBlue,
        foregroundColor: Colors.white,
        title: Text(
          'Cart${_items.isNotEmpty ? ' (${_items.length})' : ''}',
          style: const TextStyle(fontWeight: FontWeight.w900),
        ),
        elevation: 0,
        shape: const RoundedRectangleBorder(),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: sfBlue))
          : _items.isEmpty
          ? _emptyCart()
          : Column(
              children: [
                Expanded(
                  child: RefreshIndicator(
                    color: sfBlue,
                    onRefresh: _load,
                    child: ListView.builder(
                      padding: const EdgeInsets.all(12),
                      itemCount: _items.length,
                      itemBuilder: (ctx, i) => _cartItem(_items[i]),
                    ),
                  ),
                ),
                _checkoutBar(),
              ],
            ),
    );
  }

  Widget _cartItem(Map<String, dynamic> item) {
    final pid = item["product_id"] as int;
    final qty = item["quantity"] as int;
    final price = (item["price"] as num).toDouble();
    final lineTotal = (item["line_total"] as num).toDouble();
    final imageUrl = item["image_url"]?.toString();

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Row(
        children: [
          // Image
          Container(
            width: 72,
            height: 72,
            decoration: const BoxDecoration(color: Color(0xFFF3F4F6)),
            child: imageUrl != null && imageUrl.isNotEmpty
                ? Image.network(
                    imageUrl,
                    fit: BoxFit.cover,
                    errorBuilder: (_, __, ___) =>
                        const Icon(Icons.inventory_2_outlined, color: sfMuted),
                  )
                : const Icon(Icons.inventory_2_outlined, color: sfMuted),
          ),
          const SizedBox(width: 12),

          // Info
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  item["product_name"] ?? "—",
                  style: const TextStyle(
                    fontSize: 13.5,
                    fontWeight: FontWeight.w700,
                    color: sfText,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),
                if ((item["brand"] ?? "").toString().isNotEmpty)
                  Text(
                    item["brand"].toString(),
                    style: const TextStyle(fontSize: 11.5, color: sfMuted),
                  ),
                const SizedBox(height: 6),
                Text(
                  '${price.toStringAsFixed(0)} EGP each',
                  style: const TextStyle(
                    fontSize: 12,
                    color: sfBlue,
                    fontWeight: FontWeight.w700,
                  ),
                ),
              ],
            ),
          ),

          // Right side
          Column(
            crossAxisAlignment: CrossAxisAlignment.end,
            children: [
              // Line total
              Text(
                '${lineTotal.toStringAsFixed(0)} EGP',
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w900,
                  color: sfText,
                ),
              ),
              const SizedBox(height: 8),

              // Qty controls
              Row(
                children: [
                  _qtyBtn(Icons.remove, () => _updateQty(pid, -1)),
                  Container(
                    width: 36,
                    height: 32,
                    decoration: BoxDecoration(
                      border: Border.symmetric(
                        vertical: BorderSide(color: const Color(0xFFE5E7EB)),
                      ),
                    ),
                    child: Center(
                      child: Text(
                        '$qty',
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w700,
                          color: sfText,
                        ),
                      ),
                    ),
                  ),
                  _qtyBtn(Icons.add, () => _updateQty(pid, 1)),
                ],
              ),

              const SizedBox(height: 4),

              // Remove
              GestureDetector(
                onTap: () => _removeItem(pid),
                child: const Text(
                  'Remove',
                  style: TextStyle(
                    fontSize: 11,
                    color: Colors.red,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _qtyBtn(IconData icon, VoidCallback onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 32,
        height: 32,
        decoration: BoxDecoration(
          color: const Color(0xFFF8FAFC),
          border: Border.all(color: const Color(0xFFE5E7EB)),
        ),
        child: Icon(icon, size: 14, color: sfText),
      ),
    );
  }

  Widget _checkoutBar() {
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
                'Total',
                style: TextStyle(
                  fontSize: 12,
                  color: sfMuted,
                  fontWeight: FontWeight.w600,
                ),
              ),
              Text(
                '${_total.toStringAsFixed(0)} EGP',
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
              onPressed: () => Navigator.pushNamed(
                context,
                '/checkout',
              ).then((_) => _load()),
              style: ElevatedButton.styleFrom(
                backgroundColor: sfBlue,
                padding: const EdgeInsets.symmetric(vertical: 14),
                shape: const RoundedRectangleBorder(),
              ),
              child: const Text(
                'Proceed to Checkout',
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

  Widget _emptyCart() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.shopping_cart_outlined, size: 64, color: sfMuted),
          const SizedBox(height: 16),
          const Text(
            'Your cart is empty',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w800,
              color: sfText,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Browse products and add items to get started.',
            style: TextStyle(color: sfMuted, fontSize: 13.5),
            textAlign: TextAlign.center,
          ),
          const SizedBox(height: 20),
          GestureDetector(
            onTap: () => Navigator.pop(context),
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 24, vertical: 12),
              color: sfBlue,
              child: const Text(
                'Browse Products',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                  fontSize: 14,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
