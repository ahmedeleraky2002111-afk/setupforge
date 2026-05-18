<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? "") !== "vendor") {
  header("Location: ../auth/login.php?error=" . urlencode("Please login as vendor."));
  exit;
}

if (!isset($conn) || !$conn) {
  http_response_code(500);
  die("DB connection missing. Check db.php (\$conn).");
}

$vendorId = (int)$_SESSION["user_id"];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, "UTF-8"); }

$subscription = null;
$subTableCheck = pg_query($conn, "SELECT to_regclass('public.vendor_subscriptions') AS table_name");
$subTableRow = $subTableCheck ? pg_fetch_assoc($subTableCheck) : null;
$hasSubscriptionTable = !empty($subTableRow["table_name"]);

if ($hasSubscriptionTable) {
  $subRes = pg_query_params($conn, "
    SELECT plan_name, status, starts_at, expires_at, amount, created_at, updated_at
    FROM vendor_subscriptions
    WHERE vendor_user_id = $1
    ORDER BY id DESC
    LIMIT 1
  ", [$vendorId]);
  $subscription = $subRes ? pg_fetch_assoc($subRes) : null;
}

$isActive = false;
if ($subscription) {
  $status = strtolower((string)($subscription["status"] ?? ""));
  $expiresAt = $subscription["expires_at"] ?? null;
  $isActive = $status === "active" && (!$expiresAt || strtotime($expiresAt) > time());
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8" />
  <meta name="viewport" content="width=device-width,initial-scale=1" />
  <title>Vendor Subscription — SetupForge</title>
  <link rel="stylesheet" href="./vendor_ui.css?v=<?= time() ?>" />
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark sf-navbar">
  <div class="container d-flex align-items-center">
    <div class="d-flex align-items-center flex-grow-1">
      <a class="navbar-brand d-flex align-items-center gap-2" href="vendor_dashboard.php">
        <div class="sf-logo"><img src="../assets/images/Logo.png" alt="SetupForge Logo"></div>
        <span class="fw-bold text-white">SetupForge</span>
      </a>
    </div>

    <div class="d-none d-lg-flex justify-content-center flex-grow-1">
      <ul class="navbar-nav align-items-center gap-3">
        <li class="nav-item"><a class="nav-link sf-navlink" href="vendor_dashboard.php">Dashboard</a></li>
        <li class="nav-item"><a class="nav-link sf-navlink" href="vendor_orders.php">Orders</a></li>
        <li class="nav-item"><a class="nav-link sf-navlink" href="vendor_products.php">My Products</a></li>
        <li class="nav-item"><a class="nav-link sf-navlink" href="vendor_add_product.php">Add Product</a></li>
        <li class="nav-item"><a class="nav-link sf-navlink active" href="vendor_subscription.php"><?= $isActive ? "Activated" : "Subscription" ?></a></li>
      </ul>
    </div>

    <div class="d-flex justify-content-end flex-grow-1 gap-2">
      <a href="../auth/logout.php" class="btn btn-outline-light btn-sm px-3 fw-semibold">Logout</a>
    </div>
  </div>
</nav>

<div class="v-wrap">
  <div class="sf-subscription-page">

    <?php if (!$hasSubscriptionTable): ?>
      <div class="v-alert v-alert-danger">
        The subscription table is missing. Run the SQL file named <strong>vendor_subscription_schema.sql</strong> first in pgAdmin.
      </div>
    <?php endif; ?>

    <?php if (isset($_GET["error"])): ?>
      <div class="v-alert v-alert-danger"><?= h($_GET["error"]) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET["subscription"]) && $_GET["subscription"] === "activated"): ?>
      <div class="v-alert v-alert-success">Subscription activated successfully.</div>
    <?php endif; ?>

    <section class="sf-subscription-hero">
      <div class="vd-kicker">
        <span class="vd-kicker-dot"></span>
        Vendor Subscription
      </div>
      <h1><?= $isActive ? "Activated" : "Subscription" ?></h1>
      <p>
        <?= $isActive
          ? "Your vendor subscription is active. You can view the plan features below."
          : "Open this page to view the subscription features and activate your vendor plan." ?>
      </p>
    </section>

    <section class="sf-plan-card">
      <div class="sf-plan-top">
        <div>
          <div class="sf-plan-name">Premium Vendor Plan</div>
          <div class="sf-plan-price">2000 EGP</div>
          <div class="sf-plan-muted">30 days access</div>
        </div>

        <?php if ($isActive): ?>
          <span class="v-badge v-badge-delivered">Activated</span>
        <?php else: ?>
          <span class="v-badge v-badge-processing">Available</span>
        <?php endif; ?>
      </div>

      <?php if ($subscription): ?>
        <div class="v-divider"></div>
        <div class="sf-plan-muted">
          Status: <strong><?= h($subscription["status"] ?? "unknown") ?></strong>
          <?php if (!empty($subscription["expires_at"])): ?>
            · Expires at: <strong><?= h($subscription["expires_at"]) ?></strong>
          <?php endif; ?>
        </div>
      <?php endif; ?>

      <ul class="sf-plan-features">
        <li>Priority product visibility in customer recommendations.</li>
        <li>Premium vendor badge on your dashboard and vendor profile.</li>
        <li>Higher trust level for customers when comparing vendors.</li>
        <li>Early access to future vendor tools and dashboard upgrades.</li>
      </ul>

      <form class="sf-subscription-form" method="post" action="vendor_activate_subscription.php">
        <button class="v-btn <?= $isActive ? "v-btn-premium-active" : "v-btn-premium" ?>" type="submit" <?= (!$hasSubscriptionTable || $isActive) ? "disabled" : "" ?>>
          <?= $isActive ? "Activated" : "Activate Subscription" ?>
        </button>
        <a class="v-btn v-btn-outline" href="vendor_dashboard.php">Back to Dashboard</a>
      </form>
    </section>

  </div>
</div>

</body>
</html>
