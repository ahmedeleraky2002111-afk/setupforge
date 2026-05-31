import 'package:flutter/material.dart';
import '../services/api_service.dart';

class SetupScreen extends StatefulWidget {
  const SetupScreen({super.key});

  @override
  State<SetupScreen> createState() => _SetupScreenState();
}

class _SetupScreenState extends State<SetupScreen> {
  static const Color sfBlue = Color(0xFF004CAC);
  static const Color sfBg = Color(0xFFF5F7FB);
  static const Color sfText = Color(0xFF111827);
  static const Color sfMuted = Color(0xFF6B7280);

  final api = ApiService();

  // Wizard state
  List<String> _services = [];
  int _step = 0;
  bool _loading = true;
  bool _saving = false;

  // Step data
  String _businessName = '';
  String _businessType = '';
  String _restaurantType = '';
  int _indoorTables = 5;
  int _outdoorTables = 0;
  int _areaSqm = 50;
  int _floorCount = 1;
  int _budget = 0;
  List<String> _installationServices = [];
  Map<String, int> _staffCounts = {
    'waiter': 0,
    'chef': 0,
    'cashier': 0,
    'security': 0,
    'barista': 0,
    'busboy': 0,
    'host': 0,
    'kitchen_helper': 0,
  };

  final _nameC = TextEditingController();

  // Computed
  bool get _hasEquipment => _services.contains('equipment');
  bool get _hasInstall => _services.contains('installation');
  bool get _hasStaff => _services.contains('staff');
  bool get _hasFinishing => _services.contains('finishing');
  bool get _hasAdvertising => _services.contains('advertising');

  List<int> get _displaySteps {
    final steps = [0, 1];
    if (_businessType == 'Restaurant') steps.add(2);
    if (_hasEquipment) steps.add(3);
    if (_hasInstall) steps.add(4);
    if (_hasEquipment) steps.add(5);
    if (_hasInstall) steps.add(6);
    if (_hasStaff) steps.add(7);
    return steps;
  }

  int get _currentStepIndex => _displaySteps.indexOf(_step);
  int get _totalSteps => _displaySteps.length;

  @override
  void initState() {
    super.initState();
    // Read services from arguments after first frame
    WidgetsBinding.instance.addPostFrameCallback((_) => _init());
  }

  Future<void> _init() async {
    final args =
        ModalRoute.of(context)?.settings.arguments as Map<String, dynamic>?;
    final services = List<String>.from(args?['services'] ?? []);

    setState(() => _services = services);

    // Try to resume
    final resumeRes = await api.resumeWizard();
    if (!mounted) return;

    if (resumeRes["ok"] == true && resumeRes["has_saved"] == true) {
      final w = Map<String, dynamic>.from(resumeRes["wizard"] ?? {});
      final savedStep = resumeRes["saved_step"] as int;
      final savedServices = List<String>.from(w["services"] ?? []);

      setState(() {
        _services = savedServices.isNotEmpty ? savedServices : services;
        _businessName = w["business_name"] ?? '';
        _businessType = w["business_type"] ?? '';
        _restaurantType = w["restaurant_type"] ?? '';
        _indoorTables = (w["indoor_tables"] as int?) ?? 5;
        _outdoorTables = (w["outdoor_tables"] as int?) ?? 0;
        _areaSqm = (w["area_sqm"] as int?) ?? 50;
        _budget = (w["budget"] as int?) ?? 0;
        _floorCount = (w["floor_count"] as int?) ?? 1;
        _installationServices = List<String>.from(
          w["installation_services"] ?? [],
        );
        for (final role in _staffCounts.keys) {
          _staffCounts[role] = (w["${role}_count"] as int?) ?? 0;
        }
        _nameC.text = _businessName;
        _step = savedStep;
        _loading = false;
      });
    } else {
      setState(() => _loading = false);
    }
  }

  @override
  void dispose() {
    _nameC.dispose();
    super.dispose();
  }

  Map<String, dynamic> get _wizardData => {
    "business_name": _businessName,
    "business_type": _businessType,
    "restaurant_type": _restaurantType,
    "indoor_tables": _indoorTables,
    "outdoor_tables": _outdoorTables,
    "area_sqm": _areaSqm,
    "floor_count": _floorCount,
    "budget": _budget,
    "services": _services,
    "installation_services": _installationServices,
    ..._staffCounts.map((k, v) => MapEntry("${k}_count", v)),
  };

