<?php
ob_start();
ini_set('display_errors', 0);
error_reporting(E_ALL);
header("Content-Type: application/json");

try {
  require_once __DIR__ . "/../db.php";

  if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    echo json_encode(["ok"=>false, "error"=>"POST only"]);
    exit;
  }

  $name = trim($_POST["name"] ?? "");
  $email = trim($_POST["email"] ?? "");
  $password = $_POST["password"] ?? "";

  if ($name==="" || $email==="" || $password==="") {
    echo json_encode(["ok"=>false, "error"=>"Missing fields"]);
    exit;
  }

  $phone   = trim($_POST["phone"] ?? "");
  $country = trim($_POST["country"] ?? "");
  $city    = trim($_POST["city"] ?? "");
  $street  = trim($_POST["street"] ?? "");
  $user_type = trim($_POST["user_type"] ?? "business");
if (!in_array($user_type, ["business", "customer"])) {
    $user_type = "business";
}
  if (!isset($conn)) {
    echo json_encode(["ok"=>false, "error"=>"DB connection not available"]);
    exit;
  }

  $check = pg_query_params($conn, "SELECT id FROM users WHERE email = $1 LIMIT 1", [$email]);
  if (pg_fetch_assoc($check)) {
    echo json_encode(["ok"=>false, "error"=>"Email already exists"]);
    exit;
  }

  $hash  = password_hash($password, PASSWORD_BCRYPT);
  $token = bin2hex(random_bytes(32));

  $result = pg_query_params($conn, "
    INSERT INTO users (name, email, password_hash, user_type, phone, country, city, street, status, api_token, created_at)
    VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9,$10,NOW())
    RETURNING id
  ", [
    $name,
    $email,
    $hash,
    $user_type,
    $phone ?: null,
    $country ?: null,
    $city ?: null,
    $street ?: null,
    "active",
    $token
  ]);

  $id = pg_fetch_result($result, 0, "id");

  echo json_encode([
    "ok"        => true,
    "token"     => $token,
    "name"      => $name,
    "email"     => $email,
    "user_type" => $user_type,
    "user"      => ["id" => $id, "name" => $name, "email" => $email]
]);
} catch (Throwable $e) {
  file_put_contents(__DIR__ . "/api_error.log", date("c") . " api_signup: " . $e->getMessage() . "\n", FILE_APPEND);
  http_response_code(500);
  echo json_encode(["ok"=>false, "error"=>"Server error (check api_error.log)"]);
} finally {
  ob_end_flush();
}