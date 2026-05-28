<?php
$host     = getenv('PGHOST')     ?: "localhost";
$port     = getenv('PGPORT')     ?: "5432";
$dbname   = getenv('PGDATABASE') ?: "SetupForge";
$user     = getenv('PGUSER')     ?: "postgres";
$password = getenv('PGPASSWORD') ?: "1234";

$conn = pg_connect(
    "host=$host port=$port dbname=$dbname user=$user password=$password"
);

if (!$conn) {
    die("Database connection failed.");
}
?>