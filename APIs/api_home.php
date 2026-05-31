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
        "SELECT id, name, user_type FROM users WHERE api_token = $1 LIMIT 1",
        [$token]);

    if (!$userRes || pg_num_rows($userRes) === 0) {
        echo json_encode(["ok" => false, "error" => "Unauthorized"]);
        exit;
    }

    $user = pg_fetch_assoc($userRes);
    $user_id = (int)$user["id"];
    $name = $user["name"];

    // Check for completed paid order
    $paidRes = pg_query_params($conn,
        "SELECT 1 FROM orders
         WHERE business_user_id = $1
           AND payment_status = 'paid'
           AND order_type = 'setup'
           AND order_total > 0
         LIMIT 1",
        [$user_id]);
    $hasPaidOrder = $paidRes && pg_num_rows($paidRes) > 0;

    if ($hasPaidOrder) {
        $btnText = "My Business";
        $btnRoute = "my-business";
        $setupComplete = true;
    } else {
        // Check in-progress setup
        $bizRes = pg_query_params($conn,
            "SELECT setup_status, setup_step FROM businesses WHERE user_id = $1 LIMIT 1",
            [$user_id]);

        $setupComplete = false;

        if ($bizRes && pg_num_rows($bizRes) > 0) {
            $biz = pg_fetch_assoc($bizRes);
            $setupStatus = $biz["setup_status"] ?? "";
            $setupStep   = (int)($biz["setup_step"] ?? 0);

            if ($setupStatus === "completed") {
                $btnText  = "Resume Setup";
                $btnRoute = "packages";
            } elseif ($setupStatus === "in_progress" && $setupStep > 0) {
                $btnText  = "Resume Setup";
                $btnRoute = "setup";
            } else {
                $btnText  = "Start Your Setup";
                $btnRoute = "setup";
            }
        } else {
            $btnText  = "Start Your Setup";
            $btnRoute = "setup";
        }
    }

    // Determine setup state
$setupState = "none"; // none, in_progress, completed
if ($hasPaidOrder) {
    $setupState = "completed";
} else {
    if ($bizRes && pg_num_rows($bizRes) > 0) {
        $biz = pg_fetch_assoc($bizRes);
        pg_result_seek($bizRes, 0); // reset pointer
        $setupStatus = $biz["setup_status"] ?? "";
        $setupStep   = (int)($biz["setup_step"] ?? 0);
        if ($setupStatus === "completed" || $setupStep > 0) {
            $setupState = "in_progress";
        }
    }
}

echo json_encode([
    "ok"             => true,
    "name"           => $name,
    "btn_text"       => $btnText,
    "btn_route"      => $btnRoute,
    "setup_complete" => $setupComplete,
    "setup_state"    => $setupState,
]);

} catch (Throwable $e) {
    file_put_contents(__DIR__ . "/api_error.log",
        date("c") . " api_home: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error"]);
} finally {
    ob_end_flush();
}