<?php
session_start();
require_once "db.php";

function h($value) {
    return htmlspecialchars((string)$value, ENT_QUOTES, "UTF-8");
}

function formatPrice($price) {
    return number_format((float)$price, 2);
}

// Cart visibility
$userType = $_SESSION["user_type"] ?? "guest";
$showCart = !in_array($userType, ["vendor", "labor", "company"], true);

// Current cart keys for "already in cart" state
$cartKeys = [];
if ($showCart && !empty($_SESSION["shop_cart"])) {
    $cartKeys = array_keys($_SESSION["shop_cart"]);
}

/* ---------------------------------------------------------
   FILTERS
--------------------------------------------------------- */
$selectedCategory = isset($_GET["category"]) ? trim((string)$_GET["category"]) : "";
$selectedBrand    = isset($_GET["brand"]) ? trim((string)$_GET["brand"]) : "";
$selectedModule   = isset($_GET["module"]) ? trim((string)$_GET["module"]) : "";
$minPrice         = isset($_GET["min_price"]) ? trim((string)$_GET["min_price"]) : "";
$maxPrice         = isset($_GET["max_price"]) ? trim((string)$_GET["max_price"]) : "";
$sort             = isset($_GET["sort"]) ? trim((string)$_GET["sort"]) : "";

/* ---------------------------------------------------------
   GET CATEGORIES
--------------------------------------------------------- */
$categories = [];
$categoryQuery = pg_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
if ($categoryQuery) {
    while ($row = pg_fetch_assoc($categoryQuery)) $categories[] = $row;
}

