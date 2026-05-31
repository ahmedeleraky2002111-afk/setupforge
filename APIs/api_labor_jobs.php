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
    $labor_id = (int)$user["id"];

    // Handle mark as completed
    if ($_SERVER["REQUEST_METHOD"] === "POST") {
        $input = json_decode(file_get_contents("php://input"), true);
        $job_id = (int)($input["job_id"] ?? 0);

        if ($job_id > 0) {
            $jobRes = pg_query_params($conn,
                "SELECT price, status FROM jobs WHERE job_id = $1 AND worker_id = $2 LIMIT 1",
                [$job_id, $labor_id]);

            if ($jobRes && pg_num_rows($jobRes) > 0) {
                $jobData = pg_fetch_assoc($jobRes);

                if ($jobData["status"] !== "completed") {
                    $price = (float)$jobData["price"];
                    $workerEarnings = $price * 0.90; // 10% platform cut

                    pg_query_params($conn,
                        "UPDATE jobs SET status = 'completed' WHERE job_id = $1",
                        [$job_id]);

                    pg_query_params($conn,
                        "UPDATE labors SET balance = balance + $1 WHERE user_id = $2",
                        [$workerEarnings, $labor_id]);

                    echo json_encode([
                        "ok" => true,
                        "message" => "Job completed! " . number_format($workerEarnings, 2) . " EGP added to your balance."
                    ]);
                } else {
                    echo json_encode(["ok" => false, "error" => "Job already completed"]);
                }
            } else {
                echo json_encode(["ok" => false, "error" => "Job not found"]);
            }
            exit;
        }
    }

    // GET — fetch jobs + stats + balance
    $laborRes = pg_query_params($conn,
        "SELECT provider_type, balance FROM labors WHERE user_id = $1 LIMIT 1",
        [$labor_id]);
    $laborRow = $laborRes ? pg_fetch_assoc($laborRes) : null;
    $provider_type = $laborRow["provider_type"] ?? "labor";
    $balance = number_format((float)($laborRow["balance"] ?? 0), 2);

    // Stats
    $statsRes = pg_query_params($conn,
        "SELECT
            COUNT(*) AS total_jobs,
            COUNT(*) FILTER (WHERE status = 'active') AS active_jobs,
            COUNT(*) FILTER (WHERE status = 'completed') AS completed_jobs
         FROM jobs WHERE worker_id = $1",
        [$labor_id]);
    $stats = $statsRes ? pg_fetch_assoc($statsRes) : [];

    // Jobs list
    $jobsRes = pg_query_params($conn,
        "SELECT job_id, title, location, description, price, salary_amount, status, created_at
         FROM jobs WHERE worker_id = $1 ORDER BY job_id DESC",
        [$labor_id]);
    $jobs = [];
    if ($jobsRes) {
        while ($r = pg_fetch_assoc($jobsRes)) $jobs[] = $r;
    }

    echo json_encode([
        "ok"            => true,
        "provider_type" => $provider_type,
        "balance"       => $balance,
        "total_jobs"    => (int)($stats["total_jobs"] ?? 0),
        "active_jobs"   => (int)($stats["active_jobs"] ?? 0),
        "completed_jobs"=> (int)($stats["completed_jobs"] ?? 0),
        "jobs"          => $jobs,
    ]);

} catch (Throwable $e) {
    file_put_contents(__DIR__ . "/api_error.log",
        date("c") . " api_labor_jobs: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error"]);
} finally {
    ob_end_flush();
}