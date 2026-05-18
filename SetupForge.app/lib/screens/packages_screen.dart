import 'package:flutter/material.dart';
import 'package:provider/provider.dart';
import 'package:shared_preferences/shared_preferences.dart';

import '../services/api_service.dart';
import '../state/wizard_state.dart';
import 'login_screen.dart';
import 'order_summary_screen.dart';
import 'signup_screen.dart';

class SetupPackageArgs {
  final String businessType;
  final String businessName;
  final String size;
  final int budgetEGP;
  final List<String> selectedModules;
  final Map<String, String> moduleTiers;
  final String posAddOn;
  final Map<String, int> staffCounts;

  const SetupPackageArgs({
    required this.businessType,
    required this.businessName,
    required this.size,
    required this.budgetEGP,
    required this.selectedModules,
    required this.moduleTiers,
    required this.posAddOn,
    required this.staffCounts,
  });
}

class PackagesScreen extends StatefulWidget {
  final SetupPackageArgs? args;

  const PackagesScreen({super.key, this.args});

  @override
  State<PackagesScreen> createState() => _PackagesScreenState();
}

class _PackagesScreenState extends State<PackagesScreen> {
  static const Color sfBlue = Color(0xFF004CAC);
  static const Color sfTeal = Color(0xFF009994);
  static const Color bg = Color(0xFFF5F7FB);
  static const Color cardBg = Colors.white;
  static const Color muted = Color(0xFF6C757D);
  static const Color text = Color(0xFF121212);
  static const Color border = Color(0x22000000);
  int _budgetRangeToInt(String range) {
    switch (range) {
      case 'Under 500k':
        return 500000;
      case '500k-1.5M':
        return 1500000;
      case '1.5M-3M':
        return 3000000;
      case '3M+':
        return 5000000;
      default:
        return 3000000;
    }
  }

  late SetupPackageArgs data;

  final ApiService _api = ApiService();

  bool loading = true;
  String? loadError;

  bool _checkingAuth = true;
  bool _isLoggedIn = false;

  int budgetEGP = 0;
  int grandTotal = 0;
  int selectedSectionIndex = 0;

  final List<_PkgSection> sections = [];
  final Map<String, int> qty = {};
  final Map<String, int> backendAlloc = {};

  @override
  void initState() {
    super.initState();

    final wizard = context.read<WizardState>();

    data = SetupPackageArgs(
      businessType:
          (widget.args?.businessType ?? wizard.businessType).isNotEmpty
          ? (widget.args?.businessType ?? wizard.businessType)
          : "Restaurant",
      businessName: widget.args?.businessName ?? wizard.businessName,
      size: wizard.budgetRange.isNotEmpty ? wizard.budgetRange : "500k-1.5M",
      budgetEGP: _budgetRangeToInt(wizard.budgetRange),
      selectedModules: _normalizeSelectedModules(wizard.installationServices),
      moduleTiers: _normalizeModuleTiers({}),
      posAddOn: wizard.installationServices.contains('pos')
          ? "Printed Receipts"
          : "",
      staffCounts: widget.args?.staffCounts ?? wizard.staffCounts,
    );

    budgetEGP = data.budgetEGP;

    _checkAuthStatus();

    WidgetsBinding.instance.addPostFrameCallback((_) {
      _loadPackagesFromBackend();
    });
  }

  Future<void> _checkAuthStatus() async {
    final prefs = await SharedPreferences.getInstance();
    final token = prefs.getString('token');

    if (!mounted) return;

    setState(() {
      _isLoggedIn = token != null && token.isNotEmpty;
      _checkingAuth = false;
    });
  }

  List<String> _normalizeSelectedModules(List<String> modules) {
    final out = <String>[];
    for (final m in modules) {
      final v = m.trim().toLowerCase();
      if (v == 'kitchen') {
        out.add('kitchen');
      } else if (v == 'pos') {
        out.add('pos');
      } else if (v == 'ac') {
        out.add('ac');
      } else if (v == 'electrical' || v == 'network') {
        out.add('furniture');
      }
    }
    final unique = out.toSet();

    // kitchen and pos are always core modules
    unique.add('kitchen');
    unique.add('pos');
    unique.add('furniture');

    return unique.toList();
  }

