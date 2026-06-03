<?php
session_start();
require_once "db.php";
if (!isset($_SESSION["user_id"])) {
    http_response_code(401);
    echo json_encode(["error" => "Unauthorized"]);
    exit;
}
$business_id = (int)$_SESSION["user_id"];
$input = json_decode(file_get_contents("php://input"), true);
$role = trim($input["role"] ?? "");
$qty  = max(1, (int)($input["qty"] ?? 1));
$allowedRoles = ['waiter','chef','cashier','security','barista','busboy','host','kitchen_helper'];
if (!in_array($role, $allowedRoles)) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid role"]);
    exit;
}
// Get business location
$locRes = pg_query_params($conn,
    "SELECT delivery_location FROM orders WHERE business_user_id = $1 AND order_type = 'setup' AND payment_status = 'paid' ORDER BY id DESC LIMIT 1",
    [$business_id]);
$jobLocation = "Business Location";
if ($locRes && pg_num_rows($locRes) > 0) {
    $loc = pg_fetch_assoc($locRes)["delivery_location"] ?? "";
    if (trim($loc)) $jobLocation = trim($loc);
}
// Inherit salary from existing job of same role if exists
$salaryAmount     = 0;
$compensationType = "monthly";
$existingRes = pg_query_params($conn,
    "SELECT salary_amount, compensation_type FROM jobs
     WHERE business_id = $1 AND labor_role = $2 AND job_type = 'labor'
     LIMIT 1",
    [$business_id, $role]);
if ($existingRes && pg_num_rows($existingRes) > 0) {
    $row = pg_fetch_assoc($existingRes);
    $salaryAmount     = (int)$row["salary_amount"];
    $compensationType = $row["compensation_type"];
}
$roleLabel = ucfirst(str_replace("_", " ", $role));
$title       = $roleLabel . " Needed";
$description = $roleLabel . " added manually.";
pg_query($conn, "BEGIN");
try {
    for ($i = 0; $i < $qty; $i++) {
        $ok = pg_query_params($conn,
            "INSERT INTO jobs (business_id, title, description, location, salary_amount, compensation_type, status, price, worker_id, job_type, labor_role)
             VALUES ($1, $2, $3, $4, $5, $6, 'available', 0, NULL, 'labor', $7)",
            [$business_id, $title, $description, $jobLocation, $salaryAmount, $compensationType, $role]);
        if (!$ok) throw new Exception(pg_last_error($conn));
    }
    pg_query($conn, "COMMIT");
    echo json_encode(["ok" => true]);
} catch (Exception $e) {
    pg_query($conn, "ROLLBACK");
    http_response_code(500);
    echo json_encode(["error" => $e->getMessage()]);
}