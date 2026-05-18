<?php
session_start();
require "../db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? "") !== "vendor") {
  header("Location: ../auth/login.php?error=" . urlencode("Please login as vendor."));
  exit;
}

$vendorId = (int)$_SESSION["user_id"];
$productId = (int)($_GET["id"] ?? 0);

if ($productId <= 0) {
  header("Location: vendor_products.php?error=" . urlencode("Invalid product."));
  exit;
}

// Verify the product belongs to the logged-in vendor.
$check = pg_query_params(
  $conn,
  "SELECT id FROM products WHERE id = $1 AND vendor_user_id = $2 LIMIT 1",
  [$productId, $vendorId]
);

// Fallback for older schemas that used vendor_id instead of vendor_user_id.
if (!$check) {
  $check = pg_query_params(
    $conn,
    "SELECT id FROM products WHERE id = $1 AND vendor_id = $2 LIMIT 1",
    [$productId, $vendorId]
  );
}

if (!$check || pg_num_rows($check) === 0) {
  header("Location: vendor_products.php?error=" . urlencode("Product not found or you do not have permission to delete it."));
  exit;
}

pg_query($conn, "BEGIN");

try {
  // Get all uploaded images before deleting database rows.
  $imgQuery = pg_query_params(
    $conn,
    "SELECT image_url FROM product_images WHERE product_id = $1",
    [$productId]
  );

  $images = $imgQuery ? (pg_fetch_all($imgQuery) ?: []) : [];

  // Delete image rows first.
  $deleteImages = pg_query_params(
    $conn,
    "DELETE FROM product_images WHERE product_id = $1",
    [$productId]
  );

  if (!$deleteImages) {
    throw new Exception("Could not delete product images.");
  }

  // Delete product row.
  $deleteProduct = pg_query_params(
    $conn,
    "DELETE FROM products WHERE id = $1",
    [$productId]
  );

  if (!$deleteProduct) {
    throw new Exception("Could not delete product. It may be linked to existing orders.");
  }

  pg_query($conn, "COMMIT");

  // Delete physical image files after DB delete succeeds.
  foreach ($images as $img) {
    $imageUrl = (string)($img["image_url"] ?? "");
    if ($imageUrl === "") {
      continue;
    }

    $filePath = dirname(__DIR__) . "/" . ltrim($imageUrl, "/");

    if (is_file($filePath)) {
      @unlink($filePath);
    }
  }

  header("Location: vendor_products.php?success=" . urlencode("Product deleted successfully."));
  exit;

} catch (Throwable $e) {
  pg_query($conn, "ROLLBACK");
  header("Location: vendor_products.php?error=" . urlencode($e->getMessage()));
  exit;
}
