<?php
// setup.php
session_start();
require_once "db.php";

/* ================================================================
   SETUP STATUS HELPERS
   ================================================================ */

/**
 * Check if the logged-in user has a completed + paid setup.
 * Completion = at least one paid order linked to their business.
 */
function user_has_completed_setup($conn, $userId) {
    if (!$userId || !$conn) return false;
    $res = @pg_query_params($conn,
        "SELECT 1 FROM orders
         WHERE business_user_id = $1
           AND payment_status = 'paid'
           AND order_type = 'setup'
           AND order_total > 0
         LIMIT 1",
        [$userId]
    );
    return ($res && pg_num_rows($res) > 0);
}

/**
 * Get the business row for this user (or null).
 */
function get_business_row($conn, $userId) {
    if (!$userId || !$conn) return null;
    $res = @pg_query_params($conn,
        "SELECT * FROM businesses WHERE user_id = $1 LIMIT 1",
        [$userId]
    );
    if (!$res || pg_num_rows($res) === 0) return null;
    return pg_fetch_assoc($res);
}

/**
 * Upsert business record with current wizard state.
 * Creates the row if it doesn't exist, updates if it does.
 */
function save_wizard_to_db($conn, $userId, $w, $step, $status = 'in_progress') {
    if (!$userId || !$conn) return;
     // DEBUG
    file_put_contents(__DIR__ . "/biz_debug.txt", 
        "step=$step business_name=" . ($w['business_name'] ?? 'MISSING') . "\n", 
        FILE_APPEND);
    $businessName    = trim($w['business_name'] ?? '');
    $businessType    = $w['business_type'] ?? null;
    $restaurantType  = $w['restaurant_type'] ?? null;
    $indoorTables    = isset($w['indoor_tables']) ? (int)$w['indoor_tables'] : null;
    $outdoorTables   = isset($w['outdoor_tables']) ? (int)$w['outdoor_tables'] : null;
    $areaSqm         = isset($w['area_sqm']) ? (int)$w['area_sqm'] : null;
    $floorCount      = isset($w['floor_count']) ? (int)$w['floor_count'] : 1;
    $budget          = isset($w['budget']) ? (int)$w['budget'] : null;
    $seatCount       = ($indoorTables + $outdoorTables) * 4;

    $modules         = !empty($w['modules']) ? '{' . implode(',', $w['modules']) . '}' : null;
    $installSvcs     = !empty($w['installation_services']) ? '{' . implode(',', $w['installation_services']) . '}' : null;

    // Pack staffing into JSON (floor_count stored here until a dedicated column is added)
    $staffing = [];
    foreach (['waiter','chef','cashier','security','barista','busboy','host','kitchen_helper'] as $role) {
        $staffing[$role] = (int)($w[$role . '_count'] ?? 0);
    }
$staffing['floor_count'] = $floorCount;
$staffing['services'] = $w['services'] ?? [];
$staffingJson = json_encode($staffing);

    // Check if row exists
    $check = @pg_query_params($conn, "SELECT user_id FROM businesses WHERE user_id = $1", [$userId]);
    $exists = ($check && pg_num_rows($check) > 0);

    if ($exists) {
        @pg_query_params($conn, "
            UPDATE businesses SET
                business_name = CASE WHEN $2 IS NOT NULL AND $2 <> '' THEN $2 ELSE business_name END,
                business_type         = COALESCE($3, business_type),
                restaurant_type       = $4,
                indoor_tables         = $5,
                outdoor_tables        = $6,
                area_sqm              = $7,
                budget_egp            = COALESCE($8, budget_egp),
                seat_count            = $9,
                modules               = $10,
                installation_services = $11,
                staffing_data         = $12,
                setup_step            = $13,
                setup_status          = $14,
                updated_at            = now()
            WHERE user_id = $1
        ", [
            $userId, $businessName !== '' ? $businessName : null, $businessType,
            $restaurantType, $indoorTables, $outdoorTables, $areaSqm,
            $budget ?: null, $seatCount,
            $modules, $installSvcs, $staffingJson,
            $step, $status
        ]);
    } else {
        @pg_query_params($conn, "
            INSERT INTO businesses (
                user_id, business_name, business_type, restaurant_type,
                indoor_tables, outdoor_tables, area_sqm, budget_egp, seat_count,
                modules, installation_services, staffing_data,
                setup_step, setup_status, status, updated_at
            ) VALUES (
                $1, $2, $3, $4,
                $5, $6, $7, $8, $9,
                $10, $11, $12,
                $13, $14, 'pending', now()
            )
        ", [
            $userId, $businessName ?: null, $businessType, $restaurantType,
            $indoorTables, $outdoorTables, $areaSqm, $budget ?: null, $seatCount,
            $modules, $installSvcs, $staffingJson,
            $step, $status
        ]);
    }
}

/* ================================================================
   REDIRECT COMPLETED USERS AWAY FROM SETUP
   ================================================================ */

$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;

if ($userId && user_has_completed_setup($conn, $userId)) {
    header("Location: business_overview.php");
    exit;
}



/* ================================================================
   RESUME FROM SAVED STEP (if user left mid-wizard)
   ================================================================ */

// If user just hit setup.php with no step param and no wizard session,
// check DB for a saved step to resume from.
// Guest resume — no DB needed, just read session
if (!$userId && !isset($_GET['step']) && !empty($_SESSION['wizard'])) {
    $w = $_SESSION['wizard'];
    $guestStep = 0;
    if (!empty($w['budget']))           $guestStep = 6;
    elseif ((int)($w['indoor_seats'] ?? 0) > 0) $guestStep = 5;
    elseif (!empty($w['business_type'])) $guestStep = ($w['business_type'] === 'Restaurant' ? 2 : 3);
    elseif (!empty($w['business_name'])) $guestStep = 1;

    if ($guestStep > 0) {
        header("Location: setup.php?step=" . $guestStep);
        exit;
    }
}

