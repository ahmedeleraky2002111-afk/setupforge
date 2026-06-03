<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>About Us - SetupForge</title>
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@400;500;600;700;800&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/style.css?v=9" rel="stylesheet">

  <style>
    * {
      font-family: 'Poppins', sans-serif;
    }

    body {
      margin: 0;
      padding: 0;
      background:
        radial-gradient(circle at top left, rgba(13, 110, 253, 0.15), transparent 25%),
        radial-gradient(circle at bottom right, rgba(25, 135, 84, 0.12), transparent 25%),
        linear-gradient(135deg, #f7faff, #eef4ff);
      min-height: 100vh;
    }

    .about-hero {
      padding: 90px 20px 50px;
    }

    .about-wrapper {
      max-width: 1150px;
      margin: auto;
    }

    .about-card {
      background: rgba(255, 255, 255, 0.9);
      backdrop-filter: blur(10px);
      border-radius: 28px;
      padding: 50px;
      box-shadow: 0 18px 45px rgba(0, 0, 0, 0.08);
      border: 1px solid rgba(255, 255, 255, 0.7);
      overflow: hidden;
      position: relative;
    }

    .about-card::before {
      content: "";
      position: absolute;
      top: -80px;
      right: -80px;
      width: 220px;
      height: 220px;
      background: linear-gradient(135deg, rgba(13, 110, 253, 0.18), rgba(25, 135, 84, 0.12));
      border-radius: 50%;
    }

    .about-badge {
      display: inline-block;
      background: linear-gradient(90deg, #0d6efd, #198754);
      color: #fff;
      font-size: 14px;
      font-weight: 600;
      padding: 8px 18px;
      border-radius: 50px;
      margin-bottom: 18px;
      box-shadow: 0 8px 20px rgba(13, 110, 253, 0.18);
    }

    .about-title {
      font-size: 44px;
      font-weight: 800;
      color: #111827;
      margin-bottom: 18px;
      line-height: 1.2;
      position: relative;
      z-index: 1;
    }

    .about-title span {
      background: linear-gradient(90deg, #0d6efd, #198754);
      -webkit-background-clip: text;
      -webkit-text-fill-color: transparent;
    }

    .about-text {
      font-size: 17px;
      color: #5b6475;
      line-height: 1.9;
      margin-bottom: 18px;
      max-width: 700px;
      position: relative;
      z-index: 1;
    }

    .about-buttons {
      margin-top: 28px;
      display: flex;
      gap: 14px;
      flex-wrap: wrap;
      position: relative;
      z-index: 1;
    }

    .btn-custom-primary {
      background: linear-gradient(90deg, #0d6efd, #198754);
      color: #fff;
      border: none;
      padding: 13px 26px;
      border-radius: 50px;
      font-weight: 600;
      transition: 0.3s ease;
      text-decoration: none;
      box-shadow: 0 10px 22px rgba(13, 110, 253, 0.2);
    }

    .btn-custom-primary:hover {
      transform: translateY(-2px);
      color: #fff;
    }

    .btn-custom-outline {
      background: #fff;
      color: #111827;
      border: 1px solid #dbe3f0;
      padding: 13px 26px;
      border-radius: 50px;
      font-weight: 600;
      transition: 0.3s ease;
      text-decoration: none;
    }

    .btn-custom-outline:hover {
      background: #f3f7ff;
      color: #111827;
    }

    .features-row {
      margin-top: 35px;
      position: relative;
      z-index: 1;
    }

    .feature-box {
      background: #fff;
      border-radius: 20px;
      padding: 24px;
      height: 100%;
      box-shadow: 0 12px 30px rgba(0, 0, 0, 0.05);
      border: 1px solid #eef2f7;
      transition: 0.3s ease;
    }

    .feature-box:hover {
      transform: translateY(-6px);
    }

    .feature-icon {
      width: 58px;
      height: 58px;
      border-radius: 16px;
      display: flex;
      align-items: center;
      justify-content: center;
      font-size: 24px;
      margin-bottom: 16px;
      background: linear-gradient(135deg, rgba(13, 110, 253, 0.12), rgba(25, 135, 84, 0.12));
    }

    .feature-title {
      font-size: 20px;
      font-weight: 700;
      color: #111827;
      margin-bottom: 10px;
    }

    .feature-text {
      color: #667085;
      font-size: 15px;
      line-height: 1.7;
      margin-bottom: 0;
    }

    @media (max-width: 768px) {
      .about-card {
        padding: 30px 22px;
      }

      .about-title {
        font-size: 32px;
      }

      .about-text {
        font-size: 15px;
      }
    }
  </style>
</head>
<body>

  <?php include 'includes/navbar.php'; ?>

  <section class="about-hero">
    <div class="about-wrapper">
      <div class="about-card">
        <div class="about-badge">About SetupForge</div>

        <h1 class="about-title">
          Building businesses with <span>smart digital solutions</span>
        </h1>

        <p class="about-text">
          SetupForge is a modern platform created to make business setup and growth easier,
          faster, and more efficient. We connect users with equipment, trusted vendors,
          and skilled technicians in one seamless experience.
        </p>

        <p class="about-text">
          Our mission is to simplify the journey of launching and managing a business
          by providing reliable tools, valuable connections, and a user-friendly digital platform.
        </p>

        <p class="about-text">
          Whether you are starting your first project or expanding an existing one,
          SetupForge is here to support you every step of the way.
        </p>

        <div class="about-buttons">
          <a href="contact.php" class="btn-custom-primary">Contact Us</a>
          <a href="products.php" class="btn-custom-outline">Explore Products</a>
        </div>

        <div class="row g-4 features-row">
          <div class="col-md-4">
            <div class="feature-box">
              <div class="feature-icon">⚙️</div>
              <h3 class="feature-title">All-in-One Platform</h3>
              <p class="feature-text">
                Access equipment, vendors, and technicians from one organized and reliable place.
              </p>
            </div>
          </div>

          <div class="col-md-4">
            <div class="feature-box">
              <div class="feature-icon">🚀</div>
              <h3 class="feature-title">Business Growth</h3>
              <p class="feature-text">
                We help businesses move faster by simplifying setup, sourcing, and daily operations.
              </p>
            </div>
          </div>

          <div class="col-md-4">
            <div class="feature-box">
              <div class="feature-icon">🤝</div>
              <h3 class="feature-title">Trusted Support</h3>
              <p class="feature-text">
                Our goal is to offer dependable support and smart solutions for every stage of your journey.
              </p>
            </div>
          </div>
        </div>

      </div>
    </div>
  </section>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>