/* ---------------------------------------------------------
   GET BRANDS
--------------------------------------------------------- */
$brands = [];
$brandQuery = pg_query($conn, "
    SELECT DISTINCT brand FROM products
    WHERE brand IS NOT NULL AND TRIM(brand) <> ''
    ORDER BY brand ASC
");
if ($brandQuery) {
    while ($row = pg_fetch_assoc($brandQuery)) $brands[] = $row["brand"];
}

/* ---------------------------------------------------------
   QUERY BUILD
--------------------------------------------------------- */
$params     = [];
$where      = ["1=1"];
$paramIndex = 1;

if ($selectedCategory !== "" && ctype_digit($selectedCategory)) {
    $where[]  = "p.category_id = $" . $paramIndex;
    $params[] = (int)$selectedCategory;
    $paramIndex++;
}
if ($selectedBrand !== "") {
    $where[]  = "p.brand = $" . $paramIndex;
    $params[] = $selectedBrand;
    $paramIndex++;
}
if ($selectedModule !== "") {
    $where[]  = "p.module = $" . $paramIndex;
    $params[] = $selectedModule;
    $paramIndex++;
}
if ($minPrice !== "" && is_numeric($minPrice)) {
    $where[]  = "p.price >= $" . $paramIndex;
    $params[] = (float)$minPrice;
    $paramIndex++;
}
if ($maxPrice !== "" && is_numeric($maxPrice)) {
    $where[]  = "p.price <= $" . $paramIndex;
    $params[] = (float)$maxPrice;
    $paramIndex++;
}

$orderBy = "p.created_at DESC";
if ($sort === "price_low")   $orderBy = "p.price ASC";
elseif ($sort === "price_high") $orderBy = "p.price DESC";
elseif ($sort === "name_asc")   $orderBy = "p.product_name ASC";
elseif ($sort === "name_desc")  $orderBy = "p.product_name DESC";

$sql = "
    SELECT
        p.id, p.product_name, p.brand, p.price, p.stock_quantity,
        p.avg_rating, p.created_at, p.category_id,
        c.name AS category_name, p.business_type, p.priority,
        p.module, p.tier, p.product_group_key, img.image_url
    FROM products p
    LEFT JOIN categories c ON c.id = p.category_id
    LEFT JOIN LATERAL (
        SELECT pi.image_url FROM product_images pi
        WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1
    ) img ON true
    WHERE " . implode(" AND ", $where) . "
    ORDER BY $orderBy
";

$productResult = pg_query_params($conn, $sql, $params);
$products = [];
if ($productResult) {
    while ($row = pg_fetch_assoc($productResult)) $products[] = $row;
}
$productCount = count($products);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Products - SetupForge</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="assets/style.css?v=20" rel="stylesheet">
</head>
<body>

<?php include "includes/navbar.php"; ?>

<main class="sf-products-page">
  <div class="container py-5">

    <h1 class="sf-products-title mb-4">Products</h1>

    <div class="sf-products-layout">

      <!-- FILTERS -->
      <form method="GET" class="sf-products-filter-card">
        <div class="sf-filter-head">
          <span class="sf-filter-label">Filters</span>
          <a href="products.php" class="sf-clear-filters">Clear</a>
        </div>

        <div class="sf-filter-group sf-custom-select" data-select="#category">
          <button type="button" class="sf-custom-select-trigger" data-label="Category">
            <span class="sf-custom-select-text">Category</span>
            <i class="bi bi-chevron-down"></i>
          </button>
          <div class="sf-custom-select-menu">
            <?php foreach ($categories as $cat): ?>
              <div class="sf-custom-select-option" data-value="<?= h($cat["id"]) ?>"><?= h($cat["name"]) ?></div>
            <?php endforeach; ?>
          </div>
          <select name="category" id="category" class="sf-filter-input" style="display:none;">
            <option value="">Category</option>
            <?php foreach ($categories as $cat): ?>
              <option value="<?= h($cat["id"]) ?>" <?= ($selectedCategory !== "" && (string)$selectedCategory === (string)$cat["id"]) ? "selected" : "" ?>>
                <?= h($cat["name"]) ?>
              </option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="sf-filter-group sf-custom-select" data-select="#brand">
          <button type="button" class="sf-custom-select-trigger" data-label="Brand">
            <span class="sf-custom-select-text">Brand</span>
            <i class="bi bi-chevron-down"></i>
          </button>
          <div class="sf-custom-select-menu">
            <?php foreach ($brands as $brand): ?>
              <div class="sf-custom-select-option" data-value="<?= h($brand) ?>"><?= h($brand) ?></div>
            <?php endforeach; ?>
          </div>
          <select name="brand" id="brand" class="sf-filter-input" style="display:none;">
            <option value="">Brand</option>
            <?php foreach ($brands as $brand): ?>
              <option value="<?= h($brand) ?>" <?= ($selectedBrand === $brand) ? "selected" : "" ?>><?= h($brand) ?></option>
            <?php endforeach; ?>
          </select>
        </div>

        <div class="sf-filter-group sf-custom-select" data-select="#module">
          <button type="button" class="sf-custom-select-trigger" data-label="Module">
            <span class="sf-custom-select-text">Module</span>
            <i class="bi bi-chevron-down"></i>
          </button>
          <div class="sf-custom-select-menu">
            <div class="sf-custom-select-option" data-value="kitchen">Kitchen</div>
            <div class="sf-custom-select-option" data-value="pos">POS</div>
            <div class="sf-custom-select-option" data-value="furniture">Dining Area</div>
            <div class="sf-custom-select-option" data-value="general">General</div>
            <div class="sf-custom-select-option" data-value="infrastructure">Infrastructure</div>
          </div>
          <select name="module" id="module" class="sf-filter-input" style="display:none;">
            <option value="">Module</option>
            <option value="kitchen"        <?= $selectedModule === 'kitchen'        ? 'selected' : '' ?>>Kitchen</option>
            <option value="pos"            <?= $selectedModule === 'pos'            ? 'selected' : '' ?>>POS</option>
            <option value="furniture"      <?= $selectedModule === 'furniture'      ? 'selected' : '' ?>>Dining Area</option>
            <option value="general"        <?= $selectedModule === 'general'        ? 'selected' : '' ?>>General</option>
            <option value="infrastructure" <?= $selectedModule === 'infrastructure' ? 'selected' : '' ?>>Infrastructure</option>
          </select>
        </div>

        <div class="sf-filter-group sf-custom-select" data-select="#sort">
          <button type="button" class="sf-custom-select-trigger" data-label="Sort By">
            <span class="sf-custom-select-text">Sort By</span>
            <i class="bi bi-chevron-down"></i>
          </button>
          <div class="sf-custom-select-menu">
            <div class="sf-custom-select-option" data-value="newest">Newest</div>
            <div class="sf-custom-select-option" data-value="price_low">Price: Low to High</div>
            <div class="sf-custom-select-option" data-value="price_high">Price: High to Low</div>
            <div class="sf-custom-select-option" data-value="name_asc">Name: A to Z</div>
            <div class="sf-custom-select-option" data-value="name_desc">Name: Z to A</div>
          </div>
          <select name="sort" id="sort" class="sf-filter-input" style="display:none;">
            <option value="">Sort By</option>
            <option value="newest"     <?= $sort === "newest"     ? "selected" : "" ?>>Newest</option>
            <option value="price_low"  <?= $sort === "price_low"  ? "selected" : "" ?>>Price: Low to High</option>
            <option value="price_high" <?= $sort === "price_high" ? "selected" : "" ?>>Price: High to Low</option>
            <option value="name_asc"   <?= $sort === "name_asc"   ? "selected" : "" ?>>Name: A to Z</option>
            <option value="name_desc"  <?= $sort === "name_desc"  ? "selected" : "" ?>>Name: Z to A</option>
          </select>
        </div>

        <div class="sf-price-slider-wrap">
          <div class="sf-price-slider-labels">
            <span id="sliderMinLabel">EGP <span id="sliderMinVal"><?= $minPrice !== '' ? (int)$minPrice : 0 ?></span></span>
            <span id="sliderMaxLabel">EGP <span id="sliderMaxVal"><?= $maxPrice !== '' ? (int)$maxPrice : 200000 ?></span></span>
          </div>
          <div class="sf-range-slider" id="sfRangeSlider">
            <div class="sf-range-track"></div>
            <div class="sf-range-fill" id="sfRangeFill"></div>
            <input type="range" id="sliderMin" min="0" max="200000" step="500" value="<?= $minPrice !== '' ? (int)$minPrice : 0 ?>">
            <input type="range" id="sliderMax" min="0" max="200000" step="500" value="<?= $maxPrice !== '' ? (int)$maxPrice : 200000 ?>">
          </div>
          <input type="hidden" name="min_price" id="min_price" value="<?= h($minPrice) ?>">
          <input type="hidden" name="max_price" id="max_price" value="<?= h($maxPrice) ?>">
        </div>

        <button type="submit" class="sf-filter-btn">Apply Filters</button>
      </form>

      <!-- PRODUCTS -->
      <section class="sf-products-main">

        <div class="sf-products-toolbar">
          <div class="sf-products-toolbar-left">
            <span class="sf-products-toolbar-label">
              <?= $productCount ?> product<?= $productCount === 1 ? "" : "s" ?>
            </span>
          </div>
        </div>

        <?php if (!empty($products)): ?>
          <div class="sf-products-grid">
            <?php foreach ($products as $product):
              $pid        = (int)$product["id"];
              $inCart     = in_array((string)$pid, $cartKeys, true);
              $outOfStock = (int)$product["stock_quantity"] <= 0;
            ?>
              <article class="sf-product-card-modern">

                <div class="sf-product-card-media">
                  <?php if (!empty($product["image_url"])): ?>
                    <img src="<?= h($product["image_url"]) ?>" alt="<?= h($product["product_name"]) ?>">
                  <?php else: ?>
                    <div class="sf-product-card-fallback">
                      <i class="bi bi-box-seam"></i>
                    </div>
                  <?php endif; ?>
                </div>

                <div class="sf-product-card-body">
                  <div class="sf-product-card-top">
                    <?php if (!empty($product["category_name"])): ?>
                      <span class="sf-product-badge"><?= h($product["category_name"]) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($product["condition"])): ?>
                      <span class="sf-product-badge sf-product-badge-light"><?= h(ucfirst($product["condition"])) ?></span>
                    <?php endif; ?>
                  </div>

                  <h3 class="sf-product-name"><?= h($product["product_name"]) ?></h3>

                  <div class="sf-product-brand">
                    <?= !empty($product["brand"]) ? h($product["brand"]) : "No brand" ?>
                  </div>

                  <div class="sf-product-meta-row">
                    <?php if (!empty($product["module"])): ?>
                      <span><?= h($product["module"] === 'furniture' ? 'Dining Area' : ucfirst($product["module"])) ?></span>
                    <?php endif; ?>
                    <?php if (!empty($product["tier"])): ?>
                      <span><?= h($product["tier"]) ?></span>
                    <?php endif; ?>
                    <?php if (!$outOfStock): ?>
                      <span>In Stock</span>
                    <?php else: ?>
                      <span>Out of Stock</span>
                    <?php endif; ?>
                  </div>

                  <div class="sf-product-price-row">
                    <div class="sf-product-price">
                      EGP <?= formatPrice($product["price"]) ?>
                    </div>
                    <?php if ($product["avg_rating"] !== null && $product["avg_rating"] !== ""): ?>
                      <div class="sf-product-rating">
                        <i class="bi bi-star-fill"></i>
                        <?= h($product["avg_rating"]) ?>
                      </div>
                    <?php endif; ?>
                  </div>

                  <div class="sf-product-actions">
                    <a href="pr_details.php?id=<?= $pid ?>" class="sf-product-btn-main">
                      View Details
                    </a>

                    <?php if ($showCart): ?>
                      <?php if ($outOfStock): ?>
                        <button class="sf-product-btn-cart" disabled>Out of Stock</button>
                      <?php elseif ($inCart): ?>
                        <a href="cart.php" class="sf-product-btn-cart sf-product-btn-cart--added">
                          <i class="bi bi-check2"></i> In Cart
                        </a>
                      <?php else: ?>
                        <button
                          class="sf-product-btn-cart sf-add-to-cart-btn"
                          data-product-id="<?= $pid ?>"
                          data-name="<?= h($product["product_name"]) ?>">
                          <i class="bi bi-cart-plus"></i> Add to Cart
                        </button>
                      <?php endif; ?>
                    <?php endif; ?>
                  </div>
                </div>

              </article>
            <?php endforeach; ?>
          </div>
        <?php else: ?>
          <div class="sf-products-empty">
            <div class="sf-products-empty-icon">
              <i class="bi bi-search"></i>
            </div>
            <h3>No products found</h3>
            <p>Try changing the filters.</p>
            <a href="products.php" class="sf-filter-btn sf-filter-btn-inline">Clear Filters</a>
          </div>
        <?php endif; ?>

      </section>
    </div>
  </div>
