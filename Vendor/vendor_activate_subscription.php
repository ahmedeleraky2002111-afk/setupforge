<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? "") !== "vendor") {
  header("Location: ../auth/login.php?error=" . urlencode("Please login as vendor."));
  exit;
}

if ($_SERVER["REQUEST_METHOD"] !== "POST") {
  header("Location: vendor_subscription.php");
  exit;
}

if (!isset($conn) || !$conn) {
  http_response_code(500);
  die("DB connection missing. Check db.php (\$conn).");
}

$vendorId = (int)$_SESSION["user_id"];

$tableCheck = pg_query($conn, "SELECT to_regclass('public.vendor_subscriptions') AS table_name");
$tableRow = $tableCheck ? pg_fetch_assoc($tableCheck) : null;
if (empty($tableRow["table_name"])) {
  header("Location: vendor_subscription.php?error=" . urlencode("Subscription table missing. Run vendor_subscription_schema.sql first."));
  exit;
}

$res = pg_query_params($conn, "
  INSERT INTO vendor_subscriptions
    (vendor_user_id, plan_name, status, amount, starts_at, expires_at, payment_method, payment_reference, created_at, updated_at)
  VALUES
    ($1, 'premium', 'active', 2000, NOW(), NOW() + INTERVAL '30 days', 'manual', NULL, NOW(), NOW())
  ON CONFLICT (vendor_user_id)
  DO UPDATE SET
    plan_name = EXCLUDED.plan_name,
    status = EXCLUDED.status,
    amount = EXCLUDED.amount,
    starts_at = NOW(),
    expires_at = NOW() + INTERVAL '30 days',
    payment_method = EXCLUDED.payment_method,
    payment_reference = EXCLUDED.payment_reference,
    updated_at = NOW()
", [$vendorId]);

if (!$res) {
  header("Location: vendor_subscription.php?error=" . urlencode("Could not activate subscription."));
  exit;
}

header("Location: vendor_subscription.php?subscription=activated");
exit;
