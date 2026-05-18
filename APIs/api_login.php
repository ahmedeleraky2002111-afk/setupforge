<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header("Content-Type: application/json");

try {
    require_once __DIR__ . "/../db.php";

    if ($_SERVER["REQUEST_METHOD"] !== "POST") {
        echo json_encode(["ok" => false, "error" => "POST only"]);
        exit;
    }

    $email = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";

    if ($email === "" || $password === "") {
        echo json_encode(["ok" => false, "error" => "Missing email/password"]);
        exit;
    }

    if (!isset($conn) || !$conn) {
        echo json_encode(["ok" => false, "error" => "DB connection not available (\$conn missing)"]);
        exit;
    }

    $res = pg_query_params($conn, "
        SELECT id, name, email, user_type, password_hash
        FROM users
        WHERE email = $1
        LIMIT 1
    ", [$email]);

    if (!$res) {
        echo json_encode(["ok" => false, "error" => "Database query failed"]);
        exit;
    }

    $user = pg_fetch_assoc($res);

    if (!$user) {
        echo json_encode(["ok" => false, "error" => "User not found"]);
        exit;
    }

    if (!password_verify($password, $user["password_hash"])) {
        echo json_encode([
            "ok" => false,
            "error" => "Wrong password"
        ]);
        exit;
    }

    $token = bin2hex(random_bytes(32));

    $upd = pg_query_params($conn, "
        UPDATE users
        SET api_token = $1
        WHERE id = $2
    ", [$token, $user["id"]]);

    if (!$upd) {
        echo json_encode(["ok" => false, "error" => "Failed to save token"]);
        exit;
    }

    echo json_encode([
    "ok"        => true,
    "token"     => $token,
    "name"      => $user["name"],
    "email"     => $user["email"],
    "user_type" => $user["user_type"],
    "user"      => [
        "id"        => (int)$user["id"],
        "name"      => $user["name"],
        "email"     => $user["email"],
        "user_type" => $user["user_type"]
    ]
]);
} catch (Throwable $e) {
    file_put_contents(
        __DIR__ . "/api_error.log",
        date("c") . " api_login: " . $e->getMessage() . "\n",
        FILE_APPEND
    );
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error (check api_error.log)"]);
} finally {
    ob_end_flush();
}