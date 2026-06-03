<?php
session_start();
require "db.php";
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? "") !== "admin") {
    header("Location: auth/login.php"); exit();
}

$search   = trim($_GET['search'] ?? '');
$usertype = $_GET['usertype'] ?? 'all';
$city_f   = trim($_GET['city'] ?? '');

$where  = ["u.user_type != 'admin'"];
$params = [];
$pi     = 1;

if ($usertype !== 'all') {
    $where[] = "u.user_type = $".$pi;
    $params[] = $usertype; $pi++;
}
if ($search !== '') {
    $where[] = "(u.name ILIKE $".$pi." OR u.email ILIKE $".($pi+1).")";
    $params[] = '%'.$search.'%';
    $params[] = '%'.$search.'%';
    $pi += 2;
}
if ($city_f !== '') {
    $where[] = "u.city ILIKE $".$pi;
    $params[] = '%'.$city_f.'%'; $pi++;
}

$whereStr = implode(' AND ', $where);

$users = pg_query_params($conn,
    "SELECT u.id, u.name, u.email, u.user_type, u.city, u.phone,
            u.country,
            (SELECT COUNT(*) FROM orders o WHERE o.business_user_id = u.id) AS order_count
     FROM users u
     WHERE $whereStr
     ORDER BY u.id DESC LIMIT 200",
    $params);

/* City list for filter */
$cities_res = pg_query($conn,
    "SELECT DISTINCT city FROM users WHERE city IS NOT NULL AND city != '' ORDER BY city");
$city_opts = [];
if ($cities_res) while ($r = pg_fetch_row($cities_res)) $city_opts[] = $r[0];

$total = $users ? pg_num_rows($users) : 0;

$ad_title = 'Users';
$ad_page  = 'users';
require 'admin_layout.php';

$typeLabels = [
    'business'  => ['label' => 'Business', 'pill' => 'info'],
    'customer'  => ['label' => 'Customer', 'pill' => 'neutral'],
    'vendor'    => ['label' => 'Vendor',   'pill' => 'success'],
    'labor'     => ['label' => 'Labor',    'pill' => 'warning'],
    'company'   => ['label' => 'Company',  'pill' => 'info'],
];
?>

<div class="ad-page-header">
  <div class="ad-page-title">All users</div>
  <div class="ad-page-sub">Every registered user on the platform</div>
</div>

<div class="ad-toolbar">
  <div class="ad-search-input">
    <i class="bi bi-search"></i>
    <input type="text" id="searchInput" placeholder="Search by name or email..."
           value="<?= htmlspecialchars($search) ?>">
  </div>
  <select class="ad-select" id="typeSelect">
    <option value="all" <?= $usertype === 'all' ? 'selected' : '' ?>>All types</option>
    <option value="business" <?= $usertype === 'business' ? 'selected' : '' ?>>Business</option>
    <option value="customer" <?= $usertype === 'customer' ? 'selected' : '' ?>>Customer</option>
    <option value="vendor"   <?= $usertype === 'vendor'   ? 'selected' : '' ?>>Vendor</option>
    <option value="labor"    <?= $usertype === 'labor'    ? 'selected' : '' ?>>Labor</option>
    <option value="company"  <?= $usertype === 'company'  ? 'selected' : '' ?>>Company</option>
  </select>
  <select class="ad-select" id="citySelect">
    <option value="">All cities</option>
    <?php foreach ($city_opts as $c): ?>
      <option value="<?= htmlspecialchars($c) ?>" <?= $city_f === $c ? 'selected' : '' ?>>
        <?= htmlspecialchars($c) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <div class="ad-btn-group">
    <span style="font-size:12px;color:var(--ad-hint);line-height:36px"><?= $total ?> users</span>
  </div>
</div>

<div class="ad-box">
  <div class="ad-box-body no-pad">
    <div class="ad-table-wrap">
      <table class="ad-table">
        <thead>
          <tr>
            <th>Name</th>
            <th>Email</th>
            <th>Type</th>
            <th>City</th>
            <th>Phone</th>
            <th>Orders</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($users && pg_num_rows($users) > 0): ?>
            <?php while ($r = pg_fetch_assoc($users)):
              $tinfo = $typeLabels[$r['user_type']] ?? ['label' => ucfirst($r['user_type']), 'pill' => 'neutral'];
            ?>
            <tr>
              <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
              <td style="font-size:12px;color:var(--ad-muted)"><?= htmlspecialchars($r['email']) ?></td>
              <td><span class="ad-pill <?= $tinfo['pill'] ?>"><?= $tinfo['label'] ?></span></td>
              <td><?= htmlspecialchars($r['city'] ?? '—') ?></td>
              <td style="font-size:12px"><?= htmlspecialchars($r['phone'] ?? '—') ?></td>
              <td class="mono"><?= (int)$r['order_count'] ?></td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="6">
              <div class="ad-empty"><i class="bi bi-people"></i><p>No users found</p></div>
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
        search:   document.getElementById('searchInput').value,
        usertype: document.getElementById('typeSelect').value,
        city:     document.getElementById('citySelect').value,
    });
    window.location.href = 'admin_users.php?' + p.toString();
}
document.getElementById('typeSelect').addEventListener('change', applyFilters);
document.getElementById('citySelect').addEventListener('change', applyFilters);
let _t;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(_t); _t = setTimeout(applyFilters, 500);
});
</script>

  </main>
</div>
</body>
</html>
