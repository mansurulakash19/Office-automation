<?php
// logout.php — ends the session. Sign-out is a POST form (with CSRF token) so other sites cannot log you out.
require_once __DIR__ . '/includes/auth.php';

if ($_SERVER['REQUEST_METHOD'] === 'POST' && csrf_valid()) {
    do_logout();
    redirect('login.php');
}
redirect(is_logged_in() ? HOME_PAGE : 'login.php');