  Map<String, String> _normalizeModuleTiers(Map<String, String> tiers) {
    if (tiers.isEmpty) {
      return {
        "kitchen": "Balanced",
        "furniture": "Balanced",
        "pos": "Balanced",
      };
    }

    final out = <String, String>{};

    tiers.forEach((key, value) {
      final k = key.trim().toLowerCase();

      if (k == "kitchen / equipment" || k == "kitchen") {
        out["kitchen"] = value;
      } else if (k == "furniture" ||
          k == "dining area" ||
          k == "dining_area" ||
          k == "dining" ||
          k == "ambience" ||
          k == "decor" ||
          k == "ac" ||
          k == "lighting" ||
          k == "ac & infrastructure" ||
          k == "branding & signage" ||
          k == "service & staff") {
        out["furniture"] = value;
      } else if (k == "pos & tech" ||
          k == "pos & operations" ||
          k == "pos" ||
          k == "operations") {
        out["pos"] = value;
      } else if (k == "electronics" ||
          k == "electronic devices" ||
          k == "tv" ||
          k == "screens" ||
          k == "display") {
        out["electronics"] = value;
      }
    });

    if (!out.containsKey("kitchen")) out["kitchen"] = "Balanced";
    if (!out.containsKey("furniture")) out["furniture"] = "Balanced";
    if (!out.containsKey("pos")) out["pos"] = "Balanced";

    return out;
  }

  Future<void> _loadPackagesFromBackend() async {
    final wizard = context.read<WizardState>(); // ADD THIS

    setState(() {
      loading = true;
      loadError = null;
      sections.clear();
      qty.clear();
      backendAlloc.clear();
      grandTotal = 0;
      selectedSectionIndex = 0;
    });

    try {
      final res = await _api.generatePackages(
        businessType: data.businessType,
        size: data.size,
        budget: data.budgetEGP,
        modules: data.selectedModules,
        moduleTiers: data.moduleTiers,
        restaurantType: wizard.restaurantType.isNotEmpty
            ? wizard.restaurantType
            : 'standard_dining',
        indoorTables: wizard.indoorTables,
        outdoorTables: wizard.outdoorTables,
        areaSqm: wizard.areaSqm,
        budgetRange: wizard.budgetRange,
      );

      if (!mounted) return;

      if (res["ok"] != true) {
        setState(() {
          loading = false;
          loadError = (res["error"] ?? "Failed to load packages").toString();
        });
        return;
      }

      final allocRaw = res["alloc"];
      if (allocRaw is Map) {
        allocRaw.forEach((key, value) {
          backendAlloc[key.toString()] = _toInt(value);
        });
      }

      final moduleCarts = res["module_carts"];
      final builtSections = <_PkgSection>[];

      if (moduleCarts is Map) {
        moduleCarts.forEach((moduleKey, cartValue) {
          final module = moduleKey.toString();
          final cart = cartValue is Map ? cartValue : {};
          final itemsMap = cart["items"];

          if (itemsMap is! Map || itemsMap.isEmpty) return;

          final sectionItems = <_PkgItem>[];

          itemsMap.forEach((typeKey, itemValue) {
            if (itemValue is! Map) return;

            final productId = _toInt(itemValue["product_id"]);
            final name = (itemValue["name"] ?? "Item").toString();
            final seller = (itemValue["vendor_name"] ?? "Vendor").toString();
            final price = _toInt(itemValue["unit"]);
            final defaultQty = _toInt(itemValue["qty"]);
            final imageUrl = (itemValue["image_url"] ?? "").toString();
            final brand = (itemValue["brand"] ?? "").toString();

            final keyId = "${module}_${typeKey}_$productId";
            qty[keyId] = defaultQty;

            sectionItems.add(
              _PkgItem(
                keyId: keyId,
                productId: productId,
                typeKey: typeKey.toString(),
                name: name,
                seller: seller,
                priceEGP: price,
                code: _makeCode(typeKey.toString(), name),
                defaultQty: defaultQty,
                moduleKey: module,
                imageUrl: imageUrl,
                brand: brand,
              ),
            );
          });

          if (sectionItems.isNotEmpty) {
            builtSections.add(
              _PkgSection(
                title: _moduleLabel(module),
                subtitle: _moduleSubtitle(module),
                badge: module.toUpperCase(),
                capEGP: backendAlloc[module] ?? 0,
                moduleKey: module,
                items: sectionItems,
              ),
            );
          }
        });
      }

      if (!mounted) return;

      setState(() {
        sections
          ..clear()
          ..addAll(builtSections);

        if (selectedSectionIndex >= sections.length) {
          selectedSectionIndex = 0;
        }

        grandTotal = _toInt(res["grand_total"]);
        loading = false;
        loadError = sections.isEmpty ? "No packages available." : null;
      });

      _syncCartWithWizard();
    } catch (e) {
      if (!mounted) return;
      setState(() {
        loading = false;
        loadError = "Error loading packages: $e";
      });
    }
  }

