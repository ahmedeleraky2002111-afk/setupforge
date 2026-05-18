<?php
// place_order.php  ✅ NEW FLOW
// Creates only: orders + order_items
// Then redirects to payment.php
// Does NOT create vendor fulfillments or jobs yet

session_start();
file_put_contents(__DIR__ . "/wizard_debug.txt", "place_order session: " . print_r($_SESSION["wizard"] ?? [], true) . "\n---\n", FILE_APPEND);

if (!isset($_SESSION["user_id"])) {
  header("Location: auth/login.php?next=" . urlencode("order_summary.php"));
  exit;
}

require_once "db.php";

if (!isset($conn) || !$conn) {
  http_response_code(500);
  die("DB connection missing. Check db.php (\$conn).");
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: order_summary.php");
  exit;
}

// ---------- Helpers ----------
function fail($msg){
  http_response_code(400);
  echo "<h3>ORDER ERROR</h3><p>" . htmlspecialchars($msg) . "</p>";
  exit;
}

function cart_items($cart){
  return ($cart && isset($cart["items"]) && is_array($cart["items"])) ? $cart["items"] : [];
}

// ---------- Load carts ----------
if (!isset($_SESSION["carts"]) || !is_array($_SESSION["carts"])) {
  $_SESSION["carts"] = [];
}
if (!empty($_SESSION["wizard"]["pos_cart"])) {
  $_SESSION["carts"]["pos"] = $_SESSION["wizard"]["pos_cart"];
}
if (!empty($_SESSION["wizard"]["kitchen_cart"])) {
  $_SESSION["carts"]["kitchen"] = $_SESSION["wizard"]["kitchen_cart"];
}
if (!empty($_SESSION["wizard"]["furniture_cart"])) {
  $_SESSION["carts"]["furniture"] = $_SESSION["wizard"]["furniture_cart"];
}

$carts = $_SESSION["carts"];

// ---------- Flatten items ----------
$allItems = [];
$orderTotal = 0;

foreach ($carts as $module => $cart) {
  foreach (cart_items($cart) as $type => $it) {
    $qty = (int)($it["qty"] ?? 0);
    $unit = (float)($it["unit"] ?? 0);
    $pid = $it["product_id"] ?? null;

    if ($qty <= 0 || $unit <= 0) continue;

    if ($pid === null || $pid === "") {
      fail("Missing product_id for item: " . ($it["name"] ?? $type) . ". Make sure carts are built from DB products.");
    }

    $rowTotal = $qty * $unit;
    $orderTotal += $rowTotal;

    $allItems[] = [
      "product_id" => (int)$pid,
      "qty" => $qty,
      "unit_price" => $unit,
      "name" => (string)($it["name"] ?? ""),
      "module" => (string)$module,
    ];
  }
}

if ($orderTotal <= 0 || empty($allItems)) {
  fail("Your order is empty. Go back and add items.");
}

// ---------- Delivery ----------
$deliveryLocation = trim((string)($_POST["delivery_location"] ?? ""));
$preferredDeliveryDate = trim((string)($_POST["preferred_delivery_date"] ?? ""));

// ---------- Decide maker ----------
$userId = (int)$_SESSION["user_id"];

// Check businesses table directly — don't rely on user_type
// This handles customers who go through the wizard without being a 'business' user_type
$bizCheck = @pg_query_params($conn,
    "SELECT 1 FROM businesses WHERE user_id = $1 LIMIT 1",
    [$userId]
);
$businessExists = ($bizCheck && pg_num_rows($bizCheck) > 0);

$customerId = $businessExists ? null : $userId;
$businessId = $businessExists ? $userId : null;

$serviceFees = 0.00;

pg_query($conn, "BEGIN");

try {
  // ---------- Insert order ----------
  $insOrderSql = "
    INSERT INTO orders (
      status,
      customer_user_id,
      business_user_id,
      service_fees,
      order_total,
      delivery_location,
      payment_status,
      preferred_delivery_date
    )
    VALUES ('pending', $1, $2, $3, $4, $5, 'pending', $6)
    RETURNING id
  ";

  $resOrder = pg_query_params($conn, $insOrderSql, [
    $customerId,
    $businessId,
    $serviceFees,
    $orderTotal,
    $deliveryLocation !== "" ? $deliveryLocation : null,
    $preferredDeliveryDate !== "" ? $preferredDeliveryDate : null
  ]);

  if (!$resOrder) {
    throw new Exception("Insert order failed: " . pg_last_error($conn));
  }

  $row = pg_fetch_assoc($resOrder);
  $orderId = (int)($row["id"] ?? 0);

  // ---------- Derive counts from cart ----------
  $kitchenItemCount = 0;
  $terminalCount    = 0;
  foreach ($allItems as $it) {
    if ($it["module"] === "kitchen") $kitchenItemCount += $it["qty"];
    if ($it["module"] === "pos")     $terminalCount    += $it["qty"];
  }

  $saveJobData = pg_query_params($conn, "
  UPDATE orders
  SET labor_data = $1,
      installation_data = $2
  WHERE id = $3
", [
json_encode([
  "roles" => [
    "waiter"         => (int)($_SESSION["wizard"]["waiter_count"]         ?? 0),
    "chef"           => (int)($_SESSION["wizard"]["chef_count"]           ?? 0),
    "cashier"        => (int)($_SESSION["wizard"]["cashier_count"]        ?? 0),
    "security"       => (int)($_SESSION["wizard"]["security_count"]       ?? 0),
    "kitchen_helper" => (int)($_SESSION["wizard"]["kitchen_helper_count"] ?? 0),
    "barista"        => (int)($_SESSION["wizard"]["barista_count"] ?? 0),
    "busboy"         => (int)($_SESSION["wizard"]["busboy_count"]  ?? 0),
    "host"           => (int)($_SESSION["wizard"]["host_count"]    ?? 0),
    "cleaner"        => 0,
  ],
  "salary_amount"     => (int)($_SESSION["wizard"]["salary_amount"] ?? 0),
  "compensation_type" => $_SESSION["wizard"]["compensation_type"] ?? "monthly",
]),
json_encode([
  "services"           => $_SESSION["wizard"]["installation_services"] ?? [],
  "area_sqm"           => (int)($_SESSION["wizard"]["area_sqm"] ?? 50),   
  "ac_units"           => (int)($_SESSION["wizard"]["ac_units"] ?? 1),
  "kitchen_item_count" => $kitchenItemCount,
  "terminal_count"     => $terminalCount,
]),  $orderId
]);

if (!$saveJobData) {
  throw new Exception("Failed to save labor/installation data: " . pg_last_error($conn));
}

  if ($orderId <= 0) {
    throw new Exception("Insert order failed: no id returned.");
  }

  // ---------- Insert order items ----------
  $insItemSql = "
    INSERT INTO order_items (order_id, product_id, quantity, unit_price)
    VALUES ($1, $2, $3, $4)
  ";

  foreach ($allItems as $it) {
    $okItem = pg_query_params($conn, $insItemSql, [
      $orderId,
      (int)$it["product_id"],
      (int)$it["qty"],
      (float)$it["unit_price"]
    ]);

    if (!$okItem) {
      throw new Exception("Insert order_items failed: " . pg_last_error($conn));
    }
  }

  pg_query($conn, "COMMIT");

  // Do NOT clear carts yet until payment succeeds
header("Location: payments/paymob_checkout.php?order_id=" . urlencode((string)$orderId));exit;

} catch (Throwable $e) {
  pg_query($conn, "ROLLBACK");
  fail($e->getMessage());
}