<?php
session_start();
require "db.php";

/* ---------- ADMIN CHECK ---------- */
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? "") !== "admin") {
    header("Location: auth/login.php");
    exit();
}

/* ---------- QUICK STATS ---------- */
$totalBusinesses = pg_fetch_result(pg_query($conn, "
    SELECT COUNT(*) FROM businesses
"), 0, 0);

$totalVendors = pg_fetch_result(pg_query($conn, "
    SELECT COUNT(*) FROM vendors
"), 0, 0);

$totalLabors = pg_fetch_result(pg_query($conn, "
    SELECT COUNT(*) FROM labors
"), 0, 0);

$totalOrders = pg_fetch_result(pg_query($conn, "
    SELECT COUNT(*) FROM orders
"), 0, 0);

$totalProducts = pg_fetch_result(pg_query($conn, "
    SELECT COUNT(*) FROM products
"), 0, 0);

$pendingVendorApprovals = pg_fetch_result(pg_query($conn, "
    SELECT COUNT(*) FROM vendors WHERE status = 'pending'
"), 0, 0);

$pendingLaborApprovals = pg_fetch_result(pg_query($conn, "
    SELECT COUNT(*) FROM labors WHERE status = 'pending'
"), 0, 0);

/* ---------- BUSINESS SETUPS THIS MONTH ---------- */
$businessSetupsThisMonth = pg_fetch_result(pg_query($conn, "
    SELECT COUNT(DISTINCT business_user_id)
    FROM orders
    WHERE business_user_id IS NOT NULL
      AND DATE_TRUNC('month', order_date) = DATE_TRUNC('month', CURRENT_DATE)
"), 0, 0);

/* ---------- MARKETPLACE FINANCIALS (paid orders only) ---------- */
$financialsRes = pg_query($conn, "
    SELECT
        COALESCE(SUM(vof.gross_amount), 0)      AS total_marketplace_sales,
        COALESCE(SUM(vof.commission_amount), 0) AS total_platform_profit,
        COALESCE(SUM(vof.vendor_payout), 0)     AS total_vendor_payouts
    FROM vendor_order_fulfillments vof
    JOIN orders o ON o.id = vof.order_id
    WHERE o.payment_status = 'paid'
");
$financials = $financialsRes ? pg_fetch_assoc($financialsRes) : [];

$totalMarketplaceSales = (float)($financials["total_marketplace_sales"] ?? 0);
$totalPlatformProfit   = (float)($financials["total_platform_profit"]   ?? 0);
$totalVendorPayouts    = (float)($financials["total_vendor_payouts"]    ?? 0);

/* ---------- MONTHLY REVENUE TREND (last 6 months, paid only) ---------- */
$monthlyRes = pg_query($conn, "
    SELECT
        TO_CHAR(DATE_TRUNC('month', o.paid_at), 'Mon YYYY') AS month_label,
        DATE_TRUNC('month', o.paid_at)                      AS month_ts,
        COALESCE(SUM(vof.gross_amount), 0)                  AS sales,
        COALESCE(SUM(vof.commission_amount), 0)             AS profit
    FROM vendor_order_fulfillments vof
    JOIN orders o ON o.id = vof.order_id
    WHERE o.payment_status = 'paid'
      AND o.paid_at >= DATE_TRUNC('month', CURRENT_DATE) - INTERVAL '5 months'
    GROUP BY DATE_TRUNC('month', o.paid_at)
    ORDER BY month_ts ASC
");

$monthlyLabels  = [];
$monthlySales   = [];
$monthlyProfit  = [];

if ($monthlyRes) {
    while ($row = pg_fetch_assoc($monthlyRes)) {
        $monthlyLabels[] = $row["month_label"];
        $monthlySales[]  = (float)$row["sales"];
        $monthlyProfit[] = (float)$row["profit"];
    }
}

/* ---------- PER-VENDOR COMMISSION BREAKDOWN (paid only) ---------- */
$vendorBreakdownRes = pg_query($conn, "
    SELECT
        u.name                                  AS vendor_name,
        u.email                                 AS vendor_email,
        COUNT(DISTINCT vof.order_id)            AS order_count,
        COALESCE(SUM(vof.gross_amount), 0)      AS gross_amount,
        COALESCE(AVG(vof.commission_rate), 0)   AS avg_commission_rate,
        COALESCE(SUM(vof.commission_amount), 0) AS commission_amount,
        COALESCE(SUM(vof.vendor_payout), 0)     AS vendor_payout
    FROM vendor_order_fulfillments vof
    JOIN orders o ON o.id = vof.order_id
    JOIN users u ON u.id = vof.vendor_user_id
    WHERE o.payment_status = 'paid'
    GROUP BY u.id, u.name, u.email
    ORDER BY gross_amount DESC
");

/* ---------- TOP VENDORS BY SALES (paid only, top 5) ---------- */
$topVendorsRes = pg_query($conn, "
    SELECT
        u.name                             AS vendor_name,
        COALESCE(SUM(vof.gross_amount), 0) AS total_sales
    FROM vendor_order_fulfillments vof
    JOIN orders o ON o.id = vof.order_id
    JOIN users u ON u.id = vof.vendor_user_id
    WHERE o.payment_status = 'paid'
    GROUP BY u.id, u.name
    ORDER BY total_sales DESC
    LIMIT 5
");

$topVendorLabels = [];
$topVendorSales  = [];

if ($topVendorsRes) {
    while ($row = pg_fetch_assoc($topVendorsRes)) {
        $topVendorLabels[] = $row["vendor_name"];
        $topVendorSales[]  = (float)$row["total_sales"];
    }
}

/* ---------- ORDERS BY STATUS ---------- */
$orderStatusResult = pg_query($conn, "
    SELECT status, COUNT(*) AS total
    FROM orders
    GROUP BY status
    ORDER BY status
");

$orderStatusLabels = [];
$orderStatusCounts = [];

if ($orderStatusResult) {
    while ($row = pg_fetch_assoc($orderStatusResult)) {
        $orderStatusLabels[] = $row["status"];
        $orderStatusCounts[] = (int)$row["total"];
    }
}

/* ---------- RECENT ORDERS ---------- */
$recentOrders = pg_query($conn, "
    SELECT
        o.id,
        o.order_date,
        o.status,
        o.payment_status,
        o.order_total,
        u.name AS business_name
    FROM orders o
    LEFT JOIN users u ON u.id = o.business_user_id
    ORDER BY o.id DESC
    LIMIT 5
");

/* ---------- PENDING VENDORS ---------- */
$pendingVendors = pg_query($conn, "
    SELECT
        u.id,
        u.name,
        u.email,
        v.items_type,
        v.status
    FROM vendors v
    INNER JOIN users u ON u.id = v.user_id
    WHERE v.status = 'pending'
    ORDER BY u.id DESC
    LIMIT 5
");

/* ---------- PENDING LABORS ---------- */
$pendingLabors = pg_query($conn, "
    SELECT
        u.id,
        u.name,
        u.email,
        l.skills,
        l.status
    FROM labors l
    INNER JOIN users u ON u.id = l.user_id
    WHERE l.status = 'pending'
    ORDER BY u.id DESC
    LIMIT 5
");

function money($n){
    return number_format((float)$n, 0) . " EGP";
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Admin Dashboard - SetupForge</title>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="admin.css?v=2" rel="stylesheet">
  <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>

  <style>
    .admin-hero-badge{
      display:inline-flex;
      align-items:center;
      gap:8px;
      padding:8px 12px;
      border-radius:999px;
      background:rgba(0,76,172,.06);
      color:#004cac;
      border:1px solid rgba(0,76,172,.10);
      font-size:12px;
      font-weight:800;
      margin-bottom:14px;
    }
    .admin-hero-badge::before{
      content:"";
      width:8px;
      height:8px;
      border-radius:50%;
      background:linear-gradient(135deg,#004cac,#009994);
      box-shadow:0 0 0 4px rgba(0,153,148,.10);
    }
    .admin-topbar-actions{
      display:flex;
      flex-wrap:wrap;
      gap:10px;
    }
    .admin-soft-chip{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:10px 14px;
      border-radius:999px;
      background:#fff;
      color:#004cac;
      border:1px solid rgba(0,76,172,.10);
      box-shadow:0 8px 18px rgba(15,23,42,.05);
      font-size:13px;
      font-weight:800;
      white-space:nowrap;
    }
    .admin-summary-row{
      display:grid;
      grid-template-columns:repeat(3,minmax(0,1fr));
      gap:18px;
      margin:0 0 28px;
    }
    .admin-summary-card{
      padding:18px 20px;
      border-radius:20px;
      background:#fff;
      border:1px solid rgba(15,23,42,.06);
      box-shadow:0 14px 28px rgba(15,23,42,.05);
    }
    .admin-summary-label{
      margin:0 0 6px;
      font-size:12px;
      font-weight:800;
      color:#64748b;
      text-transform:uppercase;
      letter-spacing:.05em;
    }
    .admin-summary-value{
      font-size:28px;
      font-weight:900;
      line-height:1;
      letter-spacing:-.03em;
      color:#0f1f43;
    }
    .admin-summary-sub{
      font-size:11px;
      color:#94a3b8;
      font-weight:600;
      margin-top:6px;
    }
    .admin-cards.admin-cards-financial{
      grid-template-columns:repeat(3,1fr);
    }
    .admin-chart-box canvas{
      max-height:320px;
    }
    .admin-status-pill.is-payment{
      background:rgba(0,153,148,.08);
      border-color:rgba(0,153,148,.12);
      color:#087a75;
    }
    .admin-table strong{
      color:#0f172a;
      font-weight:900;
    }
    .admin-profile-label{
      display:none;
    }
    .admin-section-title{
      font-size:13px;
      font-weight:800;
      color:#64748b;
      text-transform:uppercase;
      letter-spacing:.06em;
      margin:32px 0 14px;
      display:flex;
      align-items:center;
      gap:8px;
    }
    .admin-section-title::after{
      content:"";
      flex:1;
      height:1px;
      background:rgba(15,23,42,.06);
    }
    .commission-rate-badge{
      display:inline-block;
      padding:2px 8px;
      border-radius:999px;
      background:rgba(0,153,148,.08);
      color:#087a75;
      font-size:11px;
      font-weight:800;
    }
    @media(max-width:1100px){
      .admin-summary-row,
      .admin-cards.admin-cards-financial{
        grid-template-columns:1fr;
      }
    }
  </style>
</head>
<body>

<!-- NAVBAR -->
<nav class="navbar navbar-expand-lg navbar-dark sf-navbar">
  <div class="container d-flex align-items-center">
    <div class="d-flex align-items-center flex-grow-1">
      <a class="navbar-brand d-flex align-items-center gap-2" href="admin_dashboard.php">
        <div class="sf-logo"><img src="assets/images/Logo.png" alt="SetupForge Logo"></div>
        <span class="fw-bold text-white">SetupForge</span>
      </a>
    </div>
    <div class="d-none d-lg-flex justify-content-center flex-grow-1">
      <ul class="navbar-nav align-items-center gap-3">
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle sf-navlink" href="products.php" data-bs-toggle="dropdown">Products</a>
          <ul class="dropdown-menu sf-dropdown">
            <li><span class="dropdown-item-text text-muted">Coming soon</span></li>
          </ul>
        </li>
        <li class="nav-item">
          <a class="nav-link sf-navlink" href="services.php">Services</a>
        </li>
        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle sf-navlink" href="#" data-bs-toggle="dropdown">Resources</a>
          <ul class="dropdown-menu sf-dropdown">
            <li><a class="dropdown-item" href="help-center.php">Help Center</a></li>
            <li><a class="dropdown-item" href="faq.php">FAQ</a></li>
            <li><a class="dropdown-item" href="about.php">About Us</a></li>
          </ul>
        </li>
      </ul>
    </div>
    <div class="d-flex justify-content-end flex-grow-1 gap-2">
      <div class="admin-profile">
        <a href="#" class="admin-profile-btn" aria-label="Admin Profile">
          <svg viewBox="0 0 24 24" fill="none" aria-hidden="true">
            <path d="M12 12a4 4 0 1 0 0-8 4 4 0 0 0 0 8Z" fill="currentColor"/>
            <path d="M4 20a8 8 0 0 1 16 0" stroke="currentColor" stroke-width="2" stroke-linecap="round"/>
          </svg>
        </a>
        <div class="admin-profile-menu">
          <a class="admin-profile-link logout" href="auth/logout.php">Logout</a>
        </div>
      </div>
    </div>
  </div>
</nav>

<div class="admin-shell">
  <div class="admin-container">

    <div class="admin-topbar">
      <div>
        <div class="admin-hero-badge">Admin Control Center</div>
        <h1>Welcome, Admin</h1>
        <p class="mb-0 text-muted">Overview of SetupForge operations, approvals, and marketplace revenue.</p>
      </div>
      <div class="admin-topbar-actions">
        <span class="admin-soft-chip">Operations Overview</span>
        <span class="admin-soft-chip">Revenue Monitoring</span>
      </div>
    </div>

    <!-- REVENUE SUMMARY (paid orders only) -->
    <div class="admin-summary-row">
      <div class="admin-summary-card">
        <div class="admin-summary-label">Marketplace Sales</div>
        <div class="admin-summary-value"><?php echo money($totalMarketplaceSales); ?></div>
        <div class="admin-summary-sub">Paid orders only</div>
      </div>
      <div class="admin-summary-card">
        <div class="admin-summary-label">Platform Profit</div>
        <div class="admin-summary-value"><?php echo money($totalPlatformProfit); ?></div>
        <div class="admin-summary-sub">Commission earned · paid orders only</div>
      </div>
      <div class="admin-summary-card">
        <div class="admin-summary-label">Business Setups This Month</div>
        <div class="admin-summary-value"><?php echo $businessSetupsThisMonth; ?></div>
        <div class="admin-summary-sub">Unique businesses · current month</div>
      </div>
    </div>

    <!-- OPERATIONS CARDS (removed duplicate Business Setups) -->
    <div class="admin-section-title">Operations</div>
    <div class="admin-cards">
      <div class="admin-card">
        <h3>Total Businesses</h3>
        <div class="admin-value"><?php echo $totalBusinesses; ?></div>
        <small>Registered business accounts</small>
      </div>
      <div class="admin-card">
        <h3>Total Vendors</h3>
        <div class="admin-value"><?php echo $totalVendors; ?></div>
        <small>Marketplace vendor accounts</small>
      </div>
      <div class="admin-card">
        <h3>Total Labors</h3>
        <div class="admin-value"><?php echo $totalLabors; ?></div>
        <small>Available labor profiles</small>
      </div>
      <div class="admin-card">
        <h3>Total Orders</h3>
        <div class="admin-value"><?php echo $totalOrders; ?></div>
        <small>Orders recorded on the platform</small>
      </div>
      <div class="admin-card">
        <h3>Total Products</h3>
        <div class="admin-value"><?php echo $totalProducts; ?></div>
        <small>Products currently listed</small>
      </div>
      <div class="admin-card">
        <h3>Pending Vendor Approvals</h3>
        <div class="admin-value"><?php echo $pendingVendorApprovals; ?></div>
        <small>Vendor applications awaiting review</small>
      </div>
      <div class="admin-card">
        <h3>Pending Labor Approvals</h3>
        <div class="admin-value"><?php echo $pendingLaborApprovals; ?></div>
        <small>Labor applications awaiting review</small>
      </div>
    </div>

    <!-- FINANCIAL CARDS (paid only) -->
    <div class="admin-section-title">Revenue · Paid Orders Only</div>
    <div class="admin-cards admin-cards-financial">
      <div class="admin-card">
        <h3>Total Marketplace Sales</h3>
        <div class="admin-value"><?php echo money($totalMarketplaceSales); ?></div>
        <small>Gross revenue · paid orders only</small>
      </div>
      <div class="admin-card">
        <h3>Platform Profit</h3>
        <div class="admin-value"><?php echo money($totalPlatformProfit); ?></div>
        <small>Commission earned by SetupForge</small>
      </div>
      <div class="admin-card">
        <h3>Total Vendor Payouts</h3>
        <div class="admin-value"><?php echo money($totalVendorPayouts); ?></div>
        <small>Net payouts allocated to vendors</small>
      </div>
    </div>

    <!-- CHARTS ROW -->
    <div class="admin-grid-2 mt-4">

      <!-- MONTHLY REVENUE TREND -->
      <div class="admin-chart-box">
        <div class="admin-box-head">
          <h2>Monthly Revenue Trend</h2>
          <span class="admin-box-badge">Last 6 Months · Paid</span>
        </div>
        <div class="admin-chart-area">
          <canvas id="revenueChart" height="120"></canvas>
        </div>
      </div>

      <!-- TOP VENDORS BY SALES -->
      <div class="admin-chart-box">
        <div class="admin-box-head">
          <h2>Top Vendors by Sales</h2>
          <span class="admin-box-badge">Top 5 · Paid</span>
        </div>
        <div class="admin-chart-area">
          <canvas id="topVendorsChart" height="120"></canvas>
        </div>
      </div>

    </div>

    <!-- ORDERS BY STATUS CHART -->
    <div class="admin-chart-box mt-4">
      <div class="admin-box-head">
        <h2>Orders by Status</h2>
        <span class="admin-box-badge">Live Distribution</span>
      </div>
      <div class="admin-chart-area">
        <canvas id="ordersChart" height="90"></canvas>
      </div>
    </div>

    <!-- PER-VENDOR COMMISSION BREAKDOWN -->
    <div class="admin-section-title">Commission Breakdown by Vendor</div>
    <div class="admin-table-box">
      <div class="admin-box-head">
        <h2>Vendor Commission Breakdown</h2>
        <span class="admin-box-badge">Paid Orders Only</span>
      </div>

      <?php if ($vendorBreakdownRes && pg_num_rows($vendorBreakdownRes) > 0): ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Vendor</th>
                <th>Email</th>
                <th>Orders</th>
                <th>Gross Sales</th>
                <th>Avg Rate</th>
                <th>Commission (SetupForge)</th>
                <th>Vendor Payout</th>
              </tr>
            </thead>
            <tbody>
              <?php while($row = pg_fetch_assoc($vendorBreakdownRes)): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($row["vendor_name"]); ?></strong></td>
                  <td><?php echo htmlspecialchars($row["vendor_email"]); ?></td>
                  <td><?php echo (int)$row["order_count"]; ?></td>
                  <td><?php echo money($row["gross_amount"]); ?></td>
                  <td><span class="commission-rate-badge"><?php echo number_format((float)$row["avg_commission_rate"], 1); ?>%</span></td>
                  <td><strong><?php echo money($row["commission_amount"]); ?></strong></td>
                  <td><?php echo money($row["vendor_payout"]); ?></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="admin-empty">No vendor data yet.</div>
      <?php endif; ?>
    </div>

    <!-- RECENT ORDERS + PENDING VENDORS -->
    <div class="admin-grid-2 mt-4">
      <div class="admin-table-box">
        <div class="admin-box-head">
          <h2>Recent Orders</h2>
          <span class="admin-box-badge">Latest 5</span>
        </div>
        <?php if ($recentOrders && pg_num_rows($recentOrders) > 0): ?>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Order ID</th>
                  <th>Business</th>
                  <th>Status</th>
                  <th>Payment</th>
                  <th>Total</th>
                </tr>
              </thead>
              <tbody>
                <?php while($row = pg_fetch_assoc($recentOrders)): ?>
                  <tr>
                    <td><strong>#<?php echo htmlspecialchars($row["id"]); ?></strong></td>
                    <td><?php echo htmlspecialchars($row["business_name"] ?? "—"); ?></td>
                    <td><span class="admin-status-pill"><?php echo htmlspecialchars($row["status"]); ?></span></td>
                    <td><span class="admin-status-pill is-payment"><?php echo htmlspecialchars($row["payment_status"] ?? "pending"); ?></span></td>
                    <td><?php echo money($row["order_total"]); ?></td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="admin-empty">No orders found.</div>
        <?php endif; ?>
      </div>

      <div class="admin-table-box">
        <div class="admin-box-head">
          <h2>Pending Vendor Approvals</h2>
          <span class="admin-box-badge">Review Queue</span>
        </div>
        <?php if ($pendingVendors && pg_num_rows($pendingVendors) > 0): ?>
          <div class="admin-table-wrap">
            <table class="admin-table">
              <thead>
                <tr>
                  <th>Name</th>
                  <th>Email</th>
                  <th>Items Type</th>
                  <th>Action</th>
                </tr>
              </thead>
              <tbody>
                <?php while($row = pg_fetch_assoc($pendingVendors)): ?>
                  <tr>
                    <td><strong><?php echo htmlspecialchars($row["name"]); ?></strong></td>
                    <td><?php echo htmlspecialchars($row["email"]); ?></td>
                    <td><?php echo htmlspecialchars($row["items_type"] ?? "—"); ?></td>
                    <td>
                      <a class="admin-action approve" href="approve_vendor.php?id=<?php echo $row['id']; ?>">Approve</a>
                      <a class="admin-action reject"  href="reject_vendor.php?id=<?php echo $row['id']; ?>">Reject</a>
                    </td>
                  </tr>
                <?php endwhile; ?>
              </tbody>
            </table>
          </div>
        <?php else: ?>
          <div class="admin-empty">No pending vendors.</div>
        <?php endif; ?>
      </div>
    </div>

    <!-- PENDING LABORS -->
    <div class="admin-table-box mt-4">
      <div class="admin-box-head">
        <h2>Pending Labor Approvals</h2>
        <span class="admin-box-badge">Awaiting Approval</span>
      </div>
      <?php if ($pendingLabors && pg_num_rows($pendingLabors) > 0): ?>
        <div class="admin-table-wrap">
          <table class="admin-table">
            <thead>
              <tr>
                <th>Name</th>
                <th>Email</th>
                <th>Skills</th>
                <th>Status</th>
              </tr>
            </thead>
            <tbody>
              <?php while($row = pg_fetch_assoc($pendingLabors)): ?>
                <tr>
                  <td><strong><?php echo htmlspecialchars($row["name"]); ?></strong></td>
                  <td><?php echo htmlspecialchars($row["email"]); ?></td>
                  <td><?php echo htmlspecialchars($row["skills"] ?? "—"); ?></td>
                  <td><span class="admin-status-pill"><?php echo htmlspecialchars($row["status"]); ?></span></td>
                </tr>
              <?php endwhile; ?>
            </tbody>
          </table>
        </div>
      <?php else: ?>
        <div class="admin-empty">No pending labors.</div>
      <?php endif; ?>
    </div>

  </div>
</div>

<script>
/* ---- MONTHLY REVENUE TREND ---- */
new Chart(document.getElementById('revenueChart'), {
  type: 'line',
  data: {
    labels: <?php echo json_encode($monthlyLabels); ?>,
    datasets: [
      {
        label: 'Sales',
        data: <?php echo json_encode($monthlySales); ?>,
        borderColor: 'rgba(0,76,172,0.9)',
        backgroundColor: 'rgba(0,76,172,0.08)',
        borderWidth: 2.5,
        pointRadius: 4,
        pointBackgroundColor: '#004cac',
        tension: 0.35,
        fill: true
      },
      {
        label: 'Platform Profit',
        data: <?php echo json_encode($monthlyProfit); ?>,
        borderColor: 'rgba(0,153,148,0.9)',
        backgroundColor: 'rgba(0,153,148,0.06)',
        borderWidth: 2.5,
        pointRadius: 4,
        pointBackgroundColor: '#009994',
        tension: 0.35,
        fill: true
      }
    ]
  },
  options: {
    responsive: true,
    plugins: {
      legend: {
        display: true,
        labels: { color: '#64748b', font: { weight: '700', size: 12 } }
      },
      tooltip: {
        backgroundColor: 'rgba(15,23,42,0.94)',
        titleColor: '#fff',
        bodyColor: '#e5eefb',
        callbacks: {
          label: ctx => ' ' + ctx.dataset.label + ': ' + Number(ctx.raw).toLocaleString() + ' EGP'
        }
      }
    },
    scales: {
      x: {
        ticks: { color: '#64748b', font: { weight: '700' } },
        grid: { display: false },
        border: { display: false }
      },
      y: {
        beginAtZero: true,
        ticks: {
          color: '#64748b',
          font: { weight: '700' },
          callback: v => Number(v).toLocaleString()
        },
        grid: { color: 'rgba(15,23,42,0.06)' },
        border: { display: false }
      }
    }
  }
});

/* ---- TOP VENDORS BY SALES ---- */
new Chart(document.getElementById('topVendorsChart'), {
  type: 'bar',
  data: {
    labels: <?php echo json_encode($topVendorLabels); ?>,
    datasets: [{
      label: 'Sales (EGP)',
      data: <?php echo json_encode($topVendorSales); ?>,
      borderWidth: 0,
      borderRadius: 10,
      backgroundColor: [
        'rgba(0,76,172,0.88)',
        'rgba(0,153,148,0.88)',
        'rgba(58,123,213,0.88)',
        'rgba(16,185,129,0.88)',
        'rgba(14,165,233,0.88)'
      ]
    }]
  },
  options: {
    responsive: true,
    indexAxis: 'y',
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: 'rgba(15,23,42,0.94)',
        titleColor: '#fff',
        bodyColor: '#e5eefb',
        callbacks: {
          label: ctx => ' ' + Number(ctx.raw).toLocaleString() + ' EGP'
        }
      }
    },
    scales: {
      x: {
        beginAtZero: true,
        ticks: {
          color: '#64748b',
          font: { weight: '700' },
          callback: v => Number(v).toLocaleString()
        },
        grid: { color: 'rgba(15,23,42,0.06)' },
        border: { display: false }
      },
      y: {
        ticks: { color: '#0f172a', font: { weight: '700' } },
        grid: { display: false },
        border: { display: false }
      }
    }
  }
});

/* ---- ORDERS BY STATUS ---- */
new Chart(document.getElementById('ordersChart'), {
  type: 'bar',
  data: {
    labels: <?php echo json_encode($orderStatusLabels); ?>,
    datasets: [{
      label: 'Orders',
      data: <?php echo json_encode($orderStatusCounts); ?>,
      borderWidth: 0,
      borderRadius: 10,
      backgroundColor: [
        'rgba(0,76,172,0.88)',
        'rgba(0,153,148,0.88)',
        'rgba(58,123,213,0.88)',
        'rgba(16,185,129,0.88)',
        'rgba(59,130,246,0.88)',
        'rgba(14,165,233,0.88)'
      ]
    }]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { display: false },
      tooltip: {
        backgroundColor: 'rgba(15,23,42,0.94)',
        titleColor: '#fff',
        bodyColor: '#e5eefb',
        borderColor: 'rgba(255,255,255,0.08)',
        borderWidth: 1,
        padding: 12
      }
    },
    scales: {
      x: {
        ticks: { color: '#64748b', font: { weight: '700' } },
        grid: { display: false },
        border: { display: false }
      },
      y: {
        beginAtZero: true,
        ticks: { precision: 0, color: '#64748b', font: { weight: '700' } },
        grid: { color: 'rgba(15,23,42,0.06)' },
        border: { display: false }
      }
    }
  }
});
</script>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>