if ($userId && !isset($_GET['step'])) {
  file_put_contents(__DIR__ . "/resume_debug.txt", 
        "userId: $userId\n" .
        "bizRow: " . print_r(get_business_row($conn, $userId), true) . "\n",
        FILE_APPEND
    );
    $bizRow = get_business_row($conn, $userId);
    if ($bizRow && (int)($bizRow['setup_step'] ?? 0) > 0 && ($bizRow['setup_status'] ?? '') === 'in_progress') {
        $savedStep = (int)$bizRow['setup_step'];
                unset($_SESSION['wizard']);


        // Restore wizard session from DB
        $_SESSION['wizard'] = [
            'business_name'        => $bizRow['business_name'] ?? '',
            'business_type'        => $bizRow['business_type'] ?? '',
            'restaurant_type'      => $bizRow['restaurant_type'] ?? '',
            'indoor_tables'        => (int)($bizRow['indoor_tables'] ?? 0),
            'outdoor_tables'       => (int)($bizRow['outdoor_tables'] ?? 0),
            'area_sqm'             => (int)($bizRow['area_sqm'] ?? 0),
            'indoor_seats'         => (int)($bizRow['indoor_tables'] ?? 0) * 4,
            'outdoor_seats'        => (int)($bizRow['outdoor_tables'] ?? 0) * 4,
            'budget'               => (int)($bizRow['budget_egp'] ?? 0),
        ];
        // Restore services from staffing_data
if (!empty($bizRow['staffing_data'])) {
    $staffingData = json_decode($bizRow['staffing_data'], true);
    if (!empty($staffingData['services'])) {
        $_SESSION['wizard']['services'] = $staffingData['services'];
    }
}

        // Restore modules array
        $rawModules = $bizRow['modules'] ?? '';
        if ($rawModules) {
            $rawModules = trim($rawModules, '{}');
            $_SESSION['wizard']['modules'] = $rawModules ? explode(',', $rawModules) : [];
        }

        // Restore staffing
        if (!empty($bizRow['staffing_data'])) {
            $staffing = json_decode($bizRow['staffing_data'], true);
            if (is_array($staffing)) {
                foreach ($staffing as $role => $count) {
                    if ($role === 'floor_count') {
                        $_SESSION['wizard']['floor_count'] = (int)$count;
                    } else {
                        $_SESSION['wizard'][$role . '_count'] = (int)$count;
                    }
                }
            }
        }

        header("Location: setup.php?step=" . $savedStep);
        exit;
    }
}

/* ================================================================
   ORIGINAL SETUP LOGIC (unchanged below, with save_wizard_to_db added)
   ================================================================ */
   // Read selected services
$services       = $_SESSION['wizard']['services'] ?? [];
$hasEquipment   = in_array('equipment',   $services);
$hasStaff       = in_array('staff',       $services);
$hasInstall     = in_array('installation',$services);
$hasFinishing   = in_array('finishing',   $services);
$hasAdvertising = in_array('advertising', $services);

// If no services selected at all, go back to service select
if (empty($services) && !isset($_GET['step']) && empty($_SESSION['wizard'])) {
    header("Location: service_select.php");
    exit;
}

$step = isset($_GET["step"]) ? (int)$_GET["step"] : 0;
function redirect_step($n){
  header("Location: setup.php?step=".$n);
  exit;
}

