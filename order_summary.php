<?php
session_start();

if (!isset($_SESSION["user_id"])) {
  header("Location: auth/login.php?next=" . urlencode("order_summary.php"));
  exit;
}

require_once "db.php";
function egp($n){ return number_format((float)$n, 0) . " EGP"; }

/**
 * UNIVERSAL CART LOADER
 */
if (!isset($_SESSION["carts"]) || !is_array($_SESSION["carts"])) {
  $_SESSION["carts"] = [];
}
if (!empty($_SESSION["wizard"]["pos_cart"]))       $_SESSION["carts"]["pos"]       = $_SESSION["wizard"]["pos_cart"];
if (!empty($_SESSION["wizard"]["kitchen_cart"]))   $_SESSION["carts"]["kitchen"]   = $_SESSION["wizard"]["kitchen_cart"];
if (!empty($_SESSION["wizard"]["furniture_cart"])) $_SESSION["carts"]["furniture"] = $_SESSION["wizard"]["furniture_cart"];

$carts = $_SESSION["carts"];

function cart_items($cart){
  return ($cart && isset($cart["items"]) && is_array($cart["items"])) ? $cart["items"] : [];
}
function cart_total($cart){
  $sum = 0;
  foreach(cart_items($cart) as $it){
    $sum += ((int)($it["qty"] ?? 0)) * ((float)($it["unit"] ?? 0));
  }
  return $sum;
}

