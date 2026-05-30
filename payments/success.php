<?php
session_start();
require_once "../db.php";

if (!isset($conn) || !$conn) {
  http_response_code(500);
  die("DB connection missing.");
}

$orderId = (int)($_GET["order_id"] ?? 0);
$order = null;

function h($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

if ($orderId > 0) {
  $res = pg_query_params($conn, "
    SELECT
      id,
      customer_user_id,
      business_user_id,
      order_total,
      payment_reference,
      payment_method,
      paid_at,
      payment_status,
      status,
      order_type
    FROM orders
    WHERE id = $1
    LIMIT 1
  ", [$orderId]);

  if ($res && pg_num_rows($res) > 0) {
    $order = pg_fetch_assoc($res);
  }
}

if (!$order) {
  die("Order not found.");
}

$sessionUserId = (int)($_SESSION["user_id"] ?? 0);
$orderCustomerId = (int)($order["customer_user_id"] ?? 0);
$orderBusinessId = (int)($order["business_user_id"] ?? 0);

// Only check ownership if user is logged in
// If not logged in, still show the success page (Paymob redirect)
// If session is lost (ngrok/redirect domain switch), restore it from the order
if ($sessionUserId <= 0) {
  $restoreId = $orderBusinessId > 0 ? $orderBusinessId : $orderCustomerId;
  if ($restoreId > 0) {
    $_SESSION["user_id"] = $restoreId;
    $sessionUserId = $restoreId;
  }
} else {
  $isOwner =
    ($orderCustomerId > 0 && $orderCustomerId === $sessionUserId) ||
    ($orderBusinessId > 0 && $orderBusinessId === $sessionUserId);

  if (!$isOwner) {
    // Session mismatch — restore from order
    $restoreId = $orderBusinessId > 0 ? $orderBusinessId : $orderCustomerId;
    if ($restoreId > 0) {
      $_SESSION["user_id"] = $restoreId;
      $sessionUserId = $restoreId;
    }
  }
}

if (($order["payment_status"] ?? "") !== "paid") {
  // Retry a few times — callback may still be processing
  $retries = 0;
  while ($retries < 5 && ($order["payment_status"] ?? "") !== "paid") {
    sleep(1);
    $retryRes = pg_query_params($conn, "SELECT payment_status FROM orders WHERE id = $1 LIMIT 1", [$orderId]);
    if ($retryRes && pg_num_rows($retryRes) > 0) {
      $retryRow = pg_fetch_assoc($retryRes);
      $order["payment_status"] = $retryRow["payment_status"];
    }
    $retries++;
  }
  if (($order["payment_status"] ?? "") !== "paid") {
    header("Location: payment_failed.php?order_id=" . urlencode((string)$orderId));
    exit;
  }
}
$orderType = trim((string)($order["order_type"] ?? "setup"));

if ($orderType === 'shop') {
    unset($_SESSION["shop_cart"]);
    unset($_SESSION["shop_last_order_id"]);
} else {
    unset($_SESSION["carts"]);
    unset($_SESSION["wizard"]);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SetupForge - Payment Success</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/style.css" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark sf-navbar">
  <div class="container d-flex align-items-center">
    <a class="navbar-brand d-flex align-items-center gap-2" href="../home.php">
      <div class="sf-logo"><img src="../assets/images/Logo.png" alt="SetupForge Logo"></div>
      <span class="fw-bold text-white">SetupForge</span>
    </a>
  </div>
</nav>
<main class="container py-5">
  <div class="row justify-content-center">
    <div class="col-lg-8">
      <div class="p-4 p-lg-5 border rounded-4 bg-white text-center shadow-sm">
        <h1 class="fw-bold mb-2">Payment succesful</h1>
        <p class="text-secondary mb-4">
          This page shows the latest status returned to your order.
        </p>

        <div class="border rounded-4 bg-light p-3 p-md-4 text-start mx-auto mb-4" style="max-width: 560px;">
          <div class="d-flex justify-content-between mb-2">
            <span class="text-secondary">Order ID</span>
            <strong>#<?= (int)$order["id"] ?></strong>
          </div>

          <div class="d-flex justify-content-between mb-2">
            <span class="text-secondary">Order Total</span>
            <strong><?= number_format((float)$order["order_total"], 0) ?> EGP</strong>
          </div>

          <div class="d-flex justify-content-between mb-2">
            <span class="text-secondary">Order Status</span>
            <strong><?= h($order["status"] ?? "pending") ?></strong>
          </div>

          <div class="d-flex justify-content-between mb-2">
            <span class="text-secondary">Payment Status</span>
            <strong><?= h($order["payment_status"] ?? "pending") ?></strong>
          </div>

          <?php if (!empty($order["payment_method"])): ?>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-secondary">Payment Method</span>
              <strong><?= h($order["payment_method"]) ?></strong>
            </div>
          <?php endif; ?>

          <?php if (!empty($order["payment_reference"])): ?>
            <div class="d-flex justify-content-between mb-2">
              <span class="text-secondary">Payment Reference</span>
              <strong><?= h($order["payment_reference"]) ?></strong>
            </div>
          <?php endif; ?>

          <?php if (!empty($order["paid_at"])): ?>
            <div class="d-flex justify-content-between">
              <span class="text-secondary">Paid At</span>
              <strong><?= h($order["paid_at"]) ?></strong>
            </div>
          <?php endif; ?>
        </div>

        <div class="d-flex justify-content-center gap-2 flex-wrap">
  <a href="../home.php" class="btn btn-dark px-4">Go Home</a>
  <a href="../packages.php" class="btn btn-outline-secondary px-4">Build Another Setup</a>

  <?php if ($orderType === 'shop'): ?>
    <a href="../products.php" class="btn btn-primary px-4">Continue Shopping</a>
<?php else: ?>
    <?php if (!empty($order["business_user_id"])): ?>
        <a href="../service_jobs.php" class="btn btn-primary px-4">Manage Service Jobs</a>
        <a href="../business_overview.php?order_id=<?= (int)$order["id"] ?>" class="btn btn-outline-dark px-4">View Business Overview</a>
    <?php endif; ?>
<?php endif; ?>
</div>
      </div>
    </div>
  </div>
</main>

</body>
</html>