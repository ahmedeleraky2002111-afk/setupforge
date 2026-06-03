<?php
session_start();
require "db.php";
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? "") !== "admin") {
    header("Location: auth/login.php"); exit();
}

$search = trim($_GET['search'] ?? '');
$city_f = trim($_GET['city'] ?? '');

$where  = ["1=1"];
$params = [];
$pi     = 1;

if ($search !== '') {
    $where[] = "(u.name ILIKE $".$pi." OR u.email ILIKE $".($pi+1).")";
    $params[] = '%'.$search.'%';
    $params[] = '%'.$search.'%';
    $pi += 2;
}
if ($city_f !== '') {
    $where[] = "u.city ILIKE $".$pi;
    $params[] = '%'.$city_f.'%';
    $pi++;
}

$whereStr = implode(' AND ', $where);

$labors = pg_query_params($conn,
    "SELECT u.id, u.name, u.email, u.city, u.phone,
            l.skills, l.status,
            (SELECT COUNT(*) FROM jobs j WHERE j.worker_id = u.id) AS jobs_done
     FROM labors l
     JOIN users u ON u.id = l.user_id
     WHERE $whereStr
     ORDER BY u.id DESC LIMIT 200",
    $params);

$total = $labors ? pg_num_rows($labors) : 0;

$cities_res = pg_query($conn,
    "SELECT DISTINCT u.city FROM labors l JOIN users u ON u.id = l.user_id
     WHERE u.city IS NOT NULL AND u.city != '' ORDER BY 1");
$city_opts = [];
if ($cities_res) while ($r = pg_fetch_row($cities_res)) $city_opts[] = $r[0];

$ad_title = 'Labor';
$ad_page  = 'labor';
require 'admin_layout.php';
?>

<div class="ad-page-header">
  <div class="ad-page-title">Labor workers</div>
  <div class="ad-page-sub">All registered labor workers on the platform</div>
</div>

<div class="ad-toolbar">
  <div class="ad-search-input">
    <i class="bi bi-search"></i>
    <input type="text" id="searchInput" placeholder="Search by name or email..."
           value="<?= htmlspecialchars($search) ?>">
  </div>
  <select class="ad-select" id="citySelect">
    <option value="">All cities</option>
    <?php foreach ($city_opts as $c): ?>
      <option value="<?= htmlspecialchars($c) ?>" <?= $city_f === $c ? 'selected' : '' ?>>
        <?= htmlspecialchars($c) ?>
      </option>
    <?php endforeach; ?>
  </select>
  <div class="ad-btn-group">
    <span style="font-size:12px;color:var(--ad-hint);line-height:36px"><?= $total ?> workers</span>
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
            <th>City</th>
            <th>Phone</th>
            <th>Skills</th>
            <th>Jobs done</th>
            <th>Status</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($labors && pg_num_rows($labors) > 0): ?>
            <?php while ($r = pg_fetch_assoc($labors)):
              $st = $r['status'] ?? 'pending';
              $pc = $st === 'approved' ? 'success' : ($st === 'pending' ? 'warning' : 'danger');
            ?>
            <tr>
              <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
              <td style="font-size:12px;color:var(--ad-muted)"><?= htmlspecialchars($r['email']) ?></td>
              <td><?= htmlspecialchars($r['city'] ?? '—') ?></td>
              <td style="font-size:12px"><?= htmlspecialchars($r['phone'] ?? '—') ?></td>
              <td style="font-size:12px"><?= htmlspecialchars($r['skills'] ?? '—') ?></td>
              <td class="mono"><?= (int)$r['jobs_done'] ?></td>
              <td><span class="ad-pill <?= $pc ?>"><?= ucfirst($st) ?></span></td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="7">
              <div class="ad-empty"><i class="bi bi-person-badge"></i><p>No labor workers found</p></div>
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
        city:   document.getElementById('citySelect').value,
    });
    window.location.href = 'admin_labor.php?' + p.toString();
}
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
