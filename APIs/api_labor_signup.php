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

    $name     = trim($_POST["name"] ?? "");
    $email    = trim($_POST["email"] ?? "");
    $password = $_POST["password"] ?? "";
    $phone    = trim($_POST["phone"] ?? "");
    $country  = trim($_POST["country"] ?? "");
    $city     = trim($_POST["city"] ?? "");
    $street   = trim($_POST["street"] ?? "");

    $national_id      = trim($_POST["national_id"] ?? "");
    $dob              = trim($_POST["dob"] ?? "");
    $skills           = trim($_POST["skills"] ?? "");
    $experience_level = trim($_POST["experience_level"] ?? "junior");
    $hourly_rate      = (float)($_POST["hourly_rate"] ?? 0);
    $provider_type    = trim($_POST["provider_type"] ?? "");
    $labor_role       = trim($_POST["labor_role"] ?? "");
    $military_status  = trim($_POST["military_status"] ?? "n/a");

    if ($name === "" || $email === "" || $password === "") {
        echo json_encode(["ok" => false, "error" => "Missing required fields"]);
        exit;
    }

    if (!isset($conn)) {
        echo json_encode(["ok" => false, "error" => "DB connection not available"]);
        exit;
    }

    // Check email not already taken
    $check = pg_query_params($conn, "SELECT id FROM users WHERE email = $1 LIMIT 1", [$email]);
    if (pg_fetch_assoc($check)) {
        echo json_encode(["ok" => false, "error" => "Email already exists"]);
        exit;
    }

    $hash  = password_hash($password, PASSWORD_BCRYPT);
    $token = bin2hex(random_bytes(32));

    pg_query($conn, "BEGIN");

    // 1) Insert into users
    $resUser = pg_query_params($conn, "
        INSERT INTO users (name, email, password_hash, user_type, phone, country, city, street, status, api_token, created_at)
        VALUES ($1,$2,$3,'labor',$4,$5,$6,$7,'active',$8,NOW())
        RETURNING id
    ", [$name, $email, $hash, $phone ?: null, $country ?: null, $city ?: null, $street ?: null, $token]);

    if (!$resUser) {
        pg_query($conn, "ROLLBACK");
        echo json_encode(["ok" => false, "error" => "Failed to create user"]);
        exit;
    }

    $user_id = (int)pg_fetch_result($resUser, 0, "id");

    // 2) Insert into labors
    $resLabor = pg_query_params($conn, "
        INSERT INTO labors (user_id, national_id, dob, skills, experience_level, hourly_rate, avg_rating, status, provider_type, labor_role, military_status, balance, availability_status)
        VALUES ($1,$2,$3,$4,$5,$6,0,'active',$7,$8,$9,0,'available')
    ", [
        $user_id,
        $national_id ?: null,
        $dob ?: null,
        $skills ?: null,
        $experience_level,
        $hourly_rate,
        $provider_type ?: null,
        $labor_role ?: null,
        $military_status,
    ]);

    if (!$resLabor) {
        pg_query($conn, "ROLLBACK");
        echo json_encode(["ok" => false, "error" => "Failed to create labor profile"]);
        exit;
    }

    pg_query($conn, "COMMIT");

    echo json_encode([
        "ok"        => true,
        "token"     => $token,
        "name"      => $name,
        "email"     => $email,
        "user_type" => "labor",
        "user"      => ["id" => $user_id, "name" => $name, "email" => $email, "user_type" => "labor"]
    ]);

} catch (Throwable $e) {
    file_put_contents(__DIR__ . "/api_error.log", date("c") . " api_labor_signup: " . $e->getMessage() . "\n", FILE_APPEND);
    http_response_code(500);
    echo json_encode(["ok" => false, "error" => "Server error"]);
} finally {
    ob_end_flush();
}