<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: auth/login.php");
    exit();
}

$business_id = (int)$_SESSION["user_id"];

function h($v){ return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function egp($n){ return number_format((float)$n, 0) . " EGP"; }

/* ── Load latest paid order ─────────────────────────────── */
$orderRes = pg_query_params($conn, "
    SELECT id, order_total FROM orders
    WHERE business_user_id = \$1
    AND payment_status = 'paid'
    AND order_type = 'setup'
    ORDER BY id DESC LIMIT 1
", [$business_id]);

if (!$orderRes || pg_num_rows($orderRes) === 0) {
    header("Location: business_overview.php");
    exit();
}

$order        = pg_fetch_assoc($orderRes);
$order_id     = (int)$order["id"];
$original_total = (float)$order["order_total"];

/* ── Load business name ─────────────────────────────────── */
$bizRes = pg_query_params($conn,
    "SELECT business_name FROM businesses WHERE user_id = \$1 LIMIT 1",
    [$business_id]
);
$businessName = "Your Business";
if ($bizRes && pg_num_rows($bizRes) > 0) {
    $bRow = pg_fetch_assoc($bizRes);
    $businessName = $bRow["business_name"] ?: $businessName;
}

/* ── Handle swap (replace product in order) ─────────────── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "swap_item") {
    $item_id        = (int)($_POST["item_id"] ?? 0);
    $new_product_id = (int)($_POST["new_product_id"] ?? 0);
    $mod            = $_POST["module"] ?? "";
    if ($item_id > 0 && $new_product_id > 0) {
        $priceRes = pg_query_params($conn,
            "SELECT price FROM products WHERE id = \$1 LIMIT 1", [$new_product_id]);
        if ($priceRes && pg_num_rows($priceRes) > 0) {
            $newPrice = (float)pg_fetch_assoc($priceRes)["price"];
            pg_query_params($conn,
                "UPDATE order_items SET product_id = \$1, unit_price = \$2 WHERE id = \$3 AND order_id = \$4",
                [$new_product_id, $newPrice, $item_id, $order_id]
            );
        }
    }
    header("Location: edit_setup.php?module=" . urlencode($mod));
    exit();
}

/* ── Handle Save Changes ────────────────────────────────── */
if ($_SERVER["REQUEST_METHOD"] === "POST" && ($_POST["action"] ?? "") === "save_changes") {
    $quantities = $_POST["qty"] ?? [];
    pg_query($conn, "BEGIN");
    try {
        foreach ($quantities as $item_id => $qty) {
            $item_id = (int)$item_id;
            $qty     = (int)$qty;
            $check = pg_query_params($conn,
                "SELECT id FROM order_items WHERE id = \$1 AND order_id = \$2",
                [$item_id, $order_id]);
            if (!$check || pg_num_rows($check) === 0) continue;
            if ($qty <= 0) {
                pg_query_params($conn, "DELETE FROM order_items WHERE id = \$1", [$item_id]);
            } else {
                pg_query_params($conn,
                    "UPDATE order_items SET quantity = \$1 WHERE id = \$2", [$qty, $item_id]);
            }
        }
        $totalRes = pg_query_params($conn,
            "SELECT COALESCE(SUM(quantity * unit_price), 0) AS new_total FROM order_items WHERE order_id = \$1",
            [$order_id]);
        $newTotal = (float)pg_fetch_assoc($totalRes)["new_total"];
        pg_query_params($conn, "UPDATE orders SET order_total = \$1 WHERE id = \$2",
            [$newTotal, $order_id]);
        pg_query($conn, "COMMIT");

        if ($newTotal > $original_total) {
            $diff = (int)round(($newTotal - $original_total) * 100);
            header("Location: payments/paymob_checkout.php?order_id={$order_id}&mode=diff&amount_cents={$diff}");
        } else {
            header("Location: business_overview.php");
        }
        exit();
    } catch (Throwable $e) {
        pg_query($conn, "ROLLBACK");
        $saveError = $e->getMessage();
    }
}

/* ── Load order items ───────────────────────────────────── */
$itemsRes = pg_query_params($conn, "
    SELECT oi.id AS item_id, oi.product_id, oi.quantity, oi.unit_price,
           p.product_name, p.brand, p.module, p.product_type, p.product_group_key,
           p.vendor_user_id,
           u.name AS vendor_name,
           pi.image_url
    FROM order_items oi
    JOIN products p ON p.id = oi.product_id
    LEFT JOIN users u ON u.id = p.vendor_user_id
    LEFT JOIN LATERAL (
        SELECT image_url FROM product_images
        WHERE product_id = oi.product_id ORDER BY id LIMIT 1
    ) pi ON true
    WHERE oi.order_id = \$1
    ORDER BY p.module, p.product_name
", [$order_id]);

$moduleOrder  = ['kitchen','pos','furniture','ac','electronics'];
$moduleLabels = [
    'kitchen'     => 'Kitchen / Equipment',
    'pos'         => 'POS & Operations',
    'furniture'   => 'Dining Area',
    'ac'          => 'Ambience & AC',
    'electronics' => 'Electronics',
];
$sectionLabelsAll = [
    'kitchen'     => ['oven'=>'Ovens','fryer'=>'Fryers','grill'=>'Grills','fridge'=>'Display Fridges','freezer'=>'Freezers','blender'=>'Blenders','mixer'=>'Mixers','coffee'=>'Coffee Machines','microwave'=>'Microwaves','stove'=>'Stoves'],
    'pos'         => ['terminal'=>'POS Terminals','printer'=>'Receipt Printers','drawer'=>'Cash Drawers','software'=>'POS Software','scanner'=>'Barcode Scanners','kds'=>'Kitchen Display Screens','tablet'=>'Ordering Tablets'],
    'furniture'   => ['dining_set'=>'Dining Sets','chair'=>'Chairs','table'=>'Tables','tv'=>'TVs','sofa'=>'Sofas','bar_stool'=>'Bar Stools'],
    'ac'          => ['ac'=>'AC Units','exhaust_fan'=>'Exhaust Fans','air_curtain'=>'Air Curtains'],
    'electronics' => ['tv'=>'TVs','tablet'=>'Tablets','laptop'=>'Laptops','camera'=>'Cameras'],
];

$byModule = [];
if ($itemsRes) {
    while ($row = pg_fetch_assoc($itemsRes)) {
        $mod  = strtolower($row["module"] ?: "other");
        $type = strtolower($row["product_type"] ?: "item");

        // Load alternatives
        $altsRes = pg_query_params($conn, "
            SELECT p2.id, p2.product_name AS name, p2.brand, p2.price,
                   p2.product_group_key, p2.vendor_user_id,
                   u2.name AS vendor_name,
                   pi2.image_url
            FROM products p2
            LEFT JOIN users u2 ON u2.id = p2.vendor_user_id
            LEFT JOIN LATERAL (
                SELECT image_url FROM product_images WHERE product_id = p2.id ORDER BY id LIMIT 1
            ) pi2 ON true
            WHERE p2.module = \$1
              AND p2.product_type = \$2
              AND p2.id != \$3
            ORDER BY p2.price ASC
            LIMIT 3
        ", [$row["module"], $row["product_type"], $row["product_id"]]);

        $alts = [];
        if ($altsRes) while ($a = pg_fetch_assoc($altsRes)) $alts[] = $a;

        $row["alternatives"] = $alts;
        $byModule[$mod][$type] = $row;
    }
}

$sortedModules = [];
foreach ($moduleOrder as $m) {
    if (isset($byModule[$m])) $sortedModules[$m] = $byModule[$m];
}
foreach ($byModule as $m => $items) {
    if (!isset($sortedModules[$m])) $sortedModules[$m] = $items;
}

$firstModule  = array_key_first($sortedModules) ?? 'pos';
$activeModule = $_GET["module"] ?? $firstModule;
if (!isset($sortedModules[$activeModule])) $activeModule = $firstModule;
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Edit Your Setup – SetupForge</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/style.css" rel="stylesheet">
  <style>
    body { background: #f8fafc; }

    /* Remove X button on card */
    .es-remove-btn {
      position: absolute;
      top: 38px; right: 8px; /* below the rec badge */
      width: 24px; height: 24px;
      border-radius: 50%;
      background: rgba(220,38,38,.10);
      color: #dc2626;
      border: none; font-size: .78rem;
      cursor: pointer;
      display: flex; align-items: center; justify-content: center;
      z-index: 5;
      transition: background .15s;
    }
    .es-remove-btn:hover { background: rgba(220,38,38,.22); }

    /* Removed state */
    .es-removed { position: relative; }
    .es-removed .sf-pkg-card-media,
    .es-removed .sf-pkg-card-body,
    .es-removed .sf-pkg-card-footer { opacity: .25; pointer-events: none; }
    .es-removed::after {
      content: "Removed";
      position: absolute; top: 50%; left: 50%;
      transform: translate(-50%,-50%);
      background: #dc2626; color: #fff;
      font-size: .72rem; font-weight: 800;
      padding: 4px 12px; border-radius: 999px;
      z-index: 10; pointer-events: none;
    }
  </style>
</head>
<body>

<?php include "includes/navbar.php"; ?>

<!-- Hero bar -->
<div class="sf-pkg-hero-bar">
  <div class="container">
    <div class="sf-pkg-hero-inner">
      <div class="sf-pkg-hero-badges">
        <span class="sf-pkg-badge"><i class="bi bi-pencil-square"></i> Edit Setup</span>
        <span class="sf-pkg-badge sf-pkg-badge-teal"><i class="bi bi-shop"></i> <?= h($businessName) ?></span>
        <span class="sf-pkg-badge"><i class="bi bi-receipt"></i> Order #<?= $order_id ?></span>
      </div>
      <div style="color:rgba(255,255,255,.7);font-size:.85rem;">
        Original Total: <strong style="color:#fff"><?= egp($original_total) ?></strong>
      </div>
    </div>
  </div>
</div>

<?php if (!empty($saveError)): ?>
<div class="container mt-3">
  <div class="alert alert-danger"><?= h($saveError) ?></div>
</div>
<?php endif; ?>

<main class="sf-packages-page">
  <div class="container sf-pkg-body">

    <!-- Module tabs — same as packages.php -->
    <div class="sf-pkg-tabrow">
      <div class="sf-pkg-tabs">
        <?php foreach ($sortedModules as $mod => $items): ?>
        <a href="edit_setup.php?module=<?= h($mod) ?>"
           class="sf-pkg-tab <?= $mod === $activeModule ? 'is-active' : '' ?>">
          <?= h($moduleLabels[$mod] ?? ucfirst($mod)) ?>
        </a>
        <?php endforeach; ?>
      </div>
      <a href="business_overview.php" class="sf-pkg-restart-btn">
        <i class="bi bi-arrow-left"></i> Back to Overview
      </a>
    </div>

    <!-- Save form -->
    <form method="POST" id="edit-form">
      <input type="hidden" name="action" value="save_changes">

      <div class="sf-pkg-content">
        <div class="sf-module-shell">

          <?php if (!empty($sortedModules[$activeModule])): ?>
            <div class="sf-pkg-sections">

              <?php foreach ($sortedModules[$activeModule] as $type => $item):
                $itemId    = (int)$item["item_id"];
                $unitPrice = (float)$item["unit_price"];
                $qty       = (int)$item["quantity"];
                $alts      = $item["alternatives"] ?? [];
                $sectionTitle = ($sectionLabelsAll[$activeModule][$type] ?? ucfirst($type));
                $altCount  = 1 + count($alts);
              ?>

              <div class="sf-pkg-section">

                <div class="sf-pkg-section-head">
                  <h4 class="sf-pkg-section-title"><?= h($sectionTitle) ?></h4>
                  <span class="sf-pkg-section-count">
                    <?= $altCount ?> option<?= $altCount !== 1 ? 's' : '' ?>
                  </span>
                </div>

                <div class="sf-pkg-slider-wrap">
                  <div class="sf-pkg-slider">

                    <!-- ── CURRENT PRODUCT CARD ── -->
                    <article class="sf-pkg-card sf-pkg-card--rec"
                             id="card-<?= $itemId ?>" style="position:relative;">

                      <div class="sf-pkg-rec-badge">
                        <i class="bi bi-patch-check-fill"></i> Current
                      </div>

                      <!-- Small X remove button -->
                      <button type="button" class="es-remove-btn"
                              onclick="removeItem(<?= $itemId ?>, <?= $unitPrice ?>, <?= $qty ?>)"
                              title="Remove this product">
                        <i class="bi bi-x"></i>
                      </button>

                      <div class="sf-pkg-card-media">
                        <?php if (!empty($item["image_url"])): ?>
                          <img src="<?= h($item["image_url"]) ?>" alt="">
                        <?php else: ?>
                          <div class="sf-pkg-card-fallback"><?= strtoupper(substr($type,0,2)) ?></div>
                        <?php endif; ?>
                      </div>

                      <div class="sf-pkg-card-body">
                        <h3 class="sf-pkg-card-name"><?= h($item["product_name"]) ?></h3>
                        <div class="sf-pkg-card-meta">
                          <?php if (!empty($item["brand"])): ?><span><?= h($item["brand"]) ?></span><?php endif; ?>
                          <?php if (!empty($item["vendor_name"])): ?><span><?= h($item["vendor_name"]) ?></span><?php endif; ?>
                        </div>
                        <div class="sf-pkg-card-price"><?= egp($unitPrice) ?></div>
                      </div>

                      <div class="sf-pkg-card-footer">
                        <div class="sf-pkg-qty">
                          <button type="button" class="sf-pkg-qty-btn"
                            onclick="changeQty(<?= $itemId ?>, -1, <?= $unitPrice ?>)">−</button>
                          <span class="sf-pkg-qty-val" id="qty-display-<?= $itemId ?>"><?= $qty ?></span>
                          <input type="hidden" name="qty[<?= $itemId ?>]"
                                 id="qty-input-<?= $itemId ?>" value="<?= $qty ?>">
                          <button type="button" class="sf-pkg-qty-btn"
                            onclick="changeQty(<?= $itemId ?>, 1, <?= $unitPrice ?>)">+</button>
                        </div>
                        <span class="sf-pkg-line-total" id="line-<?= $itemId ?>">
                          <?= egp($qty * $unitPrice) ?>
                        </span>
                      </div>

                    </article>

                    <!-- ── ALTERNATIVE CARDS ── -->
                    <?php foreach ($alts as $alt): ?>
                    <article class="sf-pkg-card sf-pkg-card--alt">

                      <div class="sf-pkg-card-media">
                        <?php if (!empty($alt["image_url"])): ?>
                          <img src="<?= h($alt["image_url"]) ?>" alt="">
                        <?php else: ?>
                          <div class="sf-pkg-card-fallback"><?= strtoupper(substr($type,0,2)) ?></div>
                        <?php endif; ?>
                      </div>

                      <div class="sf-pkg-card-body">
                        <h3 class="sf-pkg-card-name"><?= h($alt["name"]) ?></h3>
                        <div class="sf-pkg-card-meta">
                          <?php if (!empty($alt["brand"])): ?><span><?= h($alt["brand"]) ?></span><?php endif; ?>
                          <?php if (!empty($alt["vendor_name"])): ?><span><?= h($alt["vendor_name"]) ?></span><?php endif; ?>
                        </div>
                        <div class="sf-pkg-card-price"><?= egp($alt["price"]) ?></div>
                      </div>

                      <div class="sf-pkg-card-footer">
                        <form method="POST" class="m-0 w-100">
                          <input type="hidden" name="action" value="swap_item">
                          <input type="hidden" name="item_id" value="<?= $itemId ?>">
                          <input type="hidden" name="new_product_id" value="<?= h($alt["id"]) ?>">
                          <input type="hidden" name="module" value="<?= h($activeModule) ?>">
                          <button type="submit" class="sf-pkg-select-btn">Select this</button>
                        </form>
                      </div>

                    </article>
                    <?php endforeach; ?>

                  </div><!-- /.sf-pkg-slider -->
                </div><!-- /.sf-pkg-slider-wrap -->

              </div><!-- /.sf-pkg-section -->
              <?php endforeach; ?>

            </div><!-- /.sf-pkg-sections -->
          <?php else: ?>
            <div class="sf-empty-module-box">No products in this module.</div>
          <?php endif; ?>

        </div><!-- /.sf-module-shell -->
      </div><!-- /.sf-pkg-content -->

      <!-- Fixed bottom bar — same as packages.php -->
      <div class="sf-pkg-bottom-bar">
        <div class="container">
          <div class="sf-pkg-bottom-inner">
            <div class="sf-pkg-bottom-total">
              <span>New Total</span>
              <strong id="grand-total-display"><?= egp($original_total) ?></strong>
            </div>
            <div style="display:flex;gap:12px;align-items:center;">
              <a href="business_overview.php"
                 style="padding:13px 22px;border-radius:0;background:rgba(255,255,255,.12);
                        color:#fff;text-decoration:none;font-size:.9rem;font-weight:700;
                        border:1px solid rgba(255,255,255,.2);">
                Cancel
              </a>
              <button type="submit" class="sf-pkg-review-btn">
                <i class="bi bi-check2-circle me-2"></i>Save Changes
              </button>
            </div>
          </div>
        </div>
      </div>

    </form>

  </div><!-- /.container -->
</main>

<script>
// Build price/qty maps
const unitPrices = {};
const quantities = {};

<?php foreach ($sortedModules as $mod => $items): ?>
<?php foreach ($items as $type => $item): ?>
unitPrices[<?= (int)$item["item_id"] ?>] = <?= (float)$item["unit_price"] ?>;
quantities[<?= (int)$item["item_id"] ?>] = <?= (int)$item["quantity"] ?>;
<?php endforeach; ?>
<?php endforeach; ?>

function updateGrandTotal() {
  let total = 0;
  for (const [id, qty] of Object.entries(quantities)) {
    if (qty > 0) total += qty * (unitPrices[id] || 0);
  }
  document.getElementById('grand-total-display').textContent =
    total.toLocaleString('en-EG') + ' EGP';
}

function changeQty(itemId, delta, unitPrice) {
  const current = quantities[itemId] || 1;
  const newQty  = Math.max(1, current + delta);
  quantities[itemId] = newQty;
  document.getElementById('qty-display-' + itemId).textContent = newQty;
  document.getElementById('qty-input-'   + itemId).value       = newQty;
  document.getElementById('line-'        + itemId).textContent =
    (newQty * unitPrice).toLocaleString('en-EG') + ' EGP';
  updateGrandTotal();
}

function removeItem(itemId) {
  quantities[itemId] = 0;
  document.getElementById('qty-input-' + itemId).value = 0;
  document.getElementById('card-'      + itemId).classList.add('es-removed');
  updateGrandTotal();
}

updateGrandTotal();
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>