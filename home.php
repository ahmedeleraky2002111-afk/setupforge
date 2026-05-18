<?php
// home.php
session_start();
require_once "db.php";

/*
|--------------------------------------------------------------------------
| Hero button logic
|--------------------------------------------------------------------------
*/
$setupBtnText = "Start Your Setup";
$setupBtnLink = "setup.php?step=0";

// Guest with active wizard session
if (!isset($_SESSION["user_id"]) && !empty($_SESSION["wizard"])) {
    $guestStep = (int)($_SESSION["wizard"]["indoor_seats"] ?? 0) > 0 ? 3 : 
                 (!empty($_SESSION["wizard"]["business_type"]) ? 1 : 0);
    if ($guestStep > 0) {
        $setupBtnText = "Resume Setup";
        $setupBtnLink = "setup.php";
    }
}

if (isset($_SESSION["user_id"]) && $conn) {
    $userId = (int)$_SESSION["user_id"];

    // Check for completed (paid) order
    $paidRes = @pg_query_params($conn,
        "SELECT 1 FROM orders WHERE business_user_id = $1 AND payment_status = 'paid' LIMIT 1",
        [$userId]
    );
    if ($paidRes && pg_num_rows($paidRes) > 0) {
        $setupBtnText = "My business";
        $setupBtnLink = "business_overview.php";
    } else {
        // Check for in-progress setup
        $bizRes = @pg_query_params($conn,
            "SELECT setup_status, setup_step FROM businesses WHERE user_id = $1 LIMIT 1",
            [$userId]
        );
        if ($bizRes && pg_num_rows($bizRes) > 0) {
            $biz = pg_fetch_assoc($bizRes);
            if (($biz['setup_status'] ?? '') === 'in_progress' && (int)($biz['setup_step'] ?? 0) > 0) {
                $setupBtnText = "Resume Setup";
                $setupBtnLink = "setup.php";
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
  <title>SetupForge - Home</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
  <link href="assets/style.css?v=9" rel="stylesheet">
</head>
<body class="sf-home-page">
  <?php include "includes/navbar.php"; ?>

  <main class="sf-home-wrap">

    <!-- HERO -->
    <section class="sf-home-hero" id="sfHomeHero">
      <video class="sf-home-hero-video" autoplay muted loop playsinline>
        <source src="assets/videos/home1.mp4" type="video/mp4">
        Your browser does not support the video tag.
      </video>

      <div class="sf-home-hero-overlay"></div>

      <div class="container sf-home-hero-content">
        <p class="sf-home-hero-kicker">SetupForge</p>

        <h1 class="sf-home-hero-title">
          We build your setup.<br>
          You build your business.
        </h1>

        <p class="sf-home-hero-sub">
          Buy the right equipment and we'll deliver, install, and prepare your place so you can open faster.
        </p>

        <div class="sf-hero-actions">
          <a href="<?= htmlspecialchars($setupBtnLink) ?>" class="sf-btn sf-btn-primary sf-btn-arrow">
            <span><?= htmlspecialchars($setupBtnText) ?></span>
            <span class="sf-btn-icon">→</span>
          </a>
        </div>
      </div>
    </section>

    <!-- OUR SERVICES -->
        <!-- OUR SERVICES -->
    <section class="sf-home-services-section">
      <div class="container">
        <div class="sf-home-services-head reveal-up">
          <h2 class="sf-home-services-title">Our Services</h2>
          <p class="sf-home-services-sub">
            Everything you need to plan, build, and operate your setup.
          </p>
        </div>

        <div class="sf-home-services-grid">

          <div class="sf-home-service-card reveal-up">

  <img src="assets/images/service-packages.png" alt="Smart Setup Packages" class="sf-home-service-bg">

  <div class="sf-home-service-overlay"></div>

  <div class="sf-home-service-card-body">
  <div class="sf-home-service-head-row">
    <div class="sf-home-service-icon">
      <i class="bi bi-box-seam"></i>
    </div>
    <h3 class="sf-home-service-card-title">Smart Setup Packages</h3>
  </div>

  <p class="sf-home-service-card-text">
    Generate tailored setup packages based on your business needs, space, and budget.
  </p>
</div>

</div>

          <div class="sf-home-service-card reveal-up">

  <img src="assets/images/home-installation.png" alt="Installation and Setup" class="sf-home-service-bg">

  <div class="sf-home-service-overlay"></div>

  <div class="sf-home-service-card-body">
  <div class="sf-home-service-head-row">
    <div class="sf-home-service-icon">
      <i class="bi bi-wrench-adjustable-circle"></i>
    </div>
    <h3 class="sf-home-service-card-title">Installation & Setup</h3>
  </div>

  <p class="sf-home-service-card-text">
    Get professional installation for equipment, systems, and setup essentials.
  </p>
</div>

</div>

          <div class="sf-home-service-card reveal-up">

  <img src="assets/images/home-staffing.png" alt="Staffing" class="sf-home-service-bg">

  <div class="sf-home-service-overlay"></div>

  <div class="sf-home-service-card-body">
  <div class="sf-home-service-head-row">
    <div class="sf-home-service-icon">
      <i class="bi bi-people"></i>
    </div>
    <h3 class="sf-home-service-card-title">Staffing</h3>
  </div>

  <p class="sf-home-service-card-text">
    Hire workers to support your daily operations and keep your business running smoothly.
  </p>
</div>

</div>

        </div>
      </div>
    </section>

  </main>

  <footer class="sf-footer mt-5">
    <div class="container py-5">
      <div class="row g-4">

        <div class="col-lg-4">
          <div class="d-flex align-items-center gap-3 mb-3">
            <div class="sf-footer-logo">
              <img src="assets/images/Logo.png" alt="SetupForge Logo">
            </div>
            <h5 class="mb-0 text-white fw-bold">SetupForge</h5>
          </div>

          <p class="sf-footer-text">
            SetupForge helps entrepreneurs launch, furnish, and fully prepare their businesses.
            From equipment sourcing to installation and optimization — we handle it all.
          </p>

          <div class="sf-socials mt-3">
            <a href="#">Facebook</a>
            <a href="#">Instagram</a>
            <a href="#">LinkedIn</a>
          </div>
        </div>

        <div class="col-6 col-lg-2">
          <h6 class="sf-footer-title">Products</h6>
          <ul class="sf-footer-links">
            <li><a href="#">Kitchen Equipment</a></li>
            <li><a href="#">Furniture</a></li>
            <li><a href="#">POS Systems</a></li>
            <li><a href="#">Security Systems</a></li>
            <li><a href="#">Packaging</a></li>
          </ul>
        </div>

        <div class="col-6 col-lg-2">
          <h6 class="sf-footer-title">Services</h6>
          <ul class="sf-footer-links">
            <li><a href="#">Installation</a></li>
            <li><a href="#">Interior Design</a></li>
            <li><a href="#">Branding</a></li>
            <li><a href="#">Consultation</a></li>
            <li><a href="#">Maintenance</a></li>
          </ul>
        </div>

        <div class="col-6 col-lg-2">
          <h6 class="sf-footer-title">Resources</h6>
          <ul class="sf-footer-links">
            <li><a href="help-center.php">Help Center</a></li>
            <li><a href="faq.php">FAQ</a></li>
            <li><a href="about.php">About Us</a></li>
            <li><a href="#">Blog</a></li>
            <li><a href="#">Guides</a></li>
          </ul>
        </div>

        <div class="col-12 col-lg-2">
          <h6 class="sf-footer-title">Stay Updated</h6>
          <p class="sf-footer-text small">
            Get updates, product releases, and startup tips.
          </p>

          <form>
            <input type="email" class="sf-footer-input mb-2" placeholder="Your email">
            <button type="submit" class="btn btn-light w-100 btn-sm fw-semibold">
              Subscribe
            </button>
          </form>
        </div>

      </div>
    </div>

    <div class="sf-footer-bottom">
      <div class="container d-flex justify-content-between flex-wrap gap-2">
        <span>© 2026 SetupForge. All rights reserved.</span>
        <div>
          <a href="#">Privacy Policy</a>
          <a href="#" class="ms-3">Terms</a>
        </div>
      </div>
    </div>
  </footer>

  <script>
  (function () {
    const nav = document.querySelector('.sf-navbar');
    const hero = document.getElementById('sfHomeHero');

    if (!nav || !hero) return;

    function updateNav() {
      const trigger = hero.offsetHeight - nav.offsetHeight - 40;
      if (window.scrollY > trigger) {
        nav.classList.add('is-scrolled');
      } else {
        nav.classList.remove('is-scrolled');
      }
    }

    updateNav();
    window.addEventListener('scroll', updateNav, { passive: true });
    window.addEventListener('resize', updateNav);
  })();
  </script>
<script>
(function () {
  const items = document.querySelectorAll('.reveal-up');
  if (!items.length) return;

  const observer = new IntersectionObserver((entries) => {
    entries.forEach((entry) => {
      if (entry.isIntersecting) {
        entry.target.classList.add('is-visible');
        observer.unobserve(entry.target);
      }
    });
  }, {
    threshold: 0.16
  });

  items.forEach((item) => observer.observe(item));
})();
</script>
  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
  <script src="assets/site.js"></script>
</body>
</html>