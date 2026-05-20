<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: auth/login.php");
    exit();
}

$business_id = (int)$_SESSION["user_id"];

/* ── helpers ──────────────────────────────────────────────────── */
function h(mixed $v): string { return htmlspecialchars((string)$v, ENT_QUOTES, 'UTF-8'); }
function egp(mixed $n): string { return number_format((float)$n, 0) . " EGP"; }

function serviceIcon($s){
    $s = strtolower((string)$s);
    if (strpos($s,'kitchen') !== false) return 'bi-wrench';
    if (strpos($s,'pos')     !== false) return 'bi-display';
    if (strpos($s,'electr')  !== false) return 'bi-lightning-charge';
    if (strpos($s,'network') !== false || strpos($s,'wifi') !== false) return 'bi-wifi';
    if (strpos($s,'ac')      !== false || strpos($s,'hvac') !== false || strpos($s,'air') !== false) return 'bi-thermometer-half';
    return 'bi-gear';
}

function moduleColor($m){
    $m = strtolower((string)$m);
    if (strpos($m,'kitchen')    !== false) return ['#f97316','#fff7ed'];
    if (strpos($m,'furniture')  !== false) return ['#22c55e','#f0fdf4'];
    if (strpos($m,'pos')        !== false) return ['#3b82f6','#eff6ff'];
    if (strpos($m,'ac')         !== false) return ['#06b6d4','#ecfeff'];
    if (strpos($m,'electronic') !== false) return ['#8b5cf6','#f5f3ff'];
    return ['#6b7280','#f9fafb'];
}

function fmtDate($d){
    if (!$d) return '';
    $ts = strtotime((string)$d);
    return $ts ? date('M j, Y', $ts) : (string)$d;
}

