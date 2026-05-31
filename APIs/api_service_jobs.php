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
        "SELECT id FROM users WHERE api_token = $1 AND user_type = 'business' LIMIT 1",
        [$token]);

    if (!$userRes || pg_num_rows($userRes) === 0) {
        echo json_encode(["ok" => false, "error" => "Unauthorized"]);
        exit;
    }

    $business_id = (int)pg_fetch_assoc($userRes)["id"];

    // Read selected services from staffing_data
    $bizRes = pg_query_params($conn,
        "SELECT staffing_data FROM businesses WHERE user_id = $1 LIMIT 1",
        [$business_id]);

    $bizServices = [];
    if ($bizRes && pg_num_rows($bizRes) > 0) {
        $brow = pg_fetch_assoc($bizRes);
        if (!empty($brow["staffing_data"])) {
            $staffingData = json_decode($brow["staffing_data"], true);
            $bizServices = $staffingData["services"] ?? [];
        }
    }

    // Tab visibility — same logic as service_jobs.php
    $showLabor        = empty($bizServices) || in_array('staff',        $bizServices);
    $showInstallation = empty($bizServices) || in_array('installation', $bizServices);
    $showFinishing    = empty($bizServices) || in_array('finishing',     $bizServices);
    $showAdvertising  = empty($bizServices) || in_array('advertising',  $bizServices);

    // ── LABOR ─────────────────────────────────────────────────────────────────
    $laborRoles = [];
    $applicantsMap = [];
    $hiredMap = [];
    $totalApplicants = 0;

    if ($showLabor) {
        $laborRes = pg_query_params($conn, "
            SELECT title, location,
                   MIN(description) AS description,
                   COUNT(*) AS total_openings,
                   COUNT(*) FILTER (WHERE worker_id IS NULL) AS openings_left,
                   COUNT(*) FILTER (WHERE worker_id IS NOT NULL) AS filled_openings
            FROM jobs
            WHERE business_id = $1 AND job_type = 'labor'
            GROUP BY title, location
            ORDER BY title ASC
        ", [$business_id]);

        if ($laborRes) {
            while ($r = pg_fetch_assoc($laborRes)) $laborRoles[] = $r;
        }

        if (!empty($laborRoles)) {
            $appsRes = pg_query_params($conn, "
                SELECT ja.id AS application_id, ja.labor_user_id,
                       j.title, j.location, j.job_id,
                       l.name AS worker_name, l.experience_level,
                       l.avg_rating, l.hourly_rate, l.skills,
                       l.profile_picture, l.availability_status,
                       l.military_status, l.dob, l.labor_role,
                       ja.status AS app_status
                FROM job_applications ja
                JOIN jobs j ON ja.job_id = j.job_id
                JOIN labors l ON ja.labor_user_id = l.user_id
                WHERE j.business_id = $1 AND j.job_type = 'labor'
                ORDER BY j.title ASC, ja.applied_at ASC
            ", [$business_id]);

            if ($appsRes) {
                while ($row = pg_fetch_assoc($appsRes)) {
                    $key = $row["title"] . '||' . $row["location"];
                    if ($row["app_status"] === 'accepted') {
                        $hiredMap[$key][] = $row;
                    } else {
                        $applicantsMap[$key][] = $row;
                    }
                }
            }

            foreach ($applicantsMap as $list) $totalApplicants += count($list);
        }

        // Salary info per role
        foreach ($laborRoles as &$role) {
            $salRes = pg_query_params($conn,
                "SELECT salary_amount, compensation_type FROM jobs
                 WHERE business_id = $1 AND title = $2 AND location = $3
                 AND job_type = 'labor' LIMIT 1",
                [$business_id, $role["title"], $role["location"]]);
            $salRow = ($salRes && pg_num_rows($salRes) > 0)
                ? pg_fetch_assoc($salRes) : null;
            $role["salary_amount"]     = $salRow ? (int)$salRow["salary_amount"] : 0;
            $role["compensation_type"] = $salRow ? $salRow["compensation_type"] : "monthly";
            $role["applicants"]        = $applicantsMap[$role["title"] . '||' . $role["location"]] ?? [];
            $role["hired"]             = $hiredMap[$role["title"] . '||' . $role["location"]] ?? [];
        }
        unset($role);
    }

    // ── INSTALLATION ──────────────────────────────────────────────────────────
    $installationRows = [];

    if ($showInstallation) {
        // Get order for AC/kitchen/POS breakdown
        $orderRes = pg_query_params($conn,
            "SELECT id, installation_data FROM orders
             WHERE business_user_id = $1 AND order_type = 'setup'
             ORDER BY id DESC LIMIT 1",
            [$business_id]);

        $orderId  = null;
        $instData = [];
        $acUnits  = 1;
        $acHp     = 1.5;

        if ($orderRes && pg_num_rows($orderRes) > 0) {
            $orderRow = pg_fetch_assoc($orderRes);
            $orderId  = (int)$orderRow["id"];
            $instData = json_decode($orderRow["installation_data"], true) ?? [];
            $acUnits  = max(1, (int)($instData["ac_units"] ?? 1));
            $acHp     = (float)($instData["ac_hp"] ?? 1.5);
        }

        // AC rates
        $acRatesMap = [];
        if ($acHp) {
            $ratesRes = pg_query_params($conn,
                "SELECT company_id, rate_per_unit FROM company_ac_rates WHERE hp = $1",
                [$acHp]);
            if ($ratesRes) {
                while ($r = pg_fetch_assoc($ratesRes))
                    $acRatesMap[(int)$r["company_id"]] = (int)$r["rate_per_unit"];
            }
        }

        // Order items for breakdown
        $orderItems = [];
        if ($orderId) {
            $itemsRes = pg_query_params($conn, "
                SELECT oi.product_id, oi.quantity, p.product_name,
                       p.product_type, p.module, p.specs
                FROM order_items oi
                JOIN products p ON p.id = oi.product_id
                WHERE oi.order_id = $1 AND p.module IN ('kitchen','pos','ac')
            ", [$orderId]);
            if ($itemsRes) while ($r = pg_fetch_assoc($itemsRes)) $orderItems[] = $r;
        }

        // Kitchen rates
        $kitchenRatesAll = [];
        $krRes = pg_query($conn, "SELECT company_id, product_type, rate FROM company_kitchen_rates");
        if ($krRes) while ($r = pg_fetch_assoc($krRes))
            $kitchenRatesAll[(int)$r["company_id"]][$r["product_type"]] = (int)$r["rate"];

        // POS rates
        $posRatesAll = [];
        $prRes = pg_query($conn, "SELECT company_id, terminal_type, rate FROM company_pos_rates");
        if ($prRes) while ($r = pg_fetch_assoc($prRes))
            $posRatesAll[(int)$r["company_id"]][$r["terminal_type"]] = (int)$r["rate"];

        // Build breakdowns
        $kitchenBreakdown = [];
        $posBreakdown     = [];

        foreach ($orderItems as $item) {
            $ptype = strtolower(trim($item["product_type"]));
            $qty   = (int)$item["quantity"];
            $name  = $item["product_name"];

            $imgRes = pg_query_params($conn,
                "SELECT image_url FROM product_images WHERE product_id = $1 LIMIT 1",
                [$item["product_id"]]);
            $image = ($imgRes && pg_num_rows($imgRes) > 0)
                ? pg_fetch_assoc($imgRes)["image_url"] : null;

            if ($item["module"] === "kitchen") {
                foreach ($kitchenRatesAll as $cid => $rates) {
                    $rate = $rates[$ptype] ?? null;
                    if (!$rate) continue;
                    $kitchenBreakdown[$cid][] = [
                        "name" => $name, "qty" => $qty,
                        "rate" => $rate, "subtotal" => $rate * $qty,
                        "image" => $image,
                    ];
                }
            }

            if ($item["module"] === "pos") {
                foreach ($posRatesAll as $cid => $rates) {
                    $rate = $rates[$ptype] ?? null;
                    if (!$rate) continue;
                    $posBreakdown[$cid][] = [
                        "name" => $name, "qty" => $qty,
                        "rate" => $rate, "subtotal" => $rate * $qty,
                        "image" => $image,
                    ];
                }
            }
        }

        // Installation requests
        $irRes = pg_query_params($conn, "
            SELECT r.request_id, r.services, r.status, r.scheduled_date
            FROM installation_requests r
            WHERE r.user_id = $1
            ORDER BY r.created_at DESC
        ", [$business_id]);

        if ($irRes) {
            while ($req = pg_fetch_assoc($irRes)) {
                $req_id     = (int)$req["request_id"];
                $serviceKey = trim(explode(',', trim($req["services"], '{}'))[0]);

                // Companies for this service
                $companiesRes = pg_query_params($conn, "
                    SELECT c.company_id, c.company_name, c.description,
                           c.starting_from, c.website, c.avg_rating,
                           c.phone, c.location, c.image,
                           q.quote_id, q.price, q.message,
                           q.website_link, q.status AS quote_status
                    FROM companies c
                    LEFT JOIN installation_quotes q
                      ON q.company_id = c.company_id AND q.request_id = $1
                    WHERE c.services::text ILIKE $2
                      AND c.availability_status = 'available'
                    ORDER BY q.price ASC NULLS LAST, c.company_id ASC
                ", [$req_id, '%' . $serviceKey . '%']);

                $companies = [];
                if ($companiesRes) {
                    while ($co = pg_fetch_assoc($companiesRes)) {
                        $cid = (int)$co["company_id"];

                        // Build breakdown
                        $breakdownLines = [];
                        if ($serviceKey === 'ac') {
                            $rateToUse = $acRatesMap[$cid] ?? 700;
                            $acItems = array_filter($orderItems, fn($i) => $i['module'] === 'ac');
                            foreach ($acItems as $acItem) {
                                $aImg = null;
                                $aiRes = pg_query_params($conn,
                                    "SELECT image_url FROM product_images WHERE product_id = $1 LIMIT 1",
                                    [$acItem['product_id']]);
                                if ($aiRes && pg_num_rows($aiRes) > 0)
                                    $aImg = pg_fetch_assoc($aiRes)["image_url"];
                                $breakdownLines[] = [
                                    "name"     => $acItem['product_name'],
                                    "qty"      => (int)$acItem['quantity'],
                                    "rate"     => $rateToUse,
                                    "subtotal" => $rateToUse * (int)$acItem['quantity'],
                                    "image"    => $aImg,
                                ];
                            }
                            $displayPrice = $rateToUse * $acUnits;
                        } elseif ($serviceKey === 'kitchen') {
                            $breakdownLines = $kitchenBreakdown[$cid] ?? [];
                            $displayPrice = $breakdownLines
                                ? array_sum(array_column($breakdownLines, 'subtotal'))
                                : (int)($co["starting_from"] ?? 0);
                        } elseif ($serviceKey === 'pos') {
                            $breakdownLines = $posBreakdown[$cid] ?? [];
                            $displayPrice = $breakdownLines
                                ? array_sum(array_column($breakdownLines, 'subtotal'))
                                : (int)($co["starting_from"] ?? 0);
                        } else {
                            $displayPrice = (int)($co["starting_from"] ?? 0);
                        }

                        $co["breakdown_lines"] = $breakdownLines;
                        $co["display_price"]   = $displayPrice;
                        $co["service_key"]     = $serviceKey;
                        $companies[] = $co;
                    }
                }

                $req["companies"]   = $companies;
                $req["service_key"] = $serviceKey;
                $installationRows[] = $req;
            }
        }
    }

    // ── FINISHING ─────────────────────────────────────────────────────────────
    $finishingReq  = null;
    $finishingList = [];

    if ($showFinishing) {
        $fReqRes = pg_query_params($conn,
            "SELECT request_id, area_sqm, finishing_types, status, scheduled_date
             FROM finishing_requests WHERE user_id = $1
             ORDER BY created_at DESC LIMIT 1",
            [$business_id]);

        if ($fReqRes && pg_num_rows($fReqRes) > 0) {
            $finishingReq = pg_fetch_assoc($fReqRes);
            $fReqId = (int)$finishingReq["request_id"];

            $fCompRes = pg_query($conn, "
                SELECT c.company_id, c.company_name, c.description,
                       c.starting_from, c.website, c.avg_rating,
                       c.location, c.image, c.specialties,
                       q.quote_id, q.price, q.message,
                       q.website_link, q.status AS quote_status
                FROM companies c
                LEFT JOIN finishing_quotes q
                  ON q.company_id = c.company_id AND q.request_id = {$fReqId}
                WHERE c.services::text ILIKE '%finishing%'
                  AND c.availability_status = 'available'
                  AND c.status = 'active'
                ORDER BY q.price ASC NULLS LAST, c.company_id ASC
            ");
            if ($fCompRes) while ($r = pg_fetch_assoc($fCompRes)) $finishingList[] = $r;
        }
    }

    // ── ADVERTISING ───────────────────────────────────────────────────────────
    $advertisingList = [];

    if ($showAdvertising) {
        $advRes = pg_query($conn, "
            SELECT company_id, company_name, description,
                   starting_from, website, avg_rating, location, image
            FROM companies
            WHERE services::text ILIKE '%advertising%'
              AND availability_status = 'available'
              AND status = 'active'
            ORDER BY company_id ASC
        ");
        if ($advRes) while ($r = pg_fetch_assoc($advRes)) $advertisingList[] = $r;
    }

    echo json_encode([
        "ok"                  => true,
        "show_labor"          => $showLabor,
        "show_installation"   => $showInstallation,
        "show_finishing"      => $showFinishing,
        "show_advertising"    => $showAdvertising,
        "total_applicants"    => $totalApplicants,
        "total_installation"  => count($installationRows),
        "total_finishing"     => count($finishingList),
        "total_advertising"   => count($advertisingList),
        "labor_roles"         => $laborRoles,
        "installation_rows"   => $installationRows,
        "finishing_req"       => $finishingReq,
        "finishing_list"      => $finishingList,
        "advertising_list"    => $advertisingList,
    ]);

} catch (Throwable $e) {
    file_put_contents(__DIR__ . "/api_error.log",
        date("c") . " api_service_jobs: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error"]);
} finally {
    ob_end_flush();
}