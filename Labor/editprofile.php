<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: ../auth/login.php");
    exit();
}
if (!isset($_SESSION["user_type"]) || $_SESSION["user_type"] !== "labor") {
    header("Location: ../home.php");
    exit();
}

$user_id = (int)$_SESSION["user_id"];
$success = "";
$error   = "";

// Fetch current data
$profileRes = pg_query_params($conn, "
    SELECT u.name, u.email, u.phone, u.country, u.city, u.street,
           l.skills, l.hourly_rate, l.availability_status,
           l.labor_role, l.profile_picture, l.provider_type
    FROM users u
    INNER JOIN labors l ON l.user_id = u.id
    WHERE u.id = $1 LIMIT 1
", [$user_id]);

$user = $profileRes ? pg_fetch_assoc($profileRes) : null;
if (!$user) { die("Profile not found."); }

if ($_SERVER["REQUEST_METHOD"] === "POST") {
    $name         = trim($_POST["name"] ?? "");
    $phone        = trim($_POST["phone"] ?? "");
    $country      = trim($_POST["country"] ?? "");
    $city         = trim($_POST["city"] ?? "");
    $street       = trim($_POST["street"] ?? "");
    $skills       = trim($_POST["skills"] ?? "");
    $hourly_rate  = (float)($_POST["hourly_rate"] ?? 0);
    $labor_role   = trim($_POST["labor_role"] ?? "");
    $availability = trim($_POST["availability_status"] ?? "available");

    $allowed = ["available", "busy", "unavailable"];
    if (!in_array($availability, $allowed)) $availability = "available";

    if ($name === "") {
        $error = "Name is required.";
    } else {
        pg_query($conn, "BEGIN");

        $r1 = pg_query_params($conn, "
            UPDATE users SET name=$1, phone=$2, country=$3, city=$4, street=$5
            WHERE id=$6
        ", [$name, $phone ?: null, $country ?: null, $city ?: null, $street ?: null, $user_id]);

        $r2 = pg_query_params($conn, "
            UPDATE labors SET skills=$1, hourly_rate=$2, labor_role=$3,
                              availability_status=$4, name=$5
            WHERE user_id=$6
        ", [$skills ?: null, $hourly_rate, $labor_role ?: null, $availability, $name, $user_id]);

        if ($r1 && $r2) {
            pg_query($conn, "COMMIT");
            $_SESSION["name"] = $name;
            $success = "Profile updated successfully.";
            // Refresh data
            $profileRes = pg_query_params($conn, "
                SELECT u.name, u.email, u.phone, u.country, u.city, u.street,
                       l.skills, l.hourly_rate, l.availability_status,
                       l.labor_role, l.profile_picture, l.provider_type
                FROM users u
                INNER JOIN labors l ON l.user_id = u.id
                WHERE u.id = $1 LIMIT 1
            ", [$user_id]);
            $user = pg_fetch_assoc($profileRes);
        } else {
            pg_query($conn, "ROLLBACK");
            $error = "Update failed. Please try again.";
        }
    }
}

function h($s) { return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit Profile — SetupForge</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="labor.css?v=104">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark sf-navbar">
    <div class="container">
        <a class="navbar-brand sf-brand-wrap" href="dashboard.php">
            <div class="sf-logo"><img src="../assets/images/Logo.png" alt="SetupForge"></div>
            <span class="fw-bold">SetupForge</span>
        </a>
        <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#laborNav">
            <span class="navbar-toggler-icon"></span>
        </button>
        <div class="collapse navbar-collapse justify-content-center" id="laborNav">
            <ul class="navbar-nav gap-3">
                <li class="nav-item"><a class="nav-link sf-navlink" href="dashboard.php">Dashboard</a></li>
                <li class="nav-item"><a class="nav-link sf-navlink" href="laborjobs.php">Available Jobs</a></li>
                <li class="nav-item"><a class="nav-link sf-navlink" href="myjobs.php">My Jobs</a></li>
                <li class="nav-item"><a class="nav-link sf-navlink active" href="profile.php">Profile</a></li>
            </ul>
        </div>
        <div class="sf-nav-actions">
            <div class="dropdown">
                <button class="btn sf-profile-btn" data-bs-toggle="dropdown">
                    <i class="bi bi-person-fill"></i>
                </button>
                <ul class="dropdown-menu dropdown-menu-end sf-dropdown">
                    <li class="px-3 py-2 fw-semibold"><?= h($user["name"]) ?></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="profile.php">Profile</a></li>
                    <li><a class="dropdown-item" href="../auth/logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div style="max-width:680px;margin:40px auto;padding:0 16px 60px;">

    <div style="margin-bottom:24px;">
        <h1 style="font-size:1.8rem;font-weight:900;color:#111827;margin-bottom:4px;">Edit Profile</h1>
        <p style="color:#6b7280;margin:0;">Update your personal and professional information.</p>
    </div>

    <?php if ($success): ?>
        <div style="background:#dcfce7;border:1px solid #bbf7d0;border-radius:12px;padding:14px 16px;margin-bottom:20px;font-weight:700;color:#166534;">
            <i class="bi bi-check-circle me-2"></i><?= h($success) ?>
        </div>
    <?php endif; ?>

    <?php if ($error): ?>
        <div style="background:#fee2e2;border:1px solid #fecaca;border-radius:12px;padding:14px 16px;margin-bottom:20px;font-weight:700;color:#991b1b;">
            <i class="bi bi-exclamation-circle me-2"></i><?= h($error) ?>
        </div>
    <?php endif; ?>

    <form method="POST" style="background:#fff;border-radius:20px;padding:28px;border:1px solid #e5e7eb;box-shadow:0 4px 16px rgba(0,0,0,.05);">

        <div style="font-size:.72rem;font-weight:800;color:#004cac;text-transform:uppercase;letter-spacing:.08em;margin-bottom:16px;">Personal Information</div>

        <div class="row g-3 mb-3">
            <div class="col-12">
                <label style="font-size:.85rem;font-weight:800;color:#1f2937;display:block;margin-bottom:6px;">Full Name *</label>
                <input name="name" type="text" class="form-control" value="<?= h($user["name"]) ?>" required
                    style="border-radius:12px;padding:12px 14px;border:1px solid rgba(0,0,0,.12);font-size:.95rem;">
            </div>
            <div class="col-md-6">
                <label style="font-size:.85rem;font-weight:800;color:#1f2937;display:block;margin-bottom:6px;">Phone</label>
                <input name="phone" type="text" class="form-control" value="<?= h($user["phone"]) ?>"
                    style="border-radius:12px;padding:12px 14px;border:1px solid rgba(0,0,0,.12);font-size:.95rem;">
            </div>
            <div class="col-md-6">
                <label style="font-size:.85rem;font-weight:800;color:#1f2937;display:block;margin-bottom:6px;">Country</label>
                <input name="country" type="text" class="form-control" value="<?= h($user["country"]) ?>"
                    style="border-radius:12px;padding:12px 14px;border:1px solid rgba(0,0,0,.12);font-size:.95rem;">
            </div>
            <div class="col-md-6">
                <label style="font-size:.85rem;font-weight:800;color:#1f2937;display:block;margin-bottom:6px;">City</label>
                <input name="city" type="text" class="form-control" value="<?= h($user["city"]) ?>"
                    style="border-radius:12px;padding:12px 14px;border:1px solid rgba(0,0,0,.12);font-size:.95rem;">
            </div>
            <div class="col-md-6">
                <label style="font-size:.85rem;font-weight:800;color:#1f2937;display:block;margin-bottom:6px;">Street</label>
                <input name="street" type="text" class="form-control" value="<?= h($user["street"]) ?>"
                    style="border-radius:12px;padding:12px 14px;border:1px solid rgba(0,0,0,.12);font-size:.95rem;">
            </div>
        </div>

        <div style="font-size:.72rem;font-weight:800;color:#004cac;text-transform:uppercase;letter-spacing:.08em;margin:20px 0 16px;">Work Profile</div>

        <div class="row g-3 mb-3">
            <div class="col-12">
                <label style="font-size:.85rem;font-weight:800;color:#1f2937;display:block;margin-bottom:6px;">Skills</label>
                <textarea name="skills" class="form-control" rows="3"
                    style="border-radius:12px;padding:12px 14px;border:1px solid rgba(0,0,0,.12);font-size:.95rem;resize:none;"
                    placeholder="e.g. Grill cooking, knife skills, latte art..."><?= h($user["skills"]) ?></textarea>
            </div>
            <div class="col-md-6">
                <label style="font-size:.85rem;font-weight:800;color:#1f2937;display:block;margin-bottom:6px;">Labor Role / Title</label>
                <input name="labor_role" type="text" class="form-control" value="<?= h($user["labor_role"]) ?>"
                    placeholder="e.g. Head Chef"
                    style="border-radius:12px;padding:12px 14px;border:1px solid rgba(0,0,0,.12);font-size:.95rem;">
            </div>
            <div class="col-md-6">
                <label style="font-size:.85rem;font-weight:800;color:#1f2937;display:block;margin-bottom:6px;">Hourly Rate (EGP)</label>
                <input name="hourly_rate" type="number" class="form-control" value="<?= h($user["hourly_rate"]) ?>"
                    min="0" step="0.01"
                    style="border-radius:12px;padding:12px 14px;border:1px solid rgba(0,0,0,.12);font-size:.95rem;">
            </div>
            <div class="col-12">
                <label style="font-size:.85rem;font-weight:800;color:#1f2937;display:block;margin-bottom:6px;">Availability</label>
                <select name="availability_status" class="form-select"
                    style="border-radius:12px;padding:12px 14px;border:1px solid rgba(0,0,0,.12);font-size:.95rem;">
                    <option value="available" <?= $user["availability_status"] === "available" ? "selected" : "" ?>>Available</option>
                    <option value="busy"      <?= $user["availability_status"] === "busy"      ? "selected" : "" ?>>Busy</option>
                    <option value="unavailable" <?= $user["availability_status"] === "unavailable" ? "selected" : "" ?>>Unavailable</option>
                </select>
            </div>
        </div>

        <button type="submit"
            style="width:100%;padding:14px;background:#004cac;color:#fff;border:none;border-radius:12px;font-weight:800;font-size:.95rem;cursor:pointer;margin-top:8px;">
            <i class="bi bi-check2 me-2"></i>Save Changes
        </button>

        <a href="profile.php"
            style="display:block;text-align:center;margin-top:12px;color:#6b7280;font-size:.9rem;font-weight:600;text-decoration:none;">
            Cancel
        </a>

    </form>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>