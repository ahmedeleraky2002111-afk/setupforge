import 'package:flutter/material.dart';
import 'package:provider/provider.dart';

import '../state/wizard_state.dart';
import 'place_order_screen.dart';

class OrderSummaryScreen extends StatefulWidget {
  const OrderSummaryScreen({super.key});

  @override
  State<OrderSummaryScreen> createState() => _OrderSummaryScreenState();
}

class _OrderSummaryScreenState extends State<OrderSummaryScreen> {
  static const Color _blue = Color(0xFF004CAC);
  static const Color _teal = Color(0xFF009994);
  static const Color _bg = Color(0xFFF5F7FB);
  static const Color _textDark = Color(0xFF121212);
  static const Color _textMuted = Color(0xFF6C757D);
  static const Color _border = Color(0x1A000000);

  double _itemTotal(dynamic item) {
    final qty = _toDouble(item['qty'], fallback: 1);
    final price = _toDouble(item['price'], fallback: 0);
    return qty * price;
  }

  double _toDouble(dynamic value, {double fallback = 0}) {
    if (value == null) return fallback;
    if (value is num) return value.toDouble();
    return double.tryParse(value.toString()) ?? fallback;
  }

  String _money(num value) {
    return '${value.toStringAsFixed(0)} EGP';
  }

  String _text(dynamic value, {String fallback = ''}) {
    final s = value?.toString().trim() ?? '';
    return s.isEmpty ? fallback : s;
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
          'Order Summary',
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
                  _buildHeader(total),
                  const SizedBox(height: 16),
                  _buildSetupSnapshot(wizard),
                  const SizedBox(height: 18),
                  _buildItems(items),
                  const SizedBox(height: 18),
                  _buildGrandTotalCard(total),
                ],
              ),
            ),
          ),
        ),
      ),
      bottomNavigationBar: _buildStickyCTA(context, items.isNotEmpty),
    );
  }

  Widget _buildHeader(double total) {
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
            'FINAL REVIEW',
            style: TextStyle(
              fontSize: 11,
              letterSpacing: 1,
              fontWeight: FontWeight.w900,
              color: Colors.white70,
            ),
          ),
          const SizedBox(height: 8),
          const Text(
            'Review your generated package before placing the order.',
            style: TextStyle(
              fontSize: 15,
              height: 1.4,
              fontWeight: FontWeight.w700,
              color: Colors.white,
            ),
          ),
          const SizedBox(height: 14),
          const Text(
            'Grand Total',
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

  Widget _buildSetupSnapshot(WizardState wizard) {
    return Container(
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(18),
        border: Border.all(color: _border),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            'Setup Snapshot',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w900,
              color: _textDark,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Quick summary of the setup behind this order.',
            style: TextStyle(
              fontSize: 12.5,
              color: _textMuted,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 16),
          Wrap(
            runSpacing: 10,
            spacing: 16,
            children: [
              _snapshotItem(
                'Business',
                wizard.businessType.isEmpty ? '—' : wizard.businessType,
              ),
              _snapshotItem(
                'Name',
                wizard.businessName.isEmpty ? '—' : wizard.businessName,
              ),
              _snapshotItem(
                'Budget',
                wizard.budgetRange.isEmpty ? '—' : wizard.budgetRange,
              ),
              _snapshotItem(
                'Tables',
                '${wizard.indoorTables + wizard.outdoorTables} total',
              ),
              _snapshotItem(
                'Services',
                wizard.installationServices.isEmpty
                    ? '—'
                    : '${wizard.installationServices.length} selected',
              ),
            ],
          ),
        ],
      ),
    );
  }

  Widget _snapshotItem(String label, String value) {
    return SizedBox(
      width: 180,
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(
              fontSize: 11,
              color: _textMuted,
              fontWeight: FontWeight.w700,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: const TextStyle(
              fontSize: 13,
              color: _textDark,
              fontWeight: FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildItems(List<dynamic> items) {
    if (items.isEmpty) {
      return Container(
        width: double.infinity,
        padding: const EdgeInsets.all(18),
        decoration: BoxDecoration(
          color: Colors.white,
          border: Border.all(color: _border),
          borderRadius: BorderRadius.circular(18),
        ),
        child: const Text(
          'No items selected.',
          style: TextStyle(
            fontSize: 14,
            color: _textMuted,
            fontWeight: FontWeight.w600,
          ),
        ),
      );
    }

    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          'Selected Items',
          style: TextStyle(
            fontSize: 16,
            fontWeight: FontWeight.w900,
            color: _textDark,
          ),
        ),
        const SizedBox(height: 12),
        ...items.map((item) => _mobileCard(item)),
      ],
    );
  }

  Widget _mobileCard(dynamic item) {
    final module = _text(item['module'], fallback: 'POS');
    final name = _text(item['name'], fallback: 'Item');
    final seller = _text(item['seller'], fallback: '—');
    final qty = _toDouble(item['qty'], fallback: 1);
    final price = _toDouble(item['price'], fallback: 0);
    final total = _itemTotal(item);

    return Container(
      margin: const EdgeInsets.only(bottom: 12),
      padding: const EdgeInsets.all(14),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: _border),
        borderRadius: BorderRadius.circular(16),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            module,
            style: const TextStyle(
              fontSize: 12,
              fontWeight: FontWeight.w800,
              color: _teal,
            ),
          ),
          const SizedBox(height: 6),
          Text(
            name,
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w800,
              color: _textDark,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            'Seller: $seller',
            style: const TextStyle(
              fontSize: 12,
              color: _textMuted,
              fontWeight: FontWeight.w600,
            ),
          ),
          const SizedBox(height: 10),
          _mobileRow('Unit', _money(price)),
          _mobileRow('Qty', qty.toStringAsFixed(0)),
          _mobileRow('Total', _money(total), bold: true),
        ],
      ),
    );
  }

  Widget _mobileRow(String label, String value, {bool bold = false}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 6),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: const TextStyle(
                color: _textMuted,
                fontWeight: FontWeight.w600,
              ),
            ),
          ),
          Text(
            value,
            style: TextStyle(
              color: _textDark,
              fontWeight: bold ? FontWeight.w800 : FontWeight.w600,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildGrandTotalCard(double total) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(16),
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: _border),
        borderRadius: BorderRadius.circular(18),
      ),
      child: Row(
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
              fontSize: 20,
              fontWeight: FontWeight.w900,
              color: _textDark,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStickyCTA(BuildContext context, bool hasItems) {
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
                onPressed: () => Navigator.pop(context),
                style: OutlinedButton.styleFrom(
                  foregroundColor: _textMuted,
                  side: const BorderSide(color: Color(0x33000000)),
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  minimumSize: const Size.fromHeight(50),
                ),
                child: const Text(
                  "Back",
                  style: TextStyle(fontWeight: FontWeight.w700),
                ),
              ),
            ),
            const SizedBox(width: 12),
            Expanded(
              child: ElevatedButton(
                onPressed: !hasItems
                    ? null
                    : () {
                        Navigator.push(
                          context,
                          MaterialPageRoute(
                            builder: (_) => const PlaceOrderScreen(),
                          ),
                        );
                      },
                style: ElevatedButton.styleFrom(
                  backgroundColor: _blue,
                  disabledBackgroundColor: Colors.grey.shade400,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(12),
                  ),
                  minimumSize: const Size.fromHeight(50),
                ),
                child: const Text(
                  "Place Order",
                  style: TextStyle(
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
}
