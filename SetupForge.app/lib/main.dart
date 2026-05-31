import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'screens/products_screen.dart';
import 'screens/services_screen.dart';
import 'app/app_theme.dart';
import 'screens/app_shell.dart';
import 'screens/auth_gate.dart';
import 'screens/login_screen.dart';
import 'screens/order_summary_screen.dart';
import 'screens/packages_screen.dart';
import 'screens/place_order_screen.dart';
import 'screens/profile_screen.dart';
import 'screens/setup_screen.dart';
import 'screens/signup_screen.dart';
import 'screens/splash_screen.dart';
import 'screens/success_screen.dart';
import 'screens/home_screen.dart';
import 'screens/my_business_screen.dart';
import 'state/wizard_state.dart';
import 'screens/labor_shell.dart';
import 'screens/labor_home_screen.dart';
import 'screens/labor_jobs_screen.dart';
import 'screens/labor_profile_screen.dart';
import 'screens/labor_signup_screen.dart';
import 'screens/role_select_screen.dart';
import 'screens/cart_screen.dart';
import 'screens/checkout_screen.dart';
import 'screens/service_select_screen.dart';
import 'screens/order_success_screen.dart';
import 'screens/setup_payment_screen.dart';
import 'screens/setup_success_screen.dart';

void main() {
  WidgetsFlutterBinding.ensureInitialized();
  runApp(const SetupForgeApp());
}

class SetupForgeApp extends StatelessWidget {
  const SetupForgeApp({super.key});

  @override
  Widget build(BuildContext context) {
    return ChangeNotifierProvider(
      create: (_) => WizardState(),
      child: MaterialApp(
        debugShowCheckedModeBanner: false,
        title: 'SetupForge',
        theme: AppTheme.lightTheme,
        initialRoute: '/splash',
        routes: {
          '/splash': (_) => const SplashScreen(),
          '/auth-gate': (_) => const AuthGate(),
          '/login': (_) => const LoginScreen(),
          '/signup': (_) => const SignupScreen(),
          '/app-shell': (ctx) {
            final args =
                ModalRoute.of(ctx)?.settings.arguments as Map<String, dynamic>?;
            return AppShell(initialIndex: args?['initialIndex'] as int? ?? 0);
          },
          '/home': (_) => const HomeScreen(),
          '/my-business': (_) => const MyBusinessScreen(),
          '/setup': (_) => const SetupScreen(),
          '/packages': (_) => const PackagesScreen(),
          '/place-order': (_) => const PlaceOrderScreen(),
          '/order-summary': (_) => const OrderSummaryScreen(),
          '/success': (_) => const SuccessScreen(),
          '/profile': (_) => const ProfileScreen(),
          '/explore': (_) => const ProductsScreen(),
          '/services': (_) => const ServicesScreen(),
          '/labor-shell': (_) => const LaborShell(initialIndex: 0),
          '/labor-home': (_) => const LaborHomeScreen(),
          '/labor-jobs': (_) => const LaborJobsScreen(),
          '/labor-profile': (_) => const LaborProfileScreen(),
          '/labor-signup': (_) => const LaborSignupScreen(),
          '/role-select': (_) => const RoleSelectScreen(),
          '/cart': (_) => const CartScreen(),
          '/checkout': (_) => const CheckoutScreen(),
          '/order-success': (_) => const OrderSuccessScreen(),
          '/service-select': (ctx) {
            final args =
                ModalRoute.of(ctx)!.settings.arguments as Map<String, dynamic>?;
            return ServiceSelectScreen(
              preselect: args?['preselect']?.toString() ?? '',
            );
          },
          '/setup-payment': (context) {
            final args = ModalRoute.of(context)!.settings.arguments as Map;
            return SetupPaymentScreen(
              iframeUrl: args['iframe_url'],
              orderId: args['order_id'],
            );
          },
          '/setup-success': (_) => const SetupSuccessScreen(),
        },
      ),
    );
  }
}
