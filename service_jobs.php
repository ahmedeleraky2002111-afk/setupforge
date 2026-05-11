<?php
session_start();
require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: auth/login.php");
    exit();
}

$business_id = (int)$_SESSION["user_id"];
$acUnits   = 1;
$acRate    = 700;
$acTonnage = "1.5";

$orderRes = pg_query_params($conn, "
    SELECT installation_data
    FROM orders
    WHERE business_user_id = $1
    ORDER BY id DESC
    LIMIT 1
", [$business_id]);

if ($orderRes && pg_num_rows($orderRes) > 0) {
    $orderRow = pg_fetch_assoc($orderRes);
    $instData = json_decode($orderRow["installation_data"], true);
    $areaSqm  = (int)($instData["area_sqm"] ?? 50);
    $acUnits  = max(1, (int)ceil($areaSqm / 40));

    $areaPerUnit = $areaSqm / max(1, $acUnits);
    if      ($areaPerUnit <= 20) { $acTonnage = "1.5"; $acRate = 700; }
    elseif  ($areaPerUnit <= 30) { $acTonnage = "2";   $acRate = 750; }
    elseif  ($areaPerUnit <= 45) { $acTonnage = "2.5"; $acRate = 850; }
    else                         { $acTonnage = "3";   $acRate = 900; }
}
// Load per-company AC rates for the derived tonnage
$acRatesMap = [];
if ($acTonnage) {
    $ratesRes = pg_query_params($conn, "
        SELECT company_id, rate_per_unit
        FROM company_ac_rates
        WHERE tonnage = $1
    ", [$acTonnage]);

    if ($ratesRes) {
        while ($rateRow = pg_fetch_assoc($ratesRes)) {
            $acRatesMap[(int)$rateRow["company_id"]] = (int)$rateRow["rate_per_unit"];
        }
    }
}

$installationRes = pg_query_params($conn, "
    SELECT r.request_id, r.services, r.status, r.created_at
    FROM installation_requests r
    WHERE r.user_id = $1
    ORDER BY r.created_at DESC
", [$business_id]);

$installationRows = [];
if ($installationRes) {
    while ($row = pg_fetch_assoc($installationRes)) {
        $installationRows[] = $row;
    }
}
$hasInstallation = !empty($installationRows);



/* LABOR JOBS = GROUP BY TITLE + LOCATION */
$laborGroupedQuery = pg_query_params($conn, "
    SELECT
        title,
        location,
        MIN(description) AS description,
        COUNT(*) AS total_openings,
        COUNT(*) FILTER (WHERE worker_id IS NULL) AS openings_left,
        COUNT(*) FILTER (WHERE worker_id IS NOT NULL) AS filled_openings
    FROM jobs
    WHERE business_id = $1
      AND job_type = 'labor'
    GROUP BY title, location
    ORDER BY title ASC
", [$business_id]);

$hasLaborJobs = ($laborGroupedQuery && pg_num_rows($laborGroupedQuery) > 0);

$laborRoles = [];
if ($hasLaborJobs) {
    while ($r = pg_fetch_assoc($laborGroupedQuery)) $laborRoles[] = $r;
}

$applicantsMap = [];
$hiredMap = [];
$totalApplicants = 0;

if ($hasLaborJobs) {
    $applicantsQuery = pg_query_params($conn, "
    SELECT
        ja.id AS application_id,
        ja.labor_user_id,
        j.title,
        j.location,
        j.job_id,
        l.name AS worker_name,
        l.experience_level,
        l.avg_rating,
        l.hourly_rate,
        l.skills,
        l.profile_picture,
        l.availability_status,
        l.military_status,
        l.dob,
        l.labor_role,
        ja.status AS app_status
    FROM job_applications ja
    JOIN jobs j ON ja.job_id = j.job_id
    JOIN labors l ON ja.labor_user_id = l.user_id
    WHERE j.business_id = $1
      AND j.job_type = 'labor'
    ORDER BY j.title ASC, ja.applied_at ASC
", [$business_id]);

    if ($applicantsQuery) {
        while ($row = pg_fetch_assoc($applicantsQuery)) {
            $key = $row["title"] . '||' . $row["location"];
            if ($row["app_status"] === 'accepted') {
                $hiredMap[$key][] = $row;
            } else {
                $applicantsMap[$key][] = $row;
            }
        }
    }
    foreach ($applicantsMap as $list) $totalApplicants += count($list);
}

$totalInstallation = count($installationRows);
$bizRes = pg_query_params($conn, "SELECT business_name FROM businesses WHERE user_id = $1 LIMIT 1", [$business_id]);
$businessName = "Your Business";
if ($bizRes && pg_num_rows($bizRes) > 0) {
    $brow = pg_fetch_assoc($bizRes);
    $businessName = $brow["business_name"] ?? "Your Business";
}

function formatInstallationServices($raw) {
    if (!$raw) return "Installation Service";
    $cleaned = trim($raw, '{}');
    $items = explode(',', $cleaned);
    $labels = ['pos' => 'POS System', 'electrical' => 'Electrical Wiring', 'network' => 'Network & WiFi', 'ac' => 'AC Installation', 'kitchen' => 'Kitchen Setup'];
    $out = [];
    foreach ($items as $item) {
        $item = trim($item);
        $out[] = $labels[$item] ?? ucfirst($item);
    }
    return implode(', ', $out);
}
?>
<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Your Service Jobs - SetupForge</title>

  <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
  <link href="assets/style.css" rel="stylesheet">
  <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/bootstrap-icons@1.11.3/font/bootstrap-icons.min.css">
</head>
<body>

<?php include "includes/navbar.php"; ?>

<main class="sf-hc-page">

  <div class="sf-hc-topbar">
    <div class="container">
      <div class="sf-hc-topbar-inner">
        <div>
          <div class="sf-hc-kicker">Post-Payment · Hiring Console</div>
          <p class="sf-hc-biz-sub">Staff up</p>
          <h1 class="sf-hc-biz-name"><?= htmlspecialchars($businessName) ?></h1>
        </div>
        <div class="sf-hc-stats">
          <div class="sf-hc-stat">
            <div class="sf-hc-stat-label">Applicants</div>
            <div class="sf-hc-stat-val"><?= $totalApplicants ?></div>
          </div>
          <div class="sf-hc-stat">
            <div class="sf-hc-stat-label">Installation Services</div>
            <div class="sf-hc-stat-val"><?= $totalInstallation ?></div>
          </div>
        </div>
      </div>

      <div class="sf-hc-tabs">
        <button class="sf-hc-tab is-active" onclick="switchTab('labor', this)">
          Labor <span class="sf-hc-tab-count"><?= $totalApplicants ?></span>
        </button>
        <button class="sf-hc-tab" onclick="switchTab('installation', this)">
          Installation Services <span class="sf-hc-tab-count"><?= $totalInstallation ?></span>
        </button>
      </div>
    </div>
  </div>

  <div class="container sf-hc-body">

    <!-- LABOR PANEL -->
    <div class="sf-hc-panel is-active" id="panel-labor">
      <?php if (empty($laborRoles)): ?>
        <div class="sf-hc-empty">
          <h4>No Labor Jobs Yet</h4>
          <p>Once your setup generates labor positions, they will appear here.</p>
        </div>
      <?php else: ?>

        <div class="sf-hc-roles-bar">
          <?php foreach ($laborRoles as $i => $labor):
            $rKey   = $labor["title"] . '||' . $labor["location"];
            $filled = (int)$labor["filled_openings"];
            $total  = (int)$labor["total_openings"];
          ?>
          <button class="sf-hc-role-pill <?= $i === 0 ? 'is-active' : '' ?>"
            onclick="switchRole('<?= htmlspecialchars(addslashes($rKey)) ?>', this)">
            <?= htmlspecialchars($labor["title"]) ?>
            <span class="sf-hc-role-filled"><?= $filled ?>/<?= $total ?> filled</span>
          </button>
          <?php endforeach; ?>
        </div>

        <?php foreach ($laborRoles as $i => $labor):
          $rKey      = $labor["title"] . '||' . $labor["location"];
          $appList   = $applicantsMap[$rKey] ?? [];
          $hiredList = $hiredMap[$rKey] ?? [];
          $total     = (int)$labor["total_openings"];
          $filled    = (int)$labor["filled_openings"];
          $left      = (int)$labor["openings_left"];
        ?>
        <div class="sf-hc-role-panel <?= $i === 0 ? 'is-active' : '' ?>"
             id="role-<?= htmlspecialchars(base64_encode($rKey)) ?>">

          <div class="sf-hc-role-info">
            <div>
              <div class="sf-hc-role-info-title"><?= htmlspecialchars($labor["title"]) ?></div>
              <div class="sf-hc-role-info-meta">
                <?= htmlspecialchars($labor["location"]) ?> &nbsp;·&nbsp;
                <?= $total ?> opening<?= $total !== 1 ? 's' : '' ?> &nbsp;·&nbsp;
                <?= $filled ?> filled
              </div>
            </div>
            <div style="display:flex;align-items:center;gap:10px;">
              <?php
                $salaryRes = pg_query_params($conn, "
                  SELECT salary_amount, compensation_type FROM jobs
                  WHERE business_id = $1 AND title = $2 AND location = $3 AND job_type = 'labor'
                  LIMIT 1
                ", [$business_id, $labor["title"], $labor["location"]]);
                $salaryRow = ($salaryRes && pg_num_rows($salaryRes) > 0) ? pg_fetch_assoc($salaryRes) : null;
                $hasSalary = $salaryRow && (int)$salaryRow["salary_amount"] > 0;
              ?>
              <?php if ($hasSalary): ?>
                <span style="font-size:.82rem;font-weight:700;color:#15803d;background:#d1fae5;padding:4px 12px;border-radius:999px;border:1px solid rgba(34,197,94,.2);">
                  <?= number_format((int)$salaryRow["salary_amount"]) ?> EGP / <?= htmlspecialchars($salaryRow["compensation_type"]) ?>
                </span>
              <?php else: ?>
                <span style="font-size:.78rem;font-weight:700;color:#b45309;background:#fef9c3;padding:4px 12px;border-radius:999px;border:1px solid rgba(234,179,8,.2);">
                  No salary set
                </span>
              <?php endif; ?>
              <button onclick='openSalaryModal(<?= htmlspecialchars(json_encode([
                "title"    => $labor["title"],
                "location" => $labor["location"],
                "salary_amount"    => (int)($salaryRow["salary_amount"] ?? 0),
                "compensation_type" => $salaryRow["compensation_type"] ?? "monthly",
              ]), ENT_QUOTES) ?>)'
                style="padding:7px 16px;border-radius:10px;background:#004cac;color:#fff;font-size:.8rem;font-weight:700;border:none;cursor:pointer;white-space:nowrap;">
                <i class="bi bi-cash-coin me-1"></i> Set Salary
              </button>
              <span class="sf-hc-tag"><?= $left ?> remaining</span>
            </div>
          </div>

          <div class="sf-hc-board">

            <!-- Applied -->
            <div>
              <div class="sf-hc-col-head">
                <div class="sf-hc-col-title">
                  <span class="sf-hc-col-dot" style="background:#3b82f6"></span>
                  Applied
                </div>
                <span class="sf-hc-col-count"><?= count($appList) ?></span>
              </div>
              <?php if (empty($appList)): ?>
                <div class="sf-hc-col-empty">No applicants yet</div>
              <?php else: ?>
                <?php foreach ($appList as $app):
                  $initials = strtoupper(substr($app["worker_name"] ?? "?", 0, 2));
                  $skills   = array_filter(explode(',', $app["skills"] ?? ''));
                ?>
                <div class="sf-hc-applicant-card">
                  <div class="sf-hc-app-top">
                    <div class="sf-hc-avatar"><?= $initials ?></div>
                    <div>
                      <div class="sf-hc-app-name"><?= htmlspecialchars($app["worker_name"] ?? "—") ?></div>
                      <div class="sf-hc-app-meta"><?= htmlspecialchars($app["experience_level"] ?? "—") ?></div>
                    </div>
                  </div>
                  <?php if (!empty($skills)): ?>
                  <div class="sf-hc-tags">
                    <?php foreach (array_slice($skills, 0, 3) as $s): ?>
                    <span class="sf-hc-tag"><?= htmlspecialchars(trim($s)) ?></span>
                    <?php endforeach; ?>
                  </div>
                  <?php endif; ?>
                  <div class="sf-hc-app-footer">
                    <div class="sf-hc-rating">
                      <i class="bi bi-star-fill" style="font-size:.7rem"></i>
                      <?= number_format((float)($app["avg_rating"] ?? 0), 1) ?>
                    </div>
                    <div class="sf-hc-rate">
                      <?= number_format((float)($app["hourly_rate"] ?? 0), 0) ?>
                      <span style="font-size:.72rem;font-weight:600;color:#6b7280">EGP</span>
                    </div>
                  </div>
                  <button class="sf-hc-view-btn mt-2" onclick='openApplicantModal(<?= htmlspecialchars(json_encode([
                    "application_id" => $app["application_id"],
                    "labor_user_id"  => $app["labor_user_id"],
                    "job_id"         => $app["job_id"],
                    "worker_name"    => $app["worker_name"] ?? "—",
                    "experience_level" => $app["experience_level"] ?? "—",
                    "avg_rating"     => $app["avg_rating"] ?? 0,
                    "hourly_rate"    => $app["hourly_rate"] ?? 0,
                    "skills"         => $app["skills"] ?? "",
                    "profile_picture"=> $app["profile_picture"] ?? "",
                    "availability_status" => $app["availability_status"] ?? "—",
                    "military_status"=> $app["military_status"] ?? "—",
                    "dob"            => $app["dob"] ?? "—",
                    "labor_role"     => $app["labor_role"] ?? "—",
                    "title"          => $app["title"] ?? "",
                    "location"       => $app["location"] ?? "",
                  ]), ENT_QUOTES) ?>)'>
                    <i class="bi bi-eye me-1"></i> View
                  </button>
                </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

            <!-- Hired -->
            <div class="sf-hc-col-hired">
              <div class="sf-hc-col-head">
                <div class="sf-hc-col-title">
                  <span class="sf-hc-col-dot" style="background:#22c55e"></span>
                  Hired
                </div>
                <span class="sf-hc-col-count"><?= count($hiredList) ?></span>
              </div>
              <?php if (empty($hiredList)): ?>
                <div class="sf-hc-col-empty">Empty</div>
              <?php else: ?>
                <?php foreach ($hiredList as $app):
                  $initials = strtoupper(substr($app["worker_name"] ?? "?", 0, 2));
                  $skills   = array_filter(explode(',', $app["skills"] ?? ''));
                ?>
                <div class="sf-hc-applicant-card">
                  <div class="sf-hc-app-top">
                    <div class="sf-hc-avatar" style="background:rgba(34,197,94,.12);color:#15803d"><?= $initials ?></div>
                    <div>
                      <div class="sf-hc-app-name"><?= htmlspecialchars($app["worker_name"] ?? "—") ?></div>
                      <div class="sf-hc-app-meta"><?= htmlspecialchars($app["experience_level"] ?? "—") ?></div>
                    </div>
                  </div>
                  <?php if (!empty($skills)): ?>
                  <div class="sf-hc-tags">
                    <?php foreach (array_slice($skills, 0, 3) as $s): ?>
                    <span class="sf-hc-tag" style="background:#d1fae5;color:#065f46;border-color:rgba(34,197,94,.15)"><?= htmlspecialchars(trim($s)) ?></span>
                    <?php endforeach; ?>
                  </div>
                  <?php endif; ?>
                  <div class="sf-hc-app-footer">
                    <div class="sf-hc-rating">
                      <i class="bi bi-star-fill" style="font-size:.7rem"></i>
                      <?= number_format((float)($app["avg_rating"] ?? 0), 1) ?>
                    </div>
                    <div class="sf-hc-rate">
                      <?= number_format((float)($app["hourly_rate"] ?? 0), 0) ?>
                      <span style="font-size:.72rem;font-weight:600;color:#6b7280">EGP</span>
                    </div>
                  </div>
                  <button class="sf-hc-view-btn mt-2" onclick='openApplicantModal(<?= htmlspecialchars(json_encode([
                    "application_id" => $app["application_id"],
                    "labor_user_id"  => $app["labor_user_id"],
                    "job_id"         => $app["job_id"],
                    "worker_name"    => $app["worker_name"] ?? "—",
                    "experience_level" => $app["experience_level"] ?? "—",
                    "avg_rating"     => $app["avg_rating"] ?? 0,
                    "hourly_rate"    => $app["hourly_rate"] ?? 0,
                    "skills"         => $app["skills"] ?? "",
                    "profile_picture"=> $app["profile_picture"] ?? "",
                    "availability_status" => $app["availability_status"] ?? "—",
                    "military_status"=> $app["military_status"] ?? "—",
                    "dob"            => $app["dob"] ?? "—",
                    "labor_role"     => $app["labor_role"] ?? "—",
                    "title"          => $app["title"] ?? "",
                    "location"       => $app["location"] ?? "",
                    "app_status" => $app["app_status"]
                  ]), ENT_QUOTES) ?>)'>
                    <i class="bi bi-eye me-1"></i> View
                  </button>
                </div>
                <?php endforeach; ?>
              <?php endif; ?>
            </div>

          </div>
        </div>
        <?php endforeach; ?>

      <?php endif; ?>
    </div>

    <!-- INSTALLATION PANEL -->
    <div class="sf-hc-panel" id="panel-installation">
      <?php if (!$hasInstallation): ?>
        <div class="sf-hc-empty">
          <h4>No Installation Services Yet</h4>
          <p>Once your order includes installation services, companies will appear here.</p>
        </div>
      <?php else: ?>
        <?php
       foreach ($installationRows as $req):
          $req_id       = (int)$req["request_id"];
          $serviceLabel = formatInstallationServices($req["services"]);
          $reqStatus    = $req["status"];
          $serviceKey   = trim(explode(',', trim($req["services"], '{}'))[0]);

          $companiesRes = pg_query_params($conn, "
            SELECT c.company_id, c.company_name, c.description, c.starting_from,
                   c.website, c.avg_rating,
                   q.quote_id, q.price, q.message, q.website_link, q.status AS quote_status
            FROM companies c
            LEFT JOIN installation_quotes q
              ON q.company_id = c.company_id AND q.request_id = $1
            WHERE c.services::text ILIKE $2
              AND c.availability_status = 'available'
            ORDER BY q.price ASC NULLS LAST, c.company_id ASC
          ", [$req_id, '%' . $serviceKey . '%']);

          $companyList = [];
          if ($companiesRes) while ($co = pg_fetch_assoc($companiesRes)) $companyList[] = $co;
        ?>
        <div class="sf-ins-service-block">
          <div class="sf-ins-service-label">
            <i class="bi bi-tools" style="color:#004cac"></i>
            <span class="sf-ins-service-title"><?= htmlspecialchars($serviceLabel) ?></span>
            <span class="sf-ins-status-badge sf-ins-status-<?= htmlspecialchars($reqStatus) ?>">
              <?= ucfirst(str_replace('_', ' ', $reqStatus)) ?>
            </span>
          </div>

          <?php if (empty($companyList)): ?>
            <div class="sf-hc-empty"><h4>No Companies Available</h4></div>
          <?php else: ?>
            <div class="sf-ins-grid">
              <?php foreach ($companyList as $idx => $co):
                $hasQuote      = !empty($co["quote_id"]);
                $quoteAccepted = $hasQuote && $co["quote_status"] === 'accepted';
                $quoteRejected = $hasQuote && $co["quote_status"] === 'rejected';

                if ($hasQuote && !$quoteRejected) {
                  $displayPrice = number_format((float)$co["price"], 0) . ' EGP';
                  $priceLabel   = "Quote Received";
                  $priceSub     = $co["message"] ? htmlspecialchars($co["message"]) : '';
                } else {
                  if ($co["starting_from"]) {
                    if ($serviceKey === 'ac') {
                      $rateToUse    = $acRatesMap[(int)$co["company_id"]] ?? $acRate;
                      $displayPrice = number_format($rateToUse * $acUnits, 0) . ' EGP';
                      $priceSub     = $acTonnage . ' ton × ' . $acUnits . ' units';
                    } else {
                      $displayPrice = number_format((int)$co["starting_from"], 0) . ' EGP';
                      $priceSub     = '';
                    }
                    $priceLabel = "Starting from";
                  } else {
                    $displayPrice = 'TBD'; $priceLabel = "Price"; $priceSub = '';
                  }
                }

                if ($quoteAccepted)                   { $badgeClass = 'sf-ins-badge-accepted'; $badgeText = 'Accepted'; }
                elseif ($hasQuote && !$quoteRejected) { $badgeClass = 'sf-ins-badge-quote';    $badgeText = 'Quote Received'; }
                elseif ($quoteRejected)               { $badgeClass = 'sf-ins-badge-rejected';  $badgeText = 'Not Selected'; }
                else                                  { $badgeClass = 'sf-ins-badge-awaiting';  $badgeText = 'Awaiting Quote'; }
              ?>
              <div class="sf-ins-company-card <?= $quoteAccepted ? 'is-accepted' : '' ?>">
                <?php if ($idx === 0): ?><div class="sf-ins-recommended">Recommended</div><?php endif; ?>

                <div class="sf-ins-company-top">
                  <div>
                    <div class="sf-ins-company-name"><?= htmlspecialchars($co["company_name"]) ?></div>
                    <?php if ($co["avg_rating"]): ?>
                    <div class="sf-ins-rating" style="margin-top:4px">
                      <i class="bi bi-star-fill" style="font-size:.7rem"></i>
                      <?= number_format((float)$co["avg_rating"], 1) ?>
                    </div>
                    <?php endif; ?>
                  </div>
                  <span class="sf-ins-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                </div>

                <?php if ($co["description"]): ?>
                <div class="sf-ins-company-desc"><?= htmlspecialchars($co["description"]) ?></div>
                <?php endif; ?>

                <div>
                  <div class="sf-ins-price-label"><?= $priceLabel ?></div>
                  <div class="sf-ins-price"><?= $displayPrice ?></div>
                  <?php if (!empty($priceSub)): ?>
                  <div class="sf-ins-price-sub"><?= $priceSub ?></div>
                  <?php endif; ?>
                </div>

                <div class="sf-ins-actions">
                  <?php if ($co["website"] || $co["website_link"]): ?>
                  <a href="<?= htmlspecialchars($co["website"] ?? $co["website_link"]) ?>"
                     target="_blank" class="sf-ins-btn-website">
                    <i class="bi bi-box-arrow-up-right me-1"></i> Website
                  </a>
                  <?php endif; ?>
                  <?php if ($hasQuote && $co["quote_status"] === 'pending' && $reqStatus === 'pending'): ?>
                  <form action="accept_quote.php" method="POST" class="m-0">
                    <input type="hidden" name="quote_id" value="<?= (int)$co["quote_id"] ?>">
                    <input type="hidden" name="request_id" value="<?= $req_id ?>">
                    <button type="submit" class="sf-ins-btn-accept">Accept Quote</button>
                  </form>
                  <?php endif; ?>
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          <?php endif; ?>
        </div>
<?php endforeach; ?>
      <?php endif; ?>
    </div>

    <?php if (empty($laborRoles) && !$hasInstallation): ?>
    <div class="sf-hc-empty mt-4">
      <h4>No Services Yet</h4>
      <p>Once your setup generates jobs or installation services, they will appear here.</p>
    </div>
    <?php endif; ?>

  </div>
  <!-- Salary Modal -->
<div id="sf-salary-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:20px;width:min(400px,95vw);padding:28px;position:relative;box-shadow:0 24px 60px rgba(0,0,0,.18);">
    <button onclick="closeSalaryModal()" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:1.3rem;color:#6b7280;cursor:pointer;">
      <i class="bi bi-x-lg"></i>
    </button>
    <div style="font-size:1.05rem;font-weight:800;color:#111827;margin-bottom:4px;">Set Salary</div>
    <div id="salary-modal-role" style="font-size:.82rem;color:#6b7280;margin-bottom:20px;"></div>

    <div style="margin-bottom:14px;">
      <label style="font-size:.82rem;font-weight:700;color:#374151;display:block;margin-bottom:6px;">Amount (EGP)</label>
      <input type="number" id="salary-amount-input" min="0" placeholder="e.g. 5000"
        style="width:100%;padding:10px 14px;border-radius:10px;border:1px solid rgba(0,0,0,.14);font-size:.95rem;outline:none;">
    </div>
    <div style="margin-bottom:24px;">
      <label style="font-size:.82rem;font-weight:700;color:#374151;display:block;margin-bottom:6px;">Per</label>
      <select id="salary-type-select"
        style="width:100%;padding:10px 14px;border-radius:10px;border:1px solid rgba(0,0,0,.14);font-size:.95rem;outline:none;background:#fff;">
        <option value="monthly">Month</option>
        <option value="daily">Day</option>
        <option value="hourly">Hour</option>
      </select>
    </div>

    <input type="hidden" id="salary-modal-title">
    <input type="hidden" id="salary-modal-location">

    <button onclick="saveSalary()"
      style="width:100%;padding:12px;border-radius:10px;background:#004cac;color:#fff;font-weight:700;border:none;cursor:pointer;font-size:.95rem;">
      <i class="bi bi-check2-circle me-1"></i> Save
    </button>
    <div id="salary-save-msg" style="margin-top:10px;font-size:.82rem;text-align:center;"></div>
  </div>
</div>
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
function switchTab(tab, btn) {
  document.querySelectorAll('.sf-hc-tab').forEach(t => t.classList.remove('is-active'));
  document.querySelectorAll('.sf-hc-panel').forEach(p => p.classList.remove('is-active'));
  btn.classList.add('is-active');
  document.getElementById('panel-' + tab).classList.add('is-active');
}
function switchRole(rKey, btn) {
  document.querySelectorAll('.sf-hc-role-pill').forEach(p => p.classList.remove('is-active'));
  document.querySelectorAll('.sf-hc-role-panel').forEach(p => p.classList.remove('is-active'));
  btn.classList.add('is-active');
  const panel = document.getElementById('role-' + btoa(unescape(encodeURIComponent(rKey))));
  if (panel) panel.classList.add('is-active');
}
</script>
<!-- Applicant Modal -->
<div id="sf-applicant-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:20px;width:min(520px,95vw);max-height:88vh;overflow-y:auto;padding:28px;position:relative;box-shadow:0 24px 60px rgba(0,0,0,.18);">
    
    <button onclick="closeApplicantModal()" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:1.3rem;color:#6b7280;cursor:pointer;">
      <i class="bi bi-x-lg"></i>
    </button>

    <!-- Header -->
    <div style="display:flex;align-items:center;gap:16px;margin-bottom:20px;">
      <div id="modal-avatar" style="width:64px;height:64px;border-radius:50%;overflow:hidden;background:rgba(0,76,172,.10);display:flex;align-items:center;justify-content:center;font-size:1.4rem;font-weight:800;color:#004cac;flex-shrink:0;"></div>
      <div>
        <div id="modal-name" style="font-size:1.15rem;font-weight:800;color:#111827;"></div>
        <div id="modal-role" style="font-size:.8rem;font-weight:700;color:#fff;background:#004cac;padding:3px 10px;border-radius:999px;display:inline-block;margin-top:4px;"></div>
      </div>
    </div>

    <!-- Info grid -->
    <div style="display:grid;grid-template-columns:1fr 1fr;gap:10px;margin-bottom:18px;">
      <div style="background:#f8fafc;border:1px solid rgba(0,0,0,.06);border-radius:12px;padding:12px;">
        <div style="font-size:.7rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;">Experience</div>
        <div id="modal-experience" style="font-size:.95rem;font-weight:700;color:#111827;"></div>
      </div>
      <div style="background:#f8fafc;border:1px solid rgba(0,0,0,.06);border-radius:12px;padding:12px;">
        <div style="font-size:.7rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;">Rating</div>
        <div id="modal-rating" style="font-size:.95rem;font-weight:700;color:#b45309;"></div>
      </div>
      <div style="background:#f8fafc;border:1px solid rgba(0,0,0,.06);border-radius:12px;padding:12px;">
        <div style="font-size:.7rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;">Hourly Rate</div>
        <div id="modal-rate" style="font-size:.95rem;font-weight:700;color:#111827;"></div>
      </div>
      <div style="background:#f8fafc;border:1px solid rgba(0,0,0,.06);border-radius:12px;padding:12px;">
        <div style="font-size:.7rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;">Availability</div>
        <div id="modal-availability" style="font-size:.95rem;font-weight:700;color:#111827;"></div>
      </div>
      <div style="background:#f8fafc;border:1px solid rgba(0,0,0,.06);border-radius:12px;padding:12px;">
        <div style="font-size:.7rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;">Military Status</div>
        <div id="modal-military" style="font-size:.95rem;font-weight:700;color:#111827;"></div>
      </div>
      <div style="background:#f8fafc;border:1px solid rgba(0,0,0,.06);border-radius:12px;padding:12px;">
        <div style="font-size:.7rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;margin-bottom:4px;">Date of Birth</div>
        <div id="modal-dob" style="font-size:.95rem;font-weight:700;color:#111827;"></div>
      </div>
    </div>

    <!-- Skills -->
    <div style="margin-bottom:18px;">
      <div style="font-size:.7rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">Skills</div>
      <div id="modal-skills" style="display:flex;flex-wrap:wrap;gap:6px;"></div>
    </div>

    <!-- Documents -->
    <div style="margin-bottom:24px;">
      <div style="font-size:.7rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.06em;margin-bottom:8px;">Documents</div>
      <div id="modal-docs" style="font-size:.85rem;color:#6b7280;">Loading...</div>
    </div>

    <!-- Footer buttons -->
    <div style="display:flex;gap:10px;padding-top:16px;border-top:1px solid rgba(0,0,0,.08);">
      <form id="modal-hire-form" action="hire_applicant.php" method="POST" style="flex:1;">
        <input type="hidden" name="application_id" id="modal-app-id">
        <input type="hidden" name="labor_user_id" id="modal-labor-id">
        <input type="hidden" name="job_id" id="modal-job-id">
        <input type="hidden" name="title" id="modal-title">
        <input type="hidden" name="location" id="modal-location">
        <button type="submit" style="width:100%;padding:11px;border-radius:10px;background:#004cac;color:#fff;font-weight:700;border:none;cursor:pointer;font-size:.92rem;">
          <i class="bi bi-check2-circle me-1"></i> Hire
        </button>
      </form>
      <button onclick="closeApplicantModal()" style="padding:11px 20px;border-radius:10px;background:#f1f5f9;color:#374151;font-weight:700;border:none;cursor:pointer;font-size:.92rem;">
        Close
      </button>
    </div>

  </div>
</div>

<script>
function openApplicantModal(data) {
  // Avatar
  const avatar = document.getElementById('modal-avatar');
  if (data.profile_picture) {
    avatar.innerHTML = '<img src="' + data.profile_picture + '" style="width:100%;height:100%;object-fit:cover;">';
  } else {
    avatar.textContent = (data.worker_name || '?').substring(0, 2).toUpperCase();
  }

  document.getElementById('modal-name').textContent = data.worker_name;
  document.getElementById('modal-role').textContent = data.labor_role || data.title;
  document.getElementById('modal-experience').textContent = data.experience_level || '—';
  document.getElementById('modal-rating').textContent = '⭐ ' + parseFloat(data.avg_rating || 0).toFixed(1);
  document.getElementById('modal-rate').textContent = parseInt(data.hourly_rate || 0).toLocaleString() + ' EGP/hr';
  document.getElementById('modal-availability').textContent = data.availability_status || '—';
  document.getElementById('modal-military').textContent = data.military_status || '—';
  document.getElementById('modal-dob').textContent = data.dob || '—';

  // Skills
  const skillsEl = document.getElementById('modal-skills');
  skillsEl.innerHTML = '';
  if (data.skills) {
    data.skills.split(',').filter(s => s.trim()).forEach(s => {
      const tag = document.createElement('span');
      tag.textContent = s.trim();
      tag.style.cssText = 'display:inline-flex;align-items:center;padding:4px 10px;border-radius:999px;background:#f1f5f9;border:1px solid rgba(0,0,0,.06);font-size:.72rem;font-weight:700;color:#475569;';
      skillsEl.appendChild(tag);
    });
  } else {
    skillsEl.textContent = 'No skills listed';
  }

  // Hidden form fields
  document.getElementById('modal-app-id').value   = data.application_id;
  document.getElementById('modal-labor-id').value = data.labor_user_id;
  document.getElementById('modal-job-id').value   = data.job_id;
  document.getElementById('modal-title').value    = data.title;
  document.getElementById('modal-location').value = data.location;

  // Fetch documents via AJAX
  document.getElementById('modal-docs').innerHTML = 'Loading...';
  fetch('get_labor_docs.php?labor_user_id=' + data.labor_user_id)
    .then(r => r.json())
    .then(docs => {
      const docsEl = document.getElementById('modal-docs');
      if (!docs.length) {
        docsEl.textContent = 'No documents uploaded yet.';
        return;
      }
      docsEl.innerHTML = '';
      docs.forEach(doc => {
        const row = document.createElement('div');
        row.style.cssText = 'display:flex;align-items:center;justify-content:space-between;padding:8px 12px;border-radius:8px;background:#f8fafc;border:1px solid rgba(0,0,0,.06);margin-bottom:6px;';
        row.innerHTML = '<span style="font-weight:700;color:#111827;font-size:.85rem;">' + doc.doc_type + '</span>' +
          '<span style="font-size:.75rem;font-weight:700;padding:3px 10px;border-radius:999px;background:' + 
          (doc.status === 'verified' ? '#d1fae5;color:#065f46' : doc.status === 'rejected' ? '#fee2e2;color:#991b1b' : '#fef9c3;color:#854d0e') + 
          ';">' + doc.status + '</span>';
        docsEl.appendChild(row);
      });
    })
    .catch(() => {
      document.getElementById('modal-docs').textContent = 'Could not load documents.';
    });

    // Hide hire button if already hired
  const hireForm = document.getElementById('modal-hire-form');
  if (data.app_status === 'accepted') {
    hireForm.innerHTML = '<div style="width:100%;padding:11px;border-radius:10px;background:#d1fae5;color:#15803d;font-weight:700;font-size:.92rem;text-align:center;"><i class="bi bi-check2-circle me-1"></i> Hired</div>';
  } else {
    hireForm.innerHTML = '<input type="hidden" name="application_id" id="modal-app-id"><input type="hidden" name="labor_user_id" id="modal-labor-id"><input type="hidden" name="job_id" id="modal-job-id"><input type="hidden" name="title" id="modal-title"><input type="hidden" name="location" id="modal-location"><button type="submit" style="width:100%;padding:11px;border-radius:10px;background:#004cac;color:#fff;font-weight:700;border:none;cursor:pointer;font-size:.92rem;"><i class="bi bi-check2-circle me-1"></i> Hire</button>';
    document.getElementById('modal-app-id').value   = data.application_id;
    document.getElementById('modal-labor-id').value = data.labor_user_id;
    document.getElementById('modal-job-id').value   = data.job_id;
    document.getElementById('modal-title').value    = data.title;
    document.getElementById('modal-location').value = data.location;
  }
  // Show modal
  const modal = document.getElementById('sf-applicant-modal');
  modal.style.display = 'flex';
}

function closeApplicantModal() {
  document.getElementById('sf-applicant-modal').style.display = 'none';
}

function openSalaryModal(data) {
  document.getElementById('salary-modal-role').textContent = data.title + ' · ' + data.location;
  document.getElementById('salary-amount-input').value = data.salary_amount || '';
  document.getElementById('salary-type-select').value = data.compensation_type || 'monthly';
  document.getElementById('salary-modal-title').value = data.title;
  document.getElementById('salary-modal-location').value = data.location;
  document.getElementById('salary-save-msg').textContent = '';
  document.getElementById('sf-salary-modal').style.display = 'flex';
}
function closeSalaryModal() {
  document.getElementById('sf-salary-modal').style.display = 'none';
}
function saveSalary() {
  const title    = document.getElementById('salary-modal-title').value;
  const location = document.getElementById('salary-modal-location').value;
  const amount   = parseInt(document.getElementById('salary-amount-input').value) || 0;
  const type     = document.getElementById('salary-type-select').value;
  const msg      = document.getElementById('salary-save-msg');

  msg.textContent = 'Saving...';
  msg.style.color = '#6b7280';

  fetch('set_job_salary.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ title, location, salary_amount: amount, compensation_type: type })
  })
  .then(r => r.json())
  .then(res => {
    if (res.ok) {
      msg.textContent = 'Saved!';
      msg.style.color = '#15803d';
setTimeout(() => { closeSalaryModal(); window.location.reload(); }, 800);
    } else {
      msg.textContent = res.error || 'Failed to save.';
      msg.style.color = '#dc2626';
    }
  })
  .catch(() => { msg.textContent = 'Network error.'; msg.style.color = '#dc2626'; });
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
</body>
</html>
