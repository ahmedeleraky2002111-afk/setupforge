<?php
// auth/signup.php
session_start();
require_once "../db.php";
/** @var \PgSql\Connection $conn */

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$next = $_GET["next"] ?? ($_POST["next"] ?? "../packages.php");
$error = "";

/*
  IMPORTANT:
  These MUST match your PostgreSQL enum labels exactly.
  If your enum values are different, change only these 3.
*/
$DEFAULT_USER_TYPE        = "customer";
$DEFAULT_ACCOUNT_STATUS   = "active";
$DEFAULT_BUSINESS_STATUS  = "active";

/*
  If user came from setup wizard, signup becomes business signup.
  Otherwise normal signup stays customer.
*/
$userType = $DEFAULT_USER_TYPE;
if (!empty($_SESSION["signup_intent"]) && $_SESSION["signup_intent"] === "business") {
  $userType = "business";
}

/* Wizard data (used only for business signup) */
$w = $_SESSION["wizard"] ?? [];
$business_type = $w["business_type"] ?? null;
$place_size    = $w["indoor_seats"] ?? null;
$budget_egp    = (int)($w["budget"] ?? 0);

/* Only force wizard guard when signup intent is business */
if (!empty($_SESSION["signup_intent"]) && $_SESSION["signup_intent"] === "business") {
  $services = $_SESSION['wizard']['services'] ?? [];
  $hasEquipment = in_array('equipment', $services);
  if ($hasEquipment) {
    if (!$business_type || ($place_size === null || (int)$place_size < 1) || $budget_egp <= 0) {
      header("Location: ../setup.php?step=1");
      exit;
    }
  }
}

