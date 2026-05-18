<?php
// vendor_add_product_action.php
session_start();
require "../db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? "") !== "vendor") {
  header("Location: ../auth/login.php?error=" . urlencode("Please login as vendor."));
  exit;
}

$vendorId = (int)$_SESSION["user_id"];

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: vendor_add_product.php?error=" . urlencode("Invalid request."));
  exit;
}

// ---------- Required fields ----------
$product_name   = trim($_POST["product_name"] ?? "");
$category_id    = (int)($_POST["category"] ?? 0);
$brand          = trim($_POST["brand"] ?? "");
$price          = (float)($_POST["price"] ?? 0);
$stock_quantity = (int)($_POST["stock_quantity"] ?? -1);
$module         = trim($_POST["module"] ?? "");
$product_type   = trim($_POST["product_type"] ?? "");

// ---------- Auto-calculate tier ----------
// Thresholds: [starter_max, balanced_max]
// Below starter_max  → Starter
// Up to balanced_max → Balanced
// Above             → Premium
function calculate_tier(string $product_type, float $price): string {
  $thresholds = [
    // POS
    "terminal"    => [15000,  25000],
    "printer"     => [ 4000,   6000],
    "drawer"      => [ 2800,   3300],
    "software"    => [ 9000,  14000],
    "kds"         => [13000,  17000],
    "scanner"     => [ 3000,   4000],
    "tablet"      => [14000,  18000],
    // Kitchen
    "oven"        => [12000,  35000],
    "stove"       => [10000,  30000],
    "fryer"       => [ 8000,  20000],
    "grill"       => [ 6000,  18000],
    "microwave"   => [ 3000,   8000],
    "fridge"      => [15000,  40000],
    "freezer"     => [15000,  40000],
    "blender"     => [  700,   3000],
    "mixer"       => [ 4000,  12000],
    "coffee"      => [ 8000,  25000],
    // Furniture
    "dining_set"  => [ 8000,  20000],
    "table"       => [ 2000,   6000],
    "chair"       => [  800,   2500],
    "tv"          => [ 5000,  15000],
    "sofa"        => [ 6000,  18000],
    "bar_stool"   => [  800,   2500],
    "outdoor_furniture" => [ 3000, 10000],
    "reception_desk"    => [ 5000, 15000],
    "shelving"    => [ 2000,   6000],
    "speaker"     => [ 2000,   8000],
    "light"       => [  500,   2000],
    // AC
    "ac"          => [30000,  80000],
    "exhaust_fan" => [ 2000,   6000],
    "air_curtain" => [ 3000,   8000],
  ];
  [$starter_max, $balanced_max] = $thresholds[$product_type] ?? [3000, 8000];
  if ($price < $starter_max)   return "Starter";
  if ($price <= $balanced_max) return "Balanced";
  return "Premium";
}

$tier     = calculate_tier($product_type, $price);
$priority = 1;

// ---------- Optional fields ----------
$business_type_arr = $_POST["business_type"] ?? [];
$business_type = !empty($business_type_arr) ? implode(",", array_map('trim', $business_type_arr)) : null;

// product_group_key — auto-generate if blank
$product_group_key = trim($_POST["product_group_key"] ?? "");
if ($product_group_key === "") {
  $slug_base = strtolower($product_type . " " . $product_name);
  $slug_base = preg_replace('/[^a-z0-9\s]/', '', $slug_base);
  $slug_base = trim($slug_base);
  $slug_base = preg_replace('/\s+/', '_', $slug_base);
  $product_group_key = substr($slug_base, 0, 60);
}
$product_group_key = $product_group_key ?: null;

// ---------- Specs ----------
$specs_raw = trim($_POST["specs"] ?? "");
$specs = null;
if ($specs_raw !== "") {
  json_decode($specs_raw, true);
  if (json_last_error() !== JSON_ERROR_NONE) {
    header("Location: vendor_add_product.php?error=" . urlencode("Specs JSON is invalid. Please try again."));
    exit;
  }
  $specs = $specs_raw;
}

// ---------- Validation ----------
if ($product_name === "" || $category_id <= 0 || $brand === "" ||
    $price <= 0 || $stock_quantity < 0 || $module === "" || $product_type === "") {
  header("Location: vendor_add_product.php?error=" . urlencode("Please fill all required fields."));
  exit;
}

// ---------- Insert ----------
pg_query($conn, "BEGIN");

$insResult = pg_query_params(
  $conn,
  "INSERT INTO products
     (vendor_user_id, product_name, category_id, brand, price, stock_quantity,
      module, tier, product_type, priority, business_type, product_group_key, specs)
   VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13)
   RETURNING id",
  [
    $vendorId, $product_name, $category_id, $brand, $price, $stock_quantity,
    $module, $tier, $product_type, $priority, $business_type, $product_group_key, $specs,
  ]
);

if (!$insResult) {
  pg_query($conn, "ROLLBACK");
  header("Location: vendor_add_product.php?error=" . urlencode("Add failed: " . pg_last_error($conn)));
  exit;
}

$productId = (int)pg_fetch_result($insResult, 0, 0);
if ($productId <= 0) {
  pg_query($conn, "ROLLBACK");
  header("Location: vendor_add_product.php?error=" . urlencode("Failed to create product."));
  exit;
}

// ---------- Image uploads ----------
if (!empty($_FILES["images"]) && is_array($_FILES["images"]["name"])) {
  $count   = min(count($_FILES["images"]["name"]), 8);
  $baseDir = dirname(__DIR__) . "/Vendor/uploads/products/vendor_" . $vendorId . "/product_" . $productId . "/";
  if (!is_dir($baseDir)) mkdir($baseDir, 0777, true);

  for ($i = 0; $i < $count; $i++) {
    if (($_FILES["images"]["error"][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
    $tmp  = $_FILES["images"]["tmp_name"][$i];
    $name = $_FILES["images"]["name"][$i] ?? "image";
    $size = (int)($_FILES["images"]["size"][$i] ?? 0);
    if ($size <= 0) continue;
    $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
    if (!in_array($ext, ["jpg","jpeg","png","webp"], true)) continue;
    $safeFile = "img_" . time() . "_" . $i . "." . $ext;
    if (!move_uploaded_file($tmp, $baseDir . $safeFile)) continue;
    $publicUrl = "Vendor/uploads/products/vendor_" . $vendorId . "/product_" . $productId . "/" . $safeFile;
    pg_query_params($conn, "INSERT INTO product_images (product_id, image_url) VALUES ($1,$2)", [$productId, $publicUrl]);
  }
}

pg_query($conn, "COMMIT");

header("Location: vendor_products.php?success=" . urlencode("Product added successfully!"));
exit;