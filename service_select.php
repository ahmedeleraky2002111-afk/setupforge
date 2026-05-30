<?php
session_start();
require_once "db.php";

// If user is logged in and has a completed paid equipment order → business_overview
$userId = isset($_SESSION['user_id']) ? (int)$_SESSION['user_id'] : 0;
if ($userId) {
    $paidRes = pg_query_params($conn,
        "SELECT 1 FROM orders WHERE business_user_id = $1 AND payment_status = 'paid' AND order_type = 'setup' AND order_total > 0 LIMIT 1",
        [$userId]);
    if ($paidRes && pg_num_rows($paidRes) > 0) {
        header("Location: business_overview.php");
        exit;
    }
}

// Handle preselect from home page cards
$preselect = $_GET['preselect'] ?? '';
$validServices = ['equipment', 'staff', 'installation', 'finishing', 'advertising', 'full_setup'];
if ($preselect && in_array($preselect, $validServices)) {
    $_SESSION['wizard']['services'] = [$preselect];
    // installation needs equipment, push to full setup
    if ($preselect === 'installation') {
    $_SESSION['wizard']['services'] = ['installation'];
}
    if ($preselect === 'full_setup') {
        $_SESSION['wizard']['services'] = ['equipment', 'staff', 'installation', 'finishing', 'advertising'];
        header("Location: setup.php?step=0");
        exit;
    }
    header("Location: setup.php?step=0");
    exit;
}

// Handle form submit
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $selected = $_POST['services'] ?? [];
    $selected = array_filter($selected, fn($s) => in_array($s, $validServices));
    $selected = array_values($selected);
    if (in_array('full_setup', $selected)) {
    $selected = ['equipment', 'staff', 'installation', 'finishing', 'advertising'];
}

    if (empty($selected)) {
        $error = "Please select at least one service.";
    } else {
        

        $_SESSION['wizard']['services'] = $selected;
        header("Location: setup.php?step=0");
        exit;
    }
}

$error = $error ?? '';
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>What do you need? – SetupForge</title>
  <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700;800;900&display=swap" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css" rel="stylesheet">
  <link href="assets/style.css?v=10" rel="stylesheet">
</head>
<body>

<?php include "includes/navbar.php"; ?>

<main style="min-height:calc(100vh - 90px); padding: 60px 16px 80px;">
  <div style="max-width:900px; margin:0 auto;">

    <h1 class="sf-name-title" style="text-align:center; margin-bottom:10px;">
      What do you need for your business?
    </h1>
    <p class="sf-name-sub" style="text-align:center; margin-bottom:48px;">
      Select one or more. We'll build the right flow for you.
    </p>

    <?php if ($error): ?>
      <div style="background:#fee2e2;color:#dc2626;padding:12px 18px;border-radius:8px;margin-bottom:24px;font-weight:600;">
        <?= htmlspecialchars($error) ?>
      </div>
    <?php endif; ?>

    <form method="POST">
      <div style="display:grid; grid-template-columns: repeat(auto-fill, minmax(260px, 1fr)); gap:20px; margin-bottom:40px;">

        <?php
        $cards = [
          'full_setup'  => ['icon'=>'bi-stars',                   'title'=>'Full Setup',             'desc'=>'Everything equipment, staff, installation, finishing and advertising. The complete package.'],
          'equipment'   => ['icon'=>'bi-box-seam',               'title'=>'Equipment & Products',  'desc'=>'Kitchen, POS, furniture, AC full product setup with delivery.'],
          'staff'       => ['icon'=>'bi-people',                  'title'=>'Staff & Labor',          'desc'=>'Hire waiters, chefs, cashiers and other roles for your business.'],
          'installation'=> ['icon'=>'bi-wrench-adjustable-circle','title'=>'Installation',           'desc'=>'Professional installation for equipment, electrical, AC and network.'],
          'finishing'   => ['icon'=>'bi-brush',                   'title'=>'Finishing',              'desc'=>'Painting, flooring, gypsum, decor and facades.'],
          'advertising' => ['icon'=>'bi-megaphone',               'title'=>'Advertising',            'desc'=>'Connect with advertising companies to promote your business.'],
        ];
        $preSelected = $_SESSION['wizard']['services'] ?? [];
        foreach ($cards as $key => $card):
          $checked = in_array($key, $preSelected);
        ?>
        <label class="sf-svc-card <?= $checked ? 'is-selected' : '' ?>" for="svc_<?= $key ?>" data-key="<?= $key ?>">

          <input type="checkbox" name="services[]" value="<?= $key ?>" id="svc_<?= $key ?>" <?= $checked ? 'checked' : '' ?> hidden>
          <div class="sf-svc-card-top">
            <div class="sf-svc-icon"><i class="bi <?= $card['icon'] ?>"></i></div>
            <div class="sf-svc-check"><i class="bi bi-check2"></i></div>
          </div>
          <div class="sf-svc-title"><?= $card['title'] ?></div>
          <div class="sf-svc-desc"><?= $card['desc'] ?></div>
        </label>
        <?php endforeach; ?>

      </div>

      <div style="display:flex; justify-content:center;">
        <button type="submit" class="sf-name-btn" style="min-width:200px;">
          Continue →
        </button>
      </div>
    </form>

  </div>
</main>

<script>
document.querySelectorAll('.sf-svc-card').forEach(card => {
  card.addEventListener('click', (e) => {
    e.preventDefault();
    const key = card.querySelector('input[type="checkbox"]').value;

    if (key === 'full_setup') {
      // Toggle full setup
      const isSelected = card.classList.contains('is-selected');
      // Select or deselect all cards
      document.querySelectorAll('.sf-svc-card').forEach(c => {
        const cb = c.querySelector('input[type="checkbox"]');
        if (isSelected) {
          c.classList.remove('is-selected');
          cb.checked = false;
        } else {
          c.classList.add('is-selected');
          cb.checked = true;
        }
      });
    } else {
      // Toggle individual card
      card.classList.toggle('is-selected');
      card.querySelector('input[type="checkbox"]').checked = card.classList.contains('is-selected');

      // If any individual card is deselected, deselect full_setup too
      const fullSetupCard = document.querySelector('.sf-svc-card[data-key="full_setup"]');
      if (fullSetupCard) {
        fullSetupCard.classList.remove('is-selected');
        fullSetupCard.querySelector('input[type="checkbox"]').checked = false;
      }
    }
  });
});
</script>




<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>