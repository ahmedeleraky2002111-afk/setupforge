<?php
session_start();
require_once "../db.php";

if (!isset($conn) || !$conn) {
  http_response_code(500);
  exit("DB connection missing.");
}

require_once __DIR__ . "/../config.php";

function callback_fail($msg, $code = 400){
  http_response_code($code);
  echo $msg;
  exit;
}

function paymob_val($v){
  if (is_bool($v)) {
    return $v ? "true" : "false";
  }
  if ($v === null) {
    return "";
  }
  return is_scalar($v) ? (string)$v : "";
}

/*
|--------------------------------------------------------------------------
| Paymob sends:
| - hmac in GET
| - payload in RAW JSON body
|--------------------------------------------------------------------------
*/
$raw = file_get_contents("php://input");
$payload = json_decode($raw, true);

if (!is_array($payload)) {
  callback_fail("Invalid callback payload.");
}

$obj = $payload["obj"] ?? null;
if (!is_array($obj)) {
  callback_fail("Missing obj.");
}

$receivedHmac = (string)($_GET["hmac"] ?? "");

/*
|--------------------------------------------------------------------------
| HMAC verification
|--------------------------------------------------------------------------
*/
$hmacFields = [
  paymob_val($obj["amount_cents"] ?? null),
  paymob_val($obj["created_at"] ?? null),
  paymob_val($obj["currency"] ?? null),
  paymob_val($obj["error_occured"] ?? null),
  paymob_val($obj["has_parent_transaction"] ?? null),
  paymob_val($obj["id"] ?? null),
  paymob_val($obj["integration_id"] ?? null),
  paymob_val($obj["is_3d_secure"] ?? null),
  paymob_val($obj["is_auth"] ?? null),
  paymob_val($obj["is_capture"] ?? null),
  paymob_val($obj["is_refunded"] ?? null),
  paymob_val($obj["is_standalone_payment"] ?? null),
  paymob_val($obj["is_voided"] ?? null),
  paymob_val($obj["order"]["id"] ?? null),
  paymob_val($obj["owner"] ?? null),
  paymob_val($obj["pending"] ?? null),
  paymob_val($obj["source_data"]["pan"] ?? null),
  paymob_val($obj["source_data"]["sub_type"] ?? null),
  paymob_val($obj["source_data"]["type"] ?? null),
  paymob_val($obj["success"] ?? null),
];

$calculatedHmac = hash_hmac("sha512", implode("", $hmacFields), PAYMOB_HMAC_SECRET);

/*
|--------------------------------------------------------------------------
| Debug log for HMAC
|--------------------------------------------------------------------------
*/
// file_put_contents(__DIR__ . "/paymob_hmac_check.txt", print_r([
//   "time" => date("Y-m-d H:i:s"),
//   "received_hmac" => $receivedHmac,
//   "calculated_hmac" => $calculatedHmac,
//   "hmac_fields" => $hmacFields,
//   "payload_obj_id" => $obj["id"] ?? null,
//   "merchant_order_id" => $obj["order"]["merchant_order_id"] ?? null,
// ], true) . "\n--------------------\n", FILE_APPEND);

if ($receivedHmac === "" || !hash_equals($calculatedHmac, $receivedHmac)) {
  file_put_contents(__DIR__ . "/callback_debug.txt", "INVALID HMAC\n", FILE_APPEND);
  http_response_code(403);
  exit("Invalid HMAC.");
}


/*
|--------------------------------------------------------------------------
| Resolve local order id from merchant_order_id
|--------------------------------------------------------------------------
*/
$merchantOrderId = (string)($obj["order"]["merchant_order_id"] ?? "");
$orderId = (int)$merchantOrderId;

if ($orderId <= 0) {
  callback_fail("Missing merchant_order_id.");
}

$txnId = (string)($obj["id"] ?? "");
$success = filter_var($obj["success"] ?? false, FILTER_VALIDATE_BOOLEAN);
$pending = filter_var($obj["pending"] ?? false, FILTER_VALIDATE_BOOLEAN);

