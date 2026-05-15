<?php
session_start();

if (!isset($_SESSION["user_id"])) {
  header("Location: auth/signup.php?next=" . urlencode("packages.php"));
  exit;
}

require_once "db.php";
function egp($n){ return number_format((int)$n) . " EGP"; }

$w = $_SESSION["wizard"] ?? [];

$business     = $w["business_type"] ?? "";
$size         = trim((string)($w["size"] ?? ""));
$indoorSeats  = (int)($w["indoor_seats"]  ?? 0);
$outdoorSeats = (int)($w["outdoor_seats"] ?? 0);
$modules  = $w["modules"] ?? [];
$budget   = (int)($w["budget"] ?? 0);
$restaurantType = $w["restaurant_type"] ?? "standard_dining";
$areaSqm = (int)($w["area_sqm"] ?? 50);
// normalize size
$sizeNorm = ucfirst(strtolower($size));
if (in_array($sizeNorm, ["Small","Medium","Large"], true)) $size = $sizeNorm;

if (!$business || $indoorSeats < 1 || $budget <= 0) {
  header("Location: setup.php?step=1");
  exit;
}

/* ---------------- Labels + weights ---------------- */
$labels = [
  "kitchen"     => "Kitchen / Equipment",
  "furniture"   => "Dining Area",
  "pos"         => "POS & Operations",
  "electronics" => "Electronic Devices"
];

function get_module_weights($restaurantType, $modules) {
  $allWeights = [
    "fast_food" => [
      "kitchen"     => 6,
      "pos"         => 3,
      "furniture"   => 2,
      "electronics" => 1,
      "ambience"    => 0
    ],
    "standard_dining" => [
      "kitchen"     => 5,
      "furniture"   => 3,
      "pos"         => 2,
      "electronics" => 2,
      "ambience"    => 1
    ],
    "premium_dining" => [
      "kitchen"     => 4,
      "furniture"   => 5,
      "pos"         => 2,
      "electronics" => 2,
      "ambience"    => 2
    ],
    "cloud_kitchen" => [
      "kitchen"     => 8,
      "pos"         => 4,
      "furniture"   => 0,
      "electronics" => 0,
      "ambience"    => 0
    ]
  ];

  $weights = $allWeights[$restaurantType] ?? $allWeights["standard_dining"];

  $result = [];
  foreach ($modules as $m) {
    if (isset($weights[$m]) && $weights[$m] > 0) {
      $result[$m] = $weights[$m];
    }
  }
  return $result;
}

/* ---------------- Allocate budget across selected modules ---------------- */
$selectedWeights = get_module_weights($restaurantType, $modules);
$totalW = array_sum($selectedWeights);

$alloc = [];
$sum = 0;
$keys = array_keys($selectedWeights);
$lastKey = end($keys);

foreach($selectedWeights as $m => $wgt){
  $amount = (int)round($budget * ($wgt / $totalW));
  if($m === $lastKey) $amount = $budget - $sum;
  $alloc[$m] = $amount;
  $sum += $amount;
}

function derive_tier($moduleAlloc, $totalBudget) {
  if ($totalBudget <= 0) return "Balanced";
  $ratio = $moduleAlloc / $totalBudget;
  if ($ratio >= 0.35) return "Premium";
  if ($ratio >= 0.20) return "Balanced";
  return "Starter";
}

$posTier         = derive_tier($alloc["pos"]         ?? 0, $budget);
$kitchenTier     = derive_tier($alloc["kitchen"]     ?? 0, $budget);
$furnitureTier   = derive_tier($alloc["furniture"]   ?? 0, $budget);
$electronicsTier = derive_tier($alloc["electronics"] ?? 0, $budget);
$infraTier       = "Balanced";

$kitchenCap = $alloc["kitchen"] ?? 0;
$posCap     = $alloc["pos"] ?? 0;
$furnitureCap   = $alloc["furniture"] ?? 0;
$infraCap       = $alloc["infra"] ?? 0;
$electronicsCap = $alloc["electronics"] ?? 0;

/* ---------------- Fake catalogs (fallback) ---------------- */
$POS_CATALOG = [
  "terminal" => [
    ["id"=>"t1","name"=>"Sunmi T2 Lite Terminal","price"=>12000],
    ["id"=>"t2","name"=>"Partner Tech POS Touch Terminal","price"=>17000],
    ["id"=>"t3","name"=>"HP Engage One Pro Terminal","price"=>23000],
  ],
  "printer" => [
    ["id"=>"p1","name"=>"XPrinter XP-Q200 Receipt Printer","price"=>3000],
    ["id"=>"p2","name"=>"Bixolon SRP-350 Receipt Printer","price"=>4500],
    ["id"=>"p3","name"=>"Epson TM-T88VI Receipt Printer","price"=>6500],
  ],
  "drawer" => [
    ["id"=>"d1","name"=>"Generic Cash Drawer 41cm","price"=>2500],
    ["id"=>"d2","name"=>"POSIFLEX Cash Drawer","price"=>3000],
    ["id"=>"d3","name"=>"APG Heavy-duty Cash Drawer","price"=>3500],
  ],
  "software" => [
    ["id"=>"s1","name"=>"Basic POS License (1 year)","price"=>8000],
    ["id"=>"s2","name"=>"POS + Inventory (1 year)","price"=>12000],
    ["id"=>"s3","name"=>"POS Suite + Reporting (1 year)","price"=>18000],
  ],
  "kds" => [
    ["id"=>"k2","name"=>"Kitchen Display Screen 15\"","price"=>14000],
    ["id"=>"k3","name"=>"Kitchen Display Screen 21\"","price"=>18000],
  ],
  "scanner" => [
    ["id"=>"b2","name"=>"1D Barcode Scanner","price"=>3500],
    ["id"=>"b3","name"=>"2D QR/Barcode Scanner","price"=>4500],
  ],
  "tablet" => [
    ["id"=>"tb3","name"=>"Ordering Tablet 10\"","price"=>16000],
  ],
];

$KITCHEN_CATALOG_ACTIVE = [
  "oven"=>[], "fryer"=>[], "grill"=>[], "microwave"=>[],
  "fridge"=>[], "freezer"=>[], "blender"=>[], "mixer"=>[], "coffee"=>[], "stove"=>[]
];

$POS_CATALOG_ACTIVE = $POS_CATALOG;
$POS_DB_OK = false;
$KITCHEN_DB_OK = false;
$FURNITURE_CATALOG_ACTIVE = [
  "dining_set" => [],
  "table"      => [],
  "chair"      => [],
  "tv"         => []
];

$INFRA_CATALOG_ACTIVE = [
  "ac"      => [],
  "router"  => [],
  "switch"  => [],
  "cable"   => [],
  "ups"     => [],
  "panel"   => []
];

$ELECTRONICS_CATALOG_ACTIVE = [
  "tv"      => [],
  "tablet"  => [],
  "laptop"  => [],
  "monitor" => [],
  "speaker" => [],
  "camera"  => []
];

$FURNITURE_DB_OK = false;
$INFRA_DB_OK = false;
$ELECTRONICS_DB_OK = false;


function guess_kitchen_type($name){
  $n = strtolower((string)$name);
  if (strpos($n,"oven") !== false) return "oven";
  if (strpos($n,"fryer") !== false) return "fryer";
  if (strpos($n,"grill") !== false) return "grill";
  if (strpos($n,"microwave") !== false) return "microwave";
  if (strpos($n,"fridge") !== false || strpos($n,"refrigerator") !== false) return "fridge";
  if (strpos($n,"freezer") !== false) return "freezer";
  if (strpos($n,"blender") !== false) return "blender";
  if (strpos($n,"mixer") !== false) return "mixer";
  if (strpos($n,"coffee") !== false || strpos($n,"espresso") !== false) return "coffee";
  return null;
}

function guess_pos_type($name){
  $n = strtolower((string)$name);
  if (strpos($n, "terminal") !== false) return "terminal";
  if (strpos($n, "printer") !== false) return "printer";
  if (strpos($n, "drawer") !== false) return "drawer";
  if (strpos($n, "license") !== false || strpos($n, "software") !== false) return "software";
  if (strpos($n, "scanner") !== false) return "scanner";
  if (strpos($n, "display") !== false) return "kds";
  if (strpos($n, "tablet") !== false) return "tablet";
  return null;
}

function guess_furniture_type($name){
  $n = strtolower((string)$name);

  if (
    strpos($n, "tv") !== false ||
    strpos($n, "television") !== false ||
    strpos($n, "screen") !== false ||
    strpos($n, "display") !== false
  ) {
    return "tv";
  }

  if (strpos($n, "chair") !== false) return "chair";
  if (strpos($n, "table") !== false) return "table";

  return null;
}

function guess_infra_type($name){
  $n = strtolower((string)$name);

  if (strpos($n,"ac") !== false || strpos($n,"air conditioner") !== false || strpos($n,"split") !== false) return "ac";
  if (strpos($n,"router") !== false) return "router";
  if (strpos($n,"switch") !== false) return "switch";
  if (strpos($n,"cable") !== false || strpos($n,"wire") !== false) return "cable";
  if (strpos($n,"ups") !== false) return "ups";
  if (strpos($n,"panel") !== false) return "panel";

  return null;
}


function guess_electronics_type($name){
  $n = strtolower((string)$name);

  if (strpos($n,"tv") !== false || strpos($n,"television") !== false || strpos($n,"display") !== false) return "tv";
  if (strpos($n,"tablet") !== false) return "tablet";
  if (strpos($n,"laptop") !== false) return "laptop";
  if (strpos($n,"monitor") !== false || strpos($n,"screen") !== false) return "monitor";
  if (strpos($n,"speaker") !== false || strpos($n,"sound") !== false) return "speaker";
  if (strpos($n,"camera") !== false || strpos($n,"cctv") !== false) return "camera";

  return null;
}




/* ---------------- DB load POS ---------------- */
if (isset($conn) && $conn) {
  $sqlPosCatalog = "
  SELECT
  p.id,
    p.product_name,
    p.product_type,
    p.price,
    p.priority,
    p.tier,
    p.brand,
    p.vendor_user_id,
    p.product_group_key,
    p.created_at,
    u.name AS vendor_name,
    c.name AS category_name,
    (
      SELECT pi.image_url
      FROM product_images pi
      WHERE pi.product_id = p.id
      ORDER BY pi.id ASC
      LIMIT 1
    ) AS image_url
  FROM products p
  LEFT JOIN users u ON u.id = p.vendor_user_id
  LEFT JOIN categories c ON c.id = p.category_id
  WHERE p.module = 'pos'
    AND LOWER(p.tier) = LOWER($1)
  ORDER BY p.priority ASC, p.price ASC;
";
  $res = @pg_query_params($conn, $sqlPosCatalog, [$posTier]);
  if ($res) {
    $POS_CATALOG_DB = [
      "terminal"=>[], "printer"=>[], "drawer"=>[], "software"=>[],
      "kds"=>[], "scanner"=>[], "tablet"=>[],
    ];

    while ($row = pg_fetch_assoc($res)) {
      $type = trim((string)($row["product_type"] ?? ""));
      if (!$type) continue;

      $POS_CATALOG_DB[$type][] = [
        "id"            => (string)$row["id"],
        "name"          => $row["product_name"],
        "brand"         => $row["brand"] ?: null,
        "tier"          => $row["tier"] ?: null,
        "vendor_name"   => $row["vendor_name"] ?: null,
        "category_name" => $row["category_name"] ?: null,
        "price"         => (int)$row["price"],
        "image_url"     => $row["image_url"] ?: null,
        "vendor_user_id"    => $row["vendor_user_id"] ?? null,
        "product_group_key" => $row["product_group_key"] ?? null,
        "created_at"        => $row["created_at"] ?? null,
      ];
    }

    $cnt = 0;
    foreach ($POS_CATALOG_DB as $arr) $cnt += count($arr);
    if ($cnt > 0) {
      $POS_CATALOG_ACTIVE = $POS_CATALOG_DB;
      $POS_DB_OK = true;
    }
  }
}

