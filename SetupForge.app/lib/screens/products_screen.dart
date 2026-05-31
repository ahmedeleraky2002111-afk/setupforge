import 'package:flutter/material.dart';
import '../services/api_service.dart';

class ProductsScreen extends StatefulWidget {
  const ProductsScreen({super.key});

  @override
  State<ProductsScreen> createState() => _ProductsScreenState();
}

class _ProductsScreenState extends State<ProductsScreen> {
  static const Color sfBlue = Color(0xFF004CAC);
  static const Color sfBg = Color(0xFFF5F7FB);
  static const Color sfText = Color(0xFF111827);
  static const Color sfMuted = Color(0xFF6B7280);

  final api = ApiService();
  final _searchC = TextEditingController();

  bool _loading = true;
  Map<String, dynamic> _data = {};
  List<Map<String, dynamic>> _products = [];
  Set<int> _cartIds = {};
  int _cartCount = 0;

  // Filters
  String _selectedCategory = '';
  String _selectedBrand = '';
  String _selectedModule = '';
  String _selectedSort = '';
  bool _showFilters = false;

  @override
  void initState() {
    super.initState();
    _load();
  }

  @override
  void dispose() {
    _searchC.dispose();
    super.dispose();
  }

  Future<void> _load() async {
    setState(() => _loading = true);
    final res = await api.getProducts(
      category: _selectedCategory,
      brand: _selectedBrand,
      module: _selectedModule,
      sort: _selectedSort,
      search: _searchC.text.trim(),
    );
    if (!mounted) return;
    setState(() {
      _data = res;
      _products = List<Map<String, dynamic>>.from(res["products"] ?? []);
      _cartIds = Set<int>.from(
        (res["cart_product_ids"] ?? []).map(
          (e) => int.tryParse(e.toString()) ?? 0,
        ),
      );
      _cartCount = res["cart_count"] ?? 0;
      _loading = false;
    });
  }

