<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header("Content-Type: application/json");

try {
    require_once __DIR__ . "/../db.php";

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["ok" => false, "error" => "POST only"]);
        exit;
    }

    if (!isset($conn)) {
        echo json_encode(["ok" => false, "error" => "DB connection not available"]);
        exit;
    }

    // ---------- Auth ----------
    $authHeader = $_SERVER["HTTP_AUTHORIZATION"] ?? "";
    if (!$authHeader && function_exists("getallheaders")) {
        $headers = getallheaders();
        $authHeader = $headers["Authorization"] ?? $headers["authorization"] ?? "";
    }

    if (!preg_match('/Bearer\s+(.+)/i', $authHeader, $m)) {
        echo json_encode(["ok" => false, "error" => "Missing bearer token"]);
        exit;
    }

    $token = trim($m[1]);

    $userRes = pg_query_params($conn,
        "SELECT id, name, email, user_type FROM users WHERE api_token = $1 LIMIT 1",
        [$token]
    );

    if (!$userRes || pg_num_rows($userRes) === 0) {
        echo json_encode(["ok" => false, "error" => "Invalid token"]);
        exit;
    }
    $user = pg_fetch_assoc($userRes);

    // ---------- Input ----------
    $raw   = file_get_contents("php://input");
    $json  = json_decode($raw, true);
    $input = is_array($json) ? $json : $_POST;

    $businessName          = trim((string)($input["business_name"]           ?? ""));
    $phone                 = trim((string)($input["phone"]                   ?? ""));
    $address               = trim((string)($input["address"]                 ?? ""));
    $notes                 = trim((string)($input["notes"]                   ?? ""));
    $businessType          = trim((string)($input["business_type"]           ?? ""));
    $restaurantType        = trim((string)($input["restaurant_type"]         ?? ""));
    $indoorTables          = (int)($input["indoor_tables"]                   ?? 0);
    $outdoorTables         = (int)($input["outdoor_tables"]                  ?? 0);
    $areaSqm               = (float)($input["area_sqm"]                     ?? 0);
    $budgetRange           = trim((string)($input["budget_range"]            ?? ""));
    $installationServices  = $input["installation_services"]                 ?? [];
    $staffCounts           = $input["staff_counts"]                          ?? [];
    $preferredDeliveryDate = trim((string)($input["preferred_delivery_date"] ?? ""));
    $paymentMethod         = trim((string)($input["payment_method"]          ?? "cash"));

    $items = $input["items"] ?? null;

    if ($businessName === "") {
        echo json_encode(["ok" => false, "error" => "Business name is required"]);
        exit;
    }
    if ($phone === "") {
        echo json_encode(["ok" => false, "error" => "Phone is required"]);
        exit;
    }
    if ($address === "") {
        echo json_encode(["ok" => false, "error" => "Address is required"]);
        exit;
    }
    if (!is_array($items) || empty($items)) {
        echo json_encode(["ok" => false, "error" => "Order items are required"]);
        exit;
    }

    // ---------- Normalize items ----------
    $normalizedItems = [];
    $orderTotal      = 0.0;

    foreach ($items as $idx => $item) {
        if (!is_array($item)) {
            echo json_encode(["ok" => false, "error" => "Invalid item format at index $idx"]);
            exit;
        }

        $productId = isset($item["product_id"]) && $item["product_id"] !== ""
            ? (int)$item["product_id"]
            : null;

        $name      = trim((string)($item["name"]       ?? ""));
        $module    = trim((string)($item["module"]     ?? ""));
        $qty       = (int)($item["qty"]                ?? 0);
        $unitPrice = (float)($item["price"] ?? $item["unit_price"] ?? 0);

        if ($qty <= 0) continue;

        if ($unitPrice <= 0) {
            echo json_encode(["ok" => false, "error" => "Invalid price for item: " . ($name ?: "#$idx")]);
            exit;
        }

        if ($productId === null) {
            echo json_encode([
                "ok"    => false,
                "error" => "Missing product_id for item: " . ($name ?: "#$idx"),
            ]);
            exit;
        }

        $orderTotal       += $qty * $unitPrice;
        $normalizedItems[] = [
            "product_id" => $productId,
            "name"       => $name,
            "module"     => $module,
            "qty"        => $qty,
            "unit_price" => $unitPrice,
        ];
    }

    if ($orderTotal <= 0 || empty($normalizedItems)) {
        echo json_encode(["ok" => false, "error" => "Your order is empty"]);
        exit;
    }

    // ---------- Build JSON columns ----------
    $installationData = json_encode([
        "services"                => is_array($installationServices) ? $installationServices : [],
        "restaurant_type"         => $restaurantType,
        "indoor_tables"           => $indoorTables,
        "outdoor_tables"          => $outdoorTables,
        "area_sqm"                => $areaSqm,
        "budget_range"            => $budgetRange,
        "address"                 => $address,
        "phone"                   => $phone,
        "notes"                   => $notes,
        "business_name"           => $businessName,
        "business_type"           => $businessType,
        "payment_method"          => $paymentMethod,
        "preferred_delivery_date" => $preferredDeliveryDate,
    ]);

    $laborData = json_encode([
        "staff_counts" => is_array($staffCounts) ? $staffCounts : [],
    ]);

    $userId = (int)$user["id"];

    // ---------- Insert order ----------
    $orderRes = pg_query_params($conn,
        "INSERT INTO orders
             (user_id, total_amount, payment_method, status,
              installation_data, labor_data, created_at)
         VALUES ($1, $2, $3, $4, $5, $6, NOW())
         RETURNING id",
        [
            $userId,
            $orderTotal,
            $paymentMethod,
            "pending",
            $installationData,
            $laborData,
        ]
    );

    if (!$orderRes || pg_num_rows($orderRes) === 0) {
        echo json_encode([
            "ok"     => false,
            "error"  => "Failed to create order",
            "detail" => pg_last_error($conn),
        ]);
        exit;
    }
    $orderId = (int)pg_fetch_assoc($orderRes)["id"];

    // ---------- Insert order_items ----------
    foreach ($normalizedItems as $item) {
        $itemRes = pg_query_params($conn,
            "INSERT INTO order_items (order_id, product_id, quantity, unit_price, module)
             VALUES ($1, $2, $3, $4, $5)",
            [$orderId, $item["product_id"], $item["qty"], $item["unit_price"], $item["module"]]
        );

        if (!$itemRes) {
            pg_query_params($conn, "DELETE FROM order_items WHERE order_id = $1", [$orderId]);
            pg_query_params($conn, "DELETE FROM orders WHERE id = $1", [$orderId]);
            echo json_encode([
                "ok"     => false,
                "error"  => "Failed to insert order item",
                "detail" => pg_last_error($conn),
            ]);
            exit;
        }
    }

    // ---------- installation_requests (non-fatal) ----------
    if (is_array($installationServices)) {
        foreach ($installationServices as $service) {
            $service = trim((string)$service);
            if ($service === "") continue;
            pg_query_params($conn,
                "INSERT INTO installation_requests (order_id, user_id, service_type, status, created_at)
                 VALUES ($1, $2, $3, $4, NOW())",
                [$orderId, $userId, $service, "open"]
            );
        }
    }

    // ---------- jobs (non-fatal) ----------
    if (is_array($staffCounts)) {
        foreach ($staffCounts as $role => $count) {
            $count = (int)$count;
            if ($count <= 0) continue;
            $title       = ucfirst(str_replace("_", " ", $role)) . " — Order #$orderId";
            $description = "Hiring $count " . str_replace("_", " ", $role) . "(s) for restaurant setup. Order #$orderId.";
            pg_query_params($conn,
                "INSERT INTO jobs (order_id, user_id, role, title, description, quantity, status, created_at)
                 VALUES ($1, $2, $3, $4, $5, $6, $7, NOW())",
                [$orderId, $userId, $role, $title, $description, $count, "open"]
            );
        }
    }

    // ---------- Success ----------
    echo json_encode([
        "ok"          => true,
        "message"     => "Order placed successfully",
        "order_id"    => $orderId,
        "order_total" => $orderTotal,
        "user"        => [
            "id"        => $user["id"],
            "name"      => $user["name"],
            "email"     => $user["email"],
            "user_type" => $user["user_type"],
        ],
        "meta" => [
            "business_name" => $businessName,
            "phone"         => $phone,
            "address"       => $address,
            "notes"         => $notes,
            "business_type" => $businessType,
            "budget_range"  => $budgetRange,
            "items_count"   => count($normalizedItems),
        ],
    ]);

} catch (Throwable $e) {
    file_put_contents(
        __DIR__ . "/api_error.log",
        date("c") . " api_place_order: " . $e->getMessage() . "\n",
        FILE_APPEND
    );
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error (check api_error.log)"]);
} finally {
    ob_end_flush();
}