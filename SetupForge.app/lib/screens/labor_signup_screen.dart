import 'package:flutter/material.dart';
import '../services/api_service.dart';

class LaborSignupScreen extends StatefulWidget {
  const LaborSignupScreen({super.key});

  @override
  State<LaborSignupScreen> createState() => _LaborSignupScreenState();
}

class _LaborSignupScreenState extends State<LaborSignupScreen> {
  static const Color sfBlue = Color(0xFF004CAC);
  static const Color sfBg = Color(0xFFF5F7FB);
  static const Color sfText = Color(0xFF121212);
  static const Color sfMuted = Color(0xFF6C757D);
  static const Color sfBorder = Color(0x22000000);

  final api = ApiService();

  final nameC = TextEditingController();
  final emailC = TextEditingController();
  final passC = TextEditingController();
  final phoneC = TextEditingController();
  final countryC = TextEditingController(text: 'Egypt');
  final cityC = TextEditingController();
  final streetC = TextEditingController();
  final nationalIdC = TextEditingController();
  final skillsC = TextEditingController();
  final laborRoleC = TextEditingController();
  final hourlyRateC = TextEditingController();

  String _experienceLevel = 'junior';
  String _providerType = 'waiter';
  String _militaryStatus = 'n/a';
  bool _obscurePass = true;
  bool _loading = false;

  final _experienceLevels = ['junior', 'mid', 'senior'];
  final _providerTypes = [
    'chef',
    'waiter',
    'barista',
    'cashier',
    'cleaner',
    'other',
  ];
  final _militaryOptions = ['completed', 'exempt', 'pending', 'n/a'];

