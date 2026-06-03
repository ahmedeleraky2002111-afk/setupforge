<?php
session_start();
require_once "../db.php";

/* CHECK LOGIN */
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

/* CHECK USER TYPE */
if (!isset($_SESSION["user_type"]) ||
   ($_SESSION["user_type"] !== "labor" && $_SESSION["user_type"] !== "technician")) {

    header("Location: ../home.php");
    exit();
}

$labor_id = intval($_SESSION["user_id"]);

$type_query = pg_query($conn, "
    SELECT provider_type
    FROM labors
    WHERE user_id = $labor_id
");

$type_row = $type_query ? pg_fetch_assoc($type_query) : null;
$provider_type = $type_row['provider_type'] ?? '';
$successMessage = "";

/* MARK JOB AS COMPLETED + PAY WORKER */
if ($_SERVER["REQUEST_METHOD"] == "POST" && isset($_POST['complete_job'])) {

    $job_id = intval($_POST['job_id']);

    // Get job details safely
    $jobQuery = pg_query($conn, "
        SELECT price, status
        FROM jobs
        WHERE job_id = $job_id
        AND worker_id = $labor_id
    ");

    if ($jobQuery && pg_num_rows($jobQuery) > 0) {

        $jobData = pg_fetch_assoc($jobQuery);

        // Prevent double payment
        if ($jobData['status'] !== 'completed') {

            $price = floatval($jobData['price']);

            // 10% platform commission
            $platform_cut = $price * 0.10;
            $worker_earnings = $price - $platform_cut;

            // Mark job completed
            pg_query($conn, "
                UPDATE jobs
                SET status = 'completed'
                WHERE job_id = $job_id
            ");

            // Add money to worker balance
            pg_query($conn, "
                UPDATE labors
                SET balance = balance + $worker_earnings
                WHERE user_id = $labor_id
            ");

            $successMessage = "✅ Job completed! " . number_format($worker_earnings, 2) . " EGP added to your balance.";
        } else {
            $successMessage = "⚠ Job already completed.";
        }
    }
}

/* FETCH WORKER BALANCE */
$balanceQuery = pg_query($conn, "
    SELECT balance FROM labors WHERE user_id = $labor_id
");

$balanceData = $balanceQuery ? pg_fetch_assoc($balanceQuery) : null;
$current_balance = number_format((float)($balanceData['balance'] ?? 0), 2);

/* FETCH WORKER JOBS */
$result = pg_query($conn, "
    SELECT *
    FROM jobs
    WHERE worker_id = $labor_id
    ORDER BY job_id DESC
");

$userName = $_SESSION["name"] ?? "User";

/* Small extra stats from same jobs result logic */
$totalJobsCount = 0;
$activeJobsCount = 0;
$completedJobsCount = 0;

$statsResult = pg_query($conn, "
    SELECT
        COUNT(*) AS total_jobs,
        COUNT(*) FILTER (WHERE status = 'active') AS active_jobs,
        COUNT(*) FILTER (WHERE status = 'completed') AS completed_jobs
    FROM jobs
    WHERE worker_id = $labor_id
");

if ($statsResult && pg_num_rows($statsResult) > 0) {
    $statsRow = pg_fetch_assoc($statsResult);
    $totalJobsCount = (int)($statsRow['total_jobs'] ?? 0);
    $activeJobsCount = (int)($statsRow['active_jobs'] ?? 0);
    $completedJobsCount = (int)($statsRow['completed_jobs'] ?? 0);
}

function myJobStatusClass($status) {
    switch ($status) {
        case 'completed':
            return 'badge-completed';
        case 'active':
        case 'assigned':
        case 'processing':
            return 'badge-active';
        case 'available':
            return 'badge-available';
        default:
            return 'badge-default';
    }
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Jobs - SetupForge</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="labor.css?v=102">

<style>
    .jobs-page{
        max-width:1450px;
        margin:auto;
        padding:34px;
    }

    .jobs-hero{
        display:grid;
        grid-template-columns:1.5fr auto;
        gap:20px;
        align-items:center;
        margin-bottom:28px;
        background:linear-gradient(135deg, rgba(255,255,255,.95), rgba(248,250,252,.95));
        border:1px solid #eef2f7;
        border-radius:5px;
        padding:26px 28px;
        box-shadow:var(--sf-shadow-md);
    }

    .jobs-hero h1{
        font-size:2.2rem;
        line-height:1.1;
        margin:0 0 8px 0;
        color:#152033;
        font-weight:900;
        letter-spacing:-.7px;
    }

    .jobs-hero p{
        margin:0;
        color:var(--sf-muted);
        font-size:1rem;
        max-width:760px;
    }

    .jobs-hero-right{
        display:flex;
        gap:12px;
        flex-wrap:wrap;
        justify-content:flex-end;
    }

    .jobs-chip{
        background:#fff;
        border:1px solid var(--sf-border);
        border-radius:5px;
        padding:14px 18px;
        box-shadow:var(--sf-shadow-sm);
        color:#2558a8;
        font-weight:800;
        white-space:nowrap;
    }

    .alert-box{
        padding:16px 18px;
        border-radius:5px;
        margin-bottom:18px;
        font-weight:700;
        box-shadow:var(--sf-shadow-sm);
    }

    .alert-success-custom{
        background:#dcfce7;
        color:#166534;
        border:1px solid #bbf7d0;
    }

    .cards{
        display:grid;
        grid-template-columns:repeat(4, minmax(0, 1fr));
        gap:18px;
        margin-bottom:26px;
    }

    .card-box{
        background:#ffffff;
        padding:20px 20px 18px;
        border-radius:5px;
        box-shadow:var(--sf-shadow-md);
        position:relative;
        overflow:hidden;
        border:1px solid #edf2f7;
        border-top:3px solid #004cac;
        min-height:150px;
        transition:transform .18s ease, box-shadow .18s ease;
    }

    .card-box:hover{
        transform:translateY(-2px);
        box-shadow:0 14px 28px rgba(0,0,0,.09);
    }

    .card-box::before{
        display:none;
    }

    .card-box h3{
        color:#6b7280;
        font-size:.82rem;
        margin:0 0 18px 0;
        font-weight:800;
        text-transform:uppercase;
        letter-spacing:.9px;
    }

    .card-box p{
        font-size:2rem;
        line-height:1;
        font-weight:900;
        color:#004cac;
        margin:0 0 14px 0;
        letter-spacing:-.7px;
    }

    .card-sub{
        color:#6b7280;
        font-size:.93rem;
        line-height:1.45;
    }

    .jobs-grid{
        display:grid;
        grid-template-columns:repeat(auto-fill, minmax(320px, 1fr));
        gap:20px;
    }

    .job-card{
        background:#ffffff;
        padding:22px;
        border-radius:5px;
        box-shadow:var(--sf-shadow-md);
        border:1px solid #edf2f7;
        transition:transform .18s ease, box-shadow .18s ease;
    }

    .job-card:hover{
        transform:translateY(-2px);
        box-shadow:0 14px 28px rgba(0,0,0,.09);
    }

    .job-card h3{
        color:#162033;
        margin:0 0 12px 0;
        font-size:1.18rem;
        font-weight:900;
    }

    .job-card p{
        margin:8px 0;
        color:#475569;
        line-height:1.55;
        font-size:.95rem;
    }

    .desc{
        margin-top:10px;
        color:#5b6473;
    }

    .status-badge{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        padding:7px 12px;
        border-radius:5px;
        font-size:.8rem;
        font-weight:800;
        text-transform:capitalize;
        margin-top:12px;
    }

    .badge-completed{
        background:#e8f0fb;
        color:#004cac;
    }

    .badge-active{
        background:#dbeafe;
        color:#004cac;
    }

    .badge-available{
        background:#e5e7eb;
        color:#374151;
    }

    .badge-default{
        background:#f3f4f6;
        color:#374151;
    }

    .complete-btn{
        display:inline-flex;
        align-items:center;
        justify-content:center;
        width:100%;
        background:#004cac;
        color:#fff;
        text-decoration:none;
        padding:12px 14px;
        border-radius:5px;
        font-size:.92rem;
        font-weight:800;
        transition:background .2s ease, color .2s ease;
        border:1.5px solid #004cac;
        box-shadow:none;
        margin-top:14px;
        cursor:pointer;
    }

    .complete-btn:hover{
        background:#ffffff;
        color:#004cac;
        transform:none;
    }

    .empty-box{
        background:#fff;
        padding:40px;
        border-radius:5px;
        text-align:center;
        margin-top:10px;
        box-shadow:var(--sf-shadow-md);
        border:1px solid #edf2f7;
    }

    .empty-box h3{
        margin-bottom:10px;
        color:#162033;
        font-weight:900;
    }

    .empty-box p{
        color:#6b7280;
        margin:0;
    }

    @media (max-width: 1200px){
        .cards{
            grid-template-columns:repeat(2, 1fr);
        }
    }

    @media (max-width: 1100px){
        .jobs-hero{
            grid-template-columns:1fr;
        }
    }

    @media (max-width: 768px){
        .jobs-page{
            padding:22px 16px 28px;
        }

        .jobs-hero{
            padding:22px 18px;
        }

        .jobs-hero h1{
            font-size:1.75rem;
        }

        .jobs-hero-right{
            justify-content:flex-start;
        }

        .jobs-chip{
            width:100%;
        }

        .cards{
            grid-template-columns:1fr;
        }
    }
</style>
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark sf-navbar">
    <div class="container">

        <a class="navbar-brand sf-brand-wrap" href="dashboard.php">
            <div class="sf-logo">
                <img src="../assets/images/Logo.png" alt="SetupForge Logo">
            </div>
            <span class="fw-bold">SetupForge</span>
        </a>

        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#laborNavbar" aria-controls="laborNavbar" aria-expanded="false" aria-label="Toggle navigation">
            <span class="navbar-toggler-icon"></span>
        </button>

        <div class="collapse navbar-collapse justify-content-center" id="laborNavbar">
            <ul class="navbar-nav gap-3">
                <li class="nav-item">
                    <a class="nav-link sf-navlink" href="dashboard.php">Dashboard</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link sf-navlink" href="laborjobs.php">Available Jobs</a>
                </li>

                <?php if($provider_type == 'technician'): ?>
                    <li class="nav-item">
                        <a class="nav-link sf-navlink" href="mybids.php">My Bids</a>
                    </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a class="nav-link sf-navlink active" href="myjobs.php">My Jobs</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link sf-navlink" href="profile.php">Profile</a>
                </li>
            </ul>
        </div>

        <div class="sf-nav-actions">
            <div class="dropdown">
                <button class="btn sf-profile-btn" data-bs-toggle="dropdown" aria-expanded="false">
                    <i class="bi bi-person-fill"></i>
                </button>

                <ul class="dropdown-menu dropdown-menu-end sf-dropdown">
                    <li class="px-3 py-2 fw-semibold">
                        <?php echo htmlspecialchars($userName); ?>
                    </li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                    <li><a class="dropdown-item" href="../auth/logout.php">Logout</a></li>
                </ul>
            </div>
        </div>

    </div>
</nav>

<div class="jobs-page">

    <div class="jobs-hero">
        <div>
            <h1>My Jobs</h1>
            <p>
                Track all jobs assigned to you, check their status, and mark active jobs as completed when the work is finished.
            </p>
        </div>

        <div class="jobs-hero-right">
            <div class="jobs-chip">
                Provider Type: <?php echo htmlspecialchars(ucfirst($provider_type ?: 'Labor')); ?>
            </div>
            <div class="jobs-chip">
                Current Balance: <?php echo $current_balance; ?> EGP
            </div>
        </div>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert-box alert-success-custom"><?php echo htmlspecialchars($successMessage); ?></div>
    <?php endif; ?>

    <div class="cards">
        <div class="card-box">
            <h3>Total Jobs</h3>
            <p><?php echo $totalJobsCount; ?></p>
            <div class="card-sub">All jobs assigned to you</div>
        </div>

        <div class="card-box">
            <h3>Active Jobs</h3>
            <p><?php echo $activeJobsCount; ?></p>
            <div class="card-sub">Jobs currently in progress</div>
        </div>

        <div class="card-box">
            <h3>Completed Jobs</h3>
            <p><?php echo $completedJobsCount; ?></p>
            <div class="card-sub">Work you have already finished</div>
        </div>

        <div class="card-box">
            <h3>Wallet Balance</h3>
            <p><?php echo $current_balance; ?> EGP</p>
            <div class="card-sub">Current balance in your account</div>
        </div>
    </div>

    <?php if ($result && pg_num_rows($result) > 0): ?>

        <div class="jobs-grid">

            <?php while ($job = pg_fetch_assoc($result)): ?>
                <div class="job-card">
                    <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?></p>
                    <p><strong>Price:</strong> <?php echo number_format((float)$job['price'], 2); ?> EGP</p>
                    <p class="desc"><?php echo htmlspecialchars($job['description']); ?></p>

                    <div class="status-badge <?php echo myJobStatusClass($job['status']); ?>">
                        Status: <?php echo htmlspecialchars($job['status']); ?>
                    </div>

                    <?php if ($job['status'] == 'active'): ?>
                        <form method="POST">
                            <input type="hidden" name="job_id" value="<?php echo (int)$job['job_id']; ?>">
                            <button type="submit" name="complete_job" class="complete-btn">
                                Mark as Completed
                            </button>
                        </form>
                    <?php endif; ?>

                </div>
            <?php endwhile; ?>

        </div>

    <?php else: ?>

        <div class="empty-box">
            <h3>No Jobs Yet</h3>
            <p>Go to Available Jobs and accept your first job.</p>
        </div>

    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>