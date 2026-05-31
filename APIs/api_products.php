<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header("Content-Type: application/json");

try {
    require_once __DIR__ . "/../db.php";

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers["Authorization"] ?? $headers["authorization"] ?? "";

    $user_id = null;
    if (str_starts_with($auth, "Bearer ")) {
        $token = trim(substr($auth, 7));
        $userRes = pg_query_params($conn,
            "SELECT id FROM users WHERE api_token = $1 LIMIT 1", [$token]);
        if ($userRes && pg_num_rows($userRes) > 0)
            $user_id = (int)pg_fetch_assoc($userRes)["id"];
    }

    $action = trim($_POST["action"] ?? $_GET["action"] ?? "list");

    // ── CART ACTIONS ──────────────────────────────────────────────────────────
    if ($action === "cart_add") {
        if (!$user_id) { echo json_encode(["ok"=>false,"error"=>"Login required"]); exit; }
        $product_id = (int)($_POST["product_id"] ?? 0);
        $qty = max(1, (int)($_POST["qty"] ?? 1));

        // Check stock
        $stRes = pg_query_params($conn,
            "SELECT stock_quantity FROM products WHERE id = $1 LIMIT 1", [$product_id]);
        if (!$stRes || pg_num_rows($stRes) === 0) {
            echo json_encode(["ok"=>false,"error"=>"Product not found"]); exit;
        }
        $stock = (int)pg_fetch_assoc($stRes)["stock_quantity"];
        if ($stock <= 0) { echo json_encode(["ok"=>false,"error"=>"Out of stock"]); exit; }

        // Upsert cart item
        $existing = pg_query_params($conn,
            "SELECT id, quantity FROM cart_items WHERE user_id=$1 AND product_id=$2 LIMIT 1",
            [$user_id, $product_id]);

        if ($existing && pg_num_rows($existing) > 0) {
            $row = pg_fetch_assoc($existing);
            $newQty = min((int)$row["quantity"] + $qty, $stock);
            pg_query_params($conn,
                "UPDATE cart_items SET quantity=$1 WHERE id=$2",
                [$newQty, $row["id"]]);
        } else {
            pg_query_params($conn,
                "INSERT INTO cart_items (user_id, product_id, quantity) VALUES ($1,$2,$3)",
                [$user_id, $product_id, min($qty, $stock)]);
        }

        $countRes = pg_query_params($conn,
            "SELECT SUM(quantity) AS cnt FROM cart_items WHERE user_id=$1", [$user_id]);
        $cartCount = $countRes ? (int)pg_fetch_assoc($countRes)["cnt"] : 0;

        echo json_encode(["ok"=>true, "cart_count"=>$cartCount]);
        exit;
    }

    if ($action === "cart_remove") {
        if (!$user_id) { echo json_encode(["ok"=>false,"error"=>"Login required"]); exit; }
        $product_id = (int)($_POST["product_id"] ?? 0);
        pg_query_params($conn,
            "DELETE FROM cart_items WHERE user_id=$1 AND product_id=$2",
            [$user_id, $product_id]);
        echo json_encode(["ok"=>true]);
        exit;
    }

    if ($action === "cart_update") {
        if (!$user_id) { echo json_encode(["ok"=>false,"error"=>"Login required"]); exit; }
        $product_id = (int)($_POST["product_id"] ?? 0);
        $qty = (int)($_POST["qty"] ?? 0);
        if ($qty <= 0) {
            pg_query_params($conn,
                "DELETE FROM cart_items WHERE user_id=$1 AND product_id=$2",
                [$user_id, $product_id]);
        } else {
            pg_query_params($conn,
                "UPDATE cart_items SET quantity=$1 WHERE user_id=$2 AND product_id=$3",
                [$qty, $user_id, $product_id]);
        }
        echo json_encode(["ok"=>true]);
        exit;
    }

    if ($action === "cart_list") {
        if (!$user_id) { echo json_encode(["ok"=>false,"error"=>"Login required"]); exit; }
        $cartRes = pg_query_params($conn, "
            SELECT ci.product_id, ci.quantity, p.product_name, p.brand,
                   p.price, p.stock_quantity,
                   img.image_url
            FROM cart_items ci
            JOIN products p ON p.id = ci.product_id
            LEFT JOIN LATERAL (
                SELECT image_url FROM product_images
                WHERE product_id = p.id ORDER BY id ASC LIMIT 1
            ) img ON true
            WHERE ci.user_id = $1
        ", [$user_id]);

        $items = [];
        $total = 0;
        if ($cartRes) {
            while ($r = pg_fetch_assoc($cartRes)) {
                $lineTotal = (float)$r["price"] * (int)$r["quantity"];
                $total += $lineTotal;
                $items[] = [
                    "product_id"   => (int)$r["product_id"],
                    "product_name" => $r["product_name"],
                    "brand"        => $r["brand"] ?? "",
                    "price"        => (float)$r["price"],
                    "quantity"     => (int)$r["quantity"],
                    "stock"        => (int)$r["stock_quantity"],
                    "image_url"    => $r["image_url"] ?? null,
                    "line_total"   => $lineTotal,
                ];
            }
        }
        echo json_encode(["ok"=>true, "items"=>$items, "total"=>$total]);
        exit;
    }

    // ── FILTERS ───────────────────────────────────────────────────────────────
    $selectedCategory = trim($_GET["category"] ?? "");
    $selectedBrand    = trim($_GET["brand"] ?? "");
    $selectedModule   = trim($_GET["module"] ?? "");
    $minPrice         = trim($_GET["min_price"] ?? "");
    $maxPrice         = trim($_GET["max_price"] ?? "");
    $sort             = trim($_GET["sort"] ?? "");
    $search           = trim($_GET["search"] ?? "");

    // Categories
    $catRes = pg_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
    $categories = [];
    if ($catRes) while ($r = pg_fetch_assoc($catRes)) $categories[] = $r;

    // Brands
    $brandRes = pg_query($conn,
        "SELECT DISTINCT brand FROM products WHERE brand IS NOT NULL AND TRIM(brand)<>'' ORDER BY brand ASC");
    $brands = [];
    if ($brandRes) while ($r = pg_fetch_assoc($brandRes)) $brands[] = $r["brand"];

    // Build query
    $params = [];
    $where  = ["1=1"];
    $pi     = 1;

    if ($selectedCategory !== "" && ctype_digit($selectedCategory)) {
        $where[] = "p.category_id = $" . $pi++;
        $params[] = (int)$selectedCategory;
    }
    if ($selectedBrand !== "") {
        $where[] = "p.brand = $" . $pi++;
        $params[] = $selectedBrand;
    }
    if ($selectedModule !== "") {
        $where[] = "p.module = $" . $pi++;
        $params[] = $selectedModule;
    }
    if ($minPrice !== "" && is_numeric($minPrice)) {
        $where[] = "p.price >= $" . $pi++;
        $params[] = (float)$minPrice;
    }
    if ($maxPrice !== "" && is_numeric($maxPrice)) {
        $where[] = "p.price <= $" . $pi++;
        $params[] = (float)$maxPrice;
    }
    if ($search !== "") {
        $where[] = "(p.product_name ILIKE $" . $pi . " OR p.brand ILIKE $" . $pi . ")";
        $params[] = "%" . $search . "%";
        $pi++;
    }

    $orderBy = "p.created_at DESC";
    if ($sort === "price_low")   $orderBy = "p.price ASC";
    elseif ($sort === "price_high") $orderBy = "p.price DESC";
    elseif ($sort === "name_asc")   $orderBy = "p.product_name ASC";
    elseif ($sort === "name_desc")  $orderBy = "p.product_name DESC";
    elseif ($sort === "rating")     $orderBy = "p.avg_rating DESC NULLS LAST";

    $sql = "
        SELECT p.id, p.product_name, p.brand, p.price, p.stock_quantity,
               p.avg_rating, p.module, p.tier, p.category_id,
               c.name AS category_name, img.image_url
        FROM products p
        LEFT JOIN categories c ON c.id = p.category_id
        LEFT JOIN LATERAL (
            SELECT pi.image_url FROM product_images pi
            WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1
        ) img ON true
        WHERE " . implode(" AND ", $where) . "
        ORDER BY $orderBy
    ";

    $result = pg_query_params($conn, $sql, $params);
    $products = [];
    if ($result) {
        while ($r = pg_fetch_assoc($result)) {
            $products[] = [
                "id"            => (int)$r["id"],
                "product_name"  => $r["product_name"],
                "brand"         => $r["brand"] ?? "",
                "price"         => (float)$r["price"],
                "stock"         => (int)$r["stock_quantity"],
                "avg_rating"    => $r["avg_rating"] ? (float)$r["avg_rating"] : null,
                "module"        => $r["module"] ?? "",
                "tier"          => $r["tier"] ?? "",
                "category_name" => $r["category_name"] ?? "",
                "image_url"     => $r["image_url"] ?? null,
            ];
        }
    }

    // Cart product IDs for current user
    $cartProductIds = [];
    if ($user_id) {
        $ciRes = pg_query_params($conn,
            "SELECT product_id FROM cart_items WHERE user_id=$1", [$user_id]);
        if ($ciRes) while ($r = pg_fetch_assoc($ciRes))
            $cartProductIds[] = (int)$r["product_id"];
    }

    // Cart count
    $cartCount = 0;
    if ($user_id) {
        $ccRes = pg_query_params($conn,
            "SELECT SUM(quantity) AS cnt FROM cart_items WHERE user_id=$1", [$user_id]);
        if ($ccRes) $cartCount = (int)(pg_fetch_assoc($ccRes)["cnt"] ?? 0);
    }

    echo json_encode([
        "ok"               => true,
        "products"         => $products,
        "categories"       => $categories,
        "brands"           => $brands,
        "product_count"    => count($products),
        "cart_product_ids" => $cartProductIds,
        "cart_count"       => $cartCount,
    ]);

} catch (Throwable $e) {
    file_put_contents(__DIR__ . "/api_error.log",
        date("c") . " api_products: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error"]);
} finally {
    ob_end_flush();
}