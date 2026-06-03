<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header("Content-Type: application/json");

try {
    require_once __DIR__ . "/../db.php";
    require_once __DIR__ . "/../config.php";

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
    $paymentMethod    = trim($input["payment_method"] ?? "cash");

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
            customer_user_id, order_type, order_total, payment_status,
            status, delivery_location, order_date
        ) VALUES ($1, 'shop', $2, 'pending', 'pending', $3, NOW())
        RETURNING id
    ", [$user_id, $grandTotal, $deliveryLocation]);

    if (!$orderRes || pg_num_rows($orderRes) === 0) {
        pg_query($conn, "ROLLBACK");
        $pgError = pg_last_error($conn);
        echo json_encode(["ok" => false, "error" => "Failed to create order", "pg_error" => $pgError]);
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

    // ── Cash on Delivery ────────────────────────────────────────────────────
    if ($paymentMethod !== "card") {
        echo json_encode([
            "ok"       => true,
            "order_id" => $order_id,
            "total"    => $grandTotal,
            "message"  => "Order placed successfully",
        ]);
        exit;
    }

    // ── Card: Paymob ────────────────────────────────────────────────────────

    $nameParts = explode(' ', trim($deliveryName), 2);
    $firstName = $nameParts[0];
    $lastName  = $nameParts[1] ?? 'User';

    // Step 1: Auth token
    $authRes = file_get_contents('https://accept.paymob.com/api/auth/tokens', false,
        stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/json',
            'content' => json_encode(['api_key' => PAYMOB_API_KEY]),
        ]]));
    $authData = json_decode($authRes, true);
    $paymobToken = $authData['token'] ?? '';
    if (!$paymobToken) {
        echo json_encode(['ok' => false, 'error' => 'Paymob auth failed']);
        exit;
    }

    // Step 2: Register order
    $merchantOrderId = 'shop_' . $order_id . '_' . time();
    $regRes = file_get_contents('https://accept.paymob.com/api/ecommerce/orders', false,
        stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/json',
            'content' => json_encode([
                'auth_token'        => $paymobToken,
                'delivery_needed'   => false,
                'amount_cents'      => (int)round($grandTotal * 100),
                'currency'          => 'EGP',
                'merchant_order_id' => $merchantOrderId,
                'items'             => [],
            ]),
        ]]));
    $regData = json_decode($regRes, true);
    $paymobOrderId = $regData['id'] ?? '';
    if (!$paymobOrderId) {
        echo json_encode(['ok' => false, 'error' => 'Paymob order registration failed']);
        exit;
    }

    // Step 3: Payment key
    $pkRes = file_get_contents('https://accept.paymob.com/api/acceptance/payment_keys', false,
        stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/json',
            'content' => json_encode([
                'auth_token'     => $paymobToken,
                'amount_cents'   => (int)round($grandTotal * 100),
                'expiration'     => 3600,
                'order_id'       => $paymobOrderId,
                'currency'       => 'EGP',
                'integration_id' => PAYMOB_INTEGRATION_ID,
                'billing_data'   => [
                    'first_name'      => $firstName,
                    'last_name'       => $lastName,
                    'email'           => $user['email'] ?? 'na@na.com',
                    'phone_number'    => $deliveryPhone,
                    'apartment'       => 'NA',
                    'floor'           => 'NA',
                    'street'          => 'NA',
                    'building'        => 'NA',
                    'shipping_method' => 'NA',
                    'postal_code'     => 'NA',
                    'city'            => 'NA',
                    'country'         => 'EG',
                    'state'           => 'NA',
                ],
            ]),
        ]]));
    $pkData = json_decode($pkRes, true);
    $paymentKey = $pkData['token'] ?? '';
    if (!$paymentKey) {
        echo json_encode(['ok' => false, 'error' => 'Paymob payment key failed']);
        exit;
    }

    $iframeUrl = 'https://accept.paymob.com/api/acceptance/iframes/' . PAYMOB_IFRAME_ID . '?payment_token=' . $paymentKey;

    echo json_encode([
        'ok'         => true,
        'order_id'   => $order_id,
        'iframe_url' => $iframeUrl,
        'total'      => $grandTotal,
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