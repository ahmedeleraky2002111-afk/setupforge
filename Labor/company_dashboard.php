<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

if (!isset($_SESSION["user_type"]) || $_SESSION["user_type"] !== "company") {
    header("Location: ../home.php");
    exit();
}

$company_user_id = (int)$_SESSION["user_id"];
$userName = $_SESSION["name"] ?? "Company";

/* GET COMPANY INFO */
$companyRes = pg_query_params($conn,
    "SELECT company_id, company_name, services, availability_status
     FROM companies WHERE user_id = $1 LIMIT 1",
    [$company_user_id]
);
$company = $companyRes ? pg_fetch_assoc($companyRes) : null;

if (!$company) {
    die("Company profile not found.");
}

$company_id = (int)$company["company_id"];
$company_name = $company["company_name"];

/* STATS */
$statsRes = pg_query_params($conn,
    "SELECT
        COUNT(*) AS total,
        COUNT(*) FILTER (WHERE status = 'pending') AS pending,
        COUNT(*) FILTER (WHERE status = 'visit_requested') AS visit_requested,
        COUNT(*) FILTER (WHERE status = 'quoted') AS quoted,
        COUNT(*) FILTER (WHERE status = 'accepted') AS accepted,
        COUNT(*) FILTER (WHERE status = 'completed') AS completed
     FROM installation_requests
     WHERE company_id = $1 OR (company_id IS NULL AND $1 = $1)",
    [$company_id]
);
$stats = $statsRes ? pg_fetch_assoc($statsRes) : [];

/* PENDING REQUESTS (not yet assigned to any company) */
$pendingRes = pg_query($conn,
    "SELECT r.request_id, r.user_id, r.services, r.status, r.created_at,
            u.name AS business_name, u.city, u.phone
     FROM installation_requests r
     JOIN users u ON u.id = r.user_id
     WHERE r.status = 'pending' AND r.company_id IS NULL
     ORDER BY r.created_at DESC
     LIMIT 20"
);

/* MY ACTIVE REQUESTS */
$myRequestsRes = pg_query_params($conn,
    "SELECT r.request_id, r.user_id, r.services, r.status, r.total_price, r.created_at,
            u.name AS business_name, u.city, u.phone
     FROM installation_requests r
     JOIN users u ON u.id = r.user_id
     WHERE r.company_id = $1
     ORDER BY r.created_at DESC",
    [$company_id]
);

/* HANDLE SITE VISIT REQUEST */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["request_visit"])) {
    $req_id = (int)$_POST["request_id"];
    pg_query_params($conn,
        "UPDATE installation_requests
         SET status = 'visit_requested', company_id = $1
         WHERE request_id = $2 AND status = 'pending'",
        [$company_id, $req_id]
    );
    header("Location: company_dashboard.php");
    exit();
}

function formatServices($raw) {
    if (!$raw) return "N/A";
    $cleaned = trim($raw, '{}');
    $items = explode(',', $cleaned);
    $labels = ['pos' => 'POS System', 'electrical' => 'Electrical', 'network' => 'Network & WiFi', 'ac' => 'AC Installation'];
    $out = [];
    foreach ($items as $item) {
        $item = trim($item);
        $out[] = $labels[$item] ?? ucfirst($item);
    }
    return implode(', ', $out);
}

