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
        "SELECT id FROM users WHERE api_token = $1 LIMIT 1", [$token]);

    if (!$userRes || pg_num_rows($userRes) === 0) {
        echo json_encode(["ok" => false, "error" => "Unauthorized"]);
        exit;
    }

    $module   = trim($_GET["module"] ?? "");
    $type     = trim($_GET["type"] ?? "");
    $search   = trim($_GET["search"] ?? "");
    $minPrice = isset($_GET["min_price"]) && is_numeric($_GET["min_price"])
        ? (int)$_GET["min_price"] : null;
    $maxPrice = isset($_GET["max_price"]) && is_numeric($_GET["max_price"])
        ? (int)$_GET["max_price"] : null;

    $allowed = ["kitchen","pos","furniture","ac"];
    if (!in_array($module, $allowed, true)) {
        echo json_encode(["ok" => false, "error" => "Invalid module"]);
        exit;
    }

    $params = [$module];
    $where = ["p.module = \$1"];
    $pi = 2;

    if ($type !== "") {
        $where[] = "p.product_type = \$$pi";
        $params[] = $type;
        $pi++;
    }

    if ($search !== "") {
        $where[] = "(p.product_name ILIKE \$$pi OR p.brand ILIKE \$$pi)";
        $params[] = "%$search%";
        $pi++;
    }

    if ($minPrice !== null) {
        $where[] = "p.price >= \$$pi";
        $params[] = $minPrice;
        $pi++;
    }

    if ($maxPrice !== null) {
        $where[] = "p.price <= \$$pi";
        $params[] = $maxPrice;
        $pi++;
    }

    $sql = "
        SELECT p.id, p.product_name, p.product_type, p.price, p.avg_rating,
               p.brand, p.tier, p.stock_quantity,
               u.name AS vendor_name,
               (SELECT pi.image_url FROM product_images pi
                WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) AS image_url
        FROM products p
        LEFT JOIN users u ON u.id = p.vendor_user_id
        WHERE " . implode(" AND ", $where) . "
        ORDER BY p.avg_rating DESC NULLS LAST, p.price ASC
        LIMIT 50
    ";

    $res = pg_query_params($conn, $sql, $params);
    $products = [];
    if ($res) {
        while ($r = pg_fetch_assoc($res)) {
            $products[] = [
                "id"           => (string)$r["id"],
                "name"         => $r["product_name"],
                "product_type" => $r["product_type"] ?? "",
                "price"        => (int)$r["price"],
                "avg_rating"   => $r["avg_rating"] ? (float)$r["avg_rating"] : null,
                "brand"        => $r["brand"] ?? "",
                "tier"         => $r["tier"] ?? "",
                "vendor_name"  => $r["vendor_name"] ?? "",
                "stock"        => (int)($r["stock_quantity"] ?? 0),
                "image_url"    => $r["image_url"] ?? null,
            ];
        }
    }

    echo json_encode(["ok" => true, "products" => $products]);

} catch (Throwable $e) {
    file_put_contents(__DIR__ . "/api_error.log",
        date("c") . " api_packages_search: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error"]);
} finally {
    ob_end_flush();
}