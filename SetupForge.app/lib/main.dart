import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'screens/explore_screen.dart';
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
          '/app-shell': (_) => const AppShell(initialIndex: 0),
          '/home': (_) => const HomeScreen(),
          '/my-business': (_) => const MyBusinessScreen(),
          '/setup': (_) => const SetupScreen(),
          '/packages': (_) => const PackagesScreen(),
          '/place-order': (_) => const PlaceOrderScreen(),
          '/order-summary': (_) => const OrderSummaryScreen(),
          '/success': (_) => const SuccessScreen(),
          '/profile': (_) => const ProfileScreen(),
          '/explore': (_) => const ExploreScreen(),
          '/services': (_) => const ServicesScreen(),
        },
      ),
    );
  }
}
