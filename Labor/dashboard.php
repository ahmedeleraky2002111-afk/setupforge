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

$worker_id = (int)$_SESSION["user_id"];

/* GET PROVIDER TYPE + AVAILABILITY */
$type_query = pg_query_params(
    $conn,
    "SELECT provider_type, availability_status
     FROM labors
     WHERE user_id = $1
     LIMIT 1",
    [$worker_id]
);

$type_row = $type_query ? pg_fetch_assoc($type_query) : null;
$provider_type = $type_row["provider_type"] ?? "";
$availability_status = $type_row["availability_status"] ?? "available";

/* Decide job_type based on provider_type */
$job_type = ($provider_type === "technician") ? "technician" : "labor";

/* USER INFO FOR NAVBAR */
$userName = $_SESSION["name"] ?? "User";

/* =========================
   Dashboard Stats
========================= */

$card1Title = "Jobs Today";
$card1Value = 0;

if ($provider_type === "technician") {
    /* Jobs Completed Today - technician */
    $jobsTodayRes = pg_query_params(
        $conn,
        "SELECT COUNT(*) 
         FROM jobs 
         WHERE worker_id = $1
           AND job_type = 'technician'
           AND status = 'completed'
           AND created_at::date = CURRENT_DATE",
        [$worker_id]
    );
    $card1Value = $jobsTodayRes ? (int)pg_fetch_result($jobsTodayRes, 0, 0) : 0;
} else {
    /* Available labor jobs */
    $availableJobsRes = pg_query(
        $conn,
        "SELECT COUNT(*) 
         FROM jobs 
         WHERE job_type = 'labor'
           AND status = 'available'"
    );
    $card1Title = "Available Jobs";
    $card1Value = $availableJobsRes ? (int)pg_fetch_result($availableJobsRes, 0, 0) : 0;
}

/* Total Completed Jobs */
$completedJobsRes = pg_query_params(
    $conn,
    "SELECT COUNT(*) 
     FROM jobs 
     WHERE worker_id = $1
       AND job_type = $2
       AND status = 'completed'",
    [$worker_id, $job_type]
);
$completedJobs = $completedJobsRes ? (int)pg_fetch_result($completedJobsRes, 0, 0) : 0;

/* Pending / Active Jobs */
$pendingJobsRes = pg_query_params(
    $conn,
    "SELECT COUNT(*) 
     FROM jobs 
     WHERE worker_id = $1
       AND job_type = $2
       AND status IN ('active', 'assigned', 'processing')",
    [$worker_id, $job_type]
);
$pendingJobs = $pendingJobsRes ? (int)pg_fetch_result($pendingJobsRes, 0, 0) : 0;

/* Total Assigned Jobs */
$totalAssignedRes = pg_query_params(
    $conn,
    "SELECT COUNT(*) 
     FROM jobs 
     WHERE worker_id = $1
       AND job_type = $2",
    [$worker_id, $job_type]
);
$totalAssignedJobs = $totalAssignedRes ? (int)pg_fetch_result($totalAssignedRes, 0, 0) : 0;

/* Card 4 */
$card4Title = "Total Earnings";
$card4Value = "";
$totalEarnings = 0;

if ($provider_type === "technician") {
    $totalEarningsRes = pg_query_params(
        $conn,
        "SELECT COALESCE(SUM(COALESCE(price,0)),0) AS total
         FROM jobs 
         WHERE worker_id = $1
           AND job_type = 'technician'
           AND status = 'completed'",
        [$worker_id]
    );

    if ($totalEarningsRes && pg_num_rows($totalEarningsRes) > 0) {
        $earningsRow = pg_fetch_assoc($totalEarningsRes);
        $totalEarnings = (float)($earningsRow["total"] ?? 0);
    }

    $card4Value = number_format($totalEarnings, 2) . " EGP";
} else {
    $card4Title = "Availability";
    $card4Value = ucfirst($availability_status);
}

/* This Month Earnings - technician only */
$thisMonthEarnings = 0;
if ($provider_type === "technician") {
    $thisMonthEarningsRes = pg_query_params(
        $conn,
        "SELECT COALESCE(SUM(COALESCE(price,0)),0) AS total
         FROM jobs
         WHERE worker_id = $1
           AND job_type = 'technician'
           AND status = 'completed'
           AND date_trunc('month', created_at) = date_trunc('month', CURRENT_DATE)",
        [$worker_id]
    );

    if ($thisMonthEarningsRes && pg_num_rows($thisMonthEarningsRes) > 0) {
        $monthRow = pg_fetch_assoc($thisMonthEarningsRes);
        $thisMonthEarnings = (float)($monthRow["total"] ?? 0);
    }
}

/* Completion Rate */
$completionRate = 0;
if ($totalAssignedJobs > 0) {
    $completionRate = round(($completedJobs / $totalAssignedJobs) * 100);
}

