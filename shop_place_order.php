<?php
// shop_place_order.php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: auth/login.php?next=" . urlencode("shop_checkout.php"));
    exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    header("Location: shop_checkout.php");
    exit;
}

$userId = (int)$_SESSION["user_id"];
$isBuyNow = !empty($_SESSION["buy_now"]);
$cart = $isBuyNow
    ? [(string)$_SESSION["buy_now"]["product_id"] => ["qty" => $_SESSION["buy_now"]["qty"]]]
    : ($_SESSION["shop_cart"] ?? []);

if (empty($cart)) {
    header("Location: cart.php");
    exit;
}

function fail($msg) {
    http_response_code(400);
    echo "<h3>ORDER ERROR</h3><p>" . htmlspecialchars($msg) . "</p><a href='cart.php'>Back to cart</a>";
    exit;
}

// Load products from DB for accurate prices
$ids    = array_map('intval', array_keys($cart));
$idList = implode(',', $ids);

$res = pg_query($conn, "
    SELECT id, product_name, price, stock_quantity, vendor_user_id
    FROM products WHERE id IN ($idList)
");

$dbProducts = [];
if ($res) {
    while ($row = pg_fetch_assoc($res)) $dbProducts[(int)$row["id"]] = $row;
}

$allItems   = [];
$orderTotal = 0;

foreach ($cart as $key => $cartItem) {
    $pid = (int)$key;
    if (!isset($dbProducts[$pid])) continue;
    $p     = $dbProducts[$pid];
    $qty   = max(1, (int)$cartItem["qty"]);
    $price = (float)$p["price"];

    if ((int)$p["stock_quantity"] <= 0) {
        fail("'" . $p["product_name"] . "' is out of stock. Please remove it from your cart.");
    }

    $orderTotal += $qty * $price;
    $allItems[] = [
        "product_id" => $pid,
        "qty"        => $qty,
        "unit_price" => $price,
        "vendor_user_id" => $p["vendor_user_id"] ?? null,
    ];
}

if (empty($allItems) || $orderTotal <= 0) {
    fail("Your cart is empty.");
}

// Delivery info
$deliveryLocation = trim((string)($_POST["delivery_location"] ?? ""));
$deliveryName     = trim((string)($_POST["delivery_name"] ?? ""));
$deliveryPhone    = trim((string)($_POST["delivery_phone"] ?? ""));
$orderNotes       = trim((string)($_POST["order_notes"] ?? ""));

// Determine customer vs business
$bizCheck = @pg_query_params($conn,
    "SELECT 1 FROM businesses WHERE user_id = \$1 LIMIT 1", [$userId]);
$isBusiness = ($bizCheck && pg_num_rows($bizCheck) > 0);

$customerId = $isBusiness ? null : $userId;
$businessId = $isBusiness ? $userId  : null;

pg_query($conn, "BEGIN");

try {
    // Insert order with order_type = 'shop'
    $insOrder = pg_query_params($conn, "
        INSERT INTO orders (
            status, customer_user_id, business_user_id,
            service_fees, order_total,
            delivery_location, payment_status,
            order_type
        )
        VALUES ('pending', \$1, \$2, 0, \$3, \$4, 'pending', 'shop')
        RETURNING id
    ", [
        $customerId,
        $businessId,
        $orderTotal,
        $deliveryLocation !== "" ? $deliveryLocation : null,
    ]);

    if (!$insOrder) throw new Exception("Order insert failed: " . pg_last_error($conn));

    $row     = pg_fetch_assoc($insOrder);
    $orderId = (int)$row["id"];

    if ($orderId <= 0) throw new Exception("No order ID returned.");

    // Insert order items
    foreach ($allItems as $item) {
        $ok = pg_query_params($conn,
            "INSERT INTO order_items (order_id, product_id, quantity, unit_price)
             VALUES (\$1, \$2, \$3, \$4)",
            [$orderId, $item["product_id"], $item["qty"], $item["unit_price"]]
        );
        if (!$ok) throw new Exception("order_items insert failed: " . pg_last_error($conn));
    }

    pg_query($conn, "COMMIT");

    // Clear buy_now session if used
    if ($isBuyNow) {
        unset($_SESSION["buy_now"]);
    } else {
        // Regular cart is cleared after payment in success.php
        // Do not clear here — payment may still fail
    }

    // Store order ID for success page
    $_SESSION["shop_last_order_id"] = $orderId;

    // Redirect to Paymob — same flow as setup orders
    header("Location: payments/paymob_checkout.php?order_id=" . $orderId);
    exit;

} catch (Throwable $e) {
    pg_query($conn, "ROLLBACK");
    fail($e->getMessage());
}