  void _syncCartWithWizard() {
    final wizard = context.read<WizardState>();
    final items = <Map<String, dynamic>>[];

    for (final section in sections) {
      for (final item in section.items) {
        final q = qty[item.keyId] ?? 0;
        if (q > 0) {
          items.add({
            "product_id": item.productId,
            "keyId": item.keyId,
            "module": section.title,
            "module_key": section.moduleKey,
            "name": item.name,
            "price": item.priceEGP,
            "qty": q,
            "seller": item.seller,
            "code": item.code,
            "image_url": item.imageUrl,
            "brand": item.brand,
            "type_key": item.typeKey,
          });
        }
      }
    }

    wizard.setCartItems(items);
  }

  int _toInt(dynamic value) {
    if (value == null) return 0;
    if (value is int) return value;
    if (value is num) return value.toInt();
    return int.tryParse(value.toString()) ?? 0;
  }

  String _moduleLabel(String module) {
    switch (module) {
      case "kitchen":
        return "Kitchen / Equipment";
      case "furniture":
        return "Dining Area";
      case "pos":
        return "POS & Operations";
      case "electronics":
        return "Electronic Devices";
      case "ac":
        return "Ambience & AC";
      default:
        return module;
    }
  }

  String _moduleSubtitle(String module) {
    switch (module) {
      case "kitchen":
        return "Generated equipment package based on your selected tier and budget.";
      case "furniture":
        return "Generated dining area package based on your selected tier and budget.";
      case "pos":
        return "Auto-generated based on your selected setup and budget cap.";
      case "electronics":
        return "Generated electronics package based on your selected tier and budget.";
      case "ac":
        return "Auto-calculated based on your venue area in sqm.";
      default:
        return "Generated package items.";
    }
  }

  String _makeCode(String typeKey, String name) {
    final t = typeKey.trim().toUpperCase();
    if (t.length >= 3) return t.substring(0, 3);

    final words = name.trim().split(RegExp(r'\s+'));
    if (words.length >= 2) {
      final a = words[0].isNotEmpty ? words[0][0] : '';
      final b = words[1].isNotEmpty ? words[1][0] : '';
      return (a + b).toUpperCase();
    }

    return t.isNotEmpty ? t : "IT";
  }

  int get totalEGP {
    int sum = 0;
    for (final section in sections) {
      for (final item in section.items) {
        final q = qty[item.keyId] ?? 0;
        sum += item.priceEGP * q;
      }
    }
    return sum;
  }

  int get remainingEGP => budgetEGP - totalEGP;

  _PkgSection? get activeSection {
    if (sections.isEmpty) return null;
    if (selectedSectionIndex < 0 || selectedSectionIndex >= sections.length) {
      return sections.first;
    }
    return sections[selectedSectionIndex];
  }

  int sectionTotal(_PkgSection section) {
    int sum = 0;
    for (final item in section.items) {
      sum += item.priceEGP * (qty[item.keyId] ?? 0);
    }
    return sum;
  }

  String egp(int n) {
    final s = n.abs().toString();
    final out = StringBuffer();
    for (int i = 0; i < s.length; i++) {
      final idxFromEnd = s.length - i;
      out.write(s[i]);
      if (idxFromEnd > 1 && idxFromEnd % 3 == 1) {
        out.write(',');
      }
    }
    return "${n < 0 ? "-" : ""}${out.toString()} EGP";
  }

