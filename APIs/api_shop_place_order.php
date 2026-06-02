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
        "SELECT id, name, email, phone, city, country, street FROM users WHERE api_token = $1 LIMIT 1",
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

    $paymentMethod = trim($input["payment_method"] ?? "cash");

    if ($paymentMethod === "card") {
        $PAYMOB_API_KEY = "ZXlKaGJHY2lPaUpJVXpVeE1pSXNJblI1Y0NJNklrcFhWQ0o5LmV5SmpiR0Z6Y3lJNklrMWxjbU5vWVc1MElpd2ljSEp2Wm1sc1pWOXdheUk2TVRFMU1UUTVOeXdpYm1GdFpTSTZJbWx1YVhScFlXd2lmUS5lRDd6eVZVb2hNLW02QXU5NDg0NTBJd1lwS0J1QjFFR2U4c3kyc0tpXzAyb0lWWDFRaTdNZDBZZTNwTjB6TkFyZHRsZVk5Qmx3cklSQ2FocjFfM0Fsdw==";
        $PAYMOB_IFRAME_ID = "1030719";
        $PAYMOB_INT_ID = 5609912;

        $authRes = json_decode(file_get_contents("https://accept.paymob.com/api/auth/tokens", false,
            stream_context_create(["http" => ["method" => "POST", "header" => "Content-Type: application/json", "content" => json_encode(["api_key" => $PAYMOB_API_KEY])]])), true);
        $authToken = $authRes["token"] ?? null;
        if (!$authToken) { echo json_encode(["ok" => false, "error" => "Paymob auth failed"]); exit; }

        $pmOrderRes = json_decode(file_get_contents("https://accept.paymob.com/api/ecommerce/orders", false,
            stream_context_create(["http" => ["method" => "POST", "header" => "Content-Type: application/json", "content" => json_encode(["auth_token" => $authToken, "delivery_needed" => false, "amount_cents" => (int)round($grandTotal * 100), "currency" => "EGP", "merchant_order_id" => $order_id, "items" => []])]])), true);
        $pmOrderId = $pmOrderRes["id"] ?? null;
        if (!$pmOrderId) { echo json_encode(["ok" => false, "error" => "Paymob order failed"]); exit; }

        $pmKeyRes = json_decode(file_get_contents("https://accept.paymob.com/api/acceptance/payment_keys", false,
            stream_context_create(["http" => ["method" => "POST", "header" => "Content-Type: application/json", "content" => json_encode(["auth_token" => $authToken, "amount_cents" => (int)round($grandTotal * 100), "expiration" => 3600, "order_id" => $pmOrderId, "currency" => "EGP", "integration_id" => $PAYMOB_INT_ID, "billing_data" => ["first_name" => $deliveryName, "last_name" => ".", "email" => $user["email"] ?? "na@na.com", "phone_number" => $deliveryPhone, "apartment" => "NA", "floor" => "NA", "street" => $deliveryLocation, "building" => "NA", "shipping_method" => "NA", "postal_code" => "NA", "city" => "NA", "country" => "EG", "state" => "NA"]])]])), true);
        $pmKey = $pmKeyRes["token"] ?? null;
        if (!$pmKey) { echo json_encode(["ok" => false, "error" => "Paymob key failed"]); exit; }

        $iframeUrl = "https://accept.paymob.com/api/acceptance/iframes/{$PAYMOB_IFRAME_ID}?payment_token={$pmKey}";
        echo json_encode(["ok" => true, "order_id" => $order_id, "total" => $grandTotal, "iframe_url" => $iframeUrl]);
    } else {
        echo json_encode(["ok" => true, "order_id" => $order_id, "total" => $grandTotal, "message" => "Order placed successfully"]);
    }

} catch (Throwable $e) {
    pg_query($conn, "ROLLBACK");
    file_put_contents(__DIR__ . "/api_error.log",
        date("c") . " api_shop_place_order: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error"]);
} finally {
    ob_end_flush();
}