function timeAgo($datetime) {
    if (!$datetime) return "";
    $diff = time() - strtotime($datetime);
    if ($diff < 60) return "Just now";
    if ($diff < 3600) return floor($diff/60) . " min ago";
    if ($diff < 86400) return floor($diff/3600) . " hr ago";
    return date("M j, Y", strtotime($datetime));
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Company Dashboard - SetupForge</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="labor.css">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark sf-navbar">
    <div class="container">
        <a class="navbar-brand sf-brand-wrap" href="company_dashboard.php">
            <div class="sf-logo"><img src="../assets/images/Logo.png" alt="SetupForge"></div>
            <span class="fw-bold">SetupForge</span>
        </a>
        <div class="sf-nav-actions">
            <div class="dropdown">
                <button class="btn sf-profile-btn" data-bs-toggle="dropdown">
                    <i class="bi bi-person-fill"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end sf-dropdown">
                    <li class="px-3 py-2 fw-semibold"><?= htmlspecialchars($userName) ?></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="../auth/logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="main">

    <div class="header">
        <div class="header-left">
            <h1>Welcome, <?= htmlspecialchars($company_name) ?></h1>
            <p>Manage your installation requests, schedule site visits, and submit quotes.</p>
        </div>
        <div class="header-right">
            <div class="header-chip">Company Dashboard</div>
        </div>
    </div>

    <!-- STATS -->
    <div class="cards">
        <div class="card-box">
            <h3>New Requests</h3>
            <p><?= (int)($stats["pending"] ?? 0) ?></p>
            <div class="card-sub">Waiting for your response</div>
        </div>
        <div class="card-box">
            <h3>Site Visits</h3>
            <p><?= (int)($stats["visit_requested"] ?? 0) ?></p>
            <div class="card-sub">Visits scheduled</div>
        </div>
        <div class="card-box">
            <h3>Quotes Sent</h3>
            <p><?= (int)($stats["quoted"] ?? 0) ?></p>
            <div class="card-sub">Awaiting client acceptance</div>
        </div>
        <div class="card-box">
            <h3>Completed</h3>
            <p><?= (int)($stats["completed"] ?? 0) ?></p>
            <div class="card-sub">Finished installations</div>
        </div>
    </div>

    <!-- NEW INCOMING REQUESTS -->
    <div class="panel" style="margin-bottom:28px">
        <div class="panel-header">
            <h2>New Requests</h2>
            <span class="sub">Unassigned installation requests matching your services</span>
        </div>

        <?php if ($pendingRes && pg_num_rows($pendingRes) > 0): ?>
            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Business</th>
                        <th>City</th>
                        <th>Services</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                    <?php while ($req = pg_fetch_assoc($pendingRes)): ?>
                    <tr>
                        <td><?= htmlspecialchars($req["business_name"]) ?></td>
                        <td><?= htmlspecialchars($req["city"]) ?></td>
                        <td><?= htmlspecialchars(formatServices($req["services"])) ?></td>
                        <td><?= timeAgo($req["created_at"]) ?></td>
                        <td>
                            <form method="POST" style="margin:0">
                                <input type="hidden" name="request_id" value="<?= (int)$req["request_id"] ?>">
                                <button type="submit" name="request_visit" class="small-btn">
                                    Request Site Visit
                                </button>
                            </form>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-box">No new requests right now.</div>
        <?php endif; ?>
    </div>

    <!-- MY ACTIVE REQUESTS -->
    <div class="panel">
        <div class="panel-header">
            <h2>My Requests</h2>
            <span class="sub">Requests you have taken on</span>
        </div>

        <?php if ($myRequestsRes && pg_num_rows($myRequestsRes) > 0): ?>
            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Business</th>
                        <th>City</th>
                        <th>Services</th>
                        <th>Status</th>
                        <th>Price</th>
                        <th>Date</th>
                    </tr>
                    <?php while ($req = pg_fetch_assoc($myRequestsRes)): ?>
                    <tr>
                        <td><?= htmlspecialchars($req["business_name"]) ?></td>
                        <td><?= htmlspecialchars($req["city"]) ?></td>
                        <td><?= htmlspecialchars(formatServices($req["services"])) ?></td>
                        <td>
                            <span class="status-badge <?= $req['status'] === 'completed' ? 'badge-completed' : ($req['status'] === 'accepted' ? 'badge-active' : 'badge-available') ?>">
                                <?= htmlspecialchars(ucfirst(str_replace('_', ' ', $req["status"]))) ?>
                            </span>
                        </td>
                        <td><?= $req["total_price"] > 0 ? number_format((float)$req["total_price"], 0) . ' EGP' : '—' ?></td>
                        <td><?= timeAgo($req["created_at"]) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-box">No active requests yet.</div>
        <?php endif; ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>