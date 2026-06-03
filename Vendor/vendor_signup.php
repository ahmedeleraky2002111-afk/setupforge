<?php
session_start();

require_once "../db.php";
if (!isset($conn) || !$conn) {
  http_response_code(500);
  die("Database connection missing. Check ../db.php (\$conn).");
}

ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
error_reporting(E_ALL);

/* =========================
   Helpers
========================= */
function h($s){
  return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8');
}

function redirect_to_step($step, $error = ""){
  $url = "vendor_signup.php?step=" . urlencode((string)$step);
  if ($error !== "") $url .= "&error=" . urlencode($error);
  header("Location: " . $url);
  exit;
}

function uploadFile(array $file, int $userId): string
{
  if (!isset($file) || !isset($file["error"]) || $file["error"] !== UPLOAD_ERR_OK) {
    throw new Exception("File upload failed.");
  }

  $folderAbs = __DIR__ . "/uploads/vendors/" . $userId;
  if (!is_dir($folderAbs)) {
    if (!mkdir($folderAbs, 0777, true) && !is_dir($folderAbs)) {
      throw new Exception("Could not create upload folder.");
    }
  }

  $safeName = preg_replace("/[^a-zA-Z0-9._-]/", "_", basename($file["name"]));
  $fileName = time() . "_" . $safeName;
  $targetAbs = $folderAbs . "/" . $fileName;

  if (!move_uploaded_file($file["tmp_name"], $targetAbs)) {
    throw new Exception("Could not save uploaded file.");
  }

  return "uploads/vendors/" . $userId . "/" . $fileName;
}

/* =========================
   Step logic
========================= */
$step = $_GET["step"] ?? "1";
$step = ($step === "2") ? "2" : "1";

$old = $_SESSION["vendor_signup"] ?? [];
$errorMsg = trim((string)($_GET["error"] ?? ""));

