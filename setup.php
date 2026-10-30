<?php
// setup.php — ONE-TIME installer.
// Creates the database, creates all tables from database/schema.sql and adds demo data.
// After a successful install it writes config/installed.lock so it cannot be run twice.
// (For safety, delete this file when the project is finished.)

require_once __DIR__ . '/config/config.php';
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

$lockFile = __DIR__ . '/config/installed.lock';
$installed = file_exists($lockFile);
$error = '';
$done = false;
$counts = [];

function h($text): string {
    return htmlspecialchars((string)$text, ENT_QUOTES, 'UTF-8');
}

// Runs every CREATE TABLE statement in schema.sql. Returns how many statements ran.
function run_schema(mysqli $m): int {
    $sql = file_get_contents(__DIR__ . '/database/schema.sql');
    $sql = preg_replace('/^\s*--.*$/m', '', $sql);          // remove comment lines
    $ran = 0;
    foreach (explode(';', $sql) as $statement) {
        $statement = trim($statement);
        if ($statement !== '') {
            $m->query($statement);
            $ran++;
        }
    }
    return $ran;
}

// Adds demo data. All dates are RELATIVE TO TODAY, so the demo always has items that are
// overdue, due soon, and fine — so the colour-coded due dates always have something to show.
function seed_demo(): array {
    if ((int) db_value('SELECT COUNT(*) FROM users') > 0) {
        return ['skipped' => 1];      // the database already has data: do not add it twice
    }
    $d = function (int $offset): string {
        return date('Y-m-d', strtotime(sprintf('%+d days', $offset)));
    };

    // ----- Users (the first one is the administrator) -----
    $userRows = [
        ['Rafiq Ahmed',  'admin@a2z.com',  'Admin@123', 'admin', 'Managing Director'],
        ['Nusrat Jahan', 'nusrat@a2z.com', 'Staff@123', 'staff', 'Accounts Officer'],
        ['Tanvir Hasan', 'tanvir@a2z.com', 'Staff@123', 'staff', 'Site Engineer'],
        ['Sadia Rahman', 'sadia@a2z.com',  'Staff@123', 'staff', 'Design Engineer'],
    ];
    $u = [];
    foreach ($userRows as $i => $r) {
        $u[$i] = db_insert('INSERT INTO users (name, email, password_hash, role, designation) VALUES (?,?,?,?,?)',
            [$r[0], $r[1], password_hash($r[2], PASSWORD_DEFAULT), $r[3], $r[4]]);
    }

    // ----- Clients: name, contact, phone, email, address, last contact (days), next follow-up (days) -----
    $clientRows = [
        ['Bashundhara Constructions Ltd.', 'Kamal Uddin',   '01711-000111', 'kamal@bashundhara-c.example', 'Pragati Sarani, Dhaka',     -6, 2],
        ['Delta Textile Mills',            'Farzana Akter', '01811-222333', 'farzana@deltatex.example',    'Gazipur Sadar, Gazipur',    -12, -2],
        ['Green Valley Developers',        'Imran Hossain', '01911-444555', 'imran@greenvalley.example',   'Sector 7, Uttara, Dhaka',   -3, 9],
        ['Meghna Power Solutions',         'Shahidul Alam', '01611-666777', 'shahid@meghnapower.example',  'Siddhirganj, Narayanganj',  -20, 1],
        ['Sunrise Pharma Ltd.',            'Dr. Nilufar Yasmin', '01511-888999', 'nilufar@sunrise.example', 'Savar, Dhaka',             -1, 14],
        ['Padma Tower Holdings',           'Anwar Karim',   '01311-121212', 'anwar@padmatower.example',    'Motijheel C/A, Dhaka',      -30, -9],
    ];
    $c = [];
    foreach ($clientRows as $i => $r) {
        $c[$i] = db_insert('INSERT INTO clients (name, contact_person, phone, email, address, last_contact, next_followup) VALUES (?,?,?,?,?,?,?)',
            [$r[0], $r[1], $r[2], $r[3], $r[4], $d($r[5]), $d($r[6])]);
    }

    // ----- Projects: client, manager, title, description, start (days), deadline (days), status -----
    $projectRows = [
        [0, 2, 'Warehouse Steel Structure',      'Design and erection of a 40,000 sq ft pre-engineered steel warehouse.', -80, 35, 'In Progress'],
        [1, 3, 'Factory Layout & Power Design',  'New machine layout and 11 kV power distribution for the weaving unit.',  -60, 20, 'In Progress'],
        [3, 2, 'Substation Upgrade',             'Upgrade of the 33/11 kV substation with a new 10 MVA transformer.',      -95, 10, 'In Progress'],
        [5, 3, 'HVAC Installation — Padma Tower', 'Central HVAC design and installation for floors 5 to 12.',              -40, 60, 'Planning'],
        [2, 2, 'Structural Review — Green Valley', 'Structural audit and strengthening report for the Green Valley Residency.', -120, -10, 'Completed'],
        [4, 3, 'Cleanroom Fit-out',              'Class 100,000 cleanroom fit-out for the new tablet-packaging line.',    -25, 75, 'In Progress'],
    ];
    $p = [];
    foreach ($projectRows as $i => $r) {
        $p[$i] = db_insert('INSERT INTO projects (client_id, manager_id, title, description, start_date, deadline, status) VALUES (?,?,?,?,?,?,?)',
            [$c[$r[0]], $u[$r[1]], $r[2], $r[3], $d($r[4]), $d($r[5]), $r[6]]);
    }

    // ----- Tasks: project, title, assignee, priority, due (days), status -----
    $taskRows = [
        [0, 'Foundation drawing review',        3, 'Medium', -4, 'Done'],
        [0, 'Order steel beams',                2, 'High',    3, 'In Progress'],
        [0, 'Site survey and soil test report', 2, 'Medium', -9, 'Done'],
        [0, 'Erection schedule sign-off',       3, 'Low',    12, 'To Do'],
        [1, 'Load calculation sheet',           3, 'High',   -2, 'In Progress'],
        [1, 'Single-line diagram draft',        3, 'Medium',  5, 'To Do'],
        [1, 'Client layout review meeting',     2, 'Medium',  1, 'To Do'],
        [1, 'Cable routing plan',               3, 'Low',    -6, 'Done'],
        [2, 'Transformer spec comparison',      2, 'High',    2, 'In Progress'],
        [2, 'Safety checklist update',          2, 'Medium',  6, 'To Do'],
        [2, 'Shutdown window planning',         2, 'High',   -1, 'To Do'],
        [3, 'Heat load estimation',             3, 'Medium', 14, 'To Do'],
        [3, 'Vendor quotations',                1, 'Low',     9, 'To Do'],
        [4, 'Final report submission',          3, 'Medium', -12, 'Done'],
        [4, 'Client handover',                  2, 'Low',    -10, 'Done'],
        [5, 'Cleanroom class specification',    3, 'High',    8, 'In Progress'],
        [5, 'Material sample submission',       1, 'Medium',  4, 'To Do'],
        [5, 'Budget breakdown',                 1, 'Medium',  2, 'In Progress'],
    ];
    foreach ($taskRows as $r) {
        db_insert('INSERT INTO tasks (project_id, title, assigned_to, priority, due_date, status) VALUES (?,?,?,?,?,?)',
            [$p[$r[0]], $r[1], $u[$r[2]], $r[3], $d($r[4]), $r[5]]);
    }

    // ----- Invoices: client, project, amount, issued (days), due (days), status, paid (days) -----
    // Three invoices are past their due date but still "Pending" (they show a red "days late" chip).
    $invoiceRows = [
        [3, 2, 350000, -150, -120, 'Paid', -122],
        [0, null, 180000, -130, -100, 'Paid', -105],
        [0, 0, 450000, -95, -65, 'Paid', -70],
        [1, 1, 320000, -70, -40, 'Paid', -44],
        [3, 2, 780000, -60, -30, 'Paid', -31],
        [2, 4, 240000, -55, -25, 'Paid', -26],
        [0, 0, 380000, -42, -12, 'Pending', null],
        [1, 1, 210000, -35, -5, 'Pending', null],
        [3, 2, 520000, -28, -7, 'Overdue', null],
        [5, 3, 150000, -20, 10, 'Pending', null],
        [4, 5, 640000, -12, 18, 'Pending', null],
        [2, 4, 95000, -8, -2, 'Pending', null],
        [4, 5, 275000, -3, 27, 'Pending', null],
        [5, null, 60000, -1, 14, 'Pending', null],
    ];
    foreach ($invoiceRows as $r) {
        $id = db_insert('INSERT INTO invoices (client_id, project_id, amount, issue_date, due_date, status, paid_date) VALUES (?,?,?,?,?,?,?)',
            [$c[$r[0]], $r[1] === null ? null : $p[$r[1]], (float)$r[2], $d($r[3]), $d($r[4]), $r[5], $r[6] === null ? null : $d($r[6])]);
        db_exec('UPDATE invoices SET invoice_no = ? WHERE id = ?', [sprintf('INV-%04d', $id), $id]);
    }

    // ----- Attendance: last 10 days (Friday and Saturday are the weekend in Bangladesh) -----
    for ($day = 9; $day >= 0; $day--) {
        $date = $d(-$day);
        $weekday = (int) date('N', strtotime($date));
        if ($day > 0 && ($weekday === 5 || $weekday === 6)) { continue; }
        foreach ($u as $i => $userId) {
            if ($day === 0) {
                // Today: three people are in, Sadia has not checked in yet.
                $today = [0 => ['Present', '09:42:00'], 1 => ['Present', '09:51:00'], 2 => ['Late', '10:25:00']];
                if (isset($today[$i])) {
                    db_insert('INSERT INTO attendance (user_id, att_date, status, check_in) VALUES (?,?,?,?)', [$userId, $date, $today[$i][0], $today[$i][1]]);
                }
                continue;
            }
            $k = ($i * 7 + $day * 3) % 10;
            $out = sprintf('17:%02d:00', 10 + (($i + $day) * 7) % 40);
            if ($k === 0) { db_insert('INSERT INTO attendance (user_id, att_date, status) VALUES (?,?,?)', [$userId, $date, 'Absent']); }
            elseif ($k === 1) { db_insert('INSERT INTO attendance (user_id, att_date, status) VALUES (?,?,?)', [$userId, $date, 'Leave']); }
            elseif ($k <= 3) { db_insert('INSERT INTO attendance (user_id, att_date, status, check_in, check_out) VALUES (?,?,?,?,?)', [$userId, $date, 'Late', sprintf('10:%02d:00', 12 + (($i + $day) * 5) % 30), $out]); }
            else { db_insert('INSERT INTO attendance (user_id, att_date, status, check_in, check_out) VALUES (?,?,?,?,?)', [$userId, $date, 'Present', sprintf('09:%02d:00', 30 + (($i + $day) * 4) % 28), $out]); }
        }
    }

    // ----- Design files (small placeholder text files so Download works) -----
    if (!is_dir(UPLOAD_DIR)) { mkdir(UPLOAD_DIR, 0775, true); }
    $fileRows = [
        [0, 'Foundation_Notes.txt', 1, -20, 3, 'First draft', "Foundation design notes\nVersion 1 - first draft.\n"],
        [0, 'Foundation_Notes.txt', 2, -4, 3, 'Updated after soil test', "Foundation design notes\nVersion 2 - updated after the soil test report.\n"],
        [1, 'Load_Calculation_Summary.txt', 1, -9, 3, 'Initial loads', "Load calculation summary\nVersion 1.\n"],
        [2, 'Transformer_Spec_Comparison.txt', 1, -6, 2, 'Three vendors compared', "Transformer specification comparison\nVersion 1.\n"],
    ];
    foreach ($fileRows as $r) {
        $stored = bin2hex(random_bytes(16)) . '.txt';
        file_put_contents(UPLOAD_DIR . $stored, $r[6]);
        db_insert('INSERT INTO design_files (project_id, original_name, stored_name, version, file_size, notes, uploaded_by, uploaded_at) VALUES (?,?,?,?,?,?,?,?)',
            [$p[$r[0]], $r[1], $stored, $r[2], strlen($r[6]), $r[5], $u[$r[4]], $d($r[3]) . ' 11:00:00']);
    }

    // ----- A few activity-log entries and a welcome notification -----
    $activityRows = [
        [2, 'projects', 'Moved task "Order steel beams" to In Progress', '-3 hours'],
        [3, 'files', 'Uploaded Foundation_Notes.txt as version 2', '-1 day'],
        [1, 'accounts', 'Created invoice INV-0014 (৳60,000)', '-1 day'],
    ];
    foreach ($activityRows as $r) {
        db_insert('INSERT INTO activity_log (user_id, module, action, created_at) VALUES (?,?,?,?)',
            [$u[$r[0]], $r[1], $r[2], date('Y-m-d H:i:s', strtotime($r[3]))]);
    }
    notify($u[0], 'Welcome to A2Z Central! Explore the modules from the sidebar.', 'accounts.php', 'info');

    return ['users' => count($u), 'clients' => count($c), 'projects' => count($p), 'tasks' => count($taskRows),
            'invoices' => count($invoiceRows), 'files' => count($fileRows)];
}

