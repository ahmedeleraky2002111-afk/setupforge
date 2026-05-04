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

  redirect_step(8);

}

if ($currentStep === 8) {

  // keep logo handling simple for now
  // later we can add real upload logic
  $_SESSION["wizard"]["business_logo"] = $_FILES["business_logo"]["name"] ?? "";

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
if ($step < 0 || $step > 8) redirect_step(0);

if ($step > 0 && $businessName === "") redirect_step(0);
if ($step > 1 && $business === "") redirect_step(1);
if ($step > 2 && $business === "Restaurant" && $restaurantType === "") redirect_step(2);
if ($step > 3 && $indoorSeats < 1) redirect_step(3);

if ($step > 5 && $budget <= 0) redirect_step(5);

if ($step > 6 && !array_key_exists("installation_needed", $w)) {
  redirect_step(6);
}
if ($step > 7 && $budget <= 0) redirect_step(5);
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
  <link href="assets/style.css?v=6" rel="stylesheet">
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

<h1 class="sf-step3-title">How many seats?</h1>
<p class="sf-step3-sub">Tell us your seating capacity so we can size your equipment and layout recommendations.</p>

<form method="post" class="sf-step3-form">
  <input type="hidden" name="step" value="3">

  <div class="sf-seats-grid">

    <div class="sf-seats-field">
      <label class="sf-seats-label" for="indoor_seats">Indoor Seats</label>
      <input
        class="sf-seats-input"
        type="number"
        id="indoor_seats"
        name="indoor_seats"
        min="1"
        value="<?= $indoorSeats > 0 ? h($indoorSeats) : 1 ?>"
        required
      >
    </div>

    <div class="sf-seats-field">
      <label class="sf-seats-label" for="outdoor_seats">Outdoor Seats</label>
      <input
        class="sf-seats-input"
        type="number"
        id="outdoor_seats"
        name="outdoor_seats"
        min="0"
        value="<?= h($outdoorSeats) ?>"
      >
    </div>

  </div>

  <div class="sf-actions">
    <a class="sf-btn-main sf-btn-back" href="setup.php?step=2">← Back</a>
    <button class="sf-btn-main sf-btn-next" type="submit">Next →</button>
  </div>
</form>
<?php elseif ($step === 6): ?>

<h1 class="sf-step-title">Additional Services</h1>
<p class="sf-step-sub">
  Choose any extra help you need for setup and daily operations.
</p>

<form method="post">
<input type="hidden" name="step" value="6">

<div class="sf-services-wrapper">

  <!-- 🔧 INSTALLATION -->
  <div class="sf-service-card">

    <div class="sf-service-top">
      <div>
        <div class="sf-service-title">Installation Services</div>
        <div class="sf-service-sub">Setup equipment and technical systems</div>
      </div>

      <div class="sf-toggle-group">
        <button type="button" class="sf-toggle active" data-target="installation">Yes</button>
        <button type="button" class="sf-toggle" data-target="installation">No</button>
        <input type="hidden" name="installation_needed" id="installation_input" value="yes">
      </div>
    </div>

    <div class="sf-service-options" id="installation_options">
      <label><input type="checkbox" name="installation_services[]" value="pos"> POS Setup</label>
      <label><input type="checkbox" name="installation_services[]" value="electrical"> Electrical Setup</label>
      <label><input type="checkbox" name="installation_services[]" value="network"> Network Setup</label>
      <label><input type="checkbox" name="installation_services[]" value="ac"> AC Installation</label>
    </div>

  </div>

  <!-- 👷 STAFF -->
  <div class="sf-service-card">

    <div class="sf-service-top">
      <div>
        <div class="sf-service-title">Staffing</div>
        <div class="sf-service-sub">Hire workers for daily operations</div>
      </div>

      <div class="sf-toggle-group">
        <button type="button" class="sf-toggle" data-target="staff">Yes</button>
        <button type="button" class="sf-toggle active" data-target="staff">No</button>
        <input type="hidden" name="staffing_needed" id="staff_input" value="no">
      </div>
    </div>

    <div class="sf-service-options hidden" id="staff_options">
      <label><input type="checkbox" name="staff_roles[]" value="waiter"> Waiters</label>
      <label><input type="checkbox" name="staff_roles[]" value="chef"> Chefs</label>
      <label><input type="checkbox" name="staff_roles[]" value="cashier"> Cashiers</label>
      <label><input type="checkbox" name="staff_roles[]" value="cleaner"> Cleaners</label>
    </div>

  </div>

</div>

<div class="sf-actions">
  <a class="sf-btn-main sf-btn-back" href="setup.php?step=5">← Back</a>
  <button class="sf-btn-main sf-btn-next" type="submit">Next →</button>
</div>

</form>

<?php elseif ($step === 7): ?>
<h1 class="sf-setup-hero-title">Staffing Needs</h1>
<p class="sf-card-hint">Tell us how many staff you need.</p>

<form method="post">
<input type="hidden" name="step" value="7">
  <?php
    $labor = $w["labor"] ?? [];
    $barista = (int)($labor["barista"] ?? 0);
    $cashier = (int)($labor["cashier"] ?? 0);
    $waiter  = (int)($labor["waiter"] ?? 0);
  ?>

  <div class="row g-3">
    <div class="col-md-4">
      <div class="sf-field">
        <label>Baristas</label>
        <input type="number" name="labor_barista" class="form-control" min="0" value="<?= h((string)$barista) ?>">
      </div>
    </div>

    <div class="col-md-4">
      <div class="sf-field">
        <label>Cashiers</label>
        <input type="number" name="labor_cashier" class="form-control" min="0" value="<?= h((string)$cashier) ?>">
      </div>
    </div>

    <div class="col-md-4">
      <div class="sf-field">
        <label>Waiters</label>
        <input type="number" name="labor_waiter" class="form-control" min="0" value="<?= h((string)$waiter) ?>">
      </div>
    </div>
  </div>

  <div class="sf-actions">
<a class="sf-btn-main sf-btn-back" href="setup.php?step=6">← Back</a>
  <button class="sf-btn-main sf-btn-next" type="submit">Next →</button>
</div>
</form>
<?php elseif ($step === 5): ?>

<h1 class="sf-setup-hero-title">What is your budget?</h1>
<p class="sf-card-hint">We’ll generate packages that fit your range.</p>

<form method="post" class="sf-step5-form">
      <input type="hidden" name="step" value="5">

  <div class="sf-field" style="max-width: 520px; margin: 0 auto;">
<label for="budget" style="display:block; text-align:center; ">
  Budget (EGP)
</label>    <input
  id="budget"
  name="budget"
  type="text"
  inputmode="numeric"
  class="sf-budget-input"
      placeholder="e.g. 250000"
      value="<?= $budget ? h((string)$budget) : "" ?>"
      required
    >
  </div>

  <div class="sf-actions">
    <a class="sf-btn-main sf-btn-back" href="setup.php?step=3">← Back</a>
    <button class="sf-btn-main sf-btn-next" type="submit">Next →</button>
  </div>
</form>
<?php elseif ($step === 8): ?>

<h1 class="sf-setup-hero-title">Add your business logo</h1>
<p class="sf-card-hint">Optional for now — you can always update it later.</p>

<form method="post" enctype="multipart/form-data">
  <input type="hidden" name="step" value="8">

<div class="sf-field" style="max-width: 520px; margin: 0 auto; text-align:center;">
        <label for="business_logo">Business Logo</label>
    <input
      id="business_logo"
      type="file"
      name="business_logo"
      class="form-control"
      accept="image/*"
    >
  </div>

  <div class="sf-actions">
  <a class="sf-btn-main sf-btn-back" href="setup.php?step=7">← Back</a>
  <button class="sf-btn-main sf-btn-next" type="submit">Finish →</button>
</div>
</form>

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
<script>
document.querySelectorAll('.sf-toggle').forEach(btn => {
  btn.addEventListener('click', () => {
    const target = btn.dataset.target;

    if(target === "installation"){
      document.getElementById("installation_input").value = btn.innerText.toLowerCase();
      document.getElementById("installation_options").classList.toggle("hidden", btn.innerText === "No");
    }

    if(target === "staff"){
      document.getElementById("staff_input").value = btn.innerText.toLowerCase();
      document.getElementById("staff_options").classList.toggle("hidden", btn.innerText === "No");
    }

    btn.parentElement.querySelectorAll('.sf-toggle').forEach(b => b.classList.remove('active'));
    btn.classList.add('active');
  });
});
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script src="assets/site.js"></script>
</body>
</html> 