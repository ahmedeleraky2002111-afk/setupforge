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

// Create subscription table if it doesn't exist
$createTableSQL = "
  CREATE TABLE IF NOT EXISTS vendor_subscriptions (
    id SERIAL PRIMARY KEY,
    vendor_user_id INTEGER NOT NULL UNIQUE,
    plan_name VARCHAR(50) NOT NULL DEFAULT 'premium',
    status VARCHAR(20) NOT NULL DEFAULT 'pending',
    amount DECIMAL(10, 2) NOT NULL DEFAULT 400.00,
    starts_at TIMESTAMP WITH TIME ZONE,
    expires_at TIMESTAMP WITH TIME ZONE,
    payment_method VARCHAR(50),
    payment_reference VARCHAR(255),
    pending_charge DECIMAL(10, 2) DEFAULT 0.00,
    charge_deducted BOOLEAN DEFAULT FALSE,
    created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
    updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
  );
  CREATE INDEX IF NOT EXISTS idx_vendor_subscriptions_vendor_user_id 
  ON vendor_subscriptions(vendor_user_id);
  CREATE INDEX IF NOT EXISTS idx_vendor_subscriptions_status 
  ON vendor_subscriptions(status);
";
pg_query($conn, $createTableSQL);

// Add missing columns to existing table
$alterSQL = "
  ALTER TABLE vendor_subscriptions
  ADD COLUMN IF NOT EXISTS pending_charge DECIMAL(10, 2) DEFAULT 0.00,
  ADD COLUMN IF NOT EXISTS charge_deducted BOOLEAN DEFAULT FALSE;
";
pg_query($conn, $alterSQL);

