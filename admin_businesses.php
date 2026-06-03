<?php
session_start();
require "db.php";
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? "") !== "admin") {
    header("Location: auth/login.php"); exit();
}

$search  = trim($_GET['search'] ?? '');
$btype   = $_GET['btype']  ?? 'all';
$bstatus = $_GET['status'] ?? 'all';

$where  = ["1=1"];
$params = [];
$pi     = 1;

if ($btype !== 'all') {
    $where[] = "b.business_type = $".$pi; $params[] = $btype; $pi++;
}
if ($bstatus !== 'all') {
    $where[] = "b.setup_status = $".$pi; $params[] = $bstatus; $pi++;
}
if ($search !== '') {
    $where[] = "(b.business_name ILIKE $".$pi." OR u.name ILIKE $".($pi+1).")";
    $params[] = '%'.$search.'%';
    $params[] = '%'.$search.'%';
    $pi += 2;
}

$whereStr = implode(' AND ', $where);

$businesses = pg_query_params($conn,
    "SELECT b.user_id, b.business_name, b.business_type, b.setup_status,
            b.budget_egp, b.area_sqm, b.seat_count,
            u.name AS owner, u.city, u.email,
            (SELECT COUNT(*) FROM orders o WHERE o.business_user_id = b.user_id) AS orders
     FROM businesses b JOIN users u ON u.id = b.user_id
     WHERE $whereStr
     ORDER BY b.user_id DESC LIMIT 200",
    $params);

$total = $businesses ? pg_num_rows($businesses) : 0;

/* Business types for filter */
$btypes_res = pg_query($conn,
    "SELECT DISTINCT business_type FROM businesses WHERE business_type IS NOT NULL ORDER BY 1");
$btypes = [];
if ($btypes_res) while ($r = pg_fetch_row($btypes_res)) $btypes[] = $r[0];

$ad_title = 'Businesses';
$ad_page  = 'businesses';
require 'admin_layout.php';

function money($n) { return number_format((float)$n, 0) . ' EGP'; }
$statusPill = ['completed' => 'success', 'in_progress' => 'warning', 'none' => 'neutral'];
?>

<div class="ad-page-header">
  <div class="ad-page-title">Businesses</div>
  <div class="ad-page-sub">All registered businesses and their setup status</div>
</div>

<div class="ad-toolbar">
  <div class="ad-search-input">
    <i class="bi bi-search"></i>
    <input type="text" id="searchInput" placeholder="Search by business name or owner..."
           value="<?= htmlspecialchars($search) ?>">
  </div>
  <select class="ad-select" id="btypeSelect">
    <option value="all">All types</option>
    <?php foreach ($btypes as $bt): ?>
      <option value="<?= htmlspecialchars($bt) ?>" <?= $btype === $bt ? 'selected' : '' ?>>
        <?= ucfirst(htmlspecialchars($bt)) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <select class="ad-select" id="statusSelect">
    <option value="all"         <?= $bstatus === 'all'         ? 'selected' : '' ?>>All statuses</option>
    <option value="completed"   <?= $bstatus === 'completed'   ? 'selected' : '' ?>>Completed</option>
    <option value="in_progress" <?= $bstatus === 'in_progress' ? 'selected' : '' ?>>In progress</option>
    <option value="none"        <?= $bstatus === 'none'        ? 'selected' : '' ?>>Not started</option>
  </select>
  <div class="ad-btn-group">
    <span style="font-size:12px;color:var(--ad-hint);line-height:36px"><?= $total ?> businesses</span>
  </div>
</div>

<div class="ad-box">
  <div class="ad-box-body no-pad">
    <div class="ad-table-wrap">
      <table class="ad-table">
        <thead>
          <tr>
            <th>Business</th>
            <th>Owner</th>
            <th>City</th>
            <th>Type</th>
            <th>Budget</th>
            <th>Area</th>
            <th>Setup</th>
            <th>Orders</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($businesses && pg_num_rows($businesses) > 0): ?>
            <?php while ($r = pg_fetch_assoc($businesses)):
              $skey = $r['setup_status'] ?? 'none';
              $pc   = $statusPill[$skey] ?? 'neutral';
              $slbl = $skey === 'in_progress' ? 'In progress' : ucfirst($skey);
            ?>
            <tr>
              <td><strong><?= htmlspecialchars($r['business_name'] ?? '—') ?></strong></td>
              <td style="font-size:12px"><?= htmlspecialchars($r['owner']) ?></td>
              <td><?= htmlspecialchars($r['city'] ?? '—') ?></td>
              <td><?= ucfirst(htmlspecialchars($r['business_type'] ?? '—')) ?></td>
              <td class="mono"><?= $r['budget_egp'] ? money($r['budget_egp']) : '—' ?></td>
              <td class="mono"><?= $r['area_sqm'] ? $r['area_sqm'] . ' m²' : '—' ?></td>
              <td><span class="ad-pill <?= $pc ?>"><?= $slbl ?></span></td>
              <td class="mono"><?= (int)$r['orders'] ?></td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="8">
              <div class="ad-empty"><i class="bi bi-building"></i><p>No businesses found</p></div>
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
        search: document.getElementById('searchInput').value,
        btype:  document.getElementById('btypeSelect').value,
        status: document.getElementById('statusSelect').value,
    });
    window.location.href = 'admin_businesses.php?' + p.toString();
}
document.getElementById('btypeSelect').addEventListener('change', applyFilters);
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
