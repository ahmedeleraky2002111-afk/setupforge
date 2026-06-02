import 'package:flutter/material.dart';
import 'package:webview_flutter/webview_flutter.dart';

const Color sfBlue = Color(0xFF004CAC);

class SetupPaymentScreen extends StatefulWidget {
  final String iframeUrl;
  final int orderId;
  final String flow;

  const SetupPaymentScreen({
    super.key,
    required this.iframeUrl,
    required this.orderId,
    this.flow = 'setup',
  });

  @override
  State<SetupPaymentScreen> createState() => _SetupPaymentScreenState();
}

class _SetupPaymentScreenState extends State<SetupPaymentScreen> {
  late final WebViewController _controller;
  bool _loading = true;
  bool _handled = false;
  @override
  void initState() {
    super.initState();
    _controller = WebViewController()
      ..setJavaScriptMode(JavaScriptMode.unrestricted)
      ..setNavigationDelegate(
        NavigationDelegate(
          onNavigationRequest: (request) {
            _checkUrl(request.url);
            if (_handled) return NavigationDecision.prevent;
            return NavigationDecision.navigate;
          },
          onPageStarted: (url) {
            _checkUrl(url);
            if (!_handled) setState(() => _loading = true);
          },
          onPageFinished: (url) {
            if (!_handled) setState(() => _loading = false);
            _checkUrl(url);
          },
        ),
      )
      ..loadRequest(Uri.parse(widget.iframeUrl));
  }

  void _checkUrl(String url) {
    if (_handled) return;
    if (url.contains('paymob_response') ||
        url.contains('success.php') ||
        url.contains('payment_failed')) {
      if (url.contains('success=true') || url.contains('success.php')) {
        // Payment successful
        _handled = true;
        if (widget.flow == 'shop') {
          Navigator.pushNamedAndRemoveUntil(
            context,
            '/order-success',
            (route) => route.settings.name == '/app-shell',
            arguments: {'order_id': widget.orderId, 'total': 0},
          );
        } else {
          Navigator.pushNamedAndRemoveUntil(
            context,
            '/app-shell',
            (route) => false,
            arguments: {'forceRefresh': true, 'initialIndex': 3},
          );
        }
      } else if (url.contains('success=false') ||
          url.contains('payment_failed')) {
        // Payment failed
        _handled = true;
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
          onPressed: () => Navigator.pushNamedAndRemoveUntil(
            context,
            '/app-shell',
            (route) => false,
          ),
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
