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

function ensure_vendor_subscription_table_for_activation($conn): void {
  @pg_query($conn, "
    CREATE TABLE IF NOT EXISTS vendor_subscriptions (
      id SERIAL PRIMARY KEY,
      vendor_user_id INTEGER NOT NULL,
      plan_name VARCHAR(50) NOT NULL DEFAULT 'premium',
      status VARCHAR(20) NOT NULL DEFAULT 'pending',
      amount DECIMAL(10, 2) NOT NULL DEFAULT 400.00,
      starts_at TIMESTAMP WITH TIME ZONE,
      expires_at TIMESTAMP WITH TIME ZONE,
      payment_method VARCHAR(50),
      payment_reference VARCHAR(255),
      pending_charge DECIMAL(10, 2) DEFAULT 0.00,
      charge_deducted BOOLEAN DEFAULT FALSE,
      created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
      updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW()
    );
  ");

  @pg_query($conn, "
    ALTER TABLE vendor_subscriptions
      ADD COLUMN IF NOT EXISTS plan_name VARCHAR(50) NOT NULL DEFAULT 'premium',
      ADD COLUMN IF NOT EXISTS status VARCHAR(20) NOT NULL DEFAULT 'pending',
      ADD COLUMN IF NOT EXISTS amount DECIMAL(10, 2) NOT NULL DEFAULT 400.00,
      ADD COLUMN IF NOT EXISTS starts_at TIMESTAMP WITH TIME ZONE,
      ADD COLUMN IF NOT EXISTS expires_at TIMESTAMP WITH TIME ZONE,
      ADD COLUMN IF NOT EXISTS payment_method VARCHAR(50),
      ADD COLUMN IF NOT EXISTS payment_reference VARCHAR(255),
      ADD COLUMN IF NOT EXISTS pending_charge DECIMAL(10, 2) DEFAULT 0.00,
      ADD COLUMN IF NOT EXISTS charge_deducted BOOLEAN DEFAULT FALSE,
      ADD COLUMN IF NOT EXISTS created_at TIMESTAMP WITH TIME ZONE DEFAULT NOW(),
      ADD COLUMN IF NOT EXISTS updated_at TIMESTAMP WITH TIME ZONE DEFAULT NOW();
  ");

  @pg_query($conn, "CREATE INDEX IF NOT EXISTS idx_vendor_subscriptions_vendor_user_id ON vendor_subscriptions(vendor_user_id);");
  @pg_query($conn, "CREATE INDEX IF NOT EXISTS idx_vendor_subscriptions_status ON vendor_subscriptions(status);");
}

ensure_vendor_subscription_table_for_activation($conn);

$existingRes = pg_query_params($conn, "
  SELECT id
  FROM vendor_subscriptions
  WHERE vendor_user_id = $1
  ORDER BY id DESC
  LIMIT 1
", [$vendorId]);

$existing = $existingRes ? pg_fetch_assoc($existingRes) : null;

if ($existing) {
  $subscriptionId = (int)$existing["id"];
  $res = pg_query_params($conn, "
    UPDATE vendor_subscriptions
    SET
      plan_name = 'premium',
      status = 'active',
      amount = $1,
      starts_at = NOW(),
      expires_at = NOW() + INTERVAL '30 days',
      payment_method = 'deduct_from_next_sale',
      payment_reference = 'next_sale_deduction',
      pending_charge = $1,
      charge_deducted = FALSE,
      updated_at = NOW()
    WHERE id = $2 AND vendor_user_id = $3
  ", [$subscriptionCharge, $subscriptionId, $vendorId]);
} else {
  $res = pg_query_params($conn, "
    INSERT INTO vendor_subscriptions
      (vendor_user_id, plan_name, status, amount, starts_at, expires_at, payment_method, payment_reference, pending_charge, charge_deducted, created_at, updated_at)
    VALUES
      ($1, 'premium', 'active', $2, NOW(), NOW() + INTERVAL '30 days', 'deduct_from_next_sale', 'next_sale_deduction', $2, FALSE, NOW(), NOW())
  ", [$vendorId, $subscriptionCharge]);
}

if (!$res) {
  header("Location: vendor_subscription.php?error=" . urlencode("Could not activate subscription. Please try again."));
  exit;
}

header("Location: vendor_subscription.php?subscription=activated&charge=next_sale");
exit;
