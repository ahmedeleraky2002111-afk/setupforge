<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] !== "company") {
    header("Location: ../auth/login.php");
    exit();
}

$company_id = (int)$_POST["company_id"] ?? 0;
$request_id = (int)$_POST["request_id"] ?? 0;
$price      = (float)($_POST["price"] ?? 0);
$message    = trim($_POST["message"] ?? "");
$website    = trim($_POST["website_link"] ?? "");

if ($company_id <= 0 || $request_id <= 0 || $price <= 0) {
    header("Location: company_dashboard.php");
    exit();
}

// Check not already quoted
$exists = pg_query_params($conn,
    "SELECT quote_id FROM finishing_quotes WHERE request_id = $1 AND company_id = $2 LIMIT 1",
    [$request_id, $company_id]);

if ($exists && pg_num_rows($exists) === 0) {
    pg_query_params($conn,
        "INSERT INTO finishing_quotes (request_id, company_id, price, message, website_link, status)
         VALUES ($1, $2, $3, $4, $5, 'pending')",
        [$request_id, $company_id, $price, $message, $website]);
}

header("Location: company_dashboard.php");
exit();
?>