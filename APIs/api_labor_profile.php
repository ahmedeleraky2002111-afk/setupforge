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
        "SELECT id, name FROM users WHERE api_token = $1 AND user_type = 'labor' LIMIT 1",
        [$token]);

    if (!$userRes || pg_num_rows($userRes) === 0) {
        echo json_encode(["ok" => false, "error" => "Unauthorized"]);
        exit;
    }

    $user_id = (int)pg_fetch_assoc($userRes)["id"];

    // User + labor info
    $profileRes = pg_query_params($conn, "
        SELECT u.email, u.phone, l.name, l.profile_picture,
               l.skills, l.provider_type, l.avg_rating, l.balance,
               l.experience_level, l.labor_role, l.hourly_rate,
               l.availability_status, l.military_status
        FROM users u
        INNER JOIN labors l ON l.user_id = u.id
        WHERE u.id = $1 LIMIT 1
    ", [$user_id]);

    if (!$profileRes || pg_num_rows($profileRes) === 0) {
        echo json_encode(["ok" => false, "error" => "Profile not found"]);
        exit;
    }

    $profile = pg_fetch_assoc($profileRes);

    // Total earnings
    $earningsRes = pg_query_params($conn,
        "SELECT COALESCE(SUM(price), 0) AS total
         FROM jobs WHERE worker_id = $1 AND status = 'completed'",
        [$user_id]);
    $totalEarnings = $earningsRes
        ? (float)pg_fetch_assoc($earningsRes)["total"] : 0;

    // Active jobs count
    $activeRes = pg_query_params($conn,
        "SELECT COUNT(*) AS cnt FROM jobs
         WHERE worker_id = $1 AND status IN ('assigned','in_progress','accepted','active')",
        [$user_id]);
    $activeJobs = $activeRes
        ? (int)pg_fetch_assoc($activeRes)["cnt"] : 0;

    echo json_encode([
        "ok"                  => true,
        "name"                => $profile["name"],
        "email"               => $profile["email"],
        "phone"               => $profile["phone"] ?? "",
        "skills"              => $profile["skills"] ?? "",
        "provider_type"       => $profile["provider_type"] ?? "labor",
        "avg_rating"          => (float)($profile["avg_rating"] ?? 0),
        "balance"             => number_format((float)($profile["balance"] ?? 0), 2),
        "experience_level"    => $profile["experience_level"] ?? "",
        "labor_role"          => $profile["labor_role"] ?? "",
        "hourly_rate"         => (float)($profile["hourly_rate"] ?? 0),
        "availability_status" => $profile["availability_status"] ?? "available",
        "military_status"     => $profile["military_status"] ?? "",
        "profile_picture"     => $profile["profile_picture"] ?? "",
        "total_earnings"      => number_format($totalEarnings, 2),
        "active_jobs"         => $activeJobs,
    ]);

} catch (Throwable $e) {
    file_put_contents(__DIR__ . "/api_error.log",
        date("c") . " api_labor_profile: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error"]);
} finally {
    ob_end_flush();
}