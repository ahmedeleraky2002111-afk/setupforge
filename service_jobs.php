<?php
session_start();

require_once "db.php";

if (!isset($_SESSION["user_id"])) {
    header("Location: auth/login.php");
    exit();
}

$business_id = (int)$_SESSION["user_id"];

pg_query($conn, "ALTER TABLE installation_requests ADD COLUMN IF NOT EXISTS scheduled_date DATE");
// Handle finishing types selection
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "save_finishing_types") {
    $selectedTypes = $_POST["finishing_types"] ?? [];
    $allowedTypes  = ['painting', 'flooring', 'gypsum', 'decor', 'facades'];
    $cleanTypes    = array_filter($selectedTypes, fn($t) => in_array($t, $allowedTypes));
    $pgArray       = empty($cleanTypes) ? null : '{' . implode(',', $cleanTypes) . '}';

    pg_query_params($conn,
        "UPDATE finishing_requests SET finishing_types = $1 WHERE user_id = $2",
        [$pgArray, $business_id]);

    header("Location: service_jobs.php?tab=finishing");
    exit();
}

// Handle finishing quote acceptance
if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "accept_finishing_quote") {
    $fQuoteId  = (int)($_POST["quote_id"]   ?? 0);
    $fReqId    = (int)($_POST["request_id"] ?? 0);

    $fCheck = pg_query_params($conn,
        "SELECT request_id FROM finishing_requests WHERE request_id = $1 AND user_id = $2 LIMIT 1",
        [$fReqId, $business_id]);

    if ($fCheck && pg_num_rows($fCheck) > 0) {
        $fQuoteRow = pg_query_params($conn,
            "SELECT company_id FROM finishing_quotes WHERE quote_id = $1 AND request_id = $2 LIMIT 1",
            [$fQuoteId, $fReqId]);

        if ($fQuoteRow && pg_num_rows($fQuoteRow) > 0) {
            $fCompany = pg_fetch_assoc($fQuoteRow);
            pg_query($conn, "BEGIN");
            pg_query_params($conn,
                "UPDATE finishing_quotes SET status = 'accepted' WHERE quote_id = $1",
                [$fQuoteId]);
            pg_query_params($conn,
                "UPDATE finishing_quotes SET status = 'rejected' WHERE request_id = $1 AND quote_id != $2",
                [$fReqId, $fQuoteId]);
            pg_query_params($conn,
                "UPDATE finishing_requests SET company_id = $1, status = 'accepted' WHERE request_id = $2",
                [$fCompany["company_id"], $fReqId]);
            pg_query($conn, "COMMIT");
        }
    }
    header("Location: service_jobs.php?tab=finishing");
    exit();
}


if ($_SERVER["REQUEST_METHOD"] === "POST" && isset($_POST["action"]) && $_POST["action"] === "schedule_installation") {
    $req_id_post   = (int)($_POST["request_id"] ?? 0);
    $schedule_date = trim($_POST["scheduled_date"] ?? "");
    if ($req_id_post > 0 && $schedule_date !== "") {
        pg_query_params($conn,
            "UPDATE installation_requests SET scheduled_date = $1 WHERE request_id = $2 AND user_id = $3",
            [$schedule_date, $req_id_post, $business_id]);
    }
    header("Location: service_jobs.php?tab=installation");
    exit();
}

// NEW - replace with:
$acUnits = 1;
$acHp    = 1.5;
$acRate  = 700;
$orderId  = null;
$instData = [];