/* ---------------- DB load Kitchen ---------------- */
if (isset($conn) && $conn) {
  $sqlKitchenCatalog = "
  SELECT
    p.id,
    p.product_name,
    p.product_type,
    p.price,
    p.priority,
    p.tier,
    p.brand,
    p.vendor_user_id,
    p.product_group_key,
    p.created_at,
    u.name AS vendor_name,
    c.name AS category_name,
    (
      SELECT pi.image_url
      FROM product_images pi
      WHERE pi.product_id = p.id
      ORDER BY pi.id ASC
      LIMIT 1
    ) AS image_url
  FROM products p
  LEFT JOIN users u ON u.id = p.vendor_user_id
  LEFT JOIN categories c ON c.id = p.category_id
  WHERE p.module = 'kitchen'
    AND LOWER(p.tier) = LOWER($1)
  ORDER BY p.priority ASC, p.price ASC;
";
  $resK = @pg_query_params($conn, $sqlKitchenCatalog, [$kitchenTier]);
  if ($resK) {
    $tmp = $KITCHEN_CATALOG_ACTIVE;
    $count = 0;

    while ($row = pg_fetch_assoc($resK)) {
      $type = trim((string)($row["product_type"] ?? ""));
      if (!$type) continue;

      $tmp[$type][] = [
  "id"            => (string)$row["id"],
  "name"          => $row["product_name"],
  "brand"         => $row["brand"] ?: null,
  "tier"          => $row["tier"] ?: null,
  "vendor_name"   => $row["vendor_name"] ?: null,
  "category_name" => $row["category_name"] ?: null,
  "price"         => (int)$row["price"],
  "image_url"     => $row["image_url"] ?: null,
  "vendor_user_id"    => $row["vendor_user_id"] ?? null,
  "product_group_key" => $row["product_group_key"] ?? null,
  "created_at"        => $row["created_at"] ?? null,

  // 🔥 ADD THESE TWO
        "stock_quantity" => isset($row["stock_quantity"]) ? (int)$row["stock_quantity"] : 0, 
         "specs" => !empty($row["specs"]) ? json_decode($row["specs"], true) : [],
];
      $count++;
    }

    if ($count > 0) {
      $KITCHEN_CATALOG_ACTIVE = $tmp;
      $KITCHEN_DB_OK = true;
    }
  }
}


/* ---------------- DB load Furniture ---------------- */
if (isset($conn) && $conn) {
  $sqlFurnitureCatalog = "
SELECT
  p.id,
  p.product_name,
  p.product_type,
  p.price,
  p.priority,
  p.tier,
  p.brand,
  p.stock_quantity,
  p.specs,
  p.category_id,
  p.vendor_user_id,
  p.product_group_key,
  p.created_at,
  u.name AS vendor_name,
  c.name AS category_name,
  (
    SELECT pi.image_url
    FROM product_images pi
    WHERE pi.product_id = p.id
    ORDER BY pi.id ASC
    LIMIT 1
  ) AS image_url
FROM products p
LEFT JOIN users u ON u.id = p.vendor_user_id
LEFT JOIN categories c ON c.id = p.category_id
WHERE p.module = 'furniture'
  AND LOWER(p.tier) = LOWER($1)
ORDER BY p.priority ASC, p.price ASC;
";

  $resF = @pg_query_params($conn, $sqlFurnitureCatalog, [$furnitureTier]);
  if ($resF) {
    $tmp = $FURNITURE_CATALOG_ACTIVE;
    $count = 0;

    while ($row = pg_fetch_assoc($resF)) {
      $type = trim((string)($row["product_type"] ?? ""));
      if (!$type) continue;

$tmp[$type][] = [
  "id"                => (string)$row["id"],
  "name"              => $row["product_name"],
  "brand"             => $row["brand"] ?: null,
  "tier"              => $row["tier"] ?: null,
  "vendor_name"       => $row["vendor_name"] ?: null,
  "category_name"     => $row["category_name"] ?: null,
  "category_id"       => $row["category_id"] ?? null,
  "price"             => (int)$row["price"],
  "image_url"         => $row["image_url"] ?: null,
  "vendor_user_id"    => $row["vendor_user_id"] ?? null,
  "product_group_key" => $row["product_group_key"] ?? null,
  "created_at"        => $row["created_at"] ?? null,
  "stock_quantity"    => isset($row["stock_quantity"]) ? (int)$row["stock_quantity"] : 0,
  "specs"             => !empty($row["specs"]) ? json_decode($row["specs"], true) : [],
];
      $count++;
    }

    if ($count > 0) {
      $FURNITURE_CATALOG_ACTIVE = $tmp;
      $FURNITURE_DB_OK = true;
    }
  }
}


/* =========================
   ✅ FILTERS (GET) — ONLY FOR ALTERNATIVES LISTS
   ========================= */

function get_get_str($k){
  $v = $_GET[$k] ?? "";
  $v = trim((string)$v);
  return $v;
}
function get_get_int($k){
  if (!isset($_GET[$k])) return null;
  $raw = trim((string)$_GET[$k]);
  if ($raw === "") return null;
  if (!is_numeric($raw)) return null;
  $n = (int)$raw;
  if ($n < 0) $n = 0;
  return $n;
}

$FILTER = [
  "brand" => get_get_str("brand"),
  "vendor" => get_get_str("vendor"), // vendor_user_id as string
  "min_price" => get_get_int("min_price"),
  "max_price" => get_get_int("max_price"),
  "sort" => get_get_str("sort"), // price_asc, price_desc, newest
  "panel" => get_get_str("panel"), // e.g. pos-terminal OR kitchen-oven OR sellers
  "type" => get_get_str("type"),   // e.g. terminal/oven used to persist panel open
  "group" => get_get_str("group"), // product_group_key used for sellers panel
];

function unique_brands_from_alts($alts){
  $set = [];
  foreach($alts as $a){
    $b = trim((string)($a["brand"] ?? ""));
    if ($b !== "") $set[$b] = true;
  }
  $out = array_keys($set);
  sort($out, SORT_NATURAL|SORT_FLAG_CASE);
  return $out;
}
function unique_vendors_from_alts($alts){
  $map = [];
  foreach($alts as $a){
    $vid = (string)($a["vendor_user_id"] ?? "");
    if ($vid === "") continue;
    $name = trim((string)($a["vendor_name"] ?? ""));
    if ($name === "") $name = "Vendor";
    $map[$vid] = $name;
  }
  asort($map, SORT_NATURAL|SORT_FLAG_CASE);
  return $map;
}
function pool_minmax_price($alts){
  $min = null; $max = null;
  foreach($alts as $a){
    if (!isset($a["price"])) continue;
    $p = (int)$a["price"];
    if ($min === null || $p < $min) $min = $p;
    if ($max === null || $p > $max) $max = $p;
  }
  if ($min === null) $min = 0;
  if ($max === null) $max = 0;
  return [$min,$max];
}

function filter_sort_alts($alts, $filter){
  $brand = trim((string)($filter["brand"] ?? ""));
  $vendor = trim((string)($filter["vendor"] ?? ""));
  $minP = $filter["min_price"];
  $maxP = $filter["max_price"];
  $sort = $filter["sort"] ?? "";

  $out = [];
  foreach($alts as $a){
    if ($brand !== "" && strcasecmp((string)($a["brand"] ?? ""), $brand) !== 0) continue;
    if ($vendor !== "" && (string)($a["vendor_user_id"] ?? "") !== $vendor) continue;
    $price = (int)($a["price"] ?? 0);
    if ($minP !== null && $price < (int)$minP) continue;
    if ($maxP !== null && $price > (int)$maxP) continue;
    $out[] = $a;
  }

  if ($sort === "price_desc") {
    usort($out, fn($x,$y)=> ((int)$y["price"] <=> (int)$x["price"]));
  } elseif ($sort === "newest") {
    usort($out, function($x,$y){
      $xa = $x["created_at"] ?? null;
      $ya = $y["created_at"] ?? null;
      if ($xa && $ya) return strcmp((string)$ya, (string)$xa);
      return ((int)($y["id"] ?? 0) <=> (int)($x["id"] ?? 0));
    });
  } else {
    usort($out, fn($x,$y)=> ((int)$x["price"] <=> (int)$y["price"]));
  }

  return $out;
}

function build_base_qs($overrides = []){
  $keep = [
    "module" => $_GET["module"] ?? "",
    "brand" => $_GET["brand"] ?? "",
    "vendor" => $_GET["vendor"] ?? "",
    "min_price" => $_GET["min_price"] ?? "",
    "max_price" => $_GET["max_price"] ?? "",
    "sort" => $_GET["sort"] ?? "",
    "panel" => $_GET["panel"] ?? "",
    "type" => $_GET["type"] ?? "",
    "group" => $_GET["group"] ?? "",
    "anchor" => $_GET["anchor"] ?? "",
    "open_replace" => $_GET["open_replace"] ?? "",
    "open_sellers" => $_GET["open_sellers"] ?? "",
  ];
  foreach($overrides as $k=>$v){
    $keep[$k] = $v;
  }
  foreach($keep as $k=>$v){
    if ($v === "" || $v === null) unset($keep[$k]);
  }
  return http_build_query($keep);
}

