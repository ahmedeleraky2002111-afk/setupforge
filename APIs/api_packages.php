<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header("Content-Type: application/json");

try {
    require_once __DIR__ . "/../db.php";

    $headers = function_exists('getallheaders') ? getallheaders() : [];
    $auth = $headers["Authorization"] ?? $headers["authorization"] ?? "";

    if (!str_starts_with($auth, "Bearer ")) {
        echo json_encode(["ok" => false, "error" => "No token"]);
        exit;
    }

    $token = trim(substr($auth, 7));
    $userRes = pg_query_params($conn,
        "SELECT id FROM users WHERE api_token = $1 LIMIT 1", [$token]);

    if (!$userRes || pg_num_rows($userRes) === 0) {
        echo json_encode(["ok" => false, "error" => "Unauthorized"]);
        exit;
    }

    $user_id = (int)pg_fetch_assoc($userRes)["id"];

    // Get business data
    $bizRes = pg_query_params($conn,
        "SELECT * FROM businesses WHERE user_id = $1 LIMIT 1", [$user_id]);

    if (!$bizRes || pg_num_rows($bizRes) === 0) {
        echo json_encode(["ok" => false, "error" => "No business found"]);
        exit;
    }

    $biz = pg_fetch_assoc($bizRes);

    $budget        = (int)($biz["budget_egp"] ?? 0);
    $restaurantType = $biz["restaurant_type"] ?? "standard_dining";
    $areaSqm       = (int)($biz["area_sqm"] ?? 50);
    $indoorTables  = (int)($biz["indoor_tables"] ?? 0);
    $outdoorTables = (int)($biz["outdoor_tables"] ?? 0);
    $indoorSeats   = $indoorTables * 4;
    $outdoorSeats  = $outdoorTables * 4;

    // Parse modules
    $modulesRaw = trim($biz["modules"] ?? "", "{}");
    $modules = $modulesRaw ? explode(",", $modulesRaw) : ["kitchen", "pos", "furniture", "ac"];

    // Derive tier
    function derive_tier($b) {
        if ($b < 600000)  return "Starter";
        if ($b < 2000000) return "Balanced";
        return "Premium";
    }
    $tier = derive_tier($budget);

    // Module weights
    $allWeights = [
        "fast_food"       => ["kitchen"=>6,"pos"=>3,"furniture"=>2,"ac"=>1],
        "standard_dining" => ["kitchen"=>5,"furniture"=>3,"pos"=>2,"ac"=>2],
        "premium_dining"  => ["kitchen"=>4,"furniture"=>5,"pos"=>2,"ac"=>3],
        "cloud_kitchen"   => ["kitchen"=>8,"pos"=>4,"furniture"=>0,"ac"=>0],
    ];
    $weights = $allWeights[$restaurantType] ?? $allWeights["standard_dining"];

    $selectedWeights = [];
    foreach ($modules as $m) {
        if (isset($weights[$m]) && $weights[$m] > 0)
            $selectedWeights[$m] = $weights[$m];
    }

    $totalW = array_sum($selectedWeights);
    $alloc = [];
    $sum = 0;
    $keys = array_keys($selectedWeights);
    $lastKey = end($keys);
    foreach ($selectedWeights as $m => $w) {
        $amount = (int)round($budget * ($w / $totalW));
        if ($m === $lastKey) $amount = $budget - $sum;
        $alloc[$m] = $amount;
        $sum += $amount;
    }

    // Helper: fetch products by module+tier
    function fetch_products($conn, $module, $tier) {
        $res = pg_query_params($conn, "
            SELECT p.id, p.product_name, p.product_type, p.price, p.avg_rating,
                   p.brand, p.vendor_user_id, p.product_group_key, p.stock_quantity,
                   p.specs, u.name AS vendor_name,
                   (SELECT pi.image_url FROM product_images pi
                    WHERE pi.product_id = p.id ORDER BY pi.id ASC LIMIT 1) AS image_url
            FROM products p
            LEFT JOIN users u ON u.id = p.vendor_user_id
            WHERE p.module = \$1 AND LOWER(p.tier) = LOWER(\$2)
            ORDER BY p.avg_rating DESC NULLS LAST, p.price ASC
        ", [$module, $tier]);

        $out = [];
        if ($res) {
            while ($r = pg_fetch_assoc($res)) {
                $out[] = [
                    "id"                => (string)$r["id"],
                    "name"              => $r["product_name"],
                    "product_type"      => $r["product_type"] ?? "",
                    "price"             => (int)$r["price"],
                    "avg_rating"        => $r["avg_rating"] ? (float)$r["avg_rating"] : null,
                    "brand"             => $r["brand"] ?? "",
                    "vendor_name"       => $r["vendor_name"] ?? "",
                    "vendor_user_id"    => $r["vendor_user_id"] ?? null,
                    "product_group_key" => $r["product_group_key"] ?? null,
                    "stock_quantity"    => (int)($r["stock_quantity"] ?? 0),
                    "specs"             => !empty($r["specs"]) ? json_decode($r["specs"], true) : [],
                    "image_url"         => $r["image_url"] ?? null,
                ];
            }
        }
        return $out;
    }

    // Helper: pick best under budget
    function pick_best($pool, $typePools, $type, $unitBudget) {
        $items = $typePools[$type] ?? [];
        if (empty($items)) return null;
        $under = array_filter($items, fn($p) => (int)$p["price"] <= $unitBudget);
        if (!empty($under)) {
            usort($under, function($a, $b) {
                $ra = (float)($a["avg_rating"] ?? 0);
                $rb = (float)($b["avg_rating"] ?? 0);
                if ($rb !== $ra) return $rb <=> $ra;
                return (int)$b["price"] <=> (int)$a["price"];
            });
            return array_values($under)[0];
        }
        return $items[0];
    }

    // Helper: build alternatives
    function build_alts($selected, $pool, $limit = 3) {
        if (empty($pool) || !$selected) return [];
        $alts = array_filter($pool, fn($p) =>
            (string)$p["id"] !== (string)$selected["id"]);
        usort($alts, fn($a, $b) => (int)$a["price"] <=> (int)$b["price"]);
        return array_slice(array_values($alts), 0, $limit);
    }

    // Helper: cart total
    function cart_total_api($items) {
        return array_sum(array_map(fn($i) => (int)$i["qty"] * (int)$i["unit"], $items));
    }

    // Build carts
    $carts = [];
    $moduleLabels = [
        "kitchen"  => "Kitchen / Equipment",
        "pos"      => "POS & Tech",
        "furniture"=> "Dining Area",
        "ac"       => "AC & Ambience",
    ];

    foreach ($alloc as $module => $cap) {
        $products = fetch_products($conn, $module, $tier);

        // Group by product_type
        $byType = [];
        foreach ($products as $p) {
            $t = $p["product_type"] ?? "";
            if (!$t) continue;
            if (!isset($byType[$t])) $byType[$t] = [];
            $byType[$t][] = $p;
        }

        $items = [];

        if ($module === "pos") {
            $termQty = max(1, (int)ceil($indoorSeats / 20));
            $unitMain = max(1, (int)floor($cap * 0.60 / $termQty));
            $unitPeri = max(1, (int)floor($cap * 0.25 / $termQty));

            $types = [
                "terminal" => ["budget" => $unitMain, "qty" => $termQty],
                "printer"  => ["budget" => $unitPeri, "qty" => $termQty],
                "drawer"   => ["budget" => $unitPeri, "qty" => $termQty],
                "software" => ["budget" => max(1, (int)floor($cap * 0.15)), "qty" => 1],
            ];

            foreach ($types as $type => $cfg) {
                $p = pick_best($byType, $byType, $type, $cfg["budget"]);
                if ($p) {
                    $items[$type] = [
                        "type"     => $type,
                        "product_id" => $p["id"],
                        "name"     => $p["name"],
                        "unit"     => (int)$p["price"],
                        "qty"      => $cfg["qty"],
                        "image_url"=> $p["image_url"],
                        "brand"    => $p["brand"],
                        "vendor_name" => $p["vendor_name"],
                        "avg_rating"  => $p["avg_rating"],
                        "alternatives" => build_alts($p, $byType[$type] ?? []),
                    ];
                }
            }
        }

        if ($module === "kitchen") {
            $total = $indoorSeats + $outdoorSeats;
            $qtys = [
                "oven"=>1,"fryer"=>1,"microwave"=>1,
                "fridge"=>1,"freezer"=>1,"blender"=>1,
                "grill"=>($total>=30?1:0),
                "mixer"=>($total>=30?1:0),
                "coffee"=>1,
            ];

            $coreCap = (int)floor($cap * 0.75);
            $coreTypes = ["oven","fryer","microwave","fridge","freezer","blender"];
            $coreCount = count(array_filter($coreTypes, fn($t) => ($qtys[$t]??0) > 0));
            if ($coreCount <= 0) $coreCount = 1;

            foreach ($coreTypes as $type) {
                $qty = $qtys[$type] ?? 0;
                if ($qty <= 0) continue;
                $unitBudget = (int)floor($coreCap / $coreCount / $qty);
                $p = pick_best($byType, $byType, $type, $unitBudget);
                if ($p) {
                    $items[$type] = [
                        "type"=>"$type","product_id"=>$p["id"],"name"=>$p["name"],
                        "unit"=>(int)$p["price"],"qty"=>$qty,
                        "image_url"=>$p["image_url"],"brand"=>$p["brand"],
                        "vendor_name"=>$p["vendor_name"],"avg_rating"=>$p["avg_rating"],
                        "alternatives"=>build_alts($p, $byType[$type]??[]),
                    ];
                }
            }

            $remaining = $cap - cart_total_api($items);
            foreach (["grill","mixer","coffee"] as $type) {
                if ($remaining <= 0) break;
                $qty = $qtys[$type] ?? 0;
                if ($qty <= 0) continue;
                $unitBudget = (int)floor($remaining * 0.45 / $qty);
                $p = pick_best($byType, $byType, $type, $unitBudget);
                if ($p) {
                    $items[$type] = [
                        "type"=>$type,"product_id"=>$p["id"],"name"=>$p["name"],
                        "unit"=>(int)$p["price"],"qty"=>$qty,
                        "image_url"=>$p["image_url"],"brand"=>$p["brand"],
                        "vendor_name"=>$p["vendor_name"],"avg_rating"=>$p["avg_rating"],
                        "alternatives"=>build_alts($p, $byType[$type]??[]),
                    ];
                    $remaining = $cap - cart_total_api($items);
                }
            }
        }

        if ($module === "furniture") {
            $ratios = [
                "fast_food"       => [2=>0.30,4=>0.50,6=>0.20],
                "standard_dining" => [2=>0.25,4=>0.50,6=>0.20,10=>0.05],
                "premium_dining"  => [2=>0.20,4=>0.45,8=>0.25,12=>0.10],
                "cloud_kitchen"   => [],
            ];
            $typeRatios = $ratios[$restaurantType] ?? $ratios["standard_dining"];
            $tables = max(1, $indoorTables);

            foreach ($typeRatios as $seatCount => $ratio) {
                $qty = max(1, (int)round($tables * $ratio));
                $matching = array_filter($byType["dining_set"] ?? [], function($p) use ($seatCount, $tier) {
                    $specs = $p["specs"] ?? [];
                    return (int)($specs["seat_count"] ?? 0) === (int)$seatCount
                        && strcasecmp($p["tier"] ?? "", $tier) === 0
                        && (int)($p["stock_quantity"] ?? 0) > 0;
                });
                if (empty($matching)) continue;
                usort($matching, fn($a,$b) => (int)$a["price"] <=> (int)$b["price"]);
                $rec = array_values($matching)[0];
                $typeKey = "dining_set_$seatCount";
                $items[$typeKey] = [
                    "type"=>$typeKey,"product_id"=>$rec["id"],"name"=>$rec["name"],
                    "unit"=>(int)$rec["price"],"qty"=>$qty,
                    "image_url"=>$rec["image_url"],"brand"=>$rec["brand"],
                    "vendor_name"=>$rec["vendor_name"],"avg_rating"=>$rec["avg_rating"],
                    "alternatives"=>build_alts($rec, array_values($matching)),
                ];
            }

            // TVs
            if ($restaurantType !== "cloud_kitchen") {
                $tvQty = max(1, (int)ceil($indoorSeats / 20));
                $seats = $indoorSeats;
                $targetSize = $seats < 30 ? 43 : ($seats <= 60 ? 50 : 55);
                $tvMatching = array_filter($byType["tv"] ?? [], function($p) use ($targetSize, $tier) {
                    $specs = $p["specs"] ?? [];
                    return (int)($specs["screen_size"] ?? 0) === $targetSize
                        && strcasecmp($p["tier"] ?? "", $tier) === 0
                        && (int)($p["stock_quantity"] ?? 0) > 0;
                });
                if (!empty($tvMatching)) {
                    usort($tvMatching, fn($a,$b) => (int)$a["price"] <=> (int)$b["price"]);
                    $rec = array_values($tvMatching)[0];
                    $items["tv"] = [
                        "type"=>"tv","product_id"=>$rec["id"],"name"=>$rec["name"],
                        "unit"=>(int)$rec["price"],"qty"=>$tvQty,
                        "image_url"=>$rec["image_url"],"brand"=>$rec["brand"],
                        "vendor_name"=>$rec["vendor_name"],"avg_rating"=>$rec["avg_rating"],
                        "alternatives"=>build_alts($rec, array_values($tvMatching)),
                    ];
                }
            }
        }

        if ($module === "ac") {
            $acMap = [
                [40,1,1.5],[80,1,2.5],[100,2,2.5],[150,3,3.0],
                [200,3,4.0],[300,4,4.0],[400,4,5.0],
            ];
            $acUnits = 0; $acHp = 0;
            foreach ($acMap as [$max, $units, $hp]) {
                if ($areaSqm <= $max) { $acUnits=$units; $acHp=$hp; break; }
            }
            if ($acUnits === 0) {
                $items["central_ac_notice"] = [
                    "type"=>"central_ac_notice","product_id"=>null,
                    "name"=>"Central AC Required","unit"=>0,"qty"=>0,
                    "image_url"=>null,"brand"=>null,"vendor_name"=>null,
                    "avg_rating"=>null,"alternatives"=>[],
                    "is_notice"=>true,
                ];
            } else {
                $acPool = $byType["ac"] ?? [];
                $matching = array_filter($acPool, function($p) use ($acHp) {
                    $specs = $p["specs"] ?? [];
                    return abs((float)($specs["hp"]??0) - $acHp) < 0.01;
                });
                if (empty($matching)) $matching = $acPool;
                usort($matching, fn($a,$b) => (int)$a["price"] <=> (int)$b["price"]);
                $matching = array_values($matching);
                if (!empty($matching)) {
                    $rec = $matching[0];
                    $items["ac"] = [
                        "type"=>"ac","product_id"=>$rec["id"],"name"=>$rec["name"],
                        "unit"=>(int)$rec["price"],"qty"=>$acUnits,
                        "image_url"=>$rec["image_url"],"brand"=>$rec["brand"],
                        "vendor_name"=>$rec["vendor_name"],"avg_rating"=>$rec["avg_rating"],
                        "hp"=>$acHp,"ac_units"=>$acUnits,
                        "alternatives"=>build_alts($rec, $matching),
                    ];
                }
            }
        }

        $total = cart_total_api($items);
        $carts[$module] = [
            "module"  => $module,
            "label"   => $moduleLabels[$module] ?? $module,
            "cap"     => $cap,
            "total"   => $total,
            "remaining"=> max(0, $cap - $total),
            "over"    => max(0, $total - $cap),
            "items"   => array_values($items),
        ];
    }

    $grandTotal = array_sum(array_map(fn($c) => $c["total"], $carts));

    echo json_encode([
        "ok"          => true,
        "tier"        => $tier,
        "budget"      => $budget,
        "grand_total" => $grandTotal,
        "modules"     => array_keys($alloc),
        "carts"       => $carts,
        "area_sqm"    => $areaSqm,
    ]);

} catch (Throwable $e) {
    file_put_contents(__DIR__ . "/api_error.log",
        date("c") . " api_packages: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error"]);
} finally {
    ob_end_flush();
}