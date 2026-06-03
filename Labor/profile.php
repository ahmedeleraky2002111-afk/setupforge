<?php
session_start();
require_once "../db.php";

/* CHECK LOGIN */
if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}

/* CHECK USER TYPE */
if (
    !isset($_SESSION["user_type"]) ||
    ($_SESSION["user_type"] !== "labor" && $_SESSION["user_type"] !== "technician")
) {
    header("Location: ../home.php");
    exit();
}

$user_id = (int) $_SESSION["user_id"];

/* GET USER + LABOR INFO */
$result = pg_query($conn, "
    SELECT 
        u.email,
        u.phone,
        l.name,
        l.profile_picture,
        l.skills,
        l.provider_type,
        l.avg_rating,
        l.balance
    FROM users u
    INNER JOIN labors l ON l.user_id = u.id
    WHERE u.id = $user_id
    LIMIT 1
");

if (!$result) {
    die("Profile query failed: " . pg_last_error($conn));
}

$user = pg_fetch_assoc($result);

if (!$user) {
    die("No profile data found for this user.");
}

$provider_type = $user["provider_type"];

/* GET TOTAL EARNINGS */
$earnings_query = pg_query($conn, "
    SELECT COALESCE(SUM(price), 0) AS total_earnings
    FROM jobs
    WHERE worker_id = $user_id
      AND status = 'completed'
");

if ($earnings_query) {
    $earnings_data = pg_fetch_assoc($earnings_query);
    $total_earnings = (float) ($earnings_data["total_earnings"] ?? 0);
} else {
    $total_earnings = 0;
}

/* OPTIONAL ACTIVE JOBS COUNT */
$active_jobs_query = pg_query($conn, "
    SELECT COUNT(*) AS active_jobs
    FROM jobs
    WHERE worker_id = $user_id
      AND status IN ('assigned', 'in_progress', 'accepted')
");

if ($active_jobs_query) {
    $active_jobs_data = pg_fetch_assoc($active_jobs_query);
    $active_jobs = (int) ($active_jobs_data["active_jobs"] ?? 0);
} else {
    $active_jobs = 0;
}

/* PROFILE IMAGE PATH */
$profile_picture = trim((string)($user["profile_picture"] ?? ""));

/*
    If stored path looks like: Labor/uploads/file.jpg
    and profile.php is inside Labor/,
    then convert it to uploads/file.jpg
*/
if ($profile_picture !== "" && strpos($profile_picture, "Labor/") === 0) {
    $profile_picture = substr($profile_picture, 6);
}

$userName = $user["name"] ?? ($_SESSION["name"] ?? "User");
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1.0">
<title>Profile - SetupForge</title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="labor.css?v=104">

<style>
    .profile-page{
        max-width:1450px;
        margin:auto;
        padding:34px;
    }

    .profile-hero{
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

    .profile-hero h1{
        font-size:2.2rem;
        line-height:1.1;
        margin:0 0 8px 0;
        color:#152033;
        font-weight:900;
        letter-spacing:-.7px;
    }

    .profile-hero p{
        margin:0;
        color:var(--sf-muted);
        font-size:1rem;
        max-width:760px;
    }

    .profile-hero-right{
        display:flex;
        gap:12px;
        flex-wrap:wrap;
        justify-content:flex-end;
    }

    .profile-chip{
        background:#fff;
        border:1px solid var(--sf-border);
        border-radius:5px;
        padding:14px 18px;
        box-shadow:var(--sf-shadow-sm);
        color:#2558a8;
        font-weight:800;
        white-space:nowrap;
    }

    .profile-card{
        background:#fff;
        padding:30px;
        border-radius:5px;
        box-shadow:var(--sf-shadow-md);
        border:1px solid #edf2f7;
        overflow:hidden;
    }

    .profile-header{
        display:flex;
        align-items:center;
        gap:22px;
        margin-bottom:28px;
        padding-bottom:24px;
        border-bottom:1px solid #eef2f7;
    }

    .profile-pic{
        width:120px;
        height:120px;
        border-radius:50%;
        object-fit:cover;
        border:5px solid #eef3ff;
        box-shadow:var(--sf-shadow-sm);
        flex-shrink:0;
    }

    .avatar{
        width:120px;
        height:120px;
        background:#004cac;
        border-radius:50%;
        display:flex;
        align-items:center;
        justify-content:center;
        font-size:44px;
        color:white;
        font-weight:900;
        border:5px solid #eef3ff;
        box-shadow:var(--sf-shadow-sm);
        flex-shrink:0;
    }

    .profile-info h2{
        margin:0 0 8px 0;
        font-size:2rem;
        color:#1b2a4a;
        font-weight:900;
    }

    .profile-info p{
        color:#6b7280;
        margin:0 0 6px 0;
        font-size:.98rem;
        line-height:1.5;
    }

    .badge-role{
        display:inline-flex;
        align-items:center;
        background:#e8f0fb;
        color:#004cac;
        padding:9px 14px;
        border-radius:5px;
        font-size:14px;
        font-weight:800;
        margin-top:10px;
        text-transform:capitalize;
    }

    .info-grid{
        display:grid;
        grid-template-columns:1fr 1fr;
        gap:20px;
        margin-bottom:24px;
    }

    .info-box{
        background:#ffffff;
        border:1px solid #e6eefc;
        border-radius:5px;
        padding:20px;
        box-shadow:var(--sf-shadow-sm);
    }

    .info-box h4{
        margin:0 0 10px 0;
        color:#004cac;
        font-size:.92rem;
        font-weight:900;
        text-transform:uppercase;
        letter-spacing:.7px;
    }

    .info-box p{
        color:#4b5563;
        line-height:1.7;
        word-break:break-word;
        margin:0;
        font-size:.96rem;
    }

    .full-width{
        grid-column:1 / -1;
    }

    .stats{
        display:grid;
        grid-template-columns:repeat(3, minmax(0, 1fr));
        gap:18px;
        margin-top:10px;
    }

    .stat-box{
        background:#ffffff;
        padding:22px;
        border-radius:5px;
        text-align:center;
        border:1px solid #edf2f7;
        border-top:3px solid #004cac;
        box-shadow:var(--sf-shadow-sm);
        position:relative;
        overflow:hidden;
    }

    .stat-box::before{
        display:none;
    }

    .stat-box h3{
        color:#004cac;
        margin:0 0 8px 0;
        font-size:2rem;
        font-weight:900;
        letter-spacing:-.5px;
    }

    .stat-box p{
        color:#667085;
        font-size:.92rem;
        margin:0;
        font-weight:600;
    }

    .edit-btn{
        margin-top:26px;
        background:#004cac;
        color:#fff;
        border:1.5px solid #004cac;
        padding:14px 18px;
        border-radius:5px;
        cursor:pointer;
        width:100%;
        transition:background .2s ease, color .2s ease;
        text-align:center;
        display:inline-flex;
        align-items:center;
        justify-content:center;
        text-decoration:none;
        font-weight:800;
        box-shadow:none;
    }

    .edit-btn:hover{
        background:#ffffff;
        color:#004cac;
        transform:none;
    }

    @media (max-width: 1100px){
        .profile-hero{
            grid-template-columns:1fr;
        }
    }

    @media (max-width: 900px){
        .info-grid{
            grid-template-columns:1fr;
        }

        .profile-header{
            flex-direction:column;
            align-items:flex-start;
        }

        .stats{
            grid-template-columns:1fr;
        }
    }

    @media (max-width: 768px){
        .profile-page{
            padding:22px 16px 28px;
        }

        .profile-hero{
            padding:22px 18px;
        }

        .profile-hero h1{
            font-size:1.75rem;
        }

        .profile-hero-right{
            justify-content:flex-start;
        }

        .profile-chip{
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

                <li class="nav-item">
                    <a class="nav-link sf-navlink" href="laborjobs.php">Available Jobs</a>
                </li>

                <?php if ($provider_type === "technician"): ?>
                    <li class="nav-item">
                        <a class="nav-link sf-navlink" href="mybids.php">My Bids</a>
                    </li>
                <?php endif; ?>

                <li class="nav-item">
                    <a class="nav-link sf-navlink" href="myjobs.php">My Jobs</a>
                </li>

                <li class="nav-item">
                    <a class="nav-link sf-navlink active" href="profile.php">Profile</a>
                </li>
            </ul>
        </div>

        <div class="sf-nav-actions">
            <div class="dropdown">
                <button class="btn sf-profile-btn" data-bs-toggle="dropdown" aria-expanded="false">
                    <?php if (!empty($profile_picture)): ?>
                        <img src="<?php echo htmlspecialchars($profile_picture); ?>" alt="Profile Picture">
                    <?php else: ?>
                        <i class="bi bi-person-fill"></i>
                    <?php endif; ?>
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

<div class="profile-page">

    <div class="profile-hero">
        <div>
            <h1>My Profile</h1>
            <p>
                View your professional information, balance, rating, and job activity in one place.
            </p>
        </div>

        <div class="profile-hero-right">
            <div class="profile-chip">
                Provider Type: <?php echo htmlspecialchars(ucfirst($provider_type)); ?>
            </div>
            <div class="profile-chip">
                Balance: <?php echo number_format((float)($user["balance"] ?? 0), 2); ?> EGP
            </div>
        </div>
    </div>

    <div class="profile-card">

        <div class="profile-header">
            <?php if (!empty($profile_picture)) { ?>
                <img src="<?php echo htmlspecialchars($profile_picture); ?>" class="profile-pic" alt="Profile Picture">
            <?php } else { ?>
                <div class="avatar">
                    <?php echo strtoupper(substr($user["name"], 0, 1)); ?>
                </div>
            <?php } ?>

            <div class="profile-info">
                <h2><?php echo htmlspecialchars($user["name"]); ?></h2>
                <p><?php echo htmlspecialchars($user["email"]); ?></p>
                <p><?php echo htmlspecialchars($user["phone"] ?: "No phone added yet."); ?></p>
                <span class="badge-role"><?php echo htmlspecialchars($provider_type); ?></span>
            </div>
        </div>

        <div class="info-grid">
            <div class="info-box">
                <h4>Skills</h4>
                <p>
                    <?php
                    echo !empty($user["skills"])
                        ? htmlspecialchars($user["skills"])
                        : "No skills added yet.";
                    ?>
                </p>
            </div>

            <div class="info-box">
                <h4>Current Balance</h4>
                <p><?php echo number_format((float)($user["balance"] ?? 0), 2); ?> EGP</p>
            </div>

            <div class="info-box full-width">
                <h4>Contact Info</h4>
                <p>
                    <strong>Email:</strong> <?php echo htmlspecialchars($user["email"]); ?><br>
                    <strong>Phone:</strong> <?php echo htmlspecialchars($user["phone"] ?: "No phone added yet."); ?>
                </p>
            </div>
        </div>

        <div class="stats">
            <div class="stat-box">
                <h3><?php echo $active_jobs; ?></h3>
                <p>Active Jobs</p>
            </div>

            <div class="stat-box">
                <h3><?php echo number_format((float)($user["avg_rating"] ?? 0), 1); ?> ⭐</h3>
                <p>Rating</p>
            </div>

            <div class="stat-box">
                <h3><?php echo number_format($total_earnings, 2); ?> EGP</h3>
                <p>Total Earnings</p>
            </div>
        </div>

        <a href="editprofile.php" class="edit-btn">Edit Profile</a>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>