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
$isAdvertisingOnly = $company_services_raw === 'advertising' || $company_services === ['advertising'];
$isFinishingCompany = in_array('finishing', $company_services);

/* HANDLE QUOTE SUBMISSION */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["submit_quote"])) {
    $req_id = (int)$_POST["request_id"];
    $priceRes = pg_query_params($conn,
    "SELECT starting_from FROM companies WHERE company_id = $1 LIMIT 1",
    [$company_id]);
    $price = ($priceRes && pg_num_rows($priceRes) > 0)
    ? (float)pg_fetch_assoc($priceRes)["starting_from"]
    : 0;
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
            COALESCE(b.business_name, u.name) AS business_name,
            u.city, u.phone,
            b.business_type, b.area_sqm, b.seat_count, b.indoor_tables, b.place_size

     FROM installation_requests r
     JOIN users u ON u.id = r.user_id
     LEFT JOIN businesses b ON b.user_id = r.user_id
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
$labels = ['pos' => 'POS System', 'electrical' => 'Electrical Wiring', 'network' => 'Network & WiFi', 'ac' => 'AC Installation', 'kitchen' => 'Kitchen Setup', 'finishing' => 'Finishing', 'advertising' => 'Advertising'];
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

<nav class="navbar navbar-expand-lg navbar-dark" style="background:#004cac;">
    <div class="container">
        <a class="navbar-brand d-flex align-items-center gap-2" href="company_dashboard.php">
            <div class="sf-logo"><img src="../assets/images/Logo.png" alt="SetupForge"></div>
            <span class="fw-bold">SetupForge</span>
        </a>
        <div class="ms-auto">
            <div class="dropdown">
                <button class="btn text-white" data-bs-toggle="dropdown">
                    <?php if (!empty($company['image'])): ?>
                        <img src="../<?= htmlspecialchars($company['image']) ?>" style="width:38px;height:38px;border-radius:50%;object-fit:cover;border:2px solid rgba(255,255,255,.3);">
                    <?php else: ?>
                        <i class="bi bi-person-circle fs-4"></i>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end">
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

    <?php if ($isFinishingCompany): ?>
    <!-- FINISHING REQUESTS -->
    <div class="panel" style="margin-bottom:28px">
        <div class="panel-header">
            <h2>Finishing Requests</h2>
            <span class="sub">Businesses looking for finishing services</span>
        </div>
        <?php
        $finishingReqsRes = pg_query_params($conn, "
            SELECT fr.request_id, fr.area_sqm, fr.finishing_types, fr.status, fr.created_at,
                   u.name AS business_name, u.city, u.phone
            FROM finishing_requests fr
            JOIN users u ON u.id = fr.user_id
            WHERE fr.status = 'pending'
            AND fr.request_id NOT IN (
                SELECT request_id FROM finishing_quotes WHERE company_id = $1
            )
            ORDER BY fr.created_at DESC
            LIMIT 20
        ", [$company_id]);

        $finishingTypeLabels = [
            'painting' => 'Painting',
            'flooring' => 'Flooring',
            'gypsum'   => 'Gypsum & Ceilings',
            'decor'    => 'Decor',
            'facades'  => 'Facades',
        ];
        ?>
        <?php if ($finishingReqsRes && pg_num_rows($finishingReqsRes) > 0): ?>
            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Business</th>
                        <th>City</th>
                        <th>Area</th>
                        <th>Needs</th>
                        <th>Date</th>
                        <th>Action</th>
                    </tr>
                    <?php while ($freq = pg_fetch_assoc($finishingReqsRes)):
                        $ftRaw = trim($freq["finishing_types"] ?? "", '{}');
                        $ftList = $ftRaw ? array_filter(array_map('trim', explode(',', $ftRaw))) : [];
                        $ftLabels = array_map(fn($t) => $finishingTypeLabels[$t] ?? ucfirst($t), $ftList);
                    ?>
                    <tr>
                        <td><?= htmlspecialchars($freq["business_name"]) ?></td>
                        <td><?= htmlspecialchars($freq["city"]) ?></td>
                        <td><?= $freq["area_sqm"] ? $freq["area_sqm"] . ' sqm' : '—' ?></td>
                        <td>
                            <?php if (!empty($ftLabels)): ?>
                                <?php foreach ($ftLabels as $ftl): ?>
                                    <span style="display:inline-block;font-size:.72rem;font-weight:700;padding:2px 8px;border-radius:999px;background:rgba(0,76,172,.08);color:#004cac;border:1px solid rgba(0,76,172,.15);margin:2px 2px 2px 0;">
                                        <?= htmlspecialchars($ftl) ?>
                                    </span>
                                <?php endforeach; ?>
                            <?php else: ?>
                                <span style="color:#9ca3af;font-size:.82rem;">Not specified yet</span>
                            <?php endif; ?>
                        </td>
                        <td><?= timeAgo($freq["created_at"]) ?></td>
                        <td>
                            <button class="small-btn" onclick="toggleFinishingForm(<?= $freq['request_id'] ?>)">
                                Submit Quote
                            </button>
                            <div class="quote-form" id="fform-<?= $freq['request_id'] ?>">
                                <form method="POST" action="submit_finishing_quote.php">
                                    <input type="hidden" name="request_id" value="<?= (int)$freq['request_id'] ?>">
                                    <input type="hidden" name="company_id" value="<?= $company_id ?>">
                                    <div class="mb-2">
                                        <label class="form-label fw-semibold">Price (EGP)</label>
                                        <input type="number" name="price" class="form-control" placeholder="e.g. 25000" required>
                                    </div>
                                    <div class="mb-2">
                                        <label class="form-label fw-semibold">Message <span class="text-muted fw-normal">(optional)</span></label>
                                        <textarea name="message" class="form-control" rows="2" placeholder="Brief note about your service..."></textarea>
                                    </div>
                                    <div class="mb-3">
                                        <label class="form-label fw-semibold">Your Website <span class="text-muted fw-normal">(optional)</span></label>
                                        <input type="url" name="website_link" class="form-control" placeholder="https://yourcompany.com" value="<?= htmlspecialchars($company['website'] ?? '') ?>">
                                    </div>
                                    <button type="submit" class="small-btn">Send Quote</button>
                                    <button type="button" class="small-btn" style="background:#6c757d;margin-left:6px" onclick="toggleFinishingForm(<?= $freq['request_id'] ?>)">Cancel</button>
                                </form>
                            </div>
                        </td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-box">No open finishing requests right now.</div>
        <?php endif; ?>
    </div>

    <!-- FINISHING MY QUOTES -->
    <div class="panel" style="margin-bottom:28px">
        <div class="panel-header">
            <h2>My Finishing Quotes</h2>
            <span class="sub">Quotes you have submitted for finishing requests</span>
        </div>
        <?php
        $myFinishingQuotesRes = pg_query_params($conn, "
            SELECT fq.quote_id, fq.request_id, fq.price, fq.message, fq.status, fq.created_at,
                   fr.area_sqm, fr.finishing_types,
                   u.name AS business_name, u.city
            FROM finishing_quotes fq
            JOIN finishing_requests fr ON fr.request_id = fq.request_id
            JOIN users u ON u.id = fr.user_id
            WHERE fq.company_id = $1
            ORDER BY fq.created_at DESC
        ", [$company_id]);
        ?>
        <?php if ($myFinishingQuotesRes && pg_num_rows($myFinishingQuotesRes) > 0): ?>
            <div class="table-wrap">
                <table>
                    <tr>
                        <th>Business</th>
                        <th>City</th>
                        <th>Area</th>
                        <th>Price</th>
                        <th>Status</th>
                        <th>Date</th>
                    </tr>
                    <?php while ($fq = pg_fetch_assoc($myFinishingQuotesRes)): ?>
                    <tr>
                        <td><?= htmlspecialchars($fq["business_name"]) ?></td>
                        <td><?= htmlspecialchars($fq["city"]) ?></td>
                        <td><?= $fq["area_sqm"] ? $fq["area_sqm"] . ' sqm' : '—' ?></td>
                        <td><?= number_format((float)$fq["price"], 0) ?> EGP</td>
                        <td>
                            <span class="status-badge badge-<?= $fq['status'] ?>">
                                <?= ucfirst($fq["status"]) ?>
                            </span>
                        </td>
                        <td><?= timeAgo($fq["created_at"]) ?></td>
                    </tr>
                    <?php endwhile; ?>
                </table>
            </div>
        <?php else: ?>
            <div class="empty-box">No finishing quotes submitted yet.</div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <!-- NEW REQUESTS -->
    <?php if (!$isAdvertisingOnly && !($isFinishingCompany && empty(array_diff($company_services, ['finishing'])))): ?>
<div class="panel" style="margin-bottom:28px">
    <div class="panel-header">
        <h2>Open Requests</h2>
        <span class="sub">Businesses looking for your services</span>
    </div>

    <?php if ($newRequestsRes && pg_num_rows($newRequestsRes) > 0): ?>
        <div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(300px,1fr));gap:16px;padding:4px 0;">
        <?php while ($req = pg_fetch_assoc($newRequestsRes)): ?>
        <div style="background:#fff;border:1.5px solid #e5eaf2;border-radius:0;padding:20px;display:flex;flex-direction:column;gap:12px;">

            <div style="display:flex;align-items:center;justify-content:space-between;">
                <div style="width:42px;height:42px;border-radius:0;background:rgba(0,76,172,.08);display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:800;color:#004cac;flex-shrink:0;">
                    <?= strtoupper(substr($req["business_name"], 0, 2)) ?>
                </div>
                <span style="font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:999px;background:#fef9c3;color:#854d0e;border:1px solid rgba(234,179,8,.2);">
                    Pending
                </span>
            </div>

            <div>
                <div style="font-size:1rem;font-weight:800;color:#111827;"><?= htmlspecialchars($req["business_name"]) ?></div>
                <div style="font-size:.78rem;color:#6b7280;font-weight:600;margin-top:2px;">
                    <i class="bi bi-geo-alt me-1" style="color:#004cac"></i><?= htmlspecialchars($req["city"] ?? "—") ?>
                    <?php if (!empty($req["phone"])): ?>
                        &nbsp;·&nbsp;<i class="bi bi-telephone me-1" style="color:#004cac"></i><?= htmlspecialchars($req["phone"]) ?>
                    <?php endif; ?>
                </div>
            </div>

            <div style="display:flex;align-items:center;gap:8px;">
                <span style="font-size:.78rem;font-weight:700;padding:4px 12px;border-radius:999px;background:rgba(0,76,172,.08);color:#004cac;border:1px solid rgba(0,76,172,.15);">
                    <i class="bi bi-tools me-1"></i><?= htmlspecialchars(formatServices($req["services"])) ?>
                </span>
            </div>

            <div style="font-size:.75rem;color:#9ca3af;font-weight:600;">
                <i class="bi bi-clock me-1"></i><?= timeAgo($req["created_at"]) ?>
            </div>
            <?php
$svcKey = trim(trim($req["services"], '{}'));
$chips = [];

// Business type — always show
if (!empty($req["business_type"])) {
    $chips[] = ['icon' => 'bi-shop', 'val' => ucfirst($req["business_type"])];
}

// Service-specific chips
if (in_array($svcKey, ['ac', 'kitchen', 'network', 'electrical'])) {
    if (!empty($req["area_sqm"]))
        $chips[] = ['icon' => 'bi-rulers', 'val' => $req["area_sqm"] . ' sqm'];
}
if (in_array($svcKey, ['ac', 'kitchen', 'network', 'electrical'])) {
    if (!empty($req["place_size"]))
        $chips[] = ['icon' => 'bi-building', 'val' => ucfirst($req["place_size"])];
}
if ($svcKey === 'pos') {
    if (!empty($req["indoor_tables"]))
        $chips[] = ['icon' => 'bi-people', 'val' => $req["indoor_tables"] . ' seats'];
    if (!empty($req["seat_count"]))
        $chips[] = ['icon' => 'bi-person-check', 'val' => $req["seat_count"] . ' total seats'];
}
?>
<?php if (!empty($chips)): ?>
<div style="display:flex;flex-wrap:wrap;gap:6px;">
    <?php foreach ($chips as $chip): ?>
    <span style="display:inline-flex;align-items:center;gap:4px;font-size:.72rem;font-weight:700;padding:3px 10px;border-radius:999px;background:#f1f5f9;color:#374151;border:1px solid rgba(0,0,0,.08);">
        <i class="bi <?= $chip['icon'] ?>"></i> <?= htmlspecialchars($chip['val']) ?>
    </span>
    <?php endforeach; ?>
</div>
<?php endif; ?>

            <button onclick="toggleForm(<?= $req['request_id'] ?>)"
                style="width:100%;padding:9px;background:#004cac;color:#fff;border:none;border-radius:0;font-weight:700;font-size:.85rem;cursor:pointer;">
                <i class="bi bi-send me-1"></i> Submit Quote
            </button>

            <div class="quote-form" id="form-<?= $req['request_id'] ?>">
                <form method="POST">
                    <input type="hidden" name="request_id" value="<?= (int)$req['request_id'] ?>">
                    <div class="mb-2">
                        <label class="form-label fw-semibold" style="font-size:.82rem;">Message <span class="text-muted fw-normal">(optional)</span></label>
                        <textarea name="message" class="form-control form-control-sm" rows="2" placeholder="Brief note..."></textarea>
                    </div>
                    <div style="display:flex;gap:8px;">
                        <button type="submit" name="submit_quote"
                            style="flex:1;padding:8px;background:#004cac;color:#fff;border:none;border-radius:0;font-weight:700;font-size:.82rem;cursor:pointer;">
                            Send Quote
                        </button>
                        <button type="button" onclick="toggleForm(<?= $req['request_id'] ?>)"
                            style="padding:8px 14px;background:#f1f5f9;color:#374151;border:none;border-radius:0;font-weight:700;font-size:.82rem;cursor:pointer;">
                            Cancel
                        </button>
                    </div>
                </form>
            </div>

        </div>
        <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-box">No open requests matching your services right now.</div>
    <?php endif; ?>
</div>
    <?php endif; ?>

    <?php if (!$isAdvertisingOnly && !($isFinishingCompany && empty(array_diff($company_services, ['finishing'])))): ?>
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
    <?php endif; ?>

    <?php if ($isAdvertisingOnly): ?>
    <div class="panel">
        <div class="panel-header">
            <h2>Your Profile</h2>
            <span class="sub">You are listed as an advertising company on SetupForge</span>
        </div>
        <div class="empty-box">
            Businesses can discover and contact you through the Service Jobs page after their setup is complete.
        </div>
    </div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function toggleForm(id) {
    const form = document.getElementById('form-' + id);
    form.classList.toggle('open');
}
</script>
<script>
function toggleFinishingForm(id) {
    const form = document.getElementById('fform-' + id);
    form.classList.toggle('open');
}
</script>
</body>
</html>