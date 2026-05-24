<?php
session_start();
require_once "db.php";

function h($v) { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function egp($n) { return number_format((float)$n, 0) . ' EGP'; }

$userType = $_SESSION["user_type"] ?? "guest";
$showCart = !in_array($userType, ["vendor", "labor", "company"], true);
if (!$showCart) { header("Location: home.php"); exit; }

$cart  = $_SESSION["shop_cart"] ?? [];
$items = [];
$grandTotal = 0;

if (!empty($cart)) {
    $ids = array_map('intval', array_keys($cart));
    $idList = implode(',', $ids);

    $res = pg_query($conn, "
        SELECT p.id, p.product_name, p.price, p.stock_quantity, p.brand,
               u.name AS vendor_name,
               (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) AS image_url
        FROM products p
        LEFT JOIN users u ON u.id = p.vendor_user_id
        WHERE p.id IN ($idList)
    ");

    $dbProducts = [];
    if ($res) {
        while ($row = pg_fetch_assoc($res)) {
            $dbProducts[(int)$row["id"]] = $row;
        }
    }

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
            "stock"       => (int)$p["stock_quantity"],
        ];
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Cart - SetupForge</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="assets/style.css?v=20" rel="stylesheet">
  <style>
    body { background: #f8fafc; }

    .sf-cart-wrap { max-width: 1100px; margin: 0 auto; padding: 40px 16px 80px; }
    .sf-cart-title { font-size: 1.6rem; font-weight: 800; color: #111827; margin-bottom: 28px; }

    .sf-cart-layout {
      display: grid;
      grid-template-columns: 1fr 300px;
      gap: 24px;
      align-items: start;
    }
    @media (max-width: 767px) {
      .sf-cart-layout { grid-template-columns: 1fr; }
    }

    /* Cart items card */
    .sf-cart-card {
      background: #fff;
      border-radius: 5px;
      border: 1.5px solid #e0eaff;
      overflow: hidden;
    }
    .sf-cart-item {
      display: flex;
      align-items: center;
      gap: 16px;
      padding: 20px;
      border-bottom: 1px solid #f1f5f9;
    }
    .sf-cart-item:last-child { border-bottom: none; }
    .sf-cart-item-img {
      width: 80px; height: 80px;
      border-radius: 5px;
      object-fit: cover;
      flex-shrink: 0;
      background: #f1f5f9;
      display: flex; align-items: center; justify-content: center;
      overflow: hidden;
    }
    .sf-cart-item-img img { width: 100%; height: 100%; object-fit: cover; }
    .sf-cart-item-info { flex: 1; min-width: 0; }
    .sf-cart-item-name { font-size: .95rem; font-weight: 700; color: #111827; margin-bottom: 3px; }
    .sf-cart-item-meta { font-size: .78rem; color: #6b7280; margin-bottom: 8px; }
    .sf-cart-item-price { font-size: 1rem; font-weight: 800; color: #004cac; }

    /* Qty controls */
    .sf-cart-qty {
      display: flex;
      align-items: center;
      gap: 0;
      border: 1.5px solid #e0eaff;
      border-radius: 5px;
      overflow: hidden;
      flex-shrink: 0;
    }
    .sf-cart-qty-btn {
      width: 32px; height: 32px;
      background: #f8fafc;
      border: none;
      font-size: 1rem;
      font-weight: 700;
      color: #374151;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      transition: background .12s;
    }
    .sf-cart-qty-btn:hover { background: #e0eaff; }
    .sf-cart-qty-val {
      width: 36px;
      text-align: center;
      font-size: .9rem;
      font-weight: 700;
      color: #111827;
      border-left: 1.5px solid #e0eaff;
      border-right: 1.5px solid #e0eaff;
      line-height: 32px;
    }

    /* Remove btn */
    .sf-cart-remove-btn {
      background: none; border: none;
      color: #9ca3af; font-size: 1.1rem;
      cursor: pointer; padding: 4px;
      transition: color .12s;
      flex-shrink: 0;
    }
    .sf-cart-remove-btn:hover { color: #dc2626; }

    /* Line total */
    .sf-cart-line-total {
      font-size: .9rem; font-weight: 700;
      color: #111827; min-width: 90px;
      text-align: right; flex-shrink: 0;
    }

    /* Summary sidebar */
    .sf-cart-summary {
      background: #004cac;
      border-radius: 5px;
      padding: 24px;
      color: #fff;
      position: sticky;
      top: 20px;
    }
    .sf-cart-summary-title { font-size: .9rem; font-weight: 700; color: rgba(255,255,255,.7); margin-bottom: 16px; }
    .sf-cart-summary-row {
      display: flex; justify-content: space-between;
      font-size: .88rem; padding: 6px 0;
      border-bottom: 1px solid rgba(255,255,255,.12);
      color: rgba(255,255,255,.85);
    }
    .sf-cart-summary-total {
      display: flex; justify-content: space-between; align-items: center;
      padding-top: 14px; margin-top: 4px;
    }
    .sf-cart-summary-total-label { font-size: .9rem; color: rgba(255,255,255,.8); font-weight: 600; }
    .sf-cart-summary-total-val { font-size: 1.4rem; font-weight: 800; color: #fff; }
    .sf-cart-checkout-btn {
      display: block; width: 100%;
      margin-top: 18px;
      padding: 13px;
      background: #fff;
      color: #004cac;
      border: none;
      border-radius: 5px;
      font-size: .95rem;
      font-weight: 700;
      text-align: center;
      text-decoration: none;
      cursor: pointer;
      transition: background .15s;
    }
    .sf-cart-checkout-btn:hover { background: #e0eaff; color: #004cac; }

    /* Empty cart */
    .sf-cart-empty {
      text-align: center;
      padding: 60px 20px;
      color: #9ca3af;
    }
    .sf-cart-empty i { font-size: 3rem; margin-bottom: 16px; display: block; }
    .sf-cart-empty h3 { font-size: 1.2rem; font-weight: 700; color: #374151; margin-bottom: 8px; }
    .sf-cart-empty p { font-size: .9rem; margin-bottom: 20px; }
  </style>
</head>
<body>

<?php include "includes/navbar.php"; ?>

<main>
  <div class="sf-cart-wrap">
    <h1 class="sf-cart-title">
      <i class="bi bi-cart3 me-2" style="color:#004cac"></i>
      Your Cart
      <?php if (!empty($items)): ?>
        <span style="font-size:1rem;font-weight:600;color:#6b7280;margin-left:8px;">(<?= count($items) ?> item<?= count($items) !== 1 ? 's' : '' ?>)</span>
      <?php endif; ?>
    </h1>

    <?php if (empty($items)): ?>
      <div class="sf-cart-card">
        <div class="sf-cart-empty">
          <i class="bi bi-cart-x"></i>
          <h3>Your cart is empty</h3>
          <p>Browse our products and add items to get started.</p>
          <a href="products.php" style="display:inline-block;padding:11px 28px;background:#004cac;color:#fff;border-radius:5px;font-weight:700;text-decoration:none;">
            Browse Products
          </a>
        </div>
      </div>
    <?php else: ?>
      <div class="sf-cart-layout">

        <!-- Items -->
        <div class="sf-cart-card">
          <?php foreach ($items as $item): ?>
          <div class="sf-cart-item" id="cart-row-<?= $item['product_id'] ?>">

            <!-- Image -->
            <div class="sf-cart-item-img">
              <?php if ($item["image_url"]): ?>
                <img src="<?= h($item["image_url"]) ?>" alt="">
              <?php else: ?>
                <i class="bi bi-box-seam" style="font-size:1.5rem;color:#9ca3af"></i>
              <?php endif; ?>
            </div>

            <!-- Info -->
            <div class="sf-cart-item-info">
              <div class="sf-cart-item-name"><?= h($item["name"]) ?></div>
              <div class="sf-cart-item-meta">
                <?php if ($item["brand"]): ?><?= h($item["brand"]) ?><?php endif; ?>
                <?php if ($item["vendor_name"]): ?> · <?= h($item["vendor_name"]) ?><?php endif; ?>
              </div>
              <div class="sf-cart-item-price"><?= egp($item["price"]) ?> each</div>
            </div>

            <!-- Qty -->
            <div class="sf-cart-qty">
              <button class="sf-cart-qty-btn" onclick="updateQty(<?= $item['product_id'] ?>, -1)">−</button>
              <span class="sf-cart-qty-val" id="qty-<?= $item['product_id'] ?>"><?= $item["qty"] ?></span>
              <button class="sf-cart-qty-btn" onclick="updateQty(<?= $item['product_id'] ?>, 1)">+</button>
            </div>

            <!-- Line total -->
            <div class="sf-cart-line-total" id="line-<?= $item['product_id'] ?>">
              <?= egp($item["total"]) ?>
            </div>

            <!-- Remove -->
            <button class="sf-cart-remove-btn" onclick="removeItem(<?= $item['product_id'] ?>)" title="Remove">
              <i class="bi bi-trash3"></i>
            </button>

          </div>
          <?php endforeach; ?>
        </div>

        <!-- Summary -->
        <div class="sf-cart-summary">
          <div class="sf-cart-summary-title">ORDER SUMMARY</div>
          <?php foreach ($items as $item): ?>
            <div class="sf-cart-summary-row" id="summary-row-<?= $item['product_id'] ?>">
              <span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap;max-width:160px;"><?= h($item["name"]) ?></span>
              <span id="summary-total-<?= $item['product_id'] ?>"><?= egp($item["total"]) ?></span>
            </div>
          <?php endforeach; ?>
          <div class="sf-cart-summary-total">
            <span class="sf-cart-summary-total-label">Total</span>
            <span class="sf-cart-summary-total-val" id="grand-total"><?= egp($grandTotal) ?></span>
          </div>
          <a href="shop_checkout.php" class="sf-cart-checkout-btn">
            Proceed to Checkout →
          </a>
          <a href="products.php" style="display:block;text-align:center;margin-top:12px;font-size:.82rem;color:rgba(255,255,255,.65);text-decoration:none;">
            ← Continue Shopping
          </a>
        </div>

      </div>
    <?php endif; ?>
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
        <div class="sf-socials mt-3"><a href="#">Facebook</a><a href="#">Instagram</a><a href="#">LinkedIn</a></div>
      </div>
      <div class="col-6 col-lg-2">
        <h6 class="sf-footer-title">Products</h6>
        <ul class="sf-footer-links"><li><a href="#">Kitchen Equipment</a></li><li><a href="#">Furniture</a></li><li><a href="#">POS Systems</a></li></ul>
      </div>
      <div class="col-6 col-lg-2">
        <h6 class="sf-footer-title">Resources</h6>
        <ul class="sf-footer-links"><li><a href="help-center.php">Help Center</a></li><li><a href="faq.php">FAQ</a></li><li><a href="about.php">About Us</a></li></ul>
      </div>
    </div>
  </div>
  <div class="sf-footer-bottom">
    <div class="container d-flex justify-content-between flex-wrap gap-2">
      <span>© 2026 SetupForge. All rights reserved.</span>
      <div><a href="#">Privacy Policy</a><a href="#" class="ms-3">Terms</a></div>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
// prices map for client-side total calculation
const prices = {
  <?php foreach ($items as $item): ?>
  <?= $item['product_id'] ?>: <?= $item['price'] ?>,
  <?php endforeach; ?>
};
const qtys = {
  <?php foreach ($items as $item): ?>
  <?= $item['product_id'] ?>: <?= $item['qty'] ?>,
  <?php endforeach; ?>
};

function fmt(n) {
  return Math.round(n).toLocaleString('en-EG') + ' EGP';
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

function recalcGrandTotal() {
  let total = 0;
  for (const [pid, qty] of Object.entries(qtys)) {
    if (qty > 0) total += qty * (prices[pid] || 0);
  }
  document.getElementById('grand-total').textContent = fmt(total);
}

function updateQty(pid, delta) {
  const current = qtys[pid] || 1;
  const newQty  = Math.max(1, current + delta);
  qtys[pid] = newQty;

  document.getElementById('qty-' + pid).textContent = newQty;
  const lineTotal = newQty * prices[pid];
  document.getElementById('line-' + pid).textContent = fmt(lineTotal);
  document.getElementById('summary-total-' + pid).textContent = fmt(lineTotal);
  recalcGrandTotal();

  const fd = new FormData();
  fd.append('product_id', pid);
  fd.append('qty', newQty);
  fetch('shop_cart_update.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => { if (data.ok) updateBadge(data.cart_count); });
}

function removeItem(pid) {
  const row = document.getElementById('cart-row-' + pid);
  const summaryRow = document.getElementById('summary-row-' + pid);
  if (row) row.remove();
  if (summaryRow) summaryRow.remove();
  delete qtys[pid];
  delete prices[pid];
  recalcGrandTotal();

  const fd = new FormData();
  fd.append('product_id', pid);
  fetch('shop_cart_remove.php', { method: 'POST', body: fd })
    .then(r => r.json())
    .then(data => {
      if (data.ok) {
        updateBadge(data.cart_count);
        // If cart is now empty, reload to show empty state
        if (data.cart_count === 0) window.location.reload();
      }
    });
}
</script>
</body>
</html>