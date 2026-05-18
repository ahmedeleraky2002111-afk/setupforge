<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header("Content-Type: application/json");

try {
    require_once __DIR__ . "/../db.php";

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["ok" => false, "error" => "POST only"]);
        exit;
    }

if (!isset($conn)) {
    echo json_encode(["ok" => false, "error" => "DB connection not available"]);
    exit;
}

    // ---------- Read input ----------
    $raw = file_get_contents("php://input");
    $json = json_decode($raw, true);
    $input = is_array($json) ? $json : $_POST;

$business      = trim((string)($input["business_type"] ?? ""));
$budget        = (int)($input["budget"] ?? 0);
$restaurantType = trim((string)($input["restaurant_type"] ?? "standard_dining"));
$indoorTables  = (int)($input["indoor_tables"] ?? 0);
$outdoorTables = (int)($input["outdoor_tables"] ?? 0);
$areaSqm       = (int)($input["area_sqm"] ?? 0);
$budgetRange   = trim((string)($input["budget_range"] ?? ""));
$staffCounts   = $input["staff_counts"] ?? [];

$modules    = $input["modules"] ?? [];
$moduleTiers = $input["module_tiers"] ?? [];

if (!is_array($modules)) $modules = [];
if (!is_array($moduleTiers)) $moduleTiers = [];

// Derive size from total tables (app no longer sends Small/Medium/Large)
$totalTables = $indoorTables + $outdoorTables;
if ($totalTables <= 5) {
    $size = "Small";
} elseif ($totalTables <= 15) {
    $size = "Medium";
} else {
    $size = "Large";
}

