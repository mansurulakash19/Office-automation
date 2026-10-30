<?php
// config/config.php — the only file you need to edit to match your computer.

// ---- Database (XAMPP defaults) ----
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');            // XAMPP root has no password by default
define('DB_NAME', 'a2z_central');

// ---- Application settings ----
define('APP_NAME', 'A2Z Central');
define('COMPANY_NAME', 'A to Z Engineering Solution Limited');
define('HOME_PAGE', 'accounts.php');   // the page opened after signing in
define('OFFICE_START', '10:00');  // checking in after this time counts as "Late"
define('MAX_UPLOAD_MB', 10);      // maximum size of an uploaded design file
define('SHOW_DEMO_LOGINS', true); // shows quick-fill demo buttons on the login page
define('UPLOAD_DIR', dirname(__DIR__) . '/uploads/');


date_default_timezone_set('Asia/Dhaka');