</main>

<!-- Toast notification -->
<div id="sf-cart-toast" style="
  display:none;position:fixed;bottom:24px;right:24px;z-index:9999;
  background:#111827;color:#fff;padding:14px 20px;border-radius:5px;
  font-size:.88rem;font-weight:600;box-shadow:0 8px 24px rgba(0,0,0,.18);
  align-items:center;gap:10px;max-width:320px;">
  <i class="bi bi-cart-check-fill" style="color:#22c55e;font-size:1.1rem;"></i>
  <span id="sf-cart-toast-msg">Added to cart</span>
  <a href="cart.php" style="color:#60a5fa;font-weight:700;margin-left:4px;text-decoration:none;">View Cart</a>
</div>

<footer class="sf-footer mt-5">
  <div class="container py-5">
    <div class="row g-4">
      <div class="col-lg-4">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="sf-footer-logo"><img src="assets/images/Logo.png" alt="SetupForge Logo"></div>
          <h5 class="mb-0 text-white fw-bold">SetupForge</h5>
        </div>
        <p class="sf-footer-text">SetupForge helps entrepreneurs launch, furnish, and fully prepare their businesses.</p>
        <div class="sf-socials mt-3">
          <a href="#">Facebook</a>
          <a href="#">Instagram</a>
          <a href="#">LinkedIn</a>
        </div>
      </div>
      <div class="col-6 col-lg-2">
        <h6 class="sf-footer-title">Products</h6>
        <ul class="sf-footer-links">
          <li><a href="#">Kitchen Equipment</a></li>
          <li><a href="#">Furniture</a></li>
          <li><a href="#">POS Systems</a></li>
          <li><a href="#">Security Systems</a></li>
          <li><a href="#">Packaging</a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-2">
        <h6 class="sf-footer-title">Services</h6>
        <ul class="sf-footer-links">
          <li><a href="#">Installation</a></li>
          <li><a href="#">Interior Design</a></li>
          <li><a href="#">Branding</a></li>
          <li><a href="#">Consultation</a></li>
          <li><a href="#">Maintenance</a></li>
        </ul>
      </div>
      <div class="col-6 col-lg-2">
        <h6 class="sf-footer-title">Resources</h6>
        <ul class="sf-footer-links">
          <li><a href="help-center.php">Help Center</a></li>
          <li><a href="faq.php">FAQ</a></li>
          <li><a href="about.php">About Us</a></li>
          <li><a href="#">Blog</a></li>
          <li><a href="#">Guides</a></li>
        </ul>
      </div>
      <div class="col-12 col-lg-2">
        <h6 class="sf-footer-title">Stay Updated</h6>
        <p class="sf-footer-text small">Get updates, product releases, and startup tips.</p>
        <form>
          <input type="email" class="sf-footer-input mb-2" placeholder="Your email">
          <button type="submit" class="btn btn-light w-100 btn-sm fw-semibold">Subscribe</button>
        </form>
      </div>
    </div>
  </div>
  <div class="sf-footer-bottom">
    <div class="container d-flex justify-content-between flex-wrap gap-2">
      <span>© 2026 SetupForge. All rights reserved.</span>
      <div>
        <a href="#">Privacy Policy</a>
        <a href="#" class="ms-3">Terms</a>
      </div>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/site.js"></script>
