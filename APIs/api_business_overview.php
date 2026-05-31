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
        "SELECT id, name FROM users WHERE api_token = $1 AND user_type = 'business' LIMIT 1",
        [$token]);

    if (!$userRes || pg_num_rows($userRes) === 0) {
        echo json_encode(["ok" => false, "error" => "Unauthorized"]);
        exit;
    }

    $business_id = (int)pg_fetch_assoc($userRes)["id"];

    // Business info
    $bizRes = pg_query_params($conn, "
        SELECT b.business_name, b.business_type, b.budget_egp, b.location_text,
               u.name, u.email, u.phone, u.city, u.country
        FROM businesses b
        JOIN users u ON b.user_id = u.id
        WHERE b.user_id = $1 LIMIT 1
    ", [$business_id]);

    if (!$bizRes || pg_num_rows($bizRes) === 0) {
        echo json_encode(["ok" => false, "error" => "Business not found"]);
        exit;
    }
    $business = pg_fetch_assoc($bizRes);

    // Latest setup order
    $orderRes = pg_query_params($conn,
        "SELECT * FROM orders
         WHERE business_user_id = $1 AND order_type = 'setup'
         ORDER BY id DESC LIMIT 1",
        [$business_id]);

    $order = null;
    $order_id = 0;
    if ($orderRes && pg_num_rows($orderRes) > 0) {
        $order    = pg_fetch_assoc($orderRes);
        $order_id = (int)$order["id"];
    }

    // Products
    $products = [];
    if ($order_id > 0) {
        $prRes = pg_query_params($conn, "
            SELECT oi.quantity, oi.unit_price,
                   p.id AS product_id, p.product_name, p.brand,
                   p.module, p.vendor_user_id,
                   u.name AS vendor_name
            FROM order_items oi
            JOIN products p ON oi.product_id = p.id
            LEFT JOIN users u ON p.vendor_user_id = u.id
            WHERE oi.order_id = $1
            ORDER BY p.module, p.product_name
        ", [$order_id]);
        if ($prRes) while ($r = pg_fetch_assoc($prRes)) $products[] = $r;

        // Product images
        if (!empty($products)) {
            $ids = implode(',', array_map('intval', array_column($products, 'product_id')));
            $imgRes = pg_query($conn, "
                SELECT DISTINCT ON (product_id) product_id, image_url
                FROM product_images WHERE product_id IN ($ids)
                ORDER BY product_id, id
            ");
            $images = [];
            if ($imgRes) while ($r = pg_fetch_assoc($imgRes))
                $images[(int)$r['product_id']] = $r['image_url'];
            foreach ($products as &$p)
                $p['image_url'] = $images[(int)$p['product_id']] ?? null;
            unset($p);
        }

        // Vendor fulfillments
        $vfRes = pg_query_params($conn,
            "SELECT vendor_user_id, status, estimated_delivery_date
             FROM vendor_order_fulfillments WHERE order_id = $1",
            [$order_id]);
        $vendorFulfillments = [];
        if ($vfRes) while ($r = pg_fetch_assoc($vfRes))
            $vendorFulfillments[(int)$r['vendor_user_id']] = $r;

        foreach ($products as &$p) {
            $vf = $vendorFulfillments[(int)$p['vendor_user_id']] ?? null;
            $p['delivery_status'] = $vf ? strtolower($vf['status']) : 'pending';
        }
        unset($p);
    }

    // Group products by module
    $byModule = [];
    foreach ($products as $p) {
        $mod = strtolower($p['module'] ?: 'general');
        $byModule[$mod][] = $p;
    }

    // Labor jobs
    $laborJobs = [];
    $totalLaborPositions = 0;
    $lrRes = pg_query_params($conn, "
        SELECT j.title, j.location,
               COUNT(*) AS total_openings,
               COUNT(*) FILTER (WHERE j.worker_id IS NOT NULL) AS filled_openings
        FROM jobs j
        WHERE j.business_id = $1 AND j.job_type = 'labor'
        GROUP BY j.title, j.location
        ORDER BY j.title ASC
    ", [$business_id]);
    if ($lrRes) while ($r = pg_fetch_assoc($lrRes)) {
        $laborJobs[] = $r;
        $totalLaborPositions += (int)$r['total_openings'];
    }

    // Installation requests
    $installationRequests = [];
    $irRes = pg_query_params($conn, "
        SELECT ir.request_id, ir.services, ir.status, ir.scheduled_date,
               c.company_name, c.starting_from, c.avg_rating
        FROM installation_requests ir
        LEFT JOIN companies c ON ir.company_id = c.company_id
        WHERE ir.user_id = $1
    ", [$business_id]);
    if ($irRes) while ($r = pg_fetch_assoc($irRes)) $installationRequests[] = $r;

    // Progress tracker
    $step2Status = ($order && $order['payment_status'] === 'paid') ? 'done' : 'pending';

    $hasActiveFulfillment = false;
    foreach (($vendorFulfillments ?? []) as $vf)
        if (in_array($vf['status'], ['processing', 'delivered']))
            { $hasActiveFulfillment = true; break; }
    $step3Status = !$order ? 'none' : ($hasActiveFulfillment ? 'done' : 'progress');

    $hasScheduledInstall = false;
    foreach ($installationRequests as $ir)
        if (!empty($ir['scheduled_date'])) { $hasScheduledInstall = true; break; }
    $step4Status = $hasScheduledInstall ? 'done' : 'pending';

    echo json_encode([
        "ok"                    => true,
        "business_name"         => $business['business_name'] ?: $business['name'],
        "business_type"         => $business['business_type'] ?? "",
        "location"              => $business['location_text'] ?: ($business['city'] ?? ""),
        "order_id"              => $order_id,
        "order_total"           => $order ? (float)$order['order_total'] : 0,
        "paid_at"               => $order['paid_at'] ?? null,
        "products_count"        => count($products),
        "total_labor_positions" => $totalLaborPositions,
        "installation_count"    => count($installationRequests),
        "tracker"               => [
            ["label" => "Order Placed",           "status" => "done"],
            ["label" => "Payment Confirmed",      "status" => $step2Status],
            ["label" => "Products in Delivery",   "status" => $step3Status],
            ["label" => "Installation Scheduled", "status" => $step4Status],
        ],
        "products_by_module"    => $byModule,
        "labor_jobs"            => $laborJobs,
        "installation_requests" => $installationRequests,
    ]);

} catch (Throwable $e) {
    file_put_contents(__DIR__ . "/api_error.log",
        date("c") . " api_business_overview: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error"]);
} finally {
    ob_end_flush();
}