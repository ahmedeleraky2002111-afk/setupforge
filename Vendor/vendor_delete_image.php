<?php
session_start();
require "../db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? "") !== "vendor") {
  header("Location: ../auth/login.php");
  exit;
}

$vendorId  = (int)$_SESSION["user_id"];
$imageId   = (int)($_GET["image_id"] ?? 0);
$productId = (int)($_GET["product_id"] ?? 0);

if ($imageId > 0 && $productId > 0) {
  // Verify this image belongs to this vendor's product before deleting
  $check = pg_query_params(
    $conn,
    "SELECT pi.image_url
     FROM product_images pi
     JOIN products p ON p.id = pi.product_id
     WHERE pi.id = $1 AND p.id = $2 AND p.vendor_user_id = $3",
    [$imageId, $productId, $vendorId]
  );

  if ($check && pg_num_rows($check) > 0) {
    $row = pg_fetch_assoc($check);
    $filePath = dirname(__DIR__) . "/" . $row["image_url"];

    if (is_file($filePath)) {
      unlink($filePath);
    }

    pg_query_params($conn, "DELETE FROM product_images WHERE id = $1", [$imageId]);
  }
}

header("Location: vendor_edit_product.php?id=" . $productId);
exit;
