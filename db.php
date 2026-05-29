<?php
$host = getenv('PGHOST') ?: 'nozomi.proxy.rlwy.net';
$port = getenv('PGPORT') ?: '24590';
$dbname   = getenv('PGDATABASE') ?: 'railway';
$user     = getenv('PGUSER')     ?: 'postgres';
$password = getenv('PGPASSWORD') ?: 'ljzNktmVDRkGjeQwnnmNzhQdPeTraHFp';

$conn_string = "host=$host port=$port dbname=$dbname user=$user password=$password";
$conn = pg_connect($conn_string);

if (!$conn) {
    die("PostgreSQL connection failed.");
}

try {
    $dsn = "pgsql:host=$host;port=$port;dbname=$dbname";
    $pdo = new PDO($dsn, $user, $password);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("PDO database connection failed: " . $e->getMessage());
}
?>