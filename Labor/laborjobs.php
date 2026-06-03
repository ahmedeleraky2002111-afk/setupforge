<?php
session_start();
require_once "../db.php";

/* CHECK LOGIN */
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

/* CHECK USER TYPE */
if (!isset($_SESSION["user_type"]) || $_SESSION["user_type"] !== "labor") {
    header("Location: ../home.php");
    exit();
}

$worker_id = (int) $_SESSION["user_id"];
$successMessage = "";
$errorMessage = "";

/* GET PROVIDER TYPE */
$type_query = pg_query_params(
    $conn,
    "SELECT provider_type
     FROM labors
     WHERE user_id = $1
     LIMIT 1",
    [$worker_id]
);

$type_row = $type_query ? pg_fetch_assoc($type_query) : null;
$provider_type = $type_row['provider_type'] ?? '';
$isTechnician = ($provider_type === 'technician');

/* GET LABOR ROLE */
$role_query = pg_query_params(
    $conn,
    "SELECT labor_role
     FROM labors
     WHERE user_id = $1
     LIMIT 1",
    [$worker_id]
);

$role_row = $role_query ? pg_fetch_assoc($role_query) : null;
$labor_role = strtolower(trim($role_row['labor_role'] ?? ''));

/* APPLY TO GROUPED LABOR JOB */
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST['apply_group']) && !$isTechnician) {

    $title = trim($_POST['title'] ?? '');
    $location = trim($_POST['location'] ?? '');
    $business_id_of_job = (int)($_POST['business_id'] ?? 0);

    if ($title === '' || $location === '' || $business_id_of_job <= 0 || $labor_role === '') {
        $errorMessage = "❌ Invalid labor job group.";
    } else {

        /* Check if already applied to any hidden slot in this exact business+title+location+role group */
        $alreadyApplied = pg_query_params(
            $conn,
            "SELECT ja.id
             FROM job_applications ja
             JOIN jobs j ON ja.job_id = j.job_id
             WHERE ja.labor_user_id = $1
               AND j.job_type = 'labor'
               AND j.business_id = $2
               AND j.title = $3
               AND j.location = $4
AND (LOWER(j.labor_role) = LOWER($5) OR j.labor_role IS NULL)
             LIMIT 1",
            [$worker_id, $business_id_of_job, $title, $location, $labor_role]
        );

        if ($alreadyApplied && pg_num_rows($alreadyApplied) > 0) {
            $errorMessage = "⚠ You already applied for this job.";
        } else {

            /* Find one open hidden slot from this exact grouped labor role */
            $openJobRes = pg_query_params(
                $conn,
                "SELECT job_id
                 FROM jobs
                 WHERE job_type = 'labor'
                   AND business_id = $1
                   AND title = $2
                   AND location = $3
                   AND (LOWER(labor_role) = LOWER($4) OR labor_role IS NULL)
AND status = 'available'
AND worker_id IS NULL
                 ORDER BY job_id ASC
                 LIMIT 1",
                [$business_id_of_job, $title, $location, $labor_role]
            );

            if (!$openJobRes || pg_num_rows($openJobRes) === 0) {
                $errorMessage = "❌ No openings left for this role.";
            } else {

                $openJob = pg_fetch_assoc($openJobRes);
                $job_id = (int)$openJob['job_id'];

                $insertApplication = pg_query_params(
                    $conn,
                    "INSERT INTO job_applications (job_id, labor_user_id, status)
                     VALUES ($1, $2, 'pending')",
                    [$job_id, $worker_id]
                );

                if ($insertApplication) {
                    $successMessage = "✅ Application sent successfully!";
                } else {
                    $errorMessage = "❌ Failed to send application.";
                }
            }
        }
    }
}

/* FETCH AVAILABLE JOBS */
if ($isTechnician) {

    $result = pg_query(
        $conn,
        "SELECT *
         FROM jobs
         WHERE status = 'available'
           AND job_type = 'technician'
         ORDER BY created_at DESC"
    );

} else {

    /* GROUP LABOR JOBS PER BUSINESS + TITLE + LOCATION + ROLE */
    $result = pg_query_params(
        $conn,
        "SELECT
            j.business_id,
            j.title,
            j.location,
            MIN(j.description) AS description,
            COUNT(*) AS total_openings,
            COUNT(*) FILTER (WHERE j.worker_id IS NULL AND j.status = 'available') AS openings_left,
            CASE
                WHEN EXISTS (
                    SELECT 1
                    FROM job_applications ja2
                    JOIN jobs j2 ON ja2.job_id = j2.job_id
                    WHERE ja2.labor_user_id = $1
                      AND j2.job_type = 'labor'
                      AND j2.business_id = j.business_id
                      AND j2.title = j.title
                      AND j2.location = j.location
                      AND LOWER(j2.labor_role) = LOWER($2)
                ) THEN true
                ELSE false
            END AS already_applied
         FROM jobs j
        WHERE j.job_type = 'labor'
AND LOWER(j.labor_role) = LOWER($2)
         GROUP BY j.business_id, j.title, j.location
         HAVING COUNT(*) FILTER (WHERE j.worker_id IS NULL AND j.status = 'available') > 0
         ORDER BY j.business_id DESC, j.title ASC",
        [$worker_id, $labor_role]
    );
}

