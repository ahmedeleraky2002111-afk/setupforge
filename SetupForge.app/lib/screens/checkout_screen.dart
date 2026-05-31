import 'package:flutter/material.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../services/api_service.dart';

class CheckoutScreen extends StatefulWidget {
  const CheckoutScreen({super.key});

  @override
  State<CheckoutScreen> createState() => _CheckoutScreenState();
}

class _CheckoutScreenState extends State<CheckoutScreen> {
  static const Color sfBlue = Color(0xFF004CAC);
  static const Color sfBg = Color(0xFFF5F7FB);
  static const Color sfText = Color(0xFF111827);
  static const Color sfMuted = Color(0xFF6B7280);

  final api = ApiService();
  final _nameC = TextEditingController();
  final _phoneC = TextEditingController();
  final _addressC = TextEditingController();
  final _notesC = TextEditingController();

  bool _loading = true;
  bool _placing = false;
  List<Map<String, dynamic>> _items = [];
  double _total = 0;

  @override
  void initState() {
    super.initState();
    _loadData();
  }

  @override
  void dispose() {
    _nameC.dispose();
    _phoneC.dispose();
    _addressC.dispose();
    _notesC.dispose();
    super.dispose();
  }

  Future<void> _loadData() async {
    setState(() => _loading = true);

    // Load cart
    final cartRes = await api.cartList();
    final items = List<Map<String, dynamic>>.from(cartRes["items"] ?? []);
    final total = (cartRes["total"] as num?)?.toDouble() ?? 0;

    // Pre-fill user info
    final prefs = await SharedPreferences.getInstance();
    _nameC.text = prefs.getString('user_name') ?? '';

    if (!mounted) return;
    setState(() {
      _items = items;
      _total = total;
      _loading = false;
    });
  }

  Future<void> _placeOrder() async {
    if (_nameC.text.trim().isEmpty ||
        _phoneC.text.trim().isEmpty ||
        _addressC.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please fill in all required fields')),
      );
      return;
    }

    setState(() => _placing = true);

    final res = await api.placeShopOrder(
      deliveryName: _nameC.text.trim(),
      deliveryPhone: _phoneC.text.trim(),
      deliveryLocation: _addressC.text.trim(),
      orderNotes: _notesC.text.trim(),
    );

    if (!mounted) return;
    setState(() => _placing = false);

