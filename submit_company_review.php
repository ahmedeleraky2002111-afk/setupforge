<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    http_response_code(403);
    echo json_encode(["error" => "Not logged in"]);
    exit;
}

$userId    = (int)$_SESSION["user_id"];
$companyId = (int)($_POST["company_id"] ?? 0);
$rating    = (int)($_POST["rating"] ?? 0);
$comment   = trim($_POST["comment"] ?? "");

if (!$companyId || $rating < 1 || $rating > 5) {
    echo json_encode(["error" => "Invalid input"]);
    exit;
}

pg_query_params($conn,
    "INSERT INTO company_reviews (company_id, user_id, rating, comment)
     VALUES ($1, $2, $3, $4)
     ON CONFLICT (company_id, user_id)
     DO UPDATE SET rating = $3, comment = $4, created_at = now()",
    [$companyId, $userId, $rating, $comment ?: null]
);

pg_query_params($conn,
    "UPDATE companies
     SET avg_rating = (SELECT ROUND(AVG(rating)::numeric, 1) FROM company_reviews WHERE company_id = $1)
     WHERE company_id = $1",
    [$companyId]
);

echo json_encode(["ok" => true]);