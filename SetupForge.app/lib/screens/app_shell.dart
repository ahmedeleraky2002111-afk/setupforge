import 'package:flutter/material.dart';
import '../services/api_service.dart';
import 'home_screen.dart';
import 'my_business_screen.dart';
import 'products_screen.dart';
import 'services_screen.dart';
import 'services_info_screen.dart';

class AppShell extends StatefulWidget {
  final int initialIndex;
  const AppShell({super.key, this.initialIndex = 0});

  @override
  State<AppShell> createState() => _AppShellState();
}

class _AppShellState extends State<AppShell> {
  late int _selectedIndex;
  String _setupState = "none"; // none, in_progress, completed
  final api = ApiService();

  @override
  void initState() {
    super.initState();
    _selectedIndex = widget.initialIndex;
    _loadSetupState();
  }

  Future<void> _loadSetupState() async {
    final res = await api.getHomeData();
    if (mounted) {
      setState(() {
        _setupState = res["setup_state"]?.toString() ?? "none";
      });
    }
  }

  List<Widget> get _screens => [
    const HomeScreen(),
    const MyBusinessScreen(),
    const ProductsScreen(),
    _setupState == "completed"
        ? const ServicesScreen()
        : ServicesInfoScreen(setupState: _setupState),
  ];

  void _onItemTapped(int index) {
    setState(() => _selectedIndex = index);
    // Refresh setup state when switching tabs
    if (index == 3) _loadSetupState();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      appBar: AppBar(
        backgroundColor: const Color(0xFF004CAC),
        elevation: 0,
        titleSpacing: 16,
        title: Image.asset('assets/logo.png', height: 32, fit: BoxFit.contain),
        actions: [
          IconButton(
            icon: const Icon(Icons.person_outline_rounded, color: Colors.white),
            onPressed: () => Navigator.pushNamed(context, '/profile'),
          ),
        ],
      ),
      body: IndexedStack(index: _selectedIndex, children: _screens),
      bottomNavigationBar: Container(
        decoration: BoxDecoration(
          color: Colors.white,
          border: const Border(top: BorderSide(color: Color(0xFFE5E7EB))),
          boxShadow: [
            BoxShadow(color: Colors.black.withOpacity(0.04), blurRadius: 10),
          ],
        ),
        child: BottomNavigationBar(
          currentIndex: _selectedIndex,
          onTap: _onItemTapped,
          type: BottomNavigationBarType.fixed,
          elevation: 0,
          backgroundColor: Colors.white,
          selectedItemColor: const Color(0xFF004CAC),
          unselectedItemColor: const Color(0xFF9CA3AF),
          selectedFontSize: 11.5,
          unselectedFontSize: 11,
          items: const [
            BottomNavigationBarItem(
              icon: Icon(Icons.home_rounded),
              label: 'Home',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.store_outlined),
              label: 'My Setup',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.storefront_outlined),
              label: 'Products',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.miscellaneous_services_outlined),
              label: 'Services',
            ),
          ],
        ),
      ),
    );
  }
}