    if (res["ok"] == true) {
      Navigator.pushNamedAndRemoveUntil(
        context,
        '/order-success',
        (route) => route.settings.name == '/app-shell',
        arguments: {"order_id": res["order_id"], "total": res["total"]},
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text(res["error"]?.toString() ?? "Failed to place order"),
        ),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: sfBg,
      appBar: AppBar(
        backgroundColor: sfBlue,
        foregroundColor: Colors.white,
        title: const Text(
          'Checkout',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        elevation: 0,
        shape: const RoundedRectangleBorder(),
      ),
      body: _loading
          ? const Center(child: CircularProgressIndicator(color: sfBlue))
          : Column(
              children: [
                Expanded(
                  child: SingleChildScrollView(
                    padding: const EdgeInsets.all(16),
                    child: Column(
                      crossAxisAlignment: CrossAxisAlignment.start,
                      children: [
                        // Delivery info
                        _sectionTitle('Delivery Information'),
                        const SizedBox(height: 12),
                        _field(_nameC, 'Full Name *', Icons.person_outline),
                        const SizedBox(height: 10),
                        _field(
                          _phoneC,
                          'Phone Number *',
                          Icons.phone_outlined,
                          keyboardType: TextInputType.phone,
                        ),
                        const SizedBox(height: 10),
                        _field(
                          _addressC,
                          'Delivery Address *',
                          Icons.location_on_outlined,
                          maxLines: 2,
                        ),
                        const SizedBox(height: 10),
                        _field(
                          _notesC,
                          'Order Notes (optional)',
                          Icons.note_outlined,
                          maxLines: 2,
                        ),

                        const SizedBox(height: 20),

                        // Order summary
                        _sectionTitle('Order Summary'),
                        const SizedBox(height: 12),
                        Container(
                          decoration: BoxDecoration(
                            color: Colors.white,
                            border: Border.all(color: const Color(0xFFE5E7EB)),
                          ),
                          child: Column(
                            children: [
                              ..._items.map((item) {
                                final lineTotal = (item["line_total"] as num)
                                    .toDouble();
                                return Padding(
                                  padding: const EdgeInsets.all(12),
                                  child: Row(
                                    children: [
                                      Expanded(
                                        child: Column(
                                          crossAxisAlignment:
                                              CrossAxisAlignment.start,
                                          children: [
                                            Text(
                                              item["product_name"] ?? "—",
                                              style: const TextStyle(
                                                fontSize: 13,
                                                fontWeight: FontWeight.w700,
                                                color: sfText,
                                              ),
                                              maxLines: 1,
                                              overflow: TextOverflow.ellipsis,
                                            ),
                                            Text(
                                              'Qty: ${item["quantity"]}',
                                              style: const TextStyle(
                                                fontSize: 11.5,
                                                color: sfMuted,
                                              ),
                                            ),
                                          ],
                                        ),
                                      ),
                                      Text(
                                        '${lineTotal.toStringAsFixed(0)} EGP',
                                        style: const TextStyle(
                                          fontSize: 13,
                                          fontWeight: FontWeight.w700,
                                          color: sfBlue,
                                        ),
                                      ),
                                    ],
                                  ),
                                );
                              }),
                              Container(
                                padding: const EdgeInsets.all(12),
                                decoration: const BoxDecoration(
                                  border: Border(
                                    top: BorderSide(color: Color(0xFFE5E7EB)),
                                  ),
                                ),
                                child: Row(
                                  mainAxisAlignment:
                                      MainAxisAlignment.spaceBetween,
                                  children: [
                                    const Text(
                                      'Total',
                                      style: TextStyle(
                                        fontSize: 14,
                                        fontWeight: FontWeight.w700,
                                        color: sfMuted,
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
                              ),
                            ],
                          ),
                        ),
                        const SizedBox(height: 32),
                      ],
                    ),
                  ),
                ),

                // Place order button
                Container(
                  padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    border: const Border(
                      top: BorderSide(color: Color(0xFFE5E7EB)),
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withOpacity(0.05),
                        blurRadius: 10,
                        offset: const Offset(0, -4),
                      ),
                    ],
                  ),
                  child: ElevatedButton.icon(
                    onPressed: _placing ? null : _placeOrder,
                    icon: _placing
                        ? const SizedBox(
                            width: 18,
                            height: 18,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : const Icon(
                            Icons.lock_outline,
                            size: 18,
                            color: Colors.white,
                          ),
                    label: Text(
                      _placing ? 'Placing Order...' : 'Place Order & Pay',
                      style: const TextStyle(
                        color: Colors.white,
                        fontWeight: FontWeight.w800,
                        fontSize: 15,
                      ),
                    ),
                    style: ElevatedButton.styleFrom(
                      backgroundColor: sfBlue,
                      minimumSize: const Size(double.infinity, 52),
                      shape: const RoundedRectangleBorder(),
                    ),
                  ),
                ),
              ],
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

  Widget _field(
    TextEditingController controller,
    String hint,
    IconData icon, {
    TextInputType? keyboardType,
    int maxLines = 1,
  }) {
    return TextField(
      controller: controller,
      keyboardType: keyboardType,
      maxLines: maxLines,
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: const TextStyle(color: sfMuted, fontSize: 13.5),
        filled: true,
        fillColor: Colors.white,
        prefixIcon: maxLines == 1 ? Icon(icon, color: sfMuted, size: 18) : null,
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
      ),
    );
  }
}