/* ---------- HANDLE FORM SUBMIT ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

  $currentStep = (int)($_POST["step"] ?? 1);

  if ($currentStep === 0) {
    $savedServices = $_SESSION['wizard']['services'] ?? [];
    $_SESSION["wizard"] = [];
    $_SESSION["wizard"]["business_name"] = trim($_POST["business_name"] ?? "");
    $_SESSION["wizard"]["services"] = $savedServices;
    
    if ($userId) {
        save_wizard_to_db($conn, $userId, $_SESSION["wizard"], 1);

        // Force save business name directly
        pg_query_params($conn,
            "UPDATE businesses SET business_name = \$1 WHERE user_id = \$2",
            [trim($_POST["business_name"] ?? ""), $userId]
        );

        // Convert customer to business if needed
        pg_query_params($conn,
            "UPDATE users SET user_type = 'business' WHERE id = $1 AND user_type = 'customer'",
            [$userId]
        );
        $_SESSION["user_type"] = "business";
    }
    
    redirect_step(1);
}

  if ($currentStep === 1) {
    $selectedBusiness = $_POST["business_type"] ?? null;
    $_SESSION["wizard"]["business_type"] = $selectedBusiness;
$nextStep = $selectedBusiness === "Restaurant" ? 2 : ($hasEquipment ? 3 : ($hasInstall ? 4 : 7));
if ($userId) save_wizard_to_db($conn, $userId, $_SESSION["wizard"], $nextStep);

    if ($selectedBusiness === "Restaurant") {
    redirect_step(2);
} else {
    unset($_SESSION["wizard"]["restaurant_type"]);
    // If no equipment, no install, no staff → finishing/advertising only
    if (!$hasEquipment && !$hasInstall && !$hasStaff) {
        if ($userId) save_wizard_to_db($conn, $userId, $_SESSION["wizard"], 1, 'completed');
        if (!$userId) { $_SESSION["signup_intent"] = "business"; header("Location: auth/signup.php?next=" . urlencode("../service_jobs.php")); exit; }
        header("Location: service_jobs.php"); exit;
    }
    // If no equipment but has staff or install → skip tables
    if (!$hasEquipment) {
        if ($hasInstall) redirect_step(4);
        else redirect_step(7);
    }
    redirect_step(3);
}
  }

  if ($currentStep === 2) {
    $_SESSION["wizard"]["restaurant_type"] = $_POST["restaurant_type"] ?? "standard_dining";
    
    // If only finishing/advertising selected (no equipment, installation, staff)
    if (!$hasEquipment && !$hasInstall && !$hasStaff) {
        if ($userId) save_wizard_to_db($conn, $userId, $_SESSION["wizard"], 2, 'completed');
        if (!$userId) {
            $_SESSION["signup_intent"] = "business";
            header("Location: auth/signup.php?next=" . urlencode("../service_jobs.php"));
            exit;
        }
        header("Location: service_jobs.php");
        exit;
    }
    
    if (!$hasEquipment && $hasInstall) {
    if ($userId) save_wizard_to_db($conn, $userId, $_SESSION["wizard"], 4);
    redirect_step(4);
} elseif (!$hasEquipment && $hasStaff) {
    if ($userId) save_wizard_to_db($conn, $userId, $_SESSION["wizard"], 7);
    redirect_step(7);
} else {
    if ($userId) save_wizard_to_db($conn, $userId, $_SESSION["wizard"], 3);
    redirect_step(3);
}
}

  if ($currentStep === 3) {
    $indoorTbls  = max(1, (int)($_POST["indoor_tables"]  ?? 1));
    $outdoorTbls = max(0, (int)($_POST["outdoor_tables"] ?? 0));
    $_SESSION["wizard"]["indoor_tables"]  = $indoorTbls;
    $_SESSION["wizard"]["outdoor_tables"] = $outdoorTbls;
    $_SESSION["wizard"]["indoor_seats"]   = $indoorTbls * 4;
    $_SESSION["wizard"]["outdoor_seats"]  = $outdoorTbls * 4;
    // Area step only needed if installation selected
    if ($hasInstall) {
        if ($userId) save_wizard_to_db($conn, $userId, $_SESSION["wizard"], 4);
        redirect_step(4);
    } else {
        if ($userId) save_wizard_to_db($conn, $userId, $_SESSION["wizard"], 5);
        redirect_step(5);
    }
}

  if ($currentStep === 4) {
    $_SESSION["wizard"]["area_sqm"]    = max(10, (int)($_POST["area_sqm"] ?? 50));
    $_SESSION["wizard"]["floor_count"] = max(1, (int)($_POST["floor_count"] ?? 1));

    $rt = $_SESSION["wizard"]["restaurant_type"] ?? "standard_dining";
    if ($rt === "cloud_kitchen") {
        $_SESSION["wizard"]["modules"] = ["kitchen","pos"];
    } elseif ($rt === "premium_dining") {
        $_SESSION["wizard"]["modules"] = ["kitchen","pos","furniture","electronics","ac"];
    } else {
        $_SESSION["wizard"]["modules"] = ["kitchen","pos","furniture","electronics","ac"];
    }

    // If no equipment, skip budget step
    if (!$hasEquipment) {
        if ($userId) save_wizard_to_db($conn, $userId, $_SESSION["wizard"], 6);
        redirect_step(6);
    } else {
        if ($userId) save_wizard_to_db($conn, $userId, $_SESSION["wizard"], 5);
        redirect_step(5);
    }
}

  if ($currentStep === 5) {
    $budget = (int)preg_replace("/[^\d]/", "", $_POST["budget"] ?? "0");
    $_SESSION["wizard"]["budget"] = $budget;
    if ($hasInstall) {
        if ($userId) save_wizard_to_db($conn, $userId, $_SESSION["wizard"], 6);
        redirect_step(6);
    } elseif ($hasStaff) {
        if ($userId) save_wizard_to_db($conn, $userId, $_SESSION["wizard"], 7);
        redirect_step(7);
    } else {
        if ($userId) save_wizard_to_db($conn, $userId, $_SESSION["wizard"], 7, 'completed');
        if (!$userId) {
            $_SESSION["signup_intent"] = "business";
            header("Location: auth/signup.php?next=" . urlencode("packages.php"));
            exit;
        }
        header("Location: packages.php");
        exit;
    }
}

  if ($currentStep === 6) {
    $installationServices = $_POST["installation_services"] ?? [];
    if (!is_array($installationServices)) $installationServices = [];

    $_SESSION["wizard"]["installation_needed"]   = "yes";
    $_SESSION["wizard"]["installation_services"] = $installationServices;
    $_SESSION["wizard"]["technicians"]           = $installationServices;

    if ($hasStaff) {
        if ($userId) save_wizard_to_db($conn, $userId, $_SESSION["wizard"], 7);
        redirect_step(7);
    } else {
        // No staff — end of wizard
        if ($userId) save_wizard_to_db($conn, $userId, $_SESSION["wizard"], 6, 'completed');
        if (!$userId) {
            $_SESSION["signup_intent"] = "business";
            header("Location: auth/signup.php?next=" . urlencode("../service_jobs.php"));
            exit;
        }
        header("Location: service_jobs.php");
        exit;
    }
}

  if ($currentStep === 7) {
    file_put_contents(__DIR__ . "/wizard_debug.txt", print_r($_POST, true) . "\n---\n", FILE_APPEND);
    file_put_contents(__DIR__ . "/step7_debug.txt", 
        "userId=$userId hasEquipment=" . var_export($hasEquipment, true) . 
        " hasStaff=" . var_export($hasStaff, true) . 
        " services=" . json_encode($services) . "\n", FILE_APPEND);

    $staffingNeeded = $_POST["staffing_needed"] ?? "no";
    $staffRoles     = $_POST["staff_roles"] ?? [];
    if (!is_array($staffRoles)) $staffRoles = [];

    $_SESSION["wizard"]["staffing_needed"] = $staffingNeeded;
    $_SESSION["wizard"]["staff_roles"]     = $staffRoles;

    foreach (['waiter','chef','cashier','security','barista','busboy','host','kitchen_helper'] as $role) {
      $_SESSION["wizard"][$role . "_count"] = max(0, (int)($_POST[$role . "_count"] ?? 0));
    }

    $_SESSION["wizard"]["labor"] = [
      "barista"        => $_SESSION["wizard"]["barista_count"],
      "busboy"         => $_SESSION["wizard"]["busboy_count"],
      "host"           => $_SESSION["wizard"]["host_count"],
      "cashier"        => $_SESSION["wizard"]["cashier_count"],
      "waiter"         => $_SESSION["wizard"]["waiter_count"],
      "chef"           => $_SESSION["wizard"]["chef_count"],
      "cleaner"        => 0,
      "security"       => $_SESSION["wizard"]["security_count"],
      "kitchen_helper" => $_SESSION["wizard"]["kitchen_helper_count"],
    ];

    if ($userId) save_wizard_to_db($conn, $userId, $_SESSION["wizard"], 7, 'completed');

    if (!isset($_SESSION["user_id"])) {
    $_SESSION["signup_intent"] = "business";
    $nextUrl = $hasEquipment ? "../packages.php" : "../service_jobs.php";
    header("Location: auth/signup.php?next=" . urlencode($nextUrl));
    exit;
}

// Check if user already has a paid equipment order (adding staff to existing setup)
$alreadyPaid = false;
if ($userId) {
    $paidCheck = pg_query_params($conn,
        "SELECT 1 FROM orders WHERE business_user_id = $1 AND payment_status = 'paid' AND order_type = 'setup' AND order_total > 0 LIMIT 1",
        [$userId]);
    $alreadyPaid = ($paidCheck && pg_num_rows($paidCheck) > 0);
}

if ($alreadyPaid || !$hasEquipment) {
    header("Location: service_jobs.php");
} else {
    header("Location: packages.php");
}
exit;
  }
}


/* ---------- LOAD DATA ---------- */
$w = $_SESSION["wizard"] ?? [];
$businessName = trim($w["business_name"] ?? "");
$business = $w["business_type"] ?? "";
$indoorSeats  = (int)($w["indoor_seats"]  ?? 0);
$outdoorSeats = (int)($w["outdoor_seats"] ?? 0);
$indoorTables  = (int)($w["indoor_tables"]  ?? ($indoorSeats  > 0 ? (int)ceil($indoorSeats  / 4) : 0));
$outdoorTables = (int)($w["outdoor_tables"] ?? ($outdoorSeats > 0 ? (int)ceil($outdoorSeats / 4) : 0));
$restaurantType = $w["restaurant_type"] ?? "";
$areaSqm = (int)($w["area_sqm"] ?? 0);
$floorCount = (int)($w["floor_count"] ?? 1);
$modules = $w["modules"] ?? [];
$modules = array_values(array_filter($modules, function($m){
  return in_array($m, ["kitchen","pos","furniture","electronics","ac"], true);
}));
$moduleTiers = $w["module_tiers"] ?? [];
$budget = (int)($w["budget"] ?? 0);

