<?php
// db.php

// Railway usually provides DATABASE_URL.
// Localhost uses your XAMPP/PostgreSQL values.

$databaseUrl = getenv('DATABASE_URL');

if ($databaseUrl) {
    $db = parse_url($databaseUrl);

    $host = $db["host"] ?? "localhost";
    $port = $db["port"] ?? "5432";
    $user = $db["user"] ?? "postgres";
    $password = $db["pass"] ?? "";
    $dbname = isset($db["path"]) ? ltrim($db["path"], "/") : "SetupForge";
} else {
    $host     = getenv('PGHOST')     ?: "localhost";
    $port     = getenv('PGPORT')     ?: "5432";
    $dbname   = getenv('PGDATABASE') ?: "SetupForge";
    $user     = getenv('PGUSER')     ?: "postgres";
    $password = getenv('PGPASSWORD') ?: "1234";
}

// Native pg_connect connection
$conn_string = "host=$host port=$port dbname=$dbname user=$user password=$password";

if ($databaseUrl) {
    $conn_string .= " sslmode=require";
}

$conn = pg_connect($conn_string);

if (!$conn) {
    die("PostgreSQL connection failed.");
}

// PDO connection too, for files/APIs that use $pdo
try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";

    if ($databaseUrl) {
        $dsn .= ";sslmode=require";
    }

    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("PDO database connection failed: " . $e->getMessage());
}
?>