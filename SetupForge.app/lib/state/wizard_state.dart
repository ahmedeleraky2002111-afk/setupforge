import 'package:flutter/foundation.dart';

class WizardState extends ChangeNotifier {
  String businessType = '';
  String businessName = '';
  String restaurantType = '';
  int indoorTables = 0;
  int outdoorTables = 0;
  int tableSize = 4;
  int areaSqm = 0;
  String budgetRange = '';
  List<String> installationServices = [];
  Map<String, int> staffCounts = {
    'waiter': 0,
    'chef': 0,
    'cashier': 0,
    'security': 0,
    'barista': 0,
    'busboy': 0,
    'host': 0,
    'kitchen_helper': 0,
  };

  List<Map<String, dynamic>> cartItems = [];

  double get totalPrice {
    double total = 0;
    for (final item in cartItems) {
      final price = (item['price'] as num?)?.toDouble() ?? 0;
      final qty = (item['qty'] as num?)?.toInt() ?? 1;
      total += price * qty;
    }
    return total;
  }

  void setBusinessType(String value) {
    businessType = value;
    notifyListeners();
  }

  void setBusinessName(String value) {
    businessName = value;
    notifyListeners();
  }

  void setRestaurantType(String value) {
    restaurantType = value;
    notifyListeners();
  }

  void setIndoorTables(int value) {
    indoorTables = value;
    notifyListeners();
  }

  void setOutdoorTables(int value) {
    outdoorTables = value;
    notifyListeners();
  }

  void setTableSize(int value) {
    tableSize = value;
    notifyListeners();
  }

  void setAreaSqm(int value) {
    areaSqm = value;
    notifyListeners();
  }

  void setBudgetRange(String value) {
    budgetRange = value;
    notifyListeners();
  }

  void setInstallationServices(List<String> value) {
    installationServices = List<String>.from(value);
    notifyListeners();
  }

  void setStaffCounts(Map<String, int> counts) {
    staffCounts = Map<String, int>.from(counts);
    notifyListeners();
  }

  void setCartItems(List<Map<String, dynamic>> items) {
    cartItems = List<Map<String, dynamic>>.from(items);
    notifyListeners();
  }

  void addCartItem(Map<String, dynamic> item) {
    cartItems.add(item);
    notifyListeners();
  }

  void updateCartItemQty(String keyId, int qty) {
    final index = cartItems.indexWhere((item) => item['keyId'] == keyId);
    if (index != -1) {
      cartItems[index]['qty'] = qty;
      notifyListeners();
    }
  }

  void clearCart() {
    cartItems.clear();
    notifyListeners();
  }

  void saveSetup({
    required String businessType,
    required String businessName,
    required String restaurantType,
    required int indoorTables,
    required int outdoorTables,
    required int tableSize,
    required int areaSqm,
    required String budgetRange,
    required List<String> installationServices,
    required Map<String, int> staffCounts,
  }) {
    this.businessType = businessType;
    this.businessName = businessName;
    this.restaurantType = restaurantType;
    this.indoorTables = indoorTables;
    this.outdoorTables = outdoorTables;
    this.tableSize = tableSize;
    this.areaSqm = areaSqm;
    this.budgetRange = budgetRange;
    this.installationServices = List<String>.from(installationServices);
    this.staffCounts = Map<String, int>.from(staffCounts);
    notifyListeners();
  }

  void resetAll() {
    businessType = '';
    businessName = '';
    restaurantType = '';
    indoorTables = 0;
    outdoorTables = 0;
    tableSize = 4;
    areaSqm = 0;
    budgetRange = '';
    installationServices = [];
    staffCounts = {
      'waiter': 0,
      'chef': 0,
      'cashier': 0,
      'security': 0,
      'barista': 0,
      'busboy': 0,
      'host': 0,
      'kitchen_helper': 0,
    };
    cartItems = [];
    notifyListeners();
  }
}
