<?php
// vendor_add_product.php
session_start();
require "../db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? "") !== "vendor") {
  header("Location: ../auth/login.php?error=" . urlencode("Please login as vendor."));
  exit;
}

$vendorId = (int)$_SESSION["user_id"];

$catsQuery = pg_query($conn, "SELECT id, name FROM categories ORDER BY name ASC");
$cats = [];
if ($catsQuery) {
  $cats = pg_fetch_all($catsQuery) ?: [];
}

// Modules must match packages.php exactly
$moduleOptions = ["pos", "kitchen", "furniture", "ac"];

$businessTypeOptions = ["restaurant", "cafe", "gym", "salon"];

// Product types per module — must match packages.php $KITCHEN_CATALOG_ACTIVE, $POS_CATALOG_ACTIVE etc.
$productTypesByModule = [
  "pos"       => ["terminal", "printer", "drawer", "software", "kds", "scanner", "tablet", "customer_display", "label_printer", "scale"],
  "kitchen"   => ["oven", "stove", "fryer", "grill", "microwave", "fridge", "freezer", "blender", "mixer", "coffee", "dishwasher", "prep_table", "exhaust_hood", "bain_marie", "slicer", "sink", "shelving", "food_processor", "rice_cooker", "meat_grinder", "ice_machine"],
  "furniture" => ["dining_set", "tv", "table", "chair", "sofa", "bar_stool", "outdoor_furniture", "reception_desk", "shelving", "speaker", "light", "sound_system", "curtain", "wall_decor", "menu_stand", "hostess_stand"],
  "ac"        => ["ac", "exhaust_fan", "air_curtain", "ceiling_fan"],
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
    ["name"=>"screen_size",  "label"=>"Screen Size",   "type"=>"number", "unit"=>"inch", "placeholder"=>"e.g. 10"],
    ["name"=>"os",           "label"=>"Operating System","type"=>"select","options"=>["Android","iOS","Windows"]],
    ["name"=>"ram",          "label"=>"RAM",            "type"=>"select", "options"=>["2GB","3GB","4GB","6GB","8GB"]],
    ["name"=>"storage",      "label"=>"Storage",        "type"=>"select", "options"=>["32GB","64GB","128GB","256GB"]],
    ["name"=>"connectivity", "label"=>"Connectivity",   "type"=>"select", "options"=>["WiFi","WiFi + 4G"]],
    ["name"=>"warranty",     "label"=>"Warranty",       "type"=>"select", "options"=>["6 months","1 year","2 years"]],
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
  // CRITICAL: packages.php uses specs.seat_count to match dining sets
  "dining_set" => [
    ["name"=>"seat_count",   "label"=>"Seats Included",   "type"=>"number", "placeholder"=>"e.g. 4"],
    ["name"=>"layout_type",  "label"=>"Layout Type",      "type"=>"select", "options"=>["standard","premium"]],
    ["name"=>"restaurant_style","label"=>"Restaurant Style","type"=>"select","options"=>["fast_food","standard_dining","premium_dining"]],
    ["name"=>"material",     "label"=>"Material",         "type"=>"select", "options"=>["Wood","Metal","Plastic","Mixed","Rattan"]],
    ["name"=>"color",        "label"=>"Color",            "type"=>"text",   "placeholder"=>"e.g. Walnut Brown"],
    ["name"=>"table_shape",  "label"=>"Table Shape",      "type"=>"select", "options"=>["Round","Square","Rectangle"]],
    ["name"=>"dimensions",   "label"=>"Table Dimensions", "type"=>"text",   "placeholder"=>"e.g. 120x80 cm"],
    ["name"=>"warranty",     "label"=>"Warranty",         "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  // CRITICAL: packages.php uses specs.screen_size to match TVs
  "tv" => [
    ["name"=>"screen_size",  "label"=>"Screen Size",   "type"=>"number", "unit"=>"inch", "placeholder"=>"e.g. 50"],
    ["name"=>"resolution",   "label"=>"Resolution",    "type"=>"select", "options"=>["HD","Full HD","4K","8K"]],
    ["name"=>"panel_type",   "label"=>"Panel Type",    "type"=>"select", "options"=>["LED","OLED","QLED","IPS"]],
    ["name"=>"smart",        "label"=>"Smart TV",      "type"=>"select", "options"=>["Yes","No"]],
    ["name"=>"hdmi_ports",   "label"=>"HDMI Ports",    "type"=>"number", "placeholder"=>"e.g. 3"],
    ["name"=>"warranty",     "label"=>"Warranty",      "type"=>"select", "options"=>["6 months","1 year","2 years","3 years"]],
  ],
  "table" => [
    ["name"=>"material",    "label"=>"Material",          "type"=>"select", "options"=>["Wood","Metal","Marble","Glass","Laminate"]],
    ["name"=>"shape",       "label"=>"Shape",             "type"=>"select", "options"=>["Round","Square","Rectangle"]],
    ["name"=>"dimensions",  "label"=>"Dimensions (WxL)",  "type"=>"text",   "placeholder"=>"e.g. 70x70 cm"],
    ["name"=>"color",       "label"=>"Color",             "type"=>"text",   "placeholder"=>"e.g. Black"],
    ["name"=>"warranty",    "label"=>"Warranty",          "type"=>"select", "options"=>["6 months","1 year","2 years"]],
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
    ["name"=>"seats",      "label"=>"Seats",      "type"=>"select", "options"=>["1 seater","2 seater","3 seater","L-shape"]],
    ["name"=>"material",   "label"=>"Material",   "type"=>"select", "options"=>["Fabric","Leather","Velvet","Synthetic"]],
    ["name"=>"color",      "label"=>"Color",      "type"=>"text",   "placeholder"=>"e.g. Grey"],
    ["name"=>"warranty",   "label"=>"Warranty",   "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "bar_stool" => [
    ["name"=>"material",    "label"=>"Material",     "type"=>"select", "options"=>["Wood","Metal","Upholstered","Mixed"]],
    ["name"=>"with_back",   "label"=>"With Back",    "type"=>"select", "options"=>["Yes","No"]],
    ["name"=>"adjustable",  "label"=>"Adjustable",   "type"=>"select", "options"=>["Yes","No"]],
    ["name"=>"color",       "label"=>"Color",        "type"=>"text",   "placeholder"=>"e.g. Black"],
    ["name"=>"warranty",    "label"=>"Warranty",     "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "outdoor_furniture" => [
    ["name"=>"set_type",   "label"=>"Set Type",    "type"=>"select", "options"=>["Table + Chairs","Lounger","Bench","Umbrella Set"]],
    ["name"=>"material",   "label"=>"Material",    "type"=>"select", "options"=>["Aluminum","Rattan","Teak","Plastic","Steel"]],
    ["name"=>"waterproof", "label"=>"Waterproof",  "type"=>"select", "options"=>["Yes","No"]],
    ["name"=>"color",      "label"=>"Color",       "type"=>"text",   "placeholder"=>"e.g. Beige"],
    ["name"=>"warranty",   "label"=>"Warranty",    "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "reception_desk" => [
    ["name"=>"material",    "label"=>"Material",    "type"=>"select", "options"=>["Wood","MDF","Marble Top","Metal Frame"]],
    ["name"=>"dimensions",  "label"=>"Dimensions",  "type"=>"text",   "placeholder"=>"e.g. 150x60x100 cm"],
    ["name"=>"color",       "label"=>"Color",       "type"=>"text",   "placeholder"=>"e.g. White"],
    ["name"=>"warranty",    "label"=>"Warranty",    "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "shelving" => [
    ["name"=>"material",    "label"=>"Material",    "type"=>"select", "options"=>["Wood","Metal","Glass","MDF"]],
    ["name"=>"shelves",     "label"=>"No. Shelves", "type"=>"number", "placeholder"=>"e.g. 4"],
    ["name"=>"dimensions",  "label"=>"Dimensions",  "type"=>"text",   "placeholder"=>"e.g. 100x30x180 cm"],
    ["name"=>"color",       "label"=>"Color",       "type"=>"text",   "placeholder"=>"e.g. Oak"],
    ["name"=>"warranty",    "label"=>"Warranty",    "type"=>"select", "options"=>["6 months","1 year","2 years"]],
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
  // CRITICAL: packages.php build_ac_cart_by_budget filters by specs.hp
  // HP map used in packages.php: 1.5t→1.5hp | 2t→2.0–2.25hp | 2.5t→2.5hp | 3t→3.0hp
  "ac" => [
    ["name"=>"hp",              "label"=>"Horsepower (HP)",   "type"=>"number", "unit"=>"HP", "placeholder"=>"e.g. 1.5"],
    ["name"=>"ac_type",         "label"=>"AC Type",           "type"=>"select", "options"=>["Split","Cassette","Ducted","Window","Portable"]],
    ["name"=>"capacity_btu",    "label"=>"Capacity",          "type"=>"number", "unit"=>"BTU","placeholder"=>"e.g. 18000"],
    ["name"=>"inverter",        "label"=>"Inverter",          "type"=>"select", "options"=>["Yes","No"]],
    ["name"=>"cooling_heating", "label"=>"Cooling + Heating", "type"=>"select", "options"=>["Cooling Only","Cooling + Heating"]],
    ["name"=>"warranty",        "label"=>"Warranty",          "type"=>"select", "options"=>["1 year","2 years","3 years","5 years"]],
  ],
  "exhaust_fan" => [
    ["name"=>"diameter_cm",  "label"=>"Blade Diameter",  "type"=>"number", "unit"=>"cm",  "placeholder"=>"e.g. 30"],
    ["name"=>"airflow_cmh",  "label"=>"Airflow",         "type"=>"number", "unit"=>"m³/h","placeholder"=>"e.g. 500"],
    ["name"=>"power_watts",  "label"=>"Power",           "type"=>"number", "unit"=>"W",   "placeholder"=>"e.g. 45"],
    ["name"=>"warranty",     "label"=>"Warranty",        "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "air_curtain" => [
    ["name"=>"width_cm",     "label"=>"Width",           "type"=>"number", "unit"=>"cm",  "placeholder"=>"e.g. 100"],
    ["name"=>"airflow_ms",   "label"=>"Air Speed",       "type"=>"number", "unit"=>"m/s", "placeholder"=>"e.g. 8"],
    ["name"=>"power_watts",  "label"=>"Power",           "type"=>"number", "unit"=>"W",   "placeholder"=>"e.g. 800"],
    ["name"=>"warranty",     "label"=>"Warranty",        "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  // ── AC extras ────────────────────────────────────────────────────────────
  "ceiling_fan" => [
    ["name"=>"blade_count",  "label"=>"Blade Count",  "type"=>"select", "options"=>["3","4","5"]],
    ["name"=>"diameter_cm",  "label"=>"Diameter",     "type"=>"number", "unit"=>"cm", "placeholder"=>"e.g. 120"],
    ["name"=>"power_watts",  "label"=>"Power",        "type"=>"number", "unit"=>"W",  "placeholder"=>"e.g. 60"],
    ["name"=>"with_light",   "label"=>"With Light",   "type"=>"select", "options"=>["Yes","No"]],
    ["name"=>"warranty",     "label"=>"Warranty",     "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  // ── Kitchen extras ───────────────────────────────────────────────────────
  "dishwasher" => [
    ["name"=>"dishwasher_type", "label"=>"Type",         "type"=>"select", "options"=>["Undercounter","Hood","Conveyor","Glasswasher"]],
    ["name"=>"capacity",        "label"=>"Capacity",     "type"=>"text",   "placeholder"=>"e.g. 40 racks/hr"],
    ["name"=>"power_kw",        "label"=>"Power",        "type"=>"number", "unit"=>"kW", "placeholder"=>"e.g. 3.2"],
    ["name"=>"warranty",        "label"=>"Warranty",     "type"=>"select", "options"=>["6 months","1 year","2 years","3 years"]],
  ],
  "prep_table" => [
    ["name"=>"material",    "label"=>"Material",     "type"=>"select", "options"=>["Stainless Steel","Polyethylene","Marble"]],
    ["name"=>"dimensions",  "label"=>"Dimensions",   "type"=>"text",   "placeholder"=>"e.g. 120x60x85 cm"],
    ["name"=>"with_shelf",  "label"=>"With Shelf",   "type"=>"select", "options"=>["Yes","No"]],
    ["name"=>"warranty",    "label"=>"Warranty",     "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "exhaust_hood" => [
    ["name"=>"hood_type",   "label"=>"Hood Type",    "type"=>"select", "options"=>["Wall-mount","Island","Canopy","Condensate"]],
    ["name"=>"width_cm",    "label"=>"Width",        "type"=>"number", "unit"=>"cm", "placeholder"=>"e.g. 120"],
    ["name"=>"airflow_cmh", "label"=>"Airflow",      "type"=>"number", "unit"=>"m³/h", "placeholder"=>"e.g. 1000"],
    ["name"=>"warranty",    "label"=>"Warranty",     "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "bain_marie" => [
    ["name"=>"bain_type",   "label"=>"Type",         "type"=>"select", "options"=>["Wet","Dry","Countertop","Drop-in"]],
    ["name"=>"sections",    "label"=>"Sections",     "type"=>"number", "placeholder"=>"e.g. 4"],
    ["name"=>"power_watts", "label"=>"Power",        "type"=>"number", "unit"=>"W", "placeholder"=>"e.g. 1500"],
    ["name"=>"warranty",    "label"=>"Warranty",     "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "slicer" => [
    ["name"=>"slicer_type", "label"=>"Slicer Type",  "type"=>"select", "options"=>["Meat","Bread","Vegetable","Multi-purpose"]],
    ["name"=>"blade_cm",    "label"=>"Blade Size",   "type"=>"number", "unit"=>"cm", "placeholder"=>"e.g. 30"],
    ["name"=>"power_watts", "label"=>"Power",        "type"=>"number", "unit"=>"W",  "placeholder"=>"e.g. 200"],
    ["name"=>"warranty",    "label"=>"Warranty",     "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "sink" => [
    ["name"=>"sink_type",   "label"=>"Sink Type",    "type"=>"select", "options"=>["Single","Double","Triple","Hand wash"]],
    ["name"=>"material",    "label"=>"Material",     "type"=>"select", "options"=>["Stainless Steel","Ceramic"]],
    ["name"=>"dimensions",  "label"=>"Dimensions",   "type"=>"text",   "placeholder"=>"e.g. 80x50 cm"],
    ["name"=>"warranty",    "label"=>"Warranty",     "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "food_processor" => [
    ["name"=>"capacity_liters", "label"=>"Bowl Capacity", "type"=>"number", "unit"=>"L", "placeholder"=>"e.g. 3"],
    ["name"=>"power_watts",     "label"=>"Power",         "type"=>"number", "unit"=>"W", "placeholder"=>"e.g. 750"],
    ["name"=>"speeds",          "label"=>"Speed Settings","type"=>"number", "placeholder"=>"e.g. 5"],
    ["name"=>"warranty",        "label"=>"Warranty",      "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "rice_cooker" => [
    ["name"=>"capacity_liters", "label"=>"Capacity",  "type"=>"number", "unit"=>"L",  "placeholder"=>"e.g. 10"],
    ["name"=>"power_watts",     "label"=>"Power",     "type"=>"number", "unit"=>"W",  "placeholder"=>"e.g. 1200"],
    ["name"=>"warranty",        "label"=>"Warranty",  "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "meat_grinder" => [
    ["name"=>"power_watts",     "label"=>"Power",     "type"=>"number", "unit"=>"W",     "placeholder"=>"e.g. 1500"],
    ["name"=>"capacity_kgh",    "label"=>"Capacity",  "type"=>"number", "unit"=>"kg/hr", "placeholder"=>"e.g. 50"],
    ["name"=>"warranty",        "label"=>"Warranty",  "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "ice_machine" => [
    ["name"=>"production_kgd",  "label"=>"Ice Production", "type"=>"number", "unit"=>"kg/day", "placeholder"=>"e.g. 50"],
    ["name"=>"storage_kg",      "label"=>"Storage",        "type"=>"number", "unit"=>"kg",     "placeholder"=>"e.g. 20"],
    ["name"=>"ice_type",        "label"=>"Ice Type",       "type"=>"select", "options"=>["Cube","Crushed","Flake","Nugget"]],
    ["name"=>"warranty",        "label"=>"Warranty",       "type"=>"select", "options"=>["6 months","1 year","2 years","3 years"]],
  ],
  // ── Furniture extras ─────────────────────────────────────────────────────
  "sound_system" => [
    ["name"=>"system_type",  "label"=>"System Type",  "type"=>"select", "options"=>["2.0","2.1","5.1","PA System","Ceiling Speakers"]],
    ["name"=>"power_watts",  "label"=>"Total Power",  "type"=>"number", "unit"=>"W", "placeholder"=>"e.g. 200"],
    ["name"=>"connectivity", "label"=>"Connectivity", "type"=>"select", "options"=>["Wired","Bluetooth","WiFi","Wired + Bluetooth"]],
    ["name"=>"warranty",     "label"=>"Warranty",     "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "curtain" => [
    ["name"=>"material",    "label"=>"Material",    "type"=>"select", "options"=>["Fabric","Blackout","Sheer","Velvet"]],
    ["name"=>"width_cm",    "label"=>"Width",       "type"=>"number", "unit"=>"cm", "placeholder"=>"e.g. 200"],
    ["name"=>"height_cm",   "label"=>"Height",      "type"=>"number", "unit"=>"cm", "placeholder"=>"e.g. 250"],
    ["name"=>"color",       "label"=>"Color",       "type"=>"text",   "placeholder"=>"e.g. Beige"],
    ["name"=>"warranty",    "label"=>"Warranty",    "type"=>"select", "options"=>["6 months","1 year"]],
  ],
  "wall_decor" => [
    ["name"=>"decor_type",  "label"=>"Type",        "type"=>"select", "options"=>["Wall Art","Mirror","Clock","Panel","Neon Sign","Plant Wall"]],
    ["name"=>"dimensions",  "label"=>"Dimensions",  "type"=>"text",   "placeholder"=>"e.g. 80x60 cm"],
    ["name"=>"material",    "label"=>"Material",    "type"=>"select", "options"=>["Wood","Metal","Canvas","Acrylic","Mixed"]],
    ["name"=>"warranty",    "label"=>"Warranty",    "type"=>"select", "options"=>["6 months","1 year"]],
  ],
  "menu_stand" => [
    ["name"=>"stand_type",  "label"=>"Type",        "type"=>"select", "options"=>["Table Stand","Floor Stand","Wall Mount","Tent Card"]],
    ["name"=>"material",    "label"=>"Material",    "type"=>"select", "options"=>["Acrylic","Metal","Wood","Leather"]],
    ["name"=>"color",       "label"=>"Color",       "type"=>"text",   "placeholder"=>"e.g. Black"],
    ["name"=>"warranty",    "label"=>"Warranty",    "type"=>"select", "options"=>["6 months","1 year"]],
  ],
  "hostess_stand" => [
    ["name"=>"material",    "label"=>"Material",    "type"=>"select", "options"=>["Wood","MDF","Metal","Mixed"]],
    ["name"=>"with_light",  "label"=>"With Light",  "type"=>"select", "options"=>["Yes","No"]],
    ["name"=>"dimensions",  "label"=>"Dimensions",  "type"=>"text",   "placeholder"=>"e.g. 50x40x120 cm"],
    ["name"=>"color",       "label"=>"Color",       "type"=>"text",   "placeholder"=>"e.g. Walnut"],
    ["name"=>"warranty",    "label"=>"Warranty",    "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  // ── POS extras ───────────────────────────────────────────────────────────
  "customer_display" => [
    ["name"=>"screen_size",  "label"=>"Screen Size",  "type"=>"number", "unit"=>"inch", "placeholder"=>"e.g. 10"],
    ["name"=>"display_type", "label"=>"Display Type", "type"=>"select", "options"=>["LCD","LED","VFD"]],
    ["name"=>"connectivity", "label"=>"Connectivity", "type"=>"select", "options"=>["USB","Serial","HDMI"]],
    ["name"=>"warranty",     "label"=>"Warranty",     "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "label_printer" => [
    ["name"=>"print_width",  "label"=>"Print Width",  "type"=>"text",   "placeholder"=>"e.g. 80mm"],
    ["name"=>"connectivity", "label"=>"Connectivity", "type"=>"select", "options"=>["USB","Bluetooth","WiFi","Ethernet"]],
    ["name"=>"warranty",     "label"=>"Warranty",     "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
  "scale" => [
    ["name"=>"max_weight_kg", "label"=>"Max Weight",  "type"=>"number", "unit"=>"kg", "placeholder"=>"e.g. 30"],
    ["name"=>"display_type",  "label"=>"Display",     "type"=>"select", "options"=>["LCD","LED"]],
    ["name"=>"connectivity",  "label"=>"Connectivity","type"=>"select", "options"=>["Standalone","USB","Bluetooth"]],
    ["name"=>"warranty",      "label"=>"Warranty",    "type"=>"select", "options"=>["6 months","1 year","2 years"]],
  ],
];
?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8" />
<meta name="viewport" content="width=device-width,initial-scale=1" />
<title>Add Product</title>
<link rel="stylesheet" href="./vendor_ui.css?v=<?= time() ?>" />
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
<style>
  #specs-section {
    display: none;
    border: 1px solid rgba(255,255,255,0.12);
    border-radius: 10px;
    padding: 18px 20px 10px;
    margin-top: 4px;
    background: rgba(255,255,255,0.03);
  }
  #specs-section.visible { display: block; }
  #specs-section .specs-title {
    font-size: 0.78rem; font-weight: 700; letter-spacing: 0.08em;
    text-transform: uppercase; color: var(--sf-teal, #2dd4bf); margin-bottom: 14px;
  }
  .spec-field-row { display: grid; grid-template-columns: 1fr 1fr; gap: 12px; }
  @media (max-width: 600px) { .spec-field-row { grid-template-columns: 1fr; } }
  #group-key-preview {
    display: inline-block; margin-top: 6px; font-size: 0.78rem; color: #94a3b8;
    font-family: monospace; background: rgba(255,255,255,0.06); border-radius: 6px; padding: 3px 10px;
  }
  #group-key-preview:empty { display: none; }
  .business-type-checks { display: flex; flex-wrap: wrap; gap: 10px; margin-top: 6px; }
  .business-type-checks label { display: flex; align-items: center; gap: 6px; font-size: 0.85rem; color: #cbd5e1; cursor: pointer; }
  .spec-hint {
    font-size: 0.72rem; color: #f59e0b; margin-top: 4px;
    padding: 4px 10px; background: rgba(245,158,11,0.08); border-radius: 6px; border-left: 3px solid #f59e0b;
  }
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
        <li class="nav-item"><a class="nav-link sf-navlink active" href="vendor_add_product.php">Add Product</a></li>
      </ul>
    </div>
    <div class="d-flex justify-content-end flex-grow-1 gap-2">
      <a href="../auth/logout.php" class="btn btn-outline-light btn-sm px-3 fw-semibold">Logout</a>
    </div>
  </div>
</nav>

<div class="v-wrap">
<div class="v-add-wrap">
<div class="v-add-card is-open" id="addCard">

<div class="v-add-head">
  <div>
    <h2 class="v-add-title">Add Product</h2>
    <p class="v-add-sub">Fill in the details below to list a new product.</p>
  </div>
</div>

<div class="v-add-body">

<?php if (!empty($_GET["error"])): ?>
<div class="v-alert v-alert-danger"><?= htmlspecialchars($_GET["error"]) ?></div>
<?php endif; ?>
<?php if (!empty($_GET["success"])): ?>
<div class="v-alert v-alert-success"><?= htmlspecialchars($_GET["success"]) ?></div>
<?php endif; ?>

<form class="v-form" action="vendor_add_product_action.php" method="POST" enctype="multipart/form-data" id="productForm">

<!-- Product Name -->
<div class="v-field">
  <label class="v-label" for="product_name">Product Name *</label>
  <input class="v-input" id="product_name" type="text" name="product_name" required
         placeholder="e.g. Samsung 50&quot; Smart TV">
</div>

<!-- Module / Product Type -->
<div class="v-form-grid">
  <div class="v-field">
    <label class="v-label" for="module">Module *</label>
    <select class="v-select" id="module" name="module" required>
      <option value="" selected disabled>Select module</option>
      <?php foreach ($moduleOptions as $m): ?>
      <option value="<?= htmlspecialchars($m) ?>"><?= htmlspecialchars(ucfirst($m)) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="v-field">
    <label class="v-label" for="product_type">Product Type *</label>
    <select class="v-select" id="product_type" name="product_type" required>
      <option value="" selected disabled>Select module first</option>
    </select>
  </div>
</div>

<!-- Category / Brand -->
<div class="v-form-grid">
  <div class="v-field">
    <label class="v-label" for="category">Category *</label>
    <select class="v-select" id="category" name="category" required>
      <option value="" selected disabled>Select category</option>
      <?php foreach ($cats as $c): ?>
      <option value="<?= htmlspecialchars($c["id"]) ?>"><?= htmlspecialchars($c["name"]) ?></option>
      <?php endforeach; ?>
    </select>
  </div>
  <div class="v-field">
    <label class="v-label" for="brand">Brand *</label>
    <input class="v-input" id="brand" type="text" name="brand" required placeholder="e.g. Samsung">
  </div>
</div>

<!-- Price / Stock -->
<div class="v-form-grid">
  <div class="v-field">
    <label class="v-label" for="price">Price (EGP) *</label>
    <input class="v-input" id="price" type="number" step="0.01" min="0.01" name="price" required>
  </div>
  <div class="v-field">
    <label class="v-label" for="stock_quantity">Stock Quantity *</label>
    <input class="v-input" id="stock_quantity" type="number" name="stock_quantity" min="0" required>
  </div>
</div>

<!-- Business Type -->
<div class="v-field">
  <label class="v-label">Business Type</label>
  <div class="business-type-checks">
    <?php foreach ($businessTypeOptions as $bt): ?>
    <label>
      <input type="checkbox" name="business_type[]" value="<?= htmlspecialchars($bt) ?>">
      <?= htmlspecialchars(ucfirst($bt)) ?>
    </label>
    <?php endforeach; ?>
  </div>
  <div class="v-sub">Leave unchecked if this product suits all business types.</div>
</div>

<!-- Product Group Key -->
<div class="v-field">
  <label class="v-label" for="product_group_key">Product Group Key</label>
  <input class="v-input" id="product_group_key" type="text" name="product_group_key"
         placeholder="e.g. epson_tm_t82">
  <span id="group-key-preview"></span>
  <div class="v-sub">Used to group the same product from different vendors. Auto-filled — edit only if needed.</div>
</div>

<!-- Dynamic Specs -->
<div id="specs-section">
  <div class="specs-title">Product Specifications</div>
  <div id="specs-hint" class="spec-hint" style="display:none;margin-bottom:12px;"></div>
  <div id="specs-fields" class="spec-field-row"></div>
  <input type="hidden" name="specs" id="specs-hidden">
</div>

<!-- Images -->
<div class="v-field" style="margin-top:18px">
  <label class="v-label" for="images">Images (up to 8)</label>
  <input class="v-input" id="images" type="file" name="images[]" multiple accept="image/*">
  <div class="v-sub">You can select multiple images at once.</div>
</div>

<div class="v-actions">
  <button class="v-btn v-btn-teal" type="submit">Add Product</button>
  <a class="v-btn v-btn-outline" href="vendor_products.php">Back to Products</a>
</div>

</form>
</div>
</div>
</div>
</div>

<script>
(function(){
  const productTypesByModule = <?= json_encode($productTypesByModule) ?>;
  const specsSchemas         = <?= json_encode($specsSchemas) ?>;

  // Critical fields that packages.php depends on — show hints to vendors
  const criticalHints = {
    "dining_set": "⚠ seat_count, layout_type and restaurant_style are required — the system uses these to match dining sets to restaurant types.",
    "tv":         "⚠ screen_size is required — the system uses this to recommend the right TV size for each venue.",
    "ac":         "⚠ hp (Horsepower) is required — the system uses this to match AC units to room size. Use: 1.5 for 1.5 ton, 2 for 2 ton, 2.5 for 2.5 ton, 3 for 3 ton.",
  };

  const moduleSelect      = document.getElementById("module");
  const productTypeSelect = document.getElementById("product_type");
  const productNameInput  = document.getElementById("product_name");
  const groupKeyInput     = document.getElementById("product_group_key");
  const groupKeyPreview   = document.getElementById("group-key-preview");
  const specsSection      = document.getElementById("specs-section");
  const specsFields       = document.getElementById("specs-fields");
  const specsHidden       = document.getElementById("specs-hidden");
  const specsHint         = document.getElementById("specs-hint");

  function updateProductTypes() {
    const types = productTypesByModule[moduleSelect.value] || [];
    productTypeSelect.innerHTML = types.length
      ? '<option value="" disabled selected>Select product type</option>'
      : '<option value="" disabled selected>No types for this module</option>';
    types.forEach(t => {
      const opt = document.createElement("option");
      opt.value = t;
      opt.textContent = t.replace(/_/g, " ").replace(/\b\w/g, c => c.toUpperCase());
      productTypeSelect.appendChild(opt);
    });
    renderSpecsFields(null);
  }
  moduleSelect.addEventListener("change", updateProductTypes);
  productTypeSelect.addEventListener("change", () => renderSpecsFields(productTypeSelect.value || null));

  function slugify(str) {
    return str.toLowerCase().replace(/[^a-z0-9\s]/g, "").trim().replace(/\s+/g, "_").substring(0, 60);
  }
  function updateGroupKey() {
    const name = productNameInput.value.trim();
    const type = productTypeSelect.value || "";
    if (!name) { groupKeyInput.value = ""; groupKeyPreview.textContent = ""; return; }
    const key = type ? slugify(type + " " + name) : slugify(name);
    if (!groupKeyInput.dataset.edited) {
      groupKeyInput.value = key;
      groupKeyPreview.textContent = key;
    }
  }
  productNameInput.addEventListener("input", updateGroupKey);
  productTypeSelect.addEventListener("change", updateGroupKey);
  groupKeyInput.addEventListener("input", () => {
    groupKeyInput.dataset.edited = "1";
    groupKeyPreview.textContent = groupKeyInput.value;
  });

  function renderSpecsFields(productType) {
    specsFields.innerHTML = "";
    specsHidden.value = "";

    if (!productType || !specsSchemas[productType]) {
      specsSection.classList.remove("visible");
      specsHint.style.display = "none";
      return;
    }
    specsSection.classList.add("visible");

    // Show hint for critical types
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
        blank.value = ""; blank.textContent = "Select…"; blank.disabled = true; blank.selected = true;
        input.appendChild(blank);
        (field.options || []).forEach(opt => {
          const o = document.createElement("option"); o.value = opt; o.textContent = opt;
          input.appendChild(o);
        });
      } else {
        input = document.createElement("input");
        input.className = "v-input";
        input.type = field.type === "number" ? "number" : "text";
        input.placeholder = field.placeholder || "";
        if (field.type === "number") { input.min = "0"; input.step = "any"; }
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

  document.getElementById("productForm").addEventListener("submit", buildSpecsJson);
})();
</script>
</body>
</html>