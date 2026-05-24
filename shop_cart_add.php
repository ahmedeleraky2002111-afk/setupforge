<?php
// shop_cart_add.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "db.php";

header("Content-Type: application/json");

$productId = (int)($_POST["product_id"] ?? 0);
$qty       = max(1, (int)($_POST["qty"] ?? 1));

if ($productId <= 0) {
    echo json_encode(["ok" => false, "error" => "Invalid product."]);
    exit;
}

// Load product from DB
$res = @pg_query_params($conn,
    "SELECT id, product_name, price, stock_quantity, vendor_user_id,
            (SELECT image_url FROM product_images WHERE product_id = p.id ORDER BY id ASC LIMIT 1) AS image_url
     FROM products p WHERE id = \$1 LIMIT 1",
    [$productId]
);

if (!$res || pg_num_rows($res) === 0) {
    echo json_encode(["ok" => false, "error" => "Product not found."]);
    exit;
}

$product = pg_fetch_assoc($res);

if ((int)$product["stock_quantity"] <= 0) {
    echo json_encode(["ok" => false, "error" => "Out of stock."]);
    exit;
}

// Init cart
if (!isset($_SESSION["shop_cart"]) || !is_array($_SESSION["shop_cart"])) {
    $_SESSION["shop_cart"] = [];
}

$key = (string)$productId;

if (isset($_SESSION["shop_cart"][$key])) {
    // Already in cart — increment qty
    $_SESSION["shop_cart"][$key]["qty"] += $qty;
} else {
    $_SESSION["shop_cart"][$key] = [
        "product_id"  => (int)$product["id"],
        "name"        => $product["product_name"],
        "price"       => (float)$product["price"],
        "qty"         => $qty,
        "image_url"   => $product["image_url"] ?: null,
        "vendor_user_id" => $product["vendor_user_id"] ?? null,
    ];
}

// Count total items for badge
$totalItems = 0;
foreach ($_SESSION["shop_cart"] as $item) {
    $totalItems += (int)($item["qty"] ?? 1);
}

echo json_encode([
    "ok"          => true,
    "cart_count"  => $totalItems,
    "product_name"=> $product["product_name"],
]);
exit;