// Load product images
$productImages = [];
$imgResult = pg_query($conn, "
  SELECT p.id, pi.image_url
  FROM products p
  LEFT JOIN product_images pi ON pi.product_id = p.id
");
if ($imgResult) {
  while ($row = pg_fetch_assoc($imgResult)) {
    if (!empty($row["image_url"])) {
      $productImages[(int)$row["id"]] = $row["image_url"];
    }
  }
}

$allRows = [];
$grandTotal = 0;

foreach($carts as $module => $cart){
  $items = cart_items($cart);
  if (!$items) continue;
  foreach($items as $type => $it){
    $qty  = (int)($it["qty"] ?? 0);
    $unit = (float)($it["unit"] ?? 0);
    if ($qty <= 0 || $unit <= 0) continue;
    $rowTotal = $qty * $unit;
    $grandTotal += $rowTotal;
    $allRows[] = [
      "module"      => $module,
      "type"        => $type,
      "name"        => (string)($it["name"] ?? ""),
      "vendor_name" => (string)($it["vendor_name"] ?? ""),
      "product_id"  => $it["product_id"] ?? null,
      "qty"         => $qty,
      "unit"        => $unit,
      "total"       => $rowTotal,
    ];
  }
}

$hasAnyItems = ($grandTotal > 0);
$moduleIcons = [
  "pos"         => "🖥",
  "kitchen"     => "🍳",
  "furniture"   => "🪑",
  "ac"          => "❄️",
  "electronics" => "📺",
  "infra"       => "🔌",
];
$moduleLabels = [
  "pos"         => "POS system",
  "kitchen"     => "Kitchen",
  "furniture"   => "Furniture & dining",
  "ac"          => "AC & ambience",
  "electronics" => "Electronics",
  "infra"       => "Infrastructure",
];

$rowsByModule = [];
foreach($allRows as $r){
  $rowsByModule[$r["module"]][] = $r;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SetupForge - Order Summary</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/style.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

<?php include "includes/navbar.php"; ?>


<!-- Hero bar: lighter blue, bigger title -->
<div style="background:#1a6dd4; padding:1.5rem; color:#fff;">
  <h1 style="font-size:26px; font-weight:700; margin-bottom:4px; color:#fff;">Setup review</h1>
  <p style="font-size:13px; color:rgba(255,255,255,0.7); margin:0;">Review your selected items before confirming</p>
  <div style="display:flex; gap:8px; margin-top:12px; flex-wrap:wrap;">
    <?php if(!empty($_SESSION["wizard"]["business_name"])): ?>
      <span style="font-size:11px;padding:3px 10px;border-radius:20px;border:0.5px solid rgba(255,255,255,0.4);color:rgba(255,255,255,0.9);background:rgba(255,255,255,0.12);">
        🏪 <?= htmlspecialchars($_SESSION["wizard"]["business_name"]) ?>
      </span>
    <?php endif; ?>
    <?php if(!empty($_SESSION["wizard"]["restaurant_type"])): ?>
      <span style="font-size:11px;padding:3px 10px;border-radius:20px;border:0.5px solid rgba(255,255,255,0.4);color:rgba(255,255,255,0.9);background:rgba(255,255,255,0.12);">
        🍽 <?= htmlspecialchars($_SESSION["wizard"]["restaurant_type"]) ?>
      </span>
    <?php endif; ?>
    <?php 
      $seats = ((int)($_SESSION["wizard"]["indoor_tables"] ?? 0) + (int)($_SESSION["wizard"]["outdoor_tables"] ?? 0)) * 4;
      if($seats > 0):
    ?>
      <span style="font-size:11px;padding:3px 10px;border-radius:20px;border:0.5px solid rgba(255,255,255,0.4);color:rgba(255,255,255,0.9);background:rgba(255,255,255,0.12);">
        🪑 <?= $seats ?> seats
      </span>
    <?php endif; ?>
    <?php if(!empty($_SESSION["wizard"]["budget_range"])): ?>
      <span style="font-size:11px;padding:3px 10px;border-radius:20px;border:0.5px solid rgba(255,255,255,0.4);color:rgba(255,255,255,0.9);background:rgba(255,255,255,0.12);">
        💰 <?= htmlspecialchars($_SESSION["wizard"]["budget_range"]) ?>
      </span>
    <?php endif; ?>
  </div>
</div>

<main class="container-fluid px-0">
<form id="order-form" action="place_order.php" method="POST">
<div style="display:grid; grid-template-columns:1fr 280px; gap:1.25rem; padding:1.25rem; align-items:start;">
  <?php if(!$hasAnyItems): ?>
    <div style="background:var(--color-background-primary);border:0.5px solid var(--color-border-tertiary);border-radius:12px;padding:2rem;text-align:center;color:var(--color-text-secondary);">
      Your order is empty. <a href="packages.php">Go back to packages</a>.
    </div>
  <?php else: ?>

<!-- Left column: items grouped by module -->
<form id="order-form" action="place_order.php" method="POST" style="display:flex; flex-direction:column; gap:1rem;">


    <?php foreach($rowsByModule as $module => $rows): 
      $modTotal = array_sum(array_column($rows, "total"));
      $icon  = $moduleIcons[$module]  ?? "📦";
      $label = $moduleLabels[$module] ?? ucfirst($module);
    ?>
    <div style="background:var(--color-background-primary);border:0.5px solid var(--color-border-tertiary);border-radius:12px;overflow:hidden;">

      <div style="padding:0.75rem 1rem;border-bottom:0.5px solid var(--color-border-tertiary);display:flex;align-items:center;gap:8px;">
        <div style="width:28px;height:28px;background:#e6f1fb;border-radius:8px;display:flex;align-items:center;justify-content:center;font-size:14px;"><?= $icon ?></div>
        <span style="font-size:13px;font-weight:500;color:var(--color-text-primary);"><?= $label ?></span>
        <span style="margin-left:auto;font-size:11px;color:var(--color-text-secondary);background:var(--color-background-secondary);padding:2px 8px;border-radius:20px;border:0.5px solid var(--color-border-tertiary);"><?= count($rows) ?> item<?= count($rows) > 1 ? "s" : "" ?></span>
      </div>

      <?php foreach($rows as $r):
        $pid    = (int)($r["product_id"] ?? 0);
        $imgUrl = $pid && isset($productImages[$pid]) ? $productImages[$pid] : null;
      ?>
      <div style="display:flex;align-items:center;gap:12px;padding:0.75rem 1rem;border-bottom:0.5px solid var(--color-border-tertiary);">

        <!-- Product image or fallback emoji -->
        <div style="width:44px;height:44px;border-radius:8px;background:var(--color-background-secondary);border:0.5px solid var(--color-border-tertiary);display:flex;align-items:center;justify-content:center;flex-shrink:0;overflow:hidden;">
          <?php if($imgUrl): ?>
            <img src="<?= htmlspecialchars($imgUrl) ?>" alt="" style="width:100%;height:100%;object-fit:cover;">
          <?php else: ?>
            <span style="font-size:20px;">📦</span>
          <?php endif; ?>
        </div>

        <div style="flex:1;min-width:0;">
          <div style="font-size:15px;font-weight:700;color:var(--color-text-primary);"><?= htmlspecialchars($r["name"]) ?></div>
          <?php if(!empty($r["vendor_name"])): ?>
            <div style="font-size:11px;color:var(--color-text-secondary);margin-top:2px;">Vendor: <?= htmlspecialchars($r["vendor_name"]) ?></div>
          <?php endif; ?>
        </div>

        <div style="display:flex;align-items:center;gap:16px;text-align:right;">
          <div>
            <div style="font-size:11px;color:var(--color-text-secondary);">Unit price</div>
            <div style="font-size:15px;font-weight:700;"><?= egp($r["unit"]) ?></div>
          </div>
          <div style="background:var(--color-background-secondary);border:0.5px solid var(--color-border-tertiary);border-radius:8px;padding:2px 10px;font-size:12px;color:var(--color-text-primary);">
            × <?= $r["qty"] ?>
          </div>
          <div>
            <div style="font-size:11px;color:var(--color-text-secondary);">Total</div>
            <div style="font-size:15px;font-weight:700;color:#004cac;"><?= egp($r["total"]) ?></div>
          </div>
        </div>

      </div>
      <?php endforeach; ?>

    </div>
    <?php endforeach; ?>

    <form id="order-form" action="place_order.php" method="POST">
<input type="text" name="delivery_location" style="display:none">
</form>
<div style="background:var(--color-background-primary);border:0.5px solid var(--color-border-tertiary);border-radius:12px;padding:1rem;">
  <label style="font-size:12px;color:var(--color-text-secondary);display:block;margin-bottom:6px;">Delivery location (optional)</label>
  <input type="text" id="delivery_location_visible" style="width:100%;font-size:13px;padding:8px 10px;border:0.5px solid var(--color-border-secondary);border-radius:8px;background:var(--color-background-secondary);color:var(--color-text-primary);outline:none;" placeholder="e.g. Cairo, Nasr City, Street name...">
</div>

  </div>

  <!-- Right column: sticky sidebar -->
  <div style="position:sticky;top:1rem;display:flex;flex-direction:column;gap:1rem;">

    <div style="background:var(--color-background-primary);border:0.5px solid var(--color-border-tertiary);border-radius:12px;overflow:hidden;">
      <div style="background:#004cac;padding:0.75rem 1rem;color:#fff;font-size:13px;font-weight:500;">Order summary</div>
      <div style="padding:0.75rem 1rem;">
        <?php foreach($rowsByModule as $module => $rows):
          $modTotal = array_sum(array_column($rows, "total"));
          $label = $moduleLabels[$module] ?? ucfirst($module);
        ?>
        <div style="display:flex;justify-content:space-between;align-items:center;padding:5px 0;font-size:13px;border-bottom:0.5px solid var(--color-border-tertiary);">
          <span style="color:var(--color-text-secondary);"><?= $label ?></span>
          <span style="font-weight:500;"><?= egp($modTotal) ?></span>
        </div>
        <?php endforeach; ?>
      </div>
      <div style="display:flex;justify-content:space-between;padding:0.75rem 1rem;border-top:0.5px solid var(--color-border-tertiary);background:var(--color-background-secondary);">
        <span style="font-size:13px;color:var(--color-text-secondary);">Grand total</span>
        <span style="font-size:20px;font-weight:700;color:#004cac;"><?= egp($grandTotal) ?></span>
      </div>
    </div>

    <?php 
      $indoorT  = (int)($_SESSION["wizard"]["indoor_tables"]  ?? 0);
      $outdoorT = (int)($_SESSION["wizard"]["outdoor_tables"] ?? 0);
      $seats    = ($indoorT + $outdoorT) * 4;
      $area     = $_SESSION["wizard"]["area_sqm"]       ?? null;
      $budget   = $_SESSION["wizard"]["budget_range"]   ?? null;
      $resType  = $_SESSION["wizard"]["restaurant_type"] ?? null;
    ?>
    <div style="background:var(--color-background-primary);border:0.5px solid var(--color-border-tertiary);border-radius:12px;padding:0.75rem 1rem;">
      <div style="font-size:11px;color:var(--color-text-secondary);text-transform:uppercase;letter-spacing:0.04em;margin-bottom:8px;">Business info</div>
      <?php if($resType): ?>
      <div style="display:flex;justify-content:space-between;font-size:12px;padding:3px 0;">
        <span style="color:var(--color-text-secondary);">Type</span>
        <span style="font-weight:500;"><?= htmlspecialchars($resType) ?></span>
      </div>
      <?php endif; ?>
      <?php if($indoorT): ?>
      <div style="display:flex;justify-content:space-between;font-size:12px;padding:3px 0;">
        <span style="color:var(--color-text-secondary);">Indoor tables</span>
        <span style="font-weight:500;"><?= $indoorT ?></span>
      </div>
      <?php endif; ?>
      <?php if($outdoorT): ?>
      <div style="display:flex;justify-content:space-between;font-size:12px;padding:3px 0;">
        <span style="color:var(--color-text-secondary);">Outdoor tables</span>
        <span style="font-weight:500;"><?= $outdoorT ?></span>
      </div>
      <?php endif; ?>
      <?php if($seats): ?>
      <div style="display:flex;justify-content:space-between;font-size:12px;padding:3px 0;">
        <span style="color:var(--color-text-secondary);">Total seats</span>
        <span style="font-weight:500;"><?= $seats ?></span>
      </div>
      <?php endif; ?>
      <?php if($area): ?>
      <div style="display:flex;justify-content:space-between;font-size:12px;padding:3px 0;">
        <span style="color:var(--color-text-secondary);">Area</span>
        <span style="font-weight:500;"><?= htmlspecialchars($area) ?> m²</span>
      </div>
      <?php endif; ?>
      <?php if($budget): ?>
      <div style="display:flex;justify-content:space-between;font-size:12px;padding:3px 0;">
        <span style="color:var(--color-text-secondary);">Budget</span>
        <span style="font-weight:500;"><?= htmlspecialchars($budget) ?></span>
      </div>
      <?php endif; ?>
    </div>

    <button type="submit" form="order-form" style="width:100%;background:#004cac;color:#fff;border:none;border-radius:8px;padding:10px;font-size:14px;font-weight:500;cursor:pointer;">
      ✓ Confirm & place order
    </button>
    <a href="packages.php" style="width:100%;display:flex;align-items:center;justify-content:center;gap:6px;background:transparent;color:var(--color-text-secondary);border:0.5px solid var(--color-border-secondary);border-radius:8px;padding:8px;font-size:13px;text-decoration:none;">
      ← Back to packages
    </a>

  </div>

  <?php endif; ?>

</div>
</main>
<script>
document.querySelector('button[form="order-form"]').addEventListener('click', function(e) {
  e.preventDefault();
  document.querySelector('input[name="delivery_location"]').value = 
    document.getElementById('delivery_location_visible').value;
  document.getElementById('order-form').submit();
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>