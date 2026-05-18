import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';
import '../services/api_service.dart';
import '../state/wizard_state.dart';
import 'success_screen.dart';

class PlaceOrderScreen extends StatefulWidget {
  const PlaceOrderScreen({super.key});

  @override
  State<PlaceOrderScreen> createState() => _PlaceOrderScreenState();
}

class _PlaceOrderScreenState extends State<PlaceOrderScreen> {
  static const Color _blue = Color(0xFF004CAC);
  static const Color _teal = Color(0xFF009994);
  static const Color _bg = Color(0xFFF5F7FB);
  static const Color _textDark = Color(0xFF121212);
  static const Color _textMuted = Color(0xFF6C757D);
  static const Color _border = Color(0x1A000000);

  final _formKey = GlobalKey<FormState>();

  final TextEditingController _businessNameController = TextEditingController();
  final TextEditingController _phoneController = TextEditingController();
  final TextEditingController _addressController = TextEditingController();
  final TextEditingController _notesController = TextEditingController();

  bool _submitting = false;
  String _paymentMethod = 'Card';

  @override
  void didChangeDependencies() {
    super.didChangeDependencies();
    final wizard = context.read<WizardState>();

    if (_businessNameController.text.isEmpty &&
        wizard.businessName.isNotEmpty) {
      _businessNameController.text = wizard.businessName;
    }
  }

  @override
  void dispose() {
    _businessNameController.dispose();
    _phoneController.dispose();
    _addressController.dispose();
    _notesController.dispose();
    super.dispose();
  }

  double _toDouble(dynamic value, {double fallback = 0}) {
    if (value == null) return fallback;
    if (value is num) return value.toDouble();
    return double.tryParse(value.toString()) ?? fallback;
  }

  double _itemTotal(dynamic item) {
    final qty = _toDouble(item['qty'], fallback: 1);
    final price = _toDouble(item['price'], fallback: 0);
    return qty * price;
  }

  String _money(num value) {
    return '${value.toStringAsFixed(0)} EGP';
  }

  String _text(dynamic value, {String fallback = ''}) {
    final s = value?.toString().trim() ?? '';
    return s.isEmpty ? fallback : s;
  }

  Future<void> _confirmOrder() async {
    if (!(_formKey.currentState?.validate() ?? false)) return;

    final wizard = context.read<WizardState>();

    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('api_token') ?? '';
    if (token.isEmpty) {
      if (!mounted) return;
      Navigator.pushReplacementNamed(context, '/signup');
      return;
    }

    setState(() => _submitting = true);

    final items = wizard.cartItems
        .map(
          (item) => {
            'product_id': item['product_id'] ?? item['id'] ?? 0,
            'name': item['name'] ?? '',
            'module': item['module'] ?? '',
            'qty': (item['qty'] as num?)?.toInt() ?? 1,
            'price': (item['price'] as num?)?.toDouble() ?? 0.0,
          },
        )
        .toList();

    try {
      final api = ApiService();
      final result = await api.placeOrder(
        token: token,
        items: items,
        businessName: _businessNameController.text.trim(),
        phone: _phoneController.text.trim(),
        address: _addressController.text.trim(),
        notes: _notesController.text.trim(),
        businessType: wizard.businessType,
        restaurantType: wizard.restaurantType,
        indoorTables: wizard.indoorTables,
        outdoorTables: wizard.outdoorTables,
        areaSqm: wizard.areaSqm,
        budgetRange: wizard.budgetRange,
        installationServices: wizard.installationServices,
        staffCounts: wizard.staffCounts,
        paymentMethod: _paymentMethod.toLowerCase(),
      );

      if (!mounted) return;

      if (result['ok'] == true) {
        wizard.clearCart();
        Navigator.pushReplacement(
          context,
          MaterialPageRoute(builder: (_) => const SuccessScreen()),
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(
            content: Text(
              result['error']?.toString() ??
                  'Failed to place order. Try again.',
            ),
            backgroundColor: Colors.red.shade700,
          ),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('Network error: $e'),
          backgroundColor: Colors.red.shade700,
        ),
      );
    } finally {
      if (mounted) setState(() => _submitting = false);
    }
  }