/* ---------------- Helpers ---------------- */
function load_vendor_offers_for_same_product($conn, $productName, $brand, $module, $tier, $categoryId, $filter = null){
  $brand = (string)$brand;
  $productName = (string)$productName;

  $sql = "
    SELECT
      p.id,
      p.product_name,
      p.brand,
      p.price,
      p.avg_rating,
      p.stock_quantity,
      p.vendor_user_id,
      p.created_at,
      u.name AS vendor_name
    FROM products p
    LEFT JOIN users u ON u.id = p.vendor_user_id
    WHERE p.product_name = $1
      AND COALESCE(p.brand,'') = COALESCE($2,'')
      AND p.module = $3
      AND LOWER(p.tier) = LOWER($4)
      AND COALESCE(p.category_id,0) = COALESCE($5,0)
  ";

  $params = [$productName, $brand, $module, $tier, $categoryId];

  if (is_array($filter)) {
    $fVendor = trim((string)($filter["vendor"] ?? ""));
    $minP = $filter["min_price"] ?? null;
    $maxP = $filter["max_price"] ?? null;

    if ($fVendor !== "") {
      $sql .= " AND p.vendor_user_id = $" . (count($params) + 1) . " ";
      $params[] = (int)$fVendor;
    }
    if ($minP !== null) {
      $sql .= " AND p.price >= $" . (count($params) + 1) . " ";
      $params[] = (int)$minP;
    }
    if ($maxP !== null) {
      $sql .= " AND p.price <= $" . (count($params) + 1) . " ";
      $params[] = (int)$maxP;
    }

    $sort = $filter["sort"] ?? "";
    if ($sort === "price_desc") {
      $sql .= " ORDER BY p.price DESC, p.avg_rating DESC NULLS LAST, p.stock_quantity DESC NULLS LAST ";
    } elseif ($sort === "newest") {
      $sql .= " ORDER BY p.created_at DESC NULLS LAST, p.id DESC ";
    } else {
      $sql .= " ORDER BY p.price ASC, p.avg_rating DESC NULLS LAST, p.stock_quantity DESC NULLS LAST ";
    }
  } else {
    $sql .= "
      ORDER BY
        p.price ASC,
        p.avg_rating DESC NULLS LAST,
        p.stock_quantity DESC NULLS LAST;
    ";
  }

  $res = @pg_query_params($conn, $sql, $params);
  if(!$res) return [];

  $out = [];
  while($r = pg_fetch_assoc($res)){
    $out[] = [
      "id" => (string)$r["id"],
      "price" => (int)$r["price"],
      "vendor_name" => $r["vendor_name"] ?: "Vendor",
      "vendor_user_id" => $r["vendor_user_id"] ?? null,
      "avg_rating" => $r["avg_rating"] !== null ? (float)$r["avg_rating"] : null,
      "stock_quantity" => $r["stock_quantity"] !== null ? (int)$r["stock_quantity"] : null,
      "created_at" => $r["created_at"] ?? null,
    ];
  }
  return $out;
}

function find_by_id($catalog, $type, $id){
  if (!isset($catalog[$type])) return null;
  foreach($catalog[$type] as $p){
    if ((string)$p["id"] === (string)$id) return $p;
  }
  return null;
}

function pick_best_under_unit_budget($catalog, $type, $unitBudget){
  if (!isset($catalog[$type]) || empty($catalog[$type])) return null;
  $best = null;
  foreach($catalog[$type] as $p){
    if ((int)$p["price"] <= (int)$unitBudget) $best = $p;
  }
  if ($best) return $best;
  return $catalog[$type][0];
}

function pos_quantities_by_size($indoorSeats){
  $terminals = max(1, (int)ceil($indoorSeats / 20));
  return [
    "terminals" => $terminals,
    "printers"  => $terminals,
    "drawers"   => $terminals
  ];
}

function kitchen_quantities_by_size($indoorSeats, $outdoorSeats){
  $total = $indoorSeats + $outdoorSeats;
  return [
    "oven"      => 1,
    "fryer"     => 1,
    "grill"     => $total >= 30 ? 1 : 0,
    "microwave" => 1,
    "fridge"    => $total >= 70 ? 2 : 1,
    "freezer"   => 1,
    "blender"   => 1,
    "mixer"     => $total >= 30 ? 1 : 0,
    "coffee"    => 1
  ];
}

function furniture_quantities_by_size($indoorSeats, $restaurantType = "standard_dining", $outdoorSeats = 0){
  $restaurantType = strtolower(trim((string)$restaurantType));
  $tables = (int)ceil($indoorSeats / 4);
  $chairs = $indoorSeats;
  if ($restaurantType === "cloud_kitchen") {
    $tvs = 0;
  } else {
    $indoorTvs  = max(1, (int)ceil($indoorSeats / 20));
    $outdoorTvs = ($outdoorSeats > 0) ? (int)ceil($outdoorSeats / 30) : 0;
    $tvs        = $indoorTvs + $outdoorTvs;
  }
  return [
    "table" => $tables,
    "chair" => $chairs,
    "tv"    => $tvs,
  ];
}
function ac_units_from_area($areaSqm){
  return max(1, (int)ceil($areaSqm / 40));
}

function ac_tonnage_from_area_per_unit($areaSqm, $acUnits){
  $areaPerUnit = $areaSqm / max(1, $acUnits);
  if ($areaPerUnit <= 20) return ["tonnage" => "1.5", "rate" => 700];
  if ($areaPerUnit <= 30) return ["tonnage" => "2",   "rate" => 750];
  if ($areaPerUnit <= 45) return ["tonnage" => "2.5", "rate" => 850];
  return                         ["tonnage" => "3",   "rate" => 900];
}
function dining_set_target_spec($restaurantType, $size){
  $restaurantType = strtolower(trim((string)$restaurantType));
  $size = ucfirst(strtolower(trim((string)$size)));

  if ($restaurantType === "fast_food") {
    return [
      "seat_count" => 4,
      "layout_type" => "standard",
      "restaurant_style" => "fast_food"
    ];
  }

  if ($restaurantType === "premium_dining") {
    if ($size === "Small") {
      return [
        "seat_count" => 4,
        "layout_type" => "premium",
        "restaurant_style" => "premium_dining"
      ];
    }

    return [
      "seat_count" => 6,
      "layout_type" => "premium",
      "restaurant_style" => "premium_dining"
    ];
  }

  // standard_dining
  if ($size === "Large") {
    return [
      "seat_count" => 6,
      "layout_type" => "standard",
      "restaurant_style" => "standard_dining"
    ];
  }

  return [
    "seat_count" => 4,
    "layout_type" => "standard",
    "restaurant_style" => "standard_dining"
  ];
}

function dining_set_quantity_by_size($restaurantType, $size){
  $restaurantType = strtolower(trim((string)$restaurantType));
  $size = ucfirst(strtolower(trim((string)$size)));

  if ($restaurantType === "fast_food") {
    return [
      "Small"  => 4,
      "Medium" => 6,
      "Large"  => 8
    ][$size] ?? 6;
  }

  if ($restaurantType === "premium_dining") {
    return [
      "Small"  => 2,
      "Medium" => 3,
      "Large"  => 4
    ][$size] ?? 3;
  }

  return [
    "Small"  => 3,
    "Medium" => 5,
    "Large"  => 6
  ][$size] ?? 5;
}

function pick_top_dining_set_options($catalog, $restaurantType, $size, $tier, $limit = 3){
  $target = dining_set_target_spec($restaurantType, $size);

  if (!isset($catalog["dining_set"]) || empty($catalog["dining_set"])) {
    return [];
  }

  $filtered = array_filter($catalog["dining_set"], function($p) use ($target, $tier){
    if (strcasecmp((string)($p["tier"] ?? ""), (string)$tier) !== 0) return false;
    if ((int)($p["stock_quantity"] ?? 0) <= 0) return false;

    $specs = $p["specs"] ?? [];
    if (!is_array($specs)) $specs = [];

    $seatCount = (int)($specs["seat_count"] ?? 0);

    if ($seatCount !== (int)$target["seat_count"]) return false;

    return true;
  });

  usort($filtered, function($a, $b){
    $stockCmp = ((int)($b["stock_quantity"] ?? 0) <=> (int)($a["stock_quantity"] ?? 0));
    if ($stockCmp !== 0) return $stockCmp;

    return ((int)$a["price"] <=> (int)$b["price"]);
  });

  return array_slice(array_values($filtered), 0, $limit);
}


function collapse_equivalent_products_cheapest_vendor($products){
  $groups = [];

  foreach ($products as $p) {
    $groupKey = trim((string)($p["product_group_key"] ?? ""));

    if ($groupKey === "") {
      $groupKey =
        strtolower(trim((string)($p["name"] ?? ""))) . "|" .
        strtolower(trim((string)($p["brand"] ?? "")));
    }

    if (!isset($groups[$groupKey])) {
      $groups[$groupKey] = $p;
      continue;
    }

    $currentPrice = (int)($groups[$groupKey]["price"] ?? 0);
    $newPrice = (int)($p["price"] ?? 0);

    if ($newPrice < $currentPrice) {
      $groups[$groupKey] = $p;
      continue;
    }

    if ($newPrice === $currentPrice) {
      $currentStock = (int)($groups[$groupKey]["stock_quantity"] ?? 0);
      $newStock = (int)($p["stock_quantity"] ?? 0);

      if ($newStock > $currentStock) {
        $groups[$groupKey] = $p;
      }
    }
  }

  return array_values($groups);
}

function build_distinct_alternatives($selectedProduct, $pool, $limit = 3){
  if (!is_array($pool) || empty($pool) || !is_array($selectedProduct)) {
    return [];
  }

  $selectedGroupKey = trim((string)($selectedProduct["product_group_key"] ?? ""));
  $selectedName = strtolower(trim((string)($selectedProduct["name"] ?? "")));
  $selectedBrand = strtolower(trim((string)($selectedProduct["brand"] ?? "")));

  $collapsed = collapse_equivalent_products_cheapest_vendor($pool);

  $alts = array_values(array_filter($collapsed, function($p) use ($selectedGroupKey, $selectedName, $selectedBrand){
    $groupKey = trim((string)($p["product_group_key"] ?? ""));
    $name = strtolower(trim((string)($p["name"] ?? "")));
    $brand = strtolower(trim((string)($p["brand"] ?? "")));

    if ($selectedGroupKey !== "" && $groupKey !== "" && $groupKey === $selectedGroupKey) {
      return false;
    }

    if ($groupKey === "" && $name === $selectedName && $brand === $selectedBrand) {
      return false;
    }

    return true;
  }));

  usort($alts, function($a, $b){
    $priceCmp = ((int)($a["price"] ?? 0) <=> (int)($b["price"] ?? 0));
    if ($priceCmp !== 0) return $priceCmp;

    return ((int)($b["stock_quantity"] ?? 0) <=> (int)($a["stock_quantity"] ?? 0));
  });

  return array_slice($alts, 0, $limit);
}

function build_item_alternatives($selectedProductId, $pool, $limit = 3){
  if (!is_array($pool) || empty($pool)) {
    return [];
  }

  $alts = array_filter($pool, function($p) use ($selectedProductId){
    return (string)($p["id"] ?? "") !== (string)$selectedProductId;
  });

  usort($alts, function($a, $b){
    $priceCmp = ((int)($a["price"] ?? 0) <=> (int)($b["price"] ?? 0));
    if ($priceCmp !== 0) return $priceCmp;

    return ((int)($b["stock_quantity"] ?? 0) <=> (int)($a["stock_quantity"] ?? 0));
  });

  return array_slice(array_values($alts), 0, $limit);
}

function tv_target_spec($restaurantType, $size){
  $seats = (int)($GLOBALS["indoorSeats"] ?? 0);
  if ($seats < 30)      return ["screen_size" => 43];
  if ($seats <= 60)     return ["screen_size" => 50];
  return                       ["screen_size" => 55];
}


function tv_quantity_by_context($restaurantType, $size){
  $restaurantType = strtolower(trim((string)$restaurantType));
  $size = ucfirst(strtolower(trim((string)$size)));

  if ($restaurantType === "fast_food") {
    return match($size) {
      "Small" => 1,
      "Medium" => 2,
      "Large" => 3,
      default => 1
    };
  }

  if ($restaurantType === "premium_dining") {
    return match($size) {
      "Small" => 1,
      "Medium" => 1,
      "Large" => 2,
      default => 1
    };
  }

  return match($size) {
    "Small" => 1,
    "Medium" => 1,
    "Large" => 2,
    default => 1
  };
}