$orderRes = pg_query_params($conn, "
    SELECT id, installation_data
    FROM orders
    WHERE business_user_id = $1
    AND order_type = 'setup'
    ORDER BY id DESC
    LIMIT 1
", [$business_id]);

if ($orderRes && pg_num_rows($orderRes) > 0) {
    $orderRow = pg_fetch_assoc($orderRes);
    $orderId  = (int)$orderRow["id"];
    $instData = json_decode($orderRow["installation_data"], true) ?? [];

    // Use HP values saved by packages.php
    $acUnits = max(1, (int)($instData["ac_units"] ?? 1));
    $acHp    = (float)($instData["ac_hp"]    ?? 1.5);

    // Default rate fallback by HP
    if      ($acHp <= 1.5) $acRate = 700;
    elseif  ($acHp <= 2.5) $acRate = 850;
    elseif  ($acHp <= 3.0) $acRate = 900;
    else                   $acRate = 950;
}

$maxDeliveryDate = null;
if ($orderId) {
    $dvRes = pg_query_params($conn,
        "SELECT MAX(estimated_delivery_date) AS max_date
         FROM vendor_order_fulfillments
         WHERE order_id = $1",
        [$orderId]);
    if ($dvRes && pg_num_rows($dvRes) > 0) {
        $maxDeliveryDate = pg_fetch_assoc($dvRes)["max_date"] ?? null;
    }
}
$minScheduleDate = $maxDeliveryDate
    ? date('Y-m-d', strtotime($maxDeliveryDate . ' +1 day'))
    : date('Y-m-d');

// NEW:
$acRatesMap = [];
if ($acHp) {
    $ratesRes = pg_query_params($conn, "
        SELECT company_id, rate_per_unit
        FROM company_ac_rates
        WHERE hp = $1
    ", [$acHp]);
    if ($ratesRes) {
        while ($rateRow = pg_fetch_assoc($ratesRes)) {
            $acRatesMap[(int)$rateRow["company_id"]] = (int)$rateRow["rate_per_unit"];
        }
    }
}

// Load order items with product info (kitchen + pos only)
$orderItems = [];
if ($orderId) {
    $itemsRes = pg_query_params($conn, "
        SELECT oi.product_id, oi.quantity, p.product_name, p.product_type, p.module,
               p.specs
        FROM order_items oi
        JOIN products p ON p.id = oi.product_id
        WHERE oi.order_id = $1
          AND p.module IN ('kitchen', 'pos', 'ac')
    ", [$orderId]);
    if ($itemsRes) {
        while ($item = pg_fetch_assoc($itemsRes)) {
            $orderItems[] = $item;
        }
    }
}

// Build per-company kitchen breakdown
// Structure: $kitchenBreakdown[company_id] = [['name'=>, 'qty'=>, 'rate'=>, 'subtotal'=>, 'image'=>], ...]
$kitchenBreakdown = [];
$kitchenRatesRes = pg_query($conn, "SELECT company_id, product_type, rate FROM company_kitchen_rates");
$kitchenRatesAll = [];
if ($kitchenRatesRes) {
    while ($r = pg_fetch_assoc($kitchenRatesRes)) {
        $kitchenRatesAll[(int)$r["company_id"]][$r["product_type"]] = (int)$r["rate"];
    }
}

// Build per-company POS breakdown
$posBreakdown = [];
$posRatesRes = pg_query($conn, "SELECT company_id, terminal_type, rate FROM company_pos_rates");
$posRatesAll = [];
if ($posRatesRes) {
    while ($r = pg_fetch_assoc($posRatesRes)) {
        $posRatesAll[(int)$r["company_id"]][$r["terminal_type"]] = (int)$r["rate"];
    }
}

foreach ($orderItems as $item) {
    $ptype = strtolower(trim($item["product_type"]));
    $qty   = (int)$item["quantity"];
    $name  = $item["product_name"];
    $imgRes = pg_query_params($conn, "SELECT image_url FROM product_images WHERE product_id = $1 LIMIT 1", [$item["product_id"]]);
    $image = ($imgRes && pg_num_rows($imgRes) > 0) ? pg_fetch_assoc($imgRes)["image_url"] : null;

    if ($item["module"] === "kitchen") {
        foreach ($kitchenRatesAll as $cid => $rates) {
            $rate = $rates[$ptype] ?? null;
            if (!$rate) continue;
            $kitchenBreakdown[$cid][] = [
                "name"     => $name,
                "qty"      => $qty,
                "rate"     => $rate,
                "subtotal" => $rate * $qty,
                "image"    => $image,
                "ptype"    => $ptype,
            ];
        }
    }

    if ($item["module"] === "pos") {
        foreach ($posRatesAll as $cid => $rates) {
            $rate = $rates[$ptype] ?? null;
            if (!$rate) continue;
            $posBreakdown[$cid][] = [
                "name"     => $name,
                "qty"      => $qty,
                "rate"     => $rate,
                "subtotal" => $rate * $qty,
                "image"    => $image,
                "ptype"    => $ptype,
            ];
        }
    }
}

// Helper: sum a breakdown array
function breakdownTotal($lines) {
    return array_sum(array_column($lines, "subtotal"));
}

$installationRes = pg_query_params($conn, "
    SELECT r.request_id, r.services, r.status, r.created_at, r.scheduled_date
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
// FINISHING REQUEST
$finishingReqRes = pg_query_params($conn, "
    SELECT request_id, area_sqm, finishing_types, status, scheduled_date
    FROM finishing_requests
    WHERE user_id = $1
    ORDER BY created_at DESC
    LIMIT 1
", [$business_id]);

$finishingReq = null;
if ($finishingReqRes && pg_num_rows($finishingReqRes) > 0) {
    $finishingReq = pg_fetch_assoc($finishingReqRes);
}
$hasFinishing = $finishingReq !== null;

// Parse finishing types
$finishingTypes = [];
if ($hasFinishing && !empty($finishingReq["finishing_types"])) {
    $raw = trim($finishingReq["finishing_types"], '{}');
    $finishingTypes = array_filter(array_map('trim', explode(',', $raw)));
}

// Load finishing companies + their quotes
$finishingList = [];
$totalFinishing = 0;
if ($hasFinishing) {
    $fReqId = (int)$finishingReq["request_id"];
    $finishingCompRes = pg_query($conn, "
        SELECT c.company_id, c.company_name, c.description, c.starting_from,
               c.website, c.avg_rating, c.location, c.image, c.specialties,
               q.quote_id, q.price, q.message, q.website_link, q.status AS quote_status
        FROM companies c
        LEFT JOIN finishing_quotes q
          ON q.company_id = c.company_id AND q.request_id = {$fReqId}
        WHERE c.services::text ILIKE '%finishing%'
          AND c.availability_status = 'available'
          AND c.status = 'active'
        ORDER BY q.price ASC NULLS LAST, c.company_id ASC
    ");
    if ($finishingCompRes) {
        while ($row = pg_fetch_assoc($finishingCompRes)) $finishingList[] = $row;
    }
    $totalFinishing = count($finishingList);
}

// ADVERTISING COMPANIES
$advertisingRes = pg_query($conn, "
    SELECT company_id, company_name, description, starting_from,
           website, avg_rating, location, image
    FROM companies
    WHERE services::text ILIKE '%advertising%'
      AND availability_status = 'available'
      AND status = 'active'
    ORDER BY company_id ASC
");
$advertisingList = [];
if ($advertisingRes) {
    while ($row = pg_fetch_assoc($advertisingRes)) $advertisingList[] = $row;
}
$totalAdvertising = count($advertisingList);

$bizRes = pg_query_params($conn, "SELECT business_name FROM businesses WHERE user_id = $1 LIMIT 1", [$business_id]);
$businessName = "Your Business";
if ($bizRes && pg_num_rows($bizRes) > 0) {
    $brow = pg_fetch_assoc($bizRes);
    $businessName = $brow["business_name"] ?? "Your Business";
}

$myCompanyReviews = [];
$crRes = pg_query_params($conn,
    "SELECT company_id, rating, comment FROM company_reviews WHERE user_id = $1",
    [$business_id]);
if ($crRes) {
    while ($row = pg_fetch_assoc($crRes)) {
        $myCompanyReviews[(int)$row["company_id"]] = $row;
    }
}

function renderCompanyStars($companyId, $myCompanyReviews) {
    $existing   = $myCompanyReviews[$companyId] ?? null;
    $curRating  = (int)($existing["rating"] ?? 0);
    $curComment = htmlspecialchars($existing["comment"] ?? "");
    ob_start(); ?>
    <div class="sf-company-review-wrap">
        <div style="font-size:.72rem;font-weight:700;color:#6b7280;text-transform:uppercase;letter-spacing:.05em;margin-bottom:8px;">
            <?= $existing ? 'Your Rating' : 'Rate This Company' ?>
        </div>
        <div class="sf-cstar-row" data-company="<?= $companyId ?>" data-current="<?= $curRating ?>">
            <?php for ($i = 1; $i <= 5; $i++): ?>
            <button type="button" class="sf-cstar <?= $i <= $curRating ? 'is-on' : '' ?>" data-val="<?= $i ?>" onclick="sfCstarClick(this)">
                <i class="bi bi-star<?= $i <= $curRating ? '-fill' : '' ?>"></i>
            </button>
            <?php endfor; ?>
            <span class="sf-cstar-label" <?= $curRating ? '' : 'style="display:none"' ?>><?= $curRating ?: '' ?>/5</span>
        </div>
        <textarea class="sf-cstar-comment" placeholder="Add a comment (optional)" rows="2"><?= $curComment ?></textarea>
        <button type="button" class="sf-cstar-submit" onclick="sfCstarSubmit(this, <?= $companyId ?>)">
            <i class="bi bi-check2 me-1"></i><?= $existing ? 'Update Rating' : 'Submit Rating' ?>
        </button>
        <span class="sf-cstar-msg"></span>
    </div>
    <?php return ob_get_clean();
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
          <div class="sf-hc-stat">
            <div class="sf-hc-stat-label">Finishing</div>
            <div class="sf-hc-stat-val"><?= $totalFinishing ?></div>
          </div>
          <div class="sf-hc-stat">
            <div class="sf-hc-stat-label">Advertising</div>
            <div class="sf-hc-stat-val"><?= $totalAdvertising ?></div>
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
        <button class="sf-hc-tab" onclick="switchTab('finishing', this)">
          Finishing <span class="sf-hc-tab-count"><?= $totalFinishing ?></span>
        </button>
        <button class="sf-hc-tab" onclick="switchTab('advertising', this)">
          Advertising <span class="sf-hc-tab-count"><?= $totalAdvertising ?></span>
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
                   c.website, c.avg_rating, c.phone, c.location,
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
  // Still calculate breakdown for View Details button
  $cid = (int)$co["company_id"];
  if ($serviceKey === 'ac') {
    $rateToUse = $acRatesMap[$cid] ?? $acRate;
    $acItems = array_filter($orderItems, fn($i) => $i['module'] === 'ac');
    $breakdownLines = [];
    foreach ($acItems as $acItem) {
        $acImgRes = pg_query_params($conn, "SELECT image_url FROM product_images WHERE product_id = $1 LIMIT 1", [$acItem['product_id']]);
        $acImg = ($acImgRes && pg_num_rows($acImgRes) > 0) ? pg_fetch_assoc($acImgRes)['image_url'] : null;
        $breakdownLines[] = [
            "name"     => $acItem['product_name'],
            "qty"      => (int)$acItem['quantity'],
            "rate"     => $rateToUse,
            "subtotal" => $rateToUse * (int)$acItem['quantity'],
            "image"    => $acImg,
        ];
    }
  } elseif ($serviceKey === 'kitchen') {
    $breakdownLines = $kitchenBreakdown[$cid] ?? [];
  } elseif ($serviceKey === 'pos') {
    $breakdownLines = $posBreakdown[$cid] ?? [];
  } else {
    $breakdownLines = [];
  }
}else {
                  if ($co["starting_from"]) {
                    $cid = (int)$co["company_id"];
                    if ($serviceKey === 'ac') {
  $rateToUse    = $acRatesMap[$cid] ?? $acRate;
  $displayPrice = number_format($rateToUse * $acUnits, 0) . ' EGP';
  $acItemCount = array_sum(array_column(array_filter($orderItems, fn($i) => $i['module'] === 'ac'), 'quantity'));
  $priceSub = $acItemCount . ' unit' . ($acItemCount !== 1 ? 's' : '');
  $acItems = array_filter($orderItems, fn($i) => $i['module'] === 'ac');
  $breakdownLines = [];
  foreach ($acItems as $acItem) {
      $acImgRes = pg_query_params($conn, "SELECT image_url FROM product_images WHERE product_id = $1 LIMIT 1", [$acItem['product_id']]);
      $acImg = ($acImgRes && pg_num_rows($acImgRes) > 0) ? pg_fetch_assoc($acImgRes)['image_url'] : null;
      $breakdownLines[] = [
          "name"     => $acItem['product_name'],
          "qty"      => (int)$acItem['quantity'],
          "rate"     => $rateToUse,
          "subtotal" => $rateToUse * (int)$acItem['quantity'],
          "image"    => $acImg,
      ];
  }
                    } elseif ($serviceKey === 'kitchen') {
                      $breakdownLines = $kitchenBreakdown[$cid] ?? [];
                      $total          = $breakdownLines ? breakdownTotal($breakdownLines) : ((int)$co["starting_from"] * (int)($instData["kitchen_item_count"] ?? 1));
                      $displayPrice   = number_format($total, 0) . ' EGP';
                      $priceSub       = count($breakdownLines) . ' item type' . (count($breakdownLines) !== 1 ? 's' : '');
                    } elseif ($serviceKey === 'pos') {
                      $breakdownLines = $posBreakdown[$cid] ?? [];
                      $total          = $breakdownLines ? breakdownTotal($breakdownLines) : ((int)$co["starting_from"] * (int)($instData["terminal_count"] ?? 1));
                      $displayPrice   = number_format($total, 0) . ' EGP';
                      $priceSub       = count($breakdownLines) . ' item type' . (count($breakdownLines) !== 1 ? 's' : '');
                    } else {
                      $displayPrice   = number_format((int)$co["starting_from"], 0) . ' EGP';
                      $priceSub       = '';
                      $breakdownLines = [];
                    }
                    $priceLabel = "Est. Installation Cost";
                  } else {
                    $displayPrice = 'TBD'; $priceLabel = "Price"; $priceSub = ''; $breakdownLines = [];
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
                  <div class="sf-ins-company-logo">
                    <?php
                      $coImgRes = pg_query_params($conn, "SELECT image FROM companies WHERE company_id = $1 LIMIT 1", [$co["company_id"]]);
                      $coImg = ($coImgRes && pg_num_rows($coImgRes) > 0) ? pg_fetch_assoc($coImgRes)["image"] : null;
                    ?>
                    <?php if ($coImg): ?>
                      <img src="<?= htmlspecialchars($coImg) ?>" style="width:44px;height:44px;border-radius:10px;object-fit:cover;border:1px solid rgba(0,0,0,.08);">
                    <?php else: ?>
                      <div style="width:44px;height:44px;border-radius:10px;background:rgba(0,76,172,.08);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:800;color:#004cac;">
                        <?= strtoupper(substr($co["company_name"], 0, 2)) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="sf-ins-company-info">
                    <div class="sf-ins-company-name"><?= htmlspecialchars($co["company_name"]) ?></div>
                    <div class="sf-ins-company-meta-row">
                      <div class="sf-ins-rating">
                        <i class="bi bi-star-fill" style="font-size:.7rem"></i>
                        <?= number_format((float)($co["avg_rating"] ?? 0), 1) ?>
                      </div>
                      <span class="sf-ins-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                    </div>
                  </div>
                </div>
                  

                <div style="display:flex;align-items:center;gap:12px;margin-top:4px;flex-wrap:wrap;">
                    <?php if (!empty($co["phone"])): ?>
                        <div style="font-size:.78rem;font-weight:600;color:#374151;">
                            <i class="bi bi-telephone me-1" style="color:#004cac;"></i><?= htmlspecialchars($co["phone"]) ?>
                        </div>
                    <?php endif; ?>
                    <?php if (!empty($co["location"])): ?>
                        <div style="font-size:.78rem;font-weight:600;color:#374151;">
                            <i class="bi bi-geo-alt me-1" style="color:#004cac;"></i><?= htmlspecialchars($co["location"]) ?>
                        </div>
                    <?php endif; ?>
                </div>

                <div>
                  <div class="sf-ins-price-label"><?= $priceLabel ?></div>
                  <div class="sf-ins-price"><?= $displayPrice ?></div>
                  <?php if (!empty($priceSub)): ?>
                  <div class="sf-ins-price-sub"><?= $priceSub ?></div>
                  <?php endif; ?>
                </div>
<?php if (!empty($breakdownLines)): ?>
                <button
                  onclick='openBreakdownModal(<?= htmlspecialchars(json_encode([
    "company"     => $co["company_name"],
    "logo"        => $coImg,
    "service"     => $serviceKey,
    "lines"       => $breakdownLines,
    "total"       => breakdownTotal($breakdownLines),
    "description" => $co["description"] ?? "",
  ]), ENT_QUOTES) ?>)'
                  style="margin-top:10px;width:100%;padding:8px;border-radius:8px;background:#f1f5f9;color:#004cac;font-weight:700;font-size:.82rem;border:1px solid rgba(0,76,172,.15);cursor:pointer;">
                  <i class="bi bi-list-ul me-1"></i> View Details
                </button>
                <?php endif; ?>

                <?php if ($quoteAccepted && !empty($req["scheduled_date"])): ?>
                    <?= renderCompanyStars((int)$co["company_id"], $myCompanyReviews) ?>
                <?php endif; ?>
                <div class="sf-ins-actions">
  <?php if ($co["website"] || $co["website_link"]): ?>
  <a href="<?= htmlspecialchars($co["website"] ?? $co["website_link"]) ?>"
     target="_blank" class="sf-ins-btn-website">
    <i class="bi bi-box-arrow-up-right me-1"></i> Website
  </a>
  <?php endif; ?>
  <?php if ($hasQuote && !$quoteAccepted && !$quoteRejected && $reqStatus === 'pending'): ?>
    <form action="accept_quote.php" method="POST" class="m-0" style="flex:1;">
    <input type="hidden" name="quote_id" value="<?= (int)$co["quote_id"] ?>">
    <input type="hidden" name="request_id" value="<?= $req_id ?>">
    <button type="submit" class="sf-ins-btn-accept" style="width:100%;">Accept Quote</button>
  </form>
  <?php endif; ?>
  <?php if ($quoteAccepted): ?>
    <?php $scheduledDate = $req["scheduled_date"] ?? null; ?>
    <?php if ($scheduledDate): ?>
      <div style="flex:1;display:inline-flex;align-items:center;justify-content:center;gap:6px;padding:8px 14px;background:#004cac;color:#fff;font-size:.82rem;font-weight:700;border-radius:0;border:none;">
        <i class="bi bi-calendar-check-fill"></i>
        <?= date('M j, Y', strtotime($scheduledDate)) ?>
      </div>
    <?php else: ?>
      <button onclick="openScheduleModal(<?= $req_id ?>, '<?= htmlspecialchars($minScheduleDate) ?>')"
        class="sf-ins-btn-accept">
        <i class="bi bi-calendar3 me-1"></i>Schedule
      </button>
    <?php endif; ?>
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

    <!-- FINISHING PANEL -->
    <div class="sf-hc-panel" id="panel-finishing">
      <?php if (!$hasFinishing): ?>
        <div class="sf-hc-empty">
          <h4>No Finishing Request Yet</h4>
          <p>Complete your payment to unlock finishing services.</p>
        </div>
      <?php else:
        $fReqId      = (int)$finishingReq["request_id"];
        $fStatus     = $finishingReq["status"];
        $fAreaSqm    = (int)($finishingReq["area_sqm"] ?? 0);
        $typeLabels  = [
          'painting' => 'Painting',
          'flooring' => 'Flooring',
          'gypsum'   => 'Gypsum & Ceilings',
          'decor'    => 'Decor',
          'facades'  => 'Facades',
        ];
      ?>

        <!-- Finishing types selector -->
        <div style="background:#f8fafc;border:1.5px solid #e0eaff;border-radius:0;padding:20px;margin-bottom:24px;">
          <div style="font-size:.85rem;font-weight:800;color:#111827;margin-bottom:4px;">
            <i class="bi bi-brush me-1" style="color:#004cac"></i> What needs finishing?
          </div>
          <div style="font-size:.78rem;color:#6b7280;margin-bottom:14px;">
            Select the areas you need — companies will quote based on your <?= $fAreaSqm > 0 ? $fAreaSqm . ' sqm' : 'space' ?>.
          </div>
          <form method="POST">
            <input type="hidden" name="action" value="save_finishing_types">
            <div style="display:flex;flex-wrap:wrap;gap:10px;margin-bottom:16px;">
              <?php foreach ($typeLabels as $val => $label): ?>
              <label style="display:flex;align-items:center;gap:6px;padding:8px 16px;border:1.5px solid <?= in_array($val, $finishingTypes) ? '#004cac' : 'rgba(0,0,0,.12)' ?>;border-radius:0;background:<?= in_array($val, $finishingTypes) ? 'rgba(0,76,172,.08)' : '#fff' ?>;cursor:pointer;font-size:.85rem;font-weight:700;color:<?= in_array($val, $finishingTypes) ? '#004cac' : '#374151' ?>;">
                <input type="checkbox" name="finishing_types[]" value="<?= $val ?>"
                  <?= in_array($val, $finishingTypes) ? 'checked' : '' ?>
                  style="accent-color:#004cac;">
                <?= $label ?>
              </label>
              <?php endforeach; ?>
            </div>
            <button type="submit" style="padding:9px 24px;background:#004cac;color:#fff;border:none;border-radius:0;font-weight:700;font-size:.85rem;cursor:pointer;">
              <i class="bi bi-check2 me-1"></i> Save
            </button>
          </form>
        </div>

        <?php if (empty($finishingTypes)): ?>
          <div class="sf-hc-empty">
            <h4>Select What You Need</h4>
            <p>Choose finishing types above to see matching companies.</p>
          </div>
        <?php elseif (empty($finishingList)): ?>
          <div class="sf-hc-empty">
            <h4>No Finishing Companies Yet</h4>
            <p>Companies will appear here once available.</p>
          </div>
        <?php else: ?>
          <div class="sf-ins-service-block">
            <div class="sf-ins-service-label">
              <i class="bi bi-brush" style="color:#004cac"></i>
              <span class="sf-ins-service-title">تشطيبات</span>
              <span class="sf-ins-status-badge sf-ins-status-<?= htmlspecialchars($fStatus) ?>">
                <?= ucfirst($fStatus) ?>
              </span>
              <?php if ($fAreaSqm > 0): ?>
                <span style="font-size:.78rem;color:#6b7280;font-weight:600;">
                  <i class="bi bi-rulers me-1"></i><?= $fAreaSqm ?> sqm
                </span>
              <?php endif; ?>
            </div>

            <div class="sf-ins-grid">
              <?php foreach ($finishingList as $idx => $co):
                $hasQuote      = !empty($co["quote_id"]);
                $quoteAccepted = $hasQuote && $co["quote_status"] === 'accepted';
                $quoteRejected = $hasQuote && $co["quote_status"] === 'rejected';

                if ($quoteAccepted || ($hasQuote && !$quoteRejected)) {
                  $displayPrice = number_format((float)$co["price"], 0) . ' EGP';
                  $priceLabel   = "Quote Received";
                  $priceSub     = $co["message"] ? htmlspecialchars($co["message"]) : '';
                } else {
                  $displayPrice = $co["starting_from"] ? number_format((int)$co["starting_from"], 0) . ' EGP' : 'TBD';
                  $priceLabel   = "Starting From";
                  $priceSub     = '';
                }

                if ($quoteAccepted)                   { $badgeClass = 'sf-ins-badge-accepted'; $badgeText = 'Accepted'; }
                elseif ($hasQuote && !$quoteRejected) { $badgeClass = 'sf-ins-badge-quote';    $badgeText = 'Quote Received'; }
                elseif ($quoteRejected)               { $badgeClass = 'sf-ins-badge-rejected';  $badgeText = 'Not Selected'; }
                else                                  { $badgeClass = 'sf-ins-badge-awaiting';  $badgeText = 'Awaiting Quote'; }

                // Parse specialties
                $specRaw  = trim($co["specialties"] ?? "", '{}');
                $specList = $specRaw ? array_filter(array_map('trim', explode(',', $specRaw))) : [];
              ?>
              <div class="sf-ins-company-card <?= $quoteAccepted ? 'is-accepted' : '' ?>">
                <?php if ($idx === 0): ?><div class="sf-ins-recommended">Recommended</div><?php endif; ?>

                <div class="sf-ins-company-top">
                  <div class="sf-ins-company-logo">
                    <?php if (!empty($co["image"])): ?>
                      <img src="<?= htmlspecialchars($co["image"]) ?>" style="width:44px;height:44px;border-radius:10px;object-fit:cover;border:1px solid rgba(0,0,0,.08);">
                    <?php else: ?>
                      <div style="width:44px;height:44px;border-radius:10px;background:rgba(0,76,172,.08);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:800;color:#004cac;">
                        <?= strtoupper(substr($co["company_name"], 0, 2)) ?>
                      </div>
                    <?php endif; ?>
                  </div>
                  <div class="sf-ins-company-info">
                    <div class="sf-ins-company-name"><?= htmlspecialchars($co["company_name"]) ?></div>
                    <div class="sf-ins-company-meta-row">
                      <div class="sf-ins-rating">
                        <i class="bi bi-star-fill" style="font-size:.7rem"></i>
                        <?= number_format((float)($co["avg_rating"] ?? 0), 1) ?>
                      </div>
                      <span class="sf-ins-badge <?= $badgeClass ?>"><?= $badgeText ?></span>
                    </div>
                  </div>
                </div>

                <?php if (!empty($specList)): ?>
                <div style="display:flex;flex-wrap:wrap;gap:5px;margin-top:8px;">
                  <?php foreach ($specList as $spec): ?>
                    <span style="font-size:.7rem;font-weight:700;padding:3px 9px;border-radius:0;background:rgba(0,76,172,.07);color:#004cac;border:1px solid rgba(0,76,172,.15);">
                      <?= htmlspecialchars($typeLabels[$spec] ?? $spec) ?>
                    </span>
                  <?php endforeach; ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($co["location"])): ?>
                <div style="font-size:.78rem;font-weight:600;color:#374151;margin-top:8px;">
                  <i class="bi bi-geo-alt me-1" style="color:#004cac"></i><?= htmlspecialchars($co["location"]) ?>
                </div>
                <?php endif; ?>

                <?php if (!empty($co["description"])): ?>
                  <div class="sf-ins-company-desc"><?= htmlspecialchars($co["description"]) ?></div>
                <?php endif; ?>

                <div>
                  <div class="sf-ins-price-label"><?= $priceLabel ?></div>
                  <div class="sf-ins-price"><?= $displayPrice ?></div>
                  <?php if (!empty($priceSub)): ?>
                    <div class="sf-ins-price-sub"><?= $priceSub ?></div>
                  <?php endif; ?>
                </div>

                <button
                  onclick='openBreakdownModal(<?= htmlspecialchars(json_encode([
                    "company"     => $co["company_name"],
                    "logo"        => $co["image"] ?? null,
                    "service"     => "Finishing",
                    "lines"       => [],
                    "total"       => 0,
                    "description" => $co["description"] ?? "",
                  ]), ENT_QUOTES) ?>)'
                  style="margin-top:10px;width:100%;padding:8px;border-radius:0;background:#f1f5f9;color:#004cac;font-weight:700;font-size:.82rem;border:1px solid rgba(0,76,172,.15);cursor:pointer;">
                  <i class="bi bi-info-circle me-1"></i> View Details
                </button>
                <?php if ($quoteAccepted && !empty($finishingReq["scheduled_date"])): ?>
                    <?= renderCompanyStars((int)$co["company_id"], $myCompanyReviews) ?>
                <?php endif; ?>
                <div class="sf-ins-actions">
                  <?php if (!empty($co["website"])): ?>
                    <a href="<?= htmlspecialchars($co["website"]) ?>" target="_blank" class="sf-ins-btn-website">
                      <i class="bi bi-box-arrow-up-right me-1"></i> Website
                    </a>
                  <?php endif; ?>
                  <?php if ($hasQuote && !$quoteAccepted && !$quoteRejected && $fStatus === 'pending'): ?>
                    <form method="POST" class="m-0" style="flex:1;">
                      <input type="hidden" name="action" value="accept_finishing_quote">
                      <input type="hidden" name="quote_id" value="<?= (int)$co["quote_id"] ?>">
                      <input type="hidden" name="request_id" value="<?= $fReqId ?>">
                      <button type="submit" class="sf-ins-btn-accept" style="width:100%;">Accept Quote</button>
                    </form>
                  <?php endif; ?>
                  
                </div>
              </div>
              <?php endforeach; ?>
            </div>
          </div>
        <?php endif; ?>
      <?php endif; ?>
    </div>

    <!-- ADVERTISING PANEL -->
    <div class="sf-hc-panel" id="panel-advertising">
      <?php if (empty($advertisingList)): ?>
        <div class="sf-hc-empty">
          <h4>No Advertising Companies Yet</h4>
          <p>Advertising partners will appear here once available.</p>
        </div>
      <?php else: ?>
        <div style="margin-bottom:18px;">
          <div style="font-size:.82rem;color:#6b7280;font-weight:600;">
            <i class="bi bi-megaphone me-1" style="color:#004cac"></i>
            Boost your business visibility — connect with professional advertising companies below.
          </div>
        </div>
        <div class="sf-ins-grid">
          <?php foreach ($advertisingList as $idx => $adco):
            $adImg = $adco['image'] ?? null;
          ?>
          <div class="sf-ins-company-card">
            <?php if ($idx === 0): ?><div class="sf-ins-recommended">Recommended</div><?php endif; ?>
            <div class="sf-ins-company-top">
              <div class="sf-ins-company-logo">
                <?php if ($adImg): ?>
                  <img src="<?= htmlspecialchars($adImg) ?>" style="width:44px;height:44px;border-radius:10px;object-fit:cover;border:1px solid rgba(0,0,0,.08);">
                <?php else: ?>
                  <div style="width:44px;height:44px;border-radius:10px;background:rgba(0,76,172,.08);display:flex;align-items:center;justify-content:center;font-size:.8rem;font-weight:800;color:#004cac;">
                    <?= strtoupper(substr($adco['company_name'], 0, 2)) ?>
                  </div>
                <?php endif; ?>
              </div>
              <div class="sf-ins-company-info">
                <div class="sf-ins-company-name"><?= htmlspecialchars($adco['company_name']) ?></div>
                <div class="sf-ins-company-meta-row">
                  <div class="sf-ins-rating">
                    <i class="bi bi-star-fill" style="font-size:.7rem"></i>
                    <?= number_format((float)($adco['avg_rating'] ?? 0), 1) ?>
                  </div>
                  <?php if (!empty($adco['location'])): ?>
                    <span style="font-size:.72rem;color:#6b7280;font-weight:600;">
                      <i class="bi bi-geo-alt me-1"></i><?= htmlspecialchars($adco['location']) ?>
                    </span>
                  <?php endif; ?>
                </div>
              </div>
            </div>
            <?php if (!empty($adco['description'])): ?>
              <div class="sf-ins-company-desc"><?= htmlspecialchars($adco['description']) ?></div>
            <?php endif; ?>
            <div>
              <div class="sf-ins-price-label">Starting From</div>
              <div class="sf-ins-price">
                <?= !empty($adco['starting_from']) ? number_format((int)$adco['starting_from'], 0) . ' EGP' : 'Contact for pricing' ?>
              </div>
            </div>
            <button
                  onclick='openBreakdownModal(<?= htmlspecialchars(json_encode([
                    "company"     => $adco["company_name"],
                    "logo"        => $adco["image"] ?? null,
                    "service"     => "Advertising",
                    "lines"       => [],
                    "total"       => 0,
                    "description" => $adco["description"] ?? "",
                  ]), ENT_QUOTES) ?>)'
                  style="margin-top:10px;width:100%;padding:8px;border-radius:0;background:#f1f5f9;color:#004cac;font-weight:700;font-size:.82rem;border:1px solid rgba(0,76,172,.15);cursor:pointer;">
                  <i class="bi bi-info-circle me-1"></i> View Details
                </button>
            <div class="sf-ins-actions">
              <?php if (!empty($adco['website'])): ?>
                <a href="<?= htmlspecialchars($adco['website']) ?>" target="_blank" class="sf-ins-btn-website">
                  <i class="bi bi-box-arrow-up-right me-1"></i> Visit Website
                </a>
              <?php endif; ?>
            </div>
          </div>
          <?php endforeach; ?>
        </div>
      <?php endif; ?>
    </div>

    <?php if (empty($laborRoles) && !$hasInstallation && !$hasFinishing && empty($advertisingList)): ?>
    <div class="sf-hc-empty mt-4">
      <h4>No Services Yet</h4>
      <p>Once your setup is complete, all services will appear here.</p>
    </div>
    <?php endif; ?>

  </div>
  <!-- Schedule Modal -->