/* ── business ─────────────────────────────────────────────────── */
$businessRes = pg_query_params($conn, "
    SELECT b.user_id, b.business_name, b.business_type, b.place_size, b.budget_egp, b.location_text,
           u.name, u.email, u.phone, u.city, u.country
    FROM businesses b
    JOIN users u ON b.user_id = u.id
    WHERE b.user_id = $1
    LIMIT 1
", [$business_id]);

if (!$businessRes || pg_num_rows($businessRes) === 0) die("Business account not found.");
$business = pg_fetch_assoc($businessRes);

/* ── order ────────────────────────────────────────────────────── */
$order_id = (int)($_GET["order_id"] ?? 0);
if ($order_id > 0) {
    $orderRes = pg_query_params($conn,
        "SELECT * FROM orders WHERE id = $1 AND business_user_id = $2 LIMIT 1",
        [$order_id, $business_id]);
} else {
    $orderRes = pg_query_params($conn,
        "SELECT * FROM orders WHERE business_user_id = $1 ORDER BY id DESC LIMIT 1",
        [$business_id]);
}
$order = null;
if ($orderRes && pg_num_rows($orderRes) > 0) {
    $order    = pg_fetch_assoc($orderRes);
    $order_id = (int)$order["id"];
}

/* ── products ─────────────────────────────────────────────────── */
$products = [];
if ($order) {
    $pr = pg_query_params($conn, "
        SELECT oi.quantity, oi.unit_price,
               p.id AS product_id, p.product_name, p.brand, p.module, p.vendor_user_id,
               u.name AS vendor_name
        FROM order_items oi
        JOIN products p ON oi.product_id = p.id
        LEFT JOIN users u ON p.vendor_user_id = u.id
        WHERE oi.order_id = $1
        ORDER BY p.module, p.product_name
    ", [$order_id]);
    if ($pr) while ($r = pg_fetch_assoc($pr)) $products[] = $r;
}

/* ── labor summary ────────────────────────────────────────────── */
$laborJobs          = [];
$totalLaborPositions = 0;
$lr = pg_query_params($conn, "
    SELECT j.title, j.location,
           COUNT(*) AS total_openings,
           COUNT(*) FILTER (WHERE j.worker_id IS NOT NULL) AS filled_openings,
           COUNT(*) FILTER (WHERE j.worker_id IS NULL)     AS remaining_openings,
           COALESCE(STRING_AGG(DISTINCT uw.name, ', ') FILTER (WHERE j.worker_id IS NOT NULL), '') AS hired_workers
    FROM jobs j
    LEFT JOIN users uw ON j.worker_id = uw.id
    WHERE j.business_id = $1 AND j.job_type = 'labor'
    GROUP BY j.title, j.location
    ORDER BY j.title ASC
", [$business_id]);
if ($lr) while ($r = pg_fetch_assoc($lr)) {
    $laborJobs[]          = $r;
    $totalLaborPositions += (int)$r['total_openings'];
}

/* ── product images ───────────────────────────────────────────── */
$productImages = [];
if (!empty($products)) {
    $safeIds = implode(',', array_map('intval', array_column($products, 'product_id')));
    $imgRes  = pg_query($conn, "
        SELECT DISTINCT ON (product_id) product_id, image_url
        FROM product_images
        WHERE product_id IN ($safeIds)
        ORDER BY product_id, id
    ");
    if ($imgRes) while ($r = pg_fetch_assoc($imgRes))
        $productImages[(int)$r['product_id']] = $r['image_url'];
}

/* ── vendor fulfillments ──────────────────────────────────────── */
$vendorFulfillments = [];
if ($order) {
    $vfRes = pg_query_params($conn, "
        SELECT vendor_user_id, status, estimated_delivery_date
        FROM vendor_order_fulfillments
        WHERE order_id = $1
    ", [$order_id]);
    if ($vfRes) while ($r = pg_fetch_assoc($vfRes))
        $vendorFulfillments[(int)$r['vendor_user_id']] = $r;
}

/* ── installation requests ────────────────────────────────────── */
$installationRequests = [];
$irRes = pg_query_params($conn, "
SELECT ir.request_id, ir.services, ir.status, ir.scheduled_date,
           c.company_name, c.starting_from, c.avg_rating
    FROM installation_requests ir
    LEFT JOIN companies c ON ir.company_id = c.company_id
    WHERE ir.user_id = $1
", [$business_id]);
if ($irRes) while ($r = pg_fetch_assoc($irRes)) $installationRequests[] = $r;

/* ── progress tracker states ──────────────────────────────────── */
$step2Status = ($order && $order['payment_status'] === 'paid') ? 'done' : 'pending';

$hasActiveFulfillment = false;
foreach ($vendorFulfillments as $vf)
    if (in_array($vf['status'], ['processing', 'delivered'])) { $hasActiveFulfillment = true; break; }
$step3Status = !$order ? 'none' : ($hasActiveFulfillment ? 'done' : 'progress');

$hasScheduledInstall = false;
foreach ($installationRequests as $ir)
    if (!empty($ir['scheduled_date'])) { $hasScheduledInstall = true; break; }
$step4Status = $hasScheduledInstall ? 'done' : 'pending';

/* ── group products by module ─────────────────────────────────── */
$byModule = [];
foreach ($products as $p) {
    $mod = strtolower($p['module'] ?: 'general');
    $byModule[$mod][] = $p;
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Business Overview – SetupForge</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/style.css" rel="stylesheet">
  <style>
    body { background: #f8fafc; }

    /* ── overview layout ── */
    .ovw-section-title {
      font-size: 1rem;
      font-weight: 800;
      color: #111827;
      margin-bottom: 20px;
    }
    .ovw-card {
      background: #fff;
      border-radius: 5px;
      box-shadow: 0 0 0 1.5px rgba(0,76,172,0.12), 0 4px 18px rgba(0,76,172,0.08);
      border: none;
    }
    .ovw-inner {
      border-radius: 5px;
      border: 1.5px solid #e0eaff;
      border-left: 3px solid #004cac;
    }

    /* ── stat cards ── */
    .ovw-stat {
      background: #fff;
      border-radius: 5px;
      border: 1.5px solid #e0eaff;
      border-left: 4px solid #004cac;
      padding: 20px 22px;
      display: flex;
      flex-direction: column;
      gap: 6px;
    }
    .ovw-stat-icon {
      width: 40px; height: 40px;
      border-radius: 5px;
      display: flex; align-items: center; justify-content: center;
      font-size: 1.1rem;
      margin-bottom: 4px;
    }
    .ovw-stat-label { font-size: .78rem; font-weight: 600; color: #9ca3af; text-transform: uppercase; letter-spacing: .04em; }
    .ovw-stat-value { font-size: 1.55rem; font-weight: 800; color: #111827; line-height: 1; }

    /* ── product cards ── */
    .ovw-product-card {
      background: #f8fafc;
      border-radius: 5px;
      border: 1.5px solid #f1f5f9;
      padding: 12px 16px;
      display: flex;
      align-items: center;
      gap: 14px;
      transition: border-color .15s;
    }
    .ovw-product-card:hover { border-color: #c7d9f7; }
    .ovw-product-img {
      width: 60px; height: 60px;
      border-radius: 5px;
      object-fit: cover;
      flex-shrink: 0;
      background: #e5e7eb;
      display: flex; align-items: center; justify-content: center;
      overflow: hidden;
    }
    .ovw-product-img img { width: 100%; height: 100%; object-fit: cover; }

    /* ── module badge ── */
    .ovw-module-badge {
      display: inline-block;
      border-radius: 5px;
      padding: 3px 12px;
      font-size: .72rem;
      font-weight: 700;
      text-transform: uppercase;
      letter-spacing: .05em;
    }

    /* ── status badges ── */
    .ovw-badge {
      display: inline-block;
      border-radius: 5px;
      padding: 3px 11px;
      font-size: .73rem;
      font-weight: 700;
    }
    .ovw-badge-green   { background: #dcfce7; color: #16a34a; }
    .ovw-badge-blue    { background: #dbeafe; color: #1d4ed8; }
    .ovw-badge-amber   { background: #fef9c3; color: #b45309; }
    .ovw-badge-grey    { background: #f1f5f9; color: #64748b; }
    .ovw-badge-red     { background: #fee2e2; color: #dc2626; }

    /* ── labor progress bar ── */
    .ovw-progress-track {
      height: 6px; border-radius: 5px;
      background: #e5e7eb; overflow: hidden;
    }
    .ovw-progress-fill {
      height: 100%; border-radius: 5px;
      background: #004cac;
      transition: width .3s;
    }

    /* ── hero bar ── */
    .ovw-hero { background: #004cac; box-shadow: 0 2px 12px rgba(0,0,0,0.06); }
    .ovw-hero-name { font-size: 1.9rem; font-weight: 800; color: #fff; margin-bottom: 8px; }
    .ovw-order-chip {
      display: inline-flex; align-items: center; gap: 5px;
      background: rgba(255,255,255,0.15); color: #fff;
      border: 1.5px solid rgba(255,255,255,0.3); border-radius: 5px;
      padding: 4px 14px; font-size: .83rem; font-weight: 700;
    }

    /* ── tracker ── */
    .ovw-tracker-wrap { overflow-x: auto; padding-bottom: 4px; }
    .ovw-tracker { min-width: 480px; }
    .ovw-step-node {
      width: 44px; height: 44px; border-radius: 50%;
      display: flex; align-items: center; justify-content: center;
      font-size: 1rem; flex-shrink: 0;
    }

    /* ── empty state ── */
    .ovw-empty {
      display: flex; flex-direction: column; align-items: center;
      justify-content: center; padding: 32px 16px; gap: 8px;
      color: #9ca3af;
    }
    .ovw-empty i { font-size: 2rem; }
    .ovw-empty span { font-size: .9rem; font-weight: 600; }
  </style>
</head>
<body>

<?php include "includes/navbar.php"; ?>

<!-- ══════════════════════════════════════════════════════════
     SECTION 1 — HERO BAR
═══════════════════════════════════════════════════════════ -->
<div class="ovw-hero">
  <div class="container py-4">
    <div class="d-flex justify-content-between align-items-center flex-wrap gap-3">

      <!-- Left: name + type + location -->
      <div>
        <div class="ovw-hero-name"><?= h($business['business_name'] ?: $business['name']) ?></div>
        <div class="d-flex align-items-center gap-2 flex-wrap">
          <?php
            $btype = strtolower($business['business_type'] ?? '');
            $typeColors = ['restaurant'=>'#f97316','cafe'=>'#f59e0b','retail'=>'#22c55e','office'=>'#3b82f6'];
            $tc = $typeColors[$btype] ?? '#6366f1';
          ?>
          <span style="background:#fff;color:<?= $tc ?>;border:1px solid <?= $tc ?>44;
                border-radius:5px;padding:3px 13px;font-size:.8rem;font-weight:700">
            <?= h(ucfirst($business['business_type'] ?: 'Business')) ?>
          </span>
          <?php $loc = $business['location_text'] ?: ($business['city'] ?: ''); if ($loc): ?>
            <span style="color:rgba(255,255,255,0.8);font-size:.9rem">
              <i class="bi bi-geo-alt-fill me-1" style="color:rgba(255,255,255,0.8)"></i><?= h($loc) ?>
            </span>
          <?php endif; ?>
        </div>
      </div>

      <!-- Right: order chip + paid date + buttons -->
      <div class="d-flex flex-column align-items-end gap-2">
        <?php if ($order): ?>
          <div class="d-flex align-items-center gap-2 flex-wrap justify-content-end">
            <span class="ovw-order-chip"><i class="bi bi-receipt"></i>Order #<?= $order_id ?></span>
            <?php if (!empty($order['paid_at'])): ?>
              <span style="color:rgba(255,255,255,0.75);font-size:.8rem">
                <i class="bi bi-calendar-check me-1"></i>Paid <?= h(fmtDate($order['paid_at'])) ?>
              </span>
            <?php endif; ?>
          </div>
        <?php endif; ?>
        <div class="d-flex gap-2 flex-wrap justify-content-end">
          <a href="edit_setup.php"
             class="btn btn-sm px-3 fw-semibold"
             style="border:2px solid #fff;color:#fff;background:transparent;border-radius:0">
            <i class="bi bi-pencil me-1"></i>Edit My Setup
          </a>
          <a href="service_jobs.php"
             class="btn btn-sm px-3 fw-semibold"
             style="background:#fff;color:#004cac;border:none;border-radius:0;font-weight:700">
            <i class="bi bi-people me-1"></i>Manage Staff &amp; Installation
          </a>
        </div>
      </div>

    </div>
  </div>
</div>

<main class="container py-4">

<!-- ══════════════════════════════════════════════════════════
     SECTION 2 — PROGRESS TRACKER
═══════════════════════════════════════════════════════════ -->
<div class="ovw-card mb-4 p-4">
  <div class="ovw-section-title mb-3">
    <i class="bi bi-diagram-3 me-2" style="color:#004cac"></i>Setup Progress
  </div>
  <div class="ovw-tracker-wrap">
    <div class="ovw-tracker d-flex align-items-start">
      <?php
      $trackerSteps = [
        ['label' => 'Order Placed',          'icon' => 'cart-check-fill', 'status' => 'done'],
        ['label' => 'Payment Confirmed',     'icon' => 'credit-card-fill','status' => $step2Status],
        ['label' => 'Products in Delivery',  'icon' => 'truck',           'status' => $step3Status],
        ['label' => 'Installation Scheduled','icon' => 'tools',           'status' => $step4Status],
      ];
      foreach ($trackerSteps as $si => $step):
        $done     = $step['status'] === 'done';
        $inprog   = $step['status'] === 'progress';
        $cbg      = $done ? '#004cac' : ($inprog ? '#f59e0b' : '#e5e7eb');
        $cclr     = ($done || $inprog) ? '#fff' : '#9ca3af';
        $cicon    = $done ? 'bi-check-lg' : 'bi-' . $step['icon'];
        $lblclr   = ($done || $inprog) ? '#111827' : '#9ca3af';
        $subclr   = $done ? '#004cac' : ($inprog ? '#f59e0b' : '#d1d5db');
        $subtxt   = $done ? 'Complete' : ($inprog ? 'In Progress' : 'Pending');
      ?>
      <div class="d-flex flex-column align-items-center text-center" style="flex:0 0 80px;width:80px">
        <div class="ovw-step-node mb-2" style="background:<?= $cbg ?>;color:<?= $cclr ?>">
          <i class="<?= $cicon ?>"></i>
        </div>
        <div style="font-size:.75rem;font-weight:700;color:<?= $lblclr ?>;line-height:1.2"><?= $step['label'] ?></div>
        <div style="font-size:.7rem;color:<?= $subclr ?>;font-weight:600;margin-top:2px"><?= $subtxt ?></div>
      </div>
      <?php if ($si < count($trackerSteps) - 1):
        $nextDone = in_array($trackerSteps[$si + 1]['status'], ['done', 'progress']);
        $lbg      = $nextDone ? '#004cac' : '#e5e7eb';
      ?>
      <div class="flex-fill" style="padding-top:22px;padding-left:6px;padding-right:6px">
        <div style="height:2px;background:<?= $lbg ?>;border-radius:5px"></div>
      </div>
      <?php endif; ?>
      <?php endforeach; ?>
    </div>
  </div>
</div>

<!-- ══════════════════════════════════════════════════════════
     SECTION 3 — STATS ROW
═══════════════════════════════════════════════════════════ -->
<div class="row g-3 mb-4">

  <div class="col-6 col-md-3">
    <div class="ovw-stat">
      <div class="ovw-stat-icon" style="background:#eff6ff;color:#004cac">
        <i class="bi bi-bag-check-fill"></i>
      </div>
      <div class="ovw-stat-label">Setup Total</div>
      <div class="ovw-stat-value"><?= $order ? egp($order['order_total']) : '—' ?></div>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="ovw-stat">
      <div class="ovw-stat-icon" style="background:#eff6ff;color:#004cac">
        <i class="bi bi-box-seam-fill"></i>
      </div>
      <div class="ovw-stat-label">Products Ordered</div>
      <div class="ovw-stat-value"><?= count($products) ?></div>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="ovw-stat">
      <div class="ovw-stat-icon" style="background:#eff6ff;color:#004cac">
        <i class="bi bi-person-fill-check"></i>
      </div>
      <div class="ovw-stat-label">Staff Positions</div>
      <div class="ovw-stat-value"><?= $totalLaborPositions ?></div>
    </div>
  </div>

  <div class="col-6 col-md-3">
    <div class="ovw-stat">
      <div class="ovw-stat-icon" style="background:#eff6ff;color:#004cac">
        <i class="bi bi-tools"></i>
      </div>
      <div class="ovw-stat-label">Installation Services</div>
      <div class="ovw-stat-value"><?= count($installationRequests) ?></div>
    </div>
  </div>

</div>

<!-- ══════════════════════════════════════════════════════════
     SECTION 4 — ORDERED PRODUCTS
═══════════════════════════════════════════════════════════ -->
<div class="ovw-card mb-4 p-4">
  <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
    <div class="ovw-section-title mb-0">Ordered Products</div>
    <a href="edit_setup.php"
       class="btn btn-sm fw-semibold px-3"
       style="background:#eff6ff;color:#004cac;border-radius:0;border:none">
      <i class="bi bi-pencil me-1"></i>Edit Setup
    </a>
  </div>

  <?php if (empty($products)): ?>
    <div class="ovw-empty">
      <i class="bi bi-box"></i>
      <span>No products ordered yet</span>
    </div>
  <?php else: ?>
    <?php foreach ($byModule as $modKey => $modProducts):
      [$mc, $mbg] = moduleColor($modKey);
$moduleLabels = [
    'kitchen'     => 'Kitchen Equipment',
    'furniture'   => 'Dining Area',
    'pos'         => 'POS System',
    'ac'          => 'AC & Climate',
    'electronics' => 'Electronics',
];
$modLabel = $moduleLabels[$modKey] ?? ucfirst($modKey);    ?>
    <div class="mb-4">
      <!-- Module label -->
      <div class="mb-3">
        <span class="ovw-module-badge" style="background:<?= $mbg ?>;color:<?= $mc ?>">
          <?= h($modLabel) ?>
        </span>
      </div>
      <!-- Product cards -->
      <div class="d-flex flex-column gap-2">
        <?php foreach ($modProducts as $p):
          $pid    = (int)$p['product_id'];
          $vid    = (int)$p['vendor_user_id'];
          $imgUrl = $productImages[$pid] ?? null;
          $vf     = $vendorFulfillments[$vid] ?? null;
          $dlvSt  = $vf ? strtolower($vf['status']) : 'pending';
          if ($dlvSt === 'delivered') { $dlvClass = 'ovw-badge-green'; $dlvLabel = 'Delivered'; }
          elseif ($dlvSt === 'processing') { $dlvClass = 'ovw-badge-blue'; $dlvLabel = 'Processing'; }
          else { $dlvClass = 'ovw-badge-amber'; $dlvLabel = 'Pending'; }
          $lineTotal = (float)$p['unit_price'] * (int)$p['quantity'];
        ?>
        <div class="ovw-product-card">
          <!-- Image -->
          <div class="ovw-product-img">
            <?php if ($imgUrl): ?>
              <img src="<?= h($imgUrl) ?>" alt="">
            <?php else: ?>
              <i class="bi bi-box text-secondary" style="font-size:1.3rem"></i>
            <?php endif; ?>
          </div>
          <!-- Info -->
          <div class="flex-fill min-w-0">
            <div class="fw-bold" style="color:#111827;font-size:.92rem"><?= h($p['product_name']) ?></div>
            <?php if (!empty($p['brand'])): ?>
              <div class="text-secondary" style="font-size:.78rem"><?= h($p['brand']) ?></div>
            <?php endif; ?>
            <div style="font-size:.78rem;color:#6b7280;margin-top:2px">
              <?= h($p['vendor_name'] ?: '—') ?>
            </div>
          </div>
          <!-- Qty + price -->
          <div class="d-flex align-items-center gap-3 flex-wrap justify-content-end">
            <span class="badge rounded-pill" style="background:#f1f5f9;color:#475569;font-size:.78rem;font-weight:700">
              ×<?= (int)$p['quantity'] ?>
            </span>
            <div class="text-end" style="min-width:90px">
              <div style="font-size:.78rem;color:#9ca3af"><?= egp($p['unit_price']) ?> each</div>
              <div class="fw-bold" style="color:#111827;font-size:.9rem"><?= egp($lineTotal) ?></div>
            </div>
            <span class="ovw-badge <?= $dlvClass ?>"><?= $dlvLabel ?></span>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
    <?php endforeach; ?>

    <!-- Total -->
    <?php
      $orderTotal = $order ? (float)$order['order_total'] : array_sum(array_map(
          fn($p) => (float)$p['unit_price'] * (int)$p['quantity'], $products
      ));
    ?>
    <div class="d-flex justify-content-end align-items-center gap-3 pt-3"
         style="border-top:2px solid #f1f5f9;margin-top:8px">
      <span style="font-size:.9rem;color:#6b7280;font-weight:600">Order Total</span>
      <span style="font-size:1.2rem;font-weight:800;color:#004cac"><?= egp($orderTotal) ?></span>
    </div>
  <?php endif; ?>
</div>

<!-- ══════════════════════════════════════════════════════════
     SECTION 5 — LABOR + INSTALLATION (two columns)
═══════════════════════════════════════════════════════════ -->
<div class="row g-4">

  <!-- ── LEFT: Labor Summary ───────────────────────────── -->
  <div class="col-12 col-xl-6">
    <div class="ovw-card p-4 h-100">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div class="ovw-section-title mb-0">Labor Summary</div>
        <a href="service_jobs.php"
           class="btn btn-sm fw-semibold px-3"
           style="background:#eff6ff;color:#004cac;border-radius:0;border:none">
          <i class="bi bi-person-lines-fill me-1"></i>View Applicants
        </a>
      </div>

      <?php if (empty($laborJobs)): ?>
        <div class="ovw-empty">
          <i class="bi bi-person-x"></i>
          <span>No labor jobs generated yet</span>
        </div>
      <?php else: ?>
        <div class="d-flex flex-column gap-3">
          <?php foreach ($laborJobs as $job):
            $total    = max(1, (int)$job['total_openings']);
            $filled   = (int)$job['filled_openings'];
            $pct      = round(($filled / $total) * 100);
            $isFull   = $filled >= $total;
          ?>
          <div class="ovw-inner p-3">
            <div class="d-flex align-items-start justify-content-between gap-2 mb-2">
              <div>
                <div class="fw-bold" style="color:#111827;font-size:.9rem"><?= h($job['title']) ?></div>
                <?php if (!empty($job['location'])): ?>
                  <div style="font-size:.78rem;color:#9ca3af">
                    <i class="bi bi-geo-alt me-1"></i><?= h($job['location']) ?>
                  </div>
                <?php endif; ?>
              </div>
              <?php if ($isFull): ?>
                <span class="ovw-badge ovw-badge-green">Full</span>
              <?php else: ?>
                <span class="ovw-badge ovw-badge-blue">Hiring</span>
              <?php endif; ?>
            </div>
            <div class="ovw-progress-track mb-1">
              <div class="ovw-progress-fill" style="width:<?= $pct ?>%"></div>
            </div>
            <div style="font-size:.75rem;color:#6b7280;font-weight:600">
              <?= $filled ?> of <?= $total ?> filled
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

  <!-- ── RIGHT: Installation Services ─────────────────── -->
  <div class="col-12 col-xl-6">
    <div class="ovw-card p-4 h-100">
      <div class="d-flex align-items-center justify-content-between flex-wrap gap-2 mb-4">
        <div class="ovw-section-title mb-0">Installation Services</div>
        <a href="service_jobs.php?tab=installation"
           class="btn btn-sm fw-semibold px-3"
           style="background:#eff6ff;color:#004cac;border-radius:0;border:none">
          <i class="bi bi-building me-1"></i>View Companies
        </a>
      </div>

      <?php if (empty($installationRequests)): ?>
        <div class="ovw-empty">
          <i class="bi bi-tools"></i>
          <span>No installation requests yet</span>
        </div>
      <?php else: ?>
        <div class="d-flex flex-column gap-3">
          <?php
          $serviceLabels = [
            'electrical' => 'Electrical Installation',
            'ac'         => 'AC & Climate Control',
            'kitchen'    => 'Kitchen Installation',
            'pos'        => 'POS Setup',
            'network'    => 'Network & WiFi',
            'plumbing'   => 'Plumbing',
          ];
          foreach ($installationRequests as $ir):
$svcRaw = strtolower(trim(str_replace(['{', '}'], '', $ir['services'] ?? '')));
            $svcDisplay = $serviceLabels[$svcRaw] ?? ucwords(str_replace(['_','-'], ' ', $ir['services'] ?: 'Installation Service'));
            $svcIcon    = serviceIcon($ir['services'] ?? '');
            $hasCompany = !empty($ir['company_name']);
            $isAccepted = strtolower($ir['status'] ?? '') === 'accepted';

            if ($hasCompany) {
                $priceText = 'Quote Received';
            } elseif (!empty($ir['starting_from'])) {
                $priceText = 'From ' . egp($ir['starting_from']);
            } else {
                $priceText = 'Quote Required';
            }

            $companyText = $isAccepted && $hasCompany ? h($ir['company_name']) : 'Awaiting Response';

            $stSt  = strtolower($ir['status'] ?? 'pending');
            if ($stSt === 'accepted')  { $stClass = 'ovw-badge-green'; $stLbl = 'Accepted'; }
            elseif ($stSt === 'rejected') { $stClass = 'ovw-badge-red'; $stLbl = 'Rejected'; }
            else { $stClass = 'ovw-badge-grey'; $stLbl = ucfirst($stSt ?: 'Pending'); }
          ?>
          <div class="ovw-inner p-3">
            <div class="d-flex align-items-start gap-3">
              <!-- Icon -->
              <div class="d-flex align-items-center justify-content-center flex-shrink-0"
                   style="width:42px;height:42px;border-radius:5px;background:#eff6ff;color:#004cac;font-size:1.1rem">
                <i class="<?= $svcIcon ?>"></i>
              </div>
              <!-- Info -->
              <div class="flex-fill min-w-0">
                <div class="d-flex align-items-center justify-content-between gap-2 mb-1">
                  <div class="fw-bold" style="color:#111827;font-size:.9rem"><?= h($svcDisplay) ?></div>
                  <span class="ovw-badge <?= $stClass ?>"><?= $stLbl ?></span>
                </div>
                <div style="font-size:.78rem;color:#6b7280">
                  <span style="margin-right:12px"><i class="bi bi-building me-1"></i><?= $companyText ?></span>
                  <span style="color:#004cac;font-weight:600"><?= h($priceText) ?></span>
                </div>
                <?php if (!empty($ir['scheduled_date'])): ?>
    <div style="margin-top:6px;font-size:.78rem;font-weight:700;color:#004cac;">
        <i class="bi bi-calendar-check me-1"></i>Scheduled: <?= date('M j, Y', strtotime($ir['scheduled_date'])) ?>
    </div>
<?php endif; ?>
                <?php if ($isAccepted && !empty($ir['avg_rating'])): ?>
                  <div style="font-size:.75rem;color:#f59e0b;margin-top:3px">
                    <i class="bi bi-star-fill me-1"></i><?= h($ir['avg_rating']) ?>
                  </div>
                <?php endif; ?>
              </div>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>
  </div>

</div>
</main>

<footer class="sf-footer mt-5">
  <div class="container py-5">
    <div class="row g-4">
      <div class="col-lg-4">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="sf-footer-logo">
            <img src="assets/images/Logo.png" alt="SetupForge Logo">
          </div>
          <h5 class="mb-0 text-white fw-bold">SetupForge</h5>
        </div>
        <p class="sf-footer-text">
          SetupForge helps entrepreneurs launch, furnish, and fully prepare their businesses.
          From equipment sourcing to installation and optimization — we handle it all.
        </p>
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
          <li><a href="#">Dining area</a></li>
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
        <p class="sf-footer-text small">
          Get updates, product releases, and startup tips.
        </p>
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
</body>
</html>