  Future<void> _saveStep() async {
    setState(() => _saving = true);
    await api.saveWizardStep(step: _step, wizard: _wizardData);
    if (mounted) setState(() => _saving = false);
  }

  Future<void> _next() async {
    // Validate current step
    if (_step == 0 && _nameC.text.trim().isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please enter your business name')),
      );
      return;
    }
    if (_step == 1 && _businessType.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a business type')),
      );
      return;
    }
    if (_step == 2 && _restaurantType.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a restaurant type')),
      );
      return;
    }
    if (_step == 5 && _budget == 0) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please select a budget range')),
      );
      return;
    }

    // Save step 0 business name
    if (_step == 0) {
      setState(() => _businessName = _nameC.text.trim());
    }

    await _saveStep();

    final idx = _currentStepIndex;
    if (idx < _totalSteps - 1) {
      setState(() => _step = _displaySteps[idx + 1]);
    } else {
      _finish();
    }
  }

  void _back() {
    final idx = _currentStepIndex;
    if (idx > 0) {
      setState(() => _step = _displaySteps[idx - 1]);
    } else {
      Navigator.pop(context);
    }
  }

  void _finish() {
    if (_hasEquipment) {
      Navigator.pushNamed(
        context,
        '/packages',
        arguments: {'wizard': _wizardData},
      );
    } else {
      // No equipment — go to services tab
      Navigator.pushNamedAndRemoveUntil(
        context,
        '/app-shell',
        (route) => false,
        arguments: {'initialIndex': 3},
      );
    }
  }

  bool get _isLastStep => _currentStepIndex == _totalSteps - 1;

  @override
  Widget build(BuildContext context) {
    if (_loading) {
      return const Scaffold(
        backgroundColor: sfBg,
        body: Center(child: CircularProgressIndicator(color: sfBlue)),
      );
    }

    return Scaffold(
      backgroundColor: sfBg,
      appBar: AppBar(
        backgroundColor: sfBlue,
        foregroundColor: Colors.white,
        title: const Text(
          'Setup',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        elevation: 0,
        shape: const RoundedRectangleBorder(),
        leading: IconButton(
          onPressed: _back,
          icon: const Icon(Icons.arrow_back_rounded),
        ),
      ),
      body: Column(
        children: [
          // Progress bar
          Container(
            color: sfBlue,
            padding: const EdgeInsets.fromLTRB(16, 0, 16, 14),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                ClipRect(
                  child: LinearProgressIndicator(
                    value: _totalSteps > 0
                        ? (_currentStepIndex + 1) / _totalSteps
                        : 0,
                    backgroundColor: Colors.white.withOpacity(0.3),
                    valueColor: const AlwaysStoppedAnimation<Color>(
                      Colors.white,
                    ),
                    minHeight: 4,
                  ),
                ),
                const SizedBox(height: 6),
                Text(
                  'Step ${_currentStepIndex + 1} of $_totalSteps',
                  style: TextStyle(
                    color: Colors.white.withOpacity(0.8),
                    fontSize: 12,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ],
            ),
          ),

          // Content
          Expanded(
            child: SingleChildScrollView(
              padding: const EdgeInsets.all(16),
              child: _buildStep(),
            ),
          ),

          // Bottom buttons
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
            child: Row(
              children: [
                if (_currentStepIndex > 0) ...[
                  Expanded(
                    child: OutlinedButton(
                      onPressed: _saving ? null : _back,
                      style: OutlinedButton.styleFrom(
                        padding: const EdgeInsets.symmetric(vertical: 14),
                        side: const BorderSide(color: Color(0xFFE5E7EB)),
                        shape: const RoundedRectangleBorder(),
                      ),
                      child: const Text(
                        '← Back',
                        style: TextStyle(
                          fontWeight: FontWeight.w700,
                          fontSize: 14,
                        ),
                      ),
                    ),
                  ),
                  const SizedBox(width: 12),
                ],
                Expanded(
                  child: ElevatedButton(
                    onPressed: _saving ? null : _next,
                    style: ElevatedButton.styleFrom(
                      backgroundColor: sfBlue,
                      padding: const EdgeInsets.symmetric(vertical: 14),
                      shape: const RoundedRectangleBorder(),
                    ),
                    child: _saving
                        ? const SizedBox(
                            width: 20,
                            height: 20,
                            child: CircularProgressIndicator(
                              strokeWidth: 2,
                              color: Colors.white,
                            ),
                          )
                        : Text(
                            _isLastStep
                                ? (_hasEquipment
                                      ? 'View Packages →'
                                      : 'Finish →')
                                : 'Next →',
                            style: const TextStyle(
                              color: Colors.white,
                              fontWeight: FontWeight.w800,
                              fontSize: 15,
                            ),
                          ),
                  ),
                ),
              ],
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildStep() {
    switch (_step) {
      case 0:
        return _stepWrap(
          title: 'What\'s your business name?',
          subtitle: 'We\'ll use it to personalize your setup experience.',
          child: TextField(
            controller: _nameC,
            onChanged: (v) => setState(() => _businessName = v),
            autofocus: true,
            decoration: const InputDecoration(
              hintText: 'Business name',
              filled: true,
              fillColor: Colors.white,
              border: OutlineInputBorder(
                borderRadius: BorderRadius.zero,
                borderSide: BorderSide(color: Color(0xFFE5E7EB)),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.zero,
                borderSide: BorderSide(color: Color(0xFFE5E7EB)),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.zero,
                borderSide: BorderSide(color: sfBlue, width: 1.5),
              ),
            ),
          ),
        );

      case 1:
        final types = [
          {'value': 'Restaurant', 'icon': Icons.restaurant_rounded},
          {'value': 'Café', 'icon': Icons.local_cafe_rounded},
          {'value': 'Gym', 'icon': Icons.fitness_center_rounded},
          {'value': 'Salon', 'icon': Icons.content_cut_rounded},
        ];
        return _stepWrap(
          title: 'What type of business?',
          subtitle: 'This helps us tailor your setup experience.',
          child: GridView.count(
            crossAxisCount: 2,
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            crossAxisSpacing: 12,
            mainAxisSpacing: 12,
            childAspectRatio: 1.4,
            children: types.map((t) {
              final selected = _businessType == t['value'];
              return GestureDetector(
                onTap: () =>
                    setState(() => _businessType = t['value'] as String),
                child: Container(
                  decoration: BoxDecoration(
                    color: selected ? const Color(0xFFEFF6FF) : Colors.white,
                    border: Border.all(
                      color: selected ? sfBlue : const Color(0xFFE5E7EB),
                      width: selected ? 2 : 1,
                    ),
                  ),
                  child: Column(
                    mainAxisAlignment: MainAxisAlignment.center,
                    children: [
                      Icon(
                        t['icon'] as IconData,
                        color: selected ? sfBlue : sfMuted,
                        size: 28,
                      ),
                      const SizedBox(height: 8),
                      Text(
                        t['value'] as String,
                        style: TextStyle(
                          fontSize: 14,
                          fontWeight: FontWeight.w800,
                          color: selected ? sfBlue : sfText,
                        ),
                      ),
                      if (selected)
                        const Padding(
                          padding: EdgeInsets.only(top: 4),
                          child: Icon(
                            Icons.check_rounded,
                            color: sfBlue,
                            size: 16,
                          ),
                        ),
                    ],
                  ),
                ),
              );
            }).toList(),
          ),
        );

      case 2:
        final types = [
          {
            'value': 'fast_food',
            'label': 'Fast Food',
            'desc': 'Quick service, high turnover',
            'icon': Icons.fastfood_rounded,
          },
          {
            'value': 'standard_dining',
            'label': 'Casual Dining',
            'desc': 'Full-service sit-down restaurant',
            'icon': Icons.restaurant_rounded,
          },
          {
            'value': 'premium_dining',
            'label': 'Premium Dining',
            'desc': 'Fine dining, upscale experience',
            'icon': Icons.star_border_rounded,
          },
          {
            'value': 'cloud_kitchen',
            'label': 'Delivery Only',
            'desc': 'Cloud kitchen, no dine-in',
            'icon': Icons.delivery_dining_rounded,
          },
        ];
        return _stepWrap(
          title: 'What type of restaurant?',
          subtitle: 'This helps us tailor layout and equipment.',
          child: Column(
            children: types.map((t) {
              final selected = _restaurantType == t['value'];
              return GestureDetector(
                onTap: () =>
                    setState(() => _restaurantType = t['value'] as String),
                child: Container(
                  margin: const EdgeInsets.only(bottom: 10),
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: selected ? const Color(0xFFEFF6FF) : Colors.white,
                    border: Border.all(
                      color: selected ? sfBlue : const Color(0xFFE5E7EB),
                      width: selected ? 2 : 1,
                    ),
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          color: selected ? sfBlue : const Color(0xFFF3F4F6),
                        ),
                        child: Icon(
                          t['icon'] as IconData,
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
                              t['label'] as String,
                              style: TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w800,
                                color: selected ? sfBlue : sfText,
                              ),
                            ),
                            Text(
                              t['desc'] as String,
                              style: const TextStyle(
                                fontSize: 12.5,
                                color: sfMuted,
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (selected)
                        const Icon(
                          Icons.check_rounded,
                          color: sfBlue,
                          size: 20,
                        ),
                    ],
                  ),
                ),
              );
            }).toList(),
          ),
        );

      case 3:
        return _stepWrap(
          title: 'How many tables?',
          subtitle: 'Indoor and outdoor seating helps us size your setup.',
          child: Column(
            children: [
              _tableRow('Indoor Tables', _indoorTables, [5, 10, 15, 20], (v) {
                setState(() => _indoorTables = v);
              }, min: 1),
              const SizedBox(height: 20),
              _tableRow('Outdoor Tables', _outdoorTables, [0, 5, 10, 15], (v) {
                setState(() => _outdoorTables = v);
              }, min: 0),
            ],
          ),
        );

      case 4:
        return _stepWrap(
          title: 'What\'s your restaurant\'s area?',
          subtitle: 'Indoor area helps us calculate AC units needed.',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              Row(
                mainAxisAlignment: MainAxisAlignment.spaceBetween,
                children: [
                  const Text(
                    'Indoor Area',
                    style: TextStyle(
                      fontSize: 14,
                      fontWeight: FontWeight.w700,
                      color: sfText,
                    ),
                  ),
                  Container(
                    padding: const EdgeInsets.symmetric(
                      horizontal: 12,
                      vertical: 6,
                    ),
                    color: const Color(0xFFEFF6FF),
                    child: Text(
                      '$_areaSqm m²',
                      style: const TextStyle(
                        fontSize: 14,
                        fontWeight: FontWeight.w800,
                        color: sfBlue,
                      ),
                    ),
                  ),
                ],
              ),
              const SizedBox(height: 12),
              SliderTheme(
                data: SliderTheme.of(context).copyWith(
                  trackShape: const RectangularSliderTrackShape(),
                  thumbShape: const RoundSliderThumbShape(
                    enabledThumbRadius: 10,
                  ),
                  overlayShape: const RoundSliderOverlayShape(
                    overlayRadius: 20,
                  ),
                  activeTrackColor: sfBlue,
                  inactiveTrackColor: const Color(0xFFE5E7EB),
                  thumbColor: sfBlue,
                ),
                child: Slider(
                  value: _areaSqm.toDouble(),
                  min: 10,
                  max: 500,
                  divisions: 98,
                  onChanged: (v) => setState(() => _areaSqm = v.round()),
                ),
              ),
              const SizedBox(height: 20),
              // Multi-floor toggle
              Container(
                padding: const EdgeInsets.all(14),
                decoration: BoxDecoration(
                  color: Colors.white,
                  border: Border.all(color: const Color(0xFFE5E7EB)),
                ),
                child: Column(
                  children: [
                    Row(
                      children: [
                        Expanded(
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: const [
                              Text(
                                'Multiple floors?',
                                style: TextStyle(
                                  fontSize: 14,
                                  fontWeight: FontWeight.w700,
                                  color: sfText,
                                ),
                              ),
                              Text(
                                'Check if your space has more than one floor',
                                style: TextStyle(fontSize: 12, color: sfMuted),
                              ),
                            ],
                          ),
                        ),
                        Switch(
                          value: _floorCount > 1,
                          activeColor: sfBlue,
                          onChanged: (v) =>
                              setState(() => _floorCount = v ? 2 : 1),
                        ),
                      ],
                    ),
                    if (_floorCount > 1) ...[
                      const SizedBox(height: 12),
                      Row(
                        mainAxisAlignment: MainAxisAlignment.spaceBetween,
                        children: [
                          const Text(
                            'Number of floors',
                            style: TextStyle(fontSize: 13, color: sfMuted),
                          ),
                          Row(
                            children: [
                              _stepBtn(Icons.remove, () {
                                if (_floorCount > 2)
                                  setState(() => _floorCount--);
                              }),
                              Padding(
                                padding: const EdgeInsets.symmetric(
                                  horizontal: 16,
                                ),
                                child: Text(
                                  '$_floorCount',
                                  style: const TextStyle(
                                    fontSize: 16,
                                    fontWeight: FontWeight.w800,
                                    color: sfText,
                                  ),
                                ),
                              ),
                              _stepBtn(Icons.add, () {
                                if (_floorCount < 10)
                                  setState(() => _floorCount++);
                              }),
                            ],
                          ),
                        ],
                      ),
                    ],
                  ],
                ),
              ),
            ],
          ),
        );

      case 5:
        final budgets = [
          {
            'label': 'Under 600,000 EGP',
            'sub': 'Small / street food',
            'value': 400000,
          },
          {
            'label': '600,000 – 2,000,000 EGP',
            'sub': 'Casual dining',
            'value': 1200000,
          },
          {
            'label': '2,000,000 – 3,500,000 EGP',
            'sub': 'Full fit-out',
            'value': 2750000,
          },
          {
            'label': '3,500,000+ EGP',
            'sub': 'Premium restaurant',
            'value': 4500000,
          },
        ];
        return _stepWrap(
          title: 'What\'s your budget?',
          subtitle: 'Helps us recommend the right products and tier.',
          child: Column(
            children: budgets.map((b) {
              final selected = _budget == b['value'];
              return GestureDetector(
                onTap: () => setState(() => _budget = b['value'] as int),
                child: Container(
                  margin: const EdgeInsets.only(bottom: 10),
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: selected ? const Color(0xFFEFF6FF) : Colors.white,
                    border: Border.all(
                      color: selected ? sfBlue : const Color(0xFFE5E7EB),
                      width: selected ? 2 : 1,
                    ),
                  ),
                  child: Row(
                    children: [
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              b['label'] as String,
                              style: TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w800,
                                color: selected ? sfBlue : sfText,
                              ),
                            ),
                            Text(
                              b['sub'] as String,
                              style: const TextStyle(
                                fontSize: 12.5,
                                color: sfMuted,
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (selected)
                        const Icon(
                          Icons.check_rounded,
                          color: sfBlue,
                          size: 20,
                        ),
                    ],
                  ),
                ),
              );
            }).toList(),
          ),
        );

      case 6:
        final services = [
          {
            'value': 'pos',
            'label': 'POS System',
            'desc': 'Cash register & payment terminal',
            'icon': Icons.point_of_sale_rounded,
          },
          {
            'value': 'electrical',
            'label': 'Electrical Wiring',
            'desc': 'Outlets, lighting & power setup',
            'icon': Icons.electrical_services_rounded,
          },
          {
            'value': 'network',
            'label': 'Network & WiFi',
            'desc': 'Internet, router & cabling',
            'icon': Icons.wifi_rounded,
          },
          {
            'value': 'ac',
            'label': 'AC Installation',
            'desc': 'Air conditioning & ventilation',
            'icon': Icons.ac_unit_rounded,
          },
          {
            'value': 'kitchen',
            'label': 'Kitchen Setup',
            'desc': 'Equipment installation & gas',
            'icon': Icons.kitchen_rounded,
          },
        ];
        return _stepWrap(
          title: 'Installation services',
          subtitle: 'Select the services you need installed.',
          child: Column(
            children: services.map((s) {
              final selected = _installationServices.contains(s['value']);
              return GestureDetector(
                onTap: () {
                  setState(() {
                    final key = s['value'] as String;
                    if (selected) {
                      _installationServices.remove(key);
                    } else {
                      _installationServices.add(key);
                    }
                  });
                },
                child: Container(
                  margin: const EdgeInsets.only(bottom: 10),
                  padding: const EdgeInsets.all(14),
                  decoration: BoxDecoration(
                    color: selected ? const Color(0xFFEFF6FF) : Colors.white,
                    border: Border.all(
                      color: selected ? sfBlue : const Color(0xFFE5E7EB),
                      width: selected ? 2 : 1,
                    ),
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          color: selected ? sfBlue : const Color(0xFFF3F4F6),
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
                              s['label'] as String,
                              style: TextStyle(
                                fontSize: 14,
                                fontWeight: FontWeight.w800,
                                color: selected ? sfBlue : sfText,
                              ),
                            ),
                            Text(
                              s['desc'] as String,
                              style: const TextStyle(
                                fontSize: 12.5,
                                color: sfMuted,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Container(
                        width: 24,
                        height: 24,
                        decoration: BoxDecoration(
                          color: selected ? sfBlue : Colors.transparent,
                          shape: BoxShape.circle,
                          border: Border.all(
                            color: selected ? sfBlue : const Color(0xFFD1D5DB),
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
            }).toList(),
          ),
        );

      case 7:
        final roles = [
          {
            'key': 'waiter',
            'label': 'Waiters',
            'desc': 'Front-of-house, serving tables',
            'icon': Icons.room_service_outlined,
          },
          {
            'key': 'chef',
            'label': 'Chefs',
            'desc': 'Kitchen staff & food preparation',
            'icon': Icons.outdoor_grill_outlined,
          },
          {
            'key': 'cashier',
            'label': 'Cashiers',
            'desc': 'Billing & payment handling',
            'icon': Icons.payments_outlined,
          },
          {
            'key': 'security',
            'label': 'Security',
            'desc': 'Entrance & premises safety',
            'icon': Icons.security_outlined,
          },
          {
            'key': 'barista',
            'label': 'Baristas',
            'desc': 'Coffee & beverages',
            'icon': Icons.local_cafe_outlined,
          },
          {
            'key': 'busboy',
            'label': 'Table Cleaners',
            'desc': 'Table clearing & resetting',
            'icon': Icons.cleaning_services_outlined,
          },
          {
            'key': 'host',
            'label': 'Reception Staff',
            'desc': 'Greeting & seating customers',
            'icon': Icons.record_voice_over_outlined,
          },
          {
            'key': 'kitchen_helper',
            'label': 'Kitchen Helpers',
            'desc': 'Dishwashing & prep support',
            'icon': Icons.soup_kitchen_outlined,
          },
        ];
        final totalStaff = _staffCounts.values.fold(0, (a, b) => a + b);
        return _stepWrap(
          title: 'Staffing',
          subtitle: 'Set the number of staff you need for each role.',
          child: Column(
            children: [
              ...roles.map((r) {
                final key = r['key'] as String;
                final count = _staffCounts[key] ?? 0;
                final active = count > 0;
                return Container(
                  margin: const EdgeInsets.only(bottom: 8),
                  padding: const EdgeInsets.all(12),
                  decoration: BoxDecoration(
                    color: active ? const Color(0xFFEFF6FF) : Colors.white,
                    border: Border.all(
                      color: active ? sfBlue : const Color(0xFFE5E7EB),
                    ),
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 40,
                        height: 40,
                        decoration: BoxDecoration(
                          color: active ? sfBlue : const Color(0xFFF3F4F6),
                        ),
                        child: Icon(
                          r['icon'] as IconData,
                          color: active ? Colors.white : sfMuted,
                          size: 18,
                        ),
                      ),
                      const SizedBox(width: 12),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              r['label'] as String,
                              style: TextStyle(
                                fontSize: 13.5,
                                fontWeight: FontWeight.w700,
                                color: active ? sfBlue : sfText,
                              ),
                            ),
                            Text(
                              r['desc'] as String,
                              style: const TextStyle(
                                fontSize: 11.5,
                                color: sfMuted,
                              ),
                            ),
                          ],
                        ),
                      ),
                      Row(
                        children: [
                          _stepBtn(
                            Icons.remove,
                            count > 0
                                ? () => setState(
                                    () => _staffCounts[key] = count - 1,
                                  )
                                : null,
                          ),
                          SizedBox(
                            width: 32,
                            child: Text(
                              '$count',
                              textAlign: TextAlign.center,
                              style: const TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w800,
                                color: sfText,
                              ),
                            ),
                          ),
                          _stepBtn(
                            Icons.add,
                            () => setState(() => _staffCounts[key] = count + 1),
                          ),
                        ],
                      ),
                    ],
                  ),
                );
              }),
              const SizedBox(height: 8),
              Container(
                padding: const EdgeInsets.all(12),
                color: const Color(0xFFEFF6FF),
                child: Row(
                  mainAxisAlignment: MainAxisAlignment.spaceBetween,
                  children: [
                    const Text(
                      'Total Staff',
                      style: TextStyle(
                        fontSize: 13,
                        fontWeight: FontWeight.w700,
                        color: sfBlue,
                      ),
                    ),
                    Text(
                      '$totalStaff',
                      style: const TextStyle(
                        fontSize: 18,
                        fontWeight: FontWeight.w900,
                        color: sfBlue,
                      ),
                    ),
                  ],
                ),
              ),
            ],
          ),
        );

      default:
        return const SizedBox.shrink();
    }
  }

  Widget _stepWrap({
    required String title,
    required String subtitle,
    required Widget child,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          title,
          style: const TextStyle(
            fontSize: 22,
            fontWeight: FontWeight.w900,
            color: sfText,
          ),
        ),
        const SizedBox(height: 6),
        Text(
          subtitle,
          style: const TextStyle(fontSize: 13.5, color: sfMuted, height: 1.4),
        ),
        const SizedBox(height: 20),
        child,
      ],
    );
  }

  Widget _tableRow(
    String label,
    int value,
    List<int> presets,
    void Function(int) onChange, {
    int min = 0,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          mainAxisAlignment: MainAxisAlignment.spaceBetween,
          children: [
            Text(
              label,
              style: const TextStyle(
                fontSize: 14,
                fontWeight: FontWeight.w700,
                color: sfText,
              ),
            ),
            Container(
              padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
              color: const Color(0xFFEFF6FF),
              child: Text(
                '$value',
                style: const TextStyle(
                  fontSize: 14,
                  fontWeight: FontWeight.w800,
                  color: sfBlue,
                ),
              ),
            ),
          ],
        ),
        const SizedBox(height: 10),
        // Presets
        Row(
          children: presets.map((p) {
            final active = value == p;
            return GestureDetector(
              onTap: () => onChange(p),
              child: Container(
                margin: const EdgeInsets.only(right: 8),
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 8,
                ),
                decoration: BoxDecoration(
                  color: active ? sfBlue : Colors.white,
                  border: Border.all(
                    color: active ? sfBlue : const Color(0xFFE5E7EB),
                  ),
                ),
                child: Text(
                  '$p',
                  style: TextStyle(
                    fontSize: 13,
                    fontWeight: FontWeight.w700,
                    color: active ? Colors.white : sfText,
                  ),
                ),
              ),
            );
          }).toList(),
        ),
        const SizedBox(height: 10),
        // Stepper
        Row(
          children: [
            _stepBtn(
              Icons.remove,
              value > min ? () => onChange(value - 1) : null,
            ),
            Padding(
              padding: const EdgeInsets.symmetric(horizontal: 16),
              child: Text(
                '$value',
                style: const TextStyle(
                  fontSize: 16,
                  fontWeight: FontWeight.w800,
                  color: sfText,
                ),
              ),
            ),
            _stepBtn(Icons.add, () => onChange(value + 1)),
          ],
        ),
      ],
    );
  }

  Widget _stepBtn(IconData icon, VoidCallback? onTap) {
    return GestureDetector(
      onTap: onTap,
      child: Container(
        width: 36,
        height: 36,
        decoration: BoxDecoration(
          color: onTap != null ? sfBlue : const Color(0xFFF3F4F6),
        ),
        child: Icon(
          icon,
          size: 18,
          color: onTap != null ? Colors.white : const Color(0xFFD1D5DB),
        ),
      ),
    );
  }
}
