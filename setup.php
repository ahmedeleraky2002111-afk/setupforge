<?php
// setup.php
session_start();

$step = isset($_GET["step"]) ? (int)$_GET["step"] : 0;
function redirect_step($n){
  header("Location: setup.php?step=".$n);
  exit;
}

/* ---------- HANDLE FORM SUBMIT ---------- */
if ($_SERVER["REQUEST_METHOD"] === "POST") {

  $currentStep = (int)($_POST["step"] ?? 1);
  // ✅ ADD THIS HERE
  if ($currentStep === 0) {

    $_SESSION["wizard"]["business_name"] = trim($_POST["business_name"] ?? "");

    redirect_step(1);
  }
  if ($currentStep === 1) {

  $selectedBusiness = $_POST["business_type"] ?? null;
  $_SESSION["wizard"]["business_type"] = $selectedBusiness;

  if ($selectedBusiness === "Restaurant") {
    redirect_step(2);
  } else {
    unset($_SESSION["wizard"]["restaurant_type"]);
    redirect_step(3);
  }
}

  if ($currentStep === 2) {

  $_SESSION["wizard"]["restaurant_type"] =
    $_POST["restaurant_type"] ?? "standard_dining";

  redirect_step(3);
}
  if ($currentStep === 3) {

  $_SESSION["wizard"]["indoor_seats"]  = max(1, (int)($_POST["indoor_seats"]  ?? 1));
  $_SESSION["wizard"]["outdoor_seats"] = max(0, (int)($_POST["outdoor_seats"] ?? 0));
    $_SESSION["wizard"]["area_sqm"]      = max(10, (int)($_POST["area_sqm"] ?? 50));


  $rt = $_SESSION["wizard"]["restaurant_type"] ?? "standard_dining";
  if ($rt === "cloud_kitchen") {
    $_SESSION["wizard"]["modules"] = ["kitchen","pos"];
  } elseif ($rt === "premium_dining") {
    $_SESSION["wizard"]["modules"] = ["kitchen","pos","furniture","electronics","ambience"];
  } else {
    $_SESSION["wizard"]["modules"] = ["kitchen","pos","furniture","electronics"];
  }

  redirect_step(5);
}

if ($currentStep === 4) {
  redirect_step(5);
}

if ($currentStep === 5) {

  $budget = (int)preg_replace("/[^\d]/", "", $_POST["budget"] ?? "0");
  $_SESSION["wizard"]["budget"] = $budget;

  redirect_step(6);
}

if ($currentStep === 6) {

  $installationNeeded   = $_POST["installation_needed"] ?? "no";
  $installationServices = $_POST["installation_services"] ?? [];

  $staffingNeeded = $_POST["staffing_needed"] ?? "no";
  $staffRoles     = $_POST["staff_roles"] ?? [];

  if (!is_array($installationServices)) $installationServices = [];
  if (!is_array($staffRoles)) $staffRoles = [];

  $_SESSION["wizard"]["installation_needed"]   = $installationNeeded;
  $_SESSION["wizard"]["installation_services"] = $installationServices;
  $_SESSION["wizard"]["staffing_needed"]       = $staffingNeeded;
  $_SESSION["wizard"]["staff_roles"]           = $staffRoles;

  /* keep old keys alive so old job creation logic still works */
  $_SESSION["wizard"]["technicians"] = ($installationNeeded === "yes")
    ? $installationServices
    : [];

  $_SESSION["wizard"]["labor"] = [
    "barista" => in_array("barista", $staffRoles, true) ? 1 : 0,
    "cashier" => in_array("cashier", $staffRoles, true) ? 1 : 0,
    "waiter"  => in_array("waiter",  $staffRoles, true) ? 1 : 0,
    "chef"    => in_array("chef",    $staffRoles, true) ? 1 : 0,
    "cleaner" => in_array("cleaner", $staffRoles, true) ? 1 : 0
  ];
  $_SESSION["wizard"]["salary_amount"]     = (int)($_POST["salary_amount"] ?? 0);
  $_SESSION["wizard"]["compensation_type"] = trim($_POST["compensation_type"] ?? "monthly");

  redirect_step(7);

}

if ($currentStep === 7) {

  $staffingNeeded = $_POST["staffing_needed"] ?? "no";
  $staffRoles     = $_POST["staff_roles"] ?? [];
  if (!is_array($staffRoles)) $staffRoles = [];

  $_SESSION["wizard"]["staffing_needed"] = $staffingNeeded;
  $_SESSION["wizard"]["staff_roles"]     = $staffRoles;

  $_SESSION["wizard"]["waiter_count"]         = max(0, (int)($_POST["waiter_count"]         ?? 0));
  $_SESSION["wizard"]["chef_count"]           = max(0, (int)($_POST["chef_count"]           ?? 0));
  $_SESSION["wizard"]["cashier_count"]        = max(0, (int)($_POST["cashier_count"]        ?? 0));
  $_SESSION["wizard"]["security_count"]       = max(0, (int)($_POST["security_count"]       ?? 0));
  $_SESSION["wizard"]["kitchen_helper_count"] = max(0, (int)($_POST["kitchen_helper_count"] ?? 0));

  $_SESSION["wizard"]["labor"] = [
    "barista"        => 0,
    "cashier"        => $_SESSION["wizard"]["cashier_count"],
    "waiter"         => $_SESSION["wizard"]["waiter_count"],
    "chef"           => $_SESSION["wizard"]["chef_count"],
    "cleaner"        => 0,
    "security"       => $_SESSION["wizard"]["security_count"],
    "kitchen_helper" => $_SESSION["wizard"]["kitchen_helper_count"],
  ];

  if (!isset($_SESSION["user_id"])) {
    $_SESSION["signup_intent"] = "business";
    header("Location: auth/signup.php?next=" . urlencode("http://localhost/setupforge/packages.php"));
    exit;
  }

  header("Location: packages.php");
  exit;

}
}