function pick_top_tv_options($catalog, $restaurantType, $size, $tier, $limit = 3){
  if (!isset($catalog["tv"]) || empty($catalog["tv"])) {
    return [];
  }

  $target = tv_target_spec($restaurantType, $size);

  $filtered = array_filter($catalog["tv"], function($p) use ($target, $tier){
    if (strcasecmp((string)($p["tier"] ?? ""), (string)$tier) !== 0) return false;
    if ((int)($p["stock_quantity"] ?? 0) <= 0) return false;

    $specs = $p["specs"] ?? [];
    if (!is_array($specs)) $specs = [];

    $screenSize = (int)($specs["screen_size"] ?? 0);

    if ($screenSize !== (int)$target["screen_size"]) return false;

    return true;
  });

  $collapsed = collapse_equivalent_products_cheapest_vendor(array_values($filtered));

  usort($collapsed, function($a, $b){
    $priceCmp = ((int)$a["price"] <=> (int)$b["price"]);
    if ($priceCmp !== 0) return $priceCmp;

    return ((int)($b["stock_quantity"] ?? 0) <=> (int)($a["stock_quantity"] ?? 0));
  });

  return array_slice($collapsed, 0, $limit);
}

function cart_total($cart){
  $sum = 0;
  foreach(($cart["items"] ?? []) as $it){
    $sum += ((int)$it["qty"]) * ((int)$it["unit"]);
  }
  return $sum;
}

function build_pos_cart_by_budget($catalog, $size, $cap){
  $q = pos_quantities_by_size((int)($GLOBALS["indoorSeats"] ?? 0));
  $termQty = (int)$q["terminals"];
  if ($termQty <= 0) $termQty = 1;

  $unitBudgetMain = max(1, (int)floor(((int)$cap * 0.60) / $termQty));
  $unitBudgetPeri = max(1, (int)floor(((int)$cap * 0.25) / $termQty));

  $terminal = pick_best_under_unit_budget($catalog, "terminal", $unitBudgetMain);
  $printer  = pick_best_under_unit_budget($catalog, "printer",  $unitBudgetPeri);
  $drawer   = pick_best_under_unit_budget($catalog, "drawer",   $unitBudgetPeri);

  $software = pick_best_under_unit_budget($catalog, "software", max(1, (int)floor((int)$cap * 0.15)));

  $cart = ["items"=>[]];

  if ($terminal) {
    $cart["items"]["terminal"] = [
  "product_name" => $terminal["name"],
  "brand" => $terminal["brand"] ?? null,
  "tier" => $GLOBALS["posTier"],
  "module" => "pos",
  "category_id" => null,
  "type"=>"terminal",
  "product_id"=>$terminal["id"],
  "name"=>$terminal["name"],
  "unit"=>(int)$terminal["price"],
  "qty"=>$termQty,
  "image_url"=>$terminal["image_url"] ?? null,
  "vendor_name" => $terminal["vendor_name"] ?? null,
  "product_group_key" => $terminal["product_group_key"] ?? null,
  "vendor_user_id"    => $terminal["vendor_user_id"] ?? null,
  "alternatives" => build_distinct_alternatives(
  $terminal,
  $catalog["terminal"] ?? [],
  3
),
];
  }
  if ($printer) {
    $cart["items"]["printer"] = [
      "product_name" => $printer["name"],
      "brand" => $printer["brand"] ?? null,
      "tier" => $GLOBALS["posTier"],
      "module" => "pos",
      "category_id" => null,
      "type"=>"printer","product_id"=>$printer["id"],"name"=>$printer["name"],
      "unit"=>(int)$printer["price"],"qty"=>$termQty,"image_url"=>$printer["image_url"] ?? null,
      "vendor_name" => $printer["vendor_name"] ?? null,
      "product_group_key" => $printer["product_group_key"] ?? null,
      "vendor_user_id"    => $printer["vendor_user_id"] ?? null,
      "alternatives" => build_distinct_alternatives(
  $printer,
  $catalog["printer"] ?? [],
  3
),
    ];
  }
  if ($drawer) {
    $cart["items"]["drawer"] = [
      "product_name" => $drawer["name"],
      "brand" => $drawer["brand"] ?? null,
      "tier" => $GLOBALS["posTier"],
      "module" => "pos",
      "category_id" => null,
      "type"=>"drawer","product_id"=>$drawer["id"],"name"=>$drawer["name"],
      "unit"=>(int)$drawer["price"],"qty"=>$termQty,"image_url"=>$drawer["image_url"] ?? null,
      "vendor_name" => $drawer["vendor_name"] ?? null,
      "product_group_key" => $drawer["product_group_key"] ?? null,
      "vendor_user_id"    => $drawer["vendor_user_id"] ?? null,
      "alternatives" => build_distinct_alternatives(
  $drawer,
  $catalog["drawer"] ?? [],
  3
),
    ];
  }
  if ($software) {
    $cart["items"]["software"] = [
      "product_name" => $software["name"],
      "brand" => $software["brand"] ?? null,
      "tier" => $GLOBALS["posTier"],
      "module" => "pos",
      "category_id" => null,
      "type"=>"software","product_id"=>$software["id"],"name"=>$software["name"],
      "unit"=>(int)$software["price"],"qty"=>1,"image_url"=>$software["image_url"] ?? null,
      "vendor_name" => $software["vendor_name"] ?? null,
      "product_group_key" => $software["product_group_key"] ?? null,
      "vendor_user_id"    => $software["vendor_user_id"] ?? null,
      "alternatives" => build_distinct_alternatives(
  $software,
  $catalog["software"] ?? [],
  3
),
    ];
  }

  $remaining = $cap - cart_total($cart);

  if ($remaining > 0 && isset($catalog["scanner"][0])) {
    $sc = pick_best_under_unit_budget($catalog, "scanner", (int)floor($remaining * 0.30));
    if ($sc) {
      $cart["items"]["scanner"] = [
        "type"=>"scanner","product_id"=>$sc["id"],"name"=>$sc["name"],
        "unit"=>(int)$sc["price"],"qty"=>1,"image_url"=>$sc["image_url"] ?? null, 
        "alternatives" => build_distinct_alternatives(
  $sc,
  $catalog["scanner"] ?? [],
  3
),     
      ];
      $remaining = $cap - cart_total($cart);
    }
  }

  if ($remaining > 0 && $size !== "Small" && isset($catalog["kds"][0])) {
    $kds = pick_best_under_unit_budget($catalog, "kds", (int)floor($remaining * 0.60));
    if ($kds) {
      $cart["items"]["kds"] = [
        "type"=>"kds","product_id"=>$kds["id"],"name"=>$kds["name"],
        "unit"=>(int)$kds["price"],"qty"=>1,"image_url"=>$kds["image_url"] ?? null,
      "alternatives" => build_distinct_alternatives(
  $kds,
  $catalog["kds"] ?? [],
  3
),
      ];
      $remaining = $cap - cart_total($cart);
    }
  }

  if ($remaining > 0 && isset($catalog["tablet"][0])) {
    $tb = pick_best_under_unit_budget($catalog, "tablet", (int)floor($remaining * 0.70));
    if ($tb) {
      $cart["items"]["tablet"] = [
        "type"=>"tablet","product_id"=>$tb["id"],"name"=>$tb["name"],
        "unit"=>(int)$tb["price"],"qty"=>1,"image_url"=>$tb["image_url"] ?? null,
        "alternatives" => build_distinct_alternatives(
  $tb,
  $catalog["tablet"] ?? [],
  3
),
      ];
    }
  }

  return $cart;
}

function build_kitchen_cart_by_budget($catalog, $size, $cap){
  $q = kitchen_quantities_by_size((int)($GLOBALS["indoorSeats"] ?? 0), (int)($GLOBALS["outdoorSeats"] ?? 0));

  $cart = ["items"=>[]];

  $core  = ["oven","fryer","microwave","fridge","freezer","blender"];
  $extra = ["grill","mixer","coffee"];

  $coreBudget = (int)floor($cap * 0.75);

  $coreTypesCount = 0;
  foreach($core as $t){
    if (($q[$t] ?? 0) > 0) $coreTypesCount++;
  }
  if ($coreTypesCount <= 0) $coreTypesCount = 1;

  foreach($core as $type){
    $qty = (int)($q[$type] ?? 0);
    if ($qty <= 0) continue;

    $unitBudget = (int)floor(($coreBudget / $coreTypesCount) / max(1,$qty));
    $p = pick_best_under_unit_budget($catalog, $type, $unitBudget);
    if ($p) {
      $cart["items"][$type] = [
  "type"=>$type,
  "product_id"=>$p["id"],
  "name"=>$p["name"],
  "unit"=>(int)$p["price"],
  "qty"=>$qty,
  "image_url"=>$p["image_url"] ?? null,
  "brand" => $p["brand"] ?? null,
  "vendor_name" => $p["vendor_name"] ?? null,
  "product_name" => $p["name"],
  "tier" => $GLOBALS["kitchenTier"],
  "module" => "kitchen",
  "category_id" => null,
  "product_group_key" => $p["product_group_key"] ?? null,
  "vendor_user_id" => $p["vendor_user_id"] ?? null,
  "alternatives" => build_distinct_alternatives(
  $p,
  $catalog[$type] ?? [],
  3
),
];
    }
  }

  $remaining = $cap - cart_total($cart);

  foreach($extra as $type){
    $qty = (int)($q[$type] ?? 0);
    if ($qty <= 0) continue;
    if ($remaining <= 0) break;

    $unitBudget = (int)floor(($remaining * 0.45) / max(1,$qty));
    $p = pick_best_under_unit_budget($catalog, $type, $unitBudget);
    if ($p) {
      $cart["items"][$type] = [
  "type"=>$type,
  "product_id"=>$p["id"],
  "name"=>$p["name"],
  "unit"=>(int)$p["price"],
  "qty"=>$qty,
  "image_url"=>$p["image_url"] ?? null,
  "brand" => $p["brand"] ?? null,
  "vendor_name" => $p["vendor_name"] ?? null,
  "product_name" => $p["name"],
  "tier" => $GLOBALS["kitchenTier"],
  "module" => "kitchen",
  "category_id" => null,
  "product_group_key" => $p["product_group_key"] ?? null,
  "vendor_user_id" => $p["vendor_user_id"] ?? null,
  "alternatives" => build_distinct_alternatives(
  $p,
  $catalog[$type] ?? [],
  3
),
];
      $remaining = $cap - cart_total($cart);
    }
  }

  return $cart;
}