/* =========================
   Handle POST
========================= */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

  $action = $_POST["action"] ?? "";

  /* ---------- STEP 1 ---------- */
  if ($action === "step1") {

    $_SESSION["vendor_signup"] = array_merge($_SESSION["vendor_signup"] ?? [], [
      "name" => trim($_POST["name"] ?? ""),
      "email" => trim($_POST["email"] ?? ""),
      "password" => (string)($_POST["password"] ?? ""),
      "phone" => trim($_POST["phone"] ?? ""),

      "country" => trim($_POST["country"] ?? ""),
      "city" => trim($_POST["city"] ?? ""),
      "street" => trim($_POST["street"] ?? ""),

      "items_type" => trim($_POST["items_type"] ?? ""),
      "certifications_note" => trim($_POST["certifications_note"] ?? ""),
    ]);

    redirect_to_step(2);
  }

  /* ---------- STEP 2 FINISH ---------- */
  if ($action === "step2_finish") {

    if (empty($_SESSION["vendor_signup"]["email"])) {
      redirect_to_step(1, "Signup session expired. Please start again.");
    }

    $_SESSION["vendor_signup"] = array_merge($_SESSION["vendor_signup"] ?? [], [
      "bank_name" => trim($_POST["bank_name"] ?? ""),
      "bank_account_number" => trim($_POST["bank_account_number"] ?? ""),
      "account_name" => trim($_POST["account_name"] ?? ""),
    ]);

    $data = $_SESSION["vendor_signup"] ?? null;
    if (!$data) {
      redirect_to_step(1, "Signup session expired. Please start again.");
    }

    $name = trim($data["name"] ?? "");
    $email = trim($data["email"] ?? "");
    $password = (string)($data["password"] ?? "");
    $phone = trim($data["phone"] ?? "");

    $country = trim($data["country"] ?? "");
    $city = trim($data["city"] ?? "");
    $street = trim($data["street"] ?? "");

    $items_type = trim($data["items_type"] ?? "");
    $certifications_note = trim($data["certifications_note"] ?? "");

    $bank_name = trim($data["bank_name"] ?? "");
    $bank_account_number = trim($data["bank_account_number"] ?? "");
    $account_name = trim($data["account_name"] ?? "");

    if ($name === "" || $email === "" || $password === "" || $items_type === "") {
      redirect_to_step(1, "Missing required fields in Step 1.");
    }

    if (
      !isset($_FILES["commercial_reg"]) || $_FILES["commercial_reg"]["error"] !== UPLOAD_ERR_OK ||
      !isset($_FILES["tax_card"]) || $_FILES["tax_card"]["error"] !== UPLOAD_ERR_OK
    ) {
      redirect_to_step(2, "Please upload Commercial Register and Tax Card.");
    }

    try {
      /* ---------- START TRANSACTION ---------- */
      if (!pg_query($conn, "BEGIN")) {
        throw new Exception("Could not start database transaction.");
      }

      $passwordHash = password_hash($password, PASSWORD_BCRYPT);

      /* 1) Insert into users */
      $resultUser = pg_query_params($conn, "
        INSERT INTO users
          (name, email, password_hash, user_type, phone, country, city, street, status, created_at)
        VALUES
          ($1, $2, $3, 'vendor', $4, $5, $6, $7, 'pending', NOW())
        RETURNING id
      ", [
        $name,
        $email,
        $passwordHash,
        ($phone !== "" ? $phone : null),
        ($country !== "" ? $country : null),
        ($city !== "" ? $city : null),
        ($street !== "" ? $street : null),
      ]);

      if (!$resultUser) {
        throw new Exception("Could not create user: " . pg_last_error($conn));
      }

      $userRow = pg_fetch_assoc($resultUser);
      $user_id = (int)($userRow["id"] ?? 0);

      if ($user_id <= 0) {
        throw new Exception("Could not create user ID.");
      }

      /* 2) Insert into vendors */
      $resultVendor = pg_query_params($conn, "
        INSERT INTO vendors
          (user_id, items_type, commercial_reg, tax_card, verification_status,
           bank_name, bank_account_number, account_name, certifications_note, status)
        VALUES
          ($1, $2, NULL, NULL, 'pending', $3, $4, $5, $6, 'pending')
      ", [
        $user_id,
        $items_type,
        ($bank_name !== "" ? $bank_name : null),
        ($bank_account_number !== "" ? $bank_account_number : null),
        ($account_name !== "" ? $account_name : null),
        ($certifications_note !== "" ? $certifications_note : null),
      ]);

      if (!$resultVendor) {
        throw new Exception("Could not create vendor: " . pg_last_error($conn));
      }

      /* 3) Upload files */
      $commercialPath = uploadFile($_FILES["commercial_reg"], $user_id);
      $taxCardPath = uploadFile($_FILES["tax_card"], $user_id);

      /* 4) Update vendor file paths */
      $resultUpdateVendor = pg_query_params($conn, "
        UPDATE vendors
        SET commercial_reg = $1,
            tax_card = $2
        WHERE user_id = $3
      ", [
        $commercialPath,
        $taxCardPath,
        $user_id
      ]);

      if (!$resultUpdateVendor) {
        throw new Exception("Could not update vendor documents: " . pg_last_error($conn));
      }

      /* 5) Insert vendor documents */
      $doc1 = pg_query_params($conn, "
        INSERT INTO vendor_documents
          (vendor_user_id, doc_type, file_url, status, uploaded_at)
        VALUES
          ($1, $2, $3, 'pending', NOW())
      ", [
        $user_id,
        "commercial_register",
        $commercialPath
      ]);

      if (!$doc1) {
        throw new Exception("Could not save commercial register document: " . pg_last_error($conn));
      }

      $doc2 = pg_query_params($conn, "
        INSERT INTO vendor_documents
          (vendor_user_id, doc_type, file_url, status, uploaded_at)
        VALUES
          ($1, $2, $3, 'pending', NOW())
      ", [
        $user_id,
        "tax_card",
        $taxCardPath
      ]);

      if (!$doc2) {
        throw new Exception("Could not save tax card document: " . pg_last_error($conn));
      }

      /* ---------- COMMIT ---------- */
      if (!pg_query($conn, "COMMIT")) {
        throw new Exception("Could not commit transaction.");
      }

      unset($_SESSION["vendor_signup"]);
      ?>
      <!doctype html>
      <html lang="en">
      <head>
        <meta charset="UTF-8" />
        <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
        <title>Vendor Signup - Success</title>
        <link rel="stylesheet" href="vendor_ui.css?v=7">
        <link rel="preconnect" href="https://fonts.googleapis.com">
        <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
        <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">
      
  <style>
    /* =========================================================
       Vendor signup split layout - matches labor signup look
       This only changes layout/design. Form fields and PHP logic unchanged.
    ========================================================= */
    body{
      margin:0;
      background:#fff !important;
      font-family:Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      color:#111827;
    }

    .v-signup-shell{
      min-height:100vh;
      display:flex;
      background:#fff;
    }

    .v-signup-side{
      width:360px;
      flex:0 0 360px;
      min-height:100vh;
      background:#004cac;
      color:#fff;
      position:sticky;
      top:0;
      align-self:flex-start;
      display:flex;
      flex-direction:column;
      overflow:hidden;
    }

    .v-signup-side-inner{
      min-height:100vh;
      padding:52px 32px 28px;
      display:flex;
      flex-direction:column;
    }

    .v-signup-brand{
      display:flex;
      align-items:center;
      gap:16px;
      margin-bottom:72px;
      color:#fff;
      text-decoration:none;
    }

    .v-signup-brand-mark{
      width:65px;
      height:65px;
      border-radius:50%;
      background:#ffffff;
      border:0;
      box-shadow:0 8px 18px rgba(0,0,0,.14);
      display:flex;
      align-items:center;
      justify-content:center;
      overflow:hidden;
      flex-shrink:0;
    }

    .v-signup-brand-mark img{
      width:65px;
      height:65px;
      object-fit:contain;
      opacity:1;
      display:block;
    }

    .v-signup-brand-text{
      font-size:18px;
      font-weight:800;
      letter-spacing:-.02em;
      color:#fff;
    }

    .v-signup-kicker{
      font-size:11px;
      font-weight:800;
      letter-spacing:.12em;
      text-transform:uppercase;
      color:rgba(255,255,255,.68);
      margin-bottom:16px;
    }

    .v-signup-side-title{
      margin:0 0 14px;
      max-width:305px;
      color:#fff;
      font-size:26px;
      line-height:1.16;
      font-weight:850;
      letter-spacing:-.04em;
    }

    .v-signup-side-text{
      margin:0;
      max-width:300px;
      color:rgba(255,255,255,.57);
      font-size:14px;
      line-height:1.7;
      font-weight:500;
    }

    .v-signup-benefits{
      list-style:none;
      padding:0;
      margin:34px 0 0;
      display:flex;
      flex-direction:column;
      gap:15px;
    }

    .v-signup-benefits li{
      display:flex;
      align-items:center;
      gap:10px;
      color:rgba(255,255,255,.76);
      font-size:14px;
      line-height:1.3;
      font-weight:500;
    }

    .v-signup-check{
      width:14px;
      height:14px;
      border-radius:999px;
      background:#ffffff;
      color:#004cac;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      font-size:10px;
      font-weight:900;
      flex-shrink:0;
    }

    .v-signup-side-footer{
      margin-top:auto;
      color:rgba(255,255,255,.58);
      font-size:13px;
      font-weight:500;
    }

    .v-signup-side-footer a{
      color:#fff;
      font-weight:800;
      text-decoration:none;
    }

    .v-signup-side-footer a:hover{
      text-decoration:underline;
    }

    .v-signup-main{
      flex:1;
      min-width:0;
      background:#fff;
      display:flex;
      justify-content:center;
      padding:58px 40px 70px;
    }

    .v-signup-main .v-wrap{
      width:100%;
      max-width:660px;
      margin:0;
      padding:0;
    }

    .v-signup-main .v-topbar{
      position:static;
      margin:0 0 34px;
      padding:0;
      display:block;
      background:transparent;
      border:0;
      box-shadow:none;
      backdrop-filter:none;
    }

    .v-signup-main .v-topbar h1{
      margin:0 0 6px;
      font-size:26px;
      line-height:1.15;
      font-weight:850;
      letter-spacing:-.03em;
      color:#111827;
    }

    .v-signup-main .v-subtitle{
      margin:0;
      font-size:14px;
      color:#6b7280;
      font-weight:500;
    }

    .v-signup-main .v-topbar .v-links{
      display:none;
    }

    .v-signup-main .v-section{
      margin:0;
      padding:0;
      border:0;
      border-radius:0;
      box-shadow:none;
      background:#fff;
    }

    .v-signup-main .v-section::before,
    .v-signup-main .v-card::before,
    .v-signup-main .v-add-card::before{
      display:none !important;
      content:none !important;
      background:none !important;
    }

    .v-signup-main .v-section-title{
      margin:0 0 6px;
      font-size:0;
      line-height:0;
    }

    .v-signup-main .v-section-title::after{
      content:"<?= $step === '1' ? 'Account Information' : 'Bank & Documents' ?>";
      display:block;
      font-size:11px;
      line-height:1.2;
      font-weight:800;
      letter-spacing:.12em;
      text-transform:uppercase;
      color:#9ca3af;
      margin:0 0 14px;
      padding-bottom:11px;
      border-bottom:1px solid #e5e7eb;
    }

    .v-signup-main .v-section-desc{
      margin:0 0 22px;
      color:#6b7280;
      font-size:14px;
      line-height:1.55;
      font-weight:500;
    }

    .v-signup-main .v-form{
      margin-top:0;
      width:100%;
    }

    .v-signup-main .v-subsection{
      margin:0 0 24px;
    }

    .v-signup-main .v-subsection-head{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:14px;
      margin:0 0 14px;
      padding-bottom:9px;
      border-bottom:1px solid #e5e7eb;
    }

    .v-signup-main .v-subsection-title{
      font-size:11px;
      font-weight:800;
      letter-spacing:.12em;
      text-transform:uppercase;
      color:#9ca3af;
    }

    .v-signup-main .v-pill,
    .v-signup-main .v-pill-muted{
      background:transparent !important;
      border:0 !important;
      padding:0 !important;
      color:#004cac !important;
      font-size:11px !important;
      font-weight:800 !important;
      text-transform:none;
      box-shadow:none !important;
    }

    .v-signup-main .v-divider{
      display:none;
    }

    .v-signup-main .v-form-grid{
      display:grid;
      grid-template-columns:repeat(2, minmax(0, 1fr));
      gap:14px;
      margin:0;
    }

    .v-signup-main .v-span-2{
      grid-column:span 2;
    }

    .v-signup-main .v-field{
      margin:0 0 14px;
    }

    .v-signup-main .v-label{
      display:block;
      margin:0 0 7px;
      color:#111827;
      font-size:12px;
      line-height:1.2;
      font-weight:750;
      letter-spacing:.01em;
    }

    .v-signup-main .v-input,
    .v-signup-main .v-select,
    .v-signup-main .v-textarea{
      width:100%;
      min-height:40px;
      height:40px;
      padding:10px 14px;
      border-radius:10px;
      border:1px solid #e5e7eb;
      background:#f9fafb;
      color:#111827;
      font-size:14px;
      font-weight:500;
      outline:none;
      box-shadow:none;
      transition:border-color .2s ease, background .2s ease;
    }

    .v-signup-main .v-textarea{
      height:auto;
      min-height:86px;
      resize:vertical;
      padding-top:11px;
    }

    .v-signup-main .v-input:hover,
    .v-signup-main .v-select:hover,
    .v-signup-main .v-textarea:hover{
      border-color:#d1d5db;
    }

    .v-signup-main .v-input:focus,
    .v-signup-main .v-select:focus,
    .v-signup-main .v-textarea:focus{
      border-color:#8b5cf6;
      background:rgba(139,92,246,.06);
      box-shadow:none;
    }

    .v-signup-main input[type="file"].v-input{
      padding:8px 10px;
      height:auto;
      min-height:40px;
      cursor:pointer;
    }

    .v-signup-main input[type="file"].v-input::file-selector-button{
      border:0;
      border-radius:8px;
      padding:8px 12px;
      background:#004cac;
      color:#fff;
      font-weight:800;
      margin-right:12px;
      cursor:pointer;
    }

    .v-signup-main .v-alert{
      margin:0 0 22px;
      padding:13px 16px;
      border-radius:10px;
      font-size:13px;
      font-weight:650;
      box-shadow:none;
    }

    .v-signup-main .v-alert-danger{
      background:rgba(239,68,68,.08);
      border:1px solid rgba(239,68,68,.22);
      color:#dc2626;
    }

    .v-signup-main .v-actions{
      display:flex;
      gap:12px;
      margin-top:16px;
      align-items:center;
    }

    .v-signup-main .v-actions .v-btn{
      min-height:46px;
      border-radius:12px;
      box-shadow:none;
      font-size:14px;
      font-weight:850;
    }

    .v-signup-main .v-actions .v-btn-primary{
      flex:1;
      background:#004cac !important;
      border-color:#004cac !important;
      color:#fff !important;
    }

    .v-signup-main .v-actions .v-btn-primary:hover{
      background:#ffffff !important;
      border-color:#004cac !important;
      color:#004cac !important;
      box-shadow:0 12px 24px rgba(0,76,172,.12) !important;
    }

    .v-signup-main .v-actions .v-btn-outline{
      background:#fff !important;
      border:1px solid #e5e7eb !important;
      color:#374151 !important;
    }

    .v-signup-main .v-actions .v-btn-outline:hover{
      border-color:#004cac !important;
      color:#004cac !important;
      background:#ffffff !important;
      box-shadow:0 10px 20px rgba(0,76,172,.10) !important;
    }

    @media (max-width: 900px){
      .v-signup-shell{
        display:block;
      }

      .v-signup-side{
        position:relative;
        width:100%;
        min-height:auto;
        height:auto;
      }

      .v-signup-side-inner{
        min-height:auto;
        padding:28px 22px;
      }

      .v-signup-brand{
        margin-bottom:28px;
      }

      .v-signup-side-title{
        font-size:24px;
      }

      .v-signup-side-footer{
        margin-top:28px;
      }

      .v-signup-main{
        padding:34px 16px 50px;
      }

      .v-signup-main .v-form-grid{
        grid-template-columns:1fr;
      }

      .v-signup-main .v-span-2{
        grid-column:span 1;
      }

      .v-signup-main .v-actions{
        flex-direction:column;
      }

      .v-signup-main .v-actions .v-btn{
        width:100%;
      }
    }

    /* Final requested fixes */
    .v-signup-check{
      background:#ffffff !important;
      color:#004cac !important;
    }

    .v-signup-brand-mark{
      width:65px !important;
      height:65px !important;
      background:#ffffff !important;
      border-radius:50% !important;
      box-shadow:0 8px 18px rgba(0,0,0,.14) !important;
    }

    .v-signup-brand-mark img{
      width:65px !important;
      height:65px !important;
      object-fit:contain !important;
      opacity:1 !important;
      display:block !important;
    }

    .v-signup-main .v-actions .v-btn-primary:hover,
    .v-signup-main .v-actions .v-btn-primary:focus,
    .v-signup-main .v-actions .v-btn-primary:active{
      background:#ffffff !important;
      border-color:#004cac !important;
      color:#004cac !important;
      box-shadow:0 12px 24px rgba(0,76,172,.12) !important;
      transform:translateY(-1px) !important;
    }

    .v-signup-main .v-actions .v-btn-outline:hover,
    .v-signup-main .v-actions .v-btn-outline:focus,
    .v-signup-main .v-actions .v-btn-outline:active{
      background:#ffffff !important;
      border-color:#004cac !important;
      color:#004cac !important;
      box-shadow:0 10px 20px rgba(0,76,172,.10) !important;
      transform:translateY(-1px) !important;
    }

  </style>