// Also derive tier from budget range if module_tiers not sent
if (empty($moduleTiers)) {
    $tierFromBudget = "Balanced";
    if ($budgetRange === "1.5M-3M" || $budgetRange === "3M+") {
        $tierFromBudget = "Premium";
    } elseif ($budgetRange === "Under 500k") {
        $tierFromBudget = "Starter";
    }
    $moduleTiers = [
        "kitchen"   => $tierFromBudget,
        "furniture" => $tierFromBudget,
        "pos"       => $tierFromBudget,
    ];
}

    if ($business === "" || $size === "" || empty($modules) || $budget <= 0) {
        echo json_encode([
            "ok" => false,
            "error" => "Missing required setup data"
        ]);
        exit;
    }

    // ---------- Labels + weights ----------
    $labels = [
    "kitchen"     => "Kitchen / Equipment",
    "furniture"   => "Dining Area",
    "pos"         => "POS & Operations",
    "electronics" => "Electronic Devices",
    "ac"          => "Ambience & AC"   // add this
];

    $baseWeights = [
        "kitchen" => 5,
        "furniture" => 3,
        "pos" => 2,
        "electronics" => 2,
        "ac" => 2
    ];

    $selectedWeights = [];
    $totalW = 0;

    foreach ($modules as $m) {
        $m = trim((string)$m);
        if (isset($baseWeights[$m])) {
            $selectedWeights[$m] = $baseWeights[$m];
            $totalW += $baseWeights[$m];
        }
    }

    if ($totalW <= 0) {
        echo json_encode(["ok" => false, "error" => "No supported modules selected"]);
        exit;
    }

    $alloc = [];
    $sum = 0;
    $keys = array_keys($selectedWeights);
    $lastKey = end($keys);

    foreach ($selectedWeights as $m => $wgt) {
        $amount = (int)round($budget * ($wgt / $totalW));
        if ($m === $lastKey) $amount = $budget - $sum;
        $alloc[$m] = $amount;
        $sum += $amount;
    }

    $posTier = $moduleTiers["pos"] ?? "Balanced";
    $kitchenTier = $moduleTiers["kitchen"] ?? "Balanced";
    $furnitureTier = $moduleTiers["furniture"] ?? "Balanced";

    // ---------- Helper functions ----------
    function guess_pos_type($name) {
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

    function guess_kitchen_type($name) {
        $n = strtolower((string)$name);
        if (strpos($n, "oven") !== false) return "oven";
        if (strpos($n, "fryer") !== false) return "fryer";
        if (strpos($n, "grill") !== false) return "grill";
        if (strpos($n, "microwave") !== false) return "microwave";
        if (strpos($n, "fridge") !== false || strpos($n, "refrigerator") !== false) return "fridge";
        if (strpos($n, "freezer") !== false) return "freezer";
        if (strpos($n, "blender") !== false) return "blender";
        if (strpos($n, "mixer") !== false) return "mixer";
        if (strpos($n, "coffee") !== false || strpos($n, "espresso") !== false) return "coffee";
        return null;
    }

    function guess_furniture_type($row) {
        $type = trim((string)($row["product_type"] ?? ""));
        if ($type !== "") return $type;

        $n = strtolower((string)($row["product_name"] ?? ""));
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
        if (strpos($n, "dining") !== false && strpos($n, "set") !== false) return "dining_set";

        return null;
    }

    function pos_quantities_by_size($size) {
        $terminals = ($size === "Large") ? 3 : (($size === "Medium") ? 2 : 1);
        return [
            "terminals" => $terminals,
            "printers" => $terminals,
            "drawers" => $terminals
        ];
    }

    function kitchen_quantities_by_size($size) {
        $isLarge = ($size === "Large");
        $isMed = ($size === "Medium");
        return [
            "oven"      => 1,
            "fryer"     => 1,
            "grill"     => ($isMed || $isLarge) ? 1 : 0,
            "microwave" => 1,
            "fridge"    => $isLarge ? 2 : 1,
            "freezer"   => 1,
            "blender"   => 1,
            "mixer"     => ($isMed || $isLarge) ? 1 : 0,
            "coffee"    => 1
        ];
    }

    function dining_set_target_spec($restaurantType, $size) {
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

    function dining_set_quantity_by_size($restaurantType, $size) {
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

    function tv_target_spec($restaurantType, $size) {
        $restaurantType = strtolower(trim((string)$restaurantType));
        $size = ucfirst(strtolower(trim((string)$size)));

        if ($restaurantType === "fast_food") {
            return [
                "screen_size" => match($size) {
                    "Small" => 43,
                    "Medium" => 50,
                    "Large" => 55,
                    default => 43
                }
            ];
        }

        if ($restaurantType === "premium_dining") {
            return [
                "screen_size" => match($size) {
                    "Small" => 43,
                    "Medium" => 50,
                    "Large" => 55,
                    default => 50
                }
            ];
        }

        return [
            "screen_size" => match($size) {
                "Small" => 43,
                "Medium" => 50,
                "Large" => 55,
                default => 50
            }
        ];
    }

    function tv_quantity_by_context($restaurantType, $size) {
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

    function collapse_equivalent_products_cheapest_vendor($products) {
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

    function pick_best_under_unit_budget($catalog, $type, $unitBudget) {
        if (!isset($catalog[$type]) || empty($catalog[$type])) return null;
        $best = null;
        foreach ($catalog[$type] as $p) {
            if ((int)$p["price"] <= (int)$unitBudget) $best = $p;
        }
        if ($best) return $best;
        return $catalog[$type][0];
    }

    function cart_total($cart) {
        $sum = 0;
        foreach (($cart["items"] ?? []) as $it) {
            $sum += ((int)$it["qty"]) * ((int)$it["unit"]);
        }
        return $sum;
    }

    function pick_top_dining_set_options($catalog, $restaurantType, $size, $tier, $limit = 3) {
        $target = dining_set_target_spec($restaurantType, $size);

        if (!isset($catalog["dining_set"]) || empty($catalog["dining_set"])) {
            return [];
        }

        $filtered = array_filter($catalog["dining_set"], function($p) use ($target, $tier) {
            if (strcasecmp((string)($p["tier"] ?? ""), (string)$tier) !== 0) return false;
            if ((int)($p["stock_quantity"] ?? 0) <= 0) return false;

            $specs = $p["specs"] ?? [];
            if (!is_array($specs)) $specs = [];

            $seatCount = (int)($specs["seat_count"] ?? 0);
            $layoutType = (string)($specs["layout_type"] ?? "");
            $style = (string)($specs["restaurant_style"] ?? "");

            if ($seatCount !== (int)$target["seat_count"]) return false;
            if ($layoutType !== $target["layout_type"]) return false;
            if ($style !== $target["restaurant_style"]) return false;

            return true;
        });

        usort($filtered, function($a, $b) {
            $stockCmp = ((int)($b["stock_quantity"] ?? 0) <=> (int)($a["stock_quantity"] ?? 0));
            if ($stockCmp !== 0) return $stockCmp;
            return ((int)$a["price"] <=> (int)$b["price"]);
        });

        return array_slice(array_values($filtered), 0, $limit);
    }

    function pick_top_tv_options($catalog, $restaurantType, $size, $tier, $limit = 3) {
        if (!isset($catalog["tv"]) || empty($catalog["tv"])) {
            return [];
        }

        $target = tv_target_spec($restaurantType, $size);

        $filtered = array_filter($catalog["tv"], function($p) use ($target, $tier) {
            if (strcasecmp((string)($p["tier"] ?? ""), (string)$tier) !== 0) return false;
            if ((int)($p["stock_quantity"] ?? 0) <= 0) return false;

            $specs = $p["specs"] ?? [];
            if (!is_array($specs)) $specs = [];

            $screenSize = (int)($specs["screen_size"] ?? 0);
            if ($screenSize !== (int)$target["screen_size"]) return false;

            return true;
        });

        $collapsed = collapse_equivalent_products_cheapest_vendor(array_values($filtered));

        usort($collapsed, function($a, $b) {
            $priceCmp = ((int)$a["price"] <=> (int)$b["price"]);
            if ($priceCmp !== 0) return $priceCmp;
            return ((int)($b["stock_quantity"] ?? 0) <=> (int)($a["stock_quantity"] ?? 0));
        });

        return array_slice($collapsed, 0, $limit);
    }

    // ---------- DB loaders ----------
    $POS_CATALOG_ACTIVE = [
        "terminal" => [], "printer" => [], "drawer" => [], "software" => [],
        "kds" => [], "scanner" => [], "tablet" => [],
    ];

    $KITCHEN_CATALOG_ACTIVE = [
        "oven" => [], "fryer" => [], "grill" => [], "microwave" => [],
        "fridge" => [], "freezer" => [], "blender" => [], "mixer" => [], "coffee" => []
    ];

    $FURNITURE_CATALOG_ACTIVE = [
        "dining_set" => [],
        "table" => [],
        "chair" => [],
        "tv" => []
    ];

    $AC_CATALOG_ACTIVE = ["ac" => []];

if (in_array("pos", $modules, true)) {
    $res = pg_query_params($conn, "
        SELECT
            p.id, p.product_name, p.product_type, p.price, p.priority,
            p.tier, p.brand, p.vendor_user_id, p.product_group_key, p.created_at,
            u.name AS vendor_name, c.name AS category_name,
            (SELECT pi.image_url FROM product_images pi
             WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) AS image_url
        FROM products p
        LEFT JOIN users u ON u.id = p.vendor_user_id
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.module = 'pos' AND LOWER(p.tier) = LOWER($1)
        ORDER BY p.priority ASC, p.price ASC
    ", [$posTier]);

    while ($row = pg_fetch_assoc($res)) {
        $type = guess_pos_type($row["product_name"] ?? "");
        if (!$type) continue;
        $POS_CATALOG_ACTIVE[$type][] = [
            "id" => (string)$row["id"],
            "name" => $row["product_name"],
            "brand" => $row["brand"] ?: null,
            "tier" => $row["tier"] ?: null,
            "vendor_name" => $row["vendor_name"] ?: null,
            "category_name" => $row["category_name"] ?: null,
            "price" => (int)$row["price"],
            "image_url" => $row["image_url"] ?: null,
            "vendor_user_id" => $row["vendor_user_id"] ?? null,
            "product_group_key" => $row["product_group_key"] ?? null,
            "created_at" => $row["created_at"] ?? null,
        ];
    }
}

    if (in_array("kitchen", $modules, true)) {
    $res = pg_query_params($conn, "
        SELECT
            p.id, p.product_name, p.price, p.priority, p.tier, p.brand,
            p.vendor_user_id, p.product_group_key, p.created_at,
            p.stock_quantity, p.specs,
            u.name AS vendor_name, c.name AS category_name,
            (SELECT pi.image_url FROM product_images pi
             WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) AS image_url
        FROM products p
        LEFT JOIN users u ON u.id = p.vendor_user_id
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.module = 'kitchen' AND LOWER(p.tier) = LOWER($1)
        ORDER BY p.priority ASC, p.price ASC
    ", [$kitchenTier]);

    while ($row = pg_fetch_assoc($res)) {
        $type = guess_kitchen_type($row["product_name"] ?? "");
        if (!$type) continue;
        $KITCHEN_CATALOG_ACTIVE[$type][] = [
            "id" => (string)$row["id"],
            "name" => $row["product_name"],
            "brand" => $row["brand"] ?: null,
            "tier" => $row["tier"] ?: null,
            "vendor_name" => $row["vendor_name"] ?: null,
            "category_name" => $row["category_name"] ?: null,
            "price" => (int)$row["price"],
            "image_url" => $row["image_url"] ?: null,
            "vendor_user_id" => $row["vendor_user_id"] ?? null,
            "product_group_key" => $row["product_group_key"] ?? null,
            "created_at" => $row["created_at"] ?? null,
            "stock_quantity" => (int)($row["stock_quantity"] ?? 0),
            "specs" => !empty($row["specs"]) ? json_decode($row["specs"], true) : [],
        ];
    }
}

    if (in_array("furniture", $modules, true)) {
    $res = pg_query_params($conn, "
        SELECT
            p.id, p.product_name, p.product_type, p.price, p.priority, p.tier,
            p.brand, p.stock_quantity, p.specs, p.category_id,
            p.vendor_user_id, p.product_group_key, p.created_at,
            u.name AS vendor_name, c.name AS category_name,
            (SELECT pi.image_url FROM product_images pi
             WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) AS image_url
        FROM products p
        LEFT JOIN users u ON u.id = p.vendor_user_id
        LEFT JOIN categories c ON c.id = p.category_id
        WHERE p.module = 'furniture' AND LOWER(p.tier) = LOWER($1)
        ORDER BY p.priority ASC, p.price ASC
    ", [$furnitureTier]);

    while ($row = pg_fetch_assoc($res)) {
        $type = guess_furniture_type($row);
        if (!$type) continue;
        if (!isset($FURNITURE_CATALOG_ACTIVE[$type])) {
            $FURNITURE_CATALOG_ACTIVE[$type] = [];
        }
        $FURNITURE_CATALOG_ACTIVE[$type][] = [
            "id" => (string)$row["id"],
            "name" => $row["product_name"],
            "brand" => $row["brand"] ?: null,
            "tier" => $row["tier"] ?: null,
            "vendor_name" => $row["vendor_name"] ?: null,
            "category_name" => $row["category_name"] ?: null,
            "category_id" => $row["category_id"] ?? null,
            "price" => (int)$row["price"],
            "image_url" => $row["image_url"] ?: null,
            "vendor_user_id" => $row["vendor_user_id"] ?? null,
            "product_group_key" => $row["product_group_key"] ?? null,
            "created_at" => $row["created_at"] ?? null,
            "stock_quantity" => (int)($row["stock_quantity"] ?? 0),
            "specs" => !empty($row["specs"]) ? json_decode($row["specs"], true) : [],
        ];
    }
}

if (in_array("ac", $modules, true)) {
    $res = pg_query_params($conn, "
        SELECT p.id, p.product_name, p.price, p.priority, p.tier, p.brand,
               p.stock_quantity, p.specs, p.vendor_user_id, p.product_group_key, p.created_at,
               u.name AS vendor_name,
               (SELECT pi.image_url FROM product_images pi WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) AS image_url
        FROM products p
        LEFT JOIN users u ON u.id = p.vendor_user_id
        WHERE p.module = 'ac'
        ORDER BY p.priority ASC, p.price ASC
    ", []);
    while ($row = pg_fetch_assoc($res)) {
        $AC_CATALOG_ACTIVE["ac"][] = [
            "id"                => (string)$row["id"],
            "name"              => $row["product_name"],
            "brand"             => $row["brand"] ?: null,
            "tier"              => $row["tier"] ?: null,
            "vendor_name"       => $row["vendor_name"] ?: null,
            "price"             => (int)$row["price"],
            "image_url"         => $row["image_url"] ?: null,
            "vendor_user_id"    => $row["vendor_user_id"] ?? null,
            "product_group_key" => $row["product_group_key"] ?? null,
            "stock_quantity"    => (int)($row["stock_quantity"] ?? 0),
            "specs"             => !empty($row["specs"]) ? json_decode($row["specs"], true) : [],
        ];
    }
}

    // ---------- Builders ----------
    function build_pos_cart_by_budget($catalog, $size, $cap, $tier) {
        $q = pos_quantities_by_size($size);
        $termQty = (int)$q["terminals"];
        if ($termQty <= 0) $termQty = 1;

        $unitBudgetMain = max(1, (int)floor(((int)$cap * 0.60) / $termQty));
        $unitBudgetPeri = max(1, (int)floor(((int)$cap * 0.25) / $termQty));

        $terminal = pick_best_under_unit_budget($catalog, "terminal", $unitBudgetMain);
        $printer  = pick_best_under_unit_budget($catalog, "printer",  $unitBudgetPeri);
        $drawer   = pick_best_under_unit_budget($catalog, "drawer",   $unitBudgetPeri);
        $software = pick_best_under_unit_budget($catalog, "software", max(1, (int)floor((int)$cap * 0.15)));

        $cart = ["items" => []];

        if ($terminal) {
            $cart["items"]["terminal"] = [
                "type" => "terminal",
                "product_id" => (int)$terminal["id"],
                "name" => $terminal["name"],
                "unit" => (int)$terminal["price"],
                "qty" => $termQty,
                "image_url" => $terminal["image_url"] ?? null,
                "brand" => $terminal["brand"] ?? null,
                "vendor_name" => $terminal["vendor_name"] ?? null,
                "product_name" => $terminal["name"],
                "tier" => $tier,
                "module" => "pos",
                "category_id" => null,
                "product_group_key" => $terminal["product_group_key"] ?? null,
                "vendor_user_id" => $terminal["vendor_user_id"] ?? null,
            ];
        }

        if ($printer) {
            $cart["items"]["printer"] = [
                "type" => "printer",
                "product_id" => (int)$printer["id"],
                "name" => $printer["name"],
                "unit" => (int)$printer["price"],
                "qty" => $termQty,
                "image_url" => $printer["image_url"] ?? null,
                "brand" => $printer["brand"] ?? null,
                "vendor_name" => $printer["vendor_name"] ?? null,
                "product_name" => $printer["name"],
                "tier" => $tier,
                "module" => "pos",
                "category_id" => null,
                "product_group_key" => $printer["product_group_key"] ?? null,
                "vendor_user_id" => $printer["vendor_user_id"] ?? null,
            ];
        }

        if ($drawer) {
            $cart["items"]["drawer"] = [
                "type" => "drawer",
                "product_id" => (int)$drawer["id"],
                "name" => $drawer["name"],
                "unit" => (int)$drawer["price"],
                "qty" => $termQty,
                "image_url" => $drawer["image_url"] ?? null,
                "brand" => $drawer["brand"] ?? null,
                "vendor_name" => $drawer["vendor_name"] ?? null,
                "product_name" => $drawer["name"],
                "tier" => $tier,
                "module" => "pos",
                "category_id" => null,
                "product_group_key" => $drawer["product_group_key"] ?? null,
                "vendor_user_id" => $drawer["vendor_user_id"] ?? null,
            ];
        }

        if ($software) {
            $cart["items"]["software"] = [
                "type" => "software",
                "product_id" => (int)$software["id"],
                "name" => $software["name"],
                "unit" => (int)$software["price"],
                "qty" => 1,
                "image_url" => $software["image_url"] ?? null,
                "brand" => $software["brand"] ?? null,
                "vendor_name" => $software["vendor_name"] ?? null,
                "product_name" => $software["name"],
                "tier" => $tier,
                "module" => "pos",
                "category_id" => null,
                "product_group_key" => $software["product_group_key"] ?? null,
                "vendor_user_id" => $software["vendor_user_id"] ?? null,
            ];
        }

        $remaining = $cap - cart_total($cart);

        if ($remaining > 0 && isset($catalog["scanner"][0])) {
            $sc = pick_best_under_unit_budget($catalog, "scanner", (int)floor($remaining * 0.30));
            if ($sc) {
                $cart["items"]["scanner"] = [
                    "type" => "scanner",
                    "product_id" => (int)$sc["id"],
                    "name" => $sc["name"],
                    "unit" => (int)$sc["price"],
                    "qty" => 1,
                    "image_url" => $sc["image_url"] ?? null,
                    "brand" => $sc["brand"] ?? null,
                    "vendor_name" => $sc["vendor_name"] ?? null,
                    "product_name" => $sc["name"],
                    "tier" => $tier,
                    "module" => "pos",
                    "category_id" => null,
                    "product_group_key" => $sc["product_group_key"] ?? null,
                    "vendor_user_id" => $sc["vendor_user_id"] ?? null,
                ];
                $remaining = $cap - cart_total($cart);
            }
        }

        if ($remaining > 0 && $size !== "Small" && isset($catalog["kds"][0])) {
            $kds = pick_best_under_unit_budget($catalog, "kds", (int)floor($remaining * 0.60));
            if ($kds) {
                $cart["items"]["kds"] = [
                    "type" => "kds",
                    "product_id" => (int)$kds["id"],
                    "name" => $kds["name"],
                    "unit" => (int)$kds["price"],
                    "qty" => 1,
                    "image_url" => $kds["image_url"] ?? null,
                    "brand" => $kds["brand"] ?? null,
                    "vendor_name" => $kds["vendor_name"] ?? null,
                    "product_name" => $kds["name"],
                    "tier" => $tier,
                    "module" => "pos",
                    "category_id" => null,
                    "product_group_key" => $kds["product_group_key"] ?? null,
                    "vendor_user_id" => $kds["vendor_user_id"] ?? null,
                ];
                $remaining = $cap - cart_total($cart);
            }
        }

        if ($remaining > 0 && isset($catalog["tablet"][0])) {
            $tb = pick_best_under_unit_budget($catalog, "tablet", (int)floor($remaining * 0.70));
            if ($tb) {
                $cart["items"]["tablet"] = [
                    "type" => "tablet",
                    "product_id" => (int)$tb["id"],
                    "name" => $tb["name"],
                    "unit" => (int)$tb["price"],
                    "qty" => 1,
                    "image_url" => $tb["image_url"] ?? null,
                    "brand" => $tb["brand"] ?? null,
                    "vendor_name" => $tb["vendor_name"] ?? null,
                    "product_name" => $tb["name"],
                    "tier" => $tier,
                    "module" => "pos",
                    "category_id" => null,
                    "product_group_key" => $tb["product_group_key"] ?? null,
                    "vendor_user_id" => $tb["vendor_user_id"] ?? null,
                ];
            }
        }

        return $cart;
    }

    function build_kitchen_cart_by_budget($catalog, $size, $cap, $tier) {
        $q = kitchen_quantities_by_size($size);
        $cart = ["items" => []];

        $core  = ["oven", "fryer", "microwave", "fridge", "freezer", "blender"];
        $extra = ["grill", "mixer", "coffee"];

        $coreBudget = (int)floor($cap * 0.75);

        $coreTypesCount = 0;
        foreach ($core as $t) {
            if (($q[$t] ?? 0) > 0) $coreTypesCount++;
        }
        if ($coreTypesCount <= 0) $coreTypesCount = 1;

        foreach ($core as $type) {
            $qty = (int)($q[$type] ?? 0);
            if ($qty <= 0) continue;

            $unitBudget = (int)floor(($coreBudget / $coreTypesCount) / max(1, $qty));
            $p = pick_best_under_unit_budget($catalog, $type, $unitBudget);

            if ($p) {
                $cart["items"][$type] = [
                    "type" => $type,
                    "product_id" => (int)$p["id"],
                    "name" => $p["name"],
                    "unit" => (int)$p["price"],
                    "qty" => $qty,
                    "image_url" => $p["image_url"] ?? null,
                    "brand" => $p["brand"] ?? null,
                    "vendor_name" => $p["vendor_name"] ?? null,
                    "product_name" => $p["name"],
                    "tier" => $tier,
                    "module" => "kitchen",
                    "category_id" => null,
                    "product_group_key" => $p["product_group_key"] ?? null,
                    "vendor_user_id" => $p["vendor_user_id"] ?? null,
                ];
            }
        }

        $remaining = $cap - cart_total($cart);

        foreach ($extra as $type) {
            $qty = (int)($q[$type] ?? 0);
            if ($qty <= 0) continue;
            if ($remaining <= 0) break;

            $unitBudget = (int)floor(($remaining * 0.45) / max(1, $qty));
            $p = pick_best_under_unit_budget($catalog, $type, $unitBudget);

            if ($p) {
                $cart["items"][$type] = [
                    "type" => $type,
                    "product_id" => (int)$p["id"],
                    "name" => $p["name"],
                    "unit" => (int)$p["price"],
                    "qty" => $qty,
                    "image_url" => $p["image_url"] ?? null,
                    "brand" => $p["brand"] ?? null,
                    "vendor_name" => $p["vendor_name"] ?? null,
                    "product_name" => $p["name"],
                    "tier" => $tier,
                    "module" => "kitchen",
                    "category_id" => null,
                    "product_group_key" => $p["product_group_key"] ?? null,
                    "vendor_user_id" => $p["vendor_user_id"] ?? null,
                ];
                $remaining = $cap - cart_total($cart);
            }
        }

        return $cart;
    }

    function build_furniture_cart_by_budget($catalog, $size, $cap, $restaurantType, $tier) {
        $cart = ["items" => []];

        $setQty = dining_set_quantity_by_size($restaurantType, $size);
        $setOptions = pick_top_dining_set_options($catalog, $restaurantType, $size, $tier, 3);

        if (!empty($setOptions)) {
            $recommended = $setOptions[0];

            $cart["items"]["dining_set"] = [
                "type" => "dining_set",
                "product_id" => (int)$recommended["id"],
                "name" => $recommended["name"],
                "unit" => (int)$recommended["price"],
                "qty" => $setQty,
                "image_url" => $recommended["image_url"] ?? null,
                "brand" => $recommended["brand"] ?? null,
                "vendor_name" => $recommended["vendor_name"] ?? null,
                "product_name" => $recommended["name"],
                "tier" => $recommended["tier"] ?? $tier,
                "module" => "furniture",
                "category_id" => $recommended["category_id"] ?? null,
                "product_group_key" => $recommended["product_group_key"] ?? null,
                "vendor_user_id" => $recommended["vendor_user_id"] ?? null,
                "stock_quantity" => $recommended["stock_quantity"] ?? 0,
                "specs" => $recommended["specs"] ?? [],
                "alternatives" => array_slice($setOptions, 1),
            ];
        }

        $remaining = $cap - cart_total($cart);
        $tvQty = tv_quantity_by_context($restaurantType, $size);
        $tvOptions = pick_top_tv_options($catalog, $restaurantType, $size, $tier, 3);

        if ($remaining > 0 && $tvQty > 0 && !empty($tvOptions)) {
            $recommendedTv = $tvOptions[0];

            $cart["items"]["tv"] = [
                "type" => "tv",
                "product_id" => (int)$recommendedTv["id"],
                "name" => $recommendedTv["name"],
                "unit" => (int)$recommendedTv["price"],
                "qty" => $tvQty,
                "image_url" => $recommendedTv["image_url"] ?? null,
                "brand" => $recommendedTv["brand"] ?? null,
                "vendor_name" => $recommendedTv["vendor_name"] ?? null,
                "product_name" => $recommendedTv["name"],
                "tier" => $recommendedTv["tier"] ?? $tier,
                "module" => "furniture",
                "category_id" => $recommendedTv["category_id"] ?? null,
                "product_group_key" => $recommendedTv["product_group_key"] ?? null,
                "vendor_user_id" => $recommendedTv["vendor_user_id"] ?? null,
                "stock_quantity" => $recommendedTv["stock_quantity"] ?? 0,
                "specs" => $recommendedTv["specs"] ?? [],
                "alternatives" => array_slice($tvOptions, 1),
            ];
        }

        return $cart;
    }

    function build_ac_cart_by_budget($catalog, $areaSqm) {
        $acUnits = max(1, (int)ceil($areaSqm / 40));
        $areaPerUnit = $areaSqm / max(1, $acUnits);
        if ($areaPerUnit <= 20)      { $tonnage = "1.5"; }
        elseif ($areaPerUnit <= 30)  { $tonnage = "2"; }
        elseif ($areaPerUnit <= 45)  { $tonnage = "2.5"; }
        else                         { $tonnage = "3"; }

        $cart = ["items" => []];
        if (empty($catalog["ac"])) return $cart;

        $matching = array_filter($catalog["ac"], function($p) use ($tonnage) {
            $specs = $p["specs"] ?? [];
            $hp = (float)($specs["hp"] ?? 0);
            $hpMap = ["1.5" => [1.5,1.5], "2" => [2.0,2.25], "2.5" => [2.5,2.5], "3" => [3.0,3.0]];
            $range = $hpMap[$tonnage] ?? null;
            if (!$range) return true;
            return $hp >= $range[0] && $hp <= $range[1];
        });

        $matching = array_values($matching);
        usort($matching, fn($a,$b) => (int)$a["price"] <=> (int)$b["price"]);
        if (empty($matching)) $matching = array_values($catalog["ac"]);

        $recommended = $matching[0];
        $cart["items"]["ac"] = [
            "type"              => "ac",
            "product_id"        => $recommended["id"],
            "name"              => $recommended["name"],
            "unit"              => (int)$recommended["price"],
            "qty"               => $acUnits,
            "image_url"         => $recommended["image_url"] ?? null,
            "brand"             => $recommended["brand"] ?? null,
            "vendor_name"       => $recommended["vendor_name"] ?? null,
            "tonnage"           => $tonnage,
            "product_group_key" => $recommended["product_group_key"] ?? null,
            "vendor_user_id"    => $recommended["vendor_user_id"] ?? null,
            "stock_quantity"    => $recommended["stock_quantity"] ?? 0,
            "specs"             => $recommended["specs"] ?? [],
        ];
        return $cart;
    }

    // ---------- Build carts ----------
    $moduleCarts = [];

    if (in_array("pos", $modules, true) && ($alloc["pos"] ?? 0) > 0) {
        $moduleCarts["pos"] = build_pos_cart_by_budget(
            $POS_CATALOG_ACTIVE,
            $size,
            $alloc["pos"],
            $posTier
        );
    }

    if (in_array("kitchen", $modules, true) && ($alloc["kitchen"] ?? 0) > 0) {
        $moduleCarts["kitchen"] = build_kitchen_cart_by_budget(
            $KITCHEN_CATALOG_ACTIVE,
            $size,
            $alloc["kitchen"],
            $kitchenTier
        );
    }

    if (in_array("furniture", $modules, true) && ($alloc["furniture"] ?? 0) > 0) {
        $moduleCarts["furniture"] = build_furniture_cart_by_budget(
            $FURNITURE_CATALOG_ACTIVE,
            $size,
            $alloc["furniture"],
            $restaurantType,
            $furnitureTier
        );
    }

    if (in_array("ac", $modules, true) && ($alloc["ac"] ?? 0) > 0) {
        $moduleCarts["ac"] = build_ac_cart_by_budget($AC_CATALOG_ACTIVE, $areaSqm);
    }

    // ---------- Flatten summary ----------
    $flatItems = [];
    $grandTotal = 0;

    foreach ($moduleCarts as $moduleKey => $cart) {
        foreach (($cart["items"] ?? []) as $type => $it) {
            $lineTotal = ((int)($it["qty"] ?? 0)) * ((int)($it["unit"] ?? 0));
            $grandTotal += $lineTotal;

            $flatItems[] = [
                "module_key" => $moduleKey,
                "module_label" => $labels[$moduleKey] ?? $moduleKey,
                "type" => $type,
                "product_id" => (int)($it["product_id"] ?? 0),
                "name" => $it["name"] ?? "",
                "price" => (int)($it["unit"] ?? 0),
                "qty" => (int)($it["qty"] ?? 0),
                "image_url" => $it["image_url"] ?? null,
                "brand" => $it["brand"] ?? null,
                "vendor_name" => $it["vendor_name"] ?? null,
                "product_group_key" => $it["product_group_key"] ?? null,
                "vendor_user_id" => $it["vendor_user_id"] ?? null,
                "alternatives" => $it["alternatives"] ?? [],
            ];
        }
    }

    echo json_encode([
        "ok" => true,
        "business_type" => $business,
        "size" => $size,
        "budget" => $budget,
        "restaurant_type" => $restaurantType,
        "alloc" => $alloc,
        "module_carts" => $moduleCarts,
        "items" => $flatItems,
        "grand_total" => $grandTotal,
    ]);
} catch (Throwable $e) {
    file_put_contents(
        __DIR__ . "/api_error.log",
        date("c") . " api_generate_packages: " . $e->getMessage() . "\n",
        FILE_APPEND
    );

    http_response_code(500);
    echo json_encode([
        "ok" => false,
        "error" => "Server error (check api_error.log)"
    ]);
} finally {
    ob_end_flush();
}