function build_furniture_cart_by_budget($catalog, $size, $cap){
  $restaurantType = $GLOBALS["restaurantType"] ?? "standard_dining";
  $tier = $GLOBALS["furnitureTier"] ?? "Balanced";

  $cart = ["items" => []];

$setQty = max(1, (int)ceil((int)($GLOBALS["indoorSeats"] ?? 0) / 4));
  $setOptions = pick_top_dining_set_options($catalog, $restaurantType, $size, $tier, 3);

  if (!empty($setOptions)) {
    $recommended = $setOptions[0];

    $cart["items"]["dining_set"] = [
      "type"              => "dining_set",
      "product_id"        => $recommended["id"],
      "name"              => $recommended["name"],
      "unit"              => (int)$recommended["price"],
      "qty"               => $setQty,
      "image_url"         => $recommended["image_url"] ?? null,
      "brand"             => $recommended["brand"] ?? null,
      "vendor_name"       => $recommended["vendor_name"] ?? null,
      "product_name"      => $recommended["name"],
      "tier"              => $recommended["tier"] ?? $tier,
      "module"            => "furniture",
      "category_id"       => $recommended["category_id"] ?? null,
      "product_group_key" => $recommended["product_group_key"] ?? null,
      "vendor_user_id"    => $recommended["vendor_user_id"] ?? null,
      "stock_quantity"    => $recommended["stock_quantity"] ?? 0,
      "specs"             => $recommended["specs"] ?? [],
      "alternatives" => build_distinct_alternatives(
    $recommended,
    $setOptions,
    3
),
    ];
  }
    /* ===== PASTE TV CODE HERE ===== */

$remaining = $cap - cart_total($cart);
$tvQty = ($restaurantType === "cloud_kitchen") ? 0 : max(1, (int)ceil((int)($GLOBALS["indoorSeats"] ?? 0) / 20));
$tvOptions = pick_top_tv_options($catalog, $restaurantType, $size, $tier, 3);

if ($remaining > 0 && $tvQty > 0 && !empty($tvOptions)) {
  $recommendedTv = $tvOptions[0];

  $cart["items"]["tv"] = [
    "type"              => "tv",
    "product_id"        => $recommendedTv["id"],
    "name"              => $recommendedTv["name"],
    "unit"              => (int)$recommendedTv["price"],
    "qty"               => $tvQty,
    "image_url"         => $recommendedTv["image_url"] ?? null,
    "brand"             => $recommendedTv["brand"] ?? null,
    "vendor_name"       => $recommendedTv["vendor_name"] ?? null,
    "product_name"      => $recommendedTv["name"],
    "tier"              => $recommendedTv["tier"] ?? $tier,
    "module"            => "furniture",
    "category_id"       => $recommendedTv["category_id"] ?? null,
    "product_group_key" => $recommendedTv["product_group_key"] ?? null,
    "vendor_user_id"    => $recommendedTv["vendor_user_id"] ?? null,
    "stock_quantity"    => $recommendedTv["stock_quantity"] ?? 0,
    "specs"             => $recommendedTv["specs"] ?? [],
    "alternatives" => build_distinct_alternatives(
    $recommendedTv,
    $tvOptions,
    3
),
  ];
}
  return $cart;
}


/* ---------------- Handle actions (Recalc / Qty / Replace) ---------------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

  if (isset($_POST["recalc_pos"])) {
    $_SESSION["wizard"]["pos_cart"] = build_pos_cart_by_budget($GLOBALS["POS_CATALOG_ACTIVE"], $GLOBALS["size"], $GLOBALS["posCap"]);
    header("Location: packages.php?module=pos");
    exit;
  }

  if (isset($_POST["recalc_kitchen"])) {
    $_SESSION["wizard"]["kitchen_cart"] = build_kitchen_cart_by_budget($GLOBALS["KITCHEN_CATALOG_ACTIVE"], $GLOBALS["size"], $GLOBALS["kitchenCap"]);
    header("Location: packages.php?module=kitchen");
    exit;
  }
  if (isset($_POST["recalc_furniture"])) {
  $_SESSION["wizard"]["furniture_cart"] = build_furniture_cart_by_budget(
    $GLOBALS["FURNITURE_CATALOG_ACTIVE"],
    $GLOBALS["size"],
    $GLOBALS["furnitureCap"]
  );
  header("Location: packages.php?module=furniture");
  exit;
}

if (isset($_POST["update_furniture_qty"])) {
  $type = $_POST["type"] ?? "";
  $delta = (int)($_POST["delta"] ?? 0);

  if (isset($_SESSION["wizard"]["furniture_cart"]["items"][$type])) {
    $qty = (int)$_SESSION["wizard"]["furniture_cart"]["items"][$type]["qty"];
    $qty = max(0, $qty + $delta);

    $core = ["dining_set","chair","table","counter"];
    if ($qty === 0 && !in_array($type, $core, true)) {
      unset($_SESSION["wizard"]["furniture_cart"]["items"][$type]);
    } else {
      $_SESSION["wizard"]["furniture_cart"]["items"][$type]["qty"] = $qty;
    }
  }

  header("Location: packages.php?module=furniture");
  exit;
}


  if (isset($_POST["update_qty"])) {
    $type = $_POST["type"] ?? "";
    $delta = (int)($_POST["delta"] ?? 0);

    if (isset($_SESSION["wizard"]["pos_cart"]["items"][$type])) {
      $qty = (int)$_SESSION["wizard"]["pos_cart"]["items"][$type]["qty"];
      $qty = max(0, $qty + $delta);
      if ($type === "software") $qty = 1;

      $core = ["terminal","printer","drawer","software"];
      if ($qty === 0 && !in_array($type, $core, true)) {
        unset($_SESSION["wizard"]["pos_cart"]["items"][$type]);
      } else {
        $_SESSION["wizard"]["pos_cart"]["items"][$type]["qty"] = $qty;
      }
    }

    header("Location: packages.php?module=pos");
    exit;
  }

  if (isset($_POST["replace_item"])) {
    $type = $_POST["type"] ?? "";
    $newId = $_POST["new_product_id"] ?? "";

    if (isset($_SESSION["wizard"]["pos_cart"]["items"][$type])) {
      $p = find_by_id($GLOBALS["POS_CATALOG_ACTIVE"], $type, $newId);
      if ($p) {
        $_SESSION["wizard"]["pos_cart"]["items"][$type]["product_id"] = $p["id"];
        $_SESSION["wizard"]["pos_cart"]["items"][$type]["name"] = $p["name"];
        $_SESSION["wizard"]["pos_cart"]["items"][$type]["unit"] = (int)$p["price"];
        $_SESSION["wizard"]["pos_cart"]["items"][$type]["image_url"] = $p["image_url"] ?? null;
        $_SESSION["wizard"]["pos_cart"]["items"][$type]["brand"] = $p["brand"] ?? null;
        $_SESSION["wizard"]["pos_cart"]["items"][$type]["vendor_name"] = $p["vendor_name"] ?? null;
        $_SESSION["wizard"]["pos_cart"]["items"][$type]["vendor_user_id"] = $p["vendor_user_id"] ?? null;
        $_SESSION["wizard"]["pos_cart"]["items"][$type]["product_group_key"] = $p["product_group_key"] ?? null;
        $_SESSION["wizard"]["pos_cart"]["items"][$type]["alternatives"] = build_distinct_alternatives(
          $p,
          $GLOBALS["POS_CATALOG_ACTIVE"][$type] ?? [],
          3
        );
      }
    }

    header("Location: packages.php?module=pos");
    exit;
  }

  if (isset($_POST["update_kitchen_qty"])) {
    $type = $_POST["type"] ?? "";
    $delta = (int)($_POST["delta"] ?? 0);

    if (isset($_SESSION["wizard"]["kitchen_cart"]["items"][$type])) {
      $qty = (int)$_SESSION["wizard"]["kitchen_cart"]["items"][$type]["qty"];
      $qty = max(0, $qty + $delta);

      $core = ["oven","fryer","microwave","fridge","freezer","blender"];
      if ($qty === 0 && !in_array($type, $core, true)) {
        unset($_SESSION["wizard"]["kitchen_cart"]["items"][$type]);
      } else {
        $_SESSION["wizard"]["kitchen_cart"]["items"][$type]["qty"] = $qty;
      }
    }

    header("Location: packages.php?module=kitchen");
    exit;
  }

  if (isset($_POST["replace_kitchen_item"])) {
    $type = $_POST["type"] ?? "";
    $newId = $_POST["new_product_id"] ?? "";

    if (isset($_SESSION["wizard"]["kitchen_cart"]["items"][$type])) {
      $p = find_by_id($GLOBALS["KITCHEN_CATALOG_ACTIVE"], $type, $newId);
      if ($p) {
        $_SESSION["wizard"]["kitchen_cart"]["items"][$type]["product_id"] = $p["id"];
        $_SESSION["wizard"]["kitchen_cart"]["items"][$type]["name"] = $p["name"];
        $_SESSION["wizard"]["kitchen_cart"]["items"][$type]["unit"] = (int)$p["price"];
        $_SESSION["wizard"]["kitchen_cart"]["items"][$type]["image_url"] = $p["image_url"] ?? null;
        $_SESSION["wizard"]["kitchen_cart"]["items"][$type]["brand"] = $p["brand"] ?? null;
        $_SESSION["wizard"]["kitchen_cart"]["items"][$type]["vendor_name"] = $p["vendor_name"] ?? null;
        $_SESSION["wizard"]["kitchen_cart"]["items"][$type]["vendor_user_id"] = $p["vendor_user_id"] ?? null;
        $_SESSION["wizard"]["kitchen_cart"]["items"][$type]["product_group_key"] = $p["product_group_key"] ?? null;
        $_SESSION["wizard"]["kitchen_cart"]["items"][$type]["alternatives"] = build_distinct_alternatives(
          $p,
          $GLOBALS["KITCHEN_CATALOG_ACTIVE"][$type] ?? [],
          3
        );
      }
    }

    header("Location: packages.php?module=kitchen");
    exit;
  }

  if (isset($_POST["replace_furniture_item"])) {
  $type = $_POST["type"] ?? "";
  $newId = $_POST["new_product_id"] ?? "";

  if (isset($_SESSION["wizard"]["furniture_cart"]["items"][$type])) {
    $p = find_by_id($GLOBALS["FURNITURE_CATALOG_ACTIVE"], $type, $newId);
    if ($p) {
      $_SESSION["wizard"]["furniture_cart"]["items"][$type]["product_id"] = $p["id"];
      $_SESSION["wizard"]["furniture_cart"]["items"][$type]["name"] = $p["name"];
      $_SESSION["wizard"]["furniture_cart"]["items"][$type]["unit"] = (int)$p["price"];
      $_SESSION["wizard"]["furniture_cart"]["items"][$type]["image_url"] = $p["image_url"] ?? null;
      $_SESSION["wizard"]["furniture_cart"]["items"][$type]["brand"] = $p["brand"] ?? null;
      $_SESSION["wizard"]["furniture_cart"]["items"][$type]["vendor_name"] = $p["vendor_name"] ?? null;
      $_SESSION["wizard"]["furniture_cart"]["items"][$type]["vendor_user_id"] = $p["vendor_user_id"] ?? null;
      $_SESSION["wizard"]["furniture_cart"]["items"][$type]["product_group_key"] = $p["product_group_key"] ?? null;
      $_SESSION["wizard"]["furniture_cart"]["items"][$type]["alternatives"] = build_distinct_alternatives(
        $p,
        $GLOBALS["FURNITURE_CATALOG_ACTIVE"][$type] ?? [],
        3
      );
    }
  }

  header("Location: packages.php?module=furniture");
  exit;
}

}

/* ---------------- Active module ---------------- */
$activeModule = $_GET["module"] ?? (in_array("pos",$modules,true) ? "pos" : $modules[0]);




