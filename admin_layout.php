<?php
/* admin_layout.php — include at top of every admin page
   Usage:
     $ad_page = 'approvals';   // set before including
     require 'admin_layout.php';
   Then close with </div></div> at the bottom of your page.
*/
if (!isset($ad_page)) $ad_page = '';

/* Navbar stats (shared across all pages) */
$_nav_orders_today = (int)pg_fetch_result(pg_query($conn,
    "SELECT COUNT(*) FROM orders WHERE DATE(order_date) = CURRENT_DATE"), 0, 0);
$_nav_revenue_res = pg_query($conn,
    "SELECT COALESCE(SUM(vof.gross_amount),0)
     FROM vendor_order_fulfillments vof
     JOIN orders o ON o.id = vof.order_id
     WHERE o.payment_status='paid' AND DATE(o.paid_at) = CURRENT_DATE");
$_nav_revenue_today = (float)pg_fetch_result($_nav_revenue_res, 0, 0);
$_nav_pending = (int)pg_fetch_result(pg_query($conn,
    "SELECT (SELECT COUNT(*) FROM vendors WHERE status='pending') +
            (SELECT COUNT(*) FROM labors  WHERE status='pending')"), 0, 0);
$_nav_pv = (int)pg_fetch_result(pg_query($conn,
    "SELECT COUNT(*) FROM vendors WHERE status='pending'"), 0, 0);
$_nav_pl = (int)pg_fetch_result(pg_query($conn,
    "SELECT COUNT(*) FROM labors WHERE status='pending'"), 0, 0);
$_nav_stuck = (int)pg_fetch_result(pg_query($conn,
    "SELECT COUNT(*) FROM orders
     WHERE payment_status != 'paid' AND order_date <= NOW() - INTERVAL '3 days'"), 0, 0);

function _money_nav($n) { return number_format((float)$n, 0) . ' EGP'; }
function _is_active($key, $page) { return $key === $page ? ' active' : ''; }
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title><?= htmlspecialchars($ad_title ?? 'Admin') ?> — SetupForge Admin</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link href="https://fonts.googleapis.com/css2?family=DM+Sans:wght@400;500;600&family=DM+Mono:wght@400;500&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="admin.css" rel="stylesheet">
  <?php if (!empty($ad_extra_head)) echo $ad_extra_head; ?>
</head>
<body>

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
      <span class="ad-nav-stat-value"><?= $_nav_orders_today ?></span>
    </div>
    <div class="ad-nav-stat">
      <i class="bi bi-coin ad-nav-stat-icon"></i>
      <div class="ad-nav-stat-divider"></div>
      <span class="ad-nav-stat-label">Revenue today</span>
      <span class="ad-nav-stat-value"><?= _money_nav($_nav_revenue_today) ?></span>
    </div>
    <div class="ad-nav-stat">
      <i class="bi bi-person-check ad-nav-stat-icon"></i>
      <div class="ad-nav-stat-divider"></div>
      <span class="ad-nav-stat-label">Pending</span>
      <span class="ad-nav-stat-value <?= $_nav_pending > 0 ? 'is-alert' : '' ?>"><?= $_nav_pending ?></span>
    </div>
  </div>

  <div class="ad-nav-right">
    <div class="ad-nav-icon-btn" tabindex="0" aria-label="Notifications">
      <i class="bi bi-bell"></i>
      <?php if ($_nav_pending > 0 || $_nav_stuck > 0): ?>
        <div class="ad-nav-badge"><?= $_nav_pending + $_nav_stuck ?></div>
      <?php endif; ?>
      <div class="ad-dropdown" style="min-width:240px">
        <div class="ad-dropdown-header">
          <div class="ad-dropdown-title">Needs attention</div>
        </div>
        <?php if ($_nav_pv > 0): ?>
        <a href="admin_approvals.php?tab=vendors" class="ad-dropdown-item">
          <i class="bi bi-shop"></i><span>Vendor applications</span>
          <span class="ad-dd-count" style="background:var(--ad-warning-bg);color:var(--ad-warning-text)"><?= $_nav_pv ?></span>
        </a>
        <?php endif; ?>
        <?php if ($_nav_pl > 0): ?>
        <a href="admin_approvals.php?tab=labor" class="ad-dropdown-item">
          <i class="bi bi-person-badge"></i><span>Labor applications</span>
          <span class="ad-dd-count" style="background:var(--ad-warning-bg);color:var(--ad-warning-text)"><?= $_nav_pl ?></span>
        </a>
        <?php endif; ?>
        <?php if ($_nav_stuck > 0): ?>
        <a href="admin_orders.php?filter=unpaid" class="ad-dropdown-item">
          <i class="bi bi-exclamation-circle"></i><span>Unpaid orders (3d+)</span>
          <span class="ad-dd-count" style="background:var(--ad-danger-bg);color:var(--ad-danger-text)"><?= $_nav_stuck ?></span>
        </a>
        <?php endif; ?>
        <?php if ($_nav_pending == 0 && $_nav_stuck == 0): ?>
        <div class="ad-dropdown-item" style="color:var(--ad-hint);cursor:default">
          <i class="bi bi-check-circle"></i> All clear
        </div>
        <?php endif; ?>
        <div class="ad-dropdown-footer">
          <a href="admin_approvals.php" class="ad-dropdown-item" style="justify-content:center;color:var(--ad-info-text)">View all approvals</a>
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

<div class="ad-shell">
  <aside class="ad-sidebar">
    <div class="ad-sidebar-section">Main</div>
    <a href="admin_dashboard.php"  class="ad-sidebar-link<?= _is_active('overview',   $ad_page) ?>"><i class="bi bi-grid-1x2"></i> Overview</a>
    <a href="admin_approvals.php"  class="ad-sidebar-link<?= _is_active('approvals',  $ad_page) ?>">
      <i class="bi bi-person-check"></i> Approvals
      <?php if ($_nav_pending > 0): ?><span class="ad-sidebar-badge"><?= $_nav_pending ?></span><?php endif; ?>
    </a>

    <div class="ad-sidebar-section">Management</div>
    <a href="admin_businesses.php" class="ad-sidebar-link<?= _is_active('businesses', $ad_page) ?>"><i class="bi bi-building"></i> Businesses</a>
    <a href="admin_users.php"      class="ad-sidebar-link<?= _is_active('users',      $ad_page) ?>"><i class="bi bi-people"></i> All users</a>
    <a href="admin_vendors.php"    class="ad-sidebar-link<?= _is_active('vendors',    $ad_page) ?>"><i class="bi bi-shop"></i> Vendors</a>
    <a href="admin_labor.php"      class="ad-sidebar-link<?= _is_active('labor',      $ad_page) ?>"><i class="bi bi-person-badge"></i> Labor</a>
    <a href="admin_products.php"   class="ad-sidebar-link<?= _is_active('products',   $ad_page) ?>"><i class="bi bi-box-seam"></i> Products</a>
    <a href="admin_orders.php"     class="ad-sidebar-link<?= _is_active('orders',     $ad_page) ?>"><i class="bi bi-receipt"></i> Orders</a>

    <div class="ad-sidebar-section">Finance</div>
    <a href="admin_reports.php"    class="ad-sidebar-link<?= _is_active('reports',    $ad_page) ?>"><i class="bi bi-bar-chart-line"></i> Reports</a>
    <a href="admin_reports.php?export=1" class="ad-sidebar-link<?= _is_active('exports', $ad_page) ?>"><i class="bi bi-download"></i> Exports</a>

    <div class="ad-sidebar-section">System</div>
    <a href="admin_settings.php"   class="ad-sidebar-link<?= _is_active('settings',  $ad_page) ?>"><i class="bi bi-gear"></i> Settings</a>
  </aside>

  <main class="ad-main">
<?php /* page content starts here — close with </main></div></body></html> */ ?>
