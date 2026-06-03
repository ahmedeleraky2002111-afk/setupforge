<?php
session_start();

// Clear all session data
$_SESSION = [];

// Delete session cookie if used
if (ini_get("session.use_cookies")) {
    $params = session_get_cookie_params();
    setcookie(
        session_name(),
        '',
        time() - 42000,
        $params["path"],
        $params["domain"],
        $params["secure"],
        $params["httponly"]
    );
}

// Destroy session
session_destroy();

// Redirect to main login page
?>
<!doctype html>
<html>
<head></head>
<body>
<script>
for(let i=0;i<8;i++) localStorage.removeItem('sf_tut_step_'+i+'_done');
localStorage.removeItem('sf_tut_packages_done');
localStorage.removeItem('sf_tut_packages2_done');
window.location.href = 'login.php';
</script>
</body>
</html>