import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:video_player/video_player.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../state/wizard_state.dart';

class SetupScreen extends StatefulWidget {
  const SetupScreen({super.key});

  @override
  State<SetupScreen> createState() => _SetupScreenState();
}

class _SetupScreenState extends State<SetupScreen> {
  static const Color sfBlue = Color(0xFF004CAC);
  // ignore: unused_field
  static const Color sfTeal = Color(0xFF009994);
  static const Color bg = Color(0xFFF5F7FB);
  static const Color text = Color(0xFF121212);
  static const Color muted = Color(0xFF6C757D);
  static const Color border = Color(0x22000000);

  int step = 0;

  late TextEditingController nameController;
  late TextEditingController areaController;

  late final Map<String, VideoPlayerController> _videoControllers;

  final List<_BusinessOption> businessOptions = const [
    _BusinessOption(
      title: 'Restaurant',
      videoPath: 'assets/restaurant.mp4',
      icon: Icons.restaurant_rounded,
    ),
    _BusinessOption(
      title: 'Cafe',
      videoPath: 'assets/cafe.mp4',
      icon: Icons.local_cafe_rounded,
    ),
    _BusinessOption(
      title: 'Gym',
      videoPath: 'assets/gym.mp4',
      icon: Icons.fitness_center_rounded,
    ),
    _BusinessOption(
      title: 'Office',
      videoPath: 'assets/office.mp4',
      icon: Icons.business_center_rounded,
    ),
  ];

  @override
  void initState() {
    super.initState();

    final wizard = Provider.of<WizardState>(context, listen: false);

    nameController = TextEditingController(text: wizard.businessName);
    areaController = TextEditingController(
      text: wizard.areaSqm == 0 ? '' : wizard.areaSqm.toString(),
    );

    _videoControllers = {
      for (final option in businessOptions)
        option.title: VideoPlayerController.asset(option.videoPath),
    };

    _initializeVideos();
  }

  Future<void> _initializeVideos() async {
    for (final controller in _videoControllers.values) {
      await controller.initialize();
      await controller.setLooping(true);
      await controller.setVolume(0);
      await controller.play();
    }

    if (mounted) setState(() {});
  }

  @override
  void dispose() {
    nameController.dispose();
    areaController.dispose();

    for (final controller in _videoControllers.values) {
      controller.dispose();
    }

    super.dispose();
  }

  Future<void> _nextStep(WizardState wizard) async {
    if (step == 1) {
      wizard.setBusinessName(nameController.text.trim());
    }
    if (step == 2) {
      wizard.setTableSize(4); // default until ratio system implemented
    }

    if (step == 4) {
      wizard.setAreaSqm(int.tryParse(areaController.text.trim()) ?? 0);
    }

    if (step < 8) {
      setState(() => step++);
    } else {
      // Check auth before going to packages — matches website flow
      final prefs = await SharedPreferences.getInstance();
      final token = prefs.getString('auth_token');
      if (!mounted) return;
      if (token == null || token.isEmpty) {
        final prefs = await SharedPreferences.getInstance();
        await prefs.setString('signup_intent', 'business'); // ADD THIS
        Navigator.pushNamed(context, '/signup');
      } else {
        Navigator.pushNamed(context, '/packages');
      }
    }
  }

  void _prevStep() {
    if (step > 0) {
      setState(() => step--);
    } else {
      Navigator.pop(context);
    }
  }

  bool _canContinue(WizardState wizard) {
    switch (step) {
      case 0:
        return wizard.businessType.isNotEmpty;
      case 1:
        return nameController.text.trim().isNotEmpty;
      case 2:
        return wizard.restaurantType.isNotEmpty;
      case 3:
        return (wizard.indoorTables + wizard.outdoorTables) > 0;
      case 4:
        return (int.tryParse(areaController.text.trim()) ?? 0) > 0;
      case 5:
        return wizard.budgetRange.isNotEmpty;
      case 6:
        return true;
      case 7:
        return true;
      case 8:
        return true;
      default:
        return true;
    }
  }

