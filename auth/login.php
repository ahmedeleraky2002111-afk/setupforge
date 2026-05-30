<?php
session_start();
require_once "../db.php";

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

$error = "";

if ($_SERVER["REQUEST_METHOD"] === "POST") {

    $email = trim($_POST["email"] ?? "");
    $pass  = $_POST["password"] ?? "";

    if ($email === "" || $pass === "") {
        $error = "Please enter email and password.";
    } else {

        $res = pg_query_params(
            $conn,
            "SELECT id, name, user_type, password_hash, status
             FROM users
             WHERE email = $1
             LIMIT 1",
            [$email]
        );

        $user = $res ? pg_fetch_assoc($res) : null;

        if (!$user) {
            $error = "User not found.";
        } elseif (!password_verify($pass, $user["password_hash"])) {
            $error = "Wrong email or password.";
        } elseif ($user["status"] !== "active") {
            $error = "Your account is not active.";
        } else {

            $uid  = (int)$user["id"];
            $type = $user["user_type"];
            $valid = false;

            switch ($type) {
                case "admin":
                    $chk = pg_query_params($conn, "SELECT 1 FROM admins WHERE user_id = $1", [$uid]);
                    $valid = $chk && pg_num_rows($chk) > 0;
                    break;

                case "business":
                    $chk = pg_query_params($conn, "SELECT 1 FROM businesses WHERE user_id = $1", [$uid]);
                    $valid = $chk && pg_num_rows($chk) > 0;
                    break;

                case "customer":
                    $chk = pg_query_params($conn, "SELECT 1 FROM customers WHERE user_id = $1", [$uid]);
                    $valid = $chk && pg_num_rows($chk) > 0;
                    break;

                case "labor":
                    $chk = pg_query_params($conn, "SELECT 1 FROM labors WHERE user_id = $1", [$uid]);
                    $valid = $chk && pg_num_rows($chk) > 0;
                    break;
                    case "company":
    $chk = pg_query_params($conn, "SELECT 1 FROM companies WHERE user_id = $1", [$uid]);
    $valid = $chk && pg_num_rows($chk) > 0;
    break;

                case "vendor":
                    $chk = pg_query_params($conn, "SELECT 1 FROM vendors WHERE user_id = $1", [$uid]);
                    $valid = $chk && pg_num_rows($chk) > 0;
                    break;
            }

            if (!$valid) {
                $error = "Account configuration error.";
            } else {

                $_SESSION["user_id"] = $uid;
                $_SESSION["name"] = $user["name"];
                $_SESSION["user_type"] = $type;

                switch ($type) {
                    case "admin":
                        header("Location: ../admin_dashboard.php");
                        exit;

                    case "business":
                        // Check if they have a completed paid order → send to overview
                        $orderCheck = pg_query_params($conn,
                            "SELECT 1 FROM orders WHERE business_user_id = $1 AND payment_status = 'paid' AND order_type = 'setup' LIMIT 1",
                            [$uid]
                        );
                        if ($orderCheck && pg_num_rows($orderCheck) > 0) {
                            header("Location: ../business_overview.php");
                        } else {
                            header("Location: ../home.php");
                        }
                        exit;

                    case "customer":
                        header("Location: ../home.php");
                        exit;

                    case "labor":
                        header("Location: ../Labor/dashboard.php");
                        exit;
                    case "company":
                        header("Location: ../Labor/company_dashboard.php");
                        exit;

                    case "vendor":
                        header("Location: ../Vendor/vendor_dashboard.php");
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

<title>SetupForge Login</title>

<link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
<link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="../assets/style.css?v=6" rel="stylesheet">

<style>
:root{
  --sf-primary:#004cac;
  --sf-accent:#009994;
  --sf-primary-dark:#00367a;
  --sf-text:#111827;
  --sf-muted:#6b7280;
  --sf-shadow:0 24px 70px rgba(0, 44, 97, .14);
}

*{
  font-family:'Inter',sans-serif;
}

body{
  margin:0;
  min-height:100vh;
  color:var(--sf-text);
  background:
    radial-gradient(circle at top left, rgba(0,153,148,.10), transparent 25%),
    radial-gradient(circle at bottom right, rgba(0,76,172,.12), transparent 30%),
    linear-gradient(135deg, #f8fbff 0%, #eef4fb 100%);
}

.sf-navbar{
  background:linear-gradient(90deg, var(--sf-primary-dark), var(--sf-primary));
  box-shadow:0 10px 30px rgba(0,76,172,.18);
  padding:10px 0;
}



.sf-nav-btn{
  border-radius:999px;
  padding:.55rem 1.1rem;
  font-weight:700;
}

.sf-auth-wrap{
  min-height:calc(100vh - 76px);
  display:flex;
  align-items:center;
  justify-content:center;
  padding:38px 16px 56px;
}

.sf-auth-shell{
  width:100%;
  max-width:1120px;
  display:grid;
  grid-template-columns: 1.05fr .95fr;
  background:rgba(255,255,255,.75);
  border:1px solid rgba(255,255,255,.65);
  border-radius:30px;
  overflow:hidden;
  box-shadow:var(--sf-shadow);
  backdrop-filter:blur(8px);
}

.sf-auth-brand{
  position:relative;
  min-height:680px;
  padding:44px 40px;
  display:flex;
  flex-direction:column;
  justify-content:space-between;
  color:#fff;
  background:
    linear-gradient(rgba(5, 70, 174, 0.1), rgba(0, 0, 0, 0.1)),
    url('../assets/images/login.jpg') center center / cover no-repeat;
}

.sf-auth-brand::before{
  content:"";
  position:absolute;
  inset:0;
  background:
    linear-gradient(180deg, rgba(0, 0, 0, 0.1) 0%, rgba(0,28,63,.5) 55%, rgba(181, 181, 181, 0.28) 100%);
  pointer-events:none;
}

.sf-auth-brand::after{
  content:"";
  position:absolute;
  inset:0;
  background:
    radial-gradient(circle at 18% 20%, rgba(255,255,255,.14), transparent 22%),
    radial-gradient(circle at 82% 80%, rgba(255,255,255,.10), transparent 26%);
  pointer-events:none;
}

.sf-brand-content,
.sf-brand-foot{
  position:relative;
  z-index:2;
}

.sf-brand-badge{
  display:inline-flex;
  align-items:center;
  justify-content:center;
  background:rgba(255,255,255,.12);
  border:1px solid rgba(255,255,255,.20);
  border-radius:999px;
  padding:9px 16px;
  font-size:.88rem;
  font-weight:700;
  width:max-content;
  margin-bottom:26px;
  backdrop-filter:blur(10px);
  box-shadow:0 10px 25px rgba(0,0,0,.10);
}

.sf-brand-copy{
  max-width:500px;
  margin-top:10px;
}

.sf-brand-title{
  font-size:2.4rem;
  line-height:1.12;
  font-weight:900;
  margin-bottom:14px;
  color:#fff;
  text-shadow:0 8px 30px rgba(0,0,0,.20);
}

.sf-brand-text{
  font-size:1.05rem;
  line-height:1.8;
  color:rgba(255,255,255,.92);
  max-width:470px;
  margin:0;
  text-shadow:0 6px 18px rgba(0,0,0,.16);
}

.sf-brand-foot{
  color:rgba(255,255,255,.80);
  font-size:.92rem;
}

.sf-auth-card{
  padding:0px 0px;
  display:flex;
  align-items:flex-start;
  justify-content:center;
}

.sf-auth-inner{
  width:100%;
  max-width:360px;
  margin:auto;
}

.sf-auth-logo{
  width:320px;
  height:180px;
  object-fit:contain;
  object-position:center;
  display:block;
  margin:-18px auto -18px auto;
}

.sf-auth-title{
  font-size:2rem;
  font-weight:900;
  margin-bottom:8px;
  color:#0f172a;
}

.sf-auth-subtitle{
  color:var(--sf-muted);
  margin-bottom:28px;
  line-height:1.65;
}

.sf-alert{
  border:none;
  border-radius:16px;
  padding:14px 16px;
  font-weight:600;
}

.sf-form-group{
  margin-bottom:18px;
}

.sf-label{
  display:block;
  margin-bottom:8px;
  font-size:.92rem;
  font-weight:800;
  color:#1f2937;
}

.sf-input-wrap{
  position:relative;
}

.sf-input{
  height:56px;
  border-radius:16px;
  border:1px solid rgba(15,23,42,.10);
  background:#f9fbfd;
  padding:12px 16px;
  font-size:.98rem;
  transition:.22s ease;
}

.sf-input:focus{
  background:#fff;
  border-color:rgba(0,153,148,.55);
  box-shadow:0 0 0 4px rgba(0,153,148,.12);
}

.sf-row{
  display:flex;
  justify-content:space-between;
  align-items:center;
  gap:12px;
  margin:8px 0 22px;
}

.sf-check{
  font-size:.92rem;
  color:var(--sf-muted);
}

.sf-link{
  text-decoration:none;
  color:var(--sf-primary);
  font-weight:700;
}

.sf-link:hover{
  color:var(--sf-accent);
}

.sf-auth-btn{
  width:100%;
  border:none;
  border-radius:16px;
  height:56px;
  font-weight:800;
  font-size:1rem;
  letter-spacing:.2px;
  color:#fff;
  background:linear-gradient(90deg, var(--sf-primary), #0b63d1);
  box-shadow:0 12px 26px rgba(0,76,172,.22);
  transition:.22s ease;
}

.sf-auth-btn:hover{
  transform:translateY(-1px);
  background:linear-gradient(90deg, var(--sf-accent), #00b0aa);
  box-shadow:0 16px 30px rgba(0,153,148,.22);
  color:#fff;
}

.sf-auth-footer{
  text-align:center;
  margin-top:22px;
  color:var(--sf-muted);
  font-size:.95rem;
}

@media (max-width: 991.98px){
  .sf-auth-shell{
    grid-template-columns:1fr;
    max-width:650px;
  }

  .sf-auth-brand{
    min-height:420px;
    padding:34px 28px;
  }

  .sf-brand-title{
    font-size:1.9rem;
  }
}

@media (max-width: 575.98px){
  .sf-auth-wrap{
    padding:24px 12px 40px;
  }

  .sf-auth-card{
  padding:40px 42px;
}

  .sf-auth-brand{
    min-height:340px;
    padding:28px 22px;
  }

  .sf-brand-title{
    font-size:1.55rem;
  }

  .sf-brand-text{
    font-size:.96rem;
    line-height:1.7;
  }

  .sf-auth-title{
    font-size:1.65rem;
  }

  .sf-row{
    flex-direction:column;
    align-items:flex-start;
  }

  .sf-auth-logo{
    width:120px;
    height:auto;
  }
}
</style>
</head>

<body>



<main class="sf-auth-wrap">
  <div class="sf-auth-shell">

    <div class="sf-auth-brand">
      <div class="sf-brand-content">
        <div class="sf-brand-badge">Welcome</div>

        <div class="sf-brand-copy">
          <h1 class="sf-brand-title">Everything You Need to Build Your Business</h1>
          <p class="sf-brand-text">
            Find equipment, vendors, and skilled technicians all in one platform.
          </p>
        </div>
      </div>

      <div class="sf-brand-foot">
        SetupForge helps teams and professionals stay connected and productive.
      </div>
    </div>

    <div class="sf-auth-card">
      <div class="sf-auth-inner">
        <img src="../assets/images/Untitled (800 x 800 px).png" class="sf-auth-logo" alt="SetupForge Logo">

        <h2 class="sf-auth-title">Sign in</h2>
        <p class="sf-auth-subtitle">
          Enter your credentials to continue to your dashboard.
        </p>

        <?php if ($error): ?>
          <div class="alert alert-danger sf-alert mb-4"><?= h($error) ?></div>
        <?php endif; ?>

        <form method="post" autocomplete="off">

          <div class="sf-form-group">
            <label class="sf-label" for="email">Email Address</label>
            <div class="sf-input-wrap">
              <input id="email" name="email" type="email" class="form-control sf-input" placeholder="Enter your email" required>
            </div>
          </div>

          <div class="sf-form-group">
            <label class="sf-label" for="password">Password</label>
            <div class="sf-input-wrap">
              <input id="password" name="password" type="password" class="form-control sf-input" placeholder="Enter your password" required>
            </div>
          </div>

          <div class="sf-row">
            <div class="form-check sf-check m-0">
              <input class="form-check-input" type="checkbox" id="remember">
              <label class="form-check-label" for="remember">Remember me</label>
            </div>

            <a href="#" class="sf-link">Forgot password?</a>
          </div>

          <button type="submit" class="btn sf-auth-btn">Login to Account</button>

          <div class="sf-auth-footer">
            Don’t have an account?
            <a href="signup.php" class="sf-link">Create one</a>
          </div>
        </form>
      </div>
    </div>

  </div>
</main>

<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>