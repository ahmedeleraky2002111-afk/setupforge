<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header("Content-Type: application/json");

try {
    require_once __DIR__ . "/../db.php";

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers["Authorization"] ?? $headers["authorization"] ?? "";

    if (!str_starts_with($auth, "Bearer ")) {
        echo json_encode(["ok" => false, "error" => "No token"]);
        exit;
    }

    $token = trim(substr($auth, 7));
    $userRes = pg_query_params($conn,
        "SELECT id, name, phone, city, country, street FROM users WHERE api_token = $1 LIMIT 1",
        [$token]);

    if (!$userRes || pg_num_rows($userRes) === 0) {
        echo json_encode(["ok" => false, "error" => "Unauthorized"]);
        exit;
    }

    $user = pg_fetch_assoc($userRes);
    $user_id = (int)$user["id"];

    $input = json_decode(file_get_contents("php://input"), true);
    $deliveryName     = trim($input["delivery_name"] ?? $user["name"] ?? "");
    $deliveryPhone    = trim($input["delivery_phone"] ?? $user["phone"] ?? "");
    $deliveryLocation = trim($input["delivery_location"] ?? "");
    $orderNotes       = trim($input["order_notes"] ?? "");

    if ($deliveryName === "" || $deliveryPhone === "" || $deliveryLocation === "") {
        echo json_encode(["ok" => false, "error" => "Missing delivery information"]);
        exit;
    }

    // Get cart items
    $cartRes = pg_query_params($conn,
        "SELECT ci.product_id, ci.quantity, p.price, p.stock_quantity, p.vendor_user_id
         FROM cart_items ci
         JOIN products p ON p.id = ci.product_id
         WHERE ci.user_id = $1",
        [$user_id]);

    if (!$cartRes || pg_num_rows($cartRes) === 0) {
        echo json_encode(["ok" => false, "error" => "Cart is empty"]);
        exit;
    }

    $items = [];
    $grandTotal = 0;
    while ($r = pg_fetch_assoc($cartRes)) {
        $qty = (int)$r["quantity"];
        $price = (float)$r["price"];
        $grandTotal += $qty * $price;
        $items[] = $r;
    }

    pg_query($conn, "BEGIN");

    // Create order
    $orderRes = pg_query_params($conn, "
        INSERT INTO orders (
            business_user_id, order_type, order_total, payment_status,
            status, delivery_location, order_date
        ) VALUES ($1, 'shop', $2, 'pending', 'pending', $3, NOW())
        RETURNING id
    ", [$user_id, $grandTotal, $deliveryLocation]);

    if (!$orderRes || pg_num_rows($orderRes) === 0) {
        pg_query($conn, "ROLLBACK");
        echo json_encode(["ok" => false, "error" => "Failed to create order"]);
        exit;
    }

    $order_id = (int)pg_fetch_assoc($orderRes)["id"];

    // Insert order items
    foreach ($items as $item) {
        pg_query_params($conn,
            "INSERT INTO order_items (order_id, product_id, quantity, unit_price)
             VALUES ($1, $2, $3, $4)",
            [$order_id, $item["product_id"], $item["quantity"], $item["price"]]);
    }

    // Clear cart
    pg_query_params($conn,
        "DELETE FROM cart_items WHERE user_id = $1", [$user_id]);

    pg_query($conn, "COMMIT");

    echo json_encode([
        "ok"       => true,
        "order_id" => $order_id,
        "total"    => $grandTotal,
        "message"  => "Order placed successfully",
    ]);

} catch (Throwable $e) {
    pg_query($conn, "ROLLBACK");
    file_put_contents(__DIR__ . "/api_error.log",
        date("c") . " api_shop_place_order: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error"]);
} finally {
    ob_end_flush();
}