$businessTypes = ["Restaurant","Café","Gym","Salon"];
$restaurantTypes = [
  "fast_food" => "Fast Food",
  "standard_dining" => "Casual Dining",
  "premium_dining" => "Premium Dining",
  "cloud_kitchen" => "Delivery Only"
];

$modulesList = [
  "kitchen"     => "Kitchen / Equipment",
  "pos"         => "POS & Operations",
  "furniture"   => "Dining Area",
  "electronics" => "Electronics & Systems",
  "ambience"    => "Ambience & Comfort"
];


/* ---------- GUARD: prevent jumping ahead ---------- */
if ($step === 2 && $business !== "Restaurant") {
    if ($hasEquipment) redirect_step(3);
    elseif ($hasInstall) redirect_step(4);
    elseif ($hasStaff) redirect_step(7);
    else redirect_step(3);
}

if ($step < 0 || $step > 7) redirect_step(0);

if ($step > 0 && $businessName === "") redirect_step(0);
if ($step > 1 && $business === "") redirect_step(1);
if ($step > 2 && $business === "Restaurant" && $restaurantType === "") redirect_step(2);
if ($hasEquipment && $step > 3 && $indoorSeats < 1) redirect_step(3);
if ($hasEquipment && $step === 4 && ($indoorTables < 1)) redirect_step(3);
if ($hasEquipment && $step > 5 && $budget <= 0) redirect_step(5);

$totalSteps = 9;
$progressPct = (int)round((($step + 1) / $totalSteps) * 100);
$stepTitles = [
  0 => "Business Name",
  1 => "Business Type",
  2 => "Restaurant Type",
  3 => "Size",
  4 => "Modules",
  5 => "Budget",
  6 => "Additional Services",
  7 => "Staffing",
  8 => "Logo",
];

function h($s){ return htmlspecialchars((string)$s, ENT_QUOTES, 'UTF-8'); }

?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>SetupForge - Setup</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
<link href="assets/style.css?v=10" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body>

<?php include "includes/navbar.php"; ?>

<main class="sf-setup">
  <div class="sf-setup-container">

    <!-- PROGRESS BAR -->
    <div class="sf-wiz-progress">
      <?php
  $allSteps = [0=>"Name", 1=>"Type", 2=>"Style", 3=>"Tables", 4=>"Space", 5=>"Budget", 6=>"Services", 7=>"Staff"];
  
  // Build display steps based on selected services
  $display = [0, 1, 2]; // always show name, type, restaurant type
  if ($hasEquipment) $display[] = 3; // tables
  if ($hasInstall)   $display[] = 4; // area
  if ($hasEquipment) $display[] = 5; // budget
  if ($hasInstall)   $display[] = 6; // installation
  if ($hasStaff)     $display[] = 7; // staff
  $display = array_unique($display);
  sort($display);

  foreach($display as $i => $s):
    $active = ($step === $s);
    $done   = ($step > $s);
?>
      <div class="sf-wiz-step <?= $done ? 'is-done' : ($active ? 'is-active' : '') ?>">
        <div class="sf-wiz-line"></div>
        <span class="sf-wiz-num">0<?= $i+1 ?></span>
        <span class="sf-wiz-label"><?= $allSteps[$s] ?></span>
      </div>
      <?php endforeach; ?>
    </div>

    <!-- TWO COLUMN GRID -->
    <div class="sf-wiz-grid">

      <!-- LEFT -->
      <section class="sf-card sf-no-box">
        <div class="sf-card-body">

<?php if ($step === 0): ?>

<section style="padding:40px 0;">
    <h1 class="sf-name-title" style="text-align:left; font-size:clamp(2rem,3vw,3rem); margin-bottom:8px;">What's your business name?</h1>
  <p class="sf-name-sub" style="text-align:left; margin-bottom:32px;">We'll use it to personalize your setup experience.</p>

  <form method="post" class="sf-name-form" style="align-items:flex-start;">
    <input type="hidden" name="step" value="0">

    <div class="sf-input-wrap">
      <input
        type="text"
        name="business_name"
        class="sf-input-lux"
        placeholder=" "
        value="<?= h($businessName) ?>"
        required
        autofocus
      >
      <label class="sf-input-label">Business name</label>
    </div>

    <button class="sf-name-btn" type="submit">
      Next <span aria-hidden="true">→</span>
    </button>
  </form>
</section>

<?php elseif ($step === 1): ?>

<section style="padding-top:0; margin-top:-60px;">
    <div class="sf-step1-layout mx-auto text-center">
          <h1 class="sf-name-title">What type of business are you building?</h1>
