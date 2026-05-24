<?php
// save_user_location.php
if (session_status() === PHP_SESSION_NONE) session_start();
require_once "db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["ok" => false, "error" => "Not logged in."]);
    exit;
}

$userId   = (int)$_SESSION["user_id"];
$location = trim((string)($_POST["location"] ?? ""));

// Parse location string back into city field
// We store the full string in city for simplicity
$ok = pg_query_params($conn,
    "UPDATE users SET city = \$1 WHERE id = \$2",
    [$location, $userId]
);

echo json_encode(["ok" => (bool)$ok]);
exit;