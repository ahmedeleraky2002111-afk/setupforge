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

    $user = pg_fetch_assoc($userRes);
    $worker_id = (int)$user["id"];
    $userName = $user["name"];

    // Get labor profile
    $laborRes = pg_query_params($conn,
        "SELECT provider_type, availability_status, labor_role
         FROM labors WHERE user_id = $1 LIMIT 1",
        [$worker_id]);

    $laborRow = $laborRes ? pg_fetch_assoc($laborRes) : null;
    $provider_type       = $laborRow["provider_type"] ?? "labor";
    $availability_status = $laborRow["availability_status"] ?? "available";
    $labor_role          = strtolower(trim($laborRow["labor_role"] ?? ""));
    $job_type = ($provider_type === "technician") ? "technician" : "labor";

    // Available jobs matching labor role
    $availableJobsRes = pg_query_params($conn,
        "SELECT COUNT(*) FROM jobs
         WHERE job_type = 'labor' AND status = 'available'
         AND LOWER(labor_role) = $1",
        [$labor_role]);
    $availableJobs = $availableJobsRes ? (int)pg_fetch_result($availableJobsRes, 0, 0) : 0;

    // Completed jobs
    $completedRes = pg_query_params($conn,
        "SELECT COUNT(*) FROM jobs
         WHERE worker_id = $1 AND job_type = $2 AND status = 'completed'",
        [$worker_id, $job_type]);
    $completedJobs = $completedRes ? (int)pg_fetch_result($completedRes, 0, 0) : 0;

    // Pending jobs
    $pendingRes = pg_query_params($conn,
        "SELECT COUNT(*) FROM jobs
         WHERE worker_id = $1 AND job_type = $2
         AND status IN ('active','assigned','processing')",
        [$worker_id, $job_type]);
    $pendingJobs = $pendingRes ? (int)pg_fetch_result($pendingRes, 0, 0) : 0;

    // Total assigned
    $totalRes = pg_query_params($conn,
        "SELECT COUNT(*) FROM jobs
         WHERE worker_id = $1 AND job_type = $2",
        [$worker_id, $job_type]);
    $totalAssigned = $totalRes ? (int)pg_fetch_result($totalRes, 0, 0) : 0;

    // Completion rate
    $completionRate = $totalAssigned > 0
        ? round(($completedJobs / $totalAssigned) * 100) : 0;

    // Active jobs
    $activeRes = pg_query_params($conn,
        "SELECT job_id, title, location, status, salary_amount, created_at
         FROM jobs
         WHERE worker_id = $1 AND job_type = $2
         AND status IN ('active','assigned','processing')
         ORDER BY job_id DESC LIMIT 4",
        [$worker_id, $job_type]);
    $activeJobs = [];
    if ($activeRes) {
        while ($r = pg_fetch_assoc($activeRes)) $activeJobs[] = $r;
    }

    // Recent jobs
    $recentRes = pg_query_params($conn,
        "SELECT job_id, title, location, status, salary_amount, created_at
         FROM jobs
         WHERE worker_id = $1 AND job_type = $2
         ORDER BY job_id DESC LIMIT 6",
        [$worker_id, $job_type]);
    $recentJobs = [];
    if ($recentRes) {
        while ($r = pg_fetch_assoc($recentRes)) $recentJobs[] = $r;
    }

    // Notifications
    $notifications = [];
    if ($availability_status !== "available") {
        $notifications[] = "Your availability is currently set to " . ucfirst($availability_status) . ".";
    }
    if ($availableJobs > 0) {
        $notifications[] = "There are {$availableJobs} available jobs matching your role.";
    }
    if ($pendingJobs > 0) {
        $notifications[] = "You have {$pendingJobs} active job" . ($pendingJobs > 1 ? "s" : "") . " in progress.";
    }
    if ($completedJobs === 0) {
        $notifications[] = "Complete your first job to grow your dashboard stats.";
    }

    echo json_encode([
        "ok"                  => true,
        "user_name"           => $userName,
        "provider_type"       => $provider_type,
        "availability_status" => $availability_status,
        "available_jobs"      => $availableJobs,
        "completed_jobs"      => $completedJobs,
        "pending_jobs"        => $pendingJobs,
        "total_assigned"      => $totalAssigned,
        "completion_rate"     => $completionRate,
        "active_jobs"         => $activeJobs,
        "recent_jobs"         => $recentJobs,
        "notifications"       => $notifications,
    ]);

} catch (Throwable $e) {
    file_put_contents(__DIR__ . "/api_error.log",
        date("c") . " api_labor_dashboard: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error"]);
} finally {
    ob_end_flush();
}   