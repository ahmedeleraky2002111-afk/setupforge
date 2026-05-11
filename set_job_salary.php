<?php
session_start();
require_once "db.php";

header('Content-Type: application/json');

if (!isset($_SESSION["user_id"])) {
  echo json_encode(["ok" => false, "error" => "Unauthorized"]);
  exit;
}

$business_id = (int)$_SESSION["user_id"];
$input = json_decode(file_get_contents("php://input"), true);

$title            = trim((string)($input["title"] ?? ""));
$location         = trim((string)($input["location"] ?? ""));
$salary_amount    = max(0, (int)($input["salary_amount"] ?? 0));
$compensation_type = trim((string)($input["compensation_type"] ?? "monthly"));

if ($title === "" || $location === "") {
  echo json_encode(["ok" => false, "error" => "Missing title or location"]);
  exit;
}

if (!in_array($compensation_type, ["hourly", "daily", "monthly"])) {
  $compensation_type = "monthly";
}

$res = pg_query_params($conn, "
  UPDATE jobs
  SET salary_amount = $1, compensation_type = $2
  WHERE business_id = $3 AND title = $4 AND location = $5 AND job_type = 'labor'
", [$salary_amount, $compensation_type, $business_id, $title, $location]);

if (!$res) {
  echo json_encode(["ok" => false, "error" => "DB error: " . pg_last_error($conn)]);
  exit;
}

echo json_encode(["ok" => true]);