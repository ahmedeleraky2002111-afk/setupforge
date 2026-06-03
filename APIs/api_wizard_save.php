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
        "SELECT id, user_type FROM users WHERE api_token = $1 LIMIT 1",
        [$token]);

    if (!$userRes || pg_num_rows($userRes) === 0) {
        echo json_encode(["ok" => false, "error" => "Unauthorized"]);
        exit;
    }

    $userRow = pg_fetch_assoc($userRes);
    $user_id = (int)$userRow["id"];
    $userType = $userRow["user_type"];

    $input = json_decode(file_get_contents("php://input"), true);
    $step = (int)($input["step"] ?? 0);
    $wizard = $input["wizard"] ?? [];

    // Upgrade customer to business on step 0
    if ($step === 0 && $userType === "customer") {
        pg_query_params($conn,
            "UPDATE users SET user_type = 'business' WHERE id = $1",
            [$user_id]);
    }

    // Extract wizard fields
    $businessName   = trim($wizard["business_name"] ?? "");
    $businessType   = $wizard["business_type"] ?? null;
    $restaurantType = $wizard["restaurant_type"] ?? null;
    $indoorTables   = isset($wizard["indoor_tables"]) ? (int)$wizard["indoor_tables"] : null;
    $outdoorTables  = isset($wizard["outdoor_tables"]) ? (int)$wizard["outdoor_tables"] : null;
    $areaSqm        = isset($wizard["area_sqm"]) ? (int)$wizard["area_sqm"] : null;
    $floorCount     = isset($wizard["floor_count"]) ? (int)$wizard["floor_count"] : 1;
    $budget         = isset($wizard["budget"]) ? (int)$wizard["budget"] : null;
    $services       = $wizard["services"] ?? [];
    $seatCount      = (($indoorTables ?? 0) + ($outdoorTables ?? 0)) * 4;

    $modules = $wizard["modules"] ?? [];
    $modulesStr = !empty($modules)
        ? '{' . implode(',', array_map('pg_escape_string', $modules)) . '}'
        : null;

    $installSvcs = $wizard["installation_services"] ?? [];
    $installStr = !empty($installSvcs)
        ? '{' . implode(',', array_map('pg_escape_string', $installSvcs)) . '}'
        : null;

    // Build staffing JSON
    $staffing = [];
    foreach (['waiter','chef','cashier','security','barista','busboy','host','kitchen_helper'] as $role) {
        $staffing[$role] = (int)($wizard[$role . "_count"] ?? 0);
    }
    $staffing['floor_count'] = $floorCount;
    $staffing['services'] = $services;
    $staffingJson = json_encode($staffing);

    $status = $step >= 7 ? 'completed' : 'in_progress';

    // Check if business row exists
    $check = pg_query_params($conn,
        "SELECT user_id FROM businesses WHERE user_id = $1", [$user_id]);
    $exists = ($check && pg_num_rows($check) > 0);

    if ($exists) {
        pg_query_params($conn, "
            UPDATE businesses SET
                business_name = CASE WHEN $2 IS NOT NULL AND $2 <> '' THEN $2 ELSE business_name END,
                business_type         = COALESCE($3, business_type),
                restaurant_type       = $4,
                indoor_tables         = $5,
                outdoor_tables        = $6,
                area_sqm              = $7,
                budget_egp            = COALESCE($8, budget_egp),
                seat_count            = $9,
                modules               = $10,
                installation_services = $11,
                staffing_data         = $12,
                setup_step            = $13,
                setup_status          = $14,
                updated_at            = NOW()
            WHERE user_id = $1
        ", [
            $user_id,
            $businessName !== '' ? $businessName : null,
            $businessType,
            $restaurantType,
            $indoorTables,
            $outdoorTables,
            $areaSqm,
            $budget ?: null,
            $seatCount,
            $modulesStr,
            $installStr,
            $staffingJson,
            $step,
            $status,
        ]);
    } else {
        pg_query_params($conn, "
            INSERT INTO businesses (
                user_id, business_name, business_type, restaurant_type,
                indoor_tables, outdoor_tables, area_sqm, budget_egp, seat_count,
                modules, installation_services, staffing_data,
                setup_step, setup_status, status, updated_at
            ) VALUES (
                $1,$2,$3,$4,$5,$6,$7,$8,$9,$10,$11,$12,$13,$14,'pending',NOW()
            )
        ", [
            $user_id,
            $businessName ?: null,
            $businessType,
            $restaurantType,
            $indoorTables,
            $outdoorTables,
            $areaSqm,
            $budget ?: null,
            $seatCount,
            $modulesStr,
            $installStr,
            $staffingJson,
            $step,
            $status,
        ]);
    }

    // Force save business name directly (fix for NULL bug)
    if ($businessName !== '') {
        pg_query_params($conn,
            "UPDATE businesses SET business_name = $1 WHERE user_id = $2",
            [$businessName, $user_id]);
    }

    echo json_encode(["ok" => true, "step" => $step, "status" => $status]);

} catch (Throwable $e) {
    file_put_contents(__DIR__ . "/api_error.log",
        date("c") . " api_wizard_save: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error"]);
} finally {
    ob_end_flush();
}