  void inc(String keyId) {
    setState(() {
      qty[keyId] = (qty[keyId] ?? 0) + 1;
    });
    _syncCartWithWizard();
  }

  void dec(String keyId) {
    setState(() {
      final value = qty[keyId] ?? 0;
      if (value > 0) {
        qty[keyId] = value - 1;
      }
    });
    _syncCartWithWizard();
  }

  int _moduleTotalByKey(String key) {
    final found = sections.where((s) => s.moduleKey == key);
    if (found.isEmpty) return 0;
    return sectionTotal(found.first);
  }

  @override
  Widget build(BuildContext context) {
    if (loading) {
      return const Scaffold(
        backgroundColor: bg,
        body: SafeArea(child: Center(child: CircularProgressIndicator())),
      );
    }

    if (loadError != null) {
      return Scaffold(
        backgroundColor: bg,
        appBar: AppBar(
          backgroundColor: Colors.transparent,
          elevation: 0,
          foregroundColor: text,
          title: const Text("Recommended Packages"),
        ),
        body: Center(
          child: Padding(
            padding: const EdgeInsets.all(24),
            child: Column(
              mainAxisSize: MainAxisSize.min,
              children: [
                Text(
                  loadError!,
                  textAlign: TextAlign.center,
                  style: const TextStyle(
                    fontSize: 15,
                    fontWeight: FontWeight.w700,
                    color: text,
                  ),
                ),
                const SizedBox(height: 16),
                ElevatedButton(
                  onPressed: _loadPackagesFromBackend,
                  style: ElevatedButton.styleFrom(backgroundColor: sfBlue),
                  child: const Text(
                    "Try Again",
                    style: TextStyle(color: Colors.white),
                  ),
                ),
              ],
            ),
          ),
        ),
      );
    }

    if (sections.isEmpty) {
      return Scaffold(
        backgroundColor: bg,
        appBar: AppBar(
          backgroundColor: Colors.transparent,
          elevation: 0,
          foregroundColor: text,
          title: const Text("Recommended Packages"),
        ),
        body: const Center(
          child: Text(
            "No packages available.",
            style: TextStyle(fontSize: 16, fontWeight: FontWeight.w800),
          ),
        ),
      );
    }

    return Scaffold(
      backgroundColor: bg,
      appBar: AppBar(
        backgroundColor: Colors.transparent,
        elevation: 0,
        foregroundColor: text,
        title: const Text(
          "Recommended Setup",
          style: TextStyle(fontWeight: FontWeight.w900),
        ),
      ),
      body: ListView(
        padding: const EdgeInsets.fromLTRB(16, 8, 16, 120),
        children: [
          _buildSetupSummary(),
          const SizedBox(height: 16),
          ..._buildModuleSections(),
          const SizedBox(height: 16),
          _buildTotalSection(),
        ],
      ),
      bottomNavigationBar: _buildBottomCTA(),
    );
  }

