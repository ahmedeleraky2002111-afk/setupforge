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
        "SELECT id FROM users WHERE api_token = $1 AND user_type = 'labor' LIMIT 1",
        [$token]);

    if (!$userRes || pg_num_rows($userRes) === 0) {
        echo json_encode(["ok" => false, "error" => "Unauthorized"]);
        exit;
    }

    $user_id = (int)pg_fetch_assoc($userRes)["id"];

    $input = json_decode(file_get_contents("php://input"), true);

    $name         = trim($input["name"] ?? "");
    $phone        = trim($input["phone"] ?? "");
    $country      = trim($input["country"] ?? "");
    $city         = trim($input["city"] ?? "");
    $street       = trim($input["street"] ?? "");
    $skills       = trim($input["skills"] ?? "");
    $hourly_rate  = (float)($input["hourly_rate"] ?? 0);
    $labor_role   = trim($input["labor_role"] ?? "");
    $availability = trim($input["availability_status"] ?? "available");

    if (!in_array($availability, ["available", "busy", "unavailable"])) {
        $availability = "available";
    }

    if ($name === "") {
        echo json_encode(["ok" => false, "error" => "Name is required"]);
        exit;
    }

    pg_query($conn, "BEGIN");

    $r1 = pg_query_params($conn,
        "UPDATE users SET name=$1, phone=$2, country=$3, city=$4, street=$5 WHERE id=$6",
        [$name, $phone ?: null, $country ?: null, $city ?: null, $street ?: null, $user_id]);

    $r2 = pg_query_params($conn,
        "UPDATE labors SET skills=$1, hourly_rate=$2, labor_role=$3, availability_status=$4, name=$5 WHERE user_id=$6",
        [$skills ?: null, $hourly_rate, $labor_role ?: null, $availability, $name, $user_id]);

    if ($r1 && $r2) {
        pg_query($conn, "COMMIT");
        echo json_encode(["ok" => true, "message" => "Profile updated successfully"]);
    } else {
        pg_query($conn, "ROLLBACK");
        echo json_encode(["ok" => false, "error" => "Update failed"]);
    }

} catch (Throwable $e) {
    file_put_contents(__DIR__ . "/api_error.log",
        date("c") . " api_labor_edit_profile: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error"]);
} finally {
    ob_end_flush();
}