/* ---------------- If tier changed → reset cart ---------------- */
if (($_SESSION["wizard"]["pos_cart_tier"] ?? null) !== $posTier) {
  unset($_SESSION["wizard"]["pos_cart"]);
  $_SESSION["wizard"]["pos_cart_tier"] = $posTier;
}

if (($_SESSION["wizard"]["kitchen_cart_tier"] ?? null) !== $kitchenTier) {
  unset($_SESSION["wizard"]["kitchen_cart"]);
  $_SESSION["wizard"]["kitchen_cart_tier"] = $kitchenTier;
}
if (($_SESSION["wizard"]["furniture_cart_tier"] ?? null) !== $furnitureTier) {
  unset($_SESSION["wizard"]["furniture_cart"]);
  $_SESSION["wizard"]["furniture_cart_tier"] = $furnitureTier;
}

/* ---------------- Auto build carts if empty (UNIVERSAL) ---------------- */
if ($posCap > 0 && empty($_SESSION["wizard"]["pos_cart"])) {
  $_SESSION["wizard"]["pos_cart"] = build_pos_cart_by_budget(
    $POS_CATALOG_ACTIVE,
    $size,
    $posCap
  );
}

if ($kitchenCap > 0 && empty($_SESSION["wizard"]["kitchen_cart"])) {
  $_SESSION["wizard"]["kitchen_cart"] = build_kitchen_cart_by_budget(
    $KITCHEN_CATALOG_ACTIVE,
    $size,
    $kitchenCap
  );
}

if ($furnitureCap > 0 && empty($_SESSION["wizard"]["furniture_cart"])) {
  $_SESSION["wizard"]["furniture_cart"] = build_furniture_cart_by_budget(
    $FURNITURE_CATALOG_ACTIVE,
    $size,
    $furnitureCap
  );
}

// Store AC unit count for service_jobs.php pricing
$acUnits = ac_units_from_area($areaSqm);
$acTonnageData = ac_tonnage_from_area_per_unit($areaSqm, $acUnits);
$_SESSION["wizard"]["ac_units"]   = $acUnits;
$_SESSION["wizard"]["ac_tonnage"] = $acTonnageData["tonnage"];
$_SESSION["wizard"]["ac_rate"]    = $acTonnageData["rate"];


$posCart = $_SESSION["wizard"]["pos_cart"] ?? null;
$posTotal = $posCart ? cart_total($posCart) : 0;
$posRemaining = max(0, $posCap - $posTotal);
$posOver = max(0, $posTotal - $posCap);

$kitchenCart = $_SESSION["wizard"]["kitchen_cart"] ?? null;
$kitchenTotal = $kitchenCart ? cart_total($kitchenCart) : 0;
$kitchenRemaining = max(0, $kitchenCap - $kitchenTotal);
$kitchenOver = max(0, $kitchenTotal - $kitchenCap);

$furnitureCart = $_SESSION["wizard"]["furniture_cart"] ?? null;
$furnitureTotal = $furnitureCart ? cart_total($furnitureCart) : 0;
$furnitureRemaining = max(0, $furnitureCap - $furnitureTotal);
$furnitureOver = max(0, $furnitureTotal - $furnitureCap);


$grandTotal = (int)$posTotal + (int)$kitchenTotal + (int)$furnitureTotal;

$hasAnyItems = false;
if (!empty($_SESSION["wizard"]["pos_cart"]["items"])) $hasAnyItems = true;
if (!empty($_SESSION["wizard"]["kitchen_cart"]["items"])) $hasAnyItems = true;
if (!empty($_SESSION["wizard"]["furniture_cart"]["items"])) $hasAnyItems = true;

if (!isset($_SESSION["carts"])) $_SESSION["carts"] = [];
$_SESSION["carts"]["pos"] = $posCart ?? ($_SESSION["carts"]["pos"] ?? null);
$_SESSION["carts"]["kitchen"] = $kitchenCart ?? ($_SESSION["carts"]["kitchen"] ?? null);
$_SESSION["carts"]["furniture"] = $furnitureCart ?? ($_SESSION["carts"]["furniture"] ?? null);

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SetupForge - Packages</title>
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/style.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

</head>
<body>

<?php include "includes/navbar.php"; ?>