  @override
  Widget build(BuildContext context) {
    final wizard = context.watch<WizardState>();
    final items = wizard.cartItems;
    final total = wizard.totalPrice;

    return Scaffold(
      backgroundColor: _bg,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        foregroundColor: _textDark,
        title: const Text(
          'Place Order',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: SafeArea(
        child: SingleChildScrollView(
          padding: const EdgeInsets.fromLTRB(18, 8, 18, 120),
          child: Center(
            child: ConstrainedBox(
              constraints: const BoxConstraints(maxWidth: 760),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  _buildTopSummary(total),
                  const SizedBox(height: 16),
                  _buildDeliveryCard(),
                  const SizedBox(height: 16),
                  _buildPaymentCard(),
                  const SizedBox(height: 16),
                  _buildOrderPreview(items, total, wizard),
                ],
              ),
            ),
          ),
        ),
      ),
      bottomNavigationBar: _buildStickyCTA(items.isNotEmpty),
    );
  }

  Widget _buildTopSummary(double total) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [_blue, _teal],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(20),
        boxShadow: [
          BoxShadow(
            color: _blue.withOpacity(0.12),
            blurRadius: 18,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'FINAL CHECKOUT',
            style: TextStyle(
              fontSize: 11,
              letterSpacing: 1,
              fontWeight: FontWeight.w900,
              color: Colors.white70,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Enter your delivery details and confirm your order.',
            style: TextStyle(
              fontSize: 15,
              height: 1.4,
              fontWeight: FontWeight.w700,
              color: Colors.white,
            ),
          ),
          const SizedBox(height: 14),
          const Text(
            'Total',
            style: TextStyle(
              fontSize: 12,
              color: Colors.white70,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            _money(total),
            style: const TextStyle(
              fontSize: 24,
              fontWeight: FontWeight.w900,
              color: Colors.white,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildDeliveryCard() {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: _border),
      ),
      child: Form(
        key: _formKey,
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            const Text(
              'Delivery Details',
              style: TextStyle(
                fontSize: 18,
                fontWeight: FontWeight.w900,
                color: _textDark,
              ),
            ),
            const SizedBox(height: 6),
            const Text(
              'We only ask for the information needed to complete the order.',
              style: TextStyle(
                fontSize: 12.5,
                color: _textMuted,
                fontWeight: FontWeight.w500,
              ),
            ),
            const SizedBox(height: 18),
            _label('Business Name'),
            _field(
              controller: _businessNameController,
              hint: 'Enter your business name',
              validator: (v) {
                if ((v ?? '').trim().isEmpty) {
                  return 'Business name is required';
                }
                return null;
              },
            ),
            const SizedBox(height: 12),
            _label('Phone Number'),
            _field(
              controller: _phoneController,
              hint: 'Enter your phone number',
              keyboardType: TextInputType.phone,
              validator: (v) {
                if ((v ?? '').trim().isEmpty) return 'Phone number is required';
                return null;
              },
            ),
            const SizedBox(height: 12),
            _label('Delivery Address'),
            _field(
              controller: _addressController,
              hint: 'Enter delivery address',
              maxLines: 3,
              validator: (v) {
                if ((v ?? '').trim().isEmpty) return 'Address is required';
                return null;
              },
            ),
            const SizedBox(height: 12),
            _label('Notes (optional)'),
            _field(
              controller: _notesController,
              hint: 'Extra notes for the order',
              maxLines: 3,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildPaymentCard() {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: _border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Payment Method',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w900,
              color: _textDark,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Choose how you want to continue with payment.',
            style: TextStyle(
              fontSize: 12.5,
              color: _textMuted,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 16),
          _paymentTile(
            title: 'Card',
            subtitle: 'Continue with secure online payment',
            value: 'Card',
            icon: Icons.credit_card_rounded,
          ),
          const SizedBox(height: 10),
          _paymentTile(
            title: 'InstaPay',
            subtitle: 'Manual confirmation flow',
            value: 'InstaPay',
            icon: Icons.account_balance_wallet_rounded,
          ),
        ],
      ),
    );
  }

  Widget _paymentTile({
    required String title,
    required String subtitle,
    required String value,
    required IconData icon,
  }) {
    final selected = _paymentMethod == value;

    return InkWell(
      onTap: () {
        setState(() => _paymentMethod = value);
      },
      borderRadius: BorderRadius.circular(14),
      child: Container(
        padding: const EdgeInsets.all(14),
        decoration: BoxDecoration(
          color: selected ? const Color(0xFFF4F8FF) : Colors.white,
          borderRadius: BorderRadius.circular(14),
          border: Border.all(
            color: selected ? _blue : _border,
            width: selected ? 1.4 : 1,
          ),
        ),
        child: Row(
          children: [
            Container(
              width: 42,
              height: 42,
              decoration: BoxDecoration(
                color: _blue.withOpacity(0.10),
                borderRadius: BorderRadius.circular(12),
              ),
              child: Icon(icon, color: _blue),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  Text(
                    title,
                    style: const TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w800,
                      color: _textDark,
                    ),
                  ),
                  const SizedBox(height: 4),
                  Text(
                    subtitle,
                    style: const TextStyle(
                      fontSize: 12,
                      color: _textMuted,
                      fontWeight: FontWeight.w500,
                    ),
                  ),
                ],
              ),
            ),
            Radio<String>(
              value: value,
              groupValue: _paymentMethod,
              onChanged: (val) {
                if (val != null) {
                  setState(() => _paymentMethod = val);
                }
              },
              activeColor: _blue,
            ),
          ],
        ),
      ),
    );
  }

  Widget _buildOrderPreview(
    List<dynamic> items,
    double total,
    WizardState wizard,
  ) {
    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: _border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Order Preview',
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w900,
              color: _textDark,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'One last review before confirming.',
            style: TextStyle(
              fontSize: 12.5,
              color: _textMuted,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 16),
          _previewInfo(
            'Business Type',
            wizard.businessType.isEmpty ? '—' : wizard.businessType,
          ),
          _previewInfo(
            'Budget',
            wizard.budgetRange.isEmpty ? '—' : wizard.budgetRange,
          ),
          _previewInfo(
            'Services',
            wizard.installationServices.isEmpty
                ? '—'
                : wizard.installationServices.join(', '),
          ),
          _previewInfo('Payment', _paymentMethod),
          const SizedBox(height: 14),
          const Divider(height: 1),
          const SizedBox(height: 14),
          if (items.isEmpty)
            const Text(
              'No items selected.',
              style: TextStyle(
                fontSize: 14,
                color: _textMuted,
                fontWeight: FontWeight.w600,
              ),
            )
          else
            ...items.map((item) => _previewItem(item)),
          const SizedBox(height: 12),
          const Divider(height: 1),
          const SizedBox(height: 12),
          Row(
            children: [
              const Expanded(
                child: Text(
                  'Grand Total',
                  style: TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w900,
                    color: _textDark,
                  ),
                ),
              ),
              Text(
                _money(total),
                style: const TextStyle(
                  fontSize: 18,
                  fontWeight: FontWeight.w900,
                  color: _textDark,
                ),
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _previewInfo(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 110,
            child: Text(
              label,
              style: const TextStyle(
                fontSize: 12,
                color: _textMuted,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
          Expanded(
            child: Text(
              value,
              style: const TextStyle(
                fontSize: 12.5,
                color: _textDark,
                fontWeight: FontWeight.w700,
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _previewItem(dynamic item) {
    final name = _text(item['name'], fallback: 'Item');
    final module = _text(item['module'], fallback: 'Module');
    final qty = _toDouble(item['qty'], fallback: 1);
    final total = _itemTotal(item);

    return Container(
      margin: const EdgeInsets.only(bottom: 10),
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFF),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _border),
      ),
      child: Row(
        children: [
          Expanded(
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Text(
                  module,
                  style: const TextStyle(
                    fontSize: 11,
                    color: _teal,
                    fontWeight: FontWeight.w800,
                  ),
                ),
                const SizedBox(height: 4),
                Text(
                  name,
                  style: const TextStyle(
                    fontSize: 13.5,
                    color: _textDark,
                    fontWeight: FontWeight.w800,
                  ),
                ),
              ],
            ),
          ),
          const SizedBox(width: 10),
          Text(
            'x${qty.toStringAsFixed(0)}',
            style: const TextStyle(
              fontSize: 12.5,
              color: _textMuted,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(width: 12),
          Text(
            _money(total),
            style: const TextStyle(
              fontSize: 13,
              color: _textDark,
              fontWeight: FontWeight.w900,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStickyCTA(bool hasItems) {
    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.all(16),
        decoration: BoxDecoration(
          color: Colors.white,
          border: const Border(top: BorderSide(color: _border)),
          boxShadow: [
            BoxShadow(color: Colors.black.withOpacity(0.05), blurRadius: 10),
          ],
        ),
        child: Row(
          children: [
            Expanded(
              child: OutlinedButton(
                onPressed: _submitting ? null : () => Navigator.pop(context),
                style: OutlinedButton.styleFrom(
                  foregroundColor: _textMuted,
                  side: const BorderSide(color: Color(0x33000000)),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  minimumSize: const Size.fromHeight(50),
                ),
                child: const Text(
                  'Back',
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: ElevatedButton(
                onPressed: (!hasItems || _submitting) ? null : _confirmOrder,
                style: ElevatedButton.styleFrom(
                  backgroundColor: _blue,
                  disabledBackgroundColor: Colors.grey.shade400,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  minimumSize: const Size.fromHeight(50),
                ),
                child: _submitting
                    ? const SizedBox(
                        width: 20,
                        height: 20,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.2,
                          color: Colors.white,
                        ),
                      )
                    : Text(
                        _paymentMethod == 'Card'
                            ? 'Confirm & Pay'
                            : 'Confirm Order',
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w800,
                        ),
                      ),
              ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _label(String text) {
    return Text(
      text,
      style: const TextStyle(
        fontSize: 12,
        fontWeight: FontWeight.w800,
        color: _textDark,
      ),
    );
  }

  Widget _field({
    required TextEditingController controller,
    required String hint,
    TextInputType? keyboardType,
    int maxLines = 1,
    String? Function(String?)? validator,
  }) {
    return TextFormField(
      controller: controller,
      keyboardType: keyboardType,
      maxLines: maxLines,
      validator: validator,
      decoration: InputDecoration(
        hintText: hint,
        hintStyle: const TextStyle(color: _textMuted, fontSize: 13),
        filled: true,
        fillColor: const Color(0xFFF8FAFF),
        contentPadding: const EdgeInsets.symmetric(
          horizontal: 14,
          vertical: 14,
        ),
        border: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: _border),
        ),
        enabledBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: _border),
        ),
        focusedBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: _blue, width: 1.4),
        ),
        errorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Colors.red),
        ),
        focusedErrorBorder: OutlineInputBorder(
          borderRadius: BorderRadius.circular(12),
          borderSide: const BorderSide(color: Colors.red, width: 1.4),
        ),
      ),
    );
  }
}