/* ---------- LOAD DATA ---------- */
$w = $_SESSION["wizard"] ?? [];
$businessName = trim($w["business_name"] ?? "");
$business = $w["business_type"] ?? "";
$indoorSeats  = (int)($w["indoor_seats"]  ?? 0);
$outdoorSeats = (int)($w["outdoor_seats"] ?? 0);
$restaurantType = $w["restaurant_type"] ?? "";
$areaSqm = (int)($w["area_sqm"] ?? 0);
$modules = $w["modules"] ?? [];
$modules = array_values(array_filter($modules, function($m){
  return in_array($m, ["kitchen","pos","furniture","electronics","ambience"], true);
})); 
$moduleTiers = $w["module_tiers"] ?? []; // ✅ per-module tiers
$budget = (int)($w["budget"] ?? 0);

$businessTypes = ["Restaurant","Café","Office","Gym","Salon"];
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
if ($step === 2 && $business !== "Restaurant") redirect_step(3);
if ($step < 0 || $step > 7) redirect_step(0);

if ($step > 0 && $businessName === "") redirect_step(0);
if ($step > 1 && $business === "") redirect_step(1);
if ($step > 2 && $business === "Restaurant" && $restaurantType === "") redirect_step(2);
if ($step > 3 && $indoorSeats < 1) redirect_step(3);

if ($step > 5 && $budget <= 0) redirect_step(5);
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
  <link href="assets/style.css?v=9" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>

<body>

<!-- NAVBAR (same as home, so brand stays consistent) -->
<!-- NAVBAR -->
<?php include "includes/navbar.php"; ?>

<main class="sf-setup">
  <div class="sf-setup-container">

    <div class="sf-setup-grid">
      <!-- LEFT: Main wizard card -->
      <section class="sf-card sf-no-box">
        <div class="sf-card-body">

<?php if ($step === 0): ?>

  <section class="sf-name-step">
  <div class="sf-name-layout">

    <div class="sf-name-copy">
      <h1 class="sf-name-title">What’s your business name?</h1>
      <p class="sf-name-sub">We’ll use it to personalize your setup experience.</p>

      <form method="post" class="sf-name-form">
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
    </div>

    <div class="sf-name-visual">
      <div class="sf-building">
  <img src="assets/images/building-default.png" alt="">

  <!-- Overlay text -->
  <div class="sf-building-sign" id="buildingSign">
    Your Business
  </div>
