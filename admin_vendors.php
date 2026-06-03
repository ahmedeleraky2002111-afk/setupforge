<?php
session_start();
require "db.php";
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? "") !== "admin") {
    header("Location: auth/login.php"); exit();
}

$search  = trim($_GET['search'] ?? '');
$vstatus = $_GET['vstatus'] ?? 'all';

$where  = ["1=1"];
$params = [];
$pi     = 1;

if ($vstatus !== 'all') {
    $where[] = "v.status = $".$pi; $params[] = $vstatus; $pi++;
}
if ($search !== '') {
    $where[] = "(u.name ILIKE $".$pi." OR u.email ILIKE $".($pi+1).")";
    $params[] = '%'.$search.'%';
    $params[] = '%'.$search.'%';
    $pi += 2;
}

$vendors = pg_query_params($conn,
    "SELECT u.id, u.name, u.email, u.city, v.items_type, v.status,
            (SELECT COUNT(*) FROM products p WHERE p.vendor_user_id = u.id) AS product_count,
            (SELECT COALESCE(SUM(vof.gross_amount),0)
             FROM vendor_order_fulfillments vof
             JOIN orders o ON o.id = vof.order_id
             WHERE vof.vendor_user_id = u.id AND o.payment_status='paid') AS total_sales,
            (SELECT COALESCE(SUM(vof.commission_amount),0)
             FROM vendor_order_fulfillments vof
             JOIN orders o ON o.id = vof.order_id
             WHERE vof.vendor_user_id = u.id AND o.payment_status='paid') AS total_commission
     FROM vendors v JOIN users u ON u.id = v.user_id
     WHERE ".implode(' AND ', $where)."
     ORDER BY total_sales DESC NULLS LAST",
    $params);

$total = $vendors ? pg_num_rows($vendors) : 0;
$ad_title = 'Vendors';
$ad_page  = 'vendors';
require 'admin_layout.php';

function money($n) { return number_format((float)$n, 0) . ' EGP'; }
$statusPill = ['approved' => 'success', 'pending' => 'warning', 'rejected' => 'danger'];
?>

<div class="ad-page-header">
  <div class="ad-page-title">Vendors</div>
  <div class="ad-page-sub">All marketplace vendors — sales, products, and approval status</div>
</div>

<div class="ad-toolbar">
  <div class="ad-search-input">
    <i class="bi bi-search"></i>
    <input type="text" id="searchInput" placeholder="Search by name or email..."
           value="<?= htmlspecialchars($search) ?>">
  </div>
  <select class="ad-select" id="statusSelect">
    <option value="all"      <?= $vstatus === 'all'      ? 'selected' : '' ?>>All statuses</option>
    <option value="approved" <?= $vstatus === 'approved' ? 'selected' : '' ?>>Approved</option>
    <option value="pending"  <?= $vstatus === 'pending'  ? 'selected' : '' ?>>Pending</option>
    <option value="rejected" <?= $vstatus === 'rejected' ? 'selected' : '' ?>>Rejected</option>
  </select>
  <div class="ad-btn-group">
    <span style="font-size:12px;color:var(--ad-hint);line-height:36px"><?= $total ?> vendors</span>
  </div>
</div>

<div class="ad-box">
  <div class="ad-box-body no-pad">
    <div class="ad-table-wrap">
      <table class="ad-table">
        <thead>
          <tr>
            <th>Vendor</th>
            <th>Email</th>
            <th>City</th>
            <th>Item type</th>
            <th>Products</th>
            <th>Total sales</th>
            <th>Commission</th>
            <th>Status</th>
            <th>Action</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($vendors && pg_num_rows($vendors) > 0): ?>
            <?php while ($r = pg_fetch_assoc($vendors)):
              $skey = $r['status'] ?? 'pending';
              $pc   = $statusPill[$skey] ?? 'neutral';
            ?>
            <tr>
              <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
              <td style="font-size:12px;color:var(--ad-muted)"><?= htmlspecialchars($r['email']) ?></td>
              <td><?= htmlspecialchars($r['city'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['items_type'] ?? '—') ?></td>
              <td class="mono"><?= (int)$r['product_count'] ?></td>
              <td class="mono"><?= money($r['total_sales']) ?></td>
              <td class="mono"><?= money($r['total_commission']) ?></td>
              <td><span class="ad-pill <?= $pc ?>"><?= ucfirst($skey) ?></span></td>
              <td>
                <?php if ($skey === 'pending'): ?>
                  <a href="approve_vendor.php?id=<?= $r['id'] ?>" class="ad-action approve">
                    <i class="bi bi-check-lg"></i> Approve
                  </a>
                <?php else: ?>
                  <a href="admin_users.php?search=<?= urlencode($r['email']) ?>" class="ad-action view">
                    <i class="bi bi-eye"></i> View
                  </a>
                <?php endif; ?>
              </td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="9">
              <div class="ad-empty"><i class="bi bi-shop"></i><p>No vendors found</p></div>
            </td></tr>
          <?php endif; ?>
        </tbody>
      </table>
    </div>
  </div>
</div>

<script>
function applyFilters() {
    const p = new URLSearchParams({
        search:  document.getElementById('searchInput').value,
        vstatus: document.getElementById('statusSelect').value,
    });
    window.location.href = 'admin_vendors.php?' + p.toString();
}
document.getElementById('statusSelect').addEventListener('change', applyFilters);
let _t;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(_t); _t = setTimeout(applyFilters, 500);
});
</script>

  </main>
</div>
</body>
</html>