<main class="sf-packages-page">

  <div class="container sf-pkg-body">

    <div class="sf-pkg-tabrow">
      <div class="sf-pkg-tabs">
        <?php foreach($alloc as $m=>$cap): ?>
          <a
            class="sf-pkg-tab <?= $activeModule === $m ? 'is-active' : '' ?>"
            href="packages.php?<?= htmlspecialchars(build_base_qs(["module"=>$m])) ?>"
          >
            <?= htmlspecialchars($labels[$m] ?? $m) ?>
          </a>
        <?php endforeach; ?>
      </div>
      <a class="sf-pkg-restart-btn" href="setup.php?step=1">
        <i class="bi bi-arrow-counterclockwise"></i> Restart Setup
      </a>
    </div>

    <div class="sf-pkg-content">

        <?php if ($activeModule === "pos"): ?>
          <div class="sf-module-shell">
            <div class="sf-pkg-progress-wrap">
              <div class="sf-pkg-progress-row">
                <span class="sf-pkg-progress-label">POS &amp; Tech</span>
                <span class="sf-pkg-progress-used"><?= egp($posTotal) ?> / <?= egp($posCap) ?></span>
                <span class="sf-pkg-progress-remaining <?= $posOver > 0 ? 'is-over' : '' ?>">
                  <?php if($posOver > 0): ?><?= egp($posOver) ?> over cap<?php else: ?><?= egp($posRemaining) ?> remaining<?php endif; ?>
                </span>
              </div>
              <div class="sf-pkg-progress-track">
                <div class="sf-pkg-progress-fill <?= $posOver > 0 ? 'is-over' : '' ?>" style="width:<?= $posCap > 0 ? min(100, round($posTotal / $posCap * 100)) : 0 ?>%"></div>
              </div>
            </div>

            <div class="sf-module-toolbar">
              <form method="post" class="m-0">
                <input type="hidden" name="recalc_pos" value="1">
                <button class="btn sf-btn-light-main btn-sm">
                  <i class="bi bi-stars"></i>
                  Recalculate Auto Package
                </button>
              </form>
            </div>

            <?php if(!$posCart): ?>
              <div class="alert alert-warning mt-3">No POS cart yet.</div>
            <?php else: ?>
              <div class="sf-pkg-grid">
                <?php foreach($posCart["items"] as $type=>$it): ?>
                  <article class="sf-pkg-card" id="row_<?= htmlspecialchars($activeModule) ?>_<?= htmlspecialchars($type) ?>">

                    <div class="sf-pkg-card-media">
                      <?php if(!empty($it["image_url"])): ?>
                        <img src="<?= htmlspecialchars($it["image_url"]) ?>" alt="">
                      <?php else: ?>
                        <div class="sf-pkg-card-fallback"><?= strtoupper(substr($type,0,2)) ?></div>
                      <?php endif; ?>
                    </div>

                    <div class="sf-pkg-card-body">
                      <h3 class="sf-pkg-card-name"><?= htmlspecialchars($it["name"]) ?></h3>
                      <div class="sf-pkg-card-meta">
                        <?php if(!empty($it["brand"])): ?><span><?= htmlspecialchars($it["brand"]) ?></span><?php endif; ?>
                        <?php if(!empty($it["vendor_name"])): ?><span><?= htmlspecialchars($it["vendor_name"]) ?></span><?php endif; ?>
                      </div>
                      <div class="sf-pkg-card-price"><?= egp($it["unit"]) ?></div>
                    </div>

                    <div class="sf-pkg-card-footer">
                      <div class="sf-pkg-qty">
                        <form method="post" class="m-0">
                          <input type="hidden" name="update_qty" value="1">
                          <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                          <input type="hidden" name="delta" value="-1">
                          <button class="sf-pkg-qty-btn" <?= $type==="software" ? "disabled" : "" ?>>−</button>
                        </form>
                        <span class="sf-pkg-qty-val"><?= (int)$it["qty"] ?></span>
                        <form method="post" class="m-0">
                          <input type="hidden" name="update_qty" value="1">
                          <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                          <input type="hidden" name="delta" value="1">
                          <button class="sf-pkg-qty-btn" <?= $type==="software" ? "disabled" : "" ?>>+</button>
                        </form>
                      </div>
                    </div>

                    <div class="sf-pkg-alts">
                      <?php if (!empty($it["alternatives"])): ?>
                        <?php foreach (array_slice($it["alternatives"], 0, 3) as $alt): ?>
                          <form method="post" class="sf-pkg-chip m-0">
                            <input type="hidden" name="replace_item" value="1">
                            <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                            <input type="hidden" name="new_product_id" value="<?= htmlspecialchars($alt["id"]) ?>">
                            <button type="submit" class="sf-pkg-chip-btn">
                              <div class="sf-pkg-chip-thumb">
                                <?php if(!empty($alt["image_url"])): ?>
                                  <img src="<?= htmlspecialchars($alt["image_url"]) ?>" class="sf-pkg-chip-img" alt=""
                                       onerror="this.style.display='none'">
                                <?php else: ?>
                                  <div class="sf-pkg-chip-fallback"><?= strtoupper(substr($type,0,2)) ?></div>
                                <?php endif; ?>
                              </div>
                              <span class="sf-pkg-chip-name"><?= htmlspecialchars($alt["name"]) ?></span>
                              <span class="sf-pkg-chip-price"><?= egp($alt["price"]) ?></span>
                            </button>
                          </form>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <div class="sf-pkg-no-alts">No alternatives available</div>
                      <?php endif; ?>
                    </div>

                    <?php
                      $sellers = [];
                      if (!empty($it["product_name"])) {
                        $sellers = load_vendor_offers_for_same_product(
                          $conn,
                          $it["product_name"],
                          $it["brand"] ?? "",
                          "pos",
                          $posTier,
                          $it["category_id"] ?? null,
                          $FILTER
                        );
                      }

                      $sellers_hint = [];
                      if (!empty($it["product_name"])) {
                        $hintFilter = $FILTER;
                        $hintFilter["min_price"] = null;
                        $hintFilter["max_price"] = null;
                        $hintFilter["vendor"] = "";
                        $sellers_hint = load_vendor_offers_for_same_product(
                          $conn,
                          $it["product_name"],
                          $it["brand"] ?? "",
                          "pos",
                          $posTier,
                          $it["category_id"] ?? null,
                          $hintFilter
                        );
                      }
                    ?>

                    <div class="sf-pkg-sellers-trigger">
                      <?php if (!empty($it["product_group_key"])): ?>
                        <button type="button" class="btn btn-link p-0 sf-text-link"
                                data-sellers-open="1"
                                data-group="<?= htmlspecialchars($it["product_group_key"]) ?>">
                          View other sellers
                        </button>
                      <?php else: ?>
                        <span class="sf-pkg-no-sellers">No other sellers</span>
                      <?php endif; ?>
                    </div>

                    <div class="sf-sellers-panel d-none" data-group="<?= htmlspecialchars($it["product_group_key"] ?? "") ?>">
                      <?php
                        $sellerChoices = [];
                        foreach ($sellers as $offer) {
                          if ((string)$offer["id"] === (string)$it["product_id"]) continue;
                          if ((string)($offer["vendor_user_id"] ?? "") === (string)($it["vendor_user_id"] ?? "")) continue;
                          $sellerChoices[] = $offer;
                        }
                      ?>
                      <?php if (empty($sellerChoices)): ?>
                        <div class="sf-empty-inline">No other sellers available for this product.</div>
                      <?php else: ?>
                        <div class="sf-seller-list">
                          <?php foreach ($sellerChoices as $offer): ?>
                            <form method="post" class="m-0">
                              <?php if ($activeModule === "pos"): ?>
                                <input type="hidden" name="replace_item" value="1">
                              <?php elseif ($activeModule === "kitchen"): ?>
                                <input type="hidden" name="replace_kitchen_item" value="1">
                              <?php elseif ($activeModule === "furniture"): ?>
                                <input type="hidden" name="replace_furniture_item" value="1">
                              <?php endif; ?>
                              <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                              <input type="hidden" name="new_product_id" value="<?= htmlspecialchars($offer["id"]) ?>">
                              <button type="submit" class="sf-seller-card-btn">
                                <div class="sf-seller-card-top">
                                  <div class="sf-seller-name"><?= htmlspecialchars($offer["vendor_name"]) ?></div>
                                  <div class="sf-seller-price"><?= egp($offer["price"]) ?></div>
                                </div>
                              </button>
                            </form>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </div>

                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

        <?php elseif ($activeModule === "kitchen"): ?>
          <div class="sf-module-shell">
            
            <div class="sf-pkg-progress-wrap">
              <div class="sf-pkg-progress-row">
                <span class="sf-pkg-progress-label">Kitchen / Equipment</span>
                <span class="sf-pkg-progress-used"><?= egp($kitchenTotal) ?> / <?= egp($kitchenCap) ?></span>
                <span class="sf-pkg-progress-remaining <?= $kitchenOver > 0 ? 'is-over' : '' ?>">
                  <?php if($kitchenOver > 0): ?><?= egp($kitchenOver) ?> over cap<?php else: ?><?= egp($kitchenRemaining) ?> remaining<?php endif; ?>
                </span>
              </div>
              <div class="sf-pkg-progress-track">
                <div class="sf-pkg-progress-fill <?= $kitchenOver > 0 ? 'is-over' : '' ?>" style="width:<?= $kitchenCap > 0 ? min(100, round($kitchenTotal / $kitchenCap * 100)) : 0 ?>%"></div>
              </div>
            </div>

            <div class="sf-module-toolbar">
              <form method="post" class="m-0">
                <input type="hidden" name="recalc_kitchen" value="1">
                <button class="btn sf-btn-light-main btn-sm">
                  <i class="bi bi-stars"></i>
                  Recalculate Auto Package
                </button>
              </form>
            </div>

            <?php if(!$kitchenCart): ?>
              <div class="alert alert-warning mt-3">No Kitchen cart yet.</div>
            <?php else: ?>
              <div class="sf-pkg-grid">
                <?php foreach($kitchenCart["items"] as $type=>$it): ?>
                  <article class="sf-pkg-card" id="row_<?= htmlspecialchars($activeModule) ?>_<?= htmlspecialchars($type) ?>">

                    <div class="sf-pkg-card-media">
                      <?php if(!empty($it["image_url"])): ?>
                        <img src="<?= htmlspecialchars($it["image_url"]) ?>" alt="">
                      <?php else: ?>
                        <div class="sf-pkg-card-fallback"><?= strtoupper(substr($type,0,2)) ?></div>
                      <?php endif; ?>
                    </div>

                    <div class="sf-pkg-card-body">
                      <h3 class="sf-pkg-card-name"><?= htmlspecialchars($it["name"]) ?></h3>
                      <div class="sf-pkg-card-meta">
                        <?php if(!empty($it["brand"])): ?><span><?= htmlspecialchars($it["brand"]) ?></span><?php endif; ?>
                        <?php if(!empty($it["vendor_name"])): ?><span><?= htmlspecialchars($it["vendor_name"]) ?></span><?php endif; ?>
                      </div>
                      <div class="sf-pkg-card-price"><?= egp($it["unit"]) ?></div>
                    </div>

                    <div class="sf-pkg-card-footer">
                      <div class="sf-pkg-qty">
                        <form method="post" class="m-0">
                          <input type="hidden" name="update_kitchen_qty" value="1">
                          <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                          <input type="hidden" name="delta" value="-1">
                          <button class="sf-pkg-qty-btn">−</button>
                        </form>
                        <span class="sf-pkg-qty-val"><?= (int)$it["qty"] ?></span>
                        <form method="post" class="m-0">
                          <input type="hidden" name="update_kitchen_qty" value="1">
                          <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                          <input type="hidden" name="delta" value="1">
                          <button class="sf-pkg-qty-btn">+</button>
                        </form>
                      </div>
                    </div>

                    <div class="sf-pkg-alts">
                      <?php if (!empty($it["alternatives"])): ?>
                        <?php foreach (array_slice($it["alternatives"], 0, 3) as $alt): ?>
                          <form method="post" class="sf-pkg-chip m-0">
                            <input type="hidden" name="replace_kitchen_item" value="1">
                            <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                            <input type="hidden" name="new_product_id" value="<?= htmlspecialchars($alt["id"]) ?>">
                            <button type="submit" class="sf-pkg-chip-btn">
                              <div class="sf-pkg-chip-thumb">
                                <?php if(!empty($alt["image_url"])): ?>
                                  <img src="<?= htmlspecialchars($alt["image_url"]) ?>" class="sf-pkg-chip-img" alt=""
                                       onerror="this.style.display='none'">
                                <?php else: ?>
                                  <div class="sf-pkg-chip-fallback"><?= strtoupper(substr($type,0,2)) ?></div>
                                <?php endif; ?>
                              </div>
                              <span class="sf-pkg-chip-name"><?= htmlspecialchars($alt["name"]) ?></span>
                              <span class="sf-pkg-chip-price"><?= egp($alt["price"]) ?></span>
                            </button>
                          </form>
                        <?php endforeach; ?>
                      <?php else: ?>
                        <div class="sf-pkg-no-alts">No alternatives available</div>
                      <?php endif; ?>
                    </div>

                    <?php
                      $sellers = [];
                      if (!empty($it["product_name"])) {
                        $sellers = load_vendor_offers_for_same_product(
                          $conn,
                          $it["product_name"],
                          $it["brand"] ?? "",
                          "kitchen",
                          $kitchenTier,
                          $it["category_id"] ?? null,
                          $FILTER
                        );
                      }

                      $sellers_hint = [];
                      if (!empty($it["product_name"])) {
                        $hintFilter = $FILTER;
                        $hintFilter["min_price"] = null;
                        $hintFilter["max_price"] = null;
                        $hintFilter["vendor"] = "";
                        $sellers_hint = load_vendor_offers_for_same_product(
                          $conn,
                          $it["product_name"],
                          $it["brand"] ?? "",
                          "kitchen",
                          $kitchenTier,
                          $it["category_id"] ?? null,
                          $hintFilter
                        );
                      }
                    ?>

                    <div class="sf-pkg-sellers-trigger">
                      <?php if (!empty($it["product_group_key"])): ?>
                        <button type="button" class="btn btn-link p-0 sf-text-link"
                                data-sellers-open="1"
                                data-group="<?= htmlspecialchars($it["product_group_key"]) ?>">
                          View other sellers
                        </button>
                      <?php else: ?>
                        <span class="sf-pkg-no-sellers">No other sellers</span>
                      <?php endif; ?>
                    </div>

                    <div class="sf-sellers-panel d-none" data-group="<?= htmlspecialchars($it["product_group_key"] ?? "") ?>">
                      <?php
                        $sellerChoices = [];
                        foreach ($sellers as $offer) {
                          if ((string)$offer["id"] === (string)$it["product_id"]) continue;
                          if ((string)($offer["vendor_user_id"] ?? "") === (string)($it["vendor_user_id"] ?? "")) continue;
                          $sellerChoices[] = $offer;
                        }
                      ?>
                      <?php if (empty($sellerChoices)): ?>
                        <div class="sf-empty-inline">No other sellers available for this product.</div>
                      <?php else: ?>
                        <div class="sf-seller-list">
                          <?php foreach ($sellerChoices as $offer): ?>
                            <form method="post" class="m-0">
                              <input type="hidden" name="replace_kitchen_item" value="1">
                              <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                              <input type="hidden" name="new_product_id" value="<?= htmlspecialchars($offer["id"]) ?>">
                              <button type="submit" class="sf-seller-card-btn">
                                <div class="sf-seller-card-top">
                                  <div class="sf-seller-name"><?= htmlspecialchars($offer["vendor_name"]) ?></div>
                                  <div class="sf-seller-price"><?= egp($offer["price"]) ?></div>
                                </div>
                              </button>
                            </form>
                          <?php endforeach; ?>
                        </div>
                      <?php endif; ?>
                    </div>

                  </article>
                <?php endforeach; ?>
              </div>
            <?php endif; ?>
          </div>

          <?php elseif ($activeModule === "furniture"): ?>
  <div class="sf-module-shell">
    
    <div class="sf-pkg-progress-wrap">
      <div class="sf-pkg-progress-row">
        <span class="sf-pkg-progress-label">Dining Area</span>
        <span class="sf-pkg-progress-used"><?= egp($furnitureTotal) ?> / <?= egp($furnitureCap) ?></span>
        <span class="sf-pkg-progress-remaining <?= $furnitureOver > 0 ? 'is-over' : '' ?>">
          <?php if($furnitureOver > 0): ?><?= egp($furnitureOver) ?> over cap<?php else: ?><?= egp($furnitureRemaining) ?> remaining<?php endif; ?>
        </span>
      </div>
      <div class="sf-pkg-progress-track">
        <div class="sf-pkg-progress-fill <?= $furnitureOver > 0 ? 'is-over' : '' ?>" style="width:<?= $furnitureCap > 0 ? min(100, round($furnitureTotal / $furnitureCap * 100)) : 0 ?>%"></div>
      </div>
    </div>

    <div class="sf-module-toolbar">
      <form method="post" class="m-0">
        <input type="hidden" name="recalc_furniture" value="1">
        <button class="btn sf-btn-light-main btn-sm">
          <i class="bi bi-stars"></i>
          Recalculate Auto Package
        </button>
      </form>
    </div>

    <?php if(!$furnitureCart): ?>
      <div class="alert alert-warning mt-3">No Dining Area cart yet.</div>
    <?php else: ?>
      <div class="sf-pkg-grid">
        <?php foreach($furnitureCart["items"] as $type=>$it): ?>
          <article class="sf-pkg-card" id="row_<?= htmlspecialchars($activeModule) ?>_<?= htmlspecialchars($type) ?>">

            <div class="sf-pkg-card-media">
              <?php if(!empty($it["image_url"])): ?>
                <img src="<?= htmlspecialchars($it["image_url"]) ?>" alt="">
              <?php else: ?>
                <div class="sf-pkg-card-fallback"><?= strtoupper(substr($type,0,2)) ?></div>
              <?php endif; ?>
            </div>

            <div class="sf-pkg-card-body">
              <h3 class="sf-pkg-card-name"><?= htmlspecialchars($it["name"]) ?></h3>
              <div class="sf-pkg-card-meta">
                <?php if(!empty($it["brand"])): ?><span><?= htmlspecialchars($it["brand"]) ?></span><?php endif; ?>
                <?php if(!empty($it["vendor_name"])): ?><span><?= htmlspecialchars($it["vendor_name"]) ?></span><?php endif; ?>
              </div>
              <div class="sf-pkg-card-price"><?= egp($it["unit"]) ?></div>
            </div>

            <div class="sf-pkg-card-footer">
              <div class="sf-pkg-qty">
                <form method="post" class="m-0">
                  <input type="hidden" name="update_furniture_qty" value="1">
                  <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                  <input type="hidden" name="delta" value="-1">
                  <button class="sf-pkg-qty-btn">−</button>
                </form>
                <span class="sf-pkg-qty-val"><?= (int)$it["qty"] ?></span>
                <form method="post" class="m-0">
                  <input type="hidden" name="update_furniture_qty" value="1">
                  <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                  <input type="hidden" name="delta" value="1">
                  <button class="sf-pkg-qty-btn">+</button>
                </form>
              </div>
            </div>

            <div class="sf-pkg-alts">
              <?php if (!empty($it["alternatives"])): ?>
                <?php foreach (array_slice($it["alternatives"], 0, 3) as $alt): ?>
                  <form method="post" class="sf-pkg-chip m-0">
                    <input type="hidden" name="replace_furniture_item" value="1">
                    <input type="hidden" name="type" value="<?= htmlspecialchars($type) ?>">
                    <input type="hidden" name="new_product_id" value="<?= htmlspecialchars($alt["id"]) ?>">
                    <button type="submit" class="sf-pkg-chip-btn">
                      <div class="sf-pkg-chip-thumb">
                        <?php if(!empty($alt["image_url"])): ?>
                          <img src="<?= htmlspecialchars($alt["image_url"]) ?>" class="sf-pkg-chip-img" alt=""
                               onerror="this.style.display='none'">
                        <?php else: ?>
                          <div class="sf-pkg-chip-fallback"><?= strtoupper(substr($type,0,2)) ?></div>
                        <?php endif; ?>
                      </div>
                      <span class="sf-pkg-chip-name"><?= htmlspecialchars($alt["name"]) ?></span>
                      <span class="sf-pkg-chip-price"><?= egp($alt["price"]) ?></span>
                    </button>
                  </form>
                <?php endforeach; ?>
              <?php else: ?>
                <div class="sf-pkg-no-alts">No alternatives available</div>
              <?php endif; ?>
            </div>

            <div class="sf-pkg-sellers-trigger">
              <?php if (!empty($it["product_group_key"])): ?>
                <button type="button" class="btn btn-link p-0 sf-text-link"
                        data-sellers-open="1"
                        data-group="<?= htmlspecialchars($it["product_group_key"]) ?>">
                  View other sellers
                </button>
              <?php else: ?>
                <span class="sf-pkg-no-sellers">No other sellers</span>
              <?php endif; ?>
            </div>

            <div class="sf-sellers-panel d-none" data-group="<?= htmlspecialchars($it["product_group_key"] ?? "") ?>">
              <div class="sf-empty-inline">No other sellers available for this product.</div>
            </div>

          </article>
        <?php endforeach; ?>
      </div>
    <?php endif; ?>
  </div>


          
        <?php else: ?>
          <div class="sf-module-shell">
            <div class="sf-module-shell-head">
              <div>
                <div class="sf-module-shell-kicker">Module</div>
                <h2 class="sf-module-shell-title"><?= htmlspecialchars($labels[$activeModule] ?? $activeModule) ?></h2>
                <div class="sf-module-shell-sub">This section is reserved for the next package builder step.</div>
              </div>

            </div>
            <div class="sf-pkg-progress-wrap">
              <div class="sf-pkg-progress-row">
                <span class="sf-pkg-progress-label"><?= htmlspecialchars($labels[$activeModule] ?? $activeModule) ?></span>
                <span class="sf-pkg-progress-used">0 EGP / <?= egp($alloc[$activeModule] ?? 0) ?></span>
                <span class="sf-pkg-progress-remaining"><?= egp($alloc[$activeModule] ?? 0) ?> remaining</span>
              </div>
              <div class="sf-pkg-progress-track">
                <div class="sf-pkg-progress-fill" style="width:0%"></div>
              </div>
            </div>

            <div class="sf-empty-module-box">
              This module is empty for now. We’ll build it next like POS/Kitchen
              with an auto-generated editable cart.
            </div>
          </div>
        <?php endif; ?>

    </div><!-- /.sf-pkg-content -->
  </div><!-- /.container.sf-pkg-body -->

  <div class="sf-pkg-bottom-bar">
    <div class="container">
      <div class="sf-pkg-bottom-inner">
        <div class="sf-pkg-bottom-total">
          <span>Grand Total</span>
          <strong><?= egp($grandTotal) ?></strong>
        </div>
        <?php if($hasAnyItems): ?>
          <a href="order_summary.php" class="sf-pkg-review-btn">
            Review Full Order <i class="bi bi-arrow-right"></i>
          </a>
        <?php else: ?>
          <button class="sf-pkg-review-btn" disabled>
            Review Full Order <i class="bi bi-arrow-right"></i>
          </button>
        <?php endif; ?>
      </div>
    </div>
  </div>

