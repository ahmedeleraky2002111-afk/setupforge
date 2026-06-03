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
$subscriptionCharge = 400.00;

// Make sure the needed columns exist on old tables.
pg_query($conn, "
  ALTER TABLE vendor_subscriptions
    ADD COLUMN IF NOT EXISTS pending_charge DECIMAL(10, 2) DEFAULT 0.00,
    ADD COLUMN IF NOT EXISTS charge_deducted BOOLEAN DEFAULT FALSE;
");

// Cancelling does not remove the current month charge.
// If the subscription charge has not been deducted yet, keep/set the pending 400 EGP charge.
$res = pg_query_params($conn, "
  UPDATE vendor_subscriptions
  SET
    status = 'cancelled',
    pending_charge = CASE
      WHEN COALESCE(charge_deducted, FALSE) = TRUE THEN COALESCE(pending_charge, 0)
      WHEN COALESCE(pending_charge, 0) >= $2 THEN COALESCE(pending_charge, 0)
      ELSE $2
    END,
    charge_deducted = COALESCE(charge_deducted, FALSE),
    updated_at = NOW()
  WHERE vendor_user_id = $1
", [$vendorId, $subscriptionCharge]);

if (!$res) {
  header("Location: vendor_subscription.php?error=" . urlencode("Could not cancel subscription."));
  exit;
}

header("Location: vendor_subscription.php?subscription=cancelled&charge=current_month");
exit;