// =====================================================================
if (!$installed && $_SERVER['REQUEST_METHOD'] === 'POST') {
    try {
        $m = new mysqli(DB_HOST, DB_USER, DB_PASS);
        $m->set_charset('utf8mb4');
        $m->query('CREATE DATABASE IF NOT EXISTS `' . DB_NAME . '` CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci');
        $m->select_db(DB_NAME);
        $tableCount = run_schema($m);
        $m->close();

        require_once __DIR__ . '/includes/helpers.php';      // also loads includes/db.php
        $counts = seed_demo();
        $counts['tables'] = $tableCount;

        file_put_contents($lockFile, date('c'));
        $done = true;
    } catch (Throwable $ex) {
        $error = $ex->getMessage();
    }
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Install · <?= h(APP_NAME) ?></title>
<style>
  *{box-sizing:border-box}
  body{margin:0;min-height:100vh;display:grid;place-items:center;padding:24px;background:linear-gradient(135deg,#0A1224,#17264A);font-family:"Segoe UI",system-ui,sans-serif;color:#0F1B33}
  .box{width:100%;max-width:560px;background:#fff;border-radius:18px;padding:34px;box-shadow:0 30px 80px rgba(0,0,0,.35)}
  .logo{display:inline-block;background:#101C36;color:#F5A623;font-weight:800;letter-spacing:.04em;padding:8px 14px;border-radius:10px;margin-bottom:18px}
  h1{margin:0 0 8px;font-size:26px} p{color:#5E6B85;line-height:1.6}
  table{width:100%;border-collapse:collapse;margin:16px 0;font-size:14px} td{padding:8px 0;border-bottom:1px solid #E3E8F0} td:last-child{text-align:right;font-weight:600}
  .btn{display:inline-block;border:0;background:#F5A623;color:#1b1300;font-weight:700;font-size:16px;padding:13px 24px;border-radius:12px;cursor:pointer;text-decoration:none}
  .err{background:#FDE8EA;color:#9B1C2C;padding:12px 14px;border-radius:10px;margin:14px 0;font-size:14px}
  .ok{background:#E4F5EE;color:#0B5E43;padding:12px 14px;border-radius:10px;margin:14px 0;font-size:14px}
  code{background:#EEF1F6;padding:2px 6px;border-radius:6px}
</style>
</head>
<body>
<div class="box">
  <span class="logo">A2Z</span>
<?php if ($done): ?>
  <h1>Installation complete</h1>
  <div class="ok">The database <b><?= h(DB_NAME) ?></b> was created with <?= h($counts['tables'] ?? 0) ?> tables and demo data.</div>
  <table>
    <tr><td>Administrator</td><td>admin@a2z.com / Admin@123</td></tr>
    <tr><td>Staff member</td><td>nusrat@a2z.com / Staff@123</td></tr>
  </table>
  <p>Next: open the login page and sign in as the administrator. The sidebar opens Accounts &amp; HR, Client Records, Design Files and Project Tracking.</p>
  <p><a class="btn" href="login.php">Go to the login page</a></p>
  <p style="font-size:13px">When your project is finished, delete <code>setup.php</code> from the folder.</p>
<?php elseif ($installed): ?>
  <h1>Already installed</h1>
  <p>A2Z Central has already been set up on this computer.</p>
  <p><a class="btn" href="login.php">Go to the login page</a></p>
  <p style="font-size:13px">To reinstall from scratch: drop the <code><?= h(DB_NAME) ?></code> database in phpMyAdmin and delete <code>config/installed.lock</code>.</p>
<?php else: ?>
  <h1>Install A2Z Central</h1>
  <p>This creates the database and tables, then adds demo data (users, clients, projects, invoices…).</p>
  <table>
    <tr><td>MySQL host</td><td><?= h(DB_HOST) ?></td></tr>
    <tr><td>MySQL user</td><td><?= h(DB_USER) ?></td></tr>
    <tr><td>Database name</td><td><?= h(DB_NAME) ?></td></tr>
  </table>
<?php if ($error !== ''): ?>
  <div class="err"><b>Installation failed:</b> <?= h($error) ?><br>Check that MySQL is running in XAMPP and that the details in <code>config/config.php</code> are correct.</div>
<?php endif; ?>
  <form method="post"><button class="btn">Install now</button></form>
<?php endif; ?>
</div>
</body>
</html>