  Widget _buildSetupSummary() {
    final displayName = data.businessName.trim().isEmpty
        ? "Your Business"
        : data.businessName.trim();

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        gradient: const LinearGradient(
          colors: [Color(0xFF004CAC), Color(0xFF009994)],
          begin: Alignment.topLeft,
          end: Alignment.bottomRight,
        ),
        borderRadius: BorderRadius.circular(24),
        boxShadow: [
          BoxShadow(
            color: sfBlue.withOpacity(0.12),
            blurRadius: 18,
            offset: const Offset(0, 10),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            "YOUR GENERATED SETUP",
            style: TextStyle(
              color: Colors.white70,
              fontSize: 11,
              letterSpacing: 1,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 8),
          Text(
            displayName,
            style: const TextStyle(
              color: Colors.white,
              fontSize: 25,
              height: 1.15,
              fontWeight: FontWeight.w900,
            ),
          ),
          const SizedBox(height: 14),
          Wrap(
            spacing: 8,
            runSpacing: 8,
            children: [
              _topChip(data.businessType),
              _topChip(data.size),
              _topChip("Budget: ${egp(budgetEGP)}", filled: true),
            ],
          ),
          const SizedBox(height: 16),
          const Text(
            "Your package recommendations are grouped by module so you can review them faster on mobile.",
            style: TextStyle(
              color: Colors.white,
              fontSize: 13,
              height: 1.45,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 14),
          OutlinedButton.icon(
            onPressed: () {
              Navigator.pushReplacementNamed(context, '/setup');
            },
            icon: const Icon(Icons.refresh_rounded, size: 18),
            label: const Text("Restart Setup"),
            style: OutlinedButton.styleFrom(
              foregroundColor: Colors.white,
              side: const BorderSide(color: Colors.white30),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(14),
              ),
            ),
          ),
        ],
      ),
    );
  }

  Widget _topChip(String label, {bool filled = false}) {
    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 10, vertical: 7),
      decoration: BoxDecoration(
        color: filled ? Colors.white : Colors.white.withOpacity(0.12),
        borderRadius: BorderRadius.circular(999),
        border: Border.all(color: filled ? Colors.white : Colors.white24),
      ),
      child: Text(
        label,
        style: TextStyle(
          fontSize: 11,
          fontWeight: FontWeight.w800,
          color: filled ? sfBlue : Colors.white,
        ),
      ),
    );
  }

  List<Widget> _buildModuleSections() {
    return sections.map((section) {
      final sectionTotalValue = sectionTotal(section);
      final cap = section.capEGP;
      final remaining = cap - sectionTotalValue;
      final over = remaining < 0;

      return Padding(
        padding: const EdgeInsets.only(bottom: 16),
        child: Container(
          decoration: BoxDecoration(
            color: cardBg,
            borderRadius: BorderRadius.circular(22),
            border: Border.all(color: border),
            boxShadow: [
              BoxShadow(
                color: Colors.black.withOpacity(0.04),
                blurRadius: 14,
                offset: const Offset(0, 8),
              ),
            ],
          ),
          child: Padding(
            padding: const EdgeInsets.fromLTRB(16, 16, 16, 16),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                Wrap(
                  spacing: 8,
                  runSpacing: 8,
                  crossAxisAlignment: WrapCrossAlignment.center,
                  children: [
                    Container(
                      padding: const EdgeInsets.symmetric(
                        horizontal: 10,
                        vertical: 6,
                      ),
                      decoration: BoxDecoration(
                        color: const Color(0xFF004CAC).withOpacity(0.10),
                        borderRadius: BorderRadius.circular(999),
                      ),
                      child: Text(
                        section.badge,
                        style: const TextStyle(
                          fontSize: 10.5,
                          fontWeight: FontWeight.w900,
                          color: sfBlue,
                        ),
                      ),
                    ),
                    Text(
                      section.title,
                      style: const TextStyle(
                        fontSize: 19,
                        height: 1.2,
                        fontWeight: FontWeight.w900,
                        color: text,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 8),
                Text(
                  section.subtitle,
                  style: const TextStyle(
                    fontSize: 13,
                    height: 1.4,
                    color: muted,
                    fontWeight: FontWeight.w500,
                  ),
                ),
                const SizedBox(height: 14),
                Row(
                  children: [
                    Expanded(
                      child: _valueBox("SECTION TOTAL", egp(sectionTotalValue)),
                    ),
                    const SizedBox(width: 10),
                    Expanded(
                      child: _valueBox(
                        over ? "OVER" : "REMAINING",
                        egp(over ? remaining.abs() : remaining),
                        accent: !over,
                        danger: over,
                      ),
                    ),
                  ],
                ),
                const SizedBox(height: 16),
                ...section.items.map((item) {
                  final q = qty[item.keyId] ?? 0;
                  final lineTotal = item.priceEGP * q;

                  return Padding(
                    padding: const EdgeInsets.only(bottom: 12),
                    child: _ProductCard(
                      code: item.code,
                      name: item.name,
                      seller: item.seller,
                      unitPrice: egp(item.priceEGP),
                      qty: q,
                      totalPrice: egp(lineTotal),
                      onMinus: () => dec(item.keyId),
                      onPlus: () => inc(item.keyId),
                      onReplace: () {
                        ScaffoldMessenger.of(context).showSnackBar(
                          SnackBar(
                            content: Text(
                              "Replace product for ${item.name} later.",
                            ),
                          ),
                        );
                      },
                    ),
                  );
                }),
              ],
            ),
          ),
        ),
      );
    }).toList();
  }

  Widget _valueBox(
    String label,
    String value, {
    bool accent = false,
    bool danger = false,
  }) {
    Color outline = border;
    Color bgColor = Colors.white;
    Color valueColor = text;

    if (accent) {
      outline = const Color(0xFF8DE0C5);
      bgColor = const Color(0xFFEAFBF5);
      valueColor = sfTeal;
    }

    if (danger) {
      outline = Colors.red.shade200;
      bgColor = Colors.red.shade50;
      valueColor = Colors.red.shade700;
    }

    return Container(
      padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 11),
      decoration: BoxDecoration(
        color: bgColor,
        borderRadius: BorderRadius.circular(14),
        border: Border.all(color: outline),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          Text(
            label,
            style: const TextStyle(
              fontSize: 10,
              fontWeight: FontWeight.w900,
              color: muted,
            ),
          ),
          const SizedBox(height: 4),
          Text(
            value,
            style: TextStyle(
              fontSize: 13,
              fontWeight: FontWeight.w900,
              color: valueColor,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildTotalSection() {
    final kitchenTotal = _moduleTotalByKey("kitchen");
    final posTotal = _moduleTotalByKey("pos");
    final furnitureTotal = _moduleTotalByKey("furniture");
    final electronicsTotal = _moduleTotalByKey("electronics");

    return Container(
      padding: const EdgeInsets.all(18),
      decoration: BoxDecoration(
        color: cardBg,
        borderRadius: BorderRadius.circular(22),
        border: Border.all(color: border),
        boxShadow: [
          BoxShadow(
            color: Colors.black.withOpacity(0.04),
            blurRadius: 14,
            offset: const Offset(0, 8),
          ),
        ],
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          const Text(
            "Order Summary",
            style: TextStyle(
              fontSize: 18,
              fontWeight: FontWeight.w900,
              color: text,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            "Live snapshot of your selected modules.",
            style: TextStyle(
              fontSize: 12.5,
              color: muted,
              fontWeight: FontWeight.w500,
            ),
          ),
          const SizedBox(height: 16),
          _sumRow("Business", data.businessType),
          _sumRow("Size", data.size),
          _sumRow("Budget", egp(budgetEGP)),
          if (kitchenTotal > 0) _sumRow("Kitchen Total", egp(kitchenTotal)),
          if (furnitureTotal > 0)
            _sumRow("Dining Area Total", egp(furnitureTotal)),
          if (posTotal > 0) _sumRow("POS Total", egp(posTotal)),
          if (electronicsTotal > 0)
            _sumRow("Electronics Total", egp(electronicsTotal)),
          const Divider(height: 24),
          _sumRow("Grand Total", egp(totalEGP), strong: true),
        ],
      ),
    );
  }

  Widget _sumRow(String label, String value, {bool strong = false}) {
    return Padding(
      padding: const EdgeInsets.only(bottom: 10),
      child: Row(
        children: [
          Expanded(
            child: Text(
              label,
              style: TextStyle(
                fontSize: strong ? 13.5 : 12.5,
                color: strong ? text : muted,
                fontWeight: strong ? FontWeight.w900 : FontWeight.w700,
              ),
            ),
          ),
          Text(
            value,
            style: TextStyle(
              fontSize: strong ? 18 : 12.5,
              color: text,
              fontWeight: strong ? FontWeight.w900 : FontWeight.w800,
            ),
          ),
        ],
      ),
    );
  }

  Widget _buildBottomCTA() {
    final wizard = context.watch<WizardState>();
    final hasItems = wizard.cartItems.isNotEmpty;

    if (_checkingAuth) {
      return const SafeArea(
        top: false,
        child: SizedBox(
          height: 84,
          child: Center(child: CircularProgressIndicator()),
        ),
      );
    }

    return SafeArea(
      top: false,
      child: Container(
        padding: const EdgeInsets.fromLTRB(16, 12, 16, 16),
        decoration: BoxDecoration(
          color: Colors.white,
          border: const Border(top: BorderSide(color: border)),
          boxShadow: [
            BoxShadow(
              color: Colors.black.withOpacity(0.05),
              blurRadius: 14,
              offset: const Offset(0, -4),
            ),
          ],
        ),
        child: _isLoggedIn
            ? SizedBox(
                width: double.infinity,
                height: 52,
                child: ElevatedButton(
                  onPressed: !hasItems
                      ? null
                      : () {
                          Navigator.push(
                            context,
                            MaterialPageRoute(
                              builder: (_) => const OrderSummaryScreen(),
                            ),
                          );
                        },
                  style: ElevatedButton.styleFrom(
                    backgroundColor: sfBlue,
                    disabledBackgroundColor: Colors.grey.shade400,
                    shape: RoundedRectangleBorder(
                      borderRadius: BorderRadius.circular(16),
                    ),
                    elevation: 0,
                  ),
                  child: Text(
                    hasItems
                        ? "Review Order • ${egp(totalEGP)}"
                        : "Select items to continue",
                    style: const TextStyle(
                      color: Colors.white,
                      fontWeight: FontWeight.w900,
                    ),
                  ),
                ),
              )
            : _authButtonsColumn(),
      ),
    );
  }

  Widget _authButtonsColumn() {
    return Column(
      mainAxisSize: MainAxisSize.min,
      children: [
        SizedBox(
          width: double.infinity,
          height: 50,
          child: ElevatedButton(
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const SignupScreen()),
              );
            },
            style: ElevatedButton.styleFrom(
              backgroundColor: sfBlue,
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
              ),
              elevation: 0,
            ),
            child: const Text(
              "Create account & continue",
              style: TextStyle(
                color: Colors.white,
                fontWeight: FontWeight.w900,
              ),
            ),
          ),
        ),
        const SizedBox(height: 10),
        SizedBox(
          width: double.infinity,
          height: 48,
          child: OutlinedButton(
            onPressed: () {
              Navigator.push(
                context,
                MaterialPageRoute(builder: (_) => const LoginScreen()),
              );
            },
            style: OutlinedButton.styleFrom(
              side: const BorderSide(color: border),
              shape: RoundedRectangleBorder(
                borderRadius: BorderRadius.circular(16),
              ),
            ),
            child: const Text(
              "Already have an account? Login",
              style: TextStyle(color: text, fontWeight: FontWeight.w800),
            ),
          ),
        ),
      ],
    );
  }
}