</head>
      <body>
        <div class="v-wrap">
          <div class="v-topbar">
            <div>
              <h1>Signup Complete</h1>
              <div class="v-subtitle">Your vendor account is created and pending verification.</div>
            </div>
            <div class="v-links">
              <a class="v-btn v-btn-outline" href="../auth/login.php">Go to Login</a>
            </div>
          </div>

          <div class="v-section">
            <div class="v-alert v-alert-success">
              Vendor signup successful 🎉
            </div>

            <div class="v-section-desc" style="margin-top:10px;">
              We received your documents. After approval, your account will be activated.
            </div>

            <div class="v-actions" style="margin-top:14px;">
              <a class="v-btn v-btn-primary" href="vendor_login.php">Login</a>
              <a class="v-btn v-btn-outline" href="../home.php">Back to Home</a>
            </div>
          </div>
        </div>
      </body>
      </html>
      <?php
      exit;

    } catch (Exception $e) {
      pg_query($conn, "ROLLBACK");

      $msg = $e->getMessage();
      if (stripos($msg, "duplicate key") !== false || stripos($msg, "unique") !== false) {
        redirect_to_step(1, "This email already exists. Please use another email.");
      }

      redirect_to_step(2, "Signup failed: " . $msg);
    }
  }

  redirect_to_step(1, "Invalid request.");
}