<div id="sf-schedule-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:5px;width:min(400px,95vw);padding:28px;position:relative;box-shadow:0 24px 60px rgba(0,0,0,.18);">
    <button onclick="closeScheduleModal()" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:1.3rem;color:#6b7280;cursor:pointer;">
      <i class="bi bi-x-lg"></i>
    </button>
    <div style="font-size:1.05rem;font-weight:800;color:#111827;margin-bottom:6px;">Schedule Installation</div>
    <div id="schedule-modal-note" style="font-size:.78rem;color:#9ca3af;margin-bottom:20px;"></div>
    <form method="POST">
<input type="hidden" name="action" value="schedule_installation" id="schedule-modal-action">
      <input type="hidden" name="request_id" id="schedule-modal-req-id">
      <div style="margin-bottom:16px;">
        <label style="font-size:.82rem;font-weight:700;color:#374151;display:block;margin-bottom:6px;">Installation Date</label>
        <input type="date" name="scheduled_date" id="schedule-modal-date"
          required
          style="width:100%;padding:10px 12px;border:1.5px solid #c7d9f7;border-radius:5px;font-size:.9rem;font-weight:600;color:#111827;outline:none;">
      </div>
      <button type="submit"
        style="width:100%;padding:12px;background:#004cac;color:#fff;border:none;border-radius:5px;font-weight:700;font-size:.92rem;cursor:pointer;">
        <i class="bi bi-check2-circle me-1"></i>Confirm Date
      </button>
    </form>
  </div>
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
// Auto-activate tab from URL ?tab=
(function() {
  const params = new URLSearchParams(window.location.search);
  const tab = params.get('tab');
  if (tab) {
    const btn = document.querySelector(`.sf-hc-tab[onclick*="'${tab}'"]`);
    if (btn) switchTab(tab, btn);
  }
})();

