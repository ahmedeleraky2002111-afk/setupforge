import 'package:flutter/material.dart';
import 'labor_home_screen.dart';
import 'labor_jobs_screen.dart';
import 'labor_profile_screen.dart';

class LaborShell extends StatefulWidget {
  final int initialIndex;
  const LaborShell({super.key, this.initialIndex = 0});

  @override
  State<LaborShell> createState() => _LaborShellState();
}

class _LaborShellState extends State<LaborShell> {
  late int _selectedIndex;

  @override
  void initState() {
    super.initState();
    _selectedIndex = widget.initialIndex;
  }

  final List<Widget> _screens = const [
    LaborHomeScreen(),
    LaborJobsScreen(),
    LaborProfileScreen(),
  ];

  void _onItemTapped(int index) {
    setState(() => _selectedIndex = index);
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
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
              icon: Icon(Icons.dashboard_rounded),
              label: 'Dashboard',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.work_rounded),
              label: 'My Jobs',
            ),
            BottomNavigationBarItem(
              icon: Icon(Icons.person_rounded),
              label: 'Profile',
            ),
          ],
        ),
      ),
    );
  }
}