/* Average Job Value - technician only */
$averageJobValue = 0;
if ($provider_type === "technician" && $completedJobs > 0) {
    $averageJobValue = $totalEarnings / $completedJobs;
}

/* Recent Jobs */
$recentJobs = pg_query_params(
    $conn,
    "SELECT job_id, title, location, status, salary_amount, price, created_at
     FROM jobs 
     WHERE worker_id = $1
       AND job_type = $2
     ORDER BY job_id DESC
     LIMIT 6",
    [$worker_id, $job_type]
);

/* Active Jobs List */
$activeJobsList = pg_query_params(
    $conn,
    "SELECT job_id, title, location, status, salary_amount, price, created_at
     FROM jobs
     WHERE worker_id = $1
       AND job_type = $2
       AND status IN ('active', 'assigned', 'processing')
     ORDER BY job_id DESC
     LIMIT 4",
    [$worker_id, $job_type]
);

/* Recent Activity */
$recentActivity = pg_query_params(
    $conn,
    "SELECT title, status, salary_amount, price, created_at
     FROM jobs
     WHERE worker_id = $1
       AND job_type = $2
     ORDER BY created_at DESC
     LIMIT 6",
    [$worker_id, $job_type]
);

/* Notifications */
$notifications = [];

if ($provider_type === "technician") {
    if ($pendingJobs > 0) {
        $notifications[] = "You have {$pendingJobs} active technician job" . ($pendingJobs > 1 ? "s" : "") . " in progress.";
    }
    if ($completedJobs > 0) {
        $notifications[] = "You have completed {$completedJobs} technician job" . ($completedJobs > 1 ? "s" : "") . ".";
    }
    if ($totalEarnings > 0) {
        $notifications[] = "Your total completed earnings reached " . number_format($totalEarnings, 2) . " EGP.";
    }
} else {
    if ($availability_status !== "available") {
        $notifications[] = "Your availability is currently set to " . ucfirst($availability_status) . ".";
    }
    if ($card1Value > 0) {
        $notifications[] = "There are {$card1Value} available labor jobs you can check now.";
    }
    if ($pendingJobs > 0) {
        $notifications[] = "You have {$pendingJobs} active labor job" . ($pendingJobs > 1 ? "s" : "") . ".";
    }
}

if ($completedJobs === 0) {
    $notifications[] = "Complete your first " . ($provider_type === "technician" ? "technician" : "labor") . " job to grow your dashboard stats.";
}

$notificationCount = count($notifications);

