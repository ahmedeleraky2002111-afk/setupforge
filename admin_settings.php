<?php
session_start();
require "db.php";
if (!isset($_SESSION["user_id"]) || ($_SESSION["user_type"] ?? "") !== "admin") {
    header("Location: auth/login.php"); exit();
}

$success = '';
$error   = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'change_password') {
        $current  = $_POST['current_password'] ?? '';
        $new      = $_POST['new_password'] ?? '';
        $confirm  = $_POST['confirm_password'] ?? '';

        $res = pg_query_params($conn,
            "SELECT password FROM users WHERE id = $1",
            [$_SESSION['user_id']]);
        $row = pg_fetch_assoc($res);

        if (!password_verify($current, $row['password'])) {
            $error = 'Current password is incorrect.';
        } elseif (strlen($new) < 8) {
            $error = 'New password must be at least 8 characters.';
        } elseif ($new !== $confirm) {
            $error = 'New passwords do not match.';
        } else {
            $hash = password_hash($new, PASSWORD_DEFAULT);
            pg_query_params($conn,
                "UPDATE users SET password = $1 WHERE id = $2",
                [$hash, $_SESSION['user_id']]);
            $success = 'Password updated successfully.';
        }
    }
}

$ad_title = 'Settings';
$ad_page  = 'settings';
require 'admin_layout.php';
?>

<div class="ad-page-header">
  <div class="ad-page-title">Settings</div>
  <div class="ad-page-sub">Admin account and platform configuration</div>
</div>

<?php if ($success): ?>
<div class="ad-alert" style="background:var(--ad-success-bg);border-color:rgba(10,122,69,.15);color:var(--ad-success-text);margin-bottom:20px">
  <i class="bi bi-check-circle-fill"></i> <?= htmlspecialchars($success) ?>
</div>
<?php endif; ?>
<?php if ($error): ?>
<div class="ad-alert is-danger" style="margin-bottom:20px">
  <i class="bi bi-exclamation-circle-fill"></i> <?= htmlspecialchars($error) ?>
</div>
<?php endif; ?>

<div style="max-width:520px;display:flex;flex-direction:column;gap:20px">

  <!-- CHANGE PASSWORD -->
  <div class="ad-box">
    <div class="ad-box-head">
      <div>
        <div class="ad-box-title">Change password</div>
        <div class="ad-box-sub">Update your admin account password</div>
      </div>
      <i class="bi bi-lock" style="font-size:20px;color:var(--ad-hint)"></i>
    </div>
    <div class="ad-box-body">
      <form method="post" style="display:flex;flex-direction:column;gap:14px">
        <input type="hidden" name="action" value="change_password">
        <div>
          <label style="font-size:12px;font-weight:600;color:var(--ad-muted);display:block;margin-bottom:6px">
            Current password
          </label>
          <input type="password" name="current_password" required
            style="width:100%;height:38px;padding:0 12px;border:1px solid var(--ad-border);
                   border-radius:var(--ad-radius-sm);font-size:13px;font-family:var(--ad-font);
                   outline:none;background:var(--ad-surface);color:var(--ad-text)">
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:var(--ad-muted);display:block;margin-bottom:6px">
            New password
          </label>
          <input type="password" name="new_password" required minlength="8"
            style="width:100%;height:38px;padding:0 12px;border:1px solid var(--ad-border);
                   border-radius:var(--ad-radius-sm);font-size:13px;font-family:var(--ad-font);
                   outline:none;background:var(--ad-surface);color:var(--ad-text)">
        </div>
        <div>
          <label style="font-size:12px;font-weight:600;color:var(--ad-muted);display:block;margin-bottom:6px">
            Confirm new password
          </label>
          <input type="password" name="confirm_password" required minlength="8"
            style="width:100%;height:38px;padding:0 12px;border:1px solid var(--ad-border);
                   border-radius:var(--ad-radius-sm);font-size:13px;font-family:var(--ad-font);
                   outline:none;background:var(--ad-surface);color:var(--ad-text)">
        </div>
        <div>
          <button type="submit" class="ad-btn primary">
            <i class="bi bi-shield-check"></i> Update password
          </button>
        </div>
      </form>
    </div>
  </div>

  <!-- PLATFORM INFO -->
  <div class="ad-box">
    <div class="ad-box-head">
      <div>
        <div class="ad-box-title">Platform info</div>
        <div class="ad-box-sub">Read-only system details</div>
      </div>
      <i class="bi bi-info-circle" style="font-size:20px;color:var(--ad-hint)"></i>
    </div>
    <div class="ad-box-body">
      <table style="width:100%;font-size:13px;border-collapse:collapse">
        <tr>
          <td style="padding:8px 0;color:var(--ad-muted);width:140px;font-weight:500">Platform</td>
          <td style="padding:8px 0;color:var(--ad-text)">SetupForge</td>
        </tr>
        <tr>
          <td style="padding:8px 0;color:var(--ad-muted);border-top:1px solid var(--ad-border-2);font-weight:500">Environment</td>
          <td style="padding:8px 0;color:var(--ad-text);border-top:1px solid var(--ad-border-2)">
            <?= isset($_SERVER['RAILWAY_ENVIRONMENT']) ? 'Railway Production' : 'Local (XAMPP)' ?>
          </td>
        </tr>
        <tr>
          <td style="padding:8px 0;color:var(--ad-muted);border-top:1px solid var(--ad-border-2);font-weight:500">Database</td>
          <td style="padding:8px 0;color:var(--ad-text);border-top:1px solid var(--ad-border-2)">PostgreSQL (Railway)</td>
        </tr>
        <tr>
          <td style="padding:8px 0;color:var(--ad-muted);border-top:1px solid var(--ad-border-2);font-weight:500">Admin ID</td>
          <td style="padding:8px 0;border-top:1px solid var(--ad-border-2)">
            <span class="mono"><?= (int)$_SESSION['user_id'] ?></span>
          </td>
        </tr>
        <tr>
          <td style="padding:8px 0;color:var(--ad-muted);border-top:1px solid var(--ad-border-2);font-weight:500">Server time</td>
          <td style="padding:8px 0;color:var(--ad-text);border-top:1px solid var(--ad-border-2)">
            <?= date('d M Y, H:i:s') ?>
          </td>
        </tr>
      </table>
    </div>
  </div>

</div>

  </main>
</div>
</body>
</html>
