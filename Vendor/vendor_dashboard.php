<?php
// vendor_dashboard.php  (PG / $conn)
// Updated without removing your existing logic.
// Added financial metrics:
// - Total Sales      = SUM(gross_amount)
// - Commission Paid  = SUM(commission_amount)
// - Net Earnings     = SUM(vendor_payout)

session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? "") !== "vendor") {
  header("Location: ../auth/login.php?error=" . urlencode("Please login as vendor."));
  exit;
}

if (!isset($conn) || !$conn) {
  http_response_code(500);
  die("DB connection missing. Check db.php (\$conn).");
}

$vendorId = (int)$_SESSION["user_id"];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }
function egp($n){
  $n = (float)$n;
  return number_format((int)round($n)) . " EGP";
}

function pg_one_val($res, $key = "v", $default = 0){
  if (!$res) return $default;
  $row = pg_fetch_assoc($res);
  if (!$row) return $default;
  return $row[$key] ?? $default;
}

function lastMonths($n = 6) {
  $labels = [];
  $keys = [];
  $dt = new DateTime("first day of this month");
  $dt->modify("-" . ($n - 1) . " months");
  for ($i=0; $i<$n; $i++) {
    $labels[] = $dt->format("M Y");
    $keys[]   = $dt->format("Y-m");
    $dt->modify("+1 month");
  }
  return [$labels, $keys];
}


/* =========================
   Vendor Subscription Status
   Safe: does not crash if table does not exist yet.
========================= */
$isSubscriptionActive = false;

$subTableCheck = pg_query($conn, "SELECT to_regclass('public.vendor_subscriptions') AS table_name");
$subTableRow = $subTableCheck ? pg_fetch_assoc($subTableCheck) : null;