</div>
    </div>

  </div>
</section>

<?php elseif ($step === 1): ?>

<section class="sf-name-step">
<div class="sf-name-layout sf-step1-layout-wrap">
<div class="sf-name-copy sf-step1-copy">
  <div class="sf-step1-layout mx-auto text-center">
          <h1 class="sf-name-title">What type of business are you building?</h1>
        <p class="sf-name-sub">This helps us tailor your setup experience.</p>

        <form method="post">
          <input type="hidden" name="step" value="1">

    <?php
      // 4 business types per slide
      $entries = [];
      foreach ($businessTypes as $t) $entries[] = $t;
      $chunks = array_chunk($entries, 4);
      $carouselId = "sfBizCarousel";

      // ✅ Put your real filenames here (match EXACTLY)
      $videoMap = [
        "Restaurant" => "assets/videos/RestaurantP.mp4",
        "Café"       => "assets/videos/CafeP.mp4",
        "Office"     => "assets/videos/OfficeP.mp4",
        "Salon"      => "assets/videos/SalonP.mp4",
        "Gym"        => "assets/videos/GymP.mp4",
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

              $video = $videoMap[$t] ?? "assets/videos/placeholder.mp4";

              $subtitle = match($t){
                "Restaurant" => "Full kitchen + seating setup",
                "Café"       => "Coffee bar + cozy layout",
                "Office"     => "Furniture + tech + productivity",
                "Salon"      => "Stations + mirrors + finishing",
                "Gym"        => "Equipment + flooring + infra",
                default      => "Business setup preview"
              };

              $icon = match($t){
                "Restaurant" => "bi-fork-knife",
                "Café"       => "bi-cup-hot",
                "Office"     => "bi-building",
                "Salon"      => "bi-scissors",
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
<?php if ($t === "Office"): ?>
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
    </div>

    <div class="sf-name-visual">
      <!-- empty for now -->
    </div>

  </div>
</section>

  <!-- Hover-play JS (scoped) -->
  <script>
    (function(){
      const root = document.querySelector('.sf-setup');
      if(!root) return;

      const cards = root.querySelectorAll('.sf-biz-card-landscape');
      cards.forEach(card => {
        const v = card.querySelector('.sf-biz-video');
        if(!v) return;

        card.addEventListener('mouseenter', () => {
          try { v.currentTime = 0; v.play(); } catch(e){}
        });

        card.addEventListener('mouseleave', () => {
          try { v.pause(); } catch(e){}
        });
      });
    })();
  </script>
  
  <?php elseif ($step === 2): ?>

<section class="sf-name-step">
 <div class="sf-name-layout sf-step2-layout-wrap">
<div class="sf-name-copy sf-step2-copy">
      <div class="sf-step2-layout">
        <h1 class="sf-name-title">What type of restaurant are you building?</h1>
        <p class="sf-name-sub">This helps us tailor the layout, equipment, and dining experience.</p>

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

  <img 
    src="assets/images/restaurant/<?= h($key) ?>.jpg" 
    class="sf-restaurant-img"
    alt=""
  >

  <div class="sf-restaurant-overlay"></div>

  <div class="sf-restaurant-content">
    <div class="sf-restaurant-card-title"><?= h($label) ?></div>
    <div class="sf-restaurant-card-sub"><?= h($restaurantMeta[$key] ?? "") ?></div>
  </div>

  <div class="sf-restaurant-card-check">
    <i class="bi bi-check2"></i>
  </div>

</div>

    </label>
  <?php endforeach; ?>
</div>

          <div class="sf-actions">
  <a class="sf-btn-main sf-btn-back" href="setup.php?step=1">← Back</a>
  <button class="sf-btn-main sf-btn-next" type="submit">Next →</button>
</div>
        </form>
      </div>
    </div>

    <div class="sf-name-visual">
      <!-- empty for now -->
    </div>

  </div>
</section>

  <?php elseif ($step === 3): ?>

<div class="sf-step3-wrap">
  <h1 class="sf-step3-title">How many seats?</h1>
  <p class="sf-step3-sub">We'll use this to recommend the right furniture, equipment, and layout for your space.</p>

  <form method="post" class="sf-step3-form">
    <input type="hidden" name="step" value="3">

    <div class="sf-seat-cards-row">

      <!-- Indoor -->
      <div class="sf-seat-card <?= ($indoorSeats > 0) ? 'is-active' : '' ?>" id="indoor-card">
        <div class="sf-seat-card-head">
          <div class="sf-seat-card-icon indoor">
            <i class="bi bi-house-door-fill"></i>
          </div>
          <div class="sf-seat-card-title">Indoor</div>
          <div class="sf-seat-card-hint">Dining room, bar &amp; lounge</div>
        </div>

        <div class="sf-seat-stepper">
          <button type="button" class="sf-step-btn" data-field="indoor_seats" data-delta="-1">−</button>
          <input type="number" class="sf-seat-num-input" name="indoor_seats" id="indoor_seats"
                 min="1" value="<?= $indoorSeats > 0 ? h($indoorSeats) : 20 ?>" required>
          <button type="button" class="sf-step-btn" data-field="indoor_seats" data-delta="1">+</button>
        </div>

        <div class="sf-seat-presets">
          <?php foreach ([10, 20, 40, 60, 80, 100] as $p): ?>
          <button type="button" class="sf-seat-preset" data-field="indoor_seats" data-val="<?= $p ?>"><?= $p ?></button>
          <?php endforeach; ?>
        </div>
      </div>

      <!-- Outdoor -->
      <div class="sf-seat-card <?= ($outdoorSeats > 0) ? 'is-active' : '' ?>" id="outdoor-card">
        <div class="sf-seat-card-head">
          <div class="sf-seat-card-icon outdoor">
            <i class="bi bi-sun-fill"></i>
          </div>
          <div class="sf-seat-card-title">Outdoor</div>
          <div class="sf-seat-card-hint">Terrace, garden &amp; rooftop</div>
        </div>

        <div class="sf-seat-stepper">
          <button type="button" class="sf-step-btn" data-field="outdoor_seats" data-delta="-1">−</button>
          <input type="number" class="sf-seat-num-input" name="outdoor_seats" id="outdoor_seats"
                 min="0" value="<?= h($outdoorSeats) ?>">
          <button type="button" class="sf-step-btn" data-field="outdoor_seats" data-delta="1">+</button>
        </div>

        <div class="sf-seat-presets">
          <?php foreach ([0, 10, 20, 30, 40, 60] as $p): ?>
          <button type="button" class="sf-seat-preset" data-field="outdoor_seats" data-val="<?= $p ?>"><?= $p ?></button>
          <?php endforeach; ?>
        </div>
      </div>

    </div>
    <!-- Area -->
    <div class="sf-seat-cards-row" style="margin-top:1rem">
      <div class="sf-seat-card <?= ($areaSqm > 0) ? 'is-active' : '' ?>" id="area-card">
        <div class="sf-seat-card-head">
          <div class="sf-seat-card-icon indoor">
            <i class="bi bi-arrows-angle-expand"></i>
          </div>
          <div class="sf-seat-card-title">Restaurant Area</div>
          <div class="sf-seat-card-hint">Total floor space in m²</div>
        </div>
        <div class="sf-seat-stepper">
          <button type="button" class="sf-step-btn" data-field="area_sqm" data-delta="-10">−</button>
          <input type="number" class="sf-seat-num-input" name="area_sqm" id="area_sqm"
                 min="10" step="10" value="<?= $areaSqm > 0 ? h($areaSqm) : 50 ?>">
          <button type="button" class="sf-step-btn" data-field="area_sqm" data-delta="10">+</button>
        </div>
        <div class="sf-seat-presets">
          <?php foreach ([30, 50, 80, 120, 200, 300] as $p): ?>
          <button type="button" class="sf-seat-preset" data-field="area_sqm" data-val="<?= $p ?>"><?= $p ?>m²</button>
          <?php endforeach; ?>
        </div>
      </div>
    </div>

    <!-- Total bar -->
    <div class="sf-seat-total-bar">
      <div class="sf-seat-total-number" id="total-seats"><?= max(1, $indoorSeats > 0 ? $indoorSeats : 20) + $outdoorSeats ?></div>
      <div class="sf-seat-total-text">total seats</div>
      <div class="sf-seat-total-size" id="size-label"></div>
    </div>

    <div class="sf-actions">
      <a class="sf-btn-main sf-btn-back" href="setup.php?step=<?= $business === 'Restaurant' ? '2' : '1' ?>">← Back</a>
      <button class="sf-btn-main sf-btn-next" type="submit">Next →</button>
    </div>
  </form>
</div>

<script>
(function(){
  const inputs = {
    indoor_seats:  document.getElementById('indoor_seats'),
    outdoor_seats: document.getElementById('outdoor_seats'),
    area_sqm:      document.getElementById('area_sqm'),
  };
  const totalEl = document.getElementById('total-seats');
  const sizeEl  = document.getElementById('size-label');

function getMin(field){
    if (field === 'indoor_seats') return 1;
    if (field === 'area_sqm') return 10;
    return 0;
  }
  function updateTotal(){
    const indoor  = parseInt(inputs.indoor_seats.value)  || 0;
    const outdoor = parseInt(inputs.outdoor_seats.value) || 0;
    const total   = indoor + outdoor;
    totalEl.textContent = total;

    if      (total <= 20)  sizeEl.textContent = 'Small café-style space';
    else if (total <= 50)  sizeEl.textContent = 'Mid-size restaurant';
    else if (total <= 100) sizeEl.textContent = 'Large restaurant';
    else                   sizeEl.textContent = 'Venue-scale setup';

    document.querySelectorAll('.sf-seat-preset').forEach(btn => {
      btn.classList.toggle('is-active', parseInt(inputs[btn.dataset.field].value) === parseInt(btn.dataset.val));
    });

    document.getElementById('indoor-card').classList.toggle('is-active',  indoor  > 0);
    document.getElementById('outdoor-card').classList.toggle('is-active', outdoor > 0);
  }

  document.querySelectorAll('.sf-step-btn').forEach(btn => {
    btn.addEventListener('click', () => {
      const input = inputs[btn.dataset.field];
      const min   = getMin(btn.dataset.field);
      input.value = Math.max(min, (parseInt(input.value) || 0) + parseInt(btn.dataset.delta));
      updateTotal();
    });
  });

  document.querySelectorAll('.sf-seat-preset').forEach(btn => {
    btn.addEventListener('click', () => {
      inputs[btn.dataset.field].value = Math.max(getMin(btn.dataset.field), parseInt(btn.dataset.val));
      updateTotal();
    });
  });

  Object.entries(inputs).forEach(([field, input]) => {
    input.addEventListener('input', updateTotal);
    input.addEventListener('change', () => {
      if ((parseInt(input.value) || 0) < getMin(field)) input.value = getMin(field);
      updateTotal();
    });
  });

  updateTotal();
})();
</script>

<?php elseif ($step === 6): ?>

<div class="sf6-step-label">Step 6 of 7</div>
<h1 class="sf-step-title">Installation &amp; Technical Setup</h1>
<p class="sf-step-sub">Select the services you need. Certified local companies will come to your location and handle everything.</p>

<form method="post" id="sf6-form">
<input type="hidden" name="step" value="6">
<input type="hidden" name="installation_needed" value="yes">

<div class="sf6-card-grid">

  <div class="sf6-card">
    <div class="sf6-card-top">
      <div class="sf6-card-icon"><i class="bi bi-display"></i></div>
      <div class="sf6-card-circle"><i class="bi bi-check2"></i></div>
    </div>
    <div class="sf6-card-name">POS System</div>
    <div class="sf6-card-desc">Cash register &amp; payment terminal installation</div>
    <input type="checkbox" name="installation_services[]" value="pos" class="sf6-svc-chk" hidden>
  </div>

  <div class="sf6-card">
    <div class="sf6-card-top">
      <div class="sf6-card-icon"><i class="bi bi-lightning-charge"></i></div>
      <div class="sf6-card-circle"><i class="bi bi-check2"></i></div>
    </div>
    <div class="sf6-card-name">Electrical Wiring</div>
    <div class="sf6-card-desc">Outlets, lighting &amp; power setup</div>
    <input type="checkbox" name="installation_services[]" value="electrical" class="sf6-svc-chk" hidden>
  </div>

  <div class="sf6-card">
    <div class="sf6-card-top">
      <div class="sf6-card-icon"><i class="bi bi-wifi"></i></div>
      <div class="sf6-card-circle"><i class="bi bi-check2"></i></div>
    </div>
    <div class="sf6-card-name">Network &amp; WiFi</div>
    <div class="sf6-card-desc">Internet, router &amp; cabling setup</div>
    <input type="checkbox" name="installation_services[]" value="network" class="sf6-svc-chk" hidden>
  </div>

  <div class="sf6-card">
    <div class="sf6-card-top">
      <div class="sf6-card-icon"><i class="bi bi-thermometer-snow"></i></div>
      <div class="sf6-card-circle"><i class="bi bi-check2"></i></div>
    </div>
    <div class="sf6-card-name">AC Installation</div>
    <div class="sf6-card-desc">Air conditioning units &amp; ventilation</div>
    <input type="checkbox" name="installation_services[]" value="ac" class="sf6-svc-chk" hidden>
  </div>

  <div class="sf6-card">
    <div class="sf6-card-top">
      <div class="sf6-card-icon"><i class="bi bi-fire"></i></div>
      <div class="sf6-card-circle"><i class="bi bi-check2"></i></div>
    </div>
    <div class="sf6-card-name">Kitchen Setup</div>
    <div class="sf6-card-desc">Commercial kitchen equipment installation &amp; gas connections</div>
    <input type="checkbox" name="installation_services[]" value="kitchen" class="sf6-svc-chk" hidden>
  </div>

</div>

<p class="sf6-info-note"><i class="bi bi-building"></i> These services are fulfilled by verified local companies, not individual workers.</p>

<p class="sf6-foot-summary" id="sf6-count-text">0 services selected</p>
<div class="sf6-footer-bar">
  <a class="sf-btn-main sf-btn-back" href="setup.php?step=5">← Back</a>
  <button class="sf-btn-main sf-btn-next" type="submit">Continue →</button>
</div>

</form>

<script>
(function(){
  document.querySelectorAll('.sf6-card').forEach(function(card){
    card.addEventListener('click', function(){
      card.classList.toggle('selected');
      card.querySelector('.sf6-svc-chk').checked = card.classList.contains('selected');
      var n = document.querySelectorAll('.sf6-card.selected').length;
      document.getElementById('sf6-count-text').textContent =
        n + ' service' + (n !== 1 ? 's' : '') + ' selected';
    });
  });
})();
</script>

<?php elseif ($step === 7): ?>

<div class="sf6-step-label">Step 7 of 7</div>
<h1 class="sf-step-title">Staffing</h1>
<p class="sf-step-sub">Tell us how many staff you need for daily operations. Set any role to 0 to skip it.</p>

<form method="post" id="sf7-form">
<input type="hidden" name="step" value="7">
<input type="hidden" name="staffing_needed" value="yes">

<div class="sf7-staff-card">
  <div class="sf6-staff-row" id="row_waiter">
    <div class="sf6-staff-left">
      <div class="sf6-staff-icon"><i class="bi bi-person"></i></div>
      <div class="sf6-staff-info">
        <span class="sf6-staff-name">Waiters</span>
        <span class="sf6-staff-role">Front-of-house, serving tables</span>
      </div>
    </div>
    <div class="sf6-qty-ctrl">
      <button type="button" class="sf6-qty-btn" data-action="minus" data-role="waiter">−</button>
      <span class="sf6-qty-num" id="qty_waiter">0</span>
      <button type="button" class="sf6-qty-btn" data-action="plus" data-role="waiter">+</button>
    </div>
    <input type="number" name="waiter_count" id="input_waiter" value="0" min="0" hidden>
    <input type="checkbox" name="staff_roles[]" value="waiter" id="chk_waiter" hidden>
  </div>
  <div class="sf6-staff-row" id="row_chef">
    <div class="sf6-staff-left">
      <div class="sf6-staff-icon"><i class="bi bi-fire"></i></div>
      <div class="sf6-staff-info">
        <span class="sf6-staff-name">Chefs</span>
        <span class="sf6-staff-role">Kitchen staff &amp; food preparation</span>
      </div>
    </div>
    <div class="sf6-qty-ctrl">
      <button type="button" class="sf6-qty-btn" data-action="minus" data-role="chef">−</button>
      <span class="sf6-qty-num" id="qty_chef">0</span>
      <button type="button" class="sf6-qty-btn" data-action="plus" data-role="chef">+</button>
    </div>
    <input type="number" name="chef_count" id="input_chef" value="0" min="0" hidden>
    <input type="checkbox" name="staff_roles[]" value="chef" id="chk_chef" hidden>
  </div>
  <div class="sf6-staff-row" id="row_cashier">
    <div class="sf6-staff-left">
      <div class="sf6-staff-icon"><i class="bi bi-cash"></i></div>
      <div class="sf6-staff-info">
        <span class="sf6-staff-name">Cashiers</span>
        <span class="sf6-staff-role">Billing &amp; payment handling</span>
      </div>
    </div>
    <div class="sf6-qty-ctrl">
      <button type="button" class="sf6-qty-btn" data-action="minus" data-role="cashier">−</button>
      <span class="sf6-qty-num" id="qty_cashier">0</span>
      <button type="button" class="sf6-qty-btn" data-action="plus" data-role="cashier">+</button>
    </div>
    <input type="number" name="cashier_count" id="input_cashier" value="0" min="0" hidden>
    <input type="checkbox" name="staff_roles[]" value="cashier" id="chk_cashier" hidden>
  </div>
  <div class="sf6-staff-row" id="row_security">
    <div class="sf6-staff-left">
      <div class="sf6-staff-icon"><i class="bi bi-shield"></i></div>
      <div class="sf6-staff-info">
        <span class="sf6-staff-name">Security</span>
        <span class="sf6-staff-role">Entrance &amp; premises safety</span>
      </div>
    </div>
    <div class="sf6-qty-ctrl">
      <button type="button" class="sf6-qty-btn" data-action="minus" data-role="security">−</button>
      <span class="sf6-qty-num" id="qty_security">0</span>
      <button type="button" class="sf6-qty-btn" data-action="plus" data-role="security">+</button>
    </div>
    <input type="number" name="security_count" id="input_security" value="0" min="0" hidden>
    <input type="checkbox" name="staff_roles[]" value="security" id="chk_security" hidden>
  </div>
  <div class="sf6-staff-row" id="row_kitchen_helper">
    <div class="sf6-staff-left">
      <div class="sf6-staff-icon"><i class="bi bi-wrench"></i></div>
      <div class="sf6-staff-info">
        <span class="sf6-staff-name">Kitchen Helpers</span>
        <span class="sf6-staff-role">Dishwashing &amp; prep support</span>
      </div>
    </div>
    <div class="sf6-qty-ctrl">
      <button type="button" class="sf6-qty-btn" data-action="minus" data-role="kitchen_helper">−</button>
      <span class="sf6-qty-num" id="qty_kitchen_helper">0</span>
      <button type="button" class="sf6-qty-btn" data-action="plus" data-role="kitchen_helper">+</button>
    </div>
    <input type="number" name="kitchen_helper_count" id="input_kitchen_helper" value="0" min="0" hidden>
    <input type="checkbox" name="staff_roles[]" value="kitchen_helper" id="chk_kitchen_helper" hidden>
  </div>
</div>

<p class="sf6-foot-summary" id="sf7-count-text">0 staff total</p>
<div class="sf6-footer-bar">
  <a class="sf-btn-main sf-btn-back" href="setup.php?step=6">← Back</a>
  <button class="sf-btn-main sf-btn-next" type="submit">Finish →</button>
</div>

</form>

<script>
(function(){
  var counts = { waiter:0, chef:0, cashier:0, security:0, kitchen_helper:0 };

  document.querySelectorAll('#sf7-form .sf6-qty-btn').forEach(function(btn){
    btn.addEventListener('click', function(){
      var role = btn.dataset.role;
      if(btn.dataset.action === 'plus') counts[role]++;
      else if(counts[role] > 0) counts[role]--;

      document.getElementById('qty_' + role).textContent = counts[role];
      document.getElementById('input_' + role).value = counts[role];
      document.getElementById('chk_' + role).checked = counts[role] > 0;

      var row = document.getElementById('row_' + role);
      row.classList.toggle('sf6-staff-row--active', counts[role] > 0);

      var total = Object.keys(counts).reduce(function(s,k){ return s + counts[k]; }, 0);
      document.getElementById('sf7-count-text').textContent = total + ' staff total';
    });
  });
})();
</script>

<?php elseif ($step === 5): ?>

<h1 class="sf-setup-hero-title">What is your budget?</h1>
<p class="sf-card-hint">Choose the range that best fits your investment plan.</p>

<form method="post" class="sf-step5-form">
  <input type="hidden" name="step" value="5">
  <input type="hidden" name="budget" id="budget-hidden" value="<?= $budget ? h((string)$budget) : "" ?>" required>

  <?php
    $budgetOptions = [
      ["label" => "Under 500,000 EGP",          "sub" => "Starter setup",       "value" => 250000],
      ["label" => "500,000 - 1,500,000 EGP",    "sub" => "Mid-range build",     "value" => 1000000],
      ["label" => "1,500,000 - 3,000,000 EGP",  "sub" => "Full fit-out",        "value" => 2250000],
      ["label" => "3,000,000+ EGP",             "sub" => "Premium experience",  "value" => 4000000],
    ];
  ?>

  <div class="sf-budget-grid">
    <?php foreach ($budgetOptions as $opt): ?>
      <button
        type="button"
        class="sf-budget-card <?= $budget === $opt["value"] ? "is-selected" : "" ?>"
        data-value="<?= $opt["value"] ?>"
      >
        <div class="sf-budget-label"><?= h($opt["label"]) ?></div>
        <div class="sf-budget-sub"><?= h($opt["sub"]) ?></div>
      </button>
    <?php endforeach; ?>
  </div>

  <div class="sf-actions">
    <a class="sf-btn-main sf-btn-back" href="setup.php?step=3">&#8592; Back</a>
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

<script>
(function(){

  /* Step 0: business name -> sign */
  const nameInput = document.querySelector('input[name="business_name"]');
  const sign = document.getElementById('buildingSign');

  if (nameInput && sign) {
    const updateSign = () => {
      const value = nameInput.value.trim();
      sign.textContent = value === "" ? "Your Business" : value;
    };

    nameInput.addEventListener('input', updateSign);
    updateSign();
  }

  /* Step 1: business type hover videos */
  const root = document.querySelector('.sf-setup');
  if (!root) return;

  const cards = root.querySelectorAll('.sf-biz-card-landscape');
  cards.forEach(card => {
    const v = card.querySelector('.sf-biz-video');
    if (!v) return;

    card.addEventListener('mouseenter', () => {
      try {
        v.currentTime = 0;
        v.play();
      } catch (e) {}
    });

    card.addEventListener('mouseleave', () => {
      try {
        v.pause();
      } catch (e) {}
    });
  });

})();
</script>

</div>
</section>

        </div>
      </section>
    </div>

  </div>
</main>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/site.js"></script>
</body>
</html> 