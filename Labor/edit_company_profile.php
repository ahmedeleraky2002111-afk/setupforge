<?php
session_start();
require_once "../db.php";

if (!isset($_SESSION["user_id"]) || $_SESSION["user_type"] !== "company") {
    header("Location: ../auth/login.php");
    exit();
}

$company_user_id = (int)$_SESSION["user_id"];

// Fetch current data
$res = pg_query_params($conn, "
    SELECT c.*, u.name, u.email, u.phone, u.city, u.country, u.street
    FROM companies c
    JOIN users u ON u.id = c.user_id
    WHERE c.user_id = $1 LIMIT 1
", [$company_user_id]);

$co = $res ? pg_fetch_assoc($res) : null;
if (!$co) die("Company not found.");

$success = false;
$error   = null;

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $company_name     = trim($_POST['company_name']);
    $description      = trim($_POST['description']);
    $website          = trim($_POST['website'] ?? '');
    $location         = trim($_POST['location']);
    $established_year = (int)$_POST['established_year'];
    $company_size     = $_POST['company_size'];
    $base_price       = (float)$_POST['base_price'];
    $starting_from    = (int)$_POST['starting_from'];
    $services         = $_POST['services'] ?? [];
    $phone            = trim($_POST['phone']);
    $city             = trim($_POST['city']);

    // Handle image upload
    $imagePath = $co['image']; // keep existing
    if (!empty($_FILES['image']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed)) {
            $filename  = 'company_' . $company_user_id . '_' . time() . '.' . $ext;
            $uploadDir = __DIR__ . '/../assets/images/company/';
            if (move_uploaded_file($_FILES['image']['tmp_name'], $uploadDir . $filename)) {
                $imagePath = 'assets/images/company/' . $filename;
            }
        }
    }

    // Update companies
    $ok1 = pg_query_params($conn, "
        UPDATE companies
        SET company_name = $1, description = $2, website = $3, location = $4,
            established_year = $5, company_size = $6, base_price = $7,
            starting_from = $8, services = $9, image = $10
        WHERE user_id = $11
    ", [
        $company_name, $description, $website, $location,
        $established_year, $company_size, $base_price, $starting_from,
        '{' . implode(',', $services) . '}',
        $imagePath, $company_user_id
    ]);

    // Update users
    $ok2 = pg_query_params($conn, "
        UPDATE users SET phone = $1, city = $2 WHERE id = $3
    ", [$phone, $city, $company_user_id]);

    if ($ok1 && $ok2) {
        $success = true;
        // Refresh data
        $res = pg_query_params($conn, "
            SELECT c.*, u.name, u.email, u.phone, u.city, u.country, u.street
            FROM companies c JOIN users u ON u.id = c.user_id
            WHERE c.user_id = $1 LIMIT 1
        ", [$company_user_id]);
        $co = pg_fetch_assoc($res);
    } else {
        $error = pg_last_error($conn);
    }
}

$currentServices = array_map('trim', explode(',', trim($co['services'] ?? '', '{}')));
?>
<!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Edit Profile - SetupForge</title>
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.css">
<link rel="stylesheet" href="labor.css">
<style>
.profile-wrap { max-width: 620px; margin: 40px auto; padding: 0 16px 60px; }
.profile-card { background: #fff; border: 1px solid rgba(0,0,0,.08); border-radius: 20px; padding: 28px; box-shadow: 0 8px 24px rgba(0,0,0,.06); }
.section-title { font-size: .72rem; font-weight: 800; color: #6b7280; text-transform: uppercase; letter-spacing: .08em; margin: 20px 0 10px; padding-top: 16px; border-top: 1px solid rgba(0,0,0,.07); }
.section-title:first-child { border-top: none; margin-top: 0; padding-top: 0; }
label { font-size: .85rem; font-weight: 700; color: #374151; display: block; margin-bottom: 5px; }
input, textarea, select { width: 100%; padding: 10px 12px; border: 1px solid rgba(0,0,0,.14); border-radius: 10px; font-size: .95rem; outline: none; transition: border-color .15s, box-shadow .15s; box-sizing: border-box; }
input:focus, textarea:focus, select:focus { border-color: rgba(0,76,172,.5); box-shadow: 0 0 0 3px rgba(0,76,172,.10); }
.services-grid { display: flex; flex-wrap: wrap; gap: 10px; }
.services-grid label { display: flex; align-items: center; gap: 6px; font-weight: 600; margin: 0; cursor: pointer; padding: 8px 14px; border: 1px solid rgba(0,0,0,.12); border-radius: 999px; background: #f8fafc; transition: all .15s; }
.services-grid input[type=checkbox] { width: auto; accent-color: #004cac; }
.services-grid label:has(input:checked) { background: rgba(0,76,172,.08); border-color: rgba(0,76,172,.35); color: #004cac; }
.img-wrap { display: flex; align-items: center; gap: 16px; margin-bottom: 10px; }
.img-current { width: 80px; height: 80px; border-radius: 14px; object-fit: cover; border: 1px solid rgba(0,0,0,.10); background: #f1f5f9; }
.img-current-fallback { width: 80px; height: 80px; border-radius: 14px; background: rgba(0,76,172,.08); display: flex; align-items: center; justify-content: center; font-size: 1.6rem; color: #004cac; }
.save-btn { width: 100%; padding: 13px; border: none; border-radius: 12px; background: #004cac; color: #fff; font-weight: 800; font-size: 1rem; cursor: pointer; margin-top: 24px; transition: background .15s; }
.save-btn:hover { background: #003b86; }
.back-link { display: inline-flex; align-items: center; gap: 6px; color: #004cac; font-weight: 700; text-decoration: none; margin-bottom: 20px; font-size: .9rem; }
.back-link:hover { color: #003b86; }
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
                    <?php if (!empty($co['image'])): ?>
                        <img src="../<?= htmlspecialchars($co['image']) ?>" style="width:46px;height:46px;border-radius:50%;object-fit:cover;">
                    <?php else: ?>
                        <i class="bi bi-person-fill"></i>
                    <?php endif; ?>
                </button>
                <ul class="dropdown-menu dropdown-menu-end sf-dropdown">
                    <li class="px-3 py-2 fw-semibold"><?= htmlspecialchars($co['name']) ?></li>
                    <li><hr class="dropdown-divider"></li>
                    <li><a class="dropdown-item" href="edit_company_profile.php">Edit Profile</a></li>
                    <li><a class="dropdown-item" href="../auth/logout.php">Logout</a></li>
                </ul>
            </div>
        </div>
    </div>
</nav>

<div class="profile-wrap">
    <a href="company_dashboard.php" class="back-link">
        <i class="bi bi-arrow-left"></i> Back to Dashboard
    </a>

    <?php if ($success): ?>
    <div style="background:#d1fae5;color:#065f46;border:1px solid rgba(34,197,94,.2);border-radius:12px;padding:12px 16px;margin-bottom:20px;font-weight:700;">
        <i class="bi bi-check2-circle me-2"></i> Profile updated successfully.
    </div>
    <?php endif; ?>

    <?php if ($error): ?>
    <div style="background:#fee2e2;color:#991b1b;border:1px solid rgba(239,68,68,.2);border-radius:12px;padding:12px 16px;margin-bottom:20px;font-weight:700;">
        Error: <?= htmlspecialchars($error) ?>
    </div>
    <?php endif; ?>

    <div class="profile-card">
        <form method="POST" enctype="multipart/form-data">

            <div class="section-title">Company Image</div>
            <div class="img-wrap">
                <?php if (!empty($co['image'])): ?>
                    <img src="../<?= htmlspecialchars($co['image']) ?>" class="img-current" id="img-preview">
                <?php else: ?>
                    <div class="img-current-fallback" id="img-fallback"><i class="bi bi-building"></i></div>
                    <img src="" class="img-current" id="img-preview" style="display:none;">
                <?php endif; ?>
                <div>
                    <label style="margin-bottom:6px;">Upload new image</label>
                    <input type="file" name="image" accept="image/*" onchange="previewImg(this)">
                    <div style="font-size:.75rem;color:#9ca3af;margin-top:4px;">JPG, PNG or WebP. Recommended: 400×400px</div>
                </div>
            </div>

            <div class="section-title">Company Info</div>
            <div style="margin-bottom:14px;">
                <label>Company Name</label>
                <input name="company_name" value="<?= htmlspecialchars($co['company_name']) ?>" required>
            </div>
            <div style="margin-bottom:14px;">
                <label>Description</label>
                <textarea name="description" rows="3"><?= htmlspecialchars($co['description'] ?? '') ?></textarea>
            </div>
            <div style="margin-bottom:14px;">
                <label>Website</label>
                <input name="website" type="url" value="<?= htmlspecialchars($co['website'] ?? '') ?>" placeholder="https://yourcompany.com">
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                <div>
                    <label>Location</label>
                    <input name="location" value="<?= htmlspecialchars($co['location'] ?? '') ?>" required>
                </div>
                <div>
                    <label>City</label>
                    <input name="city" value="<?= htmlspecialchars($co['city'] ?? '') ?>">
                </div>
            </div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                <div>
                    <label>Established Year</label>
                    <input name="established_year" type="number" value="<?= htmlspecialchars($co['established_year'] ?? '') ?>" required>
                </div>
                <div>
                    <label>Company Size</label>
                    <select name="company_size" required>
                        <option value="small"   <?= ($co['company_size'] ?? '') === 'small'   ? 'selected' : '' ?>>Small</option>
                        <option value="medium"  <?= ($co['company_size'] ?? '') === 'medium'  ? 'selected' : '' ?>>Medium</option>
                        <option value="large"   <?= ($co['company_size'] ?? '') === 'large'   ? 'selected' : '' ?>>Large</option>
                    </select>
                </div>
            </div>
            <div style="margin-bottom:14px;">
                <label>Phone</label>
                <input name="phone" value="<?= htmlspecialchars($co['phone'] ?? '') ?>">
            </div>

            <div class="section-title">Pricing</div>
            <div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;margin-bottom:14px;">
                <div>
                    <label>Base Price (EGP)</label>
                    <input name="base_price" type="number" step="0.01" value="<?= htmlspecialchars($co['base_price'] ?? '') ?>" required>
                </div>
                <div>
                    <label>Starting From (EGP)</label>
                    <input name="starting_from" type="number" value="<?= htmlspecialchars($co['starting_from'] ?? '') ?>" required>
                </div>
            </div>

            <div class="section-title">Services</div>
            <div class="services-grid">
                <?php foreach (['pos' => 'POS', 'electrical' => 'Electrical', 'network' => 'Network', 'ac' => 'AC', 'kitchen' => 'Kitchen'] as $val => $label): ?>
                <label>
                    <input type="checkbox" name="services[]" value="<?= $val ?>"
                        <?= in_array($val, $currentServices) ? 'checked' : '' ?>>
                    <?= $label ?>
                </label>
                <?php endforeach; ?>
            </div>

            <button type="submit" class="save-btn">
                <i class="bi bi-check2-circle me-2"></i> Save Changes
            </button>
        </form>
    </div>
</div>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function previewImg(input) {
    if (input.files && input.files[0]) {
        const reader = new FileReader();
        reader.onload = e => {
            const preview = document.getElementById('img-preview');
            const fallback = document.getElementById('img-fallback');
            preview.src = e.target.result;
            preview.style.display = 'block';
            if (fallback) fallback.style.display = 'none';
        };
        reader.readAsDataURL(input.files[0]);
    }
}
</script>
</body>
</html>