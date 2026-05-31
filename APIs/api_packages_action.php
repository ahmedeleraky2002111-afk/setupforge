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

    $user_id = (int)pg_fetch_assoc($userRes)["id"];

    $input = json_decode(file_get_contents("php://input"), true);
    $action  = $input["action"] ?? "";
    $module  = $input["module"] ?? "";
    $type    = $input["type"] ?? "";
    $qty     = isset($input["qty"]) ? (int)$input["qty"] : null;
    $productId = $input["product_id"] ?? null;

    // Load cart state from DB (stored in businesses.staffing_data under "app_carts")
    $bizRes = pg_query_params($conn,
        "SELECT staffing_data FROM businesses WHERE user_id = $1 LIMIT 1", [$user_id]);

    if (!$bizRes || pg_num_rows($bizRes) === 0) {
        echo json_encode(["ok" => false, "error" => "No business"]);
        exit;
    }

    $staffingData = json_decode(pg_fetch_assoc($bizRes)["staffing_data"] ?? "{}", true) ?? [];
    $appCarts = $staffingData["app_carts"] ?? [];

    if ($action === "update_qty") {
        if (isset($appCarts[$module]["items"])) {
            foreach ($appCarts[$module]["items"] as &$item) {
                if ($item["type"] === $type) {
                    $item["qty"] = max(0, $qty);
                    break;
                }
            }
        }
    }

    if ($action === "replace_product") {
        // Fetch new product from DB
        $pRes = pg_query_params($conn, "
            SELECT p.id, p.product_name, p.product_type, p.price, p.avg_rating,
                   p.brand, p.vendor_user_id, p.product_group_key,
                   u.name AS vendor_name,
                   (SELECT pi.image_url FROM product_images pi
                    WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) AS image_url
            FROM products p LEFT JOIN users u ON u.id = p.vendor_user_id
            WHERE p.id = \$1 LIMIT 1
        ", [(int)$productId]);

        if ($pRes && pg_num_rows($pRes) > 0) {
            $p = pg_fetch_assoc($pRes);
            if (isset($appCarts[$module]["items"])) {
                foreach ($appCarts[$module]["items"] as &$item) {
                    if ($item["type"] === $type) {
                        $item["product_id"] = (string)$p["id"];
                        $item["name"] = $p["product_name"];
                        $item["unit"] = (int)$p["price"];
                        $item["image_url"] = $p["image_url"] ?? null;
                        $item["brand"] = $p["brand"] ?? "";
                        $item["vendor_name"] = $p["vendor_name"] ?? "";
                        break;
                    }
                }
            }
        }
    }

    if ($action === "add_product") {
        $pRes = pg_query_params($conn, "
            SELECT p.id, p.product_name, p.product_type, p.price, p.avg_rating,
                   p.brand, p.vendor_user_id,
                   u.name AS vendor_name,
                   (SELECT pi.image_url FROM product_images pi
                    WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) AS image_url
            FROM products p LEFT JOIN users u ON u.id = p.vendor_user_id
            WHERE p.id = \$1 LIMIT 1
        ", [(int)$productId]);

        if ($pRes && pg_num_rows($pRes) > 0) {
            $p = pg_fetch_assoc($pRes);
            if (!isset($appCarts[$module]["items"])) $appCarts[$module]["items"] = [];
            $appCarts[$module]["items"][] = [
                "type"       => $type,
                "product_id" => (string)$p["id"],
                "name"       => $p["product_name"],
                "unit"       => (int)$p["price"],
                "qty"        => 1,
                "image_url"  => $p["image_url"] ?? null,
                "brand"      => $p["brand"] ?? "",
                "vendor_name"=> $p["vendor_name"] ?? "",
                "avg_rating" => $p["avg_rating"] ? (float)$p["avg_rating"] : null,
                "alternatives"=> [],
            ];
        }
    }

    // Save back
    $staffingData["app_carts"] = $appCarts;
    pg_query_params($conn,
        "UPDATE businesses SET staffing_data = \$1 WHERE user_id = \$2",
        [json_encode($staffingData), $user_id]);

    echo json_encode(["ok" => true]);

} catch (Throwable $e) {
    file_put_contents(__DIR__ . "/api_error.log",
        date("c") . " api_packages_action: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error"]);
} finally {
    ob_end_flush();
}