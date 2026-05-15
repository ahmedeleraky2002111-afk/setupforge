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
    "SELECT company_id, company_name, services, availability_status, website, image
     FROM companies WHERE user_id = $1 LIMIT 1",
    [$company_user_id]
);
$company = $companyRes ? pg_fetch_assoc($companyRes) : null;

if (!$company) {
    die("Company profile not found.");
}

$company_id = (int)$company["company_id"];
$company_name = $company["company_name"];
$company_services_raw = trim($company["services"] ?? "", '{}');
$company_services = array_map('trim', explode(',', $company_services_raw));

/* HANDLE QUOTE SUBMISSION */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_quote"])) {
    $req_id = (int)$_POST["request_id"];
    $price = (float)$_POST["price"];
    $message = trim($_POST["message"] ?? "");
    $website_link = trim($_POST["website_link"] ?? "");

    // Check not already quoted this request
    $existsRes = pg_query_params($conn,
        "SELECT quote_id FROM installation_quotes
         WHERE request_id = $1 AND company_id = $2 LIMIT 1",
        [$req_id, $company_id]
    );

    if ($existsRes && pg_num_rows($existsRes) === 0) {
        pg_query_params($conn,
            "INSERT INTO installation_quotes (request_id, company_id, price, message, website_link, status)
             VALUES ($1, $2, $3, $4, $5, 'pending')",
            [$req_id, $company_id, $price, $message, $website_link]
        );
    }

    header("Location: company_dashboard.php");
    exit();
}

/* STATS */
$statsRes = pg_query_params($conn,
    "SELECT
        COUNT(*) AS total_quotes,
        COUNT(*) FILTER (WHERE status = 'pending') AS pending_quotes,
        COUNT(*) FILTER (WHERE status = 'accepted') AS accepted_quotes,
        COUNT(*) FILTER (WHERE status = 'rejected') AS rejected_quotes
     FROM installation_quotes
     WHERE company_id = $1",
    [$company_id]
);
$stats = $statsRes ? pg_fetch_assoc($statsRes) : [];

/* NEW REQUESTS — matching company service type, not yet quoted by this company */
$serviceConditions = [];
foreach ($company_services as $svc) {
    $svc = pg_escape_string($conn, $svc);
    $serviceConditions[] = "r.services::text ILIKE '%{$svc}%'";
}
$serviceFilter = !empty($serviceConditions) ? "AND (" . implode(" OR ", $serviceConditions) . ")" : "";

$newRequestsRes = pg_query_params($conn,
    "SELECT r.request_id, r.services, r.status, r.created_at,
            u.name AS business_name, u.city, u.phone
     FROM installation_requests r
     JOIN users u ON u.id = r.user_id
     WHERE r.status = 'pending'
     {$serviceFilter}
     AND r.request_id NOT IN (
         SELECT request_id FROM installation_quotes WHERE company_id = $1
     )
     ORDER BY r.created_at DESC
     LIMIT 20",
    [$company_id]
);

/* MY QUOTES */
$myQuotesRes = pg_query_params($conn,
    "SELECT q.quote_id, q.request_id, q.price, q.message, q.website_link, q.status, q.created_at,
            r.services, u.name AS business_name, u.city
     FROM installation_quotes q
     JOIN installation_requests r ON r.request_id = q.request_id
     JOIN users u ON u.id = r.user_id
     WHERE q.company_id = $1
     ORDER BY q.created_at DESC",
    [$company_id]
);

