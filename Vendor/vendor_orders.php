<?php
  // vendor_orders.php (PG / $conn)
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
  function egp($n){
    $n = (float)$n;
    return number_format((int)round($n)) . " EGP";
  }

  $sql = "
    SELECT
      o.id AS order_id,
      o.order_date,
      vof.status AS vendor_status,
      COALESCE((
        SELECT SUM(oi.quantity * oi.unit_price)
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id = o.id
          AND p.vendor_user_id = $1
      ), 0) AS vendor_total
    FROM vendor_order_fulfillments vof
    JOIN orders o ON o.id = vof.order_id
    WHERE vof.vendor_user_id = $1
    ORDER BY o.id DESC
    LIMIT 100
  ";
  $res = pg_query_params($conn, $sql, [$vendorId]);
  $orders = $res ? pg_fetch_all($res) : [];
  if (!$orders) $orders = [];
  ?>
  <!doctype html>
  <html lang="en">
  <head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width,initial-scale=1" />
    <title>Orders — SetupForge Vendor</title>
    <link rel="stylesheet" href="vendor_ui.css?v=<?= time() ?>" />
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  </head>
  <body>

<!-- VENDOR NAVBAR (website style + vendor links) -->
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
        <li class="nav-item">
          <a class="nav-link sf-navlink" href="vendor_orders.php">Orders</a>
        </li>
        <li class="nav-item">
          <a class="nav-link sf-navlink" href="vendor_products.php">My Products</a>
        </li>
        <li class="nav-item">
          <a class="nav-link sf-navlink" href="vendor_add_product.php">Add Product</a>
        </li>
      </ul>
    </div>

    <div class="d-flex justify-content-end flex-grow-1 gap-2">
      <a href="../auth/logout.php" class="btn btn-outline-light btn-sm px-3 fw-semibold">Logout</a>
    </div>

  </div>
</nav>
  <div class="v-wrap">
    <div class="v-head">
      <div>
        <h1 class="v-title">Orders</h1>
        <div class="v-sub">All orders assigned to your vendor account.</div>
      </div>
    </div>

    <div class="v-section">
      <div class="v-card-head">
        <div>
          <div class="v-card-title">Order List</div>
          <div class="v-card-sub">Showing up to 100 latest orders.</div>
        </div>
      </div>

      <div class="v-table-wrap">
        <table class="v-table">
          <thead>
            <tr>
              <th>Order ID</th>
              <th>Date</th>
              <th>Vendor Status</th>
              <th>Vendor Total</th>
              <th>Action</th>
            </tr>
          </thead>
          <tbody>
          <?php if (empty($orders)): ?>
            <tr><td colspan="5" class="v-empty">No orders found for your vendor yet.</td></tr>
          <?php else: ?>
            <?php foreach ($orders as $o): ?>
              <?php
                $status = strtolower((string)($o["vendor_status"] ?? ""));
                $badgeClass = "v-badge";
                if ($status === "pending") $badgeClass .= " b-pending";
                else if ($status === "processing") $badgeClass .= " b-processing";
                else if ($status === "delivered") $badgeClass .= " b-delivered";
                else if ($status === "accepted") $badgeClass .= " b-processing";
                else if ($status === "rejected") $badgeClass .= " b-pending";
              ?>
              <tr>
                <td>#<?= h($o["order_id"] ?? "") ?></td>
                <td><?= h($o["order_date"] ?? "") ?></td>
                <td><span class="<?= h($badgeClass) ?>"><?= h($o["vendor_status"] ?? "unknown") ?></span></td>
                <td><?= egp($o["vendor_total"] ?? 0) ?></td>
                <td>
                  <a class="v-btn v-btn-sm v-btn-outline"
                    href="vendor_order_details.php?id=<?= urlencode((string)($o["order_id"] ?? "")) ?>">
                    View
                  </a>
                </td>
              </tr>
            <?php endforeach; ?>
          <?php endif; ?>
          </tbody>
        </table>
      </div>
    </div>
  </div>

  </body>
  </html>