$userName = $_SESSION["name"] ?? "User";
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo $isTechnician ? "Available Technician Jobs" : "Available Labor Jobs"; ?> - SetupForge</title>

    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
    <link rel="stylesheet" href="labor.css?v=101">

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

    .alert-error-custom{
        background:#fee2e2;
        color:#991b1b;
        border:1px solid #fecaca;
    }

    .jobs-grid{
        display:grid;
        grid-template-columns:repeat(auto-fill, minmax(330px, 1fr));
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

    .job-tag{
        display:inline-flex;
        align-items:center;
        padding:7px 12px;
        border-radius:5px;
        background:#e8f0fb;
        color:#004cac;
        font-size:.78rem;
        font-weight:800;
        margin-bottom:14px;
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

    .job-stats{
        background:#f8fafc;
        border:1px solid #e5e7eb;
        border-radius:5px;
        padding:14px;
        margin:16px 0;
    }

    .job-stats p{
        margin:6px 0;
        font-size:.92rem;
    }

    .action-btn{
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
        margin-top:10px;
        cursor:pointer;
    }

    .action-btn:hover{
        background:#ffffff;
        color:#004cac;
        transform:none;
    }

    .disabled-btn{
        background:#9ca3af !important;
        border-color:#9ca3af !important;
        box-shadow:none !important;
        cursor:not-allowed;
    }

    .empty-jobs{
        background:#fff;
        border:1px solid #edf2f7;
        border-radius:5px;
        padding:28px;
        box-shadow:var(--sf-shadow-md);
        color:#6b7280;
        font-size:1rem;
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

                <?php if ($isTechnician): ?>
                    
                    <li class="nav-item">
                        <a class="nav-link sf-navlink active" href="laborjobs.php">Available Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link sf-navlink" href="mybids.php">My Bids</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link sf-navlink active" href="laborjobs.php">Available Jobs</a>
                    </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a class="nav-link sf-navlink" href="myjobs.php">My Jobs</a>
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
            <h1><?php echo $isTechnician ? "Available Technician Jobs" : "Available Labor Jobs"; ?></h1>
            <p>
                <?php if ($isTechnician): ?>
                    Browse open technician jobs and submit your bid for the work that fits your expertise.
                <?php else: ?>
                    Browse grouped labor opportunities that match your role and apply to the openings available for you.
                <?php endif; ?>
            </p>
        </div>

        <div class="jobs-hero-right">
            <div class="jobs-chip">
                Provider Type: <?php echo htmlspecialchars(ucfirst($provider_type ?: 'Labor')); ?>
            </div>

            <?php if (!$isTechnician): ?>
                <div class="jobs-chip">
                    Role: <?php echo htmlspecialchars(ucfirst($labor_role ?: 'Not Set')); ?>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <?php if ($successMessage): ?>
        <div class="alert-box alert-success-custom"><?php echo htmlspecialchars($successMessage); ?></div>
    <?php endif; ?>

    <?php if ($errorMessage): ?>
        <div class="alert-box alert-error-custom"><?php echo htmlspecialchars($errorMessage); ?></div>
    <?php endif; ?>

    <?php if ($result && pg_num_rows($result) > 0): ?>
        <div class="jobs-grid">
            <?php while ($job = pg_fetch_assoc($result)): ?>
                <div class="job-card">
                    <div class="job-tag">
                        <?php echo $isTechnician ? "Technician Job" : "Labor Job"; ?>
                    </div>

                    <h3><?php echo htmlspecialchars($job['title']); ?></h3>
                    <p><strong>Location:</strong> <?php echo htmlspecialchars($job['location']); ?></p>

                    <?php if ($isTechnician): ?>

                        <p><strong>Budget:</strong> <?php echo number_format((float)$job['budget'], 2); ?> EGP</p>
                        <p class="desc"><?php echo htmlspecialchars($job['description']); ?></p>

                        <a href="submit_bid.php?job_id=<?php echo (int)$job['job_id']; ?>" class="action-btn">
                            Submit Bid
                        </a>

                    <?php else: ?>

                        <p class="desc"><?php echo htmlspecialchars($job['description']); ?></p>

                        <div class="job-stats">
                            <p><strong>Total Openings:</strong> <?php echo (int)$job['total_openings']; ?></p>
                            <p><strong>Remaining Openings:</strong> <?php echo (int)$job['openings_left']; ?></p>
                        </div>

                        <?php if (!empty($job['already_applied']) && $job['already_applied'] === 't'): ?>
                            <button type="button" class="action-btn disabled-btn" disabled>
                                Already Applied
                            </button>
                        <?php else: ?>
                            <form method="POST">
                                <input type="hidden" name="business_id" value="<?php echo (int)$job['business_id']; ?>">
                                <input type="hidden" name="title" value="<?php echo htmlspecialchars($job['title']); ?>">
                                <input type="hidden" name="location" value="<?php echo htmlspecialchars($job['location']); ?>">
                                <button type="submit" name="apply_group" class="action-btn">Apply Now</button>
                            </form>
                        <?php endif; ?>

                    <?php endif; ?>
                </div>
            <?php endwhile; ?>
        </div>
    <?php else: ?>
        <div class="empty-jobs">No available jobs right now.</div>
    <?php endif; ?>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>