</main>

<footer class="sf-footer mt-5">
  <div class="container py-5">
    <div class="row g-4">
      <div class="col-lg-4">
        <div class="d-flex align-items-center gap-3 mb-3">
          <div class="sf-footer-logo">
            <img src="assets/images/Logo.png" alt="SetupForge Logo">
          </div>
          <h5 class="mb-0 text-white fw-bold">SetupForge</h5>
        </div>
        <p class="sf-footer-text">
          SetupForge helps entrepreneurs launch, furnish, and fully prepare their businesses.
          From equipment sourcing to installation and optimization — we handle it all.
        </p>
        <div class="sf-socials mt-3">
          <a href="#">Facebook</a>
          <a href="#">Instagram</a>
          <a href="#">LinkedIn</a>
        </div>
      </div>

      <div class="col-6 col-lg-2">
        <h6 class="sf-footer-title">Products</h6>
        <ul class="sf-footer-links">
          <li><a href="#">Kitchen Equipment</a></li>
          <li><a href="#">Furniture</a></li>
          <li><a href="#">POS Systems</a></li>
          <li><a href="#">Security Systems</a></li>
          <li><a href="#">Packaging</a></li>
        </ul>
      </div>

      <div class="col-6 col-lg-2">
        <h6 class="sf-footer-title">Services</h6>
        <ul class="sf-footer-links">
          <li><a href="#">Installation</a></li>
          <li><a href="#">Interior Design</a></li>
          <li><a href="#">Branding</a></li>
          <li><a href="#">Consultation</a></li>
          <li><a href="#">Maintenance</a></li>
        </ul>
      </div>

      <div class="col-6 col-lg-2">
        <h6 class="sf-footer-title">Resources</h6>
        <ul class="sf-footer-links">
          <li><a href="help-center.php">Help Center</a></li>
          <li><a href="faq.php">FAQ</a></li>
          <li><a href="about.php">About Us</a></li>
          <li><a href="#">Blog</a></li>
          <li><a href="#">Guides</a></li>
        </ul>
      </div>

      <div class="col-12 col-lg-2">
        <h6 class="sf-footer-title">Stay Updated</h6>
        <p class="sf-footer-text small">
          Get updates, product releases, and startup tips.
        </p>
        <form>
          <input type="email" class="sf-footer-input mb-2" placeholder="Your email">
          <button type="submit" class="btn btn-light w-100 btn-sm fw-semibold">
            Subscribe
          </button>
        </form>
      </div>

    </div>
  </div>

  <div class="sf-footer-bottom">
    <div class="container d-flex justify-content-between flex-wrap gap-2">
      <span>© 2026 SetupForge. All rights reserved.</span>
      <div>
        <a href="#">Privacy Policy</a>
        <a href="#" class="ms-3">Terms</a>
      </div>
    </div>
  </div>
</footer>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/site.js"></script>

<script>
  function setupDualRange(minEl, maxEl, minOut, maxOut){
    if(!minEl || !maxEl) return;

    const minBound = parseInt(minEl.min || "0", 10);
    const maxBound = parseInt(maxEl.max || "0", 10);

    function fmt(n){
      n = parseInt(n || "0", 10);
      return n.toLocaleString("en-US") + " EGP";
    }

    function sync(){
      let a = parseInt(minEl.value, 10);
      let b = parseInt(maxEl.value, 10);
      if (a < minBound) a = minBound;
      if (b > maxBound) b = maxBound;
      if (b < a) b = a;
      minEl.value = a;
      maxEl.value = b;
      if(minOut) minOut.textContent = fmt(a);
      if(maxOut) maxOut.textContent = fmt(b);
    }

    minEl.addEventListener("input", sync);
    maxEl.addEventListener("input", sync);
    sync();
  }

  document.querySelectorAll("input[type='range'][id^='minRange_']").forEach(function(minEl){
    const base = minEl.id.replace("minRange_", "");
    const maxEl = document.getElementById("maxRange_" + base);
    const minOut = document.getElementById("minOut_" + base);
    const maxOut = document.getElementById("maxOut_" + base);
    setupDualRange(minEl, maxEl, minOut, maxOut);
  });

  function closeAllSellerPanels() {
  document.querySelectorAll(".sf-sellers-panel").forEach(function(panel){
    panel.classList.add("d-none");
  });
}

function toggleSellerPanel(group) {
  if (!group) return;

  const panel = document.querySelector('.sf-sellers-panel[data-group="' + group + '"]');
  if (!panel) return;

  const wasHidden = panel.classList.contains("d-none");
  closeAllSellerPanels();

  if (wasHidden) {
    panel.classList.remove("d-none");
    panel.scrollIntoView({ behavior: "smooth", block: "nearest" });
  }
}
  document.addEventListener("click", function(e){
    

    const sellersBtn = e.target.closest("[data-sellers-open='1']");
    if (sellersBtn) {
      e.preventDefault();
      const group = sellersBtn.getAttribute("data-group");
      toggleSellerPanel(group);
      return;
    }
  });

  (function(){
    const url = new URL(window.location.href);

    const anchor = url.searchParams.get("anchor") || "";
    if (anchor) {
      const el = document.getElementById(anchor);
      if (el) el.scrollIntoView({ behavior: "smooth", block: "start" });
    }

    const panel = url.searchParams.get("panel") || "";
    const type  = url.searchParams.get("type") || "";
    const group = url.searchParams.get("group") || "";

    const openSellers = url.searchParams.get("open_sellers") === "1";


    if ((panel === "sellers" && group) || (openSellers && group)) {
      setTimeout(function(){ toggleSellerPanel(group); }, 120);
    }
  })();
</script>


</body>
</html>
