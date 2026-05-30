<?php
session_start();
require_once "db.php";

header("Content-Type: application/json");

if (!isset($_SESSION["user_id"])) {
    echo json_encode(["ok" => false, "error" => "not logged in"]);
    exit;
}

$module = trim((string)($_POST["module"] ?? ""));
$type   = trim((string)($_POST["type"]   ?? ""));
$hidden = ($_POST["hidden"] ?? "0") === "1";

$allowed_modules = ["pos", "kitchen", "furniture", "ac"];
if (!in_array($module, $allowed_modules, true) || $type === "") {
    echo json_encode(["ok" => false, "error" => "invalid params"]);
    exit;
}

$userId = (int)$_SESSION["user_id"];

// Get current hidden_sections for this user's latest order
$res = pg_query_params($conn,
    "SELECT id, hidden_sections FROM orders WHERE user_id = $1 ORDER BY id DESC LIMIT 1",
    [$userId]
);

if (!$res || pg_num_rows($res) === 0) {
    echo json_encode(["ok" => false, "error" => "no order found"]);
    exit;
}

$row      = pg_fetch_assoc($res);
$orderId  = (int)$row["id"];
$current  = json_decode($row["hidden_sections"] ?: "{}", true) ?: [];

// Ensure module key exists
if (!isset($current[$module]) || !is_array($current[$module])) {
    $current[$module] = [];
}

if ($hidden) {
    // Add to hidden list (no duplicates)
    if (!in_array($type, $current[$module], true)) {
        $current[$module][] = $type;
    }
} else {
    // Remove from hidden list
    $current[$module] = array_values(array_filter(
        $current[$module],
        fn($t) => $t !== $type
    ));
}

$json = json_encode($current);

$upd = pg_query_params($conn,
    "UPDATE orders SET hidden_sections = $1 WHERE id = $2",
    [$json, $orderId]
);

if (!$upd) {
    echo json_encode(["ok" => false, "error" => "db update failed"]);
    exit;
}

// Also keep in session so page doesn't need a reload to reflect
$_SESSION["wizard"]["hidden_sections"] = $current;

echo json_encode(["ok" => true, "hidden_sections" => $current]);
exit;