  Future<void> _signup() async {
    FocusScope.of(context).unfocus();
    if (nameC.text.trim().isEmpty ||
        emailC.text.trim().isEmpty ||
        passC.text.isEmpty) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(content: Text('Please fill in all required fields')),
      );
      return;
    }

    setState(() => _loading = true);

    try {
      final res = await api.laborSignup(
        name: nameC.text.trim(),
        email: emailC.text.trim(),
        password: passC.text,
        phone: phoneC.text.trim(),
        country: countryC.text.trim(),
        city: cityC.text.trim(),
        street: streetC.text.trim(),
        nationalId: nationalIdC.text.trim(),
        skills: skillsC.text.trim(),
        laborRole: laborRoleC.text.trim(),
        hourlyRate: double.tryParse(hourlyRateC.text) ?? 0,
        experienceLevel: _experienceLevel,
        providerType: _providerType,
        militaryStatus: _militaryStatus,
      );

      if (!mounted) return;

      if (res["ok"] == true) {
        Navigator.pushNamedAndRemoveUntil(
          context,
          '/labor-shell',
          (route) => false,
        );
      } else {
        ScaffoldMessenger.of(context).showSnackBar(
          SnackBar(content: Text(res["error"]?.toString() ?? "Signup failed")),
        );
      }
    } catch (e) {
      if (!mounted) return;
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(SnackBar(content: Text("Error: $e")));
    } finally {
      if (mounted) setState(() => _loading = false);
    }
  }

  @override
  void dispose() {
    nameC.dispose();
    emailC.dispose();
    passC.dispose();
    phoneC.dispose();
    countryC.dispose();
    cityC.dispose();
    streetC.dispose();
    nationalIdC.dispose();
    skillsC.dispose();
    laborRoleC.dispose();
    hourlyRateC.dispose();
    super.dispose();
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: sfBg,
      appBar: AppBar(
        backgroundColor: sfBlue,
        foregroundColor: Colors.white,
        title: const Text(
          'Join as Labor Worker',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        elevation: 0,
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(20),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _sectionLabel('Personal Information'),
            _field(
              controller: nameC,
              hint: 'Full Name',
              icon: Icons.person_outline,
              required: true,
            ),
            const SizedBox(height: 12),
            _field(
              controller: emailC,
              hint: 'Email Address',
              icon: Icons.email_outlined,
              keyboardType: TextInputType.emailAddress,
              required: true,
            ),
            const SizedBox(height: 12),
            _field(
              controller: passC,
              hint: 'Password (min 8 characters)',
              icon: Icons.lock_outline,
              obscureText: _obscurePass,
              required: true,
              suffix: IconButton(
                onPressed: () => setState(() => _obscurePass = !_obscurePass),
                icon: Icon(
                  _obscurePass ? Icons.visibility_off : Icons.visibility,
                  color: sfMuted,
                  size: 20,
                ),
              ),
            ),
            const SizedBox(height: 12),
            _field(
              controller: phoneC,
              hint: 'Phone Number',
              icon: Icons.phone_outlined,
              keyboardType: TextInputType.phone,
            ),
            const SizedBox(height: 12),
            _field(
              controller: nationalIdC,
              hint: 'National ID',
              icon: Icons.credit_card_outlined,
              keyboardType: TextInputType.number,
            ),

            const SizedBox(height: 20),
            _sectionLabel('Location'),
            _field(controller: countryC, hint: 'Country', icon: Icons.public),
            const SizedBox(height: 12),
            _field(
              controller: cityC,
              hint: 'City',
              icon: Icons.location_city_outlined,
            ),
            const SizedBox(height: 12),
            _field(
              controller: streetC,
              hint: 'Street',
              icon: Icons.signpost_outlined,
            ),

            const SizedBox(height: 20),
            _sectionLabel('Work Profile'),
            _dropdownField(
              label: 'Job Role',
              value: _providerType,
              items: _providerTypes,
              labels: const {
                'chef': 'Chef',
                'waiter': 'Waiter / Waitress',
                'barista': 'Barista',
                'cashier': 'Cashier',
                'cleaner': 'Cleaner',
                'other': 'Other',
              },
              onChanged: (v) => setState(() => _providerType = v!),
            ),
            const SizedBox(height: 12),
            _dropdownField(
              label: 'Experience Level',
              value: _experienceLevel,
              items: _experienceLevels,
              labels: const {
                'junior': 'Junior (0–2 yrs)',
                'mid': 'Mid (2–5 yrs)',
                'senior': 'Senior (5+ yrs)',
              },
              onChanged: (v) => setState(() => _experienceLevel = v!),
            ),
            const SizedBox(height: 12),
            _field(
              controller: laborRoleC,
              hint: 'Specific Role / Title (e.g. Head Chef)',
              icon: Icons.badge_outlined,
            ),
            const SizedBox(height: 12),
            _field(
              controller: hourlyRateC,
              hint: 'Hourly Rate (EGP)',
              icon: Icons.payments_outlined,
              keyboardType: TextInputType.number,
            ),
            const SizedBox(height: 12),

            _sectionLabel('Military Status'),
            Wrap(
              spacing: 8,
              children: _militaryOptions.map((opt) {
                final selected = _militaryStatus == opt;
                return ChoiceChip(
                  label: Text(
                    opt == 'n/a'
                        ? 'N/A'
                        : opt[0].toUpperCase() + opt.substring(1),
                  ),
                  selected: selected,
                  selectedColor: sfBlue,
                  labelStyle: TextStyle(
                    color: selected ? Colors.white : sfText,
                    fontWeight: FontWeight.w700,
                    fontSize: 13,
                  ),
                  onSelected: (_) => setState(() => _militaryStatus = opt),
                );
              }).toList(),
            ),

            const SizedBox(height: 12),
            _sectionLabel('Skills'),
            TextField(
              controller: skillsC,
              maxLines: 3,
              decoration: InputDecoration(
                hintText: 'e.g. Grill cooking, knife skills, latte art...',
                hintStyle: const TextStyle(color: sfMuted, fontSize: 13.5),
                filled: true,
                fillColor: const Color(0xFFF8FAFF),
                border: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: const BorderSide(color: sfBorder),
                ),
                enabledBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: const BorderSide(color: sfBorder),
                ),
                focusedBorder: OutlineInputBorder(
                  borderRadius: BorderRadius.circular(14),
                  borderSide: const BorderSide(color: sfBlue, width: 1.4),
                ),
              ),
            ),

            const SizedBox(height: 28),

            SizedBox(
              width: double.infinity,
              height: 54,
              child: ElevatedButton(
                onPressed: _loading ? null : _signup,
                style: ElevatedButton.styleFrom(
                  backgroundColor: sfBlue,
                  disabledBackgroundColor: sfBlue.withOpacity(0.55),
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(16),
                  ),
                ),
                child: _loading
                    ? const SizedBox(
                        width: 22,
                        height: 22,
                        child: CircularProgressIndicator(
                          strokeWidth: 2.4,
                          color: Colors.white,
                        ),
                      )
                    : const Text(
                        'Create Account',
                        style: TextStyle(
                          fontSize: 15,
                          fontWeight: FontWeight.w800,
                          color: Colors.white,
                        ),
                      ),
              ),
            ),

            const SizedBox(height: 16),

            Center(
              child: TextButton(
                onPressed: () =>
                    Navigator.pushReplacementNamed(context, '/login'),
                child: const Text(
                  'Already have an account? Sign In',
                  style: TextStyle(color: sfBlue, fontWeight: FontWeight.w700),
                ),
              ),
            ),
            const SizedBox(height: 20),
          ],
        ),
      ),
    );
  }

  Widget _sectionLabel(String text) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Text(
        text,
        style: const TextStyle(
          fontSize: 13,
          fontWeight: FontWeight.w800,
          color: sfBlue,
          letterSpacing: 0.4,
        ),
      ),
    );
  }

  Widget _field({
    required TextEditingController controller,
    required String hint,
    required IconData icon,
    TextInputType? keyboardType,
    bool obscureText = false,
    bool required = false,
    Widget? suffix,
  }) {
    return SizedBox(
      height: 56,
      child: TextField(
        controller: controller,
        keyboardType: keyboardType,
        obscureText: obscureText,
        decoration: InputDecoration(
          hintText: required ? '$hint *' : hint,
          hintStyle: const TextStyle(color: sfMuted, fontSize: 13.5),
          filled: true,
          fillColor: const Color(0xFFF8FAFF),
          prefixIcon: Icon(icon, color: sfMuted, size: 20),
          suffixIcon: suffix,
          border: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: sfBorder),
          ),
          enabledBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: sfBorder),
          ),
          focusedBorder: OutlineInputBorder(
            borderRadius: BorderRadius.circular(14),
            borderSide: const BorderSide(color: sfBlue, width: 1.4),
          ),
        ),
      ),
    );
  }

  Widget _dropdownField({
    required String label,
    required String value,
    required List<String> items,
    required Map<String, String> labels,
    required void Function(String?) onChanged,
  }) {
    return Container(
      height: 56,
      padding: const EdgeInsets.symmetric(horizontal: 14),
      decoration: BoxDecoration(
        color: const Color(0xFFF8FAFF),
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: sfBorder),
      ),
      child: DropdownButtonHideUnderline(
        child: DropdownButton<String>(
          value: value,
          isExpanded: true,
          style: const TextStyle(
            color: sfText,
            fontSize: 14,
            fontWeight: FontWeight.w600,
          ),
          items: items
              .map(
                (e) => DropdownMenuItem(value: e, child: Text(labels[e] ?? e)),
              )
              .toList(),
          onChanged: onChanged,
        ),
      ),
    );
  }
}
