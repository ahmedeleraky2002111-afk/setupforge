import 'package:flutter/material.dart';

class ServiceSelectScreen extends StatefulWidget {
  final String preselect;
  const ServiceSelectScreen({super.key, this.preselect = ''});

  @override
  State<ServiceSelectScreen> createState() => _ServiceSelectScreenState();
}

class _ServiceSelectScreenState extends State<ServiceSelectScreen> {
  static const Color sfBlue = Color(0xFF004CAC);
  static const Color sfBg = Color(0xFFF5F7FB);
  static const Color sfText = Color(0xFF111827);
  static const Color sfMuted = Color(0xFF6B7280);

  final Set<String> _selected = {};

  final _services = [
    {
      'key': 'full_setup',
      'title': 'Full Setup',
      'desc':
          'Everything — equipment, staff, installation, finishing and advertising.',
      'icon': Icons.auto_awesome_rounded,
    },
    {
      'key': 'equipment',
      'title': 'Equipment & Products',
      'desc': 'Kitchen, POS, furniture, AC — full product setup with delivery.',
      'icon': Icons.inventory_2_outlined,
    },
    {
      'key': 'staff',
      'title': 'Staff & Labor',
      'desc':
          'Hire waiters, chefs, cashiers and other roles for your business.',
      'icon': Icons.people_outline_rounded,
    },
    {
      'key': 'installation',
      'title': 'Installation',
      'desc':
          'Professional installation for equipment, electrical, AC and network.',
      'icon': Icons.build_outlined,
    },
    {
      'key': 'finishing',
      'title': 'Finishing',
      'desc': 'Painting, flooring, gypsum, decor and facades.',
      'icon': Icons.brush_outlined,
    },
    {
      'key': 'advertising',
      'title': 'Advertising',
      'desc': 'Connect with advertising companies to promote your business.',
      'icon': Icons.campaign_outlined,
    },
  ];

  @override
  void initState() {
    super.initState();
    if (widget.preselect.isNotEmpty) {
      if (widget.preselect == 'full_setup') {
        _selected.addAll([
          'equipment',
          'staff',
          'installation',
          'finishing',
          'advertising',
        ]);
      } else {
        _selected.add(widget.preselect);
      }
    }
  }

  void _toggle(String key) {
    setState(() {
      if (key == 'full_setup') {
        final allSelected = _selected.containsAll([
          'equipment',
          'staff',
          'installation',
          'finishing',
          'advertising',
        ]);
        if (allSelected) {
          _selected.removeAll([
            'equipment',
            'staff',
            'installation',
            'finishing',
            'advertising',
            'full_setup',
          ]);
        } else {
          _selected.addAll([
            'equipment',
            'staff',
            'installation',
            'finishing',
            'advertising',
          ]);
          _selected.remove('full_setup');
        }
      } else {
        if (_selected.contains(key)) {
          _selected.remove(key);
        } else {
          _selected.add(key);
        }
        // Remove full_setup if any individual deselected
        _selected.remove('full_setup');
      }
    });
  }

  bool _isSelected(String key) {
    if (key == 'full_setup') {
      return _selected.containsAll([
        'equipment',
        'staff',
        'installation',
        'finishing',
        'advertising',
      ]);
    }
    return _selected.contains(key);
  }

  void _continue() {
    if (_selected.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select at least one service')),
      );
      return;
    }

    final services = _selected.where((s) => s != 'full_setup').toList();

    Navigator.pushNamed(context, '/setup', arguments: {'services': services});
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: sfBg,
      appBar: AppBar(
        backgroundColor: sfBlue,
        foregroundColor: Colors.white,
        title: const Text(
          'What do you need?',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        elevation: 0,
        shape: const RoundedRectangleBorder(),
      ),
      body: Column(
        children: [
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: Column(
                crossAxisAlignment: CrossAxisAlignment.start,
                children: [
                  const Text(
                    'What do you need for your business?',
                    style: TextStyle(
                      fontSize: 20,
                      fontWeight: FontWeight.w900,
                      color: sfText,
                    ),
                  ),
                  const SizedBox(height: 6),
                  const Text(
                    'Select one or more. We\'ll build the right flow for you.',
                    style: TextStyle(
                      fontSize: 13.5,
                      color: sfMuted,
                      height: 1.4,
                    ),
                  ),
                  const SizedBox(height: 20),
                  ..._services.map((s) {
                    final key = s['key'] as String;
                    final selected = _isSelected(key);
                    return GestureDetector(
                      onTap: () => _toggle(key),
                      child: Container(
                        margin: const EdgeInsets.only(bottom: 10),
                        padding: const EdgeInsets.all(16),
                        decoration: BoxDecoration(
                          color: selected
                              ? const Color(0xFFEFF6FF)
                              : Colors.white,
                          border: Border.all(
                            color: selected ? sfBlue : const Color(0xFFE5E7EB),
                            width: selected ? 2 : 1,
                          ),
                        ),
                        child: Row(
                          children: [
                            Container(
                              width: 48,
                              height: 48,
                              decoration: BoxDecoration(
                                color: selected
                                    ? sfBlue
                                    : const Color(0xFFF3F4F6),
                              ),
                              child: Icon(
                                s['icon'] as IconData,
                                color: selected ? Colors.white : sfMuted,
                                size: 22,
                              ),
                            ),
                            const SizedBox(width: 14),
                            Expanded(
                              child: Column(
                                crossAxisAlignment: CrossAxisAlignment.start,
                                children: [
                                  Text(
                                    s['title'] as String,
                                    style: TextStyle(
                                      fontSize: 14.5,
                                      fontWeight: FontWeight.w800,
                                      color: selected ? sfBlue : sfText,
                                    ),
                                  ),
                                  const SizedBox(height: 3),
                                  Text(
                                    s['desc'] as String,
                                    style: const TextStyle(
                                      fontSize: 12.5,
                                      color: sfMuted,
                                      height: 1.4,
                                    ),
                                  ),
                                ],
                              ),
                            ),
                            const SizedBox(width: 10),
                            Container(
                              width: 24,
                              height: 24,
                              decoration: BoxDecoration(
                                color: selected ? sfBlue : Colors.transparent,
                                shape: BoxShape.circle,
                                border: Border.all(
                                  color: selected
                                      ? sfBlue
                                      : const Color(0xFFD1D5DB),
                                  width: 2,
                                ),
                              ),
                              child: selected
                                  ? const Icon(
                                      Icons.check_rounded,
                                      color: Colors.white,
                                      size: 14,
                                    )
                                  : null,
                            ),
                          ],
                        ),
                      ),
                    );
                  }),
                ],
              ),
            ),
          ),
          Container(
            padding: const EdgeInsets.fromLTRB(16, 12, 16, 24),
            decoration: BoxDecoration(
              color: Colors.white,
              border: const Border(top: BorderSide(color: Color(0xFFE5E7EB))),
              boxShadow: [
                BoxShadow(
                  color: Colors.black.withOpacity(0.04),
                  blurRadius: 8,
                  offset: const Offset(0, -2),
                ),
              ],
            ),
            child: ElevatedButton(
              onPressed: _selected.isEmpty ? null : _continue,
              style: ElevatedButton.styleFrom(
                backgroundColor: sfBlue,
                disabledBackgroundColor: sfBlue.withOpacity(0.4),
                minimumSize: const Size(double.infinity, 52),
                shape: const RoundedRectangleBorder(),
              ),
              child: const Text(
                'Continue →',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w800,
                  fontSize: 15,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
