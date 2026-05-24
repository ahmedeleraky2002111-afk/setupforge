<?php
// shop_cart_update.php
if (session_status() === PHP_SESSION_NONE) session_start();

header("Content-Type: application/json");

$productId = (string)((int)($_POST["product_id"] ?? 0));
$qty       = (int)($_POST["qty"] ?? 0);

if (!isset($_SESSION["shop_cart"][$productId])) {
    echo json_encode(["ok" => false, "error" => "Item not in cart."]);
    exit;
}

if ($qty <= 0) {
    unset($_SESSION["shop_cart"][$productId]);
} else {
    $_SESSION["shop_cart"][$productId]["qty"] = $qty;
}

$totalItems = 0;
$cartTotal  = 0;
foreach ($_SESSION["shop_cart"] as $item) {
    $totalItems += (int)$item["qty"];
    $cartTotal  += (int)$item["qty"] * (float)$item["price"];
}

echo json_encode([
    "ok"         => true,
    "cart_count" => $totalItems,
    "cart_total" => $cartTotal,
    "item_total" => isset($_SESSION["shop_cart"][$productId])
        ? $_SESSION["shop_cart"][$productId]["qty"] * $_SESSION["shop_cart"][$productId]["price"]
        : 0,
]);
exit;