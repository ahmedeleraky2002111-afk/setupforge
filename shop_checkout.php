<?php
session_start();
require_once "db.php";

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function egp($n) { return number_format((float)$n, 0) . ' EGP'; }

$userType = $_SESSION["user_type"] ?? "guest";
$showCart = !in_array($userType, ["vendor", "labor", "company"], true);
if (!$showCart) { header("Location: home.php"); exit; }

// Guest redirect to login, preserving cart
if (!isset($_SESSION["user_id"])) {
    header("Location: auth/login.php?next=" . urlencode("shop_checkout.php"));
    exit;
}

$userId = (int)$_SESSION["user_id"];
// Handle Buy Now — single product bypass
if (!empty($_POST["buy_now"]) && !empty($_POST["buy_now_product_id"])) {
    $buyNowPid = (int)$_POST["buy_now_product_id"];
    $buyNowQty = max(1, (int)($_POST["buy_now_qty"] ?? 1));
    $_SESSION["buy_now"] = [
        "product_id" => $buyNowPid,
        "qty"        => $buyNowQty,
    ];
    // Redirect GET to avoid form resubmission
    header("Location: shop_checkout.php?buy_now=1");
    exit;
}

// If arriving via Buy Now GET redirect, use buy_now session
$isBuyNow = !empty($_GET["buy_now"]) && !empty($_SESSION["buy_now"]);
$cart = $isBuyNow
    ? [(string)$_SESSION["buy_now"]["product_id"] => ["qty" => $_SESSION["buy_now"]["qty"]]]
    : ($_SESSION["shop_cart"] ?? []);

if (empty($cart)) {
    header("Location: cart.php");
    exit;
}

// Load products from DB to get accurate prices
$ids    = array_map('intval', array_keys($cart));
$idList = implode(',', $ids);