<p class="sf-name-sub" style="margin-bottom:12px; text-align:center; margin-left:auto; margin-right:auto;">This helps us tailor your setup experience.</p>

        <form method="post">
          <input type="hidden" name="step" value="1">

    <?php
      $entries = [];
      foreach ($businessTypes as $t) $entries[] = $t;
      $chunks = array_chunk($entries, 4);
      $carouselId = "sfBizCarousel";

      $videoMap = [
        "Restaurant" => "assets/Videos/RestaurantP.mp4",
        "Café"       => "assets/Videos/CafeP.mp4",
        "Salon"      => "assets/Videos/OfficeP.mp4",
        "Gym"        => "assets/Videos/GymP.mp4",
      ];
    ?>

    <div id="<?= h($carouselId) ?>" class="carousel slide sf-biz-carousel" data-bs-ride="false">
  <div class="carousel-inner">

    <?php foreach ($chunks as $i => $group): ?>
      <div class="carousel-item <?= $i === 0 ? "active" : "" ?>">
        <div class="sf-biz-landscape-grid">

          <?php foreach ($group as $t): ?>
            <?php
              $key = strtolower(preg_replace('/\W+/', '', $t));
              $id = "biz_" . $key;
              $video = $videoMap[$t] ?? "assets/Videos/placeholder.mp4";
              $icon = match($t){
                "Restaurant" => "bi-fork-knife",
                "Café"       => "bi-cup-hot",
                "Salon"     => "bi-building",
                "Gym"        => "bi-activity",
                default      => "bi-grid"
              };
            ?>

            <label class="sf-biz-card-landscape" for="<?= h($id) ?>">
              <input
                id="<?= h($id) ?>"
                type="radio"
                name="business_type"
                value="<?= h($t) ?>"
                required
                <?= ($business === $t) ? "checked" : "" ?>
              >

              <div class="sf-biz-card-shell">
                <video class="sf-biz-video" muted loop playsinline preload="metadata">
                  <source src="<?= h($video) ?>" type="video/mp4">
                </video>
                <div class="sf-biz-overlay"></div>
                <div class="sf-biz-copy">
                  <div class="sf-biz-copy-top">
                    <div class="sf-biz-title-land"><?= h($t) ?></div>
                  </div>
                </div>
                <div class="sf-biz-check-land" aria-hidden="true">
                  <i class="bi bi-check2"></i>
                </div>
                <?php if ($t === "Restaurant"): ?>
                  <div class="sf-biz-icon-custom">
                    <img src="assets/icons/fork.svg" alt="">
                    <img src="assets/icons/knifes.svg" alt="">
                  </div>
                <?php endif; ?>
                <?php if ($t === "Café"): ?>
                  <div class="sf-biz-cafe-icon" aria-hidden="true">
                    <img src="assets/icons/coffee-cup.svg" alt="">
                  </div>
                <?php endif; ?>
                <?php if ($t === "Gym"): ?>
                  <div class="sf-biz-gym-icon" aria-hidden="true">
                    <img src="assets/icons/dumbbell.svg" alt="">
                  </div>
                <?php endif; ?>
                <?php if ($t === "Salon"): ?>
                  <div class="sf-biz-office-icon" aria-hidden="true">
                    <img src="assets/icons/office.svg" alt="">
                  </div>
                <?php endif; ?>
              </div>
            </label>

          <?php endforeach; ?>

        </div>
      </div>
    <?php endforeach; ?>

  </div>
</div>
          <div class="sf-actions">
  <a class="sf-btn-main sf-btn-back" href="setup.php?step=0">← Back</a>
  <button class="sf-btn-main sf-btn-next" type="submit">Next →</button>
</div>
        </form>
      </div>
</section>

  <script>
    (function(){
      const root = document.querySelector('.sf-setup');
      if(!root) return;
      const cards = root.querySelectorAll('.sf-biz-card-landscape');
      cards.forEach(card => {
        const v = card.querySelector('.sf-biz-video');
        if(!v) return;
        card.addEventListener('mouseenter', () => { try { v.currentTime = 0; v.play(); } catch(e){} });
        card.addEventListener('mouseleave', () => { try { v.pause(); } catch(e){} });
      });
    })();
  </script>

<?php elseif ($step === 2): ?>

<section style="padding-top:0; margin-top:-60px;">
<h1 class="sf-name-title" style="margin-bottom:8px; text-align:center;">What type of restaurant are you building?</h1>
  <p class="sf-name-sub" style="margin-bottom:12px; text-align:center;">This helps us tailor the layout, equipment, and dining experience.</p>

        <form method="post">
          <input type="hidden" name="step" value="2">
          <?php
              $restaurantMeta = [
                "fast_food" => "",
                "standard_dining" => "",
                "premium_dining" => "",
                "cloud_kitchen" => ""
              ];
            ?>

           <div class="sf-restaurant-type-grid">
  <?php foreach($restaurantTypes as $key => $label): ?>
    <label class="sf-restaurant-card">
      <input
        type="radio"
        name="restaurant_type"
        value="<?= h($key) ?>"
        <?= $restaurantType === $key ? "checked" : "" ?>
        required
      >
      <div class="sf-restaurant-card-shell">
  <img src="assets/images/restaurant/<?= h($key) ?>.jpg" class="sf-restaurant-img" alt="">
  <div class="sf-restaurant-overlay"></div>
  <div class="sf-restaurant-content">
    <div class="sf-restaurant-card-title"><?= h($label) ?></div>
    <div class="sf-restaurant-card-sub"><?= h($restaurantMeta[$key] ?? "") ?></div>
  </div>
  <div class="sf-restaurant-card-check"><i class="bi bi-check2"></i></div>
</div>
    </label>
  <?php endforeach; ?>
</div>

          <div class="sf-actions">
  <a class="sf-btn-main sf-btn-back" href="setup.php?step=1">← Back</a>
  <button class="sf-btn-main sf-btn-next" type="submit">Next →</button>
</div>
        </form>
      </section>

<?php elseif ($step === 3): ?>

<div class="sf-step3-wrap">
  <h1 class="sf-name-title" style="text-align:left; margin-bottom:8px;">How many tables do you have?</h1>
  <p class="sf-name-sub" style="text-align:left; margin-bottom:28px;">Indoor and outdoor seating helps us size your furniture and equipment.</p>

  <form method="post" class="sf-step3-form">
    <input type="hidden" name="step" value="3">

    <div class="sf-slider-block">
      <div class="sf-slider-head">
        <span class="sf-slider-label">Indoor Tables</span>
        <div class="sf-slider-presets">
          <?php foreach ([5, 10, 15, 20] as $p): ?>
          <button type="button" class="sf-seat-preset" data-field="indoor_tables" data-val="<?= $p ?>"><?= $p ?></button>
          <?php endforeach; ?>
        </div>
        <span class="sf-slider-val" id="indoor-display"><?= $indoorTables > 0 ? h($indoorTables) : 5 ?></span>
      </div>
      <input type="range" class="sf-slider-range" id="indoor_range" min="1" max="50" step="1" value="<?= $indoorTables > 0 ? h($indoorTables) : 5 ?>">
      <input type="number" name="indoor_tables" id="indoor_tables" hidden min="1" value="<?= $indoorTables > 0 ? h($indoorTables) : 5 ?>" required>
    </div>

    <div class="sf-slider-block">
      <div class="sf-slider-head">
        <span class="sf-slider-label">Outdoor Tables</span>
        <div class="sf-slider-presets">
          <?php foreach ([0, 5, 10, 15] as $p): ?>
          <button type="button" class="sf-seat-preset" data-field="outdoor_tables" data-val="<?= $p ?>"><?= $p ?></button>
          <?php endforeach; ?>
        </div>
        <span class="sf-slider-val" id="outdoor-display"><?= h($outdoorTables) ?></span>
      </div>
      <input type="range" class="sf-slider-range" id="outdoor_range" min="0" max="50" step="1" value="<?= h($outdoorTables) ?>">
      <input type="number" name="outdoor_tables" id="outdoor_tables" hidden min="0" value="<?= h($outdoorTables) ?>">
    </div>

    <div class="sf-actions" style="margin-top:32px;">
      <a class="sf-btn-main sf-btn-back" href="setup.php?step=<?= $business === 'Restaurant' ? '2' : '1' ?>">← Back</a>
      <button class="sf-btn-main sf-btn-next" type="submit">Next →</button>
    </div>
  </form>