  Future<void> _addToCart(int productId, String name) async {
    final res = await api.cartAdd(productId);
    if (!mounted) return;
    if (res["ok"] == true) {
      setState(() {
        _cartIds.add(productId);
        _cartCount = res["cart_count"] ?? _cartCount;
      });
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(
          content: Text('$name added to cart'),
          action: SnackBarAction(
            label: 'View Cart',
            onPressed: () => Navigator.pushNamed(context, '/cart'),
          ),
          duration: const Duration(seconds: 2),
        ),
      );
    } else {
      ScaffoldMessenger.of(context).showSnackBar(
        SnackBar(content: Text(res["error"]?.toString() ?? "Failed to add")),
      );
    }
  }

  void _clearFilters() {
    setState(() {
      _selectedCategory = '';
      _selectedBrand = '';
      _selectedModule = '';
      _selectedSort = '';
      _searchC.clear();
    });
    _load();
  }

  @override
  Widget build(BuildContext context) {
    final categories = List<Map<String, dynamic>>.from(
      _data["categories"] ?? [],
    );
    final brands = List<String>.from(_data["brands"] ?? []);

    return Scaffold(
      backgroundColor: sfBg,
      body: NestedScrollView(
        headerSliverBuilder: (context, innerBoxIsScrolled) => [
          SliverAppBar(
            backgroundColor: sfBlue,
            pinned: true,
            automaticallyImplyLeading: false,
            expandedHeight: 110,
            flexibleSpace: FlexibleSpaceBar(
              background: Container(
                color: sfBlue,
                padding: const EdgeInsets.fromLTRB(16, 50, 16, 12),
                child: Column(
                  crossAxisAlignment: CrossAxisAlignment.start,
                  mainAxisAlignment: MainAxisAlignment.end,
                  children: [
                    Row(
                      children: [
                        const Expanded(
                          child: Text(
                            'Products',
                            style: TextStyle(
                              color: Colors.white,
                              fontSize: 20,
                              fontWeight: FontWeight.w900,
                            ),
                          ),
                        ),
                        // Cart icon with badge
                        Stack(
                          children: [
                            IconButton(
                              onPressed: () => Navigator.pushNamed(
                                context,
                                '/cart',
                              ).then((_) => _load()),
                              icon: const Icon(
                                Icons.shopping_cart_outlined,
                                color: Colors.white,
                              ),
                            ),
                            if (_cartCount > 0)
                              Positioned(
                                right: 6,
                                top: 6,
                                child: Container(
                                  width: 16,
                                  height: 16,
                                  decoration: const BoxDecoration(
                                    color: Colors.red,
                                    shape: BoxShape.circle,
                                  ),
                                  child: Center(
                                    child: Text(
                                      _cartCount > 9 ? '9+' : '$_cartCount',
                                      style: const TextStyle(
                                        color: Colors.white,
                                        fontSize: 9,
                                        fontWeight: FontWeight.w800,
                                      ),
                                    ),
                                  ),
                                ),
                              ),
                          ],
                        ),
                        // Filter toggle
                        IconButton(
                          onPressed: () =>
                              setState(() => _showFilters = !_showFilters),
                          icon: Icon(
                            _showFilters
                                ? Icons.filter_list_off
                                : Icons.filter_list,
                            color: Colors.white,
                          ),
                        ),
                      ],
                    ),
                    // Search bar
                    SizedBox(
                      height: 36,
                      child: TextField(
                        controller: _searchC,
                        onSubmitted: (_) => _load(),
                        style: const TextStyle(fontSize: 13.5, color: sfText),
                        decoration: InputDecoration(
                          hintText: 'Search products...',
                          hintStyle: const TextStyle(
                            color: sfMuted,
                            fontSize: 13.5,
                          ),
                          filled: true,
                          fillColor: Colors.white,
                          contentPadding: const EdgeInsets.symmetric(
                            horizontal: 12,
                            vertical: 0,
                          ),
                          border: const OutlineInputBorder(
                            borderRadius: BorderRadius.zero,
                            borderSide: BorderSide.none,
                          ),
                          enabledBorder: const OutlineInputBorder(
                            borderRadius: BorderRadius.zero,
                            borderSide: BorderSide.none,
                          ),
                          focusedBorder: const OutlineInputBorder(
                            borderRadius: BorderRadius.zero,
                            borderSide: BorderSide.none,
                          ),
                          prefixIcon: const Icon(
                            Icons.search,
                            size: 18,
                            color: sfMuted,
                          ),
                          suffixIcon: _searchC.text.isNotEmpty
                              ? IconButton(
                                  onPressed: () {
                                    _searchC.clear();
                                    _load();
                                  },
                                  icon: const Icon(
                                    Icons.close,
                                    size: 16,
                                    color: sfMuted,
                                  ),
                                )
                              : null,
                        ),
                      ),
                    ),
                  ],
                ),
              ),
            ),
          ),
        ],
        body: Column(
          children: [
            // Filters panel
            if (_showFilters)
              Container(
                color: Colors.white,
                padding: const EdgeInsets.all(12),
                child: Column(
                  children: [
                    // Module filter
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: Row(
                        children: [
                          _filterChip('All', '', _selectedModule == ''),
                          _filterChip(
                            'Kitchen',
                            'kitchen',
                            _selectedModule == 'kitchen',
                          ),
                          _filterChip('POS', 'pos', _selectedModule == 'pos'),
                          _filterChip(
                            'Dining Area',
                            'furniture',
                            _selectedModule == 'furniture',
                          ),
                          _filterChip('AC', 'ac', _selectedModule == 'ac'),
                        ],
                      ),
                    ),
                    const SizedBox(height: 8),
                    // Sort
                    SingleChildScrollView(
                      scrollDirection: Axis.horizontal,
                      child: Row(
                        children: [
                          _sortChip('Newest', '', _selectedSort == ''),
                          _sortChip(
                            'Price ↑',
                            'price_low',
                            _selectedSort == 'price_low',
                          ),
                          _sortChip(
                            'Price ↓',
                            'price_high',
                            _selectedSort == 'price_high',
                          ),
                          _sortChip(
                            'Rating',
                            'rating',
                            _selectedSort == 'rating',
                          ),
                          _sortChip(
                            'A→Z',
                            'name_asc',
                            _selectedSort == 'name_asc',
                          ),
                        ],
                      ),
                    ),
                    if (_selectedModule.isNotEmpty ||
                        _selectedSort.isNotEmpty ||
                        _selectedCategory.isNotEmpty)
                      Align(
                        alignment: Alignment.centerRight,
                        child: TextButton(
                          onPressed: _clearFilters,
                          child: const Text(
                            'Clear Filters',
                            style: TextStyle(
                              color: sfBlue,
                              fontWeight: FontWeight.w700,
                            ),
                          ),
                        ),
                      ),
                  ],
                ),
              ),

            // Product count
            if (!_loading)
              Container(
                width: double.infinity,
                padding: const EdgeInsets.symmetric(
                  horizontal: 16,
                  vertical: 8,
                ),
                color: Colors.white,
                child: Text(
                  '${_products.length} product${_products.length == 1 ? '' : 's'}',
                  style: const TextStyle(
                    fontSize: 12.5,
                    color: sfMuted,
                    fontWeight: FontWeight.w600,
                  ),
                ),
              ),

            // Products grid
            Expanded(
              child: _loading
                  ? const Center(
                      child: CircularProgressIndicator(color: sfBlue),
                    )
                  : _products.isEmpty
                  ? _emptyState()
                  : RefreshIndicator(
                      color: sfBlue,
                      onRefresh: _load,
                      child: GridView.builder(
                        padding: const EdgeInsets.all(12),
                        gridDelegate:
                            const SliverGridDelegateWithFixedCrossAxisCount(
                              crossAxisCount: 2,
                              crossAxisSpacing: 10,
                              mainAxisSpacing: 10,
                              childAspectRatio: 0.62,
                            ),
                        itemCount: _products.length,
                        itemBuilder: (ctx, i) => _productCard(_products[i]),
                      ),
                    ),
            ),
          ],
        ),
      ),
    );
  }

  Widget _filterChip(String label, String value, bool selected) {
    return GestureDetector(
      onTap: () {
        setState(() => _selectedModule = value);
        _load();
      },
      child: Container(
        margin: const EdgeInsets.only(right: 8),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: selected ? sfBlue : const Color(0xFFF3F4F6),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 12.5,
            fontWeight: FontWeight.w700,
            color: selected ? Colors.white : sfText,
          ),
        ),
      ),
    );
  }

  Widget _sortChip(String label, String value, bool selected) {
    return GestureDetector(
      onTap: () {
        setState(() => _selectedSort = value);
        _load();
      },
      child: Container(
        margin: const EdgeInsets.only(right: 8),
        padding: const EdgeInsets.symmetric(horizontal: 12, vertical: 6),
        decoration: BoxDecoration(
          color: selected ? const Color(0xFFEFF6FF) : Colors.white,
          border: Border.all(
            color: selected ? sfBlue : const Color(0xFFE5E7EB),
          ),
        ),
        child: Text(
          label,
          style: TextStyle(
            fontSize: 12.5,
            fontWeight: FontWeight.w700,
            color: selected ? sfBlue : sfText,
          ),
        ),
      ),
    );
  }

  Widget _productCard(Map<String, dynamic> p) {
    final pid = p["id"] as int;
    final inCart = _cartIds.contains(pid);
    final outOfStock = (p["stock"] ?? 0) == 0;
    final price = (p["price"] as double?)?.toStringAsFixed(0) ?? "0";
    final rating = p["avg_rating"] as double?;
    final imageUrl = p["image_url"]?.toString();

    return Container(
      decoration: BoxDecoration(
        color: Colors.white,
        border: Border.all(color: const Color(0xFFE5E7EB)),
      ),
      child: Column(
        crossAxisAlignment: CrossAxisAlignment.start,
        children: [
          // Image
          Expanded(
            child: Stack(
              children: [
                Container(
                  width: double.infinity,
                  color: const Color(0xFFF9FAFB),
                  child: imageUrl != null && imageUrl.isNotEmpty
                      ? Image.network(
                          imageUrl,
                          fit: BoxFit.cover,
                          width: double.infinity,
                          errorBuilder: (_, __, ___) => const Icon(
                            Icons.inventory_2_outlined,
                            color: sfMuted,
                            size: 40,
                          ),
                        )
                      : const Icon(
                          Icons.inventory_2_outlined,
                          color: sfMuted,
                          size: 40,
                        ),
                ),
                if (outOfStock)
                  Container(
                    color: Colors.black.withOpacity(0.4),
                    child: const Center(
                      child: Text(
                        'Out of Stock',
                        style: TextStyle(
                          color: Colors.white,
                          fontSize: 11,
                          fontWeight: FontWeight.w700,
                        ),
                      ),
                    ),
                  ),
              ],
            ),
          ),

          // Info
          Padding(
            padding: const EdgeInsets.all(8),
            child: Column(
              crossAxisAlignment: CrossAxisAlignment.start,
              children: [
                // Category badge
                if ((p["category_name"] ?? "").toString().isNotEmpty)
                  Container(
                    margin: const EdgeInsets.only(bottom: 4),
                    padding: const EdgeInsets.symmetric(
                      horizontal: 6,
                      vertical: 2,
                    ),
                    color: const Color(0xFFEFF6FF),
                    child: Text(
                      p["category_name"].toString(),
                      style: const TextStyle(
                        fontSize: 9.5,
                        fontWeight: FontWeight.w700,
                        color: sfBlue,
                      ),
                    ),
                  ),

                Text(
                  p["product_name"] ?? "—",
                  style: const TextStyle(
                    fontSize: 12.5,
                    fontWeight: FontWeight.w700,
                    color: sfText,
                  ),
                  maxLines: 2,
                  overflow: TextOverflow.ellipsis,
                ),

                if ((p["brand"] ?? "").toString().isNotEmpty) ...[
                  const SizedBox(height: 2),
                  Text(
                    p["brand"].toString(),
                    style: const TextStyle(fontSize: 11, color: sfMuted),
                    maxLines: 1,
                    overflow: TextOverflow.ellipsis,
                  ),
                ],

                const SizedBox(height: 6),

                Row(
                  children: [
                    Expanded(
                      child: Text(
                        '$price EGP',
                        style: const TextStyle(
                          fontSize: 13,
                          fontWeight: FontWeight.w900,
                          color: sfText,
                        ),
                      ),
                    ),
                    if (rating != null)
                      Row(
                        children: [
                          const Icon(
                            Icons.star_rounded,
                            size: 11,
                            color: Color(0xFFF59E0B),
                          ),
                          const SizedBox(width: 2),
                          Text(
                            rating.toStringAsFixed(1),
                            style: const TextStyle(
                              fontSize: 10.5,
                              fontWeight: FontWeight.w700,
                              color: sfText,
                            ),
                          ),
                        ],
                      ),
                  ],
                ),

                const SizedBox(height: 8),

                // Add to cart button
                SizedBox(
                  width: double.infinity,
                  child: outOfStock
                      ? Container(
                          padding: const EdgeInsets.symmetric(vertical: 8),
                          color: const Color(0xFFF3F4F6),
                          child: const Text(
                            'Out of Stock',
                            textAlign: TextAlign.center,
                            style: TextStyle(
                              fontSize: 11.5,
                              fontWeight: FontWeight.w700,
                              color: sfMuted,
                            ),
                          ),
                        )
                      : inCart
                      ? GestureDetector(
                          onTap: () => Navigator.pushNamed(
                            context,
                            '/cart',
                          ).then((_) => _load()),
                          child: Container(
                            padding: const EdgeInsets.symmetric(vertical: 8),
                            color: const Color(0xFFDCFCE7),
                            child: const Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(
                                  Icons.check_rounded,
                                  size: 13,
                                  color: Color(0xFF16A34A),
                                ),
                                SizedBox(width: 4),
                                Text(
                                  'In Cart',
                                  style: TextStyle(
                                    fontSize: 11.5,
                                    fontWeight: FontWeight.w700,
                                    color: Color(0xFF16A34A),
                                  ),
                                ),
                              ],
                            ),
                          ),
                        )
                      : GestureDetector(
                          onTap: () => _addToCart(pid, p["product_name"] ?? ""),
                          child: Container(
                            padding: const EdgeInsets.symmetric(vertical: 8),
                            color: sfBlue,
                            child: const Row(
                              mainAxisAlignment: MainAxisAlignment.center,
                              children: [
                                Icon(
                                  Icons.add_shopping_cart_rounded,
                                  size: 13,
                                  color: Colors.white,
                                ),
                                SizedBox(width: 4),
                                Text(
                                  'Add to Cart',
                                  style: TextStyle(
                                    fontSize: 11.5,
                                    fontWeight: FontWeight.w700,
                                    color: Colors.white,
                                  ),
                                ),
                              ],
                            ),
                          ),
                        ),
                ),
                const SizedBox(height: 4),

                // Buy Now
                if (!outOfStock)
                  GestureDetector(
                    onTap: () => Navigator.pushNamed(
                      context,
                      '/checkout',
                      arguments: {"product_id": pid, "qty": 1},
                    ),
                    child: Container(
                      width: double.infinity,
                      padding: const EdgeInsets.symmetric(vertical: 8),
                      decoration: BoxDecoration(
                        border: Border.all(color: sfBlue),
                      ),
                      child: const Text(
                        'Buy Now',
                        textAlign: TextAlign.center,
                        style: TextStyle(
                          fontSize: 11.5,
                          fontWeight: FontWeight.w700,
                          color: sfBlue,
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

  Widget _emptyState() {
    return Center(
      child: Column(
        mainAxisSize: MainAxisSize.min,
        children: [
          const Icon(Icons.search_off_rounded, size: 48, color: sfMuted),
          const SizedBox(height: 12),
          const Text(
            'No products found',
            style: TextStyle(
              fontSize: 16,
              fontWeight: FontWeight.w800,
              color: sfText,
            ),
          ),
          const SizedBox(height: 6),
          const Text(
            'Try changing the filters.',
            style: TextStyle(color: sfMuted, fontSize: 13.5),
          ),
          const SizedBox(height: 16),
          GestureDetector(
            onTap: _clearFilters,
            child: Container(
              padding: const EdgeInsets.symmetric(horizontal: 20, vertical: 10),
              color: sfBlue,
              child: const Text(
                'Clear Filters',
                style: TextStyle(
                  color: Colors.white,
                  fontWeight: FontWeight.w700,
                  fontSize: 13.5,
                ),
              ),
            ),
          ),
        ],
      ),
    );
  }
}
