<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (isset($_SESSION["user_id"])) { header("Location: ../home.php"); exit; }
?>
<?php require_once "../db.php"; ?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Join SetupForge — Choose Your Role</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.0/font/bootstrap-icons.css">
  <link href="../assets/style.css?v=6" rel="stylesheet">
</head>
<body>

<nav class="navbar navbar-expand-lg navbar-dark sf-navbar">
  <div class="container d-flex align-items-center">
    <div class="d-flex align-items-center flex-grow-1">
      <a class="navbar-brand d-flex align-items-center gap-2" href="../home.php">
        <div class="sf-logo">
          <img src="../assets/images/Logo.png" alt="SetupForge">
        </div>
        <span class="fw-bold text-white">SetupForge</span>
      </a>
    </div>
    <div class="d-flex justify-content-end flex-grow-1 gap-2">
      <a href="login.php" class="btn btn-light btn-sm px-3 fw-semibold">Sign In</a>
      <a href="signup.php" class="btn btn-outline-light btn-sm px-3 fw-semibold">Sign Up</a>
    </div>
  </div>
</nav>

<main class="sf-role-main">
  <div class="sf-role-wrap">



    <h1 class="sf-role-heading">Who are you joining as?</h1>
    <p class="sf-role-sub">Choose your role so we can tailor your experience on SetupForge.</p>

    <div class="sf-role-grid">

      <a href="signup.php" class="sf-role-card">
        <div class="sf-role-icon"><i class="bi bi-shop"></i></div>
        <div class="sf-role-title">Business Owner</div>
        <div class="sf-role-desc">Setting up a restaurant or commercial space</div>
      </a>

      <a href="../Vendor/vendor_signup.php" class="sf-role-card">
        <div class="sf-role-icon"><i class="bi bi-box-seam"></i></div>
        <div class="sf-role-title">Vendor</div>
        <div class="sf-role-desc">Selling products like POS, kitchen, or furniture</div>
      </a>

      <a href="../Labor/labor_signup.php" class="sf-role-card">
        <div class="sf-role-icon"><i class="bi bi-people"></i></div>
        <div class="sf-role-title">Labor</div>
        <div class="sf-role-desc">Offering skilled work — chefs, waiters, staff</div>
      </a>

      <a href="../Labor/company_signup.php" class="sf-role-card">
        <div class="sf-role-icon"><i class="bi bi-briefcase"></i></div>
        <div class="sf-role-title">Company</div>
        <div class="sf-role-desc">Providing installation, finishing, or services</div>
      </a>

      <a href="signup.php" class="sf-role-card">
        <div class="sf-role-icon"><i class="bi bi-eye"></i></div>
        <div class="sf-role-title">Just Browsing</div>
        <div class="sf-role-desc">Exploring products and services as a customer</div>
      </a>

    </div>

    <p class="sf-role-signin">
      Already have an account? <a href="login.php">Sign in</a>
    </p>

  </div>
</main>
</body>
</html>