</div>

<script>
(function(){
  const inputs   = { indoor_tables: document.getElementById('indoor_tables'), outdoor_tables: document.getElementById('outdoor_tables') };
  const ranges   = { indoor_tables: document.getElementById('indoor_range'),  outdoor_tables: document.getElementById('outdoor_range') };
  const displays = { indoor_tables: document.getElementById('indoor-display'), outdoor_tables: document.getElementById('outdoor-display') };

  function syncPresets(){
    document.querySelectorAll('.sf-seat-preset').forEach(btn => {
      const field = btn.dataset.field;
      if (inputs[field]) btn.classList.toggle('is-active', parseInt(inputs[field].value) === parseInt(btn.dataset.val));
    });
  }

  Object.keys(ranges).forEach(field => {
    ranges[field].addEventListener('input', () => {
      inputs[field].value = ranges[field].value;
      displays[field].textContent = ranges[field].value;
      syncPresets();
    });
  });

  document.querySelectorAll('.sf-seat-preset').forEach(btn => {
    btn.addEventListener('click', () => {
      const field = btn.dataset.field;
      if (!inputs[field]) return;
      const min = field === 'indoor_tables' ? 1 : 0;
      const val = Math.max(min, parseInt(btn.dataset.val));
      inputs[field].value = val;
      ranges[field].value = val;
      displays[field].textContent = val;
      syncPresets();
    });
  });

  syncPresets();
})();
</script>

<?php elseif ($step === 4): ?>

<div class="sf-step3-wrap">
  <h1 class="sf-name-title" style="text-align:left; margin-bottom:8px;">What's your restaurant's area?</h1>
  <p class="sf-name-sub" style="text-align:left; margin-bottom:28px;">Indoor area helps us calculate how many AC units you need.</p>

  <form method="post" class="sf-step3-form">
    <input type="hidden" name="step" value="4">

    <div class="sf-slider-block">
      <div class="sf-slider-head">
        <span class="sf-slider-label">Indoor Area (m²) <small class="sf-slider-note">indoor only</small></span>
        <span class="sf-slider-val" id="area-display"><?= $areaSqm > 0 ? h($areaSqm) : 50 ?></span>
      </div>
      <input type="range" class="sf-slider-range" id="area_range" min="10" max="500" step="5" value="<?= $areaSqm > 0 ? h($areaSqm) : 50 ?>">
      <input type="number" name="area_sqm" id="area_sqm" hidden min="10" step="1" value="<?= $areaSqm > 0 ? h($areaSqm) : 50 ?>">
    </div>

    <div class="sf-slider-block sf-multifloor-block" style="border-top:1px solid #eee; padding-top:20px; margin-top:8px;">
      <label class="sf-multifloor-check-label" style="display:flex; align-items:center; gap:10px; cursor:pointer; font-size:15px; font-weight:500; color:#333;">
        <input type="checkbox" id="multifloor_chk" style="width:16px; height:16px; accent-color:#004cac; cursor:pointer;">
        <span>My restaurant has multiple floors</span>
      </label>

      <div id="multifloor_input" style="display:none; margin-top:20px;">
        <div class="sf-slider-block">
          <div class="sf-slider-head">
            <span class="sf-slider-label">Number of Floors</span>
            <div class="sf-slider-presets">
              <?php foreach ([2, 3, 4] as $p): ?>
              <button type="button" class="sf-seat-preset" data-field="floor_count" data-val="<?= $p ?>"><?= $p ?></button>
              <?php endforeach; ?>
            </div>
            <span class="sf-slider-val" id="floor-display">2</span>
          </div>
          <input type="range" class="sf-slider-range" id="floor_range" min="2" max="10" step="1" value="2">
          <input type="number" name="floor_count" id="floor_count" hidden min="2" value="2">
        </div>
      </div>
      <!-- when unchecked, submit floor_count = 1 -->
      <input type="hidden" name="floor_count" id="floor_count_default" value="1">
    </div>

    <div class="sf-actions" style="margin-top:32px;">
      <a class="sf-btn-main sf-btn-back" href="setup.php?step=<?= $hasEquipment ? '3' : '2' ?>">← Back</a>
      <button class="sf-btn-main sf-btn-next" type="submit">Next →</button>
    </div>
  </form>
</div>

<script>
(function(){
  const areaRange   = document.getElementById('area_range');
  const areaInput   = document.getElementById('area_sqm');
  const areaDisplay = document.getElementById('area-display');

  areaRange.addEventListener('input', () => {
    areaInput.value = areaRange.value;
    areaDisplay.textContent = areaRange.value;
  });

  const floorRange   = document.getElementById('floor_range');
  const floorInput   = document.getElementById('floor_count');
  const floorDisplay = document.getElementById('floor-display');
  const floorDefault = document.getElementById('floor_count_default');
  const chk          = document.getElementById('multifloor_chk');
  const floorBlock   = document.getElementById('multifloor_input');

  chk.addEventListener('change', () => {
    floorBlock.style.display = chk.checked ? 'block' : 'none';
    floorDefault.disabled = chk.checked;
    if (floorInput) floorInput.disabled = !chk.checked;
  });

  if (floorRange && floorInput) {
    floorRange.addEventListener('input', () => {
      floorInput.value = floorRange.value;
      floorDisplay.textContent = floorRange.value;
      document.querySelectorAll('.sf-seat-preset[data-field="floor_count"]').forEach(btn => {
        btn.classList.toggle('is-active', parseInt(floorRange.value) === parseInt(btn.dataset.val));
      });
    });
  }

  document.querySelectorAll('.sf-seat-preset[data-field="floor_count"]').forEach(btn => {
    btn.addEventListener('click', () => {
      const val = parseInt(btn.dataset.val);
      if (floorInput) floorInput.value = val;
      if (floorRange) floorRange.value = val;
      if (floorDisplay) floorDisplay.textContent = val;
      document.querySelectorAll('.sf-seat-preset[data-field="floor_count"]').forEach(b => {
        b.classList.toggle('is-active', parseInt(b.dataset.val) === val);
      });
    });
  });
})();
</script>

<?php elseif ($step === 6): ?>

<h1 class="sf-name-title" style="margin-bottom:8px; margin-top:-60px;">Installation &amp; Technical Setup</h1>
<p class="sf-name-sub" style="margin-bottom:20px;">Select the services you need. Certified local companies will come to your location and handle everything.</p>

<form method="post" id="sf6-form">
<input type="hidden" name="step" value="6">
<input type="hidden" name="installation_needed" value="yes">

