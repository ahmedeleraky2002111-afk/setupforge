<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$displayName =
    $_SESSION["name"]
    ?? $_SESSION["full_name"]
    ?? $_SESSION["username"]
    ?? $_SESSION["email"]
    ?? "User";

// Cart count — only for non-vendor/labor/company users
$userType = $_SESSION["user_type"] ?? "guest";
$showCart = !in_array($userType, ["vendor", "labor", "company"], true);

$cartCount = 0;
if ($showCart && !empty($_SESSION["shop_cart"])) {
    foreach ($_SESSION["shop_cart"] as $item) {
        $cartCount += (int)($item["qty"] ?? 1);
    }
}
?>

<nav class="navbar navbar-expand-lg navbar-dark sf-navbar">
  <div class="container d-flex align-items-center">

    <div class="d-flex align-items-center flex-grow-1">
      <a class="navbar-brand d-flex align-items-center gap-2" href="home.php">
        <div class="sf-logo"><img src="assets/images/Logo.png" alt="SetupForge Logo"></div>
        <span class="fw-bold text-white">SetupForge</span>
      </a>
    </div>

    <div class="d-none d-lg-flex justify-content-center flex-grow-1">
      <ul class="navbar-nav align-items-center gap-3">

        <li class="nav-item">
          <a class="nav-link sf-navlink" href="products.php">Products</a>
        </li>

        <li class="nav-item">
          <a class="nav-link sf-navlink" href="service_jobs.php">Services</a>
        </li>

        <?php
          // My Setup button logic
          $showSetupBtn  = false;
          $setupBtnLabel = "My Setup";
          $setupBtnHref  = "business_overview.php";

          if (!isset($_SESSION["user_id"])) {
            // Guest — only show if wizard is in progress
            if (!empty($_SESSION["wizard"]) && !empty($_SESSION["wizard"]["business_type"])) {
              $showSetupBtn  = true;
              $setupBtnLabel = "My Setup";
              $setupBtnHref  = "setup.php";
            }
          } else {
            $navUserId  = (int)$_SESSION["user_id"];
            $navConn    = $conn ?? null;

            // Check for completed paid setup order
            $navPaidRes = $navConn ? @pg_query_params($navConn,
              "SELECT 1 FROM orders WHERE business_user_id = \$1 AND payment_status = 'paid' AND order_type = 'setup' LIMIT 1",
              [$navUserId]) : null;

            if ($navPaidRes && pg_num_rows($navPaidRes) > 0) {
              // Completed setup
              $showSetupBtn  = true;
              $setupBtnLabel = "My Setup";
              $setupBtnHref  = "business_overview.php";
            } else {
              // Check business row for setup status
              $navBizRes = $navConn ? @pg_query_params($navConn,
                "SELECT setup_status, setup_step FROM businesses WHERE user_id = \$1 LIMIT 1",
                [$navUserId]) : null;

              if ($navBizRes && pg_num_rows($navBizRes) > 0) {
                $navBiz    = pg_fetch_assoc($navBizRes);
                $navStatus = $navBiz["setup_status"] ?? "";
                $navStep   = (int)($navBiz["setup_step"] ?? 0);

                if ($navStatus === "completed") {
                  // Wizard done, at packages, not yet paid
                  $showSetupBtn  = true;
                  $setupBtnLabel = "My Setup";
                  $setupBtnHref  = "packages.php";
                } elseif ($navStatus === "in_progress" && $navStep > 0) {
                  // Mid-wizard
                  $showSetupBtn  = true;
                  $setupBtnLabel = "My Setup";
                  $setupBtnHref  = "setup.php";
                }
              } elseif (!empty($_SESSION["wizard"]) && !empty($_SESSION["wizard"]["business_type"])) {
                // Logged in but no businesses row yet, wizard in session
                $showSetupBtn  = true;
                $setupBtnLabel = "My Setup";
                $setupBtnHref  = "setup.php";
              }
            }
          }
        ?>
        <?php if ($showSetupBtn): ?>
          <li class="nav-item">
            <a class="nav-link sf-navlink" href="<?= htmlspecialchars($setupBtnHref) ?>">
              <?= htmlspecialchars($setupBtnLabel) ?>
            </a>
          </li>
        <?php endif; ?>

        <li class="nav-item dropdown">
          <a class="nav-link dropdown-toggle sf-navlink"
            href="#"
            data-bs-toggle="dropdown"
            aria-expanded="false">
            Resources
          </a>

          <div class="dropdown-menu sf-dropdown sf-resources-dropdown">
            <div class="sf-resources-wrap">

              <div class="sf-resources-top">24/7 SUPPORT</div>

              <div class="sf-resources-links">
                <a class="sf-resource-link" href="help-center.php">
                  <span class="sf-resource-title">Help Center</span>
                </a>
                <a class="sf-resource-link" href="about.php">
                  <span class="sf-resource-title">About Us</span>
                </a>
                <a class="sf-resource-link" href="faq.php">
                  <span class="sf-resource-title">FAQ</span>
                </a>
                <a class="sf-resource-link" href="contact.php">
                  <span class="sf-resource-title">Contact Us</span>
                </a>
              </div>

              <a href="help-center.php" class="sf-resources-image-block">
                <div class="sf-resources-image">
                  <img src="assets/images/resources-preview.png" alt="Resources Preview">
                </div>
              </a>

            </div>
          </div>
        </li>

      </ul>
    </div>

    <div class="d-flex justify-content-end flex-grow-1 gap-2 align-items-center">

      <?php if ($showCart): ?>
        <a href="cart.php" class="sf-navbar-cart" title="Cart">
          <i class="bi bi-cart3"></i>
          <?php if ($cartCount > 0): ?>
            <span class="sf-navbar-cart-badge"><?= $cartCount ?></span>
          <?php endif; ?>
        </a>
      <?php endif; ?>

      <?php if (isset($_SESSION["user_id"])): ?>
        <div class="dropdown">
          <button class="btn sf-profile-btn" data-bs-toggle="dropdown">
            <i class="bi bi-person-fill"></i>
          </button>

          <ul class="dropdown-menu dropdown-menu-end sf-dropdown sf-profile-dropdown">

            <li class="sf-profile-head">
              <div class="sf-profile-name">
                <?php echo htmlspecialchars($displayName); ?>
              </div>
              <div class="sf-profile-email">
                <?php echo htmlspecialchars($_SESSION["email"] ?? ""); ?>
              </div>
            </li>

            <li><hr class="dropdown-divider"></li>

            <li><a class="dropdown-item sf-profile-item" href="#">Profile</a></li>
            <li><a class="dropdown-item sf-profile-item" href="#">Security</a></li>
            <li><a class="dropdown-item sf-profile-item" href="#">Activity</a></li>
            <li><a class="dropdown-item sf-profile-item" href="#">Email Notifications</a></li>
            <li><a class="dropdown-item sf-profile-item" href="#">Language</a></li>
            <li><a class="dropdown-item sf-profile-item" href="#">Help</a></li>

            <li><hr class="dropdown-divider"></li>

            <li>
              <a class="dropdown-item sf-profile-logout" href="auth/logout.php">
                Log Out
              </a>
            </li>

          </ul>
        </div>
      <?php else: ?>
        <a href="auth/login.php" class="btn btn-light btn-sm px-3 fw-semibold">
          Sign In
        </a>
        <a href="auth/signup.php" class="btn btn-outline-light btn-sm px-3 fw-semibold">
          Sign Up
        </a>
      <?php endif; ?>

    </div>

  </div>
</nav>