<script>
(function () {
  function showToast(msg) {
    const toast = document.getElementById('sf-cart-toast');
    document.getElementById('sf-cart-toast-msg').textContent = msg;
    toast.style.display = 'flex';
    clearTimeout(toast._timer);
    toast._timer = setTimeout(() => { toast.style.display = 'none'; }, 3000);
  }

  function updateBadge(count) {
    let badge = document.querySelector('.sf-navbar-cart-badge');
    const cartLink = document.querySelector('.sf-navbar-cart');
    if (!cartLink) return;
    if (count > 0) {
      if (!badge) {
        badge = document.createElement('span');
        badge.className = 'sf-navbar-cart-badge';
        cartLink.appendChild(badge);
      }
      badge.textContent = count;
    } else {
      if (badge) badge.remove();
    }
  }

  document.querySelectorAll('.sf-add-to-cart-btn').forEach(function (btn) {
    btn.addEventListener('click', function () {
      const productId = btn.dataset.productId;
      const name      = btn.dataset.name;

      btn.disabled = true;
      btn.innerHTML = '<i class="bi bi-hourglass-split"></i> Adding...';

      const fd = new FormData();
      fd.append('product_id', productId);
      fd.append('qty', '1');

      fetch('shop_cart_add.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(data => {
          if (data.ok) {
            btn.classList.add('sf-product-btn-cart--added');
            btn.classList.remove('sf-add-to-cart-btn');
            btn.innerHTML = '<i class="bi bi-check2"></i> In Cart';
            btn.onclick = function () { window.location.href = 'cart.php'; };
            btn.disabled = false;
            updateBadge(data.cart_count);
            showToast(name + ' added to cart');
          } else {
            btn.disabled = false;
            btn.innerHTML = '<i class="bi bi-cart-plus"></i> Add to Cart';
            alert(data.error || 'Could not add to cart.');
          }
        })
        .catch(() => {
          btn.disabled = false;
          btn.innerHTML = '<i class="bi bi-cart-plus"></i> Add to Cart';
        });
    });
  });
})();
</script>
</body>
</html>