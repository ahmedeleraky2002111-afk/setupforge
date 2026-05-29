<?php
// vendor_products.php
session_start();
require "../db.php";

// ---------- AUTH ----------
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? "") !== "vendor") {
  header("Location: ../auth/login.php?error=" . urlencode("Please login as vendor."));
  exit;
}
$vendorId = (int)$_SESSION["user_id"];

// ---------- HELPERS ----------
function h($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8");
}

function egp($n){
  $n = (float)$n;
  return number_format((int)round($n)) . " EGP";
}

// ---------- HANDLE DELETE ----------
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["delete_product_id"])) {
  $deleteId = (int)$_POST["delete_product_id"];

  // Check which column exists and use it
  $check = pg_query_params(
    $conn,
    "SELECT id FROM products WHERE id = $1 LIMIT 1",
    [$deleteId]
  );

  if ($check && pg_num_rows($check) > 0) {
    pg_query_params($conn, "DELETE FROM products WHERE id = $1", [$deleteId]);
  }

  header("Location: vendor_products.php");
  exit;
}

// ---------- FETCH PRODUCTS (vendor-only + first image + category name) ----------
$products = [];
$productsSource = "none";

// Try with vendor_user_id first
$query1 = pg_query_params(
  $conn,
  "SELECT
      p.id,
      p.product_name,
      p.category_id,
      c.name AS category_name,
      p.brand,
      p.price,
      p.stock_quantity,
      p.created_at,
      (
        SELECT pi.image_url
        FROM product_images pi
        WHERE pi.product_id = p.id
        ORDER BY pi.id ASC
        LIMIT 1
      ) AS image_url
    FROM products p
    LEFT JOIN categories c ON p.category_id = c.id
    WHERE p.vendor_user_id = $1
    ORDER BY p.id DESC
    LIMIT 200",
  [$vendorId]
);

if ($query1) {
  $products = pg_fetch_all($query1);
  if (!$products) {
    $products = [];
  }
  $productsSource = "vendor_user_id";
} else {
  // Fallback: try vendor_id instead
  $query2 = pg_query_params(
    $conn,
    "SELECT
        p.id,
        p.product_name,
        p.category_id,
        c.name AS category_name,
        p.brand,
        p.price,
        p.stock_quantity,
        p.created_at,
        (
          SELECT pi.image_url
          FROM product_images pi
          WHERE pi.product_id = p.id
          ORDER BY pi.id ASC
          LIMIT 1
        ) AS image_url
      FROM products p
      LEFT JOIN categories c ON p.category_id = c.id
      WHERE p.vendor_id = $1
      ORDER BY p.id DESC
      LIMIT 200",
    [$vendorId]
  );

  if ($query2) {
    $products = pg_fetch_all($query2);
    if (!$products) {
      $products = [];
    }
    $productsSource = "vendor_id";
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>My Products — SetupForge Vendor</title>

  <link rel="stylesheet" href="vendor_ui.css?v=<?= time() ?>" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark sf-navbar">
  <div class="container d-flex align-items-center">

    <div class="d-flex align-items-center flex-grow-1">
      <a class="navbar-brand d-flex align-items-center gap-2" href="vendor_dashboard.php">
        <div class="sf-logo"><img src="../assets/images/Logo.png" alt="SetupForge Logo"></div>
        <span class="fw-bold text-white">SetupForge</span>
      </a>
    </div>

    <div class="d-none d-lg-flex justify-content-center flex-grow-1">
      <ul class="navbar-nav align-items-center gap-3">
        <li class="nav-item">
          <a class="nav-link sf-navlink" href="vendor_orders.php">Orders</a>
        </li>
        <li class="nav-item">
          <a class="nav-link sf-navlink" href="vendor_products.php">My Products</a>
        </li>
        <li class="nav-item">
          <a class="nav-link sf-navlink" href="vendor_add_product.php">Add Product</a>
        </li>
      </ul>
    </div>

    <div class="d-flex justify-content-end flex-grow-1 gap-2">
      <a href="../auth/logout.php" class="btn btn-outline-light btn-sm px-3 fw-semibold">Logout</a>
    </div>

  </div>
</nav>

<div class="v-wrap">

  <div class="v-head">
    <div>
      <h1 class="v-title">My Products</h1>
      <div class="v-sub">Manage your listed products.</div>
    </div>

    <div class="v-actions">
      <a class="v-btn v-btn-primary" href="vendor_add_product.php">Add Product</a>
    </div>
  </div>

  <div class="v-section">
    <div class="v-card-head">
      <div>
        <div class="v-card-title">Product List</div>
        <div class="v-card-sub">Showing up to 200 products.</div>
      </div>
    </div>

    <?php if (empty($products)): ?>
      <div class="v-empty">No products found yet. Click "Add Product" to create your first product.</div>
    <?php else: ?>
      <div class="v-list">
        <?php foreach ($products as $p): ?>
          <?php
          $img = trim((string)($p["image_url"] ?? ""));
if ($img !== "" && strpos($img, "assets/") === 0) {
    $img = "../" . $img;
}
            $name  = (string)($p["product_name"] ?? "Product");
            $cat   = (string)($p["category_name"] ?? "—");
            $brand = (string)($p["brand"] ?? "—");
            $cond  = (string)($p["condition"] ?? "—");
            $price = (float)($p["price"] ?? 0);
            $stock = (int)($p["stock_quantity"] ?? 0);
            $pid   = (int)($p["id"] ?? 0);
          ?>
          <div class="v-row">
            <div class="v-row-left">
              <div class="v-thumb">
                <?php if ($img !== ""): ?>
                  <img src="<?= h($img) ?>" alt="<?= h($name) ?>">
                <?php else: ?>
                  <div class="v-thumb-placeholder">No Image</div>
                <?php endif; ?>
              </div>

              <div class="v-row-info">
                <div class="v-row-title"><?= h($name) ?></div>
                <div class="v-row-meta">
                  <span class="v-chip">Category: <?= h($cat) ?></span>
                  <span class="v-chip">Brand: <?= h($brand) ?></span>
                  <span class="v-chip">Condition: <?= h($cond) ?></span>
                  <span class="v-chip">Stock: <?= (int)$stock ?></span>
                  <span class="v-chip">Price: <?= egp($price) ?></span>
                </div>
              </div>
            </div>

            <div class="v-row-right">
              <a class="v-btn v-btn-outline" href="vendor_edit_product.php?id=<?= urlencode((string)$pid) ?>">Edit</a>
              <a class="v-btn v-btn-blue" href="vendor_product_details.php?id=<?= urlencode((string)$pid) ?>">Details</a>
              <form method="POST" action="vendor_products.php" style="display:inline;"
                    onsubmit="return confirm('Delete &quot;<?= h(addslashes($name)) ?>&quot;? This cannot be undone.');">
                <input type="hidden" name="delete_product_id" value="<?= $pid ?>">
                <button type="submit" class="v-btn v-btn-danger">Delete</button>
              </form>
            </div>
          </div>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>

  </div>

</div>

</body>
</html>