  @override
  Widget build(BuildContext context) {
    final wizard = context.watch<WizardState>();

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        foregroundColor: text,
        title: const Text(
          'My Setup',
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
        leading: IconButton(
          onPressed: _prevStep,
          icon: const Icon(Icons.arrow_back_rounded),
        ),
      ),
      body: SafeArea(
        child: Padding(
          padding: const EdgeInsets.fromLTRB(16, 4, 16, 16),
          child: Column(
            children: [
              ClipRRect(
                borderRadius: BorderRadius.circular(999),
                child: LinearProgressIndicator(
                  minHeight: 8,
                  value: (step + 1) / 9,
                  backgroundColor: Colors.grey.shade300,
                  valueColor: const AlwaysStoppedAnimation(sfBlue),
                ),
              ),
              const SizedBox(height: 12),
              Align(
                alignment: Alignment.centerLeft,
                child: Text(
                  'Step ${step + 1} of 9',
                  style: const TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: muted,
                  ),
                ),
              ),
              const SizedBox(height: 16),
              Expanded(
                child: SingleChildScrollView(child: _buildStepContent(wizard)),
              ),
              const SizedBox(height: 14),
              Row(
                children: [
                  if (step > 0)
                    Expanded(
                      child: OutlinedButton(
                        onPressed: _prevStep,
                        style: OutlinedButton.styleFrom(
                          minimumSize: const Size.fromHeight(54),
                          shape: RoundedRectangleBorder(
                            borderRadius: BorderRadius.circular(18),
                          ),
                        ),
                        child: const Text(
                          'Back',
                          style: TextStyle(fontWeight: FontWeight.w800),
                        ),
                      ),
                    ),
                  if (step > 0) const SizedBox(width: 12),
                  Expanded(
                    child: ElevatedButton(
                      onPressed: _canContinue(wizard)
                          ? () => _nextStep(wizard)
                          : null,
                      style: ElevatedButton.styleFrom(
                        backgroundColor: sfBlue,
                        minimumSize: const Size.fromHeight(56),
                        shape: RoundedRectangleBorder(
                          borderRadius: BorderRadius.circular(18),
                        ),
                      ),
                      child: Text(
                        step == 8 ? 'Generate Packages' : 'Next',
                        style: const TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w900,
                          fontSize: 16,
                        ),
                      ),
                    ),
                  ),
                ],
              ),
            ],
          ),
        ),
      ),
    );
  }

  Widget _buildStepContent(WizardState wizard) {
    switch (step) {
      case 0:
        return _sectionCard(
          title: 'What business are you opening?',
          subtitle: 'Choose the business type that best matches your setup.',
          child: GridView.builder(
            shrinkWrap: true,
            physics: const NeverScrollableScrollPhysics(),
            itemCount: businessOptions.length,
            gridDelegate: const SliverGridDelegateWithFixedCrossAxisCount(
              crossAxisCount: 2,
              mainAxisSpacing: 12,
              crossAxisSpacing: 12,
              childAspectRatio: 0.92,
            ),
            itemBuilder: (context, index) {
              final option = businessOptions[index];
              final selected = wizard.businessType == option.title;
              final controller = _videoControllers[option.title]!;

              return AnimatedContainer(
                duration: const Duration(milliseconds: 220),
                decoration: BoxDecoration(
                  borderRadius: BorderRadius.circular(22),
                  border: Border.all(
                    color: selected ? sfBlue : border,
                    width: selected ? 1.6 : 1,
                  ),
                  boxShadow: [
                    BoxShadow(
                      color: Colors.black.withValues(
                        alpha: selected ? 0.07 : 0.03,
                      ),
                      blurRadius: selected ? 16 : 10,
                      offset: const Offset(0, 8),
                    ),
                  ],
                ),
                child: InkWell(
                  borderRadius: BorderRadius.circular(22),
                  onTap: () => wizard.setBusinessType(option.title),
                  child: ClipRRect(
                    borderRadius: BorderRadius.circular(22),
                    child: Stack(
                      fit: StackFit.expand,
                      children: [
                        if (controller.value.isInitialized)
                          FittedBox(
                            fit: BoxFit.cover,
                            child: SizedBox(
                              width: controller.value.size.width,
                              height: controller.value.size.height,
                              child: VideoPlayer(controller),
                            ),
                          )
                        else
                          Container(color: Colors.grey.shade200),
                        Container(
                          decoration: BoxDecoration(
                            gradient: LinearGradient(
                              begin: Alignment.bottomCenter,
                              end: Alignment.topCenter,
                              colors: [
                                Colors.black.withValues(alpha: 0.58),
                                Colors.black.withValues(alpha: 0.08),
                              ],
                            ),
                          ),
                        ),
                        if (selected)
                          Positioned(
                            top: 12,
                            right: 12,
                            child: Container(
                              width: 34,
                              height: 34,
                              decoration: const BoxDecoration(
                                color: Colors.white,
                                shape: BoxShape.circle,
                              ),
                              child: const Icon(
                                Icons.check_rounded,
                                color: sfBlue,
                                size: 20,
                              ),
                            ),
                          ),
                        Positioned(
                          left: 14,
                          right: 14,
                          bottom: 14,
                          child: Column(
                            crossAxisAlignment: CrossAxisAlignment.start,
                            children: [
                              Icon(option.icon, color: Colors.white, size: 22),
                              const SizedBox(height: 8),
                              Text(
                                option.title,
                                style: const TextStyle(
                                  color: Colors.white,
                                  fontSize: 17,
                                  fontWeight: FontWeight.w900,
                                ),
                              ),
                            ],
                          ),
                        ),
                      ],
                    ),
                  ),
                ),
              );
            },
          ),
        );

      case 1:
        return _sectionCard(
          title: 'What is your business name?',
          subtitle: 'This helps personalize your setup journey.',
          child: TextField(
            controller: nameController,
            onChanged: (_) => setState(() {}),
            decoration: InputDecoration(
              hintText: 'Enter business name',
              filled: true,
              fillColor: const Color(0xFFF8FAFF),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(18),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(18),
                borderSide: const BorderSide(color: border),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(18),
                borderSide: const BorderSide(color: sfBlue, width: 1.4),
              ),
            ),
          ),
        );

      case 2:
        final restaurantOptions = [
          _RestaurantOption(
            value: 'fast_food',
            label: 'Fast Food',
            icon: Icons.fastfood_rounded,
            description: 'Quick service, high turnover',
          ),
          _RestaurantOption(
            value: 'standard_dining',
            label: 'Standard Dining',
            icon: Icons.restaurant_rounded,
            description: 'Full-service sit-down restaurant',
          ),
          _RestaurantOption(
            value: 'premium_dining',
            label: 'Premium Dining',
            icon: Icons.star_border_rounded,
            description: 'Fine dining, upscale experience',
          ),
          _RestaurantOption(
            value: 'cloud_kitchen',
            label: 'Cloud Kitchen',
            icon: Icons.cloud_rounded,
            description: 'Delivery-only, no dine-in',
          ),
        ];

        return _sectionCard(
          title: 'What type of restaurant?',
          subtitle:
              'Select the dining style that best describes your business.',
          child: Column(
            children: restaurantOptions.map((option) {
              final selected = wizard.restaurantType == option.value;

              return GestureDetector(
                onTap: () => wizard.setRestaurantType(option.value),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 220),
                  margin: const EdgeInsets.only(bottom: 12),
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(
                      color: selected ? sfBlue : border,
                      width: selected ? 1.6 : 1,
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(
                          alpha: selected ? 0.06 : 0.03,
                        ),
                        blurRadius: selected ? 14 : 8,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 48,
                        height: 48,
                        decoration: BoxDecoration(
                          color: selected
                              ? sfBlue.withValues(alpha: 0.1)
                              : Colors.grey.shade100,
                          borderRadius: BorderRadius.circular(14),
                        ),
                        child: Icon(
                          option.icon,
                          color: selected ? sfBlue : muted,
                          size: 24,
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Column(
                          crossAxisAlignment: CrossAxisAlignment.start,
                          children: [
                            Text(
                              option.label,
                              style: TextStyle(
                                fontSize: 15,
                                fontWeight: FontWeight.w800,
                                color: selected ? sfBlue : text,
                              ),
                            ),
                            const SizedBox(height: 3),
                            Text(
                              option.description,
                              style: const TextStyle(
                                fontSize: 13,
                                color: muted,
                                fontWeight: FontWeight.w500,
                              ),
                            ),
                          ],
                        ),
                      ),
                      if (selected)
                        const Icon(
                          Icons.check_circle_rounded,
                          color: sfBlue,
                          size: 22,
                        ),
                    ],
                  ),
                ),
              );
            }).toList(),
          ),
        );

      case 3:
        return _sectionCard(
          title: 'How many tables?',
          subtitle: 'Set the number of indoor and outdoor tables.',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _tableStepperRow(
                label: 'Indoor Tables',
                value: wizard.indoorTables,
                onDecrement: wizard.indoorTables > 0
                    ? () => wizard.setIndoorTables(wizard.indoorTables - 1)
                    : null,
                onIncrement: () =>
                    wizard.setIndoorTables(wizard.indoorTables + 1),
              ),
              const SizedBox(height: 16),
              _tableStepperRow(
                label: 'Outdoor Tables',
                value: wizard.outdoorTables,
                onDecrement: wizard.outdoorTables > 0
                    ? () => wizard.setOutdoorTables(wizard.outdoorTables - 1)
                    : null,
                onIncrement: () =>
                    wizard.setOutdoorTables(wizard.outdoorTables + 1),
              ),
            ],
          ),
        );
      case 4:
        return _sectionCard(
          title: 'What is your total area?',
          subtitle:
              'Enter the total floor area of your venue in square meters.',
          child: TextField(
            controller: areaController,
            keyboardType: TextInputType.number,
            onChanged: (_) => setState(() {}),
            decoration: InputDecoration(
              hintText: 'Enter area',
              suffixText: 'sqm',
              filled: true,
              fillColor: const Color(0xFFF8FAFF),
              border: OutlineInputBorder(
                borderRadius: BorderRadius.circular(18),
              ),
              enabledBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(18),
                borderSide: const BorderSide(color: border),
              ),
              focusedBorder: OutlineInputBorder(
                borderRadius: BorderRadius.circular(18),
                borderSide: const BorderSide(color: sfBlue, width: 1.4),
              ),
            ),
          ),
        );

      case 5:
        final budgetOptions = ['Under 500k', '500k-1.5M', '1.5M-3M', '3M+'];

        return _sectionCard(
          title: 'What is your budget range?',
          subtitle: 'This helps generate realistic recommendations.',
          child: Column(
            children: budgetOptions.map((range) {
              final selected = wizard.budgetRange == range;

              return GestureDetector(
                onTap: () => wizard.setBudgetRange(range),
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 220),
                  margin: const EdgeInsets.only(bottom: 12),
                  padding: const EdgeInsets.symmetric(
                    horizontal: 18,
                    vertical: 18,
                  ),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(
                      color: selected ? sfBlue : border,
                      width: selected ? 1.6 : 1,
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(
                          alpha: selected ? 0.06 : 0.03,
                        ),
                        blurRadius: selected ? 14 : 8,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Row(
                    children: [
                      Expanded(
                        child: Text(
                          'EGP $range',
                          style: TextStyle(
                            fontSize: 16,
                            fontWeight: FontWeight.w800,
                            color: selected ? sfBlue : text,
                          ),
                        ),
                      ),
                      if (selected)
                        const Icon(
                          Icons.check_circle_rounded,
                          color: sfBlue,
                          size: 22,
                        ),
                    ],
                  ),
                ),
              );
            }).toList(),
          ),
        );

      case 6:
        final serviceOptions = [
          _ServiceOption(
            value: 'pos',
            label: 'POS',
            icon: Icons.point_of_sale_rounded,
          ),
          _ServiceOption(
            value: 'electrical',
            label: 'Electrical',
            icon: Icons.electrical_services_rounded,
          ),
          _ServiceOption(
            value: 'network',
            label: 'Network',
            icon: Icons.wifi_rounded,
          ),
          _ServiceOption(value: 'ac', label: 'AC', icon: Icons.ac_unit_rounded),
          _ServiceOption(
            value: 'kitchen',
            label: 'Kitchen Setup',
            icon: Icons.kitchen_rounded,
          ),
        ];

        return _sectionCard(
          title: 'Installation services',
          subtitle: 'Select any services you need installed.',
          child: Column(
            children: serviceOptions.map((option) {
              final selected = wizard.installationServices.contains(
                option.value,
              );

              return GestureDetector(
                onTap: () {
                  final updated = List<String>.from(
                    wizard.installationServices,
                  );
                  if (selected) {
                    updated.remove(option.value);
                  } else {
                    updated.add(option.value);
                  }
                  wizard.setInstallationServices(updated);
                },
                child: AnimatedContainer(
                  duration: const Duration(milliseconds: 220),
                  margin: const EdgeInsets.only(bottom: 12),
                  padding: const EdgeInsets.all(16),
                  decoration: BoxDecoration(
                    color: Colors.white,
                    borderRadius: BorderRadius.circular(18),
                    border: Border.all(
                      color: selected ? sfBlue : border,
                      width: selected ? 1.6 : 1,
                    ),
                    boxShadow: [
                      BoxShadow(
                        color: Colors.black.withValues(
                          alpha: selected ? 0.06 : 0.03,
                        ),
                        blurRadius: selected ? 14 : 8,
                        offset: const Offset(0, 4),
                      ),
                    ],
                  ),
                  child: Row(
                    children: [
                      Container(
                        width: 44,
                        height: 44,
                        decoration: BoxDecoration(
                          color: selected
                              ? sfBlue.withValues(alpha: 0.1)
                              : Colors.grey.shade100,
                          borderRadius: BorderRadius.circular(12),
                        ),
                        child: Icon(
                          option.icon,
                          color: selected ? sfBlue : muted,
                          size: 22,
                        ),
                      ),
                      const SizedBox(width: 14),
                      Expanded(
                        child: Text(
                          option.label,
                          style: TextStyle(
                            fontSize: 15,
                            fontWeight: FontWeight.w800,
                            color: selected ? sfBlue : text,
                          ),
                        ),
                      ),
                      AnimatedContainer(
                        duration: const Duration(milliseconds: 180),
                        width: 24,
                        height: 24,
                        decoration: BoxDecoration(
                          color: selected ? sfBlue : Colors.transparent,
                          shape: BoxShape.circle,
                          border: Border.all(
                            color: selected ? sfBlue : border,
                            width: 1.5,
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
        const roles = [
          'waiter',
          'chef',
          'cashier',
          'security',
          'barista',
          'busboy',
          'host',
          'kitchen_helper',
        ];

        return _sectionCard(
          title: 'How many staff?',
          subtitle: 'Set the number of staff for each role.',
          child: Column(
            children: roles.map((role) {
              final displayName = role
                  .replaceAll('_', ' ')
                  .split(' ')
                  .map((w) => w[0].toUpperCase() + w.substring(1))
                  .join(' ');
              final count = wizard.staffCounts[role] ?? 0;

              return Padding(
                padding: const EdgeInsets.only(bottom: 12),
                child: Row(
                  children: [
                    Expanded(
                      child: Text(
                        displayName,
                        style: const TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w700,
                          color: text,
                        ),
                      ),
                    ),
                    _stepperControl(
                      value: count,
                      onDecrement: count > 0
                          ? () {
                              final updated = Map<String, int>.from(
                                wizard.staffCounts,
                              );
                              updated[role] = count - 1;
                              wizard.setStaffCounts(updated);
                            }
                          : null,
                      onIncrement: () {
                        final updated = Map<String, int>.from(
                          wizard.staffCounts,
                        );
                        updated[role] = count + 1;
                        wizard.setStaffCounts(updated);
                      },
                    ),
                  ],
                ),
              );
            }).toList(),
          ),
        );

      case 8:
        final services = wizard.installationServices.isEmpty
            ? ''
            : wizard.installationServices.join(', ');
        final staffEntries = wizard.staffCounts.entries
            .where((e) => e.value > 0)
            .map(
              (e) =>
                  '${e.key.replaceAll('_', ' ').split(' ').map((w) => w[0].toUpperCase() + w.substring(1)).join(' ')}: ${e.value}',
            )
            .join(', ');
        final restaurantLabel = wizard.restaurantType.isEmpty
            ? ''
            : wizard.restaurantType
                  .replaceAll('_', ' ')
                  .split(' ')
                  .map((w) => w[0].toUpperCase() + w.substring(1))
                  .join(' ');

        return _sectionCard(
          title: 'Review your setup',
          subtitle:
              'Make sure everything looks right before generating packages.',
          child: Column(
            crossAxisAlignment: CrossAxisAlignment.start,
            children: [
              _reviewRow('Business Type', wizard.businessType),
              _reviewRow('Business Name', wizard.businessName),
              _reviewRow('Restaurant Type', restaurantLabel),
              _reviewRow('Indoor Tables', '${wizard.indoorTables}'),
              _reviewRow('Outdoor Tables', '${wizard.outdoorTables}'),
              _reviewRow('Table Size', '${wizard.tableSize}-seater'),
              _reviewRow('Area', '${wizard.areaSqm} sqm'),
              _reviewRow('Budget', wizard.budgetRange),
              _reviewRow('Services', services),
              _reviewRow('Staff', staffEntries),
            ],
          ),
        );

      default:
        return const SizedBox.shrink();
    }
  }

  Widget _tableStepperRow({
    required String label,
    required int value,
    required VoidCallback? onDecrement,
    required VoidCallback onIncrement,
  }) {
    return Row(
      children: [
        Expanded(
          child: Text(
            label,
            style: const TextStyle(
              fontSize: 15,
              fontWeight: FontWeight.w700,
              color: text,
            ),
          ),
        ),
        _stepperControl(
          value: value,
          onDecrement: onDecrement,
          onIncrement: onIncrement,
        ),
      ],
    );
  }

  Widget _stepperControl({
    required int value,
    required VoidCallback? onDecrement,
    required VoidCallback onIncrement,
  }) {
    return Row(
      children: [
        _stepperButton(icon: Icons.remove_rounded, onTap: onDecrement),
        SizedBox(
          width: 36,
          child: Text(
            '$value',
            textAlign: TextAlign.center,
            style: const TextStyle(
              fontWeight: FontWeight.w800,
              fontSize: 16,
              color: text,
            ),
          ),
        ),
        _stepperButton(icon: Icons.add_rounded, onTap: onIncrement),
      ],
    );
  }

  Widget _stepperButton({
    required IconData icon,
    required VoidCallback? onTap,
  }) {
    return GestureDetector(
      onTap: onTap,
      child: AnimatedContainer(
        duration: const Duration(milliseconds: 150),
        width: 36,
        height: 36,
        decoration: BoxDecoration(
          color: onTap != null ? sfBlue : Colors.grey.shade200,
          borderRadius: BorderRadius.circular(10),
        ),
        child: Icon(
          icon,
          size: 18,
          color: onTap != null ? Colors.white : Colors.grey.shade400,
        ),
      ),
    );
  }

  Widget _sectionCard({
    required String title,
    required String subtitle,
    required Widget child,
  }) {
    return Container(
      width: double.infinity,
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: Colors.white,
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withValues(alpha: 0.04),
            blurRadius: 16,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            title,
            style: const TextStyle(
              fontSize: 20,
              fontWeight: FontWeight.w900,
              color: text,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            subtitle,
            style: const TextStyle(
              fontSize: 13.5,
              height: 1.45,
              color: muted,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 20),
          child,
        ],
      ),
    );
  }

  Widget _reviewRow(String label, String value) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 14),
      child: Row(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          SizedBox(
            width: 115,
            child: Text(
              label,
              style: const TextStyle(fontWeight: FontWeight.w800, color: text),
            ),
          ),
          const SizedBox(width: 10),
          Expanded(
            child: Text(
              value.isEmpty ? '-' : value,
              style: const TextStyle(
                color: muted,
                height: 1.4,
                fontWeight: FontWeight.w500,
              ),
            ),
          ),
        ],
      ),
    );
  }
}

class _BusinessOption {
  final String title;
  final String videoPath;
  final IconData icon;

  const _BusinessOption({
    required this.title,
    required this.videoPath,
    required this.icon,
  });
}

class _RestaurantOption {
  final String value;
  final String label;
  final IconData icon;
  final String description;

  const _RestaurantOption({
    required this.value,
    required this.label,
    required this.icon,
    required this.description,
  });
}

class _ServiceOption {
  final String value;
  final String label;
  final IconData icon;

  const _ServiceOption({
    required this.value,
    required this.label,
    required this.icon,
  });
}
