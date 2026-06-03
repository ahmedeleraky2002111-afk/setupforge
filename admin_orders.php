<?php
session_start();
require "db.php";
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? "") !== "admin") {
    header("Location: auth/login.php"); exit();
}

$search  = trim($_GET['search'] ?? '');
$filter  = $_GET['filter'] ?? 'all';
$type    = $_GET['type']   ?? 'all';

$where = ["1=1"];
$params = [];
$pi = 1;

if ($filter === 'paid')   { $where[] = "o.payment_status = 'paid'"; }
if ($filter === 'unpaid') { $where[] = "o.payment_status != 'paid'"; }
if ($filter === 'stuck')  { $where[] = "o.payment_status != 'paid' AND o.order_date <= NOW() - INTERVAL '3 days'"; }
if ($type === 'setup')    { $where[] = "o.order_type = 'setup'"; }
if ($type === 'shop')     { $where[] = "o.order_type = 'shop'"; }
if ($search !== '') {
    $where[] = "(u.name ILIKE $".$pi." OR CAST(o.id AS TEXT) = $".($pi+1).")";
    $params[] = '%'.$search.'%';
    $params[] = $search;
    $pi += 2;
}

$whereStr = implode(' AND ', $where);

$orders = pg_query_params($conn,
    "SELECT o.id, o.order_date, o.status, o.payment_status,
            o.order_total, o.order_type, o.payment_method,
            u.name AS bname, u.city
     FROM orders o
     LEFT JOIN users u ON u.id = o.business_user_id
     WHERE $whereStr
     ORDER BY o.id DESC
     LIMIT 100",
    $params);

$total_count = pg_num_rows($orders);

$ad_title = 'Orders';
$ad_page  = 'orders';
require 'admin_layout.php';

function money($n) { return number_format((float)$n, 0) . ' EGP'; }
?>

<div class="ad-page-header">
  <div class="ad-page-title">Orders</div>
  <div class="ad-page-sub">All setup and shop orders placed on the platform</div>
</div>

<div class="ad-toolbar">
  <div class="ad-search-input">
    <i class="bi bi-search"></i>
    <input type="text" id="searchInput" placeholder="Search by business name or order #ID..."
           value="<?= htmlspecialchars($search) ?>">
  </div>
  <select class="ad-select" id="filterSelect">
    <option value="all"    <?= $filter === 'all'    ? 'selected' : '' ?>>All payments</option>
    <option value="paid"   <?= $filter === 'paid'   ? 'selected' : '' ?>>Paid only</option>
    <option value="unpaid" <?= $filter === 'unpaid' ? 'selected' : '' ?>>Unpaid</option>
    <option value="stuck"  <?= $filter === 'stuck'  ? 'selected' : '' ?>>Stuck 3d+</option>
  </select>
  <select class="ad-select" id="typeSelect">
    <option value="all"   <?= $type === 'all'   ? 'selected' : '' ?>>All types</option>
    <option value="setup" <?= $type === 'setup' ? 'selected' : '' ?>>Setup orders</option>
    <option value="shop"  <?= $type === 'shop'  ? 'selected' : '' ?>>Shop orders</option>
  </select>
  <div class="ad-btn-group">
    <span style="font-size:12px;color:var(--ad-hint);line-height:36px"><?= $total_count ?> orders</span>
  </div>
</div>

<div class="ad-box">
  <div class="ad-box-body no-pad">
    <div class="ad-table-wrap">
      <table class="ad-table">
        <thead>
          <tr>
            <th style="width:70px">Order</th>
            <th>Business</th>
            <th>City</th>
            <th>Date</th>
            <th>Type</th>
            <th>Total</th>
            <th>Payment</th>
            <th>Method</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($orders && pg_num_rows($orders) > 0): ?>
            <?php while ($r = pg_fetch_assoc($orders)):
              $pstatus = $r['payment_status'] ?? 'pending';
              $pillClass = $pstatus === 'paid' ? 'success' : ($pstatus === 'pending' ? 'warning' : 'danger');
              $typeLabel = $r['order_type'] === 'setup' ? 'Setup' : 'Shop';
              $typePill  = $r['order_type'] === 'setup' ? 'info' : 'neutral';
            ?>
            <tr>
              <td><span class="mono">#<?= $r['id'] ?></span></td>
              <td><strong><?= htmlspecialchars($r['bname'] ?? '—') ?></strong></td>
              <td><?= htmlspecialchars($r['city'] ?? '—') ?></td>
              <td style="white-space:nowrap;font-size:12px">
                <?= date('d M Y', strtotime($r['order_date'])) ?>
              </td>
              <td><span class="ad-pill <?= $typePill ?>"><?= $typeLabel ?></span></td>
              <td class="mono"><?= money($r['order_total']) ?></td>
              <td><span class="ad-pill <?= $pillClass ?>"><?= ucfirst($pstatus) ?></span></td>
              <td style="font-size:12px"><?= htmlspecialchars($r['payment_method'] ?? '—') ?></td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="8">
              <div class="ad-empty"><i class="bi bi-receipt"></i><p>No orders found</p></div>
            </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function applyFilters() {
    const s = document.getElementById('searchInput').value;
    const f = document.getElementById('filterSelect').value;
    const t = document.getElementById('typeSelect').value;
    const p = new URLSearchParams({ search: s, filter: f, type: t });
    window.location.href = 'admin_orders.php?' + p.toString();
}
document.getElementById('filterSelect').addEventListener('change', applyFilters);
document.getElementById('typeSelect').addEventListener('change', applyFilters);
let _t;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(_t); _t = setTimeout(applyFilters, 500);
});
</script>

  </main>
</div>
</body>
</html>
