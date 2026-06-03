<?php
/**
 * deduct_subscription_charge.php
 *
 * Include this file in the place where you calculate the vendor payout
 * for a delivered/paid order.
 *
 * Example:
 *   require_once __DIR__ . "/deduct_subscription_charge.php";
 *   $vendorPayout = deductSubscriptionCharge($conn, $vendorId, $vendorPayout, $fulfillmentId);
 */

if (!function_exists('deductSubscriptionCharge')) {
  function deductSubscriptionCharge($conn, int $vendorId, float $vendorPayout, ?int $fulfillmentId = null): float
  {
    if (!$conn || $vendorId <= 0 || $vendorPayout <= 0) {
      return $vendorPayout;
    }

    // Keep old databases safe.
    pg_query($conn, "
      ALTER TABLE vendor_subscriptions
        ADD COLUMN IF NOT EXISTS pending_charge DECIMAL(10, 2) DEFAULT 0.00,
        ADD COLUMN IF NOT EXISTS charge_deducted BOOLEAN DEFAULT FALSE;
    ");

    pg_query($conn, "BEGIN");

    $subRes = pg_query_params($conn, "
      SELECT id, COALESCE(pending_charge, 0) AS pending_charge, COALESCE(charge_deducted, FALSE) AS charge_deducted
      FROM vendor_subscriptions
      WHERE vendor_user_id = $1
        AND COALESCE(pending_charge, 0) > 0
        AND COALESCE(charge_deducted, FALSE) = FALSE
        AND status IN ('active', 'cancelled')
      ORDER BY id DESC
      LIMIT 1
      FOR UPDATE
    ", [$vendorId]);

    if (!$subRes) {
      pg_query($conn, "ROLLBACK");
      return $vendorPayout;
    }

    $subscription = pg_fetch_assoc($subRes);
    if (!$subscription) {
      pg_query($conn, "COMMIT");
      return $vendorPayout;
    }

    $subscriptionId = (int)$subscription['id'];
    $pendingCharge = (float)$subscription['pending_charge'];
    $deduction = min($pendingCharge, $vendorPayout);
    $newPayout = $vendorPayout - $deduction;
    $remainingCharge = $pendingCharge - $deduction;
    $isFullyDeducted = $remainingCharge <= 0.00001;

    $updateSub = pg_query_params($conn, "
      UPDATE vendor_subscriptions
      SET
        pending_charge = $1,
        charge_deducted = $2,
        updated_at = NOW()
      WHERE id = $3
    ", [$isFullyDeducted ? 0 : $remainingCharge, $isFullyDeducted ? 't' : 'f', $subscriptionId]);

    if (!$updateSub) {
      pg_query($conn, "ROLLBACK");
      return $vendorPayout;
    }

    // Optional: if you pass the vendor_order_fulfillments.id, this updates the DB payout too.
    if ($fulfillmentId !== null && $fulfillmentId > 0) {
      $updateFulfillment = pg_query_params($conn, "
        UPDATE vendor_order_fulfillments
        SET vendor_payout = $1
        WHERE id = $2 AND vendor_user_id = $3
      ", [$newPayout, $fulfillmentId, $vendorId]);

      if (!$updateFulfillment) {
        pg_query($conn, "ROLLBACK");
        return $vendorPayout;
      }
    }

    pg_query($conn, "COMMIT");
    return $newPayout;
  }
}
