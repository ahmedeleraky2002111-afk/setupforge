<?php
// shop_cart_remove.php
if (session_status() === PHP_SESSION_NONE) session_start();

header("Content-Type: application/json");

$productId = (string)((int)($_POST["product_id"] ?? 0));

unset($_SESSION["shop_cart"][$productId]);

$totalItems = 0;
$cartTotal  = 0;
foreach ($_SESSION["shop_cart"] ?? [] as $item) {
    $totalItems += (int)$item["qty"];
    $cartTotal  += (int)$item["qty"] * (float)$item["price"];
}

echo json_encode([
    "ok"         => true,
    "cart_count" => $totalItems,
    "cart_total" => $cartTotal,
]);
exit;