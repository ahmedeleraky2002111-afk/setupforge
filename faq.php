<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>FAQ - SetupForge</title>

  <!-- Bootstrap -->
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
  
  <!-- Your main CSS -->
  <link href="assets/style.css?v=10" rel="stylesheet">

  <!-- Icons -->
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">

  <style>
    /* PAGE BACKGROUND */
    .sf-faq-page {
      padding: 110px 0 70px;
      background:
        radial-gradient(circle at top left, rgba(0,76,172,.08), transparent 30%),
        radial-gradient(circle at bottom right, rgba(0,153,148,.08), transparent 25%),
        #f7faff;
      min-height: 100vh;
    }

    .sf-faq-title {
      text-align: center;
      font-size: 42px;
      font-weight: 800;
      margin-bottom: 50px;
      color: #111;
    }

    .sf-faq-item {
      background: #fff;
      border-radius: 18px;
      margin-bottom: 18px;
      border: 1px solid rgba(0,0,0,.06);
      box-shadow: 0 12px 28px rgba(0,0,0,.05);
      overflow: hidden;
      transition: 0.3s ease;
    }

    .sf-faq-item:hover {
      transform: translateY(-3px);
    }

    .sf-faq-question {
      width: 100%;
      padding: 20px;
      font-weight: 700;
      font-size: 16px;
      background: #fff;
      border: none;
      display: flex;
      justify-content: space-between;
      align-items: center;
      cursor: pointer;
      text-align: left;
      color: #111;
    }

    .sf-faq-question:hover {
      background: #f5f9ff;
    }

    .sf-faq-question:focus {
      outline: none;
    }

    .sf-faq-answer {
      display: none;
      padding: 0 20px 20px;
      color: #666;
      line-height: 1.8;
      font-size: 15px;
    }

    .sf-faq-item.active .sf-faq-answer {
      display: block;
    }

    .sf-faq-icon {
      font-size: 20px;
      transition: 0.3s;
      color: #004cac;
      flex-shrink: 0;
      margin-left: 15px;
    }

    .sf-faq-item.active .sf-faq-icon {
      transform: rotate(45deg);
    }

    @media (max-width: 768px) {
      .sf-faq-page {
        padding: 90px 0 50px;
      }

      .sf-faq-title {
        font-size: 32px;
        margin-bottom: 35px;
      }

      .sf-faq-question {
        font-size: 15px;
        padding: 18px;
      }

      .sf-faq-answer {
        font-size: 14px;
        padding: 0 18px 18px;
      }
    }
  </style>
</head>

<body>

  <?php include 'includes/navbar.php'; ?>

  <main class="sf-faq-page">
    <div class="container">

      <h1 class="sf-faq-title">Frequently Asked Questions</h1>

      <div class="sf-faq-item">
        <button type="button" class="sf-faq-question">
          <span>What is SetupForge?</span>
          <i class="bi bi-plus-lg sf-faq-icon"></i>
        </button>
        <div class="sf-faq-answer">
          SetupForge is a platform that helps you build and manage your business by connecting you with equipment, vendors, and skilled technicians.
        </div>
      </div>

      <div class="sf-faq-item">
        <button type="button" class="sf-faq-question">
          <span>How can I start using SetupForge?</span>
          <i class="bi bi-plus-lg sf-faq-icon"></i>
        </button>
        <div class="sf-faq-answer">
          You can start by exploring our products or using our setup tools to plan your business step by step.
        </div>
      </div>

      <div class="sf-faq-item">
        <button type="button" class="sf-faq-question">
          <span>Do you provide support?</span>
          <i class="bi bi-plus-lg sf-faq-icon"></i>
        </button>
        <div class="sf-faq-answer">
          Yes, we provide continuous support and guidance to help you succeed in your business journey.
        </div>
      </div>

      <div class="sf-faq-item">
        <button type="button" class="sf-faq-question">
          <span>Can I find vendors and suppliers here?</span>
          <i class="bi bi-plus-lg sf-faq-icon"></i>
        </button>
        <div class="sf-faq-answer">
          Yes, SetupForge connects you with trusted vendors and suppliers all in one place.
        </div>
      </div>

    </div>
  </main>

  <script>
    document.querySelectorAll(".sf-faq-question").forEach(function(btn) {
      btn.addEventListener("click", function() {
        const item = btn.closest(".sf-faq-item");

        document.querySelectorAll(".sf-faq-item").forEach(function(otherItem) {
          if (otherItem !== item) {
            otherItem.classList.remove("active");
          }
        });

        item.classList.toggle("active");
      });
    });
  </script>

  <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>

</body>
</html>