/* Helpers */
function formatStatusClass($status) {
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

function formatTimeAgo($datetime) {
    if (!$datetime) return "Unknown time";
    $timestamp = strtotime($datetime);
    if (!$timestamp) return "Unknown time";

    $diff = time() - $timestamp;

    if ($diff < 60) return "Just now";
    if ($diff < 3600) return floor($diff / 60) . " min ago";
    if ($diff < 86400) return floor($diff / 3600) . " hour" . (floor($diff / 3600) > 1 ? "s" : "") . " ago";
    if ($diff < 172800) return "Yesterday";
    if ($diff < 2592000) return floor($diff / 86400) . " day" . (floor($diff / 86400) > 1 ? "s" : "") . " ago";

    return date("M j, Y", $timestamp);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?php echo ($provider_type === 'technician') ? 'Technician Dashboard' : 'Labor Dashboard'; ?></title>

<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="labor.css">
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

                <?php if ($provider_type === "technician"): ?>
                    <li class="nav-item">
                        <a class="nav-link sf-navlink" href="laborjobs.php">Available Jobs</a>
                    </li>
                    <li class="nav-item">
                        <a class="nav-link sf-navlink" href="mybids.php">My Bids</a>
                    </li>
                <?php else: ?>
                    <li class="nav-item">
                        <a class="nav-link sf-navlink" href="laborjobs.php">Available Jobs</a>
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
                <button class="btn sf-icon-btn sf-bell-btn" type="button" data-bs-toggle="dropdown" aria-expanded="false" title="Notifications">
                    <i class="bi bi-bell"></i>
                    <?php if ($notificationCount > 0): ?>
                        <span class="sf-bell-count"><?php echo $notificationCount > 9 ? '9+' : $notificationCount; ?></span>
                    <?php endif; ?>
                </button>

                <div class="dropdown-menu dropdown-menu-end sf-dropdown sf-notification-dropdown">
                    <div class="sf-dropdown-title">Notifications</div>

                    <?php if ($notificationCount > 0): ?>
                        <div class="sf-dropdown-notice-list">
                            <?php foreach ($notifications as $notice): ?>
                                <div class="sf-dropdown-notice-item">
                                    <div class="sf-dropdown-notice-icon">
                                        <i class="bi bi-bell"></i>
                                    </div>
                                    <div class="sf-dropdown-notice-text">
                                        <?php echo htmlspecialchars($notice); ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                    <?php else: ?>
                        <div class="sf-dropdown-empty">No notifications right now.</div>
                    <?php endif; ?>
                </div>
            </div>

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

<div class="main">

    <div class="header">
        <div class="header-left">
            <h1>Welcome <?php echo htmlspecialchars($userName); ?></h1>
            <p>
                <?php if ($provider_type === "technician"): ?>
                    Track your technician jobs, earnings, progress, and recent activity from one place.
                <?php else: ?>
                    Track your labor jobs, availability, progress, and recent activity from one place.
                <?php endif; ?>
            </p>
        </div>

        <div class="header-right">
            <div class="header-chip">
                Provider Type: <?php echo htmlspecialchars(ucfirst($provider_type ?: 'Labor')); ?>
            </div>
            <div class="header-chip">
                <?php echo ($provider_type === "technician") ? "Completion Rate" : "Status"; ?>:
                <?php echo ($provider_type === "technician") ? $completionRate . "%" : ucfirst($availability_status); ?>
            </div>
        </div>
    </div>

    <div class="cards">
        <div class="card-box">
            <h3><?php echo htmlspecialchars($card1Title); ?></h3>
            <p><?php echo $card1Value; ?></p>
            <div class="card-sub">
                <?php echo ($provider_type === "technician") ? "Completed today" : "Currently open for labor"; ?>
            </div>
        </div>

        <div class="card-box">
            <h3>Completed Jobs</h3>
            <p><?php echo $completedJobs; ?></p>
            <div class="card-sub">All completed work</div>
        </div>

        <div class="card-box">
            <h3>Pending Jobs</h3>
            <p><?php echo $pendingJobs; ?></p>
            <div class="card-sub">Currently in progress</div>
        </div>

        <div class="card-box">
            <h3><?php echo htmlspecialchars($card4Title); ?></h3>
            <p><?php echo htmlspecialchars($card4Value); ?></p>
            <div class="card-sub">
                <?php echo ($provider_type === "technician") ? "Gross completed earnings" : "Your current availability"; ?>
            </div>
        </div>

        <div class="card-box">
            <h3>Total Assigned</h3>
            <p><?php echo $totalAssignedJobs; ?></p>
            <div class="card-sub">All jobs assigned to you</div>
        </div>

        <?php if ($provider_type === "technician"): ?>
            <div class="card-box">
                <h3>This Month</h3>
                <p><?php echo number_format($thisMonthEarnings, 2); ?> EGP</p>
                <div class="card-sub">This month’s completed earnings</div>
            </div>

            <div class="card-box">
                <h3>Completion Rate</h3>
                <p><?php echo $completionRate; ?>%</p>
                <div class="card-sub"><?php echo $completedJobs; ?> of <?php echo $totalAssignedJobs; ?> jobs completed</div>
            </div>

            <div class="card-box">
                <h3>Avg Job Value</h3>
                <p><?php echo number_format($averageJobValue, 2); ?> EGP</p>
                <div class="card-sub">Average completed job price</div>
            </div>
        <?php else: ?>
            <div class="card-box">
                <h3>Labor Type</h3>
                <p><?php echo htmlspecialchars(ucfirst($provider_type ?: 'Labor')); ?></p>
                <div class="card-sub">Your current provider type</div>
            </div>

            <div class="card-box">
                <h3>Job Progress</h3>
                <p><?php echo $completionRate; ?>%</p>
                <div class="card-sub"><?php echo $completedJobs; ?> completed out of <?php echo $totalAssignedJobs; ?></div>
            </div>

            <div class="card-box">
                <h3>Availability</h3>
                <p><?php echo htmlspecialchars(ucfirst($availability_status)); ?></p>
                <div class="card-sub">Current working status</div>
            </div>
        <?php endif; ?>
    </div>

    <div class="dashboard-grid">

        <div class="panel">
            <div class="panel-header">
                <h2>Active Jobs</h2>
                <span class="sub">Your current work in progress</span>
            </div>

            <?php if ($activeJobsList && pg_num_rows($activeJobsList) > 0): ?>
                <div class="active-jobs-wrap">
                    <?php while ($activeJob = pg_fetch_assoc($activeJobsList)): ?>
                        <div class="active-job-card">
                            <h3><?php echo htmlspecialchars($activeJob["title"]); ?></h3>
                            <p><strong>Location:</strong> <?php echo htmlspecialchars($activeJob["location"]); ?></p>

                            <?php if ($provider_type === "technician"): ?>
                                <p><strong>Price:</strong> <?php echo number_format((float)$activeJob["price"], 2); ?> EGP</p>
                            <?php else: ?>
<p><strong>Salary:</strong> <?php echo number_format((float)$activeJob["salary_amount"], 2); ?> EGP</p>
                            <?php endif; ?>

                            <p><strong>Started:</strong> <?php echo !empty($activeJob["created_at"]) ? date("M j, Y", strtotime($activeJob["created_at"])) : "N/A"; ?></p>

                            <div class="active-job-footer">
                                <span class="status-badge <?php echo formatStatusClass($activeJob["status"]); ?>">
                                    <?php echo htmlspecialchars($activeJob["status"]); ?>
                                </span>
                                <a href="myjobs.php" class="small-btn">Manage Job</a>
                            </div>
                        </div>
                    <?php endwhile; ?>
                </div>
            <?php else: ?>
                <div class="empty-box">No active jobs right now.</div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h2>Notifications</h2>
                <span class="sub">Useful reminders for your account</span>
            </div>

            <?php if ($notificationCount > 0): ?>
                <div class="notice-list">
                    <?php foreach ($notifications as $notice): ?>
                        <div class="notice-item">
                            <div class="notice-icon">
                                <i class="bi bi-bell"></i>
                            </div>
                            <p><?php echo htmlspecialchars($notice); ?></p>
                        </div>
                    <?php endforeach; ?>
                </div>
            <?php else: ?>
                <div class="empty-box">No notifications right now.</div>
            <?php endif; ?>
        </div>

    </div>

    <div class="dashboard-grid-bottom">

        <div class="panel">
            <div class="panel-header">
                <h2>Recent Jobs</h2>
                <span class="sub">Your latest jobs</span>
            </div>

            <?php if ($recentJobs && pg_num_rows($recentJobs) > 0): ?>
                <div class="table-wrap">
                    <table>
                        <tr>
                            <th>Job Title</th>
                            <th>Location</th>
                            <th>Date</th>
                            <th>Status</th>
<th><?php echo ($provider_type === 'technician') ? 'Price' : 'Salary'; ?></th>
                            <th>Action</th>
                        </tr>

                        <?php while ($row = pg_fetch_assoc($recentJobs)): ?>
                            <tr>
                                <td class="job-title"><?php echo htmlspecialchars($row["title"]); ?></td>
                                <td><?php echo htmlspecialchars($row["location"]); ?></td>
                                <td><?php echo !empty($row["created_at"]) ? date("M j, Y", strtotime($row["created_at"])) : "N/A"; ?></td>
                                <td>
                                    <span class="status-badge <?php echo formatStatusClass($row["status"]); ?>">
                                        <?php echo htmlspecialchars($row["status"]); ?>
                                    </span>
                                </td>
                                <td>
                                    <?php
                                    if ($provider_type === 'technician') {
                                        echo number_format((float)$row["price"], 2) . " EGP";
                                    } else {
echo number_format((float)$row["salary_amount"], 2) . " EGP";           
                         }
                                    ?>
                                </td>
                                <td>
                                    <a href="myjobs.php" class="small-btn">Open</a>
                                </td>
                            </tr>
                        <?php endwhile; ?>
                    </table>
                </div>
            <?php else: ?>
                <div class="empty-box">No recent jobs found.</div>
            <?php endif; ?>
        </div>

        <div class="panel">
            <div class="panel-header">
                <h2>Recent Activity</h2>
                <span class="sub">Latest actions on your jobs</span>
            </div>

            <div class="activity-list">
                <?php if ($recentActivity && pg_num_rows($recentActivity) > 0): ?>
                    <?php while ($activity = pg_fetch_assoc($recentActivity)): ?>
                        <div class="activity-item">
                            <div class="activity-icon">
                                <?php if ($activity["status"] === "completed"): ?>
                                    <i class="bi bi-check2-circle"></i>
                                <?php elseif (in_array($activity["status"], ["active", "assigned", "processing"])): ?>
                                    <i class="bi bi-briefcase"></i>
                                <?php else: ?>
                                    <i class="bi bi-clock-history"></i>
                                <?php endif; ?>
                            </div>

                            <div class="activity-content">
                                <h4><?php echo htmlspecialchars($activity["title"]); ?></h4>
                                <p>
                                    Status: <?php echo htmlspecialchars(ucfirst($activity["status"])); ?>
                                    •
                                    <?php
                                    if ($provider_type === "technician") {
                                        echo number_format((float)$activity["price"], 2) . " EGP";
                                    } else {
echo number_format((float)$activity["salary_amount"], 2) . " EGP";
                                    }
                                    ?>
                                </p>
                                <div class="activity-time">
                                    <?php echo htmlspecialchars(formatTimeAgo($activity["created_at"])); ?>
                                </div>
                            </div>
                        </div>
                    <?php endwhile; ?>
                <?php else: ?>
                    <div class="empty-box">No activity yet.</div>
                <?php endif; ?>
            </div>
        </div>

    </div>

</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>