/* =========================
   Guard step2 if no step1 session
========================= */
if ($step === "2" && empty($old["email"])) {
  redirect_to_step(1, "Please complete Step 1 first.");
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1.0"/>
  <title>Vendor Signup</title>

  <link rel="stylesheet" href="vendor_ui.css?v=7">
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;600;800;900&display=swap" rel="stylesheet">

  <style>
    /* =========================================================
       Vendor signup split layout - matches labor signup look
       This only changes layout/design. Form fields and PHP logic unchanged.
    ========================================================= */
    body{
      margin:0;
      background:#fff !important;
      font-family:Inter, system-ui, -apple-system, Segoe UI, Roboto, Arial, sans-serif;
      color:#111827;
    }

    .v-signup-shell{
      min-height:100vh;
      display:flex;
      background:#fff;
    }

    .v-signup-side{
      width:360px;
      flex:0 0 360px;
      min-height:100vh;
      background:#004cac;
      color:#fff;
      position:sticky;
      top:0;
      align-self:flex-start;
      display:flex;
      flex-direction:column;
      overflow:hidden;
    }

    .v-signup-side-inner{
      min-height:100vh;
      padding:52px 32px 28px;
      display:flex;
      flex-direction:column;
    }

    .v-signup-brand{
      display:flex;
      align-items:center;
      gap:16px;
      margin-bottom:72px;
      color:#fff;
      text-decoration:none;
    }

   .v-signup-brand-mark{
  width:56px !important;
  height:56px !important;
  border-radius:50% !important;
  overflow:hidden !important;
  background:#ffffff !important;
}

.v-signup-brand-mark img{
  width:100% !important;
  height:100% !important;
  object-fit:cover !important;
  transform:scale(1.9) !important;
}
    .v-signup-brand-mark img{
      width:56px;
      height:56px;
      object-fit:contain;
      opacity:1;
      display:block;
    }

    .v-signup-brand-text{
      font-size:18px;
      font-weight:800;
      letter-spacing:-.02em;
      color:#fff;
    }

    .v-signup-kicker{
      font-size:11px;
      font-weight:800;
      letter-spacing:.12em;
      text-transform:uppercase;
      color:rgba(255,255,255,.68);
      margin-bottom:16px;
    }

    .v-signup-side-title{
      margin:0 0 14px;
      max-width:305px;
      color:#fff;
      font-size:26px;
      line-height:1.16;
      font-weight:850;
      letter-spacing:-.04em;
    }

    .v-signup-side-text{
      margin:0;
      max-width:300px;
      color:rgba(255,255,255,.57);
      font-size:14px;
      line-height:1.7;
      font-weight:500;
    }

    .v-signup-benefits{
      list-style:none;
      padding:0;
      margin:34px 0 0;
      display:flex;
      flex-direction:column;
      gap:15px;
    }

    .v-signup-benefits li{
      display:flex;
      align-items:center;
      gap:10px;
      color:rgba(255,255,255,.76);
      font-size:14px;
      line-height:1.3;
      font-weight:500;
    }

    .v-signup-check{
      width:14px;
      height:14px;
      border-radius:999px;
      background:#ffffff;
      color:#004cac;
      display:inline-flex;
      align-items:center;
      justify-content:center;
      font-size:10px;
      font-weight:900;
      flex-shrink:0;
    }

    .v-signup-side-footer{
      margin-top:auto;
      color:rgba(255,255,255,.58);
      font-size:13px;
      font-weight:500;
    }

    .v-signup-side-footer a{
      color:#fff;
      font-weight:800;
      text-decoration:none;
    }

    .v-signup-side-footer a:hover{
      text-decoration:underline;
    }

    .v-signup-main{
      flex:1;
      min-width:0;
      background:#fff;
      display:flex;
      justify-content:center;
      padding:58px 40px 70px;
    }

    .v-signup-main .v-wrap{
      width:100%;
      max-width:660px;
      margin:0;
      padding:0;
    }

    .v-signup-main .v-topbar{
      position:static;
      margin:0 0 34px;
      padding:0;
      display:block;
      background:transparent;
      border:0;
      box-shadow:none;
      backdrop-filter:none;
    }

    .v-signup-main .v-topbar h1{
      margin:0 0 6px;
      font-size:26px;
      line-height:1.15;
      font-weight:850;
      letter-spacing:-.03em;
      color:#111827;
    }

    .v-signup-main .v-subtitle{
      margin:0;
      font-size:14px;
      color:#6b7280;
      font-weight:500;
    }

    .v-signup-main .v-topbar .v-links{
      display:none;
    }

    .v-signup-main .v-section{
      margin:0;
      padding:0;
      border:0;
      border-radius:0;
      box-shadow:none;
      background:#fff;
    }

    .v-signup-main .v-section::before,
    .v-signup-main .v-card::before,
    .v-signup-main .v-add-card::before{
      display:none !important;
      content:none !important;
      background:none !important;
    }

    .v-signup-main .v-section-title{
      margin:0 0 6px;
      font-size:0;
      line-height:0;
    }

    .v-signup-main .v-section-title::after{
      content:"<?= $step === '1' ? 'Account Information' : 'Bank & Documents' ?>";
      display:block;
      font-size:11px;
      line-height:1.2;
      font-weight:800;
      letter-spacing:.12em;
      text-transform:uppercase;
      color:#9ca3af;
      margin:0 0 14px;
      padding-bottom:11px;
      border-bottom:1px solid #e5e7eb;
    }

    .v-signup-main .v-section-desc{
      margin:0 0 22px;
      color:#6b7280;
      font-size:14px;
      line-height:1.55;
      font-weight:500;
    }

    .v-signup-main .v-form{
      margin-top:0;
      width:100%;
    }

    .v-signup-main .v-subsection{
      margin:0 0 24px;
    }

    .v-signup-main .v-subsection-head{
      display:flex;
      align-items:center;
      justify-content:space-between;
      gap:14px;
      margin:0 0 14px;
      padding-bottom:9px;
      border-bottom:1px solid #e5e7eb;
    }

    .v-signup-main .v-subsection-title{
      font-size:11px;
      font-weight:800;
      letter-spacing:.12em;
      text-transform:uppercase;
      color:#9ca3af;
    }

    .v-signup-main .v-pill,
    .v-signup-main .v-pill-muted{
      background:transparent !important;
      border:0 !important;
      padding:0 !important;
      color:#004cac !important;
      font-size:11px !important;
      font-weight:800 !important;
      text-transform:none;
      box-shadow:none !important;
    }

    .v-signup-main .v-divider{
      display:none;
    }

    .v-signup-main .v-form-grid{
      display:grid;
      grid-template-columns:repeat(2, minmax(0, 1fr));
      gap:14px;
      margin:0;
    }

    .v-signup-main .v-span-2{
      grid-column:span 2;
    }

    .v-signup-main .v-field{
      margin:0 0 14px;
    }

    .v-signup-main .v-label{
      display:block;
      margin:0 0 7px;
      color:#111827;
      font-size:12px;
      line-height:1.2;
      font-weight:750;
      letter-spacing:.01em;
    }

    .v-signup-main .v-input,
    .v-signup-main .v-select,
    .v-signup-main .v-textarea{
      width:100%;
      min-height:40px;
      height:40px;
      padding:10px 14px;
      border-radius:10px;
      border:1px solid #e5e7eb;
      background:#f9fafb;
      color:#111827;
      font-size:14px;
      font-weight:500;
      outline:none;
      box-shadow:none;
      transition:border-color .2s ease, background .2s ease;
    }

    .v-signup-main .v-textarea{
      height:auto;
      min-height:86px;
      resize:vertical;
      padding-top:11px;
    }

    .v-signup-main .v-input:hover,
    .v-signup-main .v-select:hover,
    .v-signup-main .v-textarea:hover{
      border-color:#d1d5db;
    }

    .v-signup-main .v-input:focus,
    .v-signup-main .v-select:focus,
    .v-signup-main .v-textarea:focus{
      border-color:#8b5cf6;
      background:rgba(139,92,246,.06);
      box-shadow:none;
    }

    .v-signup-main input[type="file"].v-input{
      padding:8px 10px;
      height:auto;
      min-height:40px;
      cursor:pointer;
    }

    .v-signup-main input[type="file"].v-input::file-selector-button{
      border:0;
      border-radius:8px;
      padding:8px 12px;
      background:#004cac;
      color:#fff;
      font-weight:800;
      margin-right:12px;
      cursor:pointer;
    }

    .v-signup-main .v-alert{
      margin:0 0 22px;
      padding:13px 16px;
      border-radius:10px;
      font-size:13px;
      font-weight:650;
      box-shadow:none;
    }

    .v-signup-main .v-alert-danger{
      background:rgba(239,68,68,.08);
      border:1px solid rgba(239,68,68,.22);
      color:#dc2626;
    }

    .v-signup-main .v-actions{
      display:flex;
      gap:12px;
      margin-top:16px;
      align-items:center;
    }

    .v-signup-main .v-actions .v-btn{
      min-height:46px;
      border-radius:12px;
      box-shadow:none;
      font-size:14px;
      font-weight:850;
    }

    .v-signup-main .v-actions .v-btn-primary{
      flex:1;
      background:#004cac !important;
      border-color:#004cac !important;
      color:#fff !important;
    }

    .v-signup-main .v-actions .v-btn-primary:hover{
      background:#ffffff !important;
      border-color:#004cac !important;
      color:#004cac !important;
      box-shadow:0 12px 24px rgba(0,76,172,.12) !important;
    }

    .v-signup-main .v-actions .v-btn-outline{
      background:#fff !important;
      border:1px solid #e5e7eb !important;
      color:#374151 !important;
    }

    .v-signup-main .v-actions .v-btn-outline:hover{
      border-color:#004cac !important;
      color:#004cac !important;
      background:#ffffff !important;
      box-shadow:0 10px 20px rgba(0,76,172,.10) !important;
    }

    @media (max-width: 900px){
      .v-signup-shell{
        display:block;
      }

      .v-signup-side{
        position:relative;
        width:100%;
        min-height:auto;
        height:auto;
      }

      .v-signup-side-inner{
        min-height:auto;
        padding:28px 22px;
      }

      .v-signup-brand{
        margin-bottom:28px;
      }

      .v-signup-side-title{
        font-size:24px;
      }

      .v-signup-side-footer{
        margin-top:28px;
      }

      .v-signup-main{
        padding:34px 16px 50px;
      }

      .v-signup-main .v-form-grid{
        grid-template-columns:1fr;
      }

      .v-signup-main .v-span-2{
        grid-column:span 1;
      }

      .v-signup-main .v-actions{
        flex-direction:column;
      }

      .v-signup-main .v-actions .v-btn{
        width:100%;
      }
    }
  </style>

</head>
<body>

<div class="v-signup-shell">
  <aside class="v-signup-side">
    <div class="v-signup-side-inner">
      <a class="v-signup-brand" href="../home.php">
        <span class="v-signup-brand-mark">
          <img src="../assets/images/Logo.png" alt="SetupForge">
        </span>
        <span class="v-signup-brand-text">SetupForge</span>
      </a>

      <div class="v-signup-kicker">FOR VENDORS</div>
      <h2 class="v-signup-side-title">Start selling to businesses that are ready to buy.</h2>
      <p class="v-signup-side-text">
        List your products, manage orders, and grow your business through SetupForge.
      </p>

      <ul class="v-signup-benefits">
        <li><span class="v-signup-check">✓</span> Reach business customers</li>
        <li><span class="v-signup-check">✓</span> Showcase your products</li>
        <li><span class="v-signup-check">✓</span> Manage orders easily</li>
        <li><span class="v-signup-check">✓</span> Track sales on your dashboard</li>
      </ul>

      <div class="v-signup-side-footer">
        Already have an account? <a href="../auth/login.php">Sign in</a>
      </div>
    </div>
  </aside>

  <main class="v-signup-main">
    <div class="v-wrap">

  <?php if ($step === "1"): ?>

    <div class="v-topbar">
      <div>
        <h1>Create Vendor Account</h1>
        <div class="v-subtitle">Step 1 of 2 • Account, Address & Business</div>
      </div>
      <div class="v-links">
        <a class="v-btn v-btn-outline" href="../auth/login.php">Already have an account?</a>
      </div>
    </div>

    <div class="v-section">
      <h3 class="v-section-title">Step 1</h3>
      <div class="v-section-desc">Fill the required fields then continue to Step 2.</div>

      <?php if ($errorMsg !== ""): ?>
        <div class="v-alert v-alert-danger"><?= h($errorMsg) ?></div>
      <?php endif; ?>

      <form class="v-form" method="POST" action="vendor_signup.php?step=1">
        <input type="hidden" name="action" value="step1">

        <div class="v-subsection">
          <div class="v-subsection-head">
            <div class="v-subsection-title">Account Information</div>
            <div class="v-pill">Required</div>
          </div>

          <div class="v-form-grid">
            <div class="v-field">
              <label class="v-label">Full Name *</label>
              <input class="v-input" type="text" name="name" required value="<?= h($old["name"] ?? "") ?>">
            </div>

            <div class="v-field">
              <label class="v-label">Email *</label>
              <input class="v-input" type="email" name="email" required value="<?= h($old["email"] ?? "") ?>">
            </div>

            <div class="v-field">
              <label class="v-label">Password *</label>
              <input class="v-input" type="password" name="password" required value="<?= h($old["password"] ?? "") ?>">
            </div>

            <div class="v-field">
              <label class="v-label">Phone</label>
              <input class="v-input" type="text" name="phone" value="<?= h($old["phone"] ?? "") ?>">
            </div>
          </div>
        </div>

        <div class="v-divider"></div>

        <div class="v-subsection">
          <div class="v-subsection-head">
            <div class="v-subsection-title">Address</div>
            <div class="v-pill v-pill-muted">Optional</div>
          </div>

          <div class="v-form-grid">
            <div class="v-field">
              <label class="v-label">Country</label>
              <input class="v-input" type="text" name="country" value="<?= h($old["country"] ?? "") ?>">
            </div>

            <div class="v-field">
              <label class="v-label">City</label>
              <input class="v-input" type="text" name="city" value="<?= h($old["city"] ?? "") ?>">
            </div>

            <div class="v-field v-span-2">
              <label class="v-label">Street</label>
              <input class="v-input" type="text" name="street" value="<?= h($old["street"] ?? "") ?>">
            </div>
          </div>
        </div>

        <div class="v-divider"></div>

        <div class="v-subsection">
          <div class="v-subsection-head">
            <div class="v-subsection-title">Business Information</div>
            <div class="v-pill">Required</div>
          </div>

          <div class="v-form-grid">
            <div class="v-field">
              <label class="v-label">Items Type *</label>
              <select class="v-select" name="items_type" required>
                <option value="" disabled <?= empty($old["items_type"]) ? "selected" : "" ?>>Select category</option>
                <option value="electronics" <?= (($old["items_type"] ?? "") === "electronics") ? "selected" : "" ?>>Electronics</option>
                <option value="furniture" <?= (($old["items_type"] ?? "") === "furniture") ? "selected" : "" ?>>Furniture</option>
                <option value="household" <?= (($old["items_type"] ?? "") === "household") ? "selected" : "" ?>>Household</option>
              </select>
            </div>

            <div class="v-field v-span-2">
              <label class="v-label">Certifications Note</label>
              <textarea class="v-textarea" name="certifications_note"><?= h($old["certifications_note"] ?? "") ?></textarea>
            </div>
          </div>

          <div class="v-actions">
            <button class="v-btn v-btn-primary" type="submit">Next: Bank + Documents</button>
            <a class="v-btn v-btn-outline" href="vendor_login.php">Cancel</a>
          </div>
        </div>

      </form>
    </div>

  <?php else: ?>

    <div class="v-topbar">
      <div>
        <h1>Bank & Documents</h1>
        <div class="v-subtitle">Step 2 of 2 • Finish signup</div>
      </div>

      <div class="v-links">
        <a class="v-btn v-btn-outline" href="vendor_signup.php?step=1">Back</a>
        <a class="v-btn v-btn-outline" href="vendor_login.php">Login</a>
      </div>
    </div>

    <div class="v-section">
      <h3 class="v-section-title">Step 2</h3>
      <div class="v-section-desc">Upload required documents and optionally add bank information.</div>

      <?php if ($errorMsg !== ""): ?>
        <div class="v-alert v-alert-danger"><?= h($errorMsg) ?></div>
      <?php endif; ?>

      <form class="v-form" method="POST" action="vendor_signup.php?step=2" enctype="multipart/form-data">
        <input type="hidden" name="action" value="step2_finish">

        <div class="v-subsection">
          <div class="v-subsection-head">
            <div class="v-subsection-title">Bank Information</div>
            <div class="v-pill v-pill-muted">Optional</div>
          </div>

          <div class="v-form-grid">
            <div class="v-field">
              <label class="v-label" for="bank_name">Bank Name</label>
              <input class="v-input" id="bank_name" type="text" name="bank_name" value="<?= h($old["bank_name"] ?? "") ?>">
            </div>

            <div class="v-field">
              <label class="v-label" for="bank_account_number">Bank Account Number</label>
              <input class="v-input" id="bank_account_number" type="text" name="bank_account_number" value="<?= h($old["bank_account_number"] ?? "") ?>">
            </div>

            <div class="v-field v-span-2">
              <label class="v-label" for="account_name">Account Name</label>
              <input class="v-input" id="account_name" type="text" name="account_name" value="<?= h($old["account_name"] ?? "") ?>">
            </div>
          </div>
        </div>

        <div class="v-divider"></div>

        <div class="v-subsection">
          <div class="v-subsection-head">
            <div class="v-subsection-title">Vendor Documents</div>
            <div class="v-pill">Required</div>
          </div>

          <div class="v-form-grid">
            <div class="v-field">
              <label class="v-label" for="commercial_reg">Commercial Register *</label>
              <input class="v-input" id="commercial_reg" type="file" name="commercial_reg" required>
            </div>

            <div class="v-field">
              <label class="v-label" for="tax_card">Tax Card *</label>
              <input class="v-input" id="tax_card" type="file" name="tax_card" required>
            </div>
          </div>

          <div class="v-actions">
            <a class="v-btn v-btn-outline" href="vendor_signup.php?step=1">Back</a>
            <button class="v-btn v-btn-primary" type="submit">Finish Signup</button>
          </div>
        </div>

      </form>
    </div>

  <?php endif; ?>

</div>
  </main>
</div>

</body>
</html>