<div class="sf6-card-grid">
  <div class="sf6-card">
    <div class="sf6-card-top"><div class="sf6-card-icon"><i class="bi bi-display"></i></div><div class="sf6-card-circle"><i class="bi bi-check2"></i></div></div>
    <div class="sf6-card-name">POS System</div>
    <div class="sf6-card-desc">Cash register &amp; payment terminal installation</div>
    <input type="checkbox" name="installation_services[]" value="pos" class="sf6-svc-chk" hidden>
  </div>
  <div class="sf6-card">
    <div class="sf6-card-top"><div class="sf6-card-icon"><i class="bi bi-lightning-charge"></i></div><div class="sf6-card-circle"><i class="bi bi-check2"></i></div></div>
    <div class="sf6-card-name">Electrical Wiring</div>
    <div class="sf6-card-desc">Outlets, lighting &amp; power setup</div>
    <input type="checkbox" name="installation_services[]" value="electrical" class="sf6-svc-chk" hidden>
  </div>
  <div class="sf6-card">
    <div class="sf6-card-top"><div class="sf6-card-icon"><i class="bi bi-wifi"></i></div><div class="sf6-card-circle"><i class="bi bi-check2"></i></div></div>
    <div class="sf6-card-name">Network &amp; WiFi</div>
    <div class="sf6-card-desc">Internet, router &amp; cabling setup</div>
    <input type="checkbox" name="installation_services[]" value="network" class="sf6-svc-chk" hidden>
  </div>
  <div class="sf6-card">
    <div class="sf6-card-top"><div class="sf6-card-icon"><i class="bi bi-thermometer-snow"></i></div><div class="sf6-card-circle"><i class="bi bi-check2"></i></div></div>
    <div class="sf6-card-name">AC Installation</div>
    <div class="sf6-card-desc">Air conditioning units &amp; ventilation</div>
    <input type="checkbox" name="installation_services[]" value="ac" class="sf6-svc-chk" hidden>
  </div>
  <div class="sf6-card">
    <div class="sf6-card-top"><div class="sf6-card-icon"><i class="bi bi-fire"></i></div><div class="sf6-card-circle"><i class="bi bi-check2"></i></div></div>
    <div class="sf6-card-name">Kitchen Setup</div>
    <div class="sf6-card-desc">Commercial kitchen equipment installation &amp; gas connections</div>
    <input type="checkbox" name="installation_services[]" value="kitchen" class="sf6-svc-chk" hidden>
  </div>
</div>

<p class="sf6-info-note"><i class="bi bi-building"></i> These services are fulfilled by verified local companies, not individual workers.</p>
<p class="sf6-foot-summary" id="sf6-count-text">0 services selected</p>
<div class="sf-actions" style="margin-top:24px;">
  <a class="sf-btn-main sf-btn-back" href="setup.php?step=<?= $hasEquipment ? '5' : '4' ?>">← Back</a>
  <button class="sf-btn-main sf-btn-next" type="submit">Next →</button>
</div>
</form>

<script>
(function(){
  document.querySelectorAll('.sf6-card').forEach(function(card){
    card.addEventListener('click', function(){
      card.classList.toggle('selected');
      card.querySelector('.sf6-svc-chk').checked = card.classList.contains('selected');
      var n = document.querySelectorAll('.sf6-card.selected').length;
      document.getElementById('sf6-count-text').textContent = n + ' service' + (n !== 1 ? 's' : '') + ' selected';
    });
  });
})();
</script>

<?php elseif ($step === 7): ?>

<h1 class="sf-name-title" style="margin-bottom:8px; margin-top:-60px;">Staffing</h1>
<p class="sf-name-sub" style="margin-bottom:20px;">Tell us how many staff you need for daily operations. Set any role to 0 to skip it.</p>

<form method="post" id="sf7-form">
<input type="hidden" name="step" value="7">
<input type="hidden" name="staffing_needed" value="yes">

<div class="sf7-staff-card">
  <?php
  $staffRoles = [
    'waiter'         => ['icon'=>'bi-person',        'label'=>'Waiters',         'role'=>'Front-of-house, serving tables'],
    'chef'           => ['icon'=>'bi-fire',           'label'=>'Chefs',           'role'=>'Kitchen staff & food preparation'],
    'cashier'        => ['icon'=>'bi-cash',           'label'=>'Cashiers',        'role'=>'Billing & payment handling'],
    'security'       => ['icon'=>'bi-shield',         'label'=>'Security',        'role'=>'Entrance & premises safety'],
    'barista'        => ['icon'=>'bi-cup-hot',        'label'=>'Baristas',        'role'=>'Coffee & beverages'],
    'busboy'         => ['icon'=>'bi-trash',          'label'=>'Table Cleaners',  'role'=>'Table clearing & resetting'],
    'host'           => ['icon'=>'bi-person-badge',   'label'=>'Reception Staff', 'role'=>'Greeting & seating customers'],
    'kitchen_helper' => ['icon'=>'bi-wrench',         'label'=>'Kitchen Helpers', 'role'=>'Dishwashing & prep support'],
  ];
  foreach ($staffRoles as $roleKey => $meta):
    $currentCount = (int)($w[$roleKey . '_count'] ?? 0);
  ?>
  <div class="sf6-staff-row" id="row_<?= $roleKey ?>">
    <div class="sf6-staff-left">
      <div class="sf6-staff-icon"><i class="bi <?= $meta['icon'] ?>"></i></div>
      <div class="sf6-staff-info">
        <span class="sf6-staff-name"><?= h($meta['label']) ?></span>
        <span class="sf6-staff-role"><?= h($meta['role']) ?></span>
      </div>
    </div>
    <div class="sf6-qty-ctrl">
      <button type="button" class="sf6-qty-btn" data-action="minus" data-role="<?= $roleKey ?>">−</button>
      <span class="sf6-qty-num" id="qty_<?= $roleKey ?>"><?= $currentCount ?></span>
      <button type="button" class="sf6-qty-btn" data-action="plus" data-role="<?= $roleKey ?>">+</button>
    </div>
    <input type="number" name="<?= $roleKey ?>_count" id="input_<?= $roleKey ?>" value="<?= $currentCount ?>" min="0" hidden>
    <input type="checkbox" name="staff_roles[]" value="<?= $roleKey ?>" id="chk_<?= $roleKey ?>" hidden <?= $currentCount > 0 ? 'checked' : '' ?>>
  </div>
  <?php endforeach; ?>
</div>

