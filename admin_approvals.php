<?php
session_start();
require "db.php";
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? "") !== "admin") {
    header("Location: auth/login.php"); exit();
}

$tab = $_GET['tab'] ?? 'vendors';

/* Pending vendors */
$pendingVendors = pg_query($conn,
    "SELECT u.id, u.name, u.email, u.city, u.phone,
            v.items_type, v.status,
            (SELECT COUNT(*) FROM products p WHERE p.vendor_user_id = u.id) AS product_count
     FROM vendors v JOIN users u ON u.id = v.user_id
     WHERE v.status = 'pending' ORDER BY u.id DESC");

/* Pending labors */
$pendingLabors = pg_query($conn,
    "SELECT u.id, u.name, u.email, u.city, u.phone,
            l.skills, l.status
     FROM labors l JOIN users u ON u.id = l.user_id
     WHERE l.status = 'pending' ORDER BY u.id DESC");

$cnt_v = $pendingVendors ? pg_num_rows($pendingVendors) : 0;
$cnt_l = $pendingLabors  ? pg_num_rows($pendingLabors)  : 0;

$ad_title = 'Approvals';
$ad_page  = 'approvals';
require 'admin_layout.php';
?>

<div class="ad-page-header">
  <div class="ad-page-title">Approvals</div>
  <div class="ad-page-sub">Review and approve pending vendor and labor applications</div>
</div>

<?php if ($cnt_v + $cnt_l > 0): ?>
<div class="ad-alert is-warning">
  <i class="bi bi-exclamation-triangle-fill"></i>
  <div class="ad-alert-items">
    <?php if ($cnt_v > 0): ?>
      <div class="ad-alert-item"><div class="ad-alert-dot"></div>
        <span><?= $cnt_v ?> vendor <?= $cnt_v == 1 ? 'application' : 'applications' ?> awaiting review</span>
      </div>
    <?php endif; ?>
    <?php if ($cnt_l > 0): ?>
      <div class="ad-alert-item"><div class="ad-alert-dot"></div>
        <span><?= $cnt_l ?> labor <?= $cnt_l == 1 ? 'application' : 'applications' ?> awaiting review</span>
      </div>
    <?php endif; ?>
  </div>
</div>
<?php endif; ?>

<div class="ad-tabs">
  <a href="?tab=vendors" class="ad-tab <?= $tab === 'vendors' ? 'active' : '' ?>">
    <i class="bi bi-shop"></i> Vendor applications
    <?php if ($cnt_v > 0): ?><span class="ad-tab-count"><?= $cnt_v ?></span><?php endif; ?>
  </a>
  <a href="?tab=labor" class="ad-tab <?= $tab === 'labor' ? 'active' : '' ?>">
    <i class="bi bi-person-badge"></i> Labor applications
    <?php if ($cnt_l > 0): ?><span class="ad-tab-count"><?= $cnt_l ?></span><?php endif; ?>
  </a>
</div>

<?php if ($tab === 'vendors'): ?>

  <div class="ad-box">
    <div class="ad-box-head">
      <div class="ad-box-title">Pending vendor applications</div>
      <span class="ad-pill <?= $cnt_v > 0 ? 'warning' : 'success' ?>"><?= $cnt_v > 0 ? "$cnt_v pending" : 'All clear' ?></span>
    </div>
    <div class="ad-box-body no-pad">
      <?php if ($cnt_v > 0): ?>
      <div class="ad-table-wrap">
        <table class="ad-table">
          <thead>
            <tr>
              <th>Vendor</th>
              <th>Email</th>
              <th>City</th>
              <th>Item type</th>
              <th>Products</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($r = pg_fetch_assoc($pendingVendors)): ?>
            <tr>
              <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
              <td style="color:var(--ad-muted)"><?= htmlspecialchars($r['email']) ?></td>
              <td><?= htmlspecialchars($r['city'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['items_type'] ?? '—') ?></td>
              <td class="mono"><?= (int)$r['product_count'] ?></td>
              <td>
                <a href="approve_vendor.php?id=<?= $r['id'] ?>" class="ad-action approve">
                  <i class="bi bi-check-lg"></i> Approve
                </a>
                <a href="reject_vendor.php?id=<?= $r['id'] ?>" class="ad-action reject"
                   onclick="return confirm('Reject <?= htmlspecialchars($r['name']) ?>?')">
                  <i class="bi bi-x-lg"></i> Reject
                </a>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <div class="ad-empty">
          <i class="bi bi-check-circle" style="font-size:36px;color:var(--ad-success-text);opacity:1"></i>
          <p>No pending vendor applications</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

<?php else: ?>

  <div class="ad-box">
    <div class="ad-box-head">
      <div class="ad-box-title">Pending labor applications</div>
      <span class="ad-pill <?= $cnt_l > 0 ? 'warning' : 'success' ?>"><?= $cnt_l > 0 ? "$cnt_l pending" : 'All clear' ?></span>
    </div>
    <div class="ad-box-body no-pad">
      <?php if ($cnt_l > 0): ?>
      <div class="ad-table-wrap">
        <table class="ad-table">
          <thead>
            <tr>
              <th>Worker</th>
              <th>Email</th>
              <th>City</th>
              <th>Skills</th>
              <th>Actions</th>
            </tr>
          </thead>
          <tbody>
            <?php while ($r = pg_fetch_assoc($pendingLabors)): ?>
            <tr>
              <td><strong><?= htmlspecialchars($r['name']) ?></strong></td>
              <td style="color:var(--ad-muted)"><?= htmlspecialchars($r['email']) ?></td>
              <td><?= htmlspecialchars($r['city'] ?? '—') ?></td>
              <td><?= htmlspecialchars($r['skills'] ?? '—') ?></td>
              <td>
                <a href="approve_labor.php?id=<?= $r['id'] ?>" class="ad-action approve">
                  <i class="bi bi-check-lg"></i> Approve
                </a>
                <a href="reject_labor.php?id=<?= $r['id'] ?>" class="ad-action reject"
                   onclick="return confirm('Reject <?= htmlspecialchars($r['name']) ?>?')">
                  <i class="bi bi-x-lg"></i> Reject
                </a>
              </td>
            </tr>
            <?php endwhile; ?>
          </tbody>
        </table>
      </div>
      <?php else: ?>
        <div class="ad-empty">
          <i class="bi bi-check-circle" style="font-size:36px;color:var(--ad-success-text);opacity:1"></i>
          <p>No pending labor applications</p>
        </div>
      <?php endif; ?>
    </div>
  </div>

<?php endif; ?>

  </main>
</div>
</body>
</html>
