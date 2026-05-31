<?php
require_once __DIR__ . '/../db.php';
require_once __DIR__ . '/../config.php';

header('Content-Type: application/json');
ob_start();

try {
    // Auth
    $headers = getallheaders();
    $auth = $headers['Authorization'] ?? $headers['authorization'] ?? '';
    if (!preg_match('/^Bearer\s+(.+)$/i', $auth, $m)) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Unauthorized']);
        exit;
    }
    $token = trim($m[1]);
    $uRes = pg_query_params($conn, 'SELECT id FROM users WHERE api_token = $1 LIMIT 1', [$token]);
    if (!$uRes || pg_num_rows($uRes) === 0) {
        http_response_code(401);
        echo json_encode(['ok' => false, 'error' => 'Invalid token']);
        exit;
    }
    $userId = (int)pg_fetch_assoc($uRes)['id'];

    // Get business data
    $bRes = pg_query_params($conn,
        'SELECT staffing_data, budget_egp, installation_services FROM businesses WHERE user_id = $1 LIMIT 1',
        [$userId]);
    if (!$bRes || pg_num_rows($bRes) === 0) {
        echo json_encode(['ok' => false, 'error' => 'No business found']);
        exit;
    }
    $biz = pg_fetch_assoc($bRes);
    $staffingData = json_decode($biz['staffing_data'] ?? '{}', true);
    $appCarts = $staffingData['app_carts'] ?? [];

    if (empty($appCarts)) {
        echo json_encode(['ok' => false, 'error' => 'No items in package']);
        exit;
    }

    // Build order items from app_carts
    // app_carts structure: { module: { section: { product_id, quantity, unit_price, ... } } }
    $orderItems = [];
    $orderTotal = 0;

    foreach ($appCarts as $module => $sections) {
        foreach ($sections as $section => $item) {
            if (empty($item['product_id'])) continue;
            $productId = (int)$item['product_id'];
            $qty       = (int)($item['quantity'] ?? 1);
            $price     = (float)($item['unit_price'] ?? 0);
            $orderItems[] = [
                'product_id' => $productId,
                'quantity'   => $qty,
                'unit_price' => $price,
            ];
            $orderTotal += $qty * $price;
        }
    }

    if (empty($orderItems)) {
        echo json_encode(['ok' => false, 'error' => 'No valid items found']);
        exit;
    }

    $serviceFees = round($orderTotal * 0.02, 2);
    $finalTotal  = $orderTotal + $serviceFees;

    // Get user info for Paymob
    $uInfoRes = pg_query_params($conn,
        'SELECT name, email, phone FROM users WHERE id = $1 LIMIT 1', [$userId]);
    $uInfo = pg_fetch_assoc($uInfoRes);
    $nameParts = explode(' ', trim($uInfo['name'] ?? 'Customer'), 2);
    $firstName = $nameParts[0];
    $lastName  = $nameParts[1] ?? 'User';

    // Create pending order
    pg_query($conn, 'BEGIN');

    $oRes = pg_query_params($conn,
        "INSERT INTO orders
            (order_date, status, business_user_id, service_fees, order_total,
             payment_status, order_type, installation_data)
         VALUES (NOW(), 'pending', $1, $2, $3, 'pending', 'setup', $4)
         RETURNING id",
        [
            $userId,
            $serviceFees,
            $finalTotal,
            json_encode(['services' => $biz['installation_services'] ?? []]),
        ]
    );
    if (!$oRes) {
        pg_query($conn, 'ROLLBACK');
        echo json_encode(['ok' => false, 'error' => 'Failed to create order']);
        exit;
    }
    $orderId = (int)pg_fetch_assoc($oRes)['id'];

    // Insert order items
    foreach ($orderItems as $item) {
        pg_query_params($conn,
            'INSERT INTO order_items (order_id, product_id, quantity, unit_price)
             VALUES ($1, $2, $3, $4)',
            [$orderId, $item['product_id'], $item['quantity'], $item['unit_price']]);
    }

    pg_query($conn, 'COMMIT');

    // ── Paymob ─────────────────────────────────────────────────────────────

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
    $merchantOrderId = 'setup_' . $orderId . '_' . time();
    $regRes = file_get_contents('https://accept.paymob.com/api/ecommerce/orders', false,
        stream_context_create(['http' => [
            'method'  => 'POST',
            'header'  => 'Content-Type: application/json',
            'content' => json_encode([
                'auth_token'         => $paymobToken,
                'delivery_needed'    => false,
                'amount_cents'       => (int)round($finalTotal * 100),
                'currency'           => 'EGP',
                'merchant_order_id'  => $merchantOrderId,
                'items'              => [],
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
                'amount_cents'   => (int)round($finalTotal * 100),
                'expiration'     => 3600,
                'order_id'       => $paymobOrderId,
                'currency'       => 'EGP',
                'integration_id' => PAYMOB_INTEGRATION_ID,
                'billing_data'   => [
                    'first_name'    => $firstName,
                    'last_name'     => $lastName,
                    'email'         => $uInfo['email'] ?? 'na@na.com',
                    'phone_number'  => $uInfo['phone'] ?? '+201000000000',
                    'apartment'     => 'NA',
                    'floor'         => 'NA',
                    'street'        => 'NA',
                    'building'      => 'NA',
                    'shipping_method' => 'NA',
                    'postal_code'   => 'NA',
                    'city'          => 'NA',
                    'country'       => 'EG',
                    'state'         => 'NA',
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
        'ok'          => true,
        'order_id'    => $orderId,
        'iframe_url'  => $iframeUrl,
        'total'       => $finalTotal,
    ]);

} catch (Throwable $e) {
    pg_query($conn, 'ROLLBACK');
    http_response_code(500);
    echo json_encode(['ok' => false, 'error' => $e->getMessage()]);
} finally {
    ob_end_flush();
}