<p class="sf6-foot-summary" id="sf7-count-text">0 staff total</p>
<div class="sf-actions" style="margin-top:24px;">
  <a class="sf-btn-main sf-btn-back" href="setup.php?step=<?= $hasInstall ? '6' : ($hasEquipment ? '5' : '2') ?>">← Back</a>
  <button class="sf-btn-main sf-btn-next" type="submit">Finish →</button>
</div>
</form>

<script>
(function(){
  var counts = {};
  <?php foreach (array_keys($staffRoles) as $rk): ?>
  counts['<?= $rk ?>'] = parseInt(document.getElementById('input_<?= $rk ?>').value) || 0;
  <?php endforeach; ?>

  function updateDisplay(role) {
    document.getElementById('qty_' + role).textContent = counts[role];
    document.getElementById('input_' + role).value = counts[role];
    document.getElementById('chk_' + role).checked = counts[role] > 0;
    var row = document.getElementById('row_' + role);
    if (row) row.classList.toggle('sf6-staff-row--active', counts[role] > 0);
    var total = Object.values(counts).reduce(function(s,k){ return s + k; }, 0);
    document.getElementById('sf7-count-text').textContent = total + ' staff total';
    var summaryEl = document.getElementById('summary-staff-count');
    if (summaryEl) summaryEl.textContent = total > 0 ? total + ' staff' : '—';
  }

  Object.keys(counts).forEach(function(role){ updateDisplay(role); });

  document.querySelectorAll('#sf7-form .sf6-qty-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var role = btn.dataset.role;
      if (btn.dataset.action === 'plus') counts[role]++;
      else if (counts[role] > 0) counts[role]--;
      updateDisplay(role);
    });
  });
})();
</script>

<?php elseif ($step === 5): ?>

<h1 class="sf-name-title" style="margin-bottom:8px; margin-top:-60px;">What's your budget?</h1>
<p class="sf-name-sub" style="margin-bottom:20px; text-align:center;">This helps us recommend the right products, brands, and setup tier for your business.</p>

<form method="post" class="sf-step5-form">
  <input type="hidden" name="step" value="5">
  <input type="hidden" name="budget" id="budget-hidden" value="<?= $budget ? h((string)$budget) : "" ?>" required>

  <?php
    $budgetOptions = [
  ["label" => "Under 600,000 EGP",          "sub" => "Small / street food",  "value" => 400000],
  ["label" => "600,000 - 2,000,000 EGP",    "sub" => "Casual dining",        "value" => 1200000],
  ["label" => "2,000,000 - 3,500,000 EGP",  "sub" => "Full fit-out",         "value" => 2750000],
  ["label" => "3,500,000+ EGP",             "sub" => "Premium restaurant",   "value" => 4500000],
];
  ?>

  <div class="sf-budget-grid">
    <?php foreach ($budgetOptions as $opt): ?>
      <button type="button" class="sf-budget-card <?= $budget === $opt["value"] ? "is-selected" : "" ?>" data-value="<?= $opt["value"] ?>">
        <div class="sf-budget-label"><?= h($opt["label"]) ?></div>
        <div class="sf-budget-sub"><?= h($opt["sub"]) ?></div>
      </button>
    <?php endforeach; ?>
  </div>

  <div class="sf-actions">
    <a class="sf-btn-main sf-btn-back" href="setup.php?step=<?= $hasInstall ? '4' : '3' ?>">&#8592; Back</a>
    <button class="sf-btn-main sf-btn-next" type="submit">Next &#8594;</button>
  </div>
</form>

<script>
(function(){
  var cards = document.querySelectorAll(".sf-budget-card");
  var hidden = document.getElementById("budget-hidden");
  cards.forEach(function(card){
    card.addEventListener("click", function(){
      cards.forEach(function(c){ c.classList.remove("is-selected"); });
      card.classList.add("is-selected");
      hidden.value = card.dataset.value;
    });
  });
})();
</script>
<?php endif; ?>

</div>
      </section>

      <!-- RIGHT: Summary Panel -->
      <aside class="sf-wiz-summary">
        <div class="sf-wiz-summary-inner">
          <div class="sf-wiz-summary-label">SETUP SUMMARY <span><?= $step ?>/7</span></div>
          <div class="sf-wiz-summary-row">
            <span>Business name</span>
            <strong><?= $businessName !== "" ? h($businessName) : "—" ?></strong>
          </div>
          <div class="sf-wiz-summary-row">
            <span>Type</span>
            <strong><?= $business !== "" ? h($business) : "—" ?></strong>
          </div>
          <div class="sf-wiz-summary-row">
            <span>Style</span>
            <strong><?= $restaurantType !== "" ? h($restaurantTypes[$restaurantType] ?? $restaurantType) : "—" ?></strong>
          </div>
          <?php if ($hasEquipment): ?>
<div class="sf-wiz-summary-row">
    <span>Seats</span>
    <strong><?= ($indoorSeats + $outdoorSeats) > 0 ? ($indoorSeats + $outdoorSeats) : "—" ?></strong>
</div>
<div class="sf-wiz-summary-row">
    <span>Budget ceiling</span>
    <strong><?= $budget > 0 ? number_format($budget) . " EGP" : "—" ?></strong>
</div>
<div class="sf-wiz-summary-row">
    <span>Modules</span>
    <strong><?= count($modules) > 0 ? implode(", ", $modules) : "—" ?></strong>
</div>
<?php endif; ?>

<?php if ($hasStaff): ?>
<div class="sf-wiz-summary-row">
    <span>Staff</span>
    <strong id="summary-staff-count"><?php
        $totalStaff = 0;
        foreach (['waiter','chef','cashier','security','barista','busboy','host','kitchen_helper'] as $role)
            $totalStaff += (int)($w[$role . '_count'] ?? 0);
        echo $totalStaff > 0 ? $totalStaff . ' staff' : '—';
    ?></strong>
</div>
<?php endif; ?>

<?php if ($hasInstall): ?>
<div class="sf-wiz-summary-row">
    <span>Area</span>
    <strong><?= $areaSqm > 0 ? $areaSqm . ' m²' : '—' ?></strong>
</div>
<?php endif; ?>

<?php if ($hasFinishing): ?>
<div class="sf-wiz-summary-row">
    <span>Finishing</span>
    <strong>Requested</strong>
</div>
<?php endif; ?>

<?php if ($hasAdvertising): ?>
<div class="sf-wiz-summary-row">
    <span>Advertising</span>
    <strong>Requested</strong>
</div>
<?php endif; ?>
          <div class="sf-wiz-summary-note">
            <i class="bi bi-info-circle"></i> We score recommendations live — no submit button hidden away
          </div>
        </div>
      </aside>

    </div><!-- end sf-wiz-grid -->
  </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/site.js"></script>
</body>
</html>