function formatServices($raw) {
    if (!$raw) return "N/A";
    $cleaned = trim($raw, '{}');
    $items = explode(',', $cleaned);
    $labels = ['pos' => 'POS System', 'electrical' => 'Electrical Wiring', 'network' => 'Network & WiFi', 'ac' => 'AC Installation', 'kitchen' => 'Kitchen Setup'];
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
<style>
.quote-form { background:#f8f9fa; border:1px solid #dee2e6; border-radius:6px; padding:14px; margin-top:10px; display:none; }
.quote-form.open { display:block; }
.badge-accepted { background:#d1fae5; color:#065f46; }
.badge-rejected { background:#fee2e2; color:#991b1b; }
.badge-pending  { background:#fef9c3; color:#854d0e; }
</style>
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
                    <?php if (!empty($company['image'])): ?>
                        <img src="../<?= htmlspecialchars($company['image']) ?>" style="width:46px;height:46px;border-radius:50%;object-fit:cover;">
                    <?php else: ?>
                        <i class="bi bi-person-fill"></i>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end sf-dropdown">
                    <li class="px-3 py-2 fw-semibold"><?= htmlspecialchars($userName) ?></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="edit_company_profile.php"><i class="bi bi-pencil me-2"></i>Edit Profile</a></li>
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
            <p>Browse open installation requests and submit your quotes.</p>
        </div>
        <div class="header-right">
            <div class="header-chip">Company Dashboard</div>
        </div>
    </div>

    <!-- STATS -->
    <div class="cards">
        <div class="card-box">
            <h3>Quotes Sent</h3>
            <p><?= (int)($stats["total_quotes"] ?? 0) ?></p>
            <div class="card-sub">Total quotes submitted</div>
        </div>
        <div class="card-box">
            <h3>Pending</h3>
            <p><?= (int)($stats["pending_quotes"] ?? 0) ?></p>
            <div class="card-sub">Awaiting client decision</div>
        </div>
        <div class="card-box">
            <h3>Accepted</h3>
            <p><?= (int)($stats["accepted_quotes"] ?? 0) ?></p>
            <div class="card-sub">Won jobs</div>
        </div>
        <div class="card-box">
            <h3>Rejected</h3>
            <p><?= (int)($stats["rejected_quotes"] ?? 0) ?></p>
            <div class="card-sub">Not selected</div>
        </div>
    </div>

    <!-- NEW REQUESTS -->
    <div class="panel" style="margin-bottom:28px">
        <div class="panel-header">
            <h2>Open Requests</h2>
            <span class="sub">Businesses looking for your services</span>
        </div>

        <?php if ($newRequestsRes && pg_num_rows($newRequestsRes) > 0): ?>
            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Business</th>
                        <th>City</th>
                        <th>Phone</th>
                        <th>Service</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                    <?php while ($req = pg_fetch_assoc($newRequestsRes)): ?>
                    <tr>
                        <td><?= htmlspecialchars($req["business_name"]) ?></td>
                        <td><?= htmlspecialchars($req["city"]) ?></td>
                        <td><?= htmlspecialchars($req["phone"]) ?></td>
                        <td><?= htmlspecialchars(formatServices($req["services"])) ?></td>
                        <td><?= timeAgo($req["created_at"]) ?></td>
                        <td>
                            <button class="small-btn" onclick="toggleForm(<?= $req['request_id'] ?>)">
                                Submit Quote
                            </button>
                            <div class="quote-form" id="form-<?= $req['request_id'] ?>">
                                <form method="POST">
                                    <input type="hidden" name="request_id" value="<?= (int)$req['request_id'] ?>">
                                    <div class="mb-2">
                                        <label class="form-label fw-semibold">Price (EGP)</label>
                                        <input type="number" name="price" class="form-control" min="0" step="1" required>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label fw-semibold">Message <span class="text-muted fw-normal">(optional)</span></label>
                                        <textarea name="message" class="form-control" rows="2" placeholder="Brief note about your service..."></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Your Website <span class="text-muted fw-normal">(optional)</span></label>
                                        <input type="url" name="website_link" class="form-control" placeholder="https://yourcompany.com" value="<?= htmlspecialchars($company['website'] ?? '') ?>">
                                    </div>
                                    <button type="submit" name="submit_quote" class="small-btn">Send Quote</button>
                                    <button type="button" class="small-btn" style="background:#6c757d;margin-left:6px" onclick="toggleForm(<?= $req['request_id'] ?>)">Cancel</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-box">No open requests matching your services right now.</div>
        <?php endif; ?>
    </div>

    <!-- MY QUOTES -->
    <div class="panel">
        <div class="panel-header">
            <h2>My Quotes</h2>
            <span class="sub">Quotes you have submitted</span>
        </div>

        <?php if ($myQuotesRes && pg_num_rows($myQuotesRes) > 0): ?>
            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Business</th>
                        <th>City</th>
                        <th>Service</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                    <?php while ($q = pg_fetch_assoc($myQuotesRes)): ?>
                    <tr>
                        <td><?= htmlspecialchars($q["business_name"]) ?></td>
                        <td><?= htmlspecialchars($q["city"]) ?></td>
                        <td><?= htmlspecialchars(formatServices($q["services"])) ?></td>
                        <td><?= number_format((float)$q["price"], 0) ?> EGP</td>
                        <td>
                            <span class="status-badge badge-<?= $q['status'] ?>">
                                <?= ucfirst($q["status"]) ?>
                            </span>
                        </td>
                        <td><?= timeAgo($q["created_at"]) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-box">You haven't submitted any quotes yet.</div>
        <?php endif; ?>
    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleForm(id) {
    const form = document.getElementById('form-' + id);
    form.classList.toggle('open');
}
</script>
</body>
</html>