if (!empty($subTableRow["table_name"])) {
  $subRes = pg_query_params($conn, "
    SELECT status, expires_at
    FROM vendor_subscriptions
    WHERE vendor_user_id = $1
    ORDER BY id DESC
    LIMIT 1
  ", [$vendorId]);

  $subscription = $subRes ? pg_fetch_assoc($subRes) : null;

  if ($subscription) {
    $subStatus = strtolower((string)($subscription["status"] ?? ""));
    $subExpiresAt = $subscription["expires_at"] ?? null;
    $isSubscriptionActive = $subStatus === "active" && (!$subExpiresAt || strtotime($subExpiresAt) > time());
  }
}

$subscriptionNavText = $isSubscriptionActive ? "✓ Activated" : "Subscription";
$subscriptionNavClass = $isSubscriptionActive ? " is-active" : "";

/* =========================
   Metrics
========================= */
$res = pg_query_params($conn,
  "SELECT COUNT(*) AS v FROM products WHERE vendor_user_id=$1",
  [$vendorId]
);
$totalProducts = (int)pg_one_val($res);

$res = pg_query_params($conn,
  "SELECT COUNT(*) AS v FROM vendor_order_fulfillments WHERE vendor_user_id=$1 AND status='pending'::vendor_order_status",
  [$vendorId]
);
$pendingOrders = (int)pg_one_val($res);

$res = pg_query_params($conn,
  "SELECT COUNT(*) AS v FROM vendor_order_fulfillments WHERE vendor_user_id=$1 AND status='processing'::vendor_order_status",
  [$vendorId]
);
$processingOrders = (int)pg_one_val($res);

$res = pg_query_params($conn,
  "SELECT COUNT(*) AS v FROM vendor_order_fulfillments WHERE vendor_user_id=$1 AND status='delivered'::vendor_order_status",
  [$vendorId]
);
$deliveredOrders = (int)pg_one_val($res);

/* =========================
   Financial Metrics
========================= */
$res = pg_query_params($conn, "
  SELECT
    COALESCE(SUM(gross_amount), 0) AS sales,
    COALESCE(SUM(commission_amount), 0) AS commission,
    COALESCE(SUM(vendor_payout), 0) AS earnings
  FROM vendor_order_fulfillments
  WHERE vendor_user_id = $1
", [$vendorId]);

$financial = $res ? pg_fetch_assoc($res) : [];
if (!$financial) $financial = [];

$totalSales = (float)($financial["sales"] ?? 0);
$totalCommission = (float)($financial["commission"] ?? 0);
$totalEarnings = (float)($financial["earnings"] ?? 0);

/* =========================
   Recent Orders (last 10)
   Vendor total computed from order_items + products
========================= */
$sqlRecent = "
  SELECT
    o.id AS order_id,
    o.order_date,
    o.delivery_location,
    vof.status AS vendor_status,
    vof.gross_amount,
    vof.commission_amount,
    vof.vendor_payout,
    COALESCE((
      SELECT SUM(oi.quantity * oi.unit_price)
      FROM order_items oi
      JOIN products p ON p.id = oi.product_id
      WHERE oi.order_id = o.id
        AND p.vendor_user_id = $1
    ), 0) AS vendor_total
  FROM vendor_order_fulfillments vof
  JOIN orders o ON o.id = vof.order_id
  WHERE vof.vendor_user_id = $1
  ORDER BY o.id DESC
  LIMIT 10
";
$res = pg_query_params($conn, $sqlRecent, [$vendorId]);
$recentOrders = $res ? pg_fetch_all($res) : [];
if (!$recentOrders) $recentOrders = [];

/* =========================
   Sales chart (last 6 months)
   Gross sales by month
========================= */
[$labels, $keys] = lastMonths(6);

$sqlSales = "
  SELECT
    to_char(o.order_date, 'YYYY-MM') AS ym,
    COALESCE(SUM(oi.quantity * oi.unit_price), 0) AS total
  FROM vendor_order_fulfillments vof
  JOIN orders o ON o.id = vof.order_id
  JOIN order_items oi ON oi.order_id = o.id
  JOIN products p ON p.id = oi.product_id
  WHERE vof.vendor_user_id = $1
    AND p.vendor_user_id = $1
  GROUP BY ym
  ORDER BY ym
";
$res = pg_query_params($conn, $sqlSales, [$vendorId]);
$map = [];
if ($res) {
  while($r = pg_fetch_assoc($res)){
    $map[(string)$r["ym"]] = (float)($r["total"] ?? 0);
  }
}
$values = [];
foreach ($keys as $k) $values[] = (float)($map[$k] ?? 0);

/* =========================
   Earnings chart (net after commission)
========================= */
$sqlEarnings = "
  SELECT
    to_char(o.order_date, 'YYYY-MM') AS ym,
    COALESCE(SUM(vof.vendor_payout), 0) AS total
  FROM vendor_order_fulfillments vof
  JOIN orders o ON o.id = vof.order_id
  WHERE vof.vendor_user_id = $1
  GROUP BY ym
  ORDER BY ym
";
$res = pg_query_params($conn, $sqlEarnings, [$vendorId]);
$earningsMap = [];
if ($res) {
  while($r = pg_fetch_assoc($res)){
    $earningsMap[(string)$r["ym"]] = (float)($r["total"] ?? 0);
  }
}
$earningsValues = [];
foreach ($keys as $k) $earningsValues[] = (float)($earningsMap[$k] ?? 0);

$salesLabelsJson = json_encode($labels, JSON_UNESCAPED_UNICODE);
$salesValuesJson = json_encode($values, JSON_UNESCAPED_UNICODE);
$earningsValuesJson = json_encode($earningsValues, JSON_UNESCAPED_UNICODE);
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Dashboard — SetupForge Vendor</title>
  <link rel="stylesheet" href="./vendor_ui.css?v=<?= time() ?>" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">

  <style>
    .vd-shell{
      display: grid;
      gap: 22px;
    }

    .vd-hero{
      position: relative;
      overflow: hidden;
      border-radius: 28px;
      padding: 28px;
      background:
        radial-gradient(circle at top right, rgba(0,153,148,.18), transparent 26%),
        radial-gradient(circle at bottom left, rgba(0,76,172,.12), transparent 32%),
        linear-gradient(135deg, rgba(255,255,255,.98), rgba(247,251,255,.98));
      border: 1px solid rgba(15,23,42,.08);
      box-shadow: 0 22px 50px rgba(15,23,42,.08);
    }

    .vd-hero::after{
      content:"";
      position:absolute;
      right:-40px;
      top:-60px;
      width:220px;
      height:220px;
      border-radius:50%;
      background: radial-gradient(circle, rgba(0,153,148,.16), transparent 65%);
      pointer-events:none;
    }

    .vd-hero-top{
      display:flex;
      align-items:flex-start;
      justify-content:space-between;
      gap:18px;
      flex-wrap:wrap;
    }

    .vd-kicker{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:8px 12px;
      border-radius:999px;
      background: rgba(0,76,172,.06);
      color:#004cac;
      font-size:12px;
      font-weight:800;
      margin-bottom:14px;
      border:1px solid rgba(0,76,172,.10);
    }

    .vd-kicker-dot{
      width:8px;
      height:8px;
      border-radius:50%;
      background: linear-gradient(135deg, #004cac, #009994);
      box-shadow: 0 0 0 4px rgba(0,153,148,.10);
    }

    .vd-hero h1.v-title{
      margin:0;
      font-size:38px;
      line-height:1.03;
    }

    .vd-hero .v-sub{
      margin-top:10px;
      max-width:740px;
      font-size:15px;
    }

    .vd-quick-stats{
      display:grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap:14px;
      margin-top:22px;
    }

    .vd-quick-card{
      border-radius:20px;
      padding:16px 18px;
      background:#fff;
      border:1px solid rgba(15,23,42,.07);
      box-shadow: 0 12px 24px rgba(15,23,42,.05);
    }

    .vd-quick-label{
      font-size:12px;
      font-weight:800;
      color:#64748b;
      margin-bottom:6px;
      text-transform:uppercase;
      letter-spacing:.04em;
    }

    .vd-quick-value{
      font-size:24px;
      font-weight:900;
      color:#0f172a;
      letter-spacing:-.03em;
    }

    .vd-section{
      display:grid;
      gap:22px;
    }

    .vd-metrics-grid{
      display:grid;
      grid-template-columns: repeat(3, minmax(0, 1fr));
      gap:18px;
    }

    .vd-card-accent-1,
    .vd-card-accent-2,
    .vd-card-accent-3,
    .vd-card-accent-4,
    .vd-card-accent-5,
    .vd-card-accent-6{
      position:relative;
      overflow:hidden;
    }

    .vd-card-accent-1::before,
    .vd-card-accent-2::before,
    .vd-card-accent-3::before,
    .vd-card-accent-4::before,
    .vd-card-accent-5::before,
    .vd-card-accent-6::before{
      content:"";
      position:absolute;
      left:0;
      top:0;
      width:100%;
      height:4px;
    }

    .vd-card-accent-1::before{ background: linear-gradient(90deg, #004cac, #3a7bd5); }
    .vd-card-accent-2::before{ background: linear-gradient(90deg, #009994, #16c0a1); }
    .vd-card-accent-3::before{ background: linear-gradient(90deg, #004cac, #009994); }
    .vd-card-accent-4::before{ background: linear-gradient(90deg, #0a84ff, #004cac); }
    .vd-card-accent-5::before{ background: linear-gradient(90deg, #009994, #0bb3ad); }
    .vd-card-accent-6::before{ background: linear-gradient(90deg, #004cac, #009994); }

    .vd-card-icon{
      width:46px;
      height:46px;
      border-radius:16px;
      display:flex;
      align-items:center;
      justify-content:center;
      margin-bottom:14px;
      font-size:20px;
      font-weight:900;
      color:#004cac;
      background: linear-gradient(135deg, rgba(0,76,172,.10), rgba(0,153,148,.08));
      border:1px solid rgba(0,76,172,.08);
    }

    .vd-card-meta{
      margin-top:12px;
      color:#64748b;
      font-size:12px;
      font-weight:700;
    }

    .vd-table-section{
      padding: 22px;
      border-radius: 24px;
      background: linear-gradient(180deg, #ffffff, #fcfeff);
      border: 1px solid rgba(15,23,42,.08);
      box-shadow: 0 18px 40px rgba(15,23,42,.06);
    }

    .vd-table-top{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:16px;
      flex-wrap:wrap;
      margin-bottom:14px;
    }

    .vd-table-badge{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:8px 12px;
      border-radius:999px;
      font-size:12px;
      font-weight:800;
      color:#004cac;
      background: rgba(0,76,172,.06);
      border:1px solid rgba(0,76,172,.10);
      white-space:nowrap;
    }

    .vd-charts{
      display:grid;
      grid-template-columns: repeat(2, minmax(0, 1fr));
      gap:22px;
    }

    .vd-chart-card{
      padding:22px;
      border-radius:24px;
      background: linear-gradient(180deg, #ffffff, #fcfeff);
      border:1px solid rgba(15,23,42,.08);
      box-shadow: 0 18px 40px rgba(15,23,42,.06);
    }

    .vd-chart-card .v-chart{
      margin-top:16px;
      height:320px;
      padding:14px;
      border-radius:20px;
      background:
        radial-gradient(circle at top right, rgba(0,153,148,.05), transparent 30%),
        linear-gradient(180deg, #fbfdff, #f7fbfe);
      border:1px solid rgba(15,23,42,.06);
    }

    .vd-chart-card .v-badge{
      background: rgba(0,153,148,.08);
      color:#0b6f6a;
      border-color: rgba(0,153,148,.16);
    }

    .vd-chart-card.is-blue .v-badge{
      background: rgba(0,76,172,.07);
      color:#004cac;
      border-color: rgba(0,76,172,.14);
    }

    .vd-empty-note{
      margin-top:8px;
      font-size:12px;
      color:#64748b;
    }

    .v-table tbody td strong{
      color:#0f172a;
      font-weight:900;
    }

    @media (max-width: 1100px){
      .vd-metrics-grid,
      .vd-charts,
      .vd-quick-stats{
        grid-template-columns: 1fr;
      }

      .vd-hero h1.v-title{
        font-size:30px;
      }
    }

    @media (max-width: 768px){
      .vd-hero{
        padding:20px;
        border-radius:22px;
      }

      .vd-chart-card,
      .vd-table-section{
        padding:18px;
        border-radius:20px;
      }

      .vd-chart-card .v-chart{
        height:280px;
      }
    }
  </style>
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
        <li class="nav-item">
          <a class="nav-link sf-navlink sf-subscription-nav<?= $subscriptionNavClass ?>" href="vendor_subscription.php"><?= h($subscriptionNavText) ?></a>
        </li>
      </ul>
    </div>

    <div class="d-flex justify-content-end flex-grow-1 gap-2">
      <a href="vendor_subscription.php" class="btn btn-light btn-sm px-3 fw-semibold sf-subscription-mobile-btn d-lg-none"><?= h($subscriptionNavText) ?></a>
      <a href="../auth/logout.php" class="btn btn-outline-light btn-sm px-3 fw-semibold">Logout</a>
    </div>

  </div>
</nav>

<div class="v-wrap">
  <div class="vd-shell">

    <section class="vd-hero">
      <div class="vd-hero-top">
        <div>
          <div class="vd-kicker">
            <span class="vd-kicker-dot"></span>
            Vendor Control Center
          </div>
          <h1 class="v-title">Dashboard</h1>
          <div class="v-sub">Quick overview of your products, orders, sales, and earnings.</div>
        </div>

        <div class="v-actions">
          <a class="v-btn v-btn-primary" href="vendor_add_product.php">Add Product</a>
        </div>
      </div>

      <div class="vd-quick-stats">
        <div class="vd-quick-card">
          <div class="vd-quick-label">Products Listed</div>
          <div class="vd-quick-value"><?= (int)$totalProducts ?></div>
        </div>

        <div class="vd-quick-card">
          <div class="vd-quick-label">Orders Waiting</div>
          <div class="vd-quick-value"><?= (int)$pendingOrders ?></div>
        </div>

        <div class="vd-quick-card">
          <div class="vd-quick-label">Net Earnings</div>
          <div class="vd-quick-value"><?= egp($totalEarnings) ?></div>
        </div>
      </div>
    </section>

    <section class="vd-section">
      <div class="vd-metrics-grid">

        <div class="v-card vd-card-accent-1">
          <div class="vd-card-icon">P</div>
          <div class="v-metric">
            <div>
              <div class="v-label">Total Products</div>
              <div class="v-value"><?= (int)$totalProducts ?></div>
              <div class="vd-card-meta">Your current listed inventory.</div>
            </div>
            <span class="v-pill">Inventory</span>
          </div>
        </div>

        <div class="v-card vd-card-accent-2">
          <div class="vd-card-icon">O</div>
          <div class="v-metric">
            <div>
              <div class="v-label">Pending Orders</div>
              <div class="v-value"><?= (int)$pendingOrders ?></div>
              <div class="vd-card-meta">Orders waiting for your action.</div>
            </div>
            <span class="v-pill">Incoming</span>
          </div>
        </div>

        <div class="v-card vd-card-accent-3">
          <div class="vd-card-icon">F</div>
          <div class="v-metric">
            <div>
              <div class="v-label">Processing / Delivered</div>
              <div class="v-value"><?= (int)$processingOrders ?> / <?= (int)$deliveredOrders ?></div>
              <div class="vd-card-meta">Track fulfillment progress at a glance.</div>
            </div>
            <span class="v-pill">Fulfillment</span>
          </div>
        </div>

        <div class="v-card vd-card-accent-4">
          <div class="vd-card-icon">S</div>
          <div class="v-metric">
            <div>
              <div class="v-label">Total Sales</div>
              <div class="v-value"><?= egp($totalSales) ?></div>
              <div class="vd-card-meta">Gross sales across assigned orders.</div>
            </div>
            <span class="v-pill">Gross</span>
          </div>
        </div>

        <div class="v-card vd-card-accent-5">
          <div class="vd-card-icon">C</div>
          <div class="v-metric">
            <div>
              <div class="v-label">Commission Paid</div>
              <div class="v-value"><?= egp($totalCommission) ?></div>
              <div class="vd-card-meta">Platform commission deducted.</div>
            </div>
            <span class="v-pill">SetupForge Fee</span>
          </div>
        </div>

        <div class="v-card vd-card-accent-6">
          <div class="vd-card-icon">E</div>
          <div class="v-metric">
            <div>
              <div class="v-label">Net Earnings</div>
              <div class="v-value"><?= egp($totalEarnings) ?></div>
              <div class="vd-card-meta">Your revenue after commission.</div>
            </div>
            <span class="v-pill">Your Revenue</span>
          </div>
        </div>

      </div>
    </section>

    <section class="vd-table-section">
      <div class="vd-table-top">
        <div>
          <div class="v-card-title">Recent Orders</div>
          <div class="v-card-sub">Last 10 orders assigned to you.</div>
        </div>
        <span class="vd-table-badge">Latest Activity</span>
      </div>

      <div class="v-table-wrap">
        <table class="v-table">
          <thead>
            <tr>
              <th>Order ID</th>
              <th>Date</th>
              <th>Vendor Status</th>
              <th>Gross</th>
              <th>Commission</th>
              <th>Net</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($recentOrders)): ?>
            <tr><td colspan="7" class="v-empty">No orders found for your vendor yet.</td></tr>
          <?php else: ?>
            <?php foreach ($recentOrders as $o): ?>
              <?php
                $status = strtolower((string)($o["vendor_status"] ?? ""));
                $badgeClass = "v-badge";
                if ($status === "pending") $badgeClass .= " b-pending";
                else if ($status === "processing") $badgeClass .= " b-processing";
                else if ($status === "delivered") $badgeClass .= " b-delivered";
                else if ($status === "accepted") $badgeClass .= " b-processing";
                else if ($status === "ready_for_delivery") $badgeClass .= " b-processing";
                else if ($status === "rejected") $badgeClass .= " b-pending";
              ?>
              <tr>
                <td><strong>#<?= h($o["order_id"] ?? "") ?></strong></td>
                <td><?= h($o["order_date"] ?? "") ?></td>
                <td><span class="<?= h($badgeClass) ?>"><?= h($o["vendor_status"] ?? "unknown") ?></span></td>
                <td><?= egp($o["gross_amount"] ?? $o["vendor_total"] ?? 0) ?></td>
                <td><?= egp($o["commission_amount"] ?? 0) ?></td>
                <td><?= egp($o["vendor_payout"] ?? 0) ?></td>
                <td>
                  <a class="v-btn v-btn-sm v-btn-outline"
                     href="vendor_order_details.php?id=<?= urlencode((string)($o["order_id"] ?? "")) ?>">
                    View
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </section>

    <section class="vd-charts">
      <div class="vd-chart-card">
        <div class="v-card-head">
          <div>
            <div class="v-card-title">Sales Analysis</div>
            <div class="v-card-sub">Last 6 months gross sales (EGP).</div>
          </div>
          <span class="v-badge">Gross Sales</span>
        </div>

        <div class="v-chart">
          <canvas id="salesChart"></canvas>
        </div>
      </div>

      <div class="vd-chart-card is-blue">
        <div class="v-card-head">
          <div>
            <div class="v-card-title">Net Earnings Analysis</div>
            <div class="v-card-sub">Last 6 months earnings after commission (EGP).</div>
          </div>
          <span class="v-badge">After Commission</span>
        </div>

        <div class="v-chart">
          <canvas id="earningsChart"></canvas>
        </div>
      </div>
    </section>

  </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
<script>
(function(){
  const labels = <?= $salesLabelsJson ?>;
  const salesValues = <?= $salesValuesJson ?>;
  const earningsValues = <?= $earningsValuesJson ?>;

  const commonOptions = {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
      mode: "index",
      intersect: false
    },
    plugins: {
      legend: {
        labels: {
          color: "#334155",
          usePointStyle: true,
          pointStyle: "circle",
          font: {
            size: 12,
            weight: "700"
          }
        }
      },
      tooltip: {
        backgroundColor: "rgba(15,23,42,0.94)",
        titleColor: "#ffffff",
        bodyColor: "#e2e8f0",
        borderColor: "rgba(255,255,255,0.08)",
        borderWidth: 1,
        padding: 12,
        displayColors: true
      }
    },
    scales: {
      x: {
        ticks: {
          color: "#64748b",
          font: {
            size: 11,
            weight: "600"
          }
        },
        grid: {
          color: "rgba(15,23,42,0.05)",
          drawBorder: false
        },
        border: {
          display: false
        }
      },
      y: {
        beginAtZero: true,
        ticks: {
          color: "#64748b",
          font: {
            size: 11,
            weight: "600"
          }
        },
        grid: {
          color: "rgba(15,23,42,0.06)",
          drawBorder: false
        },
        border: {
          display: false
        }
      }
    }
  };

  const salesEl = document.getElementById("salesChart");
  if (salesEl) {
    const ctx = salesEl.getContext("2d");
    const salesGradient = ctx.createLinearGradient(0, 0, 0, 320);
    salesGradient.addColorStop(0, "rgba(0,153,148,0.24)");
    salesGradient.addColorStop(1, "rgba(0,153,148,0.02)");

    new Chart(ctx, {
      type: "line",
      data: {
        labels,
        datasets: [{
          label: "Gross Sales (EGP)",
          data: salesValues,
          tension: .35,
          borderWidth: 3,
          pointRadius: 3,
          pointHoverRadius: 5,
          borderColor: "#009994",
          backgroundColor: salesGradient,
          pointBackgroundColor: "#009994",
          pointBorderColor: "#ffffff",
          pointBorderWidth: 2,
          fill: true
        }]
      },
      options: commonOptions
    });
  }

  const earningsEl = document.getElementById("earningsChart");
  if (earningsEl) {
    const ctx2 = earningsEl.getContext("2d");
    const earningsGradient = ctx2.createLinearGradient(0, 0, 0, 320);
    earningsGradient.addColorStop(0, "rgba(0,76,172,0.22)");
    earningsGradient.addColorStop(1, "rgba(0,76,172,0.02)");

    new Chart(ctx2, {
      type: "line",
      data: {
        labels,
        datasets: [{
          label: "Net Earnings (EGP)",
          data: earningsValues,
          tension: .35,
          borderWidth: 3,
          pointRadius: 3,
          pointHoverRadius: 5,
          borderColor: "#004cac",
          backgroundColor: earningsGradient,
          pointBackgroundColor: "#004cac",
          pointBorderColor: "#ffffff",
          pointBorderWidth: 2,
          fill: true
        }]
      },
      options: commonOptions
    });
  }
})();
</script>

</body>
</html>