$orderRes = pg_query_params($conn, "
  SELECT *
  FROM orders
  WHERE id = $1
  LIMIT 1
", [$orderId]);

if (!$orderRes || pg_num_rows($orderRes) === 0) {
  callback_fail("Order not found.", 404);
}

$order = pg_fetch_assoc($orderRes);
file_put_contents(__DIR__ . "/callback_debug.txt", print_r([
  "time" => date("Y-m-d H:i:s"),
  "order_id" => $orderId,
  "success" => $success ?? null,
  "pending" => $pending ?? null,
  "payment_status_before" => $order["payment_status"] ?? null,
  "business_user_id" => $order["business_user_id"] ?? null,
  "labor_data" => $order["labor_data"] ?? null,
"installation_data" => $order["installation_data"] ?? null,
  "txn_order_id" => $obj["order"]["id"] ?? null,
  "merchant_order_id" => $obj["order"]["merchant_order_id"] ?? null,
  "amount_cents" => $obj["amount_cents"] ?? null,
  "raw_success_value" => $obj["success"] ?? null,
  "raw_pending_value" => $obj["pending"] ?? null,
  "data_message" => $obj["data"]["message"] ?? null,
"txn_response_code" => $obj["txn_response_code"] ?? null,
"acq_response_code" => $obj["acq_response_code"] ?? null,
"owner" => $obj["owner"] ?? null,
  ], true) . "\n--------------------\n", FILE_APPEND);

if ($pending) {
  file_put_contents(__DIR__ . "/callback_debug.txt", "PENDING CALLBACK for order {$orderId}\n", FILE_APPEND);
  http_response_code(200);
  echo "Pending callback ignored.";
  exit;
}

/*
|--------------------------------------------------------------------------
| Failed payment
|--------------------------------------------------------------------------
*/
if (!$success) {
  pg_query($conn, "BEGIN");

  try {
    $up = pg_query_params($conn, "
      UPDATE orders
      SET payment_status = 'failed'
      WHERE id = $1
        AND payment_status <> 'paid'
    ", [$orderId]);

    if (!$up) {
      throw new Exception(pg_last_error($conn));
    }

    pg_query($conn, "COMMIT");
    http_response_code(200);
    echo "Payment marked as failed.";
    exit;

  } catch (Throwable $e) {
    pg_query($conn, "ROLLBACK");
    callback_fail("Failed callback handling: " . $e->getMessage(), 500);
  }
}

/*
|--------------------------------------------------------------------------
| Successful payment
|--------------------------------------------------------------------------
*/
pg_query($conn, "BEGIN");

try {
  // Re-fetch with row lock inside transaction to prevent duplicate processing
  $lockedRes = pg_query_params($conn, "
    SELECT payment_status FROM orders WHERE id = $1 LIMIT 1 FOR UPDATE
  ", [$orderId]);

  if (!$lockedRes || pg_num_rows($lockedRes) === 0) {
    throw new Exception("Order not found on lock.");
  }

  $lockedOrder = pg_fetch_assoc($lockedRes);
  $alreadyPaid = ($lockedOrder["payment_status"] === "paid");

  if (!$alreadyPaid) {
    file_put_contents(__DIR__ . "/callback_debug.txt", "ENTERED !alreadyPaid block for order {$orderId}\n", FILE_APPEND);
    $paymentMethod = (string)($obj["source_data"]["sub_type"] ?? ($obj["source_data"]["type"] ?? "card"));


    $upOrder = pg_query_params($conn, "
      UPDATE orders
      SET payment_status = 'paid',
          status = 'confirmed',
          paid_at = NOW(),
          payment_reference = $1,
          payment_method = $2
      WHERE id = $3
        AND payment_status <> 'paid'
    ", [$txnId, $paymentMethod, $orderId]);

    if (!$upOrder) {
      throw new Exception("Failed to update order payment: " . pg_last_error($conn));
    }

    $vendorRowsRes = pg_query_params($conn, "
      SELECT
        p.vendor_user_id,
        COALESCE(SUM(oi.quantity * oi.unit_price), 0) AS gross_amount,
        COALESCE(AVG(c.commission_rate), 6.00) AS commission_rate
      FROM order_items oi
      JOIN products p ON p.id = oi.product_id
      LEFT JOIN categories c ON c.id = p.category_id
      WHERE oi.order_id = $1
      GROUP BY p.vendor_user_id
    ", [$orderId]);

    if (!$vendorRowsRes) {
      throw new Exception("Failed to load vendor totals: " . pg_last_error($conn));
    }

    while ($vr = pg_fetch_assoc($vendorRowsRes)) {
      $vendorUserId = (int)$vr["vendor_user_id"];
      $grossAmount = (float)$vr["gross_amount"];
      $commissionRate = (float)$vr["commission_rate"];
      $commissionAmount = round($grossAmount * ($commissionRate / 100), 2);
      $vendorPayout = round($grossAmount - $commissionAmount, 2);

      file_put_contents(__DIR__ . "/callback_debug.txt", "entered success block for order {$orderId}\n", FILE_APPEND);

      $insVof = pg_query_params($conn, "
        INSERT INTO vendor_order_fulfillments
        (
          order_id,
          vendor_user_id,
          status,
          notes,
          gross_amount,
          commission_rate,
          commission_amount,
          vendor_payout
        )
        VALUES ($1, $2, 'pending', $3, $4, $5, $6, $7)
        ON CONFLICT (order_id, vendor_user_id)
        DO NOTHING
      ", [
        $orderId,
        $vendorUserId,
        null,
        $grossAmount,
        $commissionRate,
        $commissionAmount,
        $vendorPayout
      ]);

      if (!$insVof) {
        throw new Exception("Failed to create vendor fulfillment: " . pg_last_error($conn));
      }
    }

    $businessId = isset($order["business_user_id"]) && $order["business_user_id"] !== null
      ? (int)$order["business_user_id"]
      : null;

    if ($businessId !== null) {
      $jobLocation = trim((string)($order["delivery_location"] ?? ""));

      if ($jobLocation === "") {
        $bizLocRes = pg_query_params($conn, "
          SELECT location_text
          FROM businesses
          WHERE user_id = $1
          LIMIT 1
        ", [$businessId]);

        if ($bizLocRes && pg_num_rows($bizLocRes) > 0) {
          $bizLocRow = pg_fetch_assoc($bizLocRes);
          $jobLocation = trim((string)($bizLocRow["location_text"] ?? ""));
        }
      }

      if ($jobLocation === "") {
        $jobLocation = "Business Location";
      }

          $laborRaw           = json_decode($order["labor_data"] ?? "[]", true);
          $laborMap           = $laborRaw["roles"] ?? $laborRaw; // fallback for old format
          $salaryAmount       = (int)($laborRaw["salary_amount"] ?? 0);
          $compensationType   = trim((string)($laborRaw["compensation_type"] ?? "monthly"));
          $technicians = json_decode($order["technician_data"] ?? "[]", true);
      if (is_array($laborMap) && !empty($laborMap)) {
       $insJobSql = "
  INSERT INTO jobs
  (business_id, title, description, location, salary_amount, compensation_type, status, price, worker_id, job_type, labor_role)
  VALUES ($1, $2, $3, $4, $5, $6, 'available', 0, NULL, 'labor', $7)
";

        foreach ($laborMap as $role => $qtyRaw) {
          $qty = (int)$qtyRaw;
          if ($qty <= 0) continue;

          for ($i = 1; $i <= $qty; $i++) {
            $roleLabel = ucfirst(str_replace("_", " ", (string)$role));
            $title = $roleLabel . " Needed";
            $description = $roleLabel . " requested during setup (Order #{$orderId}).";

            $okJob = pg_query_params($conn, $insJobSql, [
  $businessId,
  $title,
  $description,
  $jobLocation,
  $salaryAmount,
  $compensationType,
  strtolower(str_replace(" ", "_", $role)) // labor_role
]);

            if (!$okJob) {
              throw new Exception("Insert labor job failed: " . pg_last_error($conn));
            }
          }
        }
      }

     $installationData = json_decode($order["installation_data"] ?? "[]", true);
$installationServices = $installationData["services"] ?? $installationData;

if (is_array($installationServices) && !empty($installationServices) && $businessId !== null) {
  $insInstallationSql = "
    INSERT INTO installation_requests
    (user_id, order_id, services, status, company_id, total_price)
    VALUES ($1, $2, $3, $4, $5, $6)
    ON CONFLICT (user_id, services) DO NOTHING
  ";

  foreach ($installationServices as $service) {
    $service = trim((string)$service);
    if ($service === "") continue;

    $okInstallation = pg_query_params($conn, $insInstallationSql, [
      $businessId,
      $orderId,
      "{" . $service . "}",
      "pending",
      null,
      0
    ]);
    if (!$okInstallation) {
      throw new Exception("Insert installation request failed: " . pg_last_error($conn));
    }
  }
}

// Create finishing request — one per order, business selects types later in service_jobs.php
if ($businessId !== null) {
  $areaFromOrder = (int)($instData["area_sqm"] ?? 0);
  if ($areaFromOrder === 0) {
    $bizAreaRes = pg_query_params($conn,
      "SELECT area_sqm FROM businesses WHERE user_id = $1 LIMIT 1",
      [$businessId]);
    if ($bizAreaRes && pg_num_rows($bizAreaRes) > 0) {
      $areaFromOrder = (int)(pg_fetch_assoc($bizAreaRes)["area_sqm"] ?? 0);
    }
  }

  $okFinishing = pg_query_params($conn, "
    INSERT INTO finishing_requests
    (user_id, order_id, area_sqm, finishing_types, status, company_id, total_price)
    VALUES ($1, $2, $3, $4, 'pending', NULL, 0)
    ON CONFLICT (user_id, order_id) DO NOTHING
  ", [
    $businessId,
    $orderId,
    $areaFromOrder > 0 ? $areaFromOrder : null,
    null
  ]);

  if (!$okFinishing) {
    throw new Exception("Insert finishing request failed: " . pg_last_error($conn));
  }
}
    }
    // ✅ Mark business setup as completed
$bizUserId = isset($order["business_user_id"]) && $order["business_user_id"] !== null
    ? (int)$order["business_user_id"] : null;

if ($bizUserId) {
    @pg_query_params($conn,
        "UPDATE businesses SET setup_status = 'completed', updated_at = now() WHERE user_id = $1",
        [$bizUserId]
    );
}
    }

  pg_query($conn, "COMMIT");
  http_response_code(200);
  echo "OK";

} catch (Throwable $e) {
  pg_query($conn, "ROLLBACK");
  file_put_contents(__DIR__ . "/callback_debug.txt", "ROLLBACK ERROR for order {$orderId}: " . $e->getMessage() . "\n--------------------\n", FILE_APPEND);
  callback_fail("Callback processing failed: " . $e->getMessage(), 500);
}