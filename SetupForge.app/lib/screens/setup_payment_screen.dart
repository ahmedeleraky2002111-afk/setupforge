import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

const Color sfBlue = Color(0xFF004CAC);

class SetupPaymentScreen extends StatefulWidget {
  final String iframeUrl;
  final int orderId;

  const SetupPaymentScreen({
    super.key,
    required this.iframeUrl,
    required this.orderId,
  });

  @override
  State<SetupPaymentScreen> createState() => _SetupPaymentScreenState();
}

class _SetupPaymentScreenState extends State<SetupPaymentScreen> {
  late final WebViewController _controller;
  bool _loading = true;

  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onPageStarted: (url) {
            setState(() => _loading = true);
            _checkUrl(url);
          },
          onPageFinished: (url) {
            setState(() => _loading = false);
            _checkUrl(url);
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.iframeUrl));
  }

  void _checkUrl(String url) {
    // Paymob redirects to your response URL after payment
    // success contains success=true, failure contains success=false
    if (url.contains('paymob_response') ||
        url.contains('success.php') ||
        url.contains('payment_failed')) {
      if (url.contains('success=true') || url.contains('success.php')) {
        // Payment successful
        Navigator.pushReplacementNamed(
          context,
          '/setup-success',
          arguments: {'order_id': widget.orderId},
        );
      } else if (url.contains('success=false') ||
          url.contains('payment_failed')) {
        // Payment failed
        Navigator.pop(context);
        ScaffoldMessenger.of(context).showSnackBar(
          const SnackBar(
            content: Text('Payment failed. Please try again.'),
            backgroundColor: Colors.red,
          ),
        );
      }
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: Colors.white,
      appBar: AppBar(
        backgroundColor: sfBlue,
        foregroundColor: Colors.white,
        title: const Text(
          'Complete Payment',
          style: TextStyle(fontWeight: FontWeight.w800, fontSize: 16),
        ),
        leading: IconButton(
          icon: const Icon(Icons.close),
          onPressed: () => Navigator.pop(context),
        ),
        elevation: 0,
      ),
      body: Stack(
        children: [
          WebViewWidget(controller: _controller),
          if (_loading)
            const Center(child: CircularProgressIndicator(color: sfBlue)),
        ],
      ),
    );
  }
}
