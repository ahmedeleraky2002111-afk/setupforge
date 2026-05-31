import 'package:flutter/material.dart';
import '../services/api_service.dart';

class LaborEditProfileScreen extends StatefulWidget {
  final Map<String, dynamic> data;
  const LaborEditProfileScreen({super.key, required this.data});

  @override
  State<LaborEditProfileScreen> createState() => _LaborEditProfileScreenState();
}

class _LaborEditProfileScreenState extends State<LaborEditProfileScreen> {
  static const Color sfBlue = Color(0xFF004CAC);
  static const Color sfBg = Color(0xFFF5F7FB);
  static const Color sfText = Color(0xFF111827);
  static const Color sfMuted = Color(0xFF6B7280);
  static const Color sfBorder = Color(0x22000000);

  final api = ApiService();
  bool _loading = false;

  late final TextEditingController nameC;
  late final TextEditingController phoneC;
  late final TextEditingController countryC;
  late final TextEditingController cityC;
  late final TextEditingController streetC;
  late final TextEditingController skillsC;
  late final TextEditingController laborRoleC;
  late final TextEditingController hourlyRateC;
  late String _availability;

  @override
  void initState() {
    super.initState();
    final d = widget.data;
    nameC = TextEditingController(text: d["name"] ?? "");
    phoneC = TextEditingController(text: d["phone"] ?? "");
    countryC = TextEditingController(text: d["country"] ?? "");
    cityC = TextEditingController(text: d["city"] ?? "");
    streetC = TextEditingController(text: d["street"] ?? "");
    skillsC = TextEditingController(text: d["skills"] ?? "");
    laborRoleC = TextEditingController(text: d["labor_role"] ?? "");
    hourlyRateC = TextEditingController(
      text: d["hourly_rate"]?.toString() ?? "0",
    );
    _availability = d["availability_status"] ?? "available";
  }

  @override
  void dispose() {
    nameC.dispose();
    phoneC.dispose();
    countryC.dispose();
    cityC.dispose();
    streetC.dispose();
    skillsC.dispose();
    laborRoleC.dispose();
    hourlyRateC.dispose();
    super.dispose();
  }

