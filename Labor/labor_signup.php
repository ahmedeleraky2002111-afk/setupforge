<?php
session_start();
include "../db.php";

$error = '';

if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name          = trim($_POST['name']);
    $email         = trim($_POST['email']);
    $password      = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone         = trim($_POST['phone']);
    $country       = trim($_POST['country']);
    $city          = trim($_POST['city']);
    $street        = trim($_POST['street']);

    $national_id      = trim($_POST['national_id']);
    $dob              = $_POST['dob'];
    $skills           = trim($_POST['skills']);
    $experience_level = $_POST['experience_level'];
    $hourly_rate      = $_POST['hourly_rate'];
    $provider_type    = $_POST['provider_type'];
    $labor_role       = trim($_POST['labor_role']);
    $military_status  = $_POST['military_status'];

    // Handle profile picture upload
    $profile_picture = '';
    if (!empty($_FILES['profile_picture']['name'])) {
        $ext     = strtolower(pathinfo($_FILES['profile_picture']['name'], PATHINFO_EXTENSION));
        $allowed = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed)) {
            $filename  = 'labor_' . time() . '.' . $ext;
            $uploadDir = __DIR__ . '/../assets/images/labor/';
            if (!is_dir($uploadDir)) mkdir($uploadDir, 0755, true);
            if (move_uploaded_file($_FILES['profile_picture']['tmp_name'], $uploadDir . $filename)) {
                $profile_picture = 'assets/images/labor/' . $filename;
            }
        }
    }

    // 1) Insert into users
    $resultUser = pg_query_params($conn, "
        INSERT INTO users (name, email, password_hash, user_type, phone, country, city, street, status)
        VALUES ($1, $2, $3, 'labor', $4, $5, $6, $7, 'active')
        RETURNING id
    ", [$name, $email, $password, $phone, $country, $city, $street]);

    if (!$resultUser) {
        $error = "Error creating account. Email may already be in use.";
    } else {
        $userRow = pg_fetch_assoc($resultUser);
        $user_id = $userRow['id'];

        // 2) Insert into labors
        $resultLabor = pg_query_params($conn, "
            INSERT INTO labors (user_id, national_id, dob, skills, experience_level, hourly_rate, avg_rating, profile_picture, status, provider_type, labor_role, military_status, balance, availability_status)
            VALUES ($1, $2, $3, $4, $5, $6, 0, $7, 'active', $8, $9, $10, 0, 'available')
        ", [$user_id, $national_id, $dob, $skills, $experience_level, $hourly_rate, $profile_picture, $provider_type, $labor_role, $military_status]);

        if ($resultLabor) {
            header("Location: labor_login.php?registered=1");
            exit();
        } else {
            $error = "Error saving labor profile.";
        }
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Join as Labor — SetupForge</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="../assets/style.css?v=9" rel="stylesheet">
</head>
<body class="sf-labor-signup-page">

  <div class="sf-lsup-wrap">

    <!-- LEFT PANEL -->
    <div class="sf-lsup-left">
      <div class="sf-lsup-left-inner">

        <a href="../home.php" class="sf-lsup-brand">
          <img src="../assets/images/Logo.png" alt="SetupForge" class="sf-lsup-logo">
          <span>SetupForge</span>
        </a>

        <div class="sf-lsup-left-copy">
          <p class="sf-lsup-left-kicker">For Workers</p>
          <h1 class="sf-lsup-left-title">Find work with businesses that are ready to hire.</h1>
          <p class="sf-lsup-left-sub">Get matched with restaurants, cafes, and retail businesses looking for skilled staff in your city.</p>
        </div>

        <ul class="sf-lsup-perks">
          <li><i class="bi bi-check-circle-fill"></i> Get matched with nearby businesses</li>
          <li><i class="bi bi-check-circle-fill"></i> Set your own hourly rate</li>
          <li><i class="bi bi-check-circle-fill"></i> Accept or decline offers freely</li>
          <li><i class="bi bi-check-circle-fill"></i> Track earnings on your dashboard</li>
        </ul>

        <div class="sf-lsup-left-footer">
          Already have an account? <a href="labor_login.php">Sign in</a>
        </div>

      </div>
    </div>

    <!-- RIGHT PANEL — FORM -->
    <div class="sf-lsup-right">
      <div class="sf-lsup-form-wrap">

        <div class="sf-lsup-form-head">
          <h2>Create your account</h2>
          <p>Fill in your details to get started</p>
        </div>

        <?php if ($error): ?>
          <div class="sf-lsup-alert sf-lsup-alert-error">
            <i class="bi bi-exclamation-circle"></i> <?= htmlspecialchars($error) ?>
          </div>
        <?php endif; ?>

        <form method="POST" enctype="multipart/form-data" class="sf-lsup-form" novalidate>

          <div class="sf-lsup-section-label">Personal Information</div>

          <div class="sf-lsup-row-2">
            <div class="sf-lsup-field">
              <label>Full Name</label>
              <div class="sf-lsup-input-wrap">
                <i class="bi bi-person"></i>
                <input type="text" name="name" placeholder="Ahmed Hassan" required>
              </div>
            </div>
            <div class="sf-lsup-field">
              <label>Email Address</label>
              <div class="sf-lsup-input-wrap">
                <i class="bi bi-envelope"></i>
                <input type="email" name="email" placeholder="you@email.com" required>
              </div>
            </div>
          </div>

          <div class="sf-lsup-row-2">
            <div class="sf-lsup-field">
              <label>Password</label>
              <div class="sf-lsup-input-wrap">
                <i class="bi bi-lock"></i>
                <input type="password" name="password" placeholder="Min. 8 characters" required>
              </div>
            </div>
            <div class="sf-lsup-field">
              <label>Phone Number</label>
              <div class="sf-lsup-input-wrap">
                <i class="bi bi-phone"></i>
                <input type="text" name="phone" placeholder="+20 100 000 0000" required>
              </div>
            </div>
          </div>

          <div class="sf-lsup-row-2">
            <div class="sf-lsup-field">
              <label>National ID</label>
              <div class="sf-lsup-input-wrap">
                <i class="bi bi-credit-card-2-front"></i>
                <input type="text" name="national_id" placeholder="14-digit National ID" required>
              </div>
            </div>
            <div class="sf-lsup-field">
              <label>Date of Birth</label>
              <div class="sf-lsup-input-wrap">
                <i class="bi bi-calendar3"></i>
                <input type="date" name="dob" required>
              </div>
            </div>
          </div>

          <div class="sf-lsup-section-label">Location</div>

          <div class="sf-lsup-row-3">
            <div class="sf-lsup-field">
              <label>Country</label>
              <div class="sf-lsup-input-wrap">
                <i class="bi bi-globe"></i>
                <input type="text" name="country" placeholder="Egypt" required>
              </div>
            </div>
            <div class="sf-lsup-field">
              <label>City</label>
              <div class="sf-lsup-input-wrap">
                <i class="bi bi-geo-alt"></i>
                <input type="text" name="city" placeholder="Cairo" required>
              </div>
            </div>
            <div class="sf-lsup-field">
              <label>Street</label>
              <div class="sf-lsup-input-wrap">
                <i class="bi bi-signpost"></i>
                <input type="text" name="street" placeholder="Street address" required>
              </div>
            </div>
          </div>

          <div class="sf-lsup-section-label">Work Profile</div>

          <div class="sf-lsup-row-2">
            <div class="sf-lsup-field">
              <label>Job Role</label>
              <div class="sf-lsup-input-wrap sf-lsup-select-wrap">
                <i class="bi bi-briefcase"></i>
                <select name="provider_type" required>
                  <option value="">Select your role</option>
                  <option value="chef">Chef</option>
                  <option value="waiter">Waiter / Waitress</option>
                  <option value="barista">Barista</option>
                  <option value="cashier">Cashier</option>
                  <option value="cleaner">Cleaner</option>
                  <option value="other">Other</option>
                </select>
                <i class="bi bi-chevron-down sf-lsup-chevron"></i>
              </div>
            </div>
            <div class="sf-lsup-field">
              <label>Experience Level</label>
              <div class="sf-lsup-input-wrap sf-lsup-select-wrap">
                <i class="bi bi-bar-chart-steps"></i>
                <select name="experience_level" required>
                  <option value="">Select level</option>
                  <option value="junior">Junior (0–2 yrs)</option>
                  <option value="mid">Mid (2–5 yrs)</option>
                  <option value="senior">Senior (5+ yrs)</option>
                </select>
                <i class="bi bi-chevron-down sf-lsup-chevron"></i>
              </div>
            </div>
          </div>

          <div class="sf-lsup-row-2">
            <div class="sf-lsup-field">
              <label>Specific Role / Title</label>
              <div class="sf-lsup-input-wrap">
                <i class="bi bi-person-badge"></i>
                <input type="text" name="labor_role" placeholder="e.g. Head Chef, Sous Chef">
              </div>
            </div>
            <div class="sf-lsup-field">
              <label>Hourly Rate (EGP)</label>
              <div class="sf-lsup-input-wrap">
                <i class="bi bi-cash-stack"></i>
                <input type="number" name="hourly_rate" placeholder="e.g. 50" min="0" step="0.01">
              </div>
            </div>
          </div>

          <div class="sf-lsup-field">
            <label>Military Status <span class="sf-lsup-hint">(males only)</span></label>
            <div class="sf-lsup-military-group">
              <label class="sf-lsup-radio"><input type="radio" name="military_status" value="completed"> Completed</label>
              <label class="sf-lsup-radio"><input type="radio" name="military_status" value="exempt"> Exempt</label>
              <label class="sf-lsup-radio"><input type="radio" name="military_status" value="pending"> Pending</label>
              <label class="sf-lsup-radio"><input type="radio" name="military_status" value="n/a" checked> N/A</label>
            </div>
          </div>

          <div class="sf-lsup-field">
            <label>Skills <span class="sf-lsup-hint">Briefly describe what you can do</span></label>
            <div class="sf-lsup-input-wrap sf-lsup-textarea-wrap">
              <textarea name="skills" rows="3" placeholder="e.g. Grill cooking, knife skills, latte art, customer service..."></textarea>
            </div>
          </div>

          <div class="sf-lsup-section-label">Profile Photo <span class="sf-lsup-hint">Optional</span></div>

          <div class="sf-lsup-field">
            <label class="sf-lsup-upload-label" id="sfUploadLabel">
              <i class="bi bi-camera"></i>
              <span id="sfUploadText">Click to upload a photo</span>
              <input type="file" name="profile_picture" accept="image/*" id="sfPhotoInput" style="display:none">
            </label>
          </div>

          <button type="submit" class="sf-lsup-submit">
            <span>Create Account</span>
            <i class="bi bi-arrow-right"></i>
          </button>

          <p class="sf-lsup-terms">
            By signing up you agree to SetupForge's <a href="#">Terms of Service</a> and <a href="#">Privacy Policy</a>.
          </p>

        </form>
      </div>
    </div>

  </div>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script>
    document.getElementById('sfPhotoInput').addEventListener('change', function () {
      document.getElementById('sfUploadText').textContent =
        this.files[0] ? this.files[0].name : 'Click to upload a photo';
    });
    document.getElementById('sfUploadLabel').addEventListener('click', function () {
      document.getElementById('sfPhotoInput').click();
    });
  </script>

</body>
</html>