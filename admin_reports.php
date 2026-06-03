<?php
session_start();
require "db.php";
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? "") !== "admin") {
    header("Location: auth/login.php"); exit();
}

/* Date range */
$range  = $_GET['range'] ?? 'this_month';
$date_from = $_GET['from'] ?? '';
$date_to   = $_GET['to']   ?? '';

switch ($range) {
    case 'today':
        $from = date('Y-m-d'); $to = date('Y-m-d'); break;
    case 'last_month':
        $from = date('Y-m-01', strtotime('first day of last month'));
        $to   = date('Y-m-t',  strtotime('last day of last month')); break;
    case 'last_3':
        $from = date('Y-m-01', strtotime('-2 months'));
        $to   = date('Y-m-d'); break;
    case 'last_6':
        $from = date('Y-m-01', strtotime('-5 months'));
        $to   = date('Y-m-d'); break;
    case 'custom':
        $from = $date_from ?: date('Y-m-01');
        $to   = $date_to   ?: date('Y-m-d'); break;
    default: /* this_month */
        $from = date('Y-m-01'); $to = date('Y-m-d'); break;
}

/* CSV Export */
if (isset($_GET['export'])) {
    $expType = $_GET['export'];
    header('Content-Type: text/csv');
    header('Content-Disposition: attachment; filename="sf_' . $expType . '_' . date('Ymd') . '.csv"');
    $out = fopen('php://output', 'w');

    if ($expType === 'orders') {
        fputcsv($out, ['Order ID','Business','Date','Type','Total','Payment Status','Method']);
        $res = pg_query($conn,
            "SELECT o.id, u.name, o.order_date, o.order_type,
                    o.order_total, o.payment_status, o.payment_method
             FROM orders o LEFT JOIN users u ON u.id = o.business_user_id
             ORDER BY o.id DESC");
        while ($r = pg_fetch_row($res)) fputcsv($out, $r);
    } elseif ($expType === 'vendors') {
        fputcsv($out, ['Vendor','Email','Orders','Gross Sales','Commission','Payout']);
        $res = pg_query($conn,
            "SELECT u.name, u.email,
                    COUNT(DISTINCT vof.order_id),
                    COALESCE(SUM(vof.gross_amount),0),
                    COALESCE(SUM(vof.commission_amount),0),
                    COALESCE(SUM(vof.vendor_payout),0)
             FROM vendor_order_fulfillments vof
             JOIN orders o ON o.id = vof.order_id
             JOIN users u ON u.id = vof.vendor_user_id
             WHERE o.payment_status = 'paid'
             GROUP BY u.id, u.name, u.email ORDER BY 4 DESC");
        while ($r = pg_fetch_row($res)) fputcsv($out, $r);
    } elseif ($expType === 'users') {
        fputcsv($out, ['Name','Email','Type','City','Phone']);
        $res = pg_query($conn,
            "SELECT name, email, user_type, city, phone FROM users
             WHERE user_type != 'admin' ORDER BY id DESC");
        while ($r = pg_fetch_row($res)) fputcsv($out, $r);
    }
    fclose($out); exit();
}

function money($n) { return number_format((float)$n, 0) . ' EGP'; }

/* Financial totals for range */
$finRes = pg_query_params($conn,
    "SELECT COALESCE(SUM(vof.gross_amount),0)      AS sales,
            COALESCE(SUM(vof.commission_amount),0) AS profit,
            COALESCE(SUM(vof.vendor_payout),0)     AS payouts,
            COUNT(DISTINCT vof.order_id)           AS orders
     FROM vendor_order_fulfillments vof
     JOIN orders o ON o.id = vof.order_id
     WHERE o.payment_status = 'paid'
       AND DATE(o.paid_at) BETWEEN $1 AND $2",
    [$from, $to]);
$fin = pg_fetch_assoc($finRes);

/* Monthly breakdown for range */
$monthlyRes = pg_query_params($conn,
    "SELECT TO_CHAR(DATE_TRUNC('month', o.paid_at), 'Mon YYYY') AS lbl,
            DATE_TRUNC('month', o.paid_at) AS mts,
            COALESCE(SUM(vof.gross_amount),0)      AS sales,
            COALESCE(SUM(vof.commission_amount),0) AS profit,
            COUNT(DISTINCT vof.order_id)           AS orders
     FROM vendor_order_fulfillments vof
     JOIN orders o ON o.id = vof.order_id
     WHERE o.payment_status = 'paid'
       AND DATE(o.paid_at) BETWEEN $1 AND $2
     GROUP BY DATE_TRUNC('month', o.paid_at)
     ORDER BY mts ASC",
    [$from, $to]);
$monthRows = [];
$mLabels = []; $mSales = []; $mProfit = [];
if ($monthlyRes) {
    while ($r = pg_fetch_assoc($monthlyRes)) {
        $monthRows[] = $r;
        $mLabels[] = $r['lbl'];
        $mSales[]  = (float)$r['sales'];
        $mProfit[] = (float)$r['profit'];
    }
}

/* Vendor commission breakdown */
$vendorBreakRes = pg_query_params($conn,
    "SELECT u.name, u.email,
            COUNT(DISTINCT vof.order_id) AS orders,
            COALESCE(SUM(vof.gross_amount),0)      AS gross,
            COALESCE(AVG(vof.commission_rate),0)   AS avg_rate,
            COALESCE(SUM(vof.commission_amount),0) AS commission,
            COALESCE(SUM(vof.vendor_payout),0)     AS payout
     FROM vendor_order_fulfillments vof
     JOIN orders o ON o.id = vof.order_id
     JOIN users u ON u.id = vof.vendor_user_id
     WHERE o.payment_status = 'paid'
       AND DATE(o.paid_at) BETWEEN $1 AND $2
     GROUP BY u.id, u.name, u.email
     ORDER BY gross DESC",
    [$from, $to]);

$ad_title    = 'Reports';
$ad_page     = 'reports';
$ad_extra_head = '<script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.3/dist/chart.umd.min.js"></script>';
require 'admin_layout.php';
?>

<div class="ad-page-header">
  <div class="ad-page-title">Reports</div>
  <div class="ad-page-sub">Financial summaries and platform revenue data</div>
</div>

<!-- DATE RANGE TOOLBAR -->
<div class="ad-toolbar" style="margin-bottom:24px">
  <form method="get" style="display:flex;gap:10px;flex-wrap:wrap;align-items:center;width:100%">
    <select name="range" class="ad-select" onchange="toggleCustom(this.value)">
      <option value="today"      <?= $range === 'today'      ? 'selected' : '' ?>>Today</option>
      <option value="this_month" <?= $range === 'this_month' ? 'selected' : '' ?>>This month</option>
      <option value="last_month" <?= $range === 'last_month' ? 'selected' : '' ?>>Last month</option>
      <option value="last_3"     <?= $range === 'last_3'     ? 'selected' : '' ?>>Last 3 months</option>
      <option value="last_6"     <?= $range === 'last_6'     ? 'selected' : '' ?>>Last 6 months</option>
      <option value="custom"     <?= $range === 'custom'     ? 'selected' : '' ?>>Custom range</option>
    </select>
    <div id="customRange" style="display:<?= $range === 'custom' ? 'flex' : 'none' ?>;gap:8px;align-items:center">
      <input type="date" name="from" class="ad-select" value="<?= htmlspecialchars($from) ?>">
      <span style="font-size:13px;color:var(--ad-hint)">to</span>
      <input type="date" name="to"   class="ad-select" value="<?= htmlspecialchars($to) ?>">
    </div>
    <button type="submit" class="ad-btn primary"><i class="bi bi-filter"></i> Apply</button>
    <div class="ad-btn-group">
      <a href="?export=orders&range=<?= $range ?>&from=<?= $from ?>&to=<?= $to ?>" class="ad-btn ghost">
        <i class="bi bi-download"></i> Orders CSV
      </a>
      <a href="?export=vendors&range=<?= $range ?>&from=<?= $from ?>&to=<?= $to ?>" class="ad-btn ghost">
        <i class="bi bi-download"></i> Vendors CSV
      </a>
      <a href="?export=users" class="ad-btn ghost">
        <i class="bi bi-download"></i> Users CSV
      </a>
    </div>
  </form>
</div>

<!-- SUMMARY CARDS -->
<div class="ad-stat-row" style="margin-bottom:24px">
  <div class="ad-stat-card">
    <div class="ad-stat-label">Total sales</div>
    <div class="ad-stat-value"><?= money($fin['sales']) ?></div>
    <div class="ad-stat-sub"><?= (int)$fin['orders'] ?> paid orders · <?= htmlspecialchars($from) ?> → <?= htmlspecialchars($to) ?></div>
  </div>
  <div class="ad-stat-card accent-green">
    <div class="ad-stat-label">Platform profit</div>
    <div class="ad-stat-value"><?= money($fin['profit']) ?></div>
    <div class="ad-stat-sub">Commissions collected in period</div>
  </div>
  <div class="ad-stat-card accent-amber">
    <div class="ad-stat-label">Vendor payouts</div>
    <div class="ad-stat-value"><?= money($fin['payouts']) ?></div>
    <div class="ad-stat-sub">Net amount owed to vendors</div>
  </div>
</div>

<!-- REVENUE CHART -->
<?php if (!empty($mLabels)): ?>
<div class="ad-box" style="margin-bottom:24px">
  <div class="ad-box-head">
    <div>
      <div class="ad-box-title">Revenue breakdown by month</div>
      <div class="ad-box-sub">Sales vs platform profit — paid orders only</div>
    </div>
  </div>
  <div class="ad-box-body">
    <div class="ad-chart-wrap"><canvas id="reportChart" style="max-height:280px"></canvas></div>
  </div>
</div>
<?php endif; ?>

<!-- MONTHLY TABLE -->
<?php if (!empty($monthRows)): ?>
<div class="ad-box" style="margin-bottom:24px">
  <div class="ad-box-head">
    <div class="ad-box-title">Monthly breakdown</div>
  </div>
  <div class="ad-box-body no-pad">
    <div class="ad-table-wrap">
      <table class="ad-table">
        <thead>
          <tr>
            <th>Month</th>
            <th>Orders</th>
            <th>Gross Sales</th>
            <th>Platform Profit</th>
            <th>Vendor Payouts</th>
          </tr>
        </thead>
        <tbody>
          <?php foreach ($monthRows as $r): ?>
          <tr>
            <td><strong><?= htmlspecialchars($r['lbl']) ?></strong></td>
            <td class="mono"><?= (int)$r['orders'] ?></td>
            <td class="mono"><?= money($r['sales']) ?></td>
            <td class="mono"><?= money($r['profit']) ?></td>
            <td class="mono"><?= money((float)$r['sales'] - (float)$r['profit']) ?></td>
          </tr>
          <?php endforeach; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>
<?php endif; ?>

<!-- VENDOR COMMISSION BREAKDOWN -->
<div class="ad-section-title">Vendor commission breakdown</div>
<div class="ad-box">
  <div class="ad-box-head">
    <div>
      <div class="ad-box-title">Per-vendor financials</div>
      <div class="ad-box-sub">Paid orders only · selected period</div>
    </div>
    <a href="?export=vendors&range=<?= $range ?>&from=<?= $from ?>&to=<?= $to ?>" class="ad-btn ghost">
      <i class="bi bi-download"></i> Export CSV
    </a>
  </div>
  <div class="ad-box-body no-pad">
    <div class="ad-table-wrap">
      <table class="ad-table">
        <thead>
          <tr>
            <th>Vendor</th>
            <th>Email</th>
            <th>Orders</th>
            <th>Gross Sales</th>
            <th>Avg Rate</th>
            <th>Commission</th>
            <th>Payout</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($vendorBreakRes && pg_num_rows($vendorBreakRes) > 0): ?>
            <?php while ($r = pg_fetch_assoc($vendorBreakRes)): ?>
            <tr>
              <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
              <td style="font-size:12px;color:var(--ad-muted)"><?= htmlspecialchars($r['email']) ?></td>
              <td class="mono"><?= (int)$r['orders'] ?></td>
              <td class="mono"><?= money($r['gross']) ?></td>
              <td>
                <span class="ad-pill success"><?= number_format((float)$r['avg_rate'], 1) ?>%</span>
              </td>
              <td class="mono"><strong><?= money($r['commission']) ?></strong></td>
              <td class="mono"><?= money($r['payout']) ?></td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="7">
              <div class="ad-empty"><i class="bi bi-bar-chart-line"></i><p>No vendor data for this period</p></div>
            </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function toggleCustom(v) {
    document.getElementById('customRange').style.display = v === 'custom' ? 'flex' : 'none';
}
<?php if (!empty($mLabels)): ?>
new Chart(document.getElementById('reportChart'), {
  type: 'bar',
  data: {
    labels: <?= json_encode($mLabels) ?>,
    datasets: [
      { label: 'Sales', data: <?= json_encode($mSales) ?>, backgroundColor: 'rgba(0,76,172,0.85)', borderRadius: 6, borderSkipped: false },
      { label: 'Profit', data: <?= json_encode($mProfit) ?>, backgroundColor: 'rgba(10,122,69,0.75)', borderRadius: 6, borderSkipped: false }
    ]
  },
  options: {
    responsive: true,
    plugins: {
      legend: { labels: { color: '#5a6a7e', font: { family: "'DM Sans'", size: 12 }, boxWidth: 10 } },
      tooltip: { backgroundColor: '#0d1b2a', titleColor: '#fff', bodyColor: '#8fa0b4', cornerRadius: 8 }
    },
    scales: {
      x: { ticks: { color: '#8fa0b4', font: { family: "'DM Sans'" } }, grid: { display: false }, border: { display: false } },
      y: { beginAtZero: true, ticks: { color: '#8fa0b4', font: { family: "'DM Sans'" }, callback: v => v.toLocaleString() }, grid: { color: 'rgba(0,0,0,.05)' }, border: { display: false } }
    }
  }
});
<?php endif; ?>
</script>

  </main>
</div>
</body>
</html>