  Future<void> _save() async {
    if (nameC.text.trim().isEmpty) {
      ScaffoldMessenger.of(
        context,
      ).showSnackBar(const SnackBar(content: Text("Name is required")));
      return;
    }

    setState(() => _loading = true);

    final res = await api.updateLaborProfile(
      name: nameC.text.trim(),
      phone: phoneC.text.trim(),
      country: countryC.text.trim(),
      city: cityC.text.trim(),
      street: streetC.text.trim(),
      skills: skillsC.text.trim(),
      hourlyRate: double.tryParse(hourlyRateC.text) ?? 0,
      laborRole: laborRoleC.text.trim(),
      availabilityStatus: _availability,
    );

    if (!mounted) return;
    setState(() => _loading = false);

    if (res["ok"] == true) {
      ScaffoldMessenger.of(context).showSnackBar(
        const SnackBar(
          content: Text("Profile updated successfully"),
          backgroundColor: Color(0xFF16A34A),
        ),
      );
      Navigator.pop(context);
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res["error"]?.toString() ?? "Update failed")),
      );
    }
  }

  @override
  Widget build(BuildContext context) {
    return Scaffold(
      backgroundColor: sfBg,
      appBar: AppBar(
        backgroundColor: sfBlue,
        foregroundColor: Colors.white,
        title: const Text(
          'Edit Profile',
          style: TextStyle(fontWeight: FontWeight.w800),
        ),
        elevation: 0,
        actions: [
          TextButton(
            onPressed: _loading ? null : _save,
            child: _loading
                ? const SizedBox(
                    width: 18,
                    height: 18,
                    child: CircularProgressIndicator(
                      strokeWidth: 2,
                      color: Colors.white,
                    ),
                  )
                : const Text(
                    'Save',
                    style: TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w800,
                      fontSize: 15,
                    ),
                  ),
          ),
        ],
      ),
      body: SingleChildScrollView(
        padding: const EdgeInsets.all(16),
        child: Column(
          crossAxisAlignment: CrossAxisAlignment.start,
          children: [
            _section('Personal Information'),
            _field(nameC, 'Full Name *', Icons.person_outline),
            const SizedBox(height: 12),
            _field(
              phoneC,
              'Phone',
              Icons.phone_outlined,
              keyboardType: TextInputType.phone,
            ),
            const SizedBox(height: 12),
            _field(countryC, 'Country', Icons.public),
            const SizedBox(height: 12),
            _field(cityC, 'City', Icons.location_city_outlined),
            const SizedBox(height: 12),
            _field(streetC, 'Street', Icons.signpost_outlined),

            const SizedBox(height: 20),
            _section('Work Profile'),
            _field(
              laborRoleC,
              'Role / Title',
              Icons.badge_outlined,
              hint: 'e.g. Head Chef',
            ),
            const SizedBox(height: 12),
            _field(
              hourlyRateC,
              'Hourly Rate (EGP)',
              Icons.payments_outlined,
              keyboardType: TextInputType.number,
            ),
            const SizedBox(height: 12),

            // Availability
            Text(
              'Availability',
              style: const TextStyle(
                fontSize: 12.5,
                fontWeight: FontWeight.w800,
                color: sfText,
              ),
            ),
            const SizedBox(height: 8),
            Container(
              height: 52,
              padding: const EdgeInsets.symmetric(horizontal: 14),
              decoration: BoxDecoration(
                color: const Color(0xFFF8FAFF),
                borderRadius: BorderRadius.circular(14),
                border: Border.all(color: sfBorder),
              ),
              child: DropdownButtonHideUnderline(
                child: DropdownButton<String>(
                  value: _availability,
                  isExpanded: true,
                  style: const TextStyle(
                    color: sfText,
                    fontSize: 14,
                    fontWeight: FontWeight.w600,
                  ),
                  items: const [
                    DropdownMenuItem(
                      value: 'available',
                      child: Text('Available'),
                    ),
                    DropdownMenuItem(value: 'busy', child: Text('Busy')),
                    DropdownMenuItem(
                      value: 'unavailable',
                      child: Text('Unavailable'),
                    ),
                  ],
                  onChanged: (v) => setState(() => _availability = v!),
                ),
              ),
            ),

            const SizedBox(height: 12),
            _section('Skills'),
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
              height: 52,
              child: ElevatedButton(
                onPressed: _loading ? null : _save,
                style: ElevatedButton.styleFrom(
                  backgroundColor: sfBlue,
                  disabledBackgroundColor: sfBlue.withOpacity(0.55),
                  elevation: 0,
                  shape: RoundedRectangleBorder(
                    borderRadius: BorderRadius.circular(14),
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
                        'Save Changes',
                        style: TextStyle(
                          color: Colors.white,
                          fontWeight: FontWeight.w800,
                          fontSize: 15,
                        ),
                      ),
              ),
            ),
            const SizedBox(height: 32),
          ],
        ),
      ),
    );
  }

  Widget _section(String title) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 12),
      child: Text(
        title,
        style: const TextStyle(
          fontSize: 11.5,
          fontWeight: FontWeight.w800,
          color: sfBlue,
          letterSpacing: 0.5,
        ),
      ),
    );
  }

  Widget _field(
    TextEditingController controller,
    String label,
    IconData icon, {
    TextInputType? keyboardType,
    String? hint,
  }) {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Text(
          label,
          style: const TextStyle(
            fontSize: 12.5,
            fontWeight: FontWeight.w800,
            color: sfText,
          ),
        ),
        const SizedBox(height: 6),
        SizedBox(
          height: 52,
          child: TextField(
            controller: controller,
            keyboardType: keyboardType,
            decoration: InputDecoration(
              hintText: hint,
              hintStyle: const TextStyle(color: sfMuted, fontSize: 13.5),
              filled: true,
              fillColor: const Color(0xFFF8FAFF),
              prefixIcon: Icon(icon, color: sfMuted, size: 18),
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
        ),
      ],
    );
  }
}
