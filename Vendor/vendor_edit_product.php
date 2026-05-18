<?php
// vendor_edit_product.php
session_start();
require "../db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? "") !== "vendor") {
  header("Location: ../auth/login.php");
  exit;
}

$vendorId  = (int)$_SESSION["user_id"];
$productId = isset($_GET["id"]) ? (int)$_GET["id"] : 0;
if ($productId <= 0) die("Invalid product id.");

$successMsg = "";
$errorMsg   = "";

// Modules must match packages.php exactly
$moduleOptions       = ["pos", "kitchen", "furniture", "ac"];
$businessTypeOptions = ["restaurant", "cafe", "gym", "salon"];

$productTypesByModule = [
  "pos"       => ["terminal", "printer", "drawer", "software", "kds", "scanner", "tablet"],
  "kitchen"   => ["oven", "stove", "fryer", "grill", "microwave", "fridge", "freezer", "blender", "mixer", "coffee"],
  "furniture" => ["dining_set", "tv", "table", "chair", "sofa", "bar_stool", "outdoor_furniture", "reception_desk", "shelving", "speaker", "light"],
  "ac"        => ["ac", "exhaust_fan", "air_curtain"],
];

// CRITICAL: spec field names must match what packages.php reads from specs JSON
// packages.php reads: dining_set → specs.seat_count | tv → specs.screen_size | ac → specs.hp
$specsSchemas = [
  // ── POS ──────────────────────────────────────────────────────────────────
  "terminal" => [
    ["name"=>"screen_size",     "label"=>"Screen Size",       "type"=>"number", "unit"=>"inch", "placeholder"=>"e.g. 15"],
    ["name"=>"connectivity",    "label"=>"Connectivity",      "type"=>"select", "options"=>["WiFi","Ethernet","WiFi + Ethernet","4G"]],
    ["name"=>"os",              "label"=>"Operating System",  "type"=>"select", "options"=>["Android","Windows","Linux","Proprietary"]],
    ["name"=>"receipt_printer", "label"=>"Built-in Printer",  "type"=>"select", "options"=>["Yes","No"]],
    ["name"=>"warranty",        "label"=>"Warranty",          "type"=>"select", "options"=>["6 months","1 year","2 years","3 years"]],
  ],
  "printer" => [
    ["name"=>"print_width",  "label"=>"Print Width",   "type"=>"select", "options"=>["58mm","80mm"]],
    ["name"=>"connectivity", "label"=>"Connectivity",  "type"=>"select", "options"=>["USB","Bluetooth","WiFi","Ethernet"]],
    ["name"=>"print_speed",  "label"=>"Print Speed",   "type"=>"number", "unit"=>"mm/s", "placeholder"=>"e.g. 200"],
    ["name"=>"warranty",     "label"=>"Warranty",      "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "drawer" => [
    ["name"=>"size",         "label"=>"Size",          "type"=>"select", "options"=>["Small (16\")","Medium (24\")","Large (32\")"]],
    ["name"=>"connectivity", "label"=>"Connectivity",  "type"=>"select", "options"=>["RJ11 (via printer)","USB","Manual"]],
    ["name"=>"bill_slots",   "label"=>"Bill Slots",    "type"=>"number", "placeholder"=>"e.g. 5"],
    ["name"=>"coin_slots",   "label"=>"Coin Slots",    "type"=>"number", "placeholder"=>"e.g. 8"],
  ],
  "software" => [
    ["name"=>"license_type", "label"=>"License Type",  "type"=>"select", "options"=>["Monthly","Yearly","Lifetime","Per Device"]],
    ["name"=>"cloud_based",  "label"=>"Cloud Based",   "type"=>"select", "options"=>["Yes","No"]],
    ["name"=>"offline_mode", "label"=>"Offline Mode",  "type"=>"select", "options"=>["Yes","No"]],
    ["name"=>"max_devices",  "label"=>"Max Devices",   "type"=>"number", "placeholder"=>"e.g. 3"],
  ],
  "kds" => [
    ["name"=>"screen_size",  "label"=>"Screen Size",   "type"=>"number", "unit"=>"inch", "placeholder"=>"e.g. 17"],
    ["name"=>"display_type", "label"=>"Display Type",  "type"=>"select", "options"=>["LCD","LED","Touchscreen"]],
    ["name"=>"connectivity", "label"=>"Connectivity",  "type"=>"select", "options"=>["WiFi","Ethernet","WiFi + Ethernet"]],
    ["name"=>"warranty",     "label"=>"Warranty",      "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "scanner" => [
    ["name"=>"scan_type",    "label"=>"Scan Type",     "type"=>"select", "options"=>["1D Barcode","2D / QR","1D + 2D"]],
    ["name"=>"connectivity", "label"=>"Connectivity",  "type"=>"select", "options"=>["USB","Bluetooth","Wireless"]],
    ["name"=>"warranty",     "label"=>"Warranty",      "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "tablet" => [
    ["name"=>"screen_size",  "label"=>"Screen Size",    "type"=>"number", "unit"=>"inch", "placeholder"=>"e.g. 10"],
    ["name"=>"os",           "label"=>"Operating System","type"=>"select","options"=>["Android","iOS","Windows"]],
    ["name"=>"ram",          "label"=>"RAM",             "type"=>"select", "options"=>["2GB","3GB","4GB","6GB","8GB"]],
    ["name"=>"storage",      "label"=>"Storage",         "type"=>"select", "options"=>["32GB","64GB","128GB","256GB"]],
    ["name"=>"connectivity", "label"=>"Connectivity",    "type"=>"select", "options"=>["WiFi","WiFi + 4G"]],
    ["name"=>"warranty",     "label"=>"Warranty",        "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  // ── Kitchen ──────────────────────────────────────────────────────────────
  "oven" => [
    ["name"=>"oven_type",       "label"=>"Oven Type",         "type"=>"select", "options"=>["Combi","Convection","Deck","Conveyor","Pizza"]],
    ["name"=>"fuel_type",       "label"=>"Fuel Type",         "type"=>"select", "options"=>["Electric","Gas","Dual"]],
    ["name"=>"capacity_trays",  "label"=>"Capacity (Trays)",  "type"=>"number", "placeholder"=>"e.g. 6"],
    ["name"=>"power_kw",        "label"=>"Power",             "type"=>"number", "unit"=>"kW", "placeholder"=>"e.g. 12"],
    ["name"=>"dimensions",      "label"=>"Dimensions (WxDxH)","type"=>"text",   "placeholder"=>"e.g. 80x80x60 cm"],
    ["name"=>"warranty",        "label"=>"Warranty",          "type"=>"select", "options"=>["6 months","1 year","2 years","3 years"]],
  ],
  "stove" => [
    ["name"=>"stove_type",  "label"=>"Stove Type",  "type"=>"select", "options"=>["Gas","Electric","Induction","Commercial Range"]],
    ["name"=>"burners",     "label"=>"Burners",     "type"=>"select", "options"=>["2","4","6","8"]],
    ["name"=>"fuel_type",   "label"=>"Fuel Type",   "type"=>"select", "options"=>["Gas","Electric"]],
    ["name"=>"power_kw",    "label"=>"Power",       "type"=>"number", "unit"=>"kW", "placeholder"=>"e.g. 10"],
    ["name"=>"dimensions",  "label"=>"Dimensions",  "type"=>"text",   "placeholder"=>"e.g. 90x70x85 cm"],
    ["name"=>"warranty",    "label"=>"Warranty",    "type"=>"select", "options"=>["6 months","1 year","2 years","3 years"]],
  ],
  "fryer" => [
    ["name"=>"fryer_type",      "label"=>"Fryer Type",    "type"=>"select", "options"=>["Single Tank","Double Tank","Countertop","Floor"]],
    ["name"=>"fuel_type",       "label"=>"Fuel Type",     "type"=>"select", "options"=>["Electric","Gas"]],
    ["name"=>"capacity_liters", "label"=>"Oil Capacity",  "type"=>"number", "unit"=>"L", "placeholder"=>"e.g. 8"],
    ["name"=>"power_kw",        "label"=>"Power",         "type"=>"number", "unit"=>"kW","placeholder"=>"e.g. 6"],
    ["name"=>"warranty",        "label"=>"Warranty",      "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "grill" => [
    ["name"=>"grill_type",  "label"=>"Grill Type",  "type"=>"select", "options"=>["Flat Top / Griddle","Char Grill","Contact Grill","Salamander"]],
    ["name"=>"fuel_type",   "label"=>"Fuel Type",   "type"=>"select", "options"=>["Electric","Gas"]],
    ["name"=>"surface_cm",  "label"=>"Surface Area","type"=>"text",   "placeholder"=>"e.g. 60x40 cm"],
    ["name"=>"power_kw",    "label"=>"Power",       "type"=>"number", "unit"=>"kW", "placeholder"=>"e.g. 4"],
    ["name"=>"warranty",    "label"=>"Warranty",    "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "microwave" => [
    ["name"=>"power_watts",     "label"=>"Power",      "type"=>"number", "unit"=>"W",  "placeholder"=>"e.g. 1800"],
    ["name"=>"capacity_liters", "label"=>"Capacity",   "type"=>"number", "unit"=>"L",  "placeholder"=>"e.g. 25"],
    ["name"=>"warranty",        "label"=>"Warranty",   "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "fridge" => [
    ["name"=>"fridge_type",     "label"=>"Fridge Type",   "type"=>"select", "options"=>["Upright","Under-counter","Prep Table","Display","Walk-in"]],
    ["name"=>"capacity_liters", "label"=>"Capacity",      "type"=>"number", "unit"=>"L", "placeholder"=>"e.g. 400"],
    ["name"=>"doors",           "label"=>"No. of Doors",  "type"=>"select", "options"=>["1","2","3","4"]],
    ["name"=>"temp_range",      "label"=>"Temp Range",    "type"=>"text",   "placeholder"=>"e.g. 2°C to 8°C"],
    ["name"=>"warranty",        "label"=>"Warranty",      "type"=>"select", "options"=>["6 months","1 year","2 years","3 years"]],
  ],
  "freezer" => [
    ["name"=>"freezer_type",    "label"=>"Freezer Type",  "type"=>"select", "options"=>["Chest","Upright","Under-counter","Walk-in"]],
    ["name"=>"capacity_liters", "label"=>"Capacity",      "type"=>"number", "unit"=>"L", "placeholder"=>"e.g. 300"],
    ["name"=>"temp_range",      "label"=>"Temp Range",    "type"=>"text",   "placeholder"=>"e.g. -18°C to -22°C"],
    ["name"=>"warranty",        "label"=>"Warranty",      "type"=>"select", "options"=>["6 months","1 year","2 years","3 years"]],
  ],
  "blender" => [
    ["name"=>"power_watts",     "label"=>"Power",          "type"=>"number", "unit"=>"W", "placeholder"=>"e.g. 1500"],
    ["name"=>"capacity_liters", "label"=>"Jug Capacity",   "type"=>"number", "unit"=>"L", "placeholder"=>"e.g. 2"],
    ["name"=>"speeds",          "label"=>"Speed Settings", "type"=>"number", "placeholder"=>"e.g. 10"],
    ["name"=>"warranty",        "label"=>"Warranty",       "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "mixer" => [
    ["name"=>"mixer_type",      "label"=>"Mixer Type",    "type"=>"select", "options"=>["Stand","Planetary","Spiral","Hand"]],
    ["name"=>"capacity_liters", "label"=>"Bowl Capacity", "type"=>"number", "unit"=>"L", "placeholder"=>"e.g. 7"],
    ["name"=>"power_watts",     "label"=>"Power",         "type"=>"number", "unit"=>"W", "placeholder"=>"e.g. 800"],
    ["name"=>"speeds",          "label"=>"Speed Settings","type"=>"number", "placeholder"=>"e.g. 6"],
    ["name"=>"warranty",        "label"=>"Warranty",      "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "coffee" => [
    ["name"=>"machine_type", "label"=>"Machine Type",  "type"=>"select", "options"=>["Espresso","Filter","Pod","Bean-to-Cup","Cold Brew"]],
    ["name"=>"group_heads",  "label"=>"Group Heads",   "type"=>"select", "options"=>["1","2","3","4"]],
    ["name"=>"boiler_type",  "label"=>"Boiler Type",   "type"=>"select", "options"=>["Single","Dual","Multi"]],
    ["name"=>"steam_wand",   "label"=>"Steam Wand",    "type"=>"select", "options"=>["Yes","No"]],
    ["name"=>"warranty",     "label"=>"Warranty",      "type"=>"select", "options"=>["6 months","1 year","2 years","3 years"]],
  ],
  // ── Furniture ────────────────────────────────────────────────────────────
  "dining_set" => [
    ["name"=>"seat_count",      "label"=>"Seats Included",    "type"=>"number", "placeholder"=>"e.g. 4"],
    ["name"=>"layout_type",     "label"=>"Layout Type",       "type"=>"select", "options"=>["standard","premium"]],
    ["name"=>"restaurant_style","label"=>"Restaurant Style",  "type"=>"select", "options"=>["fast_food","standard_dining","premium_dining"]],
    ["name"=>"material",        "label"=>"Material",          "type"=>"select", "options"=>["Wood","Metal","Plastic","Mixed","Rattan"]],
    ["name"=>"color",           "label"=>"Color",             "type"=>"text",   "placeholder"=>"e.g. Walnut Brown"],
    ["name"=>"table_shape",     "label"=>"Table Shape",       "type"=>"select", "options"=>["Round","Square","Rectangle"]],
    ["name"=>"dimensions",      "label"=>"Table Dimensions",  "type"=>"text",   "placeholder"=>"e.g. 120x80 cm"],
    ["name"=>"warranty",        "label"=>"Warranty",          "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "tv" => [
    ["name"=>"screen_size",  "label"=>"Screen Size",   "type"=>"number", "unit"=>"inch", "placeholder"=>"e.g. 50"],
    ["name"=>"resolution",   "label"=>"Resolution",    "type"=>"select", "options"=>["HD","Full HD","4K","8K"]],
    ["name"=>"panel_type",   "label"=>"Panel Type",    "type"=>"select", "options"=>["LED","OLED","QLED","IPS"]],
    ["name"=>"smart",        "label"=>"Smart TV",      "type"=>"select", "options"=>["Yes","No"]],
    ["name"=>"hdmi_ports",   "label"=>"HDMI Ports",    "type"=>"number", "placeholder"=>"e.g. 3"],
    ["name"=>"warranty",     "label"=>"Warranty",      "type"=>"select", "options"=>["6 months","1 year","2 years","3 years"]],
  ],
  "table" => [
    ["name"=>"material",    "label"=>"Material",         "type"=>"select", "options"=>["Wood","Metal","Marble","Glass","Laminate"]],
    ["name"=>"shape",       "label"=>"Shape",            "type"=>"select", "options"=>["Round","Square","Rectangle"]],
    ["name"=>"dimensions",  "label"=>"Dimensions (WxL)", "type"=>"text",   "placeholder"=>"e.g. 70x70 cm"],
    ["name"=>"color",       "label"=>"Color",            "type"=>"text",   "placeholder"=>"e.g. Black"],
    ["name"=>"warranty",    "label"=>"Warranty",         "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "chair" => [
    ["name"=>"material",        "label"=>"Material",        "type"=>"select", "options"=>["Wood","Metal","Plastic","Upholstered","Rattan"]],
    ["name"=>"with_armrests",   "label"=>"Armrests",        "type"=>"select", "options"=>["Yes","No"]],
    ["name"=>"stackable",       "label"=>"Stackable",       "type"=>"select", "options"=>["Yes","No"]],
    ["name"=>"color",           "label"=>"Color",           "type"=>"text",   "placeholder"=>"e.g. Black"],
    ["name"=>"weight_capacity", "label"=>"Weight Capacity", "type"=>"number", "unit"=>"kg", "placeholder"=>"e.g. 120"],
    ["name"=>"warranty",        "label"=>"Warranty",        "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "sofa" => [
    ["name"=>"seats",    "label"=>"Seats",    "type"=>"select", "options"=>["1 seater","2 seater","3 seater","L-shape"]],
    ["name"=>"material", "label"=>"Material", "type"=>"select", "options"=>["Fabric","Leather","Velvet","Synthetic"]],
    ["name"=>"color",    "label"=>"Color",    "type"=>"text",   "placeholder"=>"e.g. Grey"],
    ["name"=>"warranty", "label"=>"Warranty", "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "bar_stool" => [
    ["name"=>"material",   "label"=>"Material",   "type"=>"select", "options"=>["Wood","Metal","Upholstered","Mixed"]],
    ["name"=>"with_back",  "label"=>"With Back",  "type"=>"select", "options"=>["Yes","No"]],
    ["name"=>"adjustable", "label"=>"Adjustable", "type"=>"select", "options"=>["Yes","No"]],
    ["name"=>"color",      "label"=>"Color",      "type"=>"text",   "placeholder"=>"e.g. Black"],
    ["name"=>"warranty",   "label"=>"Warranty",   "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "outdoor_furniture" => [
    ["name"=>"set_type",   "label"=>"Set Type",   "type"=>"select", "options"=>["Table + Chairs","Lounger","Bench","Umbrella Set"]],
    ["name"=>"material",   "label"=>"Material",   "type"=>"select", "options"=>["Aluminum","Rattan","Teak","Plastic","Steel"]],
    ["name"=>"waterproof", "label"=>"Waterproof", "type"=>"select", "options"=>["Yes","No"]],
    ["name"=>"color",      "label"=>"Color",      "type"=>"text",   "placeholder"=>"e.g. Beige"],
    ["name"=>"warranty",   "label"=>"Warranty",   "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "reception_desk" => [
    ["name"=>"material",   "label"=>"Material",   "type"=>"select", "options"=>["Wood","MDF","Marble Top","Metal Frame"]],
    ["name"=>"dimensions", "label"=>"Dimensions", "type"=>"text",   "placeholder"=>"e.g. 150x60x100 cm"],
    ["name"=>"color",      "label"=>"Color",      "type"=>"text",   "placeholder"=>"e.g. White"],
    ["name"=>"warranty",   "label"=>"Warranty",   "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "shelving" => [
    ["name"=>"material",   "label"=>"Material",    "type"=>"select", "options"=>["Wood","Metal","Glass","MDF"]],
    ["name"=>"shelves",    "label"=>"No. Shelves", "type"=>"number", "placeholder"=>"e.g. 4"],
    ["name"=>"dimensions", "label"=>"Dimensions",  "type"=>"text",   "placeholder"=>"e.g. 100x30x180 cm"],
    ["name"=>"color",      "label"=>"Color",       "type"=>"text",   "placeholder"=>"e.g. Oak"],
    ["name"=>"warranty",   "label"=>"Warranty",    "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "speaker" => [
    ["name"=>"speaker_type", "label"=>"Speaker Type", "type"=>"select", "options"=>["Ceiling","Wall-mount","Floor","PA System","Portable"]],
    ["name"=>"power_watts",  "label"=>"Power",        "type"=>"number", "unit"=>"W", "placeholder"=>"e.g. 60"],
    ["name"=>"connectivity", "label"=>"Connectivity", "type"=>"select", "options"=>["Wired","Bluetooth","WiFi","Wired + Bluetooth"]],
    ["name"=>"warranty",     "label"=>"Warranty",     "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "light" => [
    ["name"=>"light_type",  "label"=>"Light Type",  "type"=>"select", "options"=>["Pendant","Chandelier","Track","LED Strip","Wall Sconce","Recessed"]],
    ["name"=>"power_watts", "label"=>"Power",       "type"=>"number", "unit"=>"W", "placeholder"=>"e.g. 24"],
    ["name"=>"color_temp",  "label"=>"Color Temp",  "type"=>"select", "options"=>["Warm White (2700K)","Neutral White (4000K)","Cool White (6000K)"]],
    ["name"=>"dimmable",    "label"=>"Dimmable",    "type"=>"select", "options"=>["Yes","No"]],
    ["name"=>"warranty",    "label"=>"Warranty",    "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  // ── AC ───────────────────────────────────────────────────────────────────
  "ac" => [
    ["name"=>"hp",              "label"=>"Horsepower (HP)",   "type"=>"number", "unit"=>"HP", "placeholder"=>"e.g. 1.5"],
    ["name"=>"ac_type",         "label"=>"AC Type",           "type"=>"select", "options"=>["Split","Cassette","Ducted","Window","Portable"]],
    ["name"=>"capacity_btu",    "label"=>"Capacity",          "type"=>"number", "unit"=>"BTU","placeholder"=>"e.g. 18000"],
    ["name"=>"inverter",        "label"=>"Inverter",          "type"=>"select", "options"=>["Yes","No"]],
    ["name"=>"cooling_heating", "label"=>"Cooling + Heating", "type"=>"select", "options"=>["Cooling Only","Cooling + Heating"]],
    ["name"=>"warranty",        "label"=>"Warranty",          "type"=>"select", "options"=>["1 year","2 years","3 years","5 years"]],
  ],
  "exhaust_fan" => [
    ["name"=>"diameter_cm", "label"=>"Blade Diameter", "type"=>"number", "unit"=>"cm",   "placeholder"=>"e.g. 30"],
    ["name"=>"airflow_cmh", "label"=>"Airflow",        "type"=>"number", "unit"=>"m³/h", "placeholder"=>"e.g. 500"],
    ["name"=>"power_watts", "label"=>"Power",          "type"=>"number", "unit"=>"W",    "placeholder"=>"e.g. 45"],
    ["name"=>"warranty",    "label"=>"Warranty",       "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "air_curtain" => [
    ["name"=>"width_cm",    "label"=>"Width",      "type"=>"number", "unit"=>"cm",  "placeholder"=>"e.g. 100"],
    ["name"=>"airflow_ms",  "label"=>"Air Speed",  "type"=>"number", "unit"=>"m/s", "placeholder"=>"e.g. 8"],
    ["name"=>"power_watts", "label"=>"Power",      "type"=>"number", "unit"=>"W",   "placeholder"=>"e.g. 800"],
    ["name"=>"warranty",    "label"=>"Warranty",   "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
];

// ---------- Fetch categories ----------
$catsQuery = pg_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
$cats = $catsQuery ? (pg_fetch_all($catsQuery) ?: []) : [];

// ---------- Fetch product ----------
$productQuery = pg_query_params(
  $conn,
  "SELECT id, product_name, category_id, brand, price, stock_quantity,
          module, tier, product_type, business_type, product_group_key, specs
   FROM products WHERE id = $1 AND vendor_user_id = $2 LIMIT 1",
  [$productId, $vendorId]
);
if (!$productQuery) die("Error fetching product.");
$product = pg_fetch_assoc($productQuery);
if (!$product) { http_response_code(403); die("Not authorized or product not found."); }

$existingSpecs = [];
if (!empty($product["specs"])) {
  $d = json_decode($product["specs"], true);
  if (is_array($d)) $existingSpecs = $d;
}
$existingBusinessTypes = $product["business_type"]
  ? array_map('trim', explode(",", $product["business_type"]))
  : [];

// ---------- Fetch existing images ----------
function fetch_product_images($conn, $productId) {
  $r = pg_query_params($conn, "SELECT id, image_url FROM product_images WHERE product_id = $1 ORDER BY id ASC", [$productId]);
  return $r ? (pg_fetch_all($r) ?: []) : [];
}
$existingImages = fetch_product_images($conn, $productId);

// ---------- Handle POST ----------
if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $action = $_POST["_action"] ?? "update";

  // ── Delete a single image ──────────────────────────────────────────────
  if ($action === "delete_image") {
    $imgId = (int)($_POST["image_id"] ?? 0);
    if ($imgId > 0) {
      // Fetch url to delete physical file
      $imgRow = pg_fetch_assoc(pg_query_params($conn,
        "SELECT image_url FROM product_images WHERE id=$1 AND product_id=$2",
        [$imgId, $productId]
      ));
      if ($imgRow) {
        $physPath = dirname(__DIR__) . "/" . $imgRow["image_url"];
        if (file_exists($physPath)) @unlink($physPath);
        pg_query_params($conn, "DELETE FROM product_images WHERE id=$1 AND product_id=$2", [$imgId, $productId]);
      }
    }
    header("Location: vendor_edit_product.php?id={$productId}&success=" . urlencode("Image deleted."));
    exit;
  }

  // ── Upload new images ─────────────────────────────────────────────────
  if ($action === "upload_images") {
    $currentCount = count($existingImages);
    $allowed = max(0, 8 - $currentCount);

    if ($allowed > 0 && !empty($_FILES["new_images"]["name"])) {
      $baseDir = dirname(__DIR__) . "/Vendor/uploads/products/vendor_{$vendorId}/product_{$productId}/";
      if (!is_dir($baseDir)) mkdir($baseDir, 0777, true);
      $uploaded = 0;

      for ($i = 0; $i < min(count($_FILES["new_images"]["name"]), $allowed); $i++) {
        if (($_FILES["new_images"]["error"][$i] ?? UPLOAD_ERR_NO_FILE) !== UPLOAD_ERR_OK) continue;
        $tmp  = $_FILES["new_images"]["tmp_name"][$i];
        $name = $_FILES["new_images"]["name"][$i] ?? "img";
        $size = (int)($_FILES["new_images"]["size"][$i] ?? 0);
        if ($size <= 0) continue;
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));
        if (!in_array($ext, ["jpg","jpeg","png","webp"], true)) continue;
        $safeFile  = "img_" . time() . "_" . $i . "." . $ext;
        if (!move_uploaded_file($tmp, $baseDir . $safeFile)) continue;
        $publicUrl = "Vendor/uploads/products/vendor_{$vendorId}/product_{$productId}/{$safeFile}";
        pg_query_params($conn, "INSERT INTO product_images (product_id, image_url) VALUES ($1,$2)", [$productId, $publicUrl]);
        $uploaded++;
      }
    }
    header("Location: vendor_edit_product.php?id={$productId}&success=" . urlencode("Images uploaded."));
    exit;
  }

  // ── Default: update product details ──────────────────────────────────
  $product_name   = trim($_POST["product_name"] ?? "");
  $category_id    = (int)($_POST["category"] ?? 0);
  $brand          = trim($_POST["brand"] ?? "");
  $price          = (float)($_POST["price"] ?? 0);
  $stock_quantity = (int)($_POST["stock_quantity"] ?? 0);
  $module         = trim($_POST["module"] ?? "");
  $product_type   = trim($_POST["product_type"] ?? "");

  function calculate_tier(string $pt, float $p): string {
    $t = [
      "terminal"=>[ 15000, 25000],"printer"=>[ 4000,  6000],"drawer"  =>[ 2800,  3300],
      "software"=>[ 9000, 14000],"kds"    =>[13000, 17000],"scanner" =>[ 3000,  4000],
      "tablet"  =>[14000, 18000],
      "oven"    =>[ 12000, 35000],"stove"  =>[10000, 30000],"fryer"   =>[ 8000, 20000],
      "grill"   =>[  6000, 18000],"microwave"=>[ 3000, 8000],
      "fridge"  =>[ 15000, 40000],"freezer"=>[15000, 40000],
      "blender" =>[   700,  3000],"mixer"  =>[ 4000, 12000],"coffee"  =>[ 8000, 25000],
      "dining_set"=>[ 8000, 20000],"table"=>[2000, 6000],"chair"=>[800, 2500],
      "tv"      =>[  5000, 15000],"sofa"=>[6000,18000],"bar_stool"=>[800,2500],
      "outdoor_furniture"=>[3000,10000],"reception_desk"=>[5000,15000],
      "shelving"=>[ 2000,  6000],"speaker"=>[2000, 8000],"light"=>[500, 2000],
      "ac"      =>[ 30000, 80000],"exhaust_fan"=>[2000,6000],"air_curtain"=>[3000,8000],
    ];
    [$sm, $bm] = $t[$pt] ?? [3000, 8000];
    if ($p < $sm)  return "Starter";
    if ($p <= $bm) return "Balanced";
    return "Premium";
  }
  $tier = calculate_tier($product_type, $price);

  $business_type_arr = $_POST["business_type"] ?? [];
  $business_type = !empty($business_type_arr) ? implode(",", array_map('trim', $business_type_arr)) : null;
  $product_group_key = trim($_POST["product_group_key"] ?? "") ?: null;

  $specs_raw = trim($_POST["specs"] ?? "");
  $specs = null;
  if ($specs_raw !== "") {
    json_decode($specs_raw, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
      $errorMsg = "Specs JSON is invalid.";
    } else {
      $specs = $specs_raw;
    }
  }

  if ($errorMsg === "" && ($product_name === "" || $category_id <= 0 || $brand === "" || $price <= 0 || $module === "" || $product_type === "")) {
    $errorMsg = "Please fill all required fields.";
  }

  if ($errorMsg === "") {
    $upd = pg_query_params($conn,
      "UPDATE products SET product_name=$1, category_id=$2, brand=$3, price=$4, stock_quantity=$5,
       module=$6, tier=$7, product_type=$8, business_type=$9, product_group_key=$10, specs=$11
       WHERE id=$12 AND vendor_user_id=$13",
      [$product_name, $category_id, $brand, $price, $stock_quantity,
       $module, $tier, $product_type, $business_type, $product_group_key, $specs,
       $productId, $vendorId]
    );
    if ($upd) {
      $successMsg = "Product updated successfully.";
      $r = pg_query_params($conn,
        "SELECT id, product_name, category_id, brand, price, stock_quantity,
                module, tier, product_type, business_type, product_group_key, specs
         FROM products WHERE id=$1 AND vendor_user_id=$2 LIMIT 1",
        [$productId, $vendorId]);
      if ($r) {
        $product = pg_fetch_assoc($r);
        $existingSpecs = [];
        if (!empty($product["specs"])) { $d = json_decode($product["specs"], true); if (is_array($d)) $existingSpecs = $d; }
        $existingBusinessTypes = $product["business_type"] ? array_map('trim', explode(",", $product["business_type"])) : [];
      }
    } else {
      $errorMsg = "Update failed: " . pg_last_error($conn);
    }
  }
}

// Re-fetch images after any action
$existingImages = fetch_product_images($conn, $productId);
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit Product #<?= $productId ?></title>
<link rel="stylesheet" href="vendor_ui.css?v=<?= time() ?>">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
<style>
  #specs-section {
    border: 1px solid rgba(255,255,255,0.12); border-radius: 10px;
    padding: 18px 20px 10px; margin-top: 4px; background: rgba(255,255,255,0.03);
  }
  #specs-section .specs-title {
    font-size: 0.78rem; font-weight: 700; letter-spacing: 0.08em;
    text-transform: uppercase; color: var(--sf-teal, #2dd4bf); margin-bottom: 14px;
  }
  .spec-field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  @media (max-width: 600px) { .spec-field-row { grid-template-columns: 1fr; } }
  .business-type-checks { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 6px; }
  .business-type-checks label { display: flex; align-items: center; gap: 6px; font-size: 0.85rem; color: #cbd5e1; cursor: pointer; }
  .tier-badge { display: inline-block; margin-top: 6px; font-size: 0.8rem; font-weight: 600;
    padding: 3px 12px; border-radius: 20px; background: rgba(45,212,191,0.15); color: #2dd4bf; }
  .spec-hint {
    font-size: 0.72rem; color: #f59e0b; margin-bottom: 12px;
    padding: 4px 10px; background: rgba(245,158,11,0.08); border-radius: 6px; border-left: 3px solid #f59e0b;
  }

  /* ── Image manager ─────────────────────────────────────────── */
  .img-manager { margin-top: 8px; }
  .img-grid {
    display: grid;
    grid-template-columns: repeat(auto-fill, minmax(110px, 1fr));
    gap: 12px;
    margin-bottom: 16px;
  }
  .img-thumb-wrap {
    position: relative; border-radius: 10px; overflow: hidden;
    background: rgba(255,255,255,0.05); border: 1px solid rgba(255,255,255,0.1);
    aspect-ratio: 1;
  }
  .img-thumb-wrap img {
    width: 100%; height: 100%; object-fit: cover; display: block;
  }
  .img-delete-btn {
    position: absolute; top: 4px; right: 4px;
    background: rgba(239,68,68,0.9); color: #fff; border: none;
    border-radius: 50%; width: 24px; height: 24px; font-size: 13px;
    cursor: pointer; display: flex; align-items: center; justify-content: center;
    line-height: 1;
  }
  .img-delete-btn:hover { background: #dc2626; }
  .img-count-note { font-size: 0.78rem; color: #94a3b8; margin-bottom: 10px; }
  .img-no-images { font-size: 0.85rem; color: #64748b; font-style: italic; margin-bottom: 12px; }
</style>
</head>
<body>
<nav class="navbar navbar-expand-lg navbar-dark sf-navbar">
  <div class="container d-flex align-items-center">
    <div class="d-flex align-items-center flex-grow-1">
      <a class="navbar-brand d-flex align-items-center gap-2" href="vendor_dashboard.php">
        <div class="sf-logo"><img src="../assets/images/Logo.png" alt="SetupForge Logo"></div>
        <span class="fw-bold text-white">SetupForge</span>
      </a>
    </div>
    <div class="d-none d-lg-flex justify-content-center flex-grow-1">
      <ul class="navbar-nav align-items-center gap-3">
        <li class="nav-item"><a class="nav-link sf-navlink" href="vendor_orders.php">Orders</a></li>
        <li class="nav-item"><a class="nav-link sf-navlink" href="vendor_products.php">My Products</a></li>
        <li class="nav-item"><a class="nav-link sf-navlink" href="vendor_add_product.php">Add Product</a></li>
      </ul>
    </div>
    <div class="d-flex justify-content-end flex-grow-1 gap-2">
      <a href="../auth/logout.php" class="btn btn-outline-light btn-sm px-3 fw-semibold">Logout</a>
    </div>
  </div>
</nav>

<div class="v-wrap">
<div class="v-section">

  <h3 class="v-section-title">Edit Product #<?= $productId ?></h3>
  <div class="v-section-desc">
    Current tier: <span class="tier-badge"><?= htmlspecialchars($product["tier"] ?? "—") ?></span>
    — tier updates automatically when you save with a new price.
  </div>

  <?php if (!empty($_GET["error"])): ?>
  <div class="v-alert v-alert-danger"><?= htmlspecialchars($_GET["error"]) ?></div>
  <?php endif; ?>
  <?php if (!empty($_GET["success"])): ?>
  <div class="v-alert v-alert-success"><?= htmlspecialchars($_GET["success"]) ?></div>
  <?php endif; ?>
  <?php if ($errorMsg): ?>
  <div class="v-alert v-alert-danger"><?= htmlspecialchars($errorMsg) ?></div>
  <?php endif; ?>
  <?php if ($successMsg): ?>
  <div class="v-alert v-alert-success"><?= htmlspecialchars($successMsg) ?></div>
  <?php endif; ?>

  <!-- ══════════════════════════════════════════════════════════
       SECTION 1: Product Images
  ══════════════════════════════════════════════════════════ -->
  <div class="v-field" style="margin-bottom: 28px;">
    <label class="v-label">Product Images</label>
    <div class="img-manager">

      <!-- Existing images grid -->
      <div class="img-count-note">
        <?= count($existingImages) ?> / 8 images uploaded
      </div>

      <?php if (empty($existingImages)): ?>
        <div class="img-no-images">No images uploaded yet.</div>
      <?php else: ?>
        <div class="img-grid">
          <?php foreach ($existingImages as $img): ?>
          <div class="img-thumb-wrap">
            <img src="<?= htmlspecialchars("../" . $img["image_url"]) ?>" alt="Product image">
            <form method="POST" style="display:contents;"
                  onsubmit="return confirm('Delete this image?');">
              <input type="hidden" name="_action" value="delete_image">
              <input type="hidden" name="image_id" value="<?= (int)$img["id"] ?>">
              <button type="submit" class="img-delete-btn" title="Delete image">×</button>
            </form>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>

      <!-- Upload new images (only shown if under limit) -->
      <?php if (count($existingImages) < 8): ?>
      <form method="POST" enctype="multipart/form-data" class="v-form" style="margin-top:0;">
        <input type="hidden" name="_action" value="upload_images">
        <div class="v-field" style="margin-bottom:0;">
          <label class="v-label" for="new_images">
            Add Images (up to <?= 8 - count($existingImages) ?> more)
          </label>
          <input class="v-input" id="new_images" type="file" name="new_images[]"
                 multiple accept="image/jpeg,image/png,image/webp">
          <div class="v-sub">JPG, PNG or WebP. Max 8 total.</div>
        </div>
        <div class="v-actions" style="margin-top:12px;">
          <button type="submit" class="v-btn v-btn-teal">Upload Images</button>
        </div>
      </form>
      <?php else: ?>
        <div class="v-sub" style="margin-top:4px;">Maximum of 8 images reached. Delete one to upload more.</div>
      <?php endif; ?>

    </div>
  </div>

  <hr style="border-color:rgba(255,255,255,0.08);margin:24px 0;">

  <!-- ══════════════════════════════════════════════════════════
       SECTION 2: Product Details
  ══════════════════════════════════════════════════════════ -->
  <form class="v-form" method="POST" id="editForm">
    <input type="hidden" name="_action" value="update">

    <!-- Product Name -->
    <div class="v-field">
      <label class="v-label" for="product_name">Product Name *</label>
      <input class="v-input" id="product_name" type="text" name="product_name"
             value="<?= htmlspecialchars($product["product_name"]) ?>" required>
    </div>

    <!-- Module / Product Type -->
    <div class="v-form-grid">
      <div class="v-field">
        <label class="v-label" for="module">Module *</label>
        <select class="v-select" id="module" name="module" required>
          <option value="" disabled>Select module</option>
          <?php foreach ($moduleOptions as $m): ?>
          <option value="<?= htmlspecialchars($m) ?>" <?= $product["module"] === $m ? "selected" : "" ?>>
            <?= htmlspecialchars(ucfirst($m)) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="v-field">
        <label class="v-label" for="product_type">Product Type *</label>
        <select class="v-select" id="product_type" name="product_type" required>
          <option value="" disabled>Select module first</option>
        </select>
      </div>
    </div>

    <!-- Category / Brand -->
    <div class="v-form-grid">
      <div class="v-field">
        <label class="v-label" for="category">Category *</label>
        <select class="v-select" id="category" name="category" required>
          <option value="" disabled>Select category</option>
          <?php foreach ($cats as $c): ?>
          <option value="<?= htmlspecialchars($c["id"]) ?>"
                  <?= (int)$product["category_id"] === (int)$c["id"] ? "selected" : "" ?>>
            <?= htmlspecialchars($c["name"]) ?>
          </option>
          <?php endforeach; ?>
        </select>
      </div>
      <div class="v-field">
        <label class="v-label" for="brand">Brand *</label>
        <input class="v-input" id="brand" type="text" name="brand"
               value="<?= htmlspecialchars($product["brand"]) ?>" required>
      </div>
    </div>

    <!-- Price / Stock -->
    <div class="v-form-grid">
      <div class="v-field">
        <label class="v-label" for="price">Price (EGP) *</label>
        <input class="v-input" id="price" type="number" step="0.01" min="0.01" name="price"
               value="<?= htmlspecialchars((string)$product["price"]) ?>" required>
      </div>
      <div class="v-field">
        <label class="v-label" for="stock_quantity">Stock Quantity *</label>
        <input class="v-input" id="stock_quantity" type="number" min="0" name="stock_quantity"
               value="<?= (int)$product["stock_quantity"] ?>" required>
      </div>
    </div>

    <!-- Business Type -->
    <div class="v-field">
      <label class="v-label">Business Type</label>
      <div class="business-type-checks">
        <?php foreach ($businessTypeOptions as $bt): ?>
        <label>
          <input type="checkbox" name="business_type[]" value="<?= htmlspecialchars($bt) ?>"
                 <?= in_array($bt, $existingBusinessTypes) ? "checked" : "" ?>>
          <?= htmlspecialchars(ucfirst($bt)) ?>
        </label>
        <?php endforeach; ?>
      </div>
    </div>

    <!-- Product Group Key -->
    <div class="v-field">
      <label class="v-label" for="product_group_key">Product Group Key</label>
      <input class="v-input" id="product_group_key" type="text" name="product_group_key"
             value="<?= htmlspecialchars($product["product_group_key"] ?? "") ?>">
      <div class="v-sub">Groups the same product from different vendors for "View other sellers".</div>
    </div>

    <!-- Dynamic Specs -->
    <div id="specs-section">
      <div class="specs-title">Product Specifications</div>
      <div id="specs-hint" class="spec-hint" style="display:none;"></div>
      <div id="specs-fields" class="spec-field-row"></div>
      <input type="hidden" name="specs" id="specs-hidden">
    </div>

    <div class="v-actions" style="margin-top:20px">
      <button class="v-btn v-btn-teal" type="submit">Save Changes</button>
      <a class="v-btn v-btn-outline" href="vendor_products.php">Back to Products</a>
    </div>

  </form>
</div>
</div>

<script>
(function(){
  const productTypesByModule = <?= json_encode($productTypesByModule) ?>;
  const specsSchemas         = <?= json_encode($specsSchemas) ?>;
  const existingSpecs        = <?= json_encode($existingSpecs) ?>;
  const savedModule          = <?= json_encode($product["module"] ?? "") ?>;
  const savedProductType     = <?= json_encode($product["product_type"] ?? "") ?>;

  const criticalHints = {
    "dining_set": "⚠ seat_count, layout_type and restaurant_style are required — the system uses these to match dining sets to restaurant types.",
    "tv":         "⚠ screen_size is required — the system uses this to recommend the right TV size for each venue.",
    "ac":         "⚠ hp (Horsepower) is required — the system uses this to match AC units to room size. Use: 1.5 for 1.5 ton, 2 for 2 ton, 2.5 for 2.5 ton, 3 for 3 ton.",
  };

  const moduleSelect      = document.getElementById("module");
  const productTypeSelect = document.getElementById("product_type");
  const specsFields       = document.getElementById("specs-fields");
  const specsHidden       = document.getElementById("specs-hidden");
  const specsSection      = document.getElementById("specs-section");
  const specsHint         = document.getElementById("specs-hint");

  function updateProductTypes(selectValue) {
    const types = productTypesByModule[moduleSelect.value] || [];
    productTypeSelect.innerHTML = types.length
      ? '<option value="" disabled>Select product type</option>'
      : '<option value="" disabled>No types for this module</option>';
    types.forEach(t => {
      const opt = document.createElement("option");
      opt.value = t;
      opt.textContent = t.replace(/_/g, " ").replace(/\b\w/g, c => c.toUpperCase());
      if (t === selectValue) opt.selected = true;
      productTypeSelect.appendChild(opt);
    });
  }

  // Pre-populate on load with saved values
  updateProductTypes(savedProductType);
  if (savedProductType) renderSpecsFields(savedProductType, existingSpecs);

  moduleSelect.addEventListener("change", () => {
    updateProductTypes(null);
    renderSpecsFields(null, {});
  });
  productTypeSelect.addEventListener("change", () => renderSpecsFields(productTypeSelect.value || null, {}));

  function renderSpecsFields(productType, prefill) {
    specsFields.innerHTML = "";
    specsHidden.value = "";

    if (!productType || !specsSchemas[productType]) {
      specsHint.style.display = "none";
      return;
    }

    if (criticalHints[productType]) {
      specsHint.textContent = criticalHints[productType];
      specsHint.style.display = "block";
    } else {
      specsHint.style.display = "none";
    }

    specsSchemas[productType].forEach(field => {
      const wrap = document.createElement("div");
      wrap.className = "v-field";
      const label = document.createElement("label");
      label.className = "v-label";
      label.setAttribute("for", "spec_" + field.name);
      label.textContent = field.label + (field.unit ? " (" + field.unit + ")" : "");
      wrap.appendChild(label);

      let input;
      if (field.type === "select") {
        input = document.createElement("select");
        input.className = "v-select";
        const blank = document.createElement("option");
        blank.value = ""; blank.textContent = "Select…"; blank.disabled = true;
        if (!prefill[field.name]) blank.selected = true;
        input.appendChild(blank);
        (field.options || []).forEach(opt => {
          const o = document.createElement("option"); o.value = opt; o.textContent = opt;
          if (String(prefill[field.name]) === String(opt)) o.selected = true;
          input.appendChild(o);
        });
      } else {
        input = document.createElement("input");
        input.className = "v-input";
        input.type = field.type === "number" ? "number" : "text";
        input.placeholder = field.placeholder || "";
        if (field.type === "number") { input.min = "0"; input.step = "any"; }
        if (prefill[field.name] !== undefined) input.value = prefill[field.name];
      }
      input.id = "spec_" + field.name;
      input.name = "spec_" + field.name;
      input.addEventListener("input", buildSpecsJson);
      input.addEventListener("change", buildSpecsJson);
      wrap.appendChild(input);
      specsFields.appendChild(wrap);
    });
    buildSpecsJson();
  }

  function buildSpecsJson() {
    const productType = productTypeSelect.value;
    if (!productType || !specsSchemas[productType]) { specsHidden.value = ""; return; }
    const obj = {};
    specsSchemas[productType].forEach(field => {
      const el = document.getElementById("spec_" + field.name);
      if (el && el.value.trim() !== "") {
        obj[field.name] = field.type === "number" ? parseFloat(el.value) : el.value.trim();
      }
    });
    specsHidden.value = Object.keys(obj).length ? JSON.stringify(obj) : "";
  }

  document.getElementById("editForm").addEventListener("submit", buildSpecsJson);
})();
</script>
</body>
</html>