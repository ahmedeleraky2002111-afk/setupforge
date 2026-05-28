<?php
$host     = getenv('PGHOST')     ?: "localhost";
$port     = getenv('PGPORT')     ?: "5432";
$dbname   = getenv('PGDATABASE') ?: "SetupForge";
$user     = getenv('PGUSER')     ?: "postgres";
$password = getenv('PGPASSWORD') ?: "1234";

try {
    $pdo = new PDO("pgsql:host=$host;port=$port;dbname=$dbname", $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Database connection failed: " . $e->getMessage());
}

// Wrapper to keep pg_query compatible code working
function pg_query_params($conn, $sql, $params = []) {
    global $pdo;
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    return $stmt;
}

function pg_query($conn, $sql) {
    global $pdo;
    return $pdo->query($sql);
}

function pg_fetch_assoc($result) {
    if ($result === false) return false;
    return $result->fetch(PDO::FETCH_ASSOC);
}

function pg_num_rows($result) {
    if ($result === false) return 0;
    return $result->rowCount();
}

function pg_fetch_all($result) {
    if ($result === false) return [];
    return $result->fetchAll(PDO::FETCH_ASSOC);
}

function pg_fetch_result($result, $row, $field) {
    if ($result === false) return null;
    $all = $result->fetchAll(PDO::FETCH_ASSOC);
    return $all[$row][$field] ?? null;
}

function pg_last_error($conn = null) {
    global $pdo;
    $info = $pdo->errorInfo();
    return $info[2] ?? '';
}

$conn = true;
?>