class _ProductCard extends StatelessWidget {
  final String code;
  final String name;
  final String seller;
  final String unitPrice;
  final int qty;
  final String totalPrice;
  final VoidCallback onMinus;
  final VoidCallback onPlus;
  final VoidCallback onReplace;

  const _ProductCard({
    required this.code,
    required this.name,
    required this.seller,
    required this.unitPrice,
    required this.qty,
    required this.totalPrice,
    required this.onMinus,
    required this.onPlus,
    required this.onReplace,
  });

  @override
  Widget build(BuildContext context) {
    final isNarrow = MediaQuery.of(context).size.width < 620;

    return Container(
      padding: const EdgeInsets.all(12),
      decoration: BoxDecoration(
        color: const Color(0xFFFBFCFE),
        borderRadius: BorderRadius.circular(16),
        border: Border.all(color: _PackagesScreenState.border),
      ),
      child: isNarrow ? _mobileLayout() : _desktopLayout(),
    );
  }

  Widget _desktopLayout() {
    return Row(
      crossAxisAlignment: CrossAxisAlignment.center,
      children: [
        _imageBox(),
        const SizedBox(width: 14),
        Expanded(child: _contentBlock()),
        const SizedBox(width: 14),
        _qtyBlock(),
      ],
    );
  }

  Widget _mobileLayout() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        Row(
          children: [
            _imageBox(),
            const SizedBox(width: 12),
            Expanded(child: _contentBlock()),
          ],
        ),
        const SizedBox(height: 12),
        _qtyBlock(fullWidth: true),
      ],
    );
  }

  Widget _imageBox() {
    return Container(
      width: 86,
      height: 86,
      decoration: BoxDecoration(
        color: const Color(0xFFF1F3F7),
        borderRadius: BorderRadius.circular(12),
        border: Border.all(color: _PackagesScreenState.border),
      ),
      child: Center(
        child: Text(
          code,
          style: const TextStyle(
            fontSize: 18,
            fontWeight: FontWeight.w900,
            color: _PackagesScreenState.sfBlue,
          ),
        ),
      ),
    );
  }

  Widget _contentBlock() {
    return Column(
      crossAxisAlignment: CrossAxisAlignment.start,
      children: [
        const Text(
          "Auto-selected",
          style: TextStyle(
            fontSize: 10,
            color: _PackagesScreenState.muted,
            fontWeight: FontWeight.w700,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          name,
          style: const TextStyle(
            fontSize: 14,
            height: 1.2,
            fontWeight: FontWeight.w900,
            color: _PackagesScreenState.text,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          "From: $seller",
          style: const TextStyle(
            fontSize: 11.5,
            color: _PackagesScreenState.muted,
            fontWeight: FontWeight.w600,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          "Unit: $unitPrice",
          style: const TextStyle(
            fontSize: 11.5,
            color: _PackagesScreenState.muted,
            fontWeight: FontWeight.w700,
          ),
        ),
      ],
    );
  }

  Widget _qtyBlock({bool fullWidth = false}) {
    final content = Column(
      crossAxisAlignment: fullWidth
          ? CrossAxisAlignment.start
          : CrossAxisAlignment.end,
      children: [
        const Text(
          "LINE TOTAL",
          style: TextStyle(
            fontSize: 9.5,
            color: _PackagesScreenState.muted,
            fontWeight: FontWeight.w800,
          ),
        ),
        const SizedBox(height: 4),
        Text(
          totalPrice,
          style: const TextStyle(
            fontSize: 14,
            fontWeight: FontWeight.w900,
            color: _PackagesScreenState.text,
          ),
        ),
        const SizedBox(height: 10),
        Row(
          mainAxisSize: MainAxisSize.min,
          children: [
            _qtyBtn(icon: Icons.remove, onTap: qty > 0 ? onMinus : null),
            Container(
              width: 34,
              alignment: Alignment.center,
              child: Text(
                "$qty",
                style: const TextStyle(
                  fontSize: 13,
                  fontWeight: FontWeight.w900,
                  color: _PackagesScreenState.text,
                ),
              ),
            ),
            _qtyBtn(icon: Icons.add, onTap: onPlus),
          ],
        ),
        const SizedBox(height: 10),
        TextButton(
          onPressed: onReplace,
          style: TextButton.styleFrom(
            padding: EdgeInsets.zero,
            minimumSize: const Size(0, 0),
            tapTargetSize: MaterialTapTargetSize.shrinkWrap,
          ),
          child: const Text(
            "Replace product",
            style: TextStyle(
              color: _PackagesScreenState.sfBlue,
              fontWeight: FontWeight.w800,
              fontSize: 11.5,
            ),
          ),
        ),
      ],
    );

    if (fullWidth) {
      return Align(alignment: Alignment.centerLeft, child: content);
    }

    return SizedBox(width: 120, child: content);
  }

  Widget _qtyBtn({required IconData icon, required VoidCallback? onTap}) {
    return SizedBox(
      width: 28,
      height: 28,
      child: OutlinedButton(
        onPressed: onTap,
        style: OutlinedButton.styleFrom(
          padding: EdgeInsets.zero,
          visualDensity: VisualDensity.compact,
          side: const BorderSide(color: _PackagesScreenState.border),
          shape: RoundedRectangleBorder(borderRadius: BorderRadius.circular(8)),
        ),
        child: Icon(icon, size: 14, color: _PackagesScreenState.text),
      ),
    );
  }
}

class _PkgSection {
  final String title;
  final String subtitle;
  final int capEGP;
  final String badge;
  final String moduleKey;
  final List<_PkgItem> items;

  _PkgSection({
    required this.title,
    required this.subtitle,
    required this.capEGP,
    required this.badge,
    required this.moduleKey,
    required this.items,
  });
}

class _PkgItem {
  final String keyId;
  final int productId;
  final String typeKey;
  final String name;
  final String seller;
  final int priceEGP;
  final String code;
  final int defaultQty;
  final String moduleKey;
  final String imageUrl;
  final String brand;

  _PkgItem({
    required this.keyId,
    required this.productId,
    required this.typeKey,
    required this.name,
    required this.seller,
    required this.priceEGP,
    required this.code,
    required this.defaultQty,
    required this.moduleKey,
    this.imageUrl = '',
    this.brand = '',
  });
}
