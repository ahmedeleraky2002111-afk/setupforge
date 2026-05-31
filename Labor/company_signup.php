<?php
include "../db.php";
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    $name             = $_POST['name'];
    $email            = $_POST['email'];
    $password         = password_hash($_POST['password'], PASSWORD_DEFAULT);
    $phone            = $_POST['phone'];
    $country          = $_POST['country'];
    $city             = $_POST['city'];
    $street           = $_POST['street'];

    $company_name     = $_POST['company_name'];
    $description      = $_POST['description'];
    $services         = $_POST['services'] ?? [];
    $specialties      = $_POST['specialties'] ?? [];
    $base_price       = $_POST['base_price'];
    $starting_from    = $_POST['starting_from'];
    $company_size     = $_POST['company_size'];
    $established_year = $_POST['established_year'];
    $location         = $_POST['location'];
    $website          = $_POST['website'] ?? '';

    // Handle image upload
    $imagePath = null;
    if (!empty($_FILES['image']['name'])) {
        $ext       = strtolower(pathinfo($_FILES['image']['name'], PATHINFO_EXTENSION));
        $allowed   = ['jpg', 'jpeg', 'png', 'webp'];
        if (in_array($ext, $allowed)) {
            $filename  = 'company_' . time() . '.' . $ext;
            $uploadDir = __DIR__ . '/../assets/images/company/';
            $destPath  = $uploadDir . $filename;
            if (move_uploaded_file($_FILES['image']['tmp_name'], $destPath)) {
                $imagePath = 'assets/images/company/' . $filename;
            }
        }
    }

    // 1) Insert into users
    $resultUser = pg_query_params($conn, "
        INSERT INTO users (name, email, password_hash, user_type, phone, country, city, street, status)
        VALUES ($1, $2, $3, 'company', $4, $5, $6, $7, 'active')
        RETURNING id
    ", [$name, $email, $password, $phone, $country, $city, $street]);

    if (!$resultUser) die("Error inserting user.");

    $user_id = pg_fetch_assoc($resultUser)['id'];

    // 2) Insert into companies
    $resultCompany = pg_query_params($conn, "
        INSERT INTO companies (user_id, company_name, description, services, base_price, starting_from, company_size, established_year, location, website, image, specialties, availability_status, status)
        VALUES ($1, $2, $3, $4, $5, $6, $7, $8, $9, $10, $11, $12, 'available', 'active')
    ", [
        $user_id,
        $company_name,
        $description,
        '{' . implode(',', $services) . '}',
        $base_price,
        $starting_from,
        $company_size,
        $established_year,
        $location,
        $website,
        $imagePath,
        !empty($specialties) ? '{' . implode(',', $specialties) . '}' : null
    ]);

    if ($resultCompany) {
        header("Location: company_dashboard.php");
        exit();
    } else {
        echo "Error: " . pg_last_error($conn);
    }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Join as a Company — SetupForge</title>
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

        <a href="../home.php" class="sf-lsup-brand d-flex align-items-center gap-2">
          <div class="sf-logo">
            <img src="../assets/images/Logo.png" alt="SetupForge">
          </div>
          <span class="fw-bold text-white">SetupForge</span>
        </a>

        <div class="sf-lsup-left-copy">
          <p class="sf-lsup-left-kicker">For Companies</p>
          <h1 class="sf-lsup-left-title">Get hired by businesses that need your expertise.</h1>
          <p class="sf-lsup-left-sub">Connect with restaurants, cafes, and retail businesses looking for installation and finishing companies in your city.</p>
        </div>

        <ul class="sf-lsup-perks">
          <li><i class="bi bi-check-circle-fill"></i> Browse open installation requests</li>
          <li><i class="bi bi-check-circle-fill"></i> Submit quotes on your terms</li>
          <li><i class="bi bi-check-circle-fill"></i> Manage jobs from your dashboard</li>
          <li><i class="bi bi-check-circle-fill"></i> Build your rating and reputation</li>
        </ul>

        <div class="sf-lsup-left-footer">
          Already have an account? <a href="company_login.php">Sign in</a>
        </div>

      </div>
    </div>

    <!-- RIGHT PANEL -->
    <div class="sf-lsup-right">
      <div class="sf-lsup-form-wrap">

        <div class="sf-lsup-form-head">
          <h2>Create your account</h2>
          <p>Fill in your details to get started</p>
        </div>

        <form method="POST" enctype="multipart/form-data" class="sf-lsup-form" novalidate>

          <div class="sf-lsup-section-label">Account Information</div>

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

          <div class="sf-lsup-section-label">Company Information</div>

          <div class="sf-lsup-row-2">
            <div class="sf-lsup-field">
              <label>Company Name</label>
              <div class="sf-lsup-input-wrap">
                <i class="bi bi-building"></i>
                <input type="text" name="company_name" placeholder="e.g. Delta Installations" required>
              </div>
            </div>
            <div class="sf-lsup-field">
              <label>Location / Area</label>
              <div class="sf-lsup-input-wrap">
                <i class="bi bi-geo"></i>
                <input type="text" name="location" placeholder="e.g. Cairo, Egypt" required>
              </div>
            </div>
          </div>

          <div class="sf-lsup-row-2">
            <div class="sf-lsup-field">
              <label>Established Year</label>
              <div class="sf-lsup-input-wrap">
                <i class="bi bi-calendar3"></i>
                <input type="number" name="established_year" placeholder="e.g. 2010" required>
              </div>
            </div>
            <div class="sf-lsup-field">
              <label>Company Size</label>
              <div class="sf-lsup-input-wrap sf-lsup-select-wrap">
                <i class="bi bi-people"></i>
                <select name="company_size" required>
                  <option value="">Select size</option>
                  <option value="small">Small (1–10)</option>
                  <option value="medium">Medium (11–50)</option>
                  <option value="large">Large (50+)</option>
                </select>
                <i class="bi bi-chevron-down sf-lsup-chevron"></i>
              </div>
            </div>
          </div>

          <div class="sf-lsup-field">
            <label>Website <span class="sf-lsup-hint">Optional</span></label>
            <div class="sf-lsup-input-wrap">
              <i class="bi bi-link-45deg"></i>
              <input type="url" name="website" placeholder="https://yourcompany.com">
            </div>
          </div>

          <div class="sf-lsup-field">
            <label>Description</label>
            <div class="sf-lsup-input-wrap sf-lsup-textarea-wrap">
              <textarea name="description" rows="3" placeholder="Describe your company, experience, and what makes you stand out..."></textarea>
            </div>
          </div>

          <div class="sf-lsup-section-label">Pricing</div>

          <div class="sf-lsup-row-2">
            <div class="sf-lsup-field">
              <label>Base Price (EGP)</label>
              <div class="sf-lsup-input-wrap">
                <i class="bi bi-cash-stack"></i>
                <input type="number" name="base_price" step="0.01" placeholder="e.g. 5000" required>
              </div>
            </div>
            <div class="sf-lsup-field">
              <label>Starting From (EGP)</label>
              <div class="sf-lsup-input-wrap">
                <i class="bi bi-tag"></i>
                <input type="number" name="starting_from" placeholder="e.g. 4500" required>
              </div>
            </div>
          </div>

          <div class="sf-lsup-section-label">Services Offered</div>

          <div class="sf-csup-check-grid">
            <label class="sf-csup-check"><input type="checkbox" name="services[]" value="pos"><span>POS</span></label>
            <label class="sf-csup-check"><input type="checkbox" name="services[]" value="electrical"><span>Electrical</span></label>
            <label class="sf-csup-check"><input type="checkbox" name="services[]" value="network"><span>Network</span></label>
            <label class="sf-csup-check"><input type="checkbox" name="services[]" value="ac"><span>AC</span></label>
            <label class="sf-csup-check"><input type="checkbox" name="services[]" value="kitchen"><span>Kitchen</span></label>
            <label class="sf-csup-check"><input type="checkbox" name="services[]" value="finishing"><span>Finishing</span></label>
            <label class="sf-csup-check"><input type="checkbox" name="services[]" value="advertising"><span>Advertising</span></label>
          </div>

          <div class="sf-lsup-section-label">Specialties <span class="sf-lsup-hint">For finishing companies</span></div>

          <div class="sf-csup-check-grid">
            <label class="sf-csup-check"><input type="checkbox" name="specialties[]" value="painting"><span>Painting</span></label>
            <label class="sf-csup-check"><input type="checkbox" name="specialties[]" value="flooring"><span>Flooring</span></label>
            <label class="sf-csup-check"><input type="checkbox" name="specialties[]" value="gypsum"><span>Gypsum & Ceilings</span></label>
            <label class="sf-csup-check"><input type="checkbox" name="specialties[]" value="decor"><span>Decor</span></label>
            <label class="sf-csup-check"><input type="checkbox" name="specialties[]" value="facades"><span>Facades</span></label>
          </div>

          <div class="sf-lsup-section-label">Company Logo <span class="sf-lsup-hint">Optional</span></div>

          <div class="sf-lsup-field">
            <label class="sf-lsup-upload-label" id="sfUploadLabel">
              <i class="bi bi-building"></i>
              <span id="sfUploadText">Click to upload your logo</span>
              <input type="file" name="image" accept="image/*" id="sfPhotoInput" style="display:none">
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
        this.files[0] ? this.files[0].name : 'Click to upload your logo';
    });
    document.getElementById('sfUploadLabel').addEventListener('click', function () {
      document.getElementById('sfPhotoInput').click();
    });
  </script>

</body>
</html>