$subscription = null;
$subRes = pg_query_params($conn, "
  SELECT plan_name, status, starts_at, expires_at, amount, created_at, updated_at, 
         COALESCE(pending_charge, 0.00) as pending_charge, 
         COALESCE(charge_deducted, FALSE) as charge_deducted
  FROM vendor_subscriptions
  WHERE vendor_user_id = $1
  ORDER BY id DESC
  LIMIT 1
", [$vendorId]);
$subscription = $subRes ? pg_fetch_assoc($subRes) : null;

$isActive = false;
$chargeDeducted = false;
$pendingCharge = 0.00;

if ($subscription) {
  $status = strtolower((string)($subscription["status"] ?? ""));
  $expiresAt = $subscription["expires_at"] ?? null;
  $isActive = $status === "active" && (!$expiresAt || strtotime($expiresAt) > time());

  $pendingCharge = (float)($subscription["pending_charge"] ?? 0);
  $chargeValue = strtolower((string)($subscription["charge_deducted"] ?? "f"));
  $chargeDeducted = in_array($chargeValue, ["1", "t", "true", "yes"], true);
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
      <a href="vendor_subscription.php" class="btn btn-light btn-sm px-3 fw-semibold d-lg-none sf-subscription-mobile-btn"><?= $isActive ? '✓ Activated' : 'Subscription' ?></a>
      <a href="../auth/logout.php" class="btn btn-outline-light btn-sm px-3 fw-semibold">Logout</a>
    </div>
  </div>
</nav>

<div class="v-wrap">
  <div class="sf-subscription-page">

    <?php if (isset($_GET["error"])): ?>
      <div class="v-alert v-alert-danger"><?= h($_GET["error"]) ?></div>
    <?php endif; ?>

    <?php if (isset($_GET["subscription"]) && $_GET["subscription"] === "activated"): ?>
      <div class="v-alert v-alert-success">
        <strong>✓ Subscription Activated!</strong><br>
        Your premium vendor plan is now active for 30 days.<br>
        <strong>Note:</strong> A charge of <strong>400 EGP</strong> will be deducted from your next sale.
      </div>
    <?php endif; ?>

    <?php if (isset($_GET["subscription"]) && $_GET["subscription"] === "cancelled"): ?>
      <div class="v-alert v-alert-success">
        <strong>✓ Subscription Cancelled</strong><br>
        Your vendor subscription has been cancelled. You can reactivate it anytime.
      </div>
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
          <div class="sf-plan-price">400 EGP</div>
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
        <?php if ($pendingCharge > 0 && !$chargeDeducted): ?>
          <div style="margin-top: 12px; padding: 12px; background: rgba(249, 115, 22, 0.08); border-radius: 12px; border-left: 4px solid #f97316;">
            <strong style="color: #c2410c;">⚠ Pending Charge</strong><br>
            <span style="color: #64748b; font-size: 13px;">
              Amount of <strong>400 EGP</strong> will be deducted from your next sale.
            </span>
          </div>
        <?php endif; ?>
      <?php endif; ?>

      <ul class="sf-plan-features">
        <li>Priority product visibility in customer recommendations.</li>
        <li>Premium vendor badge on your dashboard and vendor profile.</li>
        <li>Higher trust level for customers when comparing vendors.</li>
        <li>Early access to future vendor tools and dashboard upgrades.</li>
      </ul>

      <form class="sf-subscription-form" method="post" id="subscriptionForm" action="<?= $isActive ? 'vendor_cancel_subscription.php' : 'vendor_activate_subscription.php' ?>">
        <button class="v-btn <?= $isActive ? "v-btn-danger" : "v-btn-premium" ?>" type="button" id="mainBtn">
          <?= $isActive ? "Cancel Subscription" : "Activate Subscription" ?>
        </button>
        <a class="v-btn v-btn-outline" href="vendor_dashboard.php">Back to Dashboard</a>
      </form>

      <!-- Confirmation Modal for Activation -->
      <?php if (!$isActive): ?>
      <div id="activationWarning" style="display: none; margin-top: 20px; padding: 20px; border-radius: 16px; background: rgba(0, 76, 172, 0.08); border: 2px solid rgba(0, 76, 172, 0.3);">
        <div style="margin-bottom: 16px;">
          <h3 style="margin: 0 0 8px 0; color: #004cac;">ℹ Important Information</h3>
          <p style="margin: 0; color: #64748b; font-size: 14px;">
            A charge of <strong>400 EGP</strong> will be deducted from your next sale.
          </p>
          <p style="margin: 8px 0 0 0; color: #64748b; font-size: 13px;">
            Your premium vendor plan will be active for 30 days with priority product visibility and premium badge benefits.
          </p>
        </div>

        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
          <button type="submit" form="subscriptionForm" class="v-btn v-btn-premium" style="color: white; border: none;">
            Yes, Activate Subscription
          </button>
          <button type="button" class="v-btn v-btn-outline" onclick="hideActivationWarning()">
            Cancel
          </button>
        </div>
      </div>
      <?php endif; ?>

      <!-- Confirmation Modal for Cancellation -->
      <?php if ($isActive): ?>
      <div id="cancellationWarning" style="display: none; margin-top: 20px; padding: 20px; border-radius: 16px; background: rgba(220, 38, 38, 0.08); border: 2px solid rgba(220, 38, 38, 0.3);">
        <div style="margin-bottom: 16px;">
          <h3 style="margin: 0 0 8px 0; color: #dc2626;">⚠ Warning: Subscription Cancellation</h3>
          <p style="margin: 0; color: #64748b; font-size: 14px;">
            If you cancel now, you will still be <strong>charged 400 EGP for the current month</strong>.
          </p>
          <p style="margin: 8px 0 0 0; color: #64748b; font-size: 13px;">
            Your premium vendor plan will be active until the end of this billing period.
          </p>
        </div>

        <div style="display: flex; gap: 12px; flex-wrap: wrap;">
          <button type="submit" form="subscriptionForm" class="v-btn v-btn-danger" style="background: #dc2626; color: white; border: none;">
            Yes, Cancel Subscription
          </button>
          <button type="button" class="v-btn v-btn-outline" onclick="hideCancellationWarning()">
            Keep Subscription
          </button>
        </div>
      </div>
      <?php endif; ?>

      <script>
      document.getElementById('mainBtn').addEventListener('click', function(e) {
        e.preventDefault();
        <?php if ($isActive): ?>
          showCancellationWarning();
        <?php else: ?>
          showActivationWarning();
        <?php endif; ?>
      });

      function showActivationWarning() {
        document.getElementById('activationWarning').style.display = 'block';
        document.getElementById('mainBtn').style.display = 'none';
        window.scrollTo({ top: document.getElementById('activationWarning').offsetTop - 100, behavior: 'smooth' });
      }

      function hideActivationWarning() {
        document.getElementById('activationWarning').style.display = 'none';
        document.getElementById('mainBtn').style.display = 'inline-flex';
      }

      function showCancellationWarning() {
        document.getElementById('cancellationWarning').style.display = 'block';
        document.getElementById('mainBtn').style.display = 'none';
        window.scrollTo({ top: document.getElementById('cancellationWarning').offsetTop - 100, behavior: 'smooth' });
      }

      function hideCancellationWarning() {
        document.getElementById('cancellationWarning').style.display = 'none';
        document.getElementById('mainBtn').style.display = 'inline-flex';
      }
      </script>
    </section>

  </div>
</div>

</body>
</html>
