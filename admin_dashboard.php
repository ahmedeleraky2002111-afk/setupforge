<?php
session_start();
require "db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? "") !== "admin") {
    header("Location: auth/login.php"); exit();
}

/* ---------- NAVBAR STATS (today) ---------- */
$orders_today = (int)pg_fetch_result(pg_query($conn,
    "SELECT COUNT(*) FROM orders WHERE DATE(order_date) = CURRENT_DATE"), 0, 0);

$revenue_today_res = pg_query($conn,
    "SELECT COALESCE(SUM(vof.gross_amount),0)
     FROM vendor_order_fulfillments vof
     JOIN orders o ON o.id = vof.order_id
     WHERE o.payment_status = 'paid'
       AND DATE(o.paid_at) = CURRENT_DATE");
$revenue_today = (float)pg_fetch_result($revenue_today_res, 0, 0);

$pending_total = (int)pg_fetch_result(pg_query($conn,
    "SELECT (SELECT COUNT(*) FROM vendors WHERE status='pending') +
            (SELECT COUNT(*) FROM labors  WHERE status='pending')"), 0, 0);

/* ---------- ALERT STRIP ---------- */
$pending_vendors = (int)pg_fetch_result(pg_query($conn,
    "SELECT COUNT(*) FROM vendors WHERE status='pending'"), 0, 0);
$pending_labors  = (int)pg_fetch_result(pg_query($conn,
    "SELECT COUNT(*) FROM labors WHERE status='pending'"), 0, 0);
$stuck_orders    = (int)pg_fetch_result(pg_query($conn,
    "SELECT COUNT(*) FROM orders
     WHERE payment_status != 'paid'
       AND order_date <= NOW() - INTERVAL '3 days'"), 0, 0);

/* ---------- KPI CARDS ---------- */
$rev_month = (float)pg_fetch_result(pg_query($conn,
    "SELECT COALESCE(SUM(vof.gross_amount),0)
     FROM vendor_order_fulfillments vof
     JOIN orders o ON o.id = vof.order_id
     WHERE o.payment_status='paid'
       AND DATE_TRUNC('month', o.paid_at) = DATE_TRUNC('month', CURRENT_DATE)"), 0, 0);

$rev_last = (float)pg_fetch_result(pg_query($conn,
    "SELECT COALESCE(SUM(vof.gross_amount),0)
     FROM vendor_order_fulfillments vof
     JOIN orders o ON o.id = vof.order_id
     WHERE o.payment_status='paid'
       AND DATE_TRUNC('month', o.paid_at) = DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '1 month'"), 0, 0);

$profit_month = (float)pg_fetch_result(pg_query($conn,
    "SELECT COALESCE(SUM(vof.commission_amount),0)
     FROM vendor_order_fulfillments vof
     JOIN orders o ON o.id = vof.order_id
     WHERE o.payment_status='paid'
       AND DATE_TRUNC('month', o.paid_at) = DATE_TRUNC('month', CURRENT_DATE)"), 0, 0);

$profit_last = (float)pg_fetch_result(pg_query($conn,
    "SELECT COALESCE(SUM(vof.commission_amount),0)
     FROM vendor_order_fulfillments vof
     JOIN orders o ON o.id = vof.order_id
     WHERE o.payment_status='paid'
       AND DATE_TRUNC('month', o.paid_at) = DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '1 month'"), 0, 0);

$biz_month = (int)pg_fetch_result(pg_query($conn,
    "SELECT COUNT(*) FROM businesses
     WHERE DATE_TRUNC('month', (SELECT created_at FROM users WHERE id = user_id))
         = DATE_TRUNC('month', CURRENT_DATE)"), 0, 0);

$biz_last_month = (int)pg_fetch_result(pg_query($conn,
    "SELECT COUNT(*) FROM businesses
     WHERE DATE_TRUNC('month', (SELECT created_at FROM users WHERE id = user_id))
         = DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '1 month'"), 0, 0);

$completed_setups = (int)pg_fetch_result(pg_query($conn,
    "SELECT COUNT(*) FROM businesses WHERE setup_status = 'completed'
     AND DATE_TRUNC('month', (SELECT created_at FROM users WHERE id = user_id))
         = DATE_TRUNC('month', CURRENT_DATE)"), 0, 0);

$orders_month = (int)pg_fetch_result(pg_query($conn,
    "SELECT COUNT(*) FROM orders
     WHERE DATE_TRUNC('month', order_date) = DATE_TRUNC('month', CURRENT_DATE)"), 0, 0);

$orders_last_month = (int)pg_fetch_result(pg_query($conn,
    "SELECT COUNT(*) FROM orders
     WHERE DATE_TRUNC('month', order_date) = DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '1 month'"), 0, 0);

$vendors_res = pg_query($conn, "SELECT COUNT(*) FROM vendors");
$active_vendors = $vendors_res ? (int)pg_fetch_result($vendors_res, 0, 0) : 0;
/* ---------- HELPERS ---------- */
function money($n) {
    return number_format((float)$n, 0) . " EGP";
}
function delta($now, $prev) {
    if ($prev == 0) return ['pct' => null, 'dir' => 'neutral'];
    $pct = round((($now - $prev) / $prev) * 100);
    return ['pct' => $pct, 'dir' => $pct >= 0 ? 'up' : 'down'];
}

$rev_delta    = delta($rev_month, $rev_last);
$profit_delta = delta($profit_month, $profit_last);
$biz_delta    = delta($biz_month, $biz_last_month);
$ord_delta    = delta($orders_month, $orders_last_month);

/* ---------- MONTHLY REVENUE CHART (last 6 months) ---------- */
$monthlyRes = pg_query($conn,
    "SELECT TO_CHAR(DATE_TRUNC('month', o.paid_at), 'Mon YY') AS lbl,
            COALESCE(SUM(vof.gross_amount),0)      AS sales,
            COALESCE(SUM(vof.commission_amount),0) AS profit
     FROM vendor_order_fulfillments vof
     JOIN orders o ON o.id = vof.order_id
     WHERE o.payment_status='paid'
       AND o.paid_at >= DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '5 months'
     GROUP BY DATE_TRUNC('month', o.paid_at)
     ORDER BY DATE_TRUNC('month', o.paid_at) ASC");
$mLabels = []; $mSales = []; $mProfit = [];
if ($monthlyRes) {
    while ($r = pg_fetch_assoc($monthlyRes)) {
        $mLabels[] = $r['lbl']; $mSales[] = (float)$r['sales']; $mProfit[] = (float)$r['profit'];
    }
}

/* ---------- BUSINESS TYPE BREAKDOWN ---------- */
$bizTypeRes = pg_query($conn,
    "SELECT COALESCE(business_type,'Other') AS btype, COUNT(*) AS total
     FROM businesses GROUP BY business_type ORDER BY total DESC LIMIT 6");
$btLabels = []; $btCounts = [];
if ($bizTypeRes) {
    while ($r = pg_fetch_assoc($bizTypeRes)) {
        $btLabels[] = ucfirst($r['btype']); $btCounts[] = (int)$r['total'];
    }
}

/* ---------- ORDER STATUS BREAKDOWN ---------- */
$orderStatusRes = pg_query($conn,
    "SELECT status, COUNT(*) AS total FROM orders GROUP BY status ORDER BY total DESC");
$osLabels = []; $osCounts = [];
if ($orderStatusRes) {
    while ($r = pg_fetch_assoc($orderStatusRes)) {
        $osLabels[] = ucfirst($r['status']); $osCounts[] = (int)$r['total'];
    }
}

/* ---------- TOP PRODUCTS ---------- */
$topProducts = pg_query($conn,
    "SELECT p.product_name, COUNT(oi.id) AS order_count
     FROM order_items oi
     JOIN products p ON p.id = oi.product_id
     GROUP BY p.product_name ORDER BY order_count DESC LIMIT 5");

/* ---------- TOP VENDORS ---------- */
$topVendors = pg_query($conn,
    "SELECT u.name AS vname,
            COUNT(DISTINCT vof.order_id) AS orders,
            COALESCE(SUM(vof.gross_amount),0) AS sales,
            COALESCE(SUM(vof.commission_amount),0) AS comm
     FROM vendor_order_fulfillments vof
     JOIN orders o ON o.id = vof.order_id
     JOIN users u ON u.id = vof.vendor_user_id
     WHERE o.payment_status='paid'
     GROUP BY u.id, u.name ORDER BY sales DESC LIMIT 5");

/* ---------- RECENT ORDERS ---------- */
$recentOrders = pg_query($conn,
    "SELECT o.id, o.order_date, o.status, o.payment_status,
            o.order_total, o.order_type, u.name AS bname
     FROM orders o
     LEFT JOIN users u ON u.id = o.business_user_id
     ORDER BY o.id DESC LIMIT 7");

/* ---------- CITIES BREAKDOWN ---------- */
$citiesRes = pg_query($conn,
    "SELECT COALESCE(u.city,'Unknown') AS city, COUNT(*) AS total
     FROM businesses b JOIN users u ON u.id = b.user_id
     WHERE u.city IS NOT NULL AND u.city != ''
     GROUP BY u.city ORDER BY total DESC LIMIT 6");
$cityMax = 1; $cities = [];
if ($citiesRes) {
    while ($r = pg_fetch_assoc($citiesRes)) { $cities[] = $r; }
    if (!empty($cities)) $cityMax = max(array_column($cities, 'total'));
}

/* ---------- SETUP FUNNEL ---------- */
$funnel_started   = (int)pg_fetch_result(pg_query($conn,
    "SELECT COUNT(*) FROM businesses WHERE setup_step > 0"), 0, 0);
$funnel_completed = (int)pg_fetch_result(pg_query($conn,
    "SELECT COUNT(*) FROM businesses WHERE setup_status = 'completed'"), 0, 0);
$funnel_ordered   = (int)pg_fetch_result(pg_query($conn,
    "SELECT COUNT(DISTINCT business_user_id) FROM orders WHERE business_user_id IS NOT NULL"), 0, 0);
$funnel_paid      = (int)pg_fetch_result(pg_query($conn,
    "SELECT COUNT(DISTINCT business_user_id) FROM orders
     WHERE payment_status = 'paid' AND business_user_id IS NOT NULL"), 0, 0);
$funnel_max = max($funnel_started, 1);
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Overview — SetupForge Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="admin.css" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>
</head>
<body>

<!-- ===== NAVBAR ===== -->
<nav class="ad-nav">
  <a href="admin_dashboard.php" class="ad-nav-left">
    <div class="ad-nav-logo">
      <img src="assets/images/Logo.png" alt="SetupForge">
    </div>
    <div class="ad-nav-brand">
      SetupForge
      <small>Admin Panel</small>
    </div>
  </a>

  <div class="ad-nav-stats">
    <div class="ad-nav-stat">
      <i class="bi bi-receipt ad-nav-stat-icon"></i>
      <div class="ad-nav-stat-divider"></div>
      <span class="ad-nav-stat-label">Orders today</span>
      <span class="ad-nav-stat-value"><?= $orders_today ?></span>
    </div>
    <div class="ad-nav-stat">
      <i class="bi bi-coin ad-nav-stat-icon"></i>
      <div class="ad-nav-stat-divider"></div>
      <span class="ad-nav-stat-label">Revenue today</span>
      <span class="ad-nav-stat-value"><?= money($revenue_today) ?></span>
    </div>
    <div class="ad-nav-stat">
      <i class="bi bi-person-check ad-nav-stat-icon"></i>
      <div class="ad-nav-stat-divider"></div>
      <span class="ad-nav-stat-label">Pending</span>
      <span class="ad-nav-stat-value <?= $pending_total > 0 ? 'is-alert' : '' ?>">
        <?= $pending_total ?>
      </span>
    </div>
  </div>

  <div class="ad-nav-right">
    <div class="ad-nav-icon-btn" tabindex="0" aria-label="Notifications">
      <i class="bi bi-bell"></i>
      <?php if ($pending_total > 0): ?>
        <div class="ad-nav-badge"><?= $pending_total ?></div>
      <?php endif; ?>
      <div class="ad-dropdown" style="min-width:240px">
        <div class="ad-dropdown-header">
          <div class="ad-dropdown-title">Needs attention</div>
        </div>
        <?php if ($pending_vendors > 0): ?>
        <a href="admin_approvals.php?tab=vendors" class="ad-dropdown-item">
          <i class="bi bi-shop"></i>
          <span>Vendor applications</span>
          <span class="ad-dd-count" style="background:var(--ad-warning-bg);color:var(--ad-warning-text)"><?= $pending_vendors ?></span>
        </a>
        <?php endif; ?>
        <?php if ($pending_labors > 0): ?>
        <a href="admin_approvals.php?tab=labor" class="ad-dropdown-item">
          <i class="bi bi-person-badge"></i>
          <span>Labor applications</span>
          <span class="ad-dd-count" style="background:var(--ad-warning-bg);color:var(--ad-warning-text)"><?= $pending_labors ?></span>
        </a>
        <?php endif; ?>
        <?php if ($stuck_orders > 0): ?>
        <a href="admin_orders.php?filter=unpaid" class="ad-dropdown-item">
          <i class="bi bi-exclamation-circle"></i>
          <span>Unpaid orders (3d+)</span>
          <span class="ad-dd-count" style="background:var(--ad-danger-bg);color:var(--ad-danger-text)"><?= $stuck_orders ?></span>
        </a>
        <?php endif; ?>
        <?php if ($pending_total == 0 && $stuck_orders == 0): ?>
        <div class="ad-dropdown-item" style="color:var(--ad-hint);cursor:default">
          <i class="bi bi-check-circle"></i> All clear
        </div>
        <?php endif; ?>
        <div class="ad-dropdown-footer">
          <a href="admin_approvals.php" class="ad-dropdown-item" style="justify-content:center;color:var(--ad-info-text)">
            View all approvals
          </a>
        </div>
      </div>
    </div>

    <div class="ad-nav-avatar" tabindex="0" aria-label="Admin profile">
      AD
      <div class="ad-dropdown" style="min-width:180px">
        <div class="ad-dropdown-header">
          <div class="ad-dropdown-title" style="font-size:13px;color:var(--ad-text);text-transform:none;letter-spacing:0">Admin</div>
          <div style="font-size:11px;color:var(--ad-hint);margin-top:2px">admin@setupforge.com</div>
        </div>
        <a href="#" class="ad-dropdown-item"><i class="bi bi-person"></i> My account</a>
        <div class="ad-dropdown-footer">
          <a href="auth/logout.php" class="ad-dropdown-item is-danger"><i class="bi bi-box-arrow-right"></i> Logout</a>
        </div>
      </div>
    </div>
  </div>
</nav>

<!-- ===== SHELL ===== -->
<div class="ad-shell">

  <!-- SIDEBAR -->
  <aside class="ad-sidebar">
    <div class="ad-sidebar-section">Main</div>
    <a href="admin_dashboard.php" class="ad-sidebar-link active">
      <i class="bi bi-grid-1x2"></i> Overview
    </a>
    <a href="admin_approvals.php" class="ad-sidebar-link">
      <i class="bi bi-person-check"></i> Approvals
      <?php if ($pending_total > 0): ?>
        <span class="ad-sidebar-badge"><?= $pending_total ?></span>
      <?php endif; ?>
    </a>

    <div class="ad-sidebar-section">Management</div>
    <a href="admin_businesses.php" class="ad-sidebar-link">
      <i class="bi bi-building"></i> Businesses
    </a>
    <a href="admin_users.php" class="ad-sidebar-link">
      <i class="bi bi-people"></i> All users
    </a>
    <a href="admin_vendors.php" class="ad-sidebar-link">
      <i class="bi bi-shop"></i> Vendors
    </a>
    <a href="admin_labor.php" class="ad-sidebar-link">
      <i class="bi bi-person-badge"></i> Labor
    </a>
    <a href="admin_products.php" class="ad-sidebar-link">
      <i class="bi bi-box-seam"></i> Products
    </a>
    <a href="admin_orders.php" class="ad-sidebar-link">
      <i class="bi bi-receipt"></i> Orders
    </a>

    <div class="ad-sidebar-section">Finance</div>
    <a href="admin_reports.php" class="ad-sidebar-link">
      <i class="bi bi-bar-chart-line"></i> Reports
    </a>
    <a href="admin_reports.php?export=1" class="ad-sidebar-link">
      <i class="bi bi-download"></i> Exports
    </a>

    <div class="ad-sidebar-section">System</div>
    <a href="admin_settings.php" class="ad-sidebar-link">
      <i class="bi bi-gear"></i> Settings
    </a>
  </aside>

  <!-- MAIN -->
  <main class="ad-main">

    <div class="ad-page-header">
      <div class="ad-page-title">Overview</div>
      <div class="ad-page-sub">Platform pulse — <?= date('l, d F Y') ?></div>
    </div>

    <!-- ALERT STRIP -->
    <?php if ($pending_vendors > 0 || $pending_labors > 0 || $stuck_orders > 0): ?>
    <div class="ad-alert is-warning">
      <i class="bi bi-exclamation-triangle-fill"></i>
      <div class="ad-alert-items">
        <?php if ($pending_vendors > 0): ?>
          <div class="ad-alert-item">
            <div class="ad-alert-dot"></div>
            <span><?= $pending_vendors ?> vendor <?= $pending_vendors == 1 ? 'application' : 'applications' ?> pending —
              <a href="admin_approvals.php?tab=vendors" class="ad-alert-link">review now</a>
            </span>
          </div>
        <?php endif; ?>
        <?php if ($pending_labors > 0): ?>
          <div class="ad-alert-item">
            <div class="ad-alert-dot"></div>
            <span><?= $pending_labors ?> labor <?= $pending_labors == 1 ? 'application' : 'applications' ?> waiting —
              <a href="admin_approvals.php?tab=labor" class="ad-alert-link">review now</a>
            </span>
          </div>
        <?php endif; ?>
        <?php if ($stuck_orders > 0): ?>
          <div class="ad-alert-item">
            <div class="ad-alert-dot"></div>
            <span><?= $stuck_orders ?> unpaid <?= $stuck_orders == 1 ? 'order' : 'orders' ?> older than 3 days —
              <a href="admin_orders.php?filter=unpaid" class="ad-alert-link">view orders</a>
            </span>
          </div>
        <?php endif; ?>
      </div>
    </div>
    <?php endif; ?>

    <!-- KPI CARDS -->
    <div class="ad-kpi-grid">

      <div class="ad-kpi">
        <div class="ad-kpi-icon blue"><i class="bi bi-currency-exchange"></i></div>
        <div class="ad-kpi-label">Revenue this month</div>
        <div class="ad-kpi-value is-money"><?= money($rev_month) ?></div>
        <?php if ($rev_delta['pct'] !== null): ?>
        <div class="ad-kpi-delta <?= $rev_delta['dir'] ?>">
          <i class="bi bi-arrow-<?= $rev_delta['dir'] == 'up' ? 'up' : 'down' ?>-short"></i>
          <?= abs($rev_delta['pct']) ?>% vs last month
        </div>
        <?php endif; ?>
        <div class="ad-kpi-sub">Paid orders only</div>
      </div>

      <div class="ad-kpi">
        <div class="ad-kpi-icon green"><i class="bi bi-graph-up-arrow"></i></div>
        <div class="ad-kpi-label">Platform profit</div>
        <div class="ad-kpi-value is-money"><?= money($profit_month) ?></div>
        <?php if ($profit_delta['pct'] !== null): ?>
        <div class="ad-kpi-delta <?= $profit_delta['dir'] ?>">
          <i class="bi bi-arrow-<?= $profit_delta['dir'] == 'up' ? 'up' : 'down' ?>-short"></i>
          <?= abs($profit_delta['pct']) ?>% vs last month
        </div>
        <?php endif; ?>
        <div class="ad-kpi-sub">Commissions earned</div>
      </div>

      <div class="ad-kpi">
        <div class="ad-kpi-icon blue"><i class="bi bi-building-add"></i></div>
        <div class="ad-kpi-label">New businesses</div>
        <div class="ad-kpi-value"><?= $biz_month ?></div>
        <?php if ($biz_delta['pct'] !== null): ?>
        <div class="ad-kpi-delta <?= $biz_delta['dir'] ?>">
          <i class="bi bi-arrow-<?= $biz_delta['dir'] == 'up' ? 'up' : 'down' ?>-short"></i>
          <?= abs($biz_delta['pct']) ?>% vs last month
        </div>
        <?php endif; ?>
        <div class="ad-kpi-sub">Registered this month</div>
      </div>

      <div class="ad-kpi">
        <div class="ad-kpi-icon green"><i class="bi bi-check2-all"></i></div>
        <div class="ad-kpi-label">Completed setups</div>
        <div class="ad-kpi-value"><?= $completed_setups ?></div>
        <div class="ad-kpi-delta neutral">
          <?= $biz_month > 0 ? round($completed_setups / $biz_month * 100) : 0 ?>% completion rate
        </div>
        <div class="ad-kpi-sub">Of <?= $biz_month ?> new this month</div>
      </div>

      <div class="ad-kpi">
        <div class="ad-kpi-icon amber"><i class="bi bi-receipt"></i></div>
        <div class="ad-kpi-label">Orders this month</div>
        <div class="ad-kpi-value"><?= $orders_month ?></div>
        <?php if ($ord_delta['pct'] !== null): ?>
        <div class="ad-kpi-delta <?= $ord_delta['dir'] ?>">
          <i class="bi bi-arrow-<?= $ord_delta['dir'] == 'up' ? 'up' : 'down' ?>-short"></i>
          <?= abs($ord_delta['pct']) ?>% vs last month
        </div>
        <?php endif; ?>
        <div class="ad-kpi-sub">Setup + shop orders</div>
      </div>

      <div class="ad-kpi">
        <div class="ad-kpi-icon blue"><i class="bi bi-shop"></i></div>
        <div class="ad-kpi-label">Active vendors</div>
        <div class="ad-kpi-value"><?= $active_vendors ?></div>
        <?php if ($pending_vendors > 0): ?>
        <div class="ad-kpi-delta neutral"><?= $pending_vendors ?> pending approval</div>
        <?php endif; ?>
        <div class="ad-kpi-sub">Approved only</div>
      </div>

    </div>

    <!-- CHARTS ROW 1: revenue trend + business types -->
    <div class="ad-section-title">
      Trends & distribution
      <a href="admin_reports.php">Full reports →</a>
    </div>
    <div class="ad-chart-grid cols-2" style="margin-bottom:24px">

      <div class="ad-box">
        <div class="ad-box-head">
          <div>
            <div class="ad-box-title">Revenue trend</div>
            <div class="ad-box-sub">Last 6 months · paid orders only</div>
          </div>
          <span class="ad-pill info">EGP</span>
        </div>
        <div class="ad-box-body">
          <div class="ad-chart-wrap">
            <canvas id="revenueChart"></canvas>
          </div>
        </div>
      </div>

      <div class="ad-box">
        <div class="ad-box-head">
          <div>
            <div class="ad-box-title">Business types</div>
            <div class="ad-box-sub">All registered businesses</div>
          </div>
        </div>
        <div class="ad-box-body">
          <div class="ad-chart-wrap">
            <canvas id="bizTypeChart"></canvas>
          </div>
        </div>
      </div>

    </div>

    <!-- CHARTS ROW 2: funnel + cities -->
    <div class="ad-chart-grid cols-2" style="margin-bottom:24px">

      <div class="ad-box">
        <div class="ad-box-head">
          <div>
            <div class="ad-box-title">Setup funnel</div>
            <div class="ad-box-sub">All time · how many businesses convert to paid</div>
          </div>
        </div>
        <div class="ad-box-body">
          <div class="ad-funnel-row">
            <div class="ad-funnel-label">Started wizard</div>
            <div class="ad-funnel-track"><div class="ad-funnel-fill" style="width:100%"></div></div>
            <div class="ad-funnel-val"><?= $funnel_started ?></div>
          </div>
          <div class="ad-funnel-row">
            <div class="ad-funnel-label">Completed wizard</div>
            <div class="ad-funnel-track"><div class="ad-funnel-fill" style="width:<?= $funnel_started > 0 ? round($funnel_completed/$funnel_started*100) : 0 ?>%;opacity:.85"></div></div>
            <div class="ad-funnel-val"><?= $funnel_completed ?></div>
          </div>
          <div class="ad-funnel-row">
            <div class="ad-funnel-label">Placed order</div>
            <div class="ad-funnel-track"><div class="ad-funnel-fill" style="width:<?= $funnel_started > 0 ? round($funnel_ordered/$funnel_started*100) : 0 ?>%;opacity:.7"></div></div>
            <div class="ad-funnel-val"><?= $funnel_ordered ?></div>
          </div>
          <div class="ad-funnel-row">
            <div class="ad-funnel-label">Paid</div>
            <div class="ad-funnel-track"><div class="ad-funnel-fill" style="width:<?= $funnel_started > 0 ? round($funnel_paid/$funnel_started*100) : 0 ?>%;opacity:.55"></div></div>
            <div class="ad-funnel-val"><?= $funnel_paid ?></div>
          </div>
          <div style="margin-top:14px;font-size:12px;color:var(--ad-hint)">
            <?php
              $conv = $funnel_started > 0 ? round($funnel_paid/$funnel_started*100) : 0;
              echo $conv . '% of started setups convert to a paid order';
            ?>
          </div>
        </div>
      </div>

      <div class="ad-box">
        <div class="ad-box-head">
          <div>
            <div class="ad-box-title">Businesses by city</div>
            <div class="ad-box-sub">Top 6 cities</div>
          </div>
        </div>
        <div class="ad-box-body">
          <?php foreach ($cities as $c): ?>
          <div class="ad-hbar-row">
            <div class="ad-hbar-label"><?= htmlspecialchars($c['city']) ?></div>
            <div class="ad-hbar-track">
              <div class="ad-hbar-fill" style="width:<?= round($c['total']/$cityMax*100) ?>%"></div>
            </div>
            <div class="ad-hbar-val"><?= $c['total'] ?></div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($cities)): ?>
            <div class="ad-empty"><i class="bi bi-geo-alt"></i><p>No city data yet</p></div>
          <?php endif; ?>
        </div>
      </div>

    </div>

    <!-- TABLES ROW: top vendors + recent orders -->
    <div class="ad-section-title">
      Activity
      <a href="admin_orders.php">All orders →</a>
    </div>
    <div class="ad-chart-grid cols-2" style="margin-bottom:24px">

      <div class="ad-box">
        <div class="ad-box-head">
          <div class="ad-box-title">Top vendors by sales</div>
          <span class="ad-pill info">Paid only</span>
        </div>
        <div class="ad-box-body no-pad">
          <div class="ad-table-wrap">
            <table class="ad-table">
              <thead>
                <tr>
                  <th>Vendor</th>
                  <th>Orders</th>
                  <th>Sales</th>
                  <th>Commission</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($topVendors && pg_num_rows($topVendors) > 0): ?>
                  <?php while ($r = pg_fetch_assoc($topVendors)): ?>
                  <tr>
                    <td><strong><?= htmlspecialchars($r['vname']) ?></strong></td>
                    <td class="mono"><?= (int)$r['orders'] ?></td>
                    <td class="mono"><?= money($r['sales']) ?></td>
                    <td class="mono"><?= money($r['comm']) ?></td>
                  </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr><td colspan="4"><div class="ad-empty"><p>No vendor data yet</p></div></td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

      <div class="ad-box">
        <div class="ad-box-head">
          <div class="ad-box-title">Recent orders</div>
          <span class="ad-pill neutral">Latest 7</span>
        </div>
        <div class="ad-box-body no-pad">
          <div class="ad-table-wrap">
            <table class="ad-table">
              <thead>
                <tr>
                  <th>ID</th>
                  <th>Business</th>
                  <th>Total</th>
                  <th>Payment</th>
                </tr>
              </thead>
              <tbody>
                <?php if ($recentOrders && pg_num_rows($recentOrders) > 0): ?>
                  <?php while ($r = pg_fetch_assoc($recentOrders)):
                    $pstatus = $r['payment_status'] ?? 'pending';
                    $pillClass = $pstatus === 'paid' ? 'success' : ($pstatus === 'pending' ? 'warning' : 'danger');
                  ?>
                  <tr>
                    <td><span class="mono">#<?= $r['id'] ?></span></td>
                    <td><strong><?= htmlspecialchars($r['bname'] ?? '—') ?></strong></td>
                    <td class="mono"><?= money($r['order_total']) ?></td>
                    <td><span class="ad-pill <?= $pillClass ?>"><?= ucfirst($pstatus) ?></span></td>
                  </tr>
                  <?php endwhile; ?>
                <?php else: ?>
                  <tr><td colspan="4"><div class="ad-empty"><p>No orders yet</p></div></td></tr>
                <?php endif; ?>
              </tbody>
            </table>
          </div>
        </div>
      </div>

    </div>

    <!-- CHARTS ROW 3: top products + order status -->
    <div class="ad-chart-grid cols-2">

      <div class="ad-box">
        <div class="ad-box-head">
          <div>
            <div class="ad-box-title">Top products ordered</div>
            <div class="ad-box-sub">By frequency across all orders</div>
          </div>
        </div>
        <div class="ad-box-body">
          <?php
          $maxProd = 1;
          $prodRows = [];
          if ($topProducts && pg_num_rows($topProducts) > 0) {
              while ($r = pg_fetch_assoc($topProducts)) { $prodRows[] = $r; }
              $maxProd = max(array_column($prodRows, 'order_count'));
          }
          ?>
          <?php foreach ($prodRows as $r): ?>
          <div class="ad-hbar-row">
            <div class="ad-hbar-label" style="width:140px"><?= htmlspecialchars($r['product_name']) ?></div>
            <div class="ad-hbar-track">
              <div class="ad-hbar-fill" style="width:<?= round($r['order_count']/$maxProd*100) ?>%"></div>
            </div>
            <div class="ad-hbar-val"><?= $r['order_count'] ?></div>
          </div>
          <?php endforeach; ?>
          <?php if (empty($prodRows)): ?>
            <div class="ad-empty"><i class="bi bi-box-seam"></i><p>No product orders yet</p></div>
          <?php endif; ?>
        </div>
      </div>

      <div class="ad-box">
        <div class="ad-box-head">
          <div>
            <div class="ad-box-title">Orders by status</div>
            <div class="ad-box-sub">All time distribution</div>
          </div>
        </div>
        <div class="ad-box-body">
          <div class="ad-chart-wrap">
            <canvas id="orderStatusChart"></canvas>
          </div>
        </div>
      </div>

    </div>

  </main>
</div>

<script>
const chartDefaults = {
  responsive: true,
  maintainAspectRatio: true,
  plugins: {
    legend: { labels: { color: '#5a6a7e', font: { family: "'DM Sans'", size: 12, weight: '500' }, boxWidth: 10, padding: 14 } },
    tooltip: {
      backgroundColor: '#0d1b2a',
      titleColor: '#fff',
      bodyColor: '#8fa0b4',
      padding: 10,
      cornerRadius: 8
    }
  }
};

new Chart(document.getElementById('revenueChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($mLabels) ?>,
    datasets: [
      {
        label: 'Sales',
        data: <?= json_encode($mSales) ?>,
        backgroundColor: 'rgba(0,76,172,0.85)',
        borderRadius: 6,
        borderSkipped: false,
      },
      {
        label: 'Profit',
        data: <?= json_encode($mProfit) ?>,
        backgroundColor: 'rgba(10,122,69,0.75)',
        borderRadius: 6,
        borderSkipped: false,
      }
    ]
  },
  options: {
    ...chartDefaults,
    scales: {
      x: { ticks: { color: '#8fa0b4', font: { family: "'DM Sans'" } }, grid: { display: false }, border: { display: false } },
      y: { beginAtZero: true, ticks: { color: '#8fa0b4', font: { family: "'DM Sans'" }, callback: v => v.toLocaleString() }, grid: { color: 'rgba(0,0,0,.05)' }, border: { display: false } }
    }
  }
});

new Chart(document.getElementById('bizTypeChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($btLabels) ?>,
    datasets: [{
      data: <?= json_encode($btCounts) ?>,
      backgroundColor: ['#004cac','#1D9E75','#EF9F27','#E24B4A','#7F77DD','#8fa0b4'],
      borderWidth: 2,
      borderColor: '#fff',
      hoverOffset: 4
    }]
  },
  options: {
    ...chartDefaults,
    cutout: '62%',
    plugins: { ...chartDefaults.plugins, legend: { ...chartDefaults.plugins.legend, position: 'right' } }
  }
});

new Chart(document.getElementById('orderStatusChart'), {
  type: 'doughnut',
  data: {
    labels: <?= json_encode($osLabels) ?>,
    datasets: [{
      data: <?= json_encode($osCounts) ?>,
      backgroundColor: ['#004cac','#EF9F27','#E24B4A','#1D9E75','#7F77DD','#8fa0b4'],
      borderWidth: 2,
      borderColor: '#fff',
      hoverOffset: 4
    }]
  },
  options: {
    ...chartDefaults,
    cutout: '62%',
    plugins: { ...chartDefaults.plugins, legend: { ...chartDefaults.plugins.legend, position: 'right' } }
  }
});
</script>

</body>
</html>
