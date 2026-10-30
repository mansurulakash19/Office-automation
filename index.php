<?php
// index.php — the front door. Sends you to the dashboard (if signed in) or the login page.
require_once __DIR__ . '/includes/auth.php';
redirect(is_logged_in() ? 'dashboard.php' : 'login.php');