if ($_SERVER["REQUEST_METHOD"] === "POST") {
  $name     = trim($_POST["name"] ?? "");
  $email    = trim($_POST["email"] ?? "");
  $phone    = trim($_POST["phone"] ?? "");
  $country  = trim($_POST["country"] ?? "");
  $city     = trim($_POST["city"] ?? "");
  $street   = trim($_POST["street"] ?? "");
  $pass     = $_POST["password"] ?? "";
  $pass2    = $_POST["password2"] ?? "";

  if ($name === "" || $email === "" || $pass === "" || $pass2 === "") {
    $error = "Please fill in all required fields.";
  } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    $error = "Please enter a valid email address.";
  } elseif (strlen($pass) < 8) {
    $error = "Password must be at least 8 characters.";
  } elseif ($pass !== $pass2) {
    $error = "Passwords do not match.";
  } else {
    $check = pg_query_params($conn, "SELECT id FROM users WHERE email = $1 LIMIT 1", [$email]);

    if ($check && pg_fetch_assoc($check)) {
      $error = "This email is already registered. Please sign in instead.";
    } else {
      $hash = password_hash($pass, PASSWORD_DEFAULT);

      pg_query($conn, "BEGIN");

      $sqlUser = "
        INSERT INTO users (name, email, password_hash, user_type, phone, country, city, street, status)
        VALUES ($1,$2,$3,$4,$5,$6,$7,$8,$9)
        RETURNING id
      ";
      $resUser = pg_query_params($conn, $sqlUser, [
        $name,
        $email,
        $hash,
        $userType,
        ($phone !== "" ? $phone : null),
        ($country !== "" ? $country : null),
        ($city !== "" ? $city : null),
        ($street !== "" ? $street : null),
        $DEFAULT_ACCOUNT_STATUS
      ]);

      if (!$resUser) {
        pg_query($conn, "ROLLBACK");
        $error = "Signup failed (users insert). Check enum values for user_type/status.";
      } else {
        $row = pg_fetch_assoc($resUser);
        $user_id = (int)$row["id"];

        if ($userType === "business") {
          // Business row already created by save_wizard_to_db() during wizard steps
          // Just update the user_id link in case it was created as guest
          $resBiz = pg_query_params($conn, "
            INSERT INTO businesses (user_id, status)
            VALUES ($1, $2)
            ON CONFLICT (user_id) DO UPDATE SET status = $2
          ", [$user_id, $DEFAULT_BUSINESS_STATUS]);

          if (!$resBiz) {
            pg_query($conn, "ROLLBACK");
            $error = "Signup failed (business insert).";
          } else {
            pg_query($conn, "COMMIT");

            $_SESSION["user_id"] = $user_id;
            $_SESSION["name"] = $name;
            $_SESSION["user_type"] = $userType;

            unset($_SESSION["signup_intent"]);
            // For non-equipment users, create zero order + service records
$signupServices = $_SESSION['wizard']['services'] ?? [];
$hasEquipmentSignup = in_array('equipment', $signupServices);

if (!$hasEquipmentSignup && !empty($signupServices)) {
    require_once "../create_service_records.php";

    // Create zero order
    $zeroOrderRes = pg_query_params($conn, "
        INSERT INTO orders (business_user_id, order_type, order_total, payment_status, status, order_date)
        VALUES ($1, 'setup', 0, 'paid', 'confirmed', NOW())
        RETURNING id
    ", [$user_id]);

    $zeroOrderId = null;
    if ($zeroOrderRes && pg_num_rows($zeroOrderRes) > 0) {
        $zeroOrderId = (int)pg_fetch_assoc($zeroOrderRes)["id"];
    }

    if ($zeroOrderId) {
    create_service_records($conn, $user_id, $zeroOrderId, $_SESSION['wizard'] ?? []);
}

// Save services to staffing_data so service_jobs.php shows correct tabs
$staffingJson = json_encode(['services' => $signupServices]);
pg_query_params($conn,
    "UPDATE businesses SET staffing_data = $2 WHERE user_id = $1",
    [$user_id, $staffingJson]);
}
            header("Location: " . $next);
            exit;
          }
        } else {
          pg_query($conn, "COMMIT");

          $_SESSION["user_id"] = $user_id;
          $_SESSION["name"] = $name;
          $_SESSION["user_type"] = $userType;

          unset($_SESSION["signup_intent"]);

          header("Location: " . $next);
          exit;
        }
      }
    }
  }
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Sign Up - SetupForge</title>

  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="../assets/style.css?v=6" rel="stylesheet">

  <style>
    :root{
      --sf-primary:#004cac;
      --sf-primary-dark:#00367a;
      --sf-accent:#009994;
      --sf-bg:#f4f8fc;
      --sf-text:#0f172a;
      --sf-muted:#667085;
      --sf-line:rgba(15,23,42,.08);
      --sf-soft:rgba(0,76,172,.06);
      --sf-shadow:0 20px 60px rgba(0,45,98,.12);
    }

    *{
      font-family:'Inter',sans-serif;
    }

    body{
      min-height:100vh;
      background:
        radial-gradient(circle at top left, rgba(0,153,148,.10), transparent 24%),
        radial-gradient(circle at bottom right, rgba(0,76,172,.10), transparent 28%),
        linear-gradient(135deg, #f7fbff 0%, #eef4fb 100%);
      color:var(--sf-text);
    }

    .sf-auth{
      padding: 44px 16px 70px;
      min-height: calc(100vh - 88px);
      display: grid;
      place-items: center;
    }

    .sf-auth-card{
      width: 100%;
      max-width: 555px;
      background: rgba(255,255,255,.92);
      border: 1px solid rgba(255,255,255,.7);
      border-radius: 28px;
      box-shadow: var(--sf-shadow);
      overflow: hidden;
      backdrop-filter: blur(10px);
    }

    .sf-auth-head{
      padding: 28px 28px 12px;
      text-align:center;
      background:
        linear-gradient(180deg, rgba(0,76,172,.04), rgba(255,255,255,0));
      border-bottom: 1px solid rgba(15,23,42,.04);
    }

    .sf-auth-logo-wrap{
      display:flex;
      justify-content:center;
      align-items:center;
      margin-bottom:12px;
    }

    .sf-auth-logo{
      width:220px;
      max-width:100%;
      height:auto;
      margin: -35px auto;
      display:block;
      object-fit:contain;
      filter:drop-shadow(0 10px 22px rgba(0,76,172,.10));
    }

    .sf-auth-sub{
      margin: 0 auto;
      color: var(--sf-muted);
      font-weight: 500;
      font-size: .98rem;
      line-height: 1.6;
      max-width: 500px;
    }

    .sf-auth-body{
      padding: 24px 28px 30px;
    }

    .sf-auth-body label{
      font-weight: 800;
      font-size: .92rem;
      color:#1f2937;
      margin-bottom:8px;
    }

    .sf-auth-body .form-control{
      border-radius: 16px;
      padding: 13px 15px;
      border: 1px solid rgba(15,23,42,.10);
      background:#fbfcfe;
      font-size:.97rem;
      color:var(--sf-text);
      transition:.2s ease;
    }

    .sf-auth-body .form-control::placeholder{
      color:#98a2b3;
    }

    .sf-auth-body .form-control:focus{
      background:#fff;
      border-color: rgba(0,153,148,.55);
      box-shadow: 0 0 0 4px rgba(0,153,148,.12);
    }

    .sf-auth-btn{
      border-radius: 0px;
      padding: 14px 16px;
      font-weight: 800;
      font-size:1rem;
      background: linear-gradient(90deg, var(--sf-primary), #0a63d1);
      border: none;
      color:#fff !important;
      box-shadow: 0 14px 28px rgba(0,76,172,.18);
      transition: .2s ease;
    }

    .sf-auth-btn:hover{
  color:#004cac !important;
  background: #fff;
  border: 2px solid #004cac;
  transform: translateY(-1px);
  box-shadow: 0 18px 34px rgba(0,76,172,.18);
}

    .sf-auth-note{
      color: var(--sf-muted);
      font-size: .94rem;
    }

    .sf-auth-note a{
      color:var(--sf-primary);
      text-decoration:none;
    }

    .sf-auth-note a:hover{
      color:var(--sf-accent);
    }

    .sf-auth-mini{
      margin-bottom: 20px;
      padding: 16px 16px 14px;
      border-radius: 20px;
      border: 1px solid rgba(0,76,172,.10);
      background:
        linear-gradient(180deg, rgba(0,76,172,.05), rgba(0,153,148,.03));
    }

    .sf-auth-mini-row{
      display:flex;
      flex-wrap:wrap;
      gap:10px;
      margin-bottom:10px;
    }

    .sf-pill{
      display:inline-flex;
      align-items:center;
      justify-content:center;
      padding:8px 12px;
      border-radius:999px;
      font-size:.84rem;
      font-weight:800;
      line-height:1;
      border:1px solid transparent;
    }

    .sf-pill-primary{
      background:rgba(0,76,172,.10);
      color:var(--sf-primary);
      border-color:rgba(0,76,172,.12);
    }

    .sf-pill-accent{
      background:rgba(0,153,148,.10);
      color:var(--sf-accent);
      border-color:rgba(0,153,148,.14);
    }

    .sf-pill-soft{
      background:rgba(15,23,42,.05);
      color:#334155;
      border-color:rgba(15,23,42,.08);
    }

    .alert{
      border-radius:16px;
      border:none;
      font-weight:600;
    }

    @media (max-width: 767.98px){
      .sf-auth{
        padding: 26px 12px 40px;
      }

      .sf-auth-head{
        padding: 18px 28px 6px;
      }

      .sf-auth-body{
        padding: 20px 20px 24px;
      }

      .sf-auth-card{
        border-radius:22px;
      }

      .sf-auth-logo{
        width:126px;
      }
    }
  </style>
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
      <a href="login.php?next=<?= h($next) ?>" class="btn btn-light btn-sm px-3 fw-semibold">Sign In</a>
      <a href="signup.php?next=<?= h($next) ?>" class="btn btn-outline-light btn-sm px-3 fw-semibold">Sign Up</a>
    </div>
  </div>
</nav>

<main class="sf-auth">
  <div class="sf-auth-card">

    <div class="sf-auth-head">
      <div class="sf-auth-logo-wrap">
        <img src="../assets/images/Logo.png" alt="SetupForge" class="sf-auth-logo">
      </div>
      <p class="sf-auth-sub">Sign up to save your setup and view your generated packages.</p>
    </div>

    <div class="sf-auth-body">
      <?php if ($error): ?>
        <div class="alert alert-danger mb-3"><?= h($error) ?></div>
      <?php endif; ?>

      

      <form method="post" novalidate>
        <input type="hidden" name="next" value="<?= h($next) ?>">

        <div class="row g-3">
          <div class="col-12">
            <label class="form-label">Full name</label>
            <input name="name" class="form-control" placeholder="Your name" required>
          </div>

          <div class="col-12">
            <label class="form-label">Email</label>
            <input name="email" type="email" class="form-control" placeholder="you@example.com" required>
          </div>

          <div class="col-12">
            <label class="form-label">Phone (optional)</label>
            <input name="phone" class="form-control" placeholder="+20 ...">
          </div>

          <div class="col-md-4">
            <label class="form-label">Country (optional)</label>
            <input name="country" class="form-control" placeholder="Egypt">
          </div>
          <div class="col-md-4">
            <label class="form-label">City (optional)</label>
            <input name="city" class="form-control" placeholder="Cairo">
          </div>
          <div class="col-md-4">
            <label class="form-label">Street (optional)</label>
            <input name="street" class="form-control" placeholder="...">
          </div>

          <div class="col-12">
            <label class="form-label">Password</label>
            <input name="password" type="password" class="form-control" placeholder="Min 8 characters" required>
          </div>

          <div class="col-12">
            <label class="form-label">Confirm password</label>
            <input name="password2" type="password" class="form-control" required>
          </div>
        </div>

<div class="text-center mt-4">
<button class="btn sf-auth-btn">Create account→</button>
</div>

        <div class="text-center mt-3 sf-auth-note">
          Already have an account?
          <a href="login.php?next=<?= h($next) ?>" class="fw-semibold">Sign in</a>
        </div>
      </form>
    </div>
  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
