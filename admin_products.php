<?php
session_start();
require "db.php";
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? "") !== "admin") {
    header("Location: auth/login.php"); exit();
}

$search  = trim($_GET['search'] ?? '');
$module  = $_GET['module'] ?? 'all';
$tier    = $_GET['tier']   ?? 'all';

$where  = ["1=1"];
$params = [];
$pi     = 1;

if ($module !== 'all') {
    $where[] = "p.module = $".$pi; $params[] = $module; $pi++;
}
if ($tier !== 'all') {
    $where[] = "p.tier = $".$pi; $params[] = $tier; $pi++;
}
if ($search !== '') {
    $where[] = "(p.product_name ILIKE $".$pi." OR p.brand ILIKE $".($pi+1).")";
    $params[] = '%'.$search.'%';
    $params[] = '%'.$search.'%';
    $pi += 2;
}

$whereStr = implode(' AND ', $where);

$products = pg_query_params($conn,
    "SELECT p.id, p.product_name, p.brand, p.module, p.tier,
            p.price, p.stock_quantity, p.avg_rating,
            u.name AS vendor_name,
            (SELECT COUNT(*) FROM order_items oi WHERE oi.product_id = p.id) AS order_count
     FROM products p
     LEFT JOIN users u ON u.id = p.vendor_user_id
     WHERE $whereStr
     ORDER BY order_count DESC NULLS LAST, p.id DESC
     LIMIT 200",
    $params);

$total = $products ? pg_num_rows($products) : 0;

$ad_title = 'Products';
$ad_page  = 'products';
require 'admin_layout.php';

function money($n) { return number_format((float)$n, 0) . ' EGP'; }
$tierPill = ['premium' => 'success', 'balanced' => 'info', 'starter' => 'neutral'];
?>

<div class="ad-page-header">
  <div class="ad-page-title">Products</div>
  <div class="ad-page-sub">All products listed across all vendors</div>
</div>

<div class="ad-toolbar">
  <div class="ad-search-input">
    <i class="bi bi-search"></i>
    <input type="text" id="searchInput" placeholder="Search by product name or brand..."
           value="<?= htmlspecialchars($search) ?>">
  </div>
  <select class="ad-select" id="moduleSelect">
    <option value="all">All modules</option>
    <option value="kitchen"   <?= $module === 'kitchen'   ? 'selected' : '' ?>>Kitchen</option>
    <option value="pos"       <?= $module === 'pos'       ? 'selected' : '' ?>>POS & Tech</option>
    <option value="furniture" <?= $module === 'furniture' ? 'selected' : '' ?>>Furniture</option>
    <option value="ac"        <?= $module === 'ac'        ? 'selected' : '' ?>>AC</option>
  </select>
  <select class="ad-select" id="tierSelect">
    <option value="all">All tiers</option>
    <option value="premium"  <?= $tier === 'premium'  ? 'selected' : '' ?>>Premium</option>
    <option value="balanced" <?= $tier === 'balanced' ? 'selected' : '' ?>>Balanced</option>
    <option value="starter"  <?= $tier === 'starter'  ? 'selected' : '' ?>>Starter</option>
  </select>
  <div class="ad-btn-group">
    <span style="font-size:12px;color:var(--ad-hint);line-height:36px"><?= $total ?> products</span>
  </div>
</div>

<div class="ad-box">
  <div class="ad-box-body no-pad">
    <div class="ad-table-wrap">
      <table class="ad-table">
        <thead>
          <tr>
            <th>Product</th>
            <th>Brand</th>
            <th>Vendor</th>
            <th>Module</th>
            <th>Tier</th>
            <th>Price</th>
            <th>Stock</th>
            <th>Rating</th>
            <th>Orders</th>
          </tr>
        </thead>
        <tbody>
          <?php if ($products && pg_num_rows($products) > 0): ?>
            <?php while ($r = pg_fetch_assoc($products)):
              $tkey = strtolower($r['tier'] ?? '');
              $tc   = $tierPill[$tkey] ?? 'neutral';
              $stock = (int)$r['stock_quantity'];
              $stockPill = $stock === 0 ? 'danger' : ($stock < 5 ? 'warning' : 'success');
            ?>
            <tr>
              <td><strong><?= htmlspecialchars($r['product_name']) ?></strong></td>
              <td style="font-size:12px"><?= htmlspecialchars($r['brand'] ?? '—') ?></td>
              <td style="font-size:12px"><?= htmlspecialchars($r['vendor_name'] ?? '—') ?></td>
              <td><?= ucfirst(htmlspecialchars($r['module'] ?? '—')) ?></td>
              <td><span class="ad-pill <?= $tc ?>"><?= ucfirst($tkey ?: '—') ?></span></td>
              <td class="mono"><?= money($r['price']) ?></td>
              <td><span class="ad-pill <?= $stockPill ?>"><?= $stock ?></span></td>
              <td>
                <span style="font-size:13px;color:var(--ad-warning-text)">
                  ★ <?= number_format((float)$r['avg_rating'], 1) ?>
                </span>
              </td>
              <td class="mono"><?= (int)$r['order_count'] ?></td>
            </tr>
            <?php endwhile; ?>
          <?php else: ?>
            <tr><td colspan="9">
              <div class="ad-empty"><i class="bi bi-box-seam"></i><p>No products found</p></div>
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
        module: document.getElementById('moduleSelect').value,
        tier:   document.getElementById('tierSelect').value,
    });
    window.location.href = 'admin_products.php?' + p.toString();
}
document.getElementById('moduleSelect').addEventListener('change', applyFilters);
document.getElementById('tierSelect').addEventListener('change', applyFilters);
let _t;
document.getElementById('searchInput').addEventListener('input', function() {
    clearTimeout(_t); _t = setTimeout(applyFilters, 500);
});
</script>

  </main>
</div>
</body>
</html>
