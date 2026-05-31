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
        "SELECT id FROM users WHERE api_token = $1 LIMIT 1",
        [$token]);

    if (!$userRes || pg_num_rows($userRes) === 0) {
        echo json_encode(["ok" => false, "error" => "Unauthorized"]);
        exit;
    }

    $user_id = (int)pg_fetch_assoc($userRes)["id"];

    $bizRes = pg_query_params($conn, "
        SELECT business_name, business_type, restaurant_type,
               indoor_tables, outdoor_tables, area_sqm, budget_egp,
               modules, installation_services, staffing_data,
               setup_step, setup_status
        FROM businesses WHERE user_id = $1 LIMIT 1
    ", [$user_id]);

    if (!$bizRes || pg_num_rows($bizRes) === 0) {
        echo json_encode(["ok" => true, "has_saved" => false]);
        exit;
    }

    $biz = pg_fetch_assoc($bizRes);
    $savedStep = (int)($biz["setup_step"] ?? 0);
    $setupStatus = $biz["setup_status"] ?? "";

    if ($savedStep === 0 || $setupStatus !== "in_progress") {
        echo json_encode(["ok" => true, "has_saved" => false]);
        exit;
    }

    // Parse modules
    $modules = [];
    if (!empty($biz["modules"])) {
        $raw = trim($biz["modules"], '{}');
        $modules = $raw ? explode(',', $raw) : [];
    }

    // Parse installation services
    $installSvcs = [];
    if (!empty($biz["installation_services"])) {
        $raw = trim($biz["installation_services"], '{}');
        $installSvcs = $raw ? explode(',', $raw) : [];
    }

    // Parse staffing data
    $staffing = [];
    $services = [];
    $floorCount = 1;
    if (!empty($biz["staffing_data"])) {
        $staffing = json_decode($biz["staffing_data"], true) ?? [];
        $services = $staffing["services"] ?? [];
        $floorCount = (int)($staffing["floor_count"] ?? 1);
    }

    $wizard = [
        "business_name"         => $biz["business_name"] ?? "",
        "business_type"         => $biz["business_type"] ?? "",
        "restaurant_type"       => $biz["restaurant_type"] ?? "",
        "indoor_tables"         => (int)($biz["indoor_tables"] ?? 0),
        "outdoor_tables"        => (int)($biz["outdoor_tables"] ?? 0),
        "area_sqm"              => (int)($biz["area_sqm"] ?? 0),
        "budget"                => (int)($biz["budget_egp"] ?? 0),
        "modules"               => $modules,
        "installation_services" => $installSvcs,
        "floor_count"           => $floorCount,
        "services"              => $services,
    ];

    // Restore staff counts
    foreach (['waiter','chef','cashier','security','barista','busboy','host','kitchen_helper'] as $role) {
        $wizard[$role . "_count"] = (int)($staffing[$role] ?? 0);
    }

    echo json_encode([
        "ok"         => true,
        "has_saved"  => true,
        "saved_step" => $savedStep,
        "wizard"     => $wizard,
    ]);

} catch (Throwable $e) {
    file_put_contents(__DIR__ . "/api_error.log",
        date("c") . " api_wizard_resume: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error"]);
} finally {
    ob_end_flush();
}