$res = pg_query($conn, "
    SELECT p.id, p.product_name, p.price, p.stock_quantity, p.brand, p.vendor_user_id,
           u.name AS vendor_name,
           (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) AS image_url
    FROM products p
    LEFT JOIN users u ON u.id = p.vendor_user_id
    WHERE p.id IN ($idList)
");

$dbProducts = [];
if ($res) {
    while ($row = pg_fetch_assoc($res)) $dbProducts[(int)$row["id"]] = $row;
}

$items      = [];
$grandTotal = 0;

foreach ($cart as $key => $cartItem) {
    $pid = (int)$key;
    if (!isset($dbProducts[$pid])) continue;
    $p       = $dbProducts[$pid];
    $qty     = (int)$cartItem["qty"];
    $price   = (float)$p["price"];
    $total   = $qty * $price;
    $grandTotal += $total;
    $items[] = [
        "product_id"  => $pid,
        "name"        => $p["product_name"],
        "brand"       => $p["brand"] ?? "",
        "vendor_name" => $p["vendor_name"] ?? "",
        "price"       => $price,
        "qty"         => $qty,
        "total"       => $total,
        "image_url"   => $p["image_url"] ?? null,
        "vendor_user_id" => $p["vendor_user_id"] ?? null,
    ];
}

if (empty($items)) {
    header("Location: cart.php");
    exit;
}

// Load user info for pre-fill
$userRes = pg_query_params($conn,
    "SELECT name, email, phone, city, country, street FROM users WHERE id = \$1 LIMIT 1",
    [$userId]);
$user = ($userRes && pg_num_rows($userRes) > 0) ? pg_fetch_assoc($userRes) : [];
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Checkout - SetupForge</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="assets/style.css?v=20" rel="stylesheet">
  <style>
    * { border-radius: 0 !important; }
    body { background: #f8fafc; }
    .sf-co-wrap { max-width: 1060px; margin: 0 auto; padding: 40px 16px 80px; }
    .sf-co-title { font-size: 1.5rem; font-weight: 800; color: #111827; margin-bottom: 28px; }

    .sf-co-layout {
      display: grid;
      grid-template-columns: 1fr 320px;
      gap: 28px;
      align-items: start;
    }
    @media (max-width: 767px) {
      .sf-co-layout { grid-template-columns: 1fr; }
    }

    .sf-co-card {
      background: #fff;
      border-radius: 5px;
      border: 1.5px solid #e0eaff;
      padding: 28px;
      margin-bottom: 20px;
    }
    .sf-co-card-title {
      font-size: .95rem;
      font-weight: 800;
      color: #111827;
      margin-bottom: 18px;
      padding-bottom: 12px;
      border-bottom: 1.5px solid #f1f5f9;
    }

    .sf-co-field { margin-bottom: 16px; }
    .sf-co-field label {
      display: block;
      font-size: .82rem;
      font-weight: 700;
      color: #374151;
      margin-bottom: 6px;
    }
    .sf-co-field input,
    .sf-co-field textarea {
      width: 100%;
      padding: 10px 12px;
      border: 1.5px solid #e0eaff;
      border-radius: 5px;
      font-size: .9rem;
      color: #111827;
      background: #f8fafc;
      outline: none;
      transition: border-color .15s;
      font-family: inherit;
    }
    .sf-co-field input:focus,
    .sf-co-field textarea:focus {
      border-color: #004cac;
      background: #fff;
    }

    /* Order summary sidebar */
    .sf-co-summary {
      background: #fff;
      border-radius: 5px;
      border: 1.5px solid #e0eaff;
      overflow: hidden;
      position: sticky;
      top: 20px;
    }
    .sf-co-summary-head {
      background: #004cac;
      padding: 16px 20px;
      color: #fff;
      font-size: .88rem;
      font-weight: 700;
    }
    .sf-co-summary-body { padding: 16px 20px; }
    .sf-co-summary-item {
      display: flex;
      align-items: center;
      gap: 12px;
      padding: 10px 0;
      border-bottom: 1px solid #f1f5f9;
    }
    .sf-co-summary-item:last-child { border-bottom: none; }
    .sf-co-summary-img {
      width: 48px; height: 48px;
      border-radius: 5px;
      object-fit: cover;
      flex-shrink: 0;
      background: #f1f5f9;
      display: flex; align-items: center; justify-content: center;
      overflow: hidden;
    }
    .sf-co-summary-img img { width: 100%; height: 100%; object-fit: cover; }
    .sf-co-summary-name { font-size: .82rem; font-weight: 700; color: #111827; flex: 1; min-width: 0; }
    .sf-co-summary-qty { font-size: .75rem; color: #6b7280; }
    .sf-co-summary-price { font-size: .88rem; font-weight: 700; color: #004cac; flex-shrink: 0; }

    .sf-co-total-row {
      display: flex; justify-content: space-between; align-items: center;
      padding: 16px 20px;
      border-top: 2px solid #e0eaff;
      background: #f8fafc;
    }
    .sf-co-total-label { font-size: .88rem; font-weight: 600; color: #6b7280; }
    .sf-co-total-val { font-size: 1.25rem; font-weight: 800; color: #004cac; }

    .sf-co-place-btn {
      display: block;
      width: 100%;
      padding: 14px;
      background: #004cac;
      color: #fff;
      border: none;
      border-radius: 5px;
      font-size: .95rem;
      font-weight: 700;
      cursor: pointer;
      text-align: center;
      margin: 16px 20px 20px;
      width: calc(100% - 40px);
      transition: background .15s;
    }
    .sf-co-place-btn:hover { background: #003a82; }
  </style>
</head>
<body>

<?php include "includes/navbar.php"; ?>

<main>
  <div class="sf-co-wrap">
    <h1 class="sf-co-title">
      <i class="bi bi-bag-check me-2" style="color:#004cac"></i>Checkout
    </h1>

    <form method="POST" action="shop_place_order.php" id="checkout-form">

      <div class="sf-co-layout">

        <!-- LEFT: Delivery + Contact -->
        <div>

          <div class="sf-co-card">
            <div class="sf-co-card-title"><i class="bi bi-geo-alt me-2" style="color:#004cac"></i>Delivery Information</div>

            <div class="sf-co-field">
              <label>Full Name *</label>
              <input type="text" name="delivery_name" required
                value="<?= h($user["name"] ?? "") ?>"
                placeholder="Your full name">
            </div>

            <div class="sf-co-field">
              <label>Phone Number *</label>
              <input type="tel" name="delivery_phone" required
                value="<?= h($user["phone"] ?? "") ?>"
                placeholder="+20 ...">
            </div>

            <div class="sf-co-field">
              <label>Delivery Address *</label>
              <textarea name="delivery_location" rows="2" required
                placeholder="Street, district, city..."><?= h(trim(($user["street"] ?? "") . " " . ($user["city"] ?? "") . " " . ($user["country"] ?? ""))) ?></textarea>
            </div>

            <div class="sf-co-field">
              <label>Order Notes (optional)</label>
              <textarea name="order_notes" rows="2" placeholder="Any special instructions..."></textarea>
            </div>
          </div>

        </div>

        <!-- RIGHT: Summary -->
        <div>
          <div class="sf-co-summary">
            <div class="sf-co-summary-head">
              <i class="bi bi-receipt me-2"></i>Order Summary (<?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?>)
            </div>
            <div class="sf-co-summary-body">
              <?php foreach ($items as $item): ?>
              <div class="sf-co-summary-item">
                <div class="sf-co-summary-img">
                  <?php if ($item["image_url"]): ?>
                    <img src="<?= h($item["image_url"]) ?>" alt="">
                  <?php else: ?>
                    <i class="bi bi-box-seam" style="color:#9ca3af"></i>
                  <?php endif; ?>
                </div>
                <div class="flex-fill min-w-0">
                  <div class="sf-co-summary-name"><?= h($item["name"]) ?></div>
                  <div class="sf-co-summary-qty">Qty: <?= $item["qty"] ?> × <?= egp($item["price"]) ?></div>
                </div>
                <div class="sf-co-summary-price"><?= egp($item["total"]) ?></div>
              </div>
              <?php endforeach; ?>
            </div>
            <div class="sf-co-total-row">
              <span class="sf-co-total-label">Total</span>
              <span class="sf-co-total-val"><?= egp($grandTotal) ?></span>
            </div>
            <button type="submit" class="sf-co-place-btn">
              <i class="bi bi-lock-fill me-2"></i>Place Order & Pay
            </button>
          </div>

          <a href="cart.php" style="display:block;text-align:center;margin-top:12px;font-size:.82rem;color:#6b7280;text-decoration:none;">
            ← Back to Cart
          </a>
        </div>

      </div>
    </form>
  </div>
</main>

<footer class="sf-footer mt-5">
  <div class="container py-5">
    <div class="row g-4">
      <div class="col-lg-4">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="sf-footer-logo"><img src="assets/images/Logo.png" alt="SetupForge Logo"></div>
          <h5 class="mb-0 text-white fw-bold">SetupForge</h5>
        </div>
        <p class="sf-footer-text">SetupForge helps entrepreneurs launch, furnish, and fully prepare their businesses.</p>
      </div>
    </div>
  </div>
  <div class="sf-footer-bottom">
    <div class="container d-flex justify-content-between flex-wrap gap-2">
      <span>© 2026 SetupForge. All rights reserved.</span>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>