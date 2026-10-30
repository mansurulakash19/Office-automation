<?php
// index.php — the front door. Sends you to the home page (if signed in) or the login page.
require_once __DIR__ . '/includes/auth.php';
redirect(is_logged_in() ? HOME_PAGE : 'login.php');