function switchRole(rKey, btn) {
  document.querySelectorAll('.sf-hc-role-pill').forEach(p => p.classList.remove('is-active'));
  document.querySelectorAll('.sf-hc-role-panel').forEach(p => p.classList.remove('is-active'));
  btn.classList.add('is-active');
  const panel = document.getElementById('role-' + btoa(unescape(encodeURIComponent(rKey))));
  if (panel) panel.classList.add('is-active');
}
function openScheduleModal(reqId, minDate, type) {
  document.getElementById('schedule-modal-req-id').value = reqId;
  document.getElementById('schedule-modal-action').value = type === 'finishing' ? 'schedule_finishing' : 'schedule_installation';
  document.getElementById('schedule-modal-date').min = minDate;
  document.getElementById('schedule-modal-note').textContent = minDate
    ? 'Must be after estimated delivery: ' + new Date(minDate).toLocaleDateString('en-US', {month:'short',day:'numeric',year:'numeric'})
    : 'No delivery date set yet. You can still schedule.';
  document.getElementById('sf-schedule-modal').style.display = 'flex';
}

function closeScheduleModal() {
  document.getElementById('sf-schedule-modal').style.display = 'none';
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
<!-- Breakdown Modal -->
<div id="sf-breakdown-modal" style="display:none;position:fixed;inset:0;z-index:9999;background:rgba(0,0,0,.45);align-items:center;justify-content:center;">
  <div style="background:#fff;border-radius:20px;width:min(480px,95vw);max-height:85vh;overflow-y:auto;padding:28px;position:relative;box-shadow:0 24px 60px rgba(0,0,0,.18);">
    <button onclick="closeBreakdownModal()" style="position:absolute;top:16px;right:16px;background:none;border:none;font-size:1.3rem;color:#6b7280;cursor:pointer;">
      <i class="bi bi-x-lg"></i>
    </button>
    <div style="display:flex;align-items:center;gap:12px;margin-bottom:16px;">
      <div id="bm-logo" style="width:48px;height:48px;border-radius:12px;overflow:hidden;flex-shrink:0;background:rgba(0,76,172,.08);display:flex;align-items:center;justify-content:center;font-size:.85rem;font-weight:800;color:#004cac;"></div>
      <div>
        <div id="bm-company" style="font-size:1.05rem;font-weight:800;color:#111827;margin-bottom:4px;"></div>
        <div id="bm-service" style="font-size:.78rem;font-weight:700;color:#004cac;background:rgba(0,76,172,.08);padding:3px 10px;border-radius:999px;display:inline-block;"></div>
        <div id="bm-desc" style="font-size:.82rem;color:#6b7280;margin-top:6px;line-height:1.5;"></div>
      </div>
    </div>

    <div id="bm-lines" style="display:flex;flex-direction:column;gap:10px;margin-bottom:20px;"></div>

    <div style="border-top:2px solid rgba(0,0,0,.08);padding-top:14px;display:flex;justify-content:space-between;align-items:center;">
      <span style="font-size:.85rem;font-weight:700;color:#6b7280;">Estimated Total</span>
      <span id="bm-total" style="font-size:1.2rem;font-weight:900;color:#004cac;"></span>
    </div>
    <div style="margin-top:8px;font-size:.72rem;color:#9ca3af;text-align:center;">
      This is an estimate. Final price comes from the company's quote.
    </div>
  </div>
</div>

<script>
function openBreakdownModal(data) {
  document.getElementById('bm-company').textContent = data.company;
  document.getElementById('bm-service').textContent = data.service.toUpperCase() + ' Installation';
  const descEl = document.getElementById('bm-desc');
  if (descEl) descEl.textContent = data.description || '';
  document.getElementById('bm-total').textContent   = parseInt(data.total).toLocaleString() + ' EGP';

  const logoEl = document.getElementById('bm-logo');
  if (data.logo) {
    logoEl.innerHTML = `<img src="${data.logo}" style="width:100%;height:100%;object-fit:cover;">`;
  } else {
    logoEl.textContent = data.company.substring(0, 2).toUpperCase();
  }

  const lines = document.getElementById('bm-lines');
  lines.innerHTML = '';

  data.lines.forEach(line => {
    const row = document.createElement('div');
    row.style.cssText = 'display:flex;align-items:center;gap:12px;padding:10px 12px;border-radius:12px;background:#f8fafc;border:1px solid rgba(0,0,0,.06);';

    const img = line.image
      ? `<img src="${line.image}" style="width:44px;height:44px;object-fit:cover;border-radius:8px;flex-shrink:0;">`
      : `<div style="width:44px;height:44px;border-radius:8px;background:#e5e7eb;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><i class="bi bi-box" style="color:#9ca3af;"></i></div>`;

    row.innerHTML = img + `
      <div style="flex:1;min-width:0;">
        <div style="font-size:.88rem;font-weight:700;color:#111827;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;">${line.name}</div>
        <div style="font-size:.75rem;color:#6b7280;margin-top:2px;">${line.qty} × ${parseInt(line.rate).toLocaleString()} EGP</div>
      </div>
      <div style="font-size:.95rem;font-weight:800;color:#004cac;flex-shrink:0;">${parseInt(line.subtotal).toLocaleString()} EGP</div>
    `;
    lines.appendChild(row);
  });

  const totalRow = document.querySelector('#sf-breakdown-modal [style*="Estimated Total"]')?.parentElement;
  if (totalRow) totalRow.style.display = data.lines && data.lines.length > 0 ? 'flex' : 'none';
  document.getElementById('sf-breakdown-modal').style.display = 'flex';
}
function closeBreakdownModal() {
  document.getElementById('sf-breakdown-modal').style.display = 'none';
}
</script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
<script>
function sfCstarClick(btn) {
    const row = btn.closest('.sf-cstar-row');
    const val = parseInt(btn.dataset.val);
    row.dataset.current = val;
    row.querySelectorAll('.sf-cstar').forEach((s, i) => {
        s.classList.toggle('is-on', i < val);
        s.querySelector('i').className = 'bi bi-star' + (i < val ? '-fill' : '');
    });
    const label = row.querySelector('.sf-cstar-label');
    label.textContent = val + '/5';
    label.style.display = '';
}
function sfCstarSubmit(btn, companyId) {
    const wrap    = btn.closest('.sf-company-review-wrap');
    const row     = wrap.querySelector('.sf-cstar-row');
    const rating  = parseInt(row.dataset.current || 0);
    const comment = wrap.querySelector('.sf-cstar-comment').value.trim();
    const msg     = wrap.querySelector('.sf-cstar-msg');
    if (!rating) { msg.textContent = 'Pick a star first.'; msg.style.color = '#dc2626'; return; }
    msg.textContent = 'Saving…'; msg.style.color = '#6b7280';
    btn.disabled = true;
    const fd = new FormData();
    fd.append('company_id', companyId);
    fd.append('rating',     rating);
    fd.append('comment',    comment);
    fetch('submit_company_review.php', { method: 'POST', body: fd })
        .then(r => r.json())
        .then(res => {
            if (res.ok) {
                msg.textContent = 'Saved!'; msg.style.color = '#15803d';
                btn.textContent = 'Update Rating';
            } else {
                msg.textContent = res.error || 'Failed.'; msg.style.color = '#dc2626';
            }
            btn.disabled = false;
        })
        .catch(() => { msg.textContent = 'Network error.'; msg.style.color = '#dc2626'; btn.disabled = false; });
}
</script>
</body>
</html>
