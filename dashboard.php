<?php
// dashboard.php — the home page. It AGGREGATES data from every module in one place.
require_once __DIR__ . '/includes/queries.php';
require_login();

$me = current_user();
$isAdmin = is_admin();
$uid = user_id();
$today = date('Y-m-d');
$monthStart = date('Y-m-01');

$hour = (int) date('G');
$greeting = $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening');

// ---------- Numbers used by several cards ----------
$staffTotal = (int) db_value('SELECT COUNT(*) FROM users WHERE is_active = 1');
$presentToday = (int) db_value("SELECT COUNT(*) FROM attendance WHERE att_date = ? AND status IN ('Present','Late')", [$today]);
$activeProjects = (int) db_value("SELECT COUNT(*) FROM projects WHERE status IN ('Planning','In Progress')");

// ---------- KPI cards (admins see money, staff see their own work) ----------
if ($isAdmin) {
    $invoicedMonth = (float) db_value('SELECT COALESCE(SUM(amount),0) FROM invoices WHERE issue_date >= ?', [$monthStart]);
    $outstanding = (float) db_value("SELECT COALESCE(SUM(amount),0) FROM invoices WHERE status IN ('Pending','Overdue')");
    // Unpaid invoices whose due date has already passed.
    $overdueCount = (int) db_value("SELECT COUNT(*) FROM invoices WHERE status <> 'Paid' AND due_date < ?", [$today]);
    $pendingAppr = (int) db_value("SELECT COUNT(*) FROM approvals WHERE status IN ('Pending','Overdue')");

    $kpis = [
        ['label' => 'Invoiced this month', 'value' => $invoicedMonth, 'text' => money($invoicedMonth), 'format' => 'money', 'icon' => 'receipt', 'tone' => 'info', 'hint' => 'Since ' . date('d M', strtotime($monthStart))],
        ['label' => 'Outstanding', 'value' => $outstanding, 'text' => money($outstanding), 'format' => 'money', 'icon' => 'wallet', 'tone' => 'warning', 'hint' => 'Pending and overdue invoices'],
        ['label' => 'Overdue invoices', 'value' => $overdueCount, 'text' => (string)$overdueCount, 'format' => 'int', 'icon' => 'alert', 'tone' => 'danger', 'hint' => 'Unpaid and past the due date'],
        ['label' => 'Active projects', 'value' => $activeProjects, 'text' => (string)$activeProjects, 'format' => 'int', 'icon' => 'briefcase', 'tone' => 'success', 'hint' => 'Planning or in progress'],
        ['label' => 'Pending approvals', 'value' => $pendingAppr, 'text' => (string)$pendingAppr, 'format' => 'int', 'icon' => 'shield', 'tone' => 'info', 'hint' => 'Waiting for a decision'],
        ['label' => 'Present today', 'value' => $presentToday, 'text' => $presentToday . ' / ' . $staffTotal, 'format' => 'text', 'icon' => 'users', 'tone' => 'success', 'hint' => 'Checked in at the office'],
    ];
    $heroLine = $pendingAppr . ' approval(s) waiting and ' . $overdueCount . ' overdue invoice(s) need attention today.';
} else {
    $openTasks = (int) db_value("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status <> 'Done'", [$uid]);
    $dueWeek = (int) db_value("SELECT COUNT(*) FROM tasks WHERE assigned_to = ? AND status <> 'Done' AND due_date <= ?", [$uid, date('Y-m-d', strtotime('+7 days'))]);
    $myPending = (int) db_value("SELECT COUNT(*) FROM approvals WHERE requested_by = ? AND status IN ('Pending','Overdue')", [$uid]);

    $kpis = [
        ['label' => 'My open tasks', 'value' => $openTasks, 'text' => (string)$openTasks, 'format' => 'int', 'icon' => 'kanban', 'tone' => 'info', 'hint' => 'Assigned to you'],
        ['label' => 'Due within 7 days', 'value' => $dueWeek, 'text' => (string)$dueWeek, 'format' => 'int', 'icon' => 'clock', 'tone' => 'warning', 'hint' => 'Includes late tasks'],
        ['label' => 'Active projects', 'value' => $activeProjects, 'text' => (string)$activeProjects, 'format' => 'int', 'icon' => 'briefcase', 'tone' => 'success', 'hint' => 'Planning or in progress'],
        ['label' => 'My pending approvals', 'value' => $myPending, 'text' => (string)$myPending, 'format' => 'int', 'icon' => 'shield', 'tone' => 'info', 'hint' => 'Requests you submitted'],
    ];
    $heroLine = 'You have ' . $openTasks . ' open task(s), ' . $dueWeek . ' due within a week.';
}

// ---------- Chart data ----------
if ($isAdmin) {
    // Bar chart: money invoiced in each of the last 6 months.
    $months = [];
    for ($i = 5; $i >= 0; $i--) {
        $months[date('Y-m', strtotime('-' . $i . ' months', strtotime($monthStart)))] = 0;
    }
    $firstMonth = date('Y-m-01', strtotime('-5 months', strtotime($monthStart)));
    foreach (db_all("SELECT DATE_FORMAT(issue_date, '%Y-%m') AS ym, SUM(amount) AS total
                     FROM invoices WHERE issue_date >= ? GROUP BY ym", [$firstMonth]) as $r) {
        if (isset($months[$r['ym']])) { $months[$r['ym']] = (float)$r['total']; }
    }
    $barLabels = [];
    foreach (array_keys($months) as $ym) { $barLabels[] = date('M', strtotime($ym . '-01')); }
    $barTitle = 'Invoiced per month';
    $barValues = array_values($months);
    $barMoney = true;

    // Doughnut: invoices by status.
    $donutTitle = 'Invoices by status';
    $donutLabels = []; $donutValues = []; $donutColors = [];
    $colorMap = ['Paid' => '#12805C', 'Pending' => '#E3A008', 'Overdue' => '#C62F3F'];
    foreach (db_all('SELECT status, COUNT(*) AS c FROM invoices GROUP BY status') as $r) {
        $donutLabels[] = $r['status'];
        $donutValues[] = (int)$r['c'];
        $donutColors[] = $colorMap[$r['status']];
    }
} else {
    $barTitle = 'Open tasks per project';
    $barLabels = []; $barValues = []; $barMoney = false;
    foreach (db_all("SELECT p.title, COUNT(*) AS c FROM tasks t JOIN projects p ON p.id = t.project_id
                     WHERE t.status <> 'Done' GROUP BY p.id, p.title ORDER BY c DESC LIMIT 6") as $r) {
        $barLabels[] = $r['title'];
        $barValues[] = (int)$r['c'];
    }
    $donutTitle = 'Tasks by status';
    $donutLabels = []; $donutValues = []; $donutColors = [];
    $colorMap = ['To Do' => '#8A95AB', 'In Progress' => '#1F5FD1', 'Done' => '#12805C'];
    foreach (db_all('SELECT status, COUNT(*) AS c FROM tasks GROUP BY status') as $r) {
        $donutLabels[] = $r['status'];
        $donutValues[] = (int)$r['c'];
        $donutColors[] = $colorMap[$r['status']];
    }
}
$chartJson = json_encode([
    'bar'   => ['labels' => $barLabels, 'values' => $barValues, 'money' => $barMoney],
    'donut' => ['labels' => $donutLabels, 'values' => $donutValues, 'colors' => $donutColors],
], JSON_HEX_TAG | JSON_HEX_AMP);

// ---------- Lists ----------
$deadlines = upcoming_deadlines(7, 7);
$activity = db_all('SELECT a.*, u.name AS user_name FROM activity_log a
                    LEFT JOIN users u ON u.id = a.user_id
                    ORDER BY a.id DESC LIMIT 7');

$pageTitle = 'Dashboard';
$pageSubtitle = 'Live overview of every module';
$activePage = 'dashboard';
?>
<?php include __DIR__ . '/includes/layout_top.php'; ?>

<section class="hero">
  <div class="hero-text">
    <p class="hero-kicker"><?= e(date('l, d F Y')) ?></p>
    <h2><?= e($greeting) ?>, <?= e(first_name($me['name'])) ?>.</h2>
    <p><?= e($heroLine) ?></p>
  </div>
<?php if ($isAdmin): ?>
  <div class="hero-actions">
    <a class="btn btn-accent" href="accounts.php?tab=invoices"><?= icon('receipt', 16) ?> Open invoices</a>
    <a class="btn btn-glass" href="approvals.php">Review approvals</a>
  </div>
<?php else: ?>
  <div class="hero-actions">
    <a class="btn btn-accent" href="projects.php?tab=board"><?= icon('kanban', 16) ?> Open my task board</a>
    <a class="btn btn-glass" href="accounts.php?tab=attendance">Check in</a>
  </div>
<?php endif; ?>
</section>

<div class="kpi-grid kpi-<?= e(count($kpis)) ?>">
<?php foreach ($kpis as $k): ?>
  <div class="kpi kpi-<?= e($k['tone']) ?>">
    <div class="kpi-top"><span class="kpi-icon"><?= icon($k['icon'], 20) ?></span><span class="kpi-label"><?= e($k['label']) ?></span></div>
    <div class="kpi-value" data-count="<?= e($k['value']) ?>" data-format="<?= e($k['format']) ?>"><?= e($k['text']) ?></div>
    <div class="kpi-hint"><?= e($k['hint']) ?></div>
  </div>
<?php endforeach; ?>
</div>

<div class="grid-charts">
  <div class="card">
    <div class="card-head"><h3 class="card-title"><?= e($barTitle) ?></h3></div>
    <div class="card-body"><div class="chart-box" id="chartBar"><canvas></canvas></div></div>
  </div>
  <div class="card">
    <div class="card-head"><h3 class="card-title"><?= e($donutTitle) ?></h3></div>
    <div class="card-body"><div class="chart-box chart-box-sm" id="chartDonut"><canvas></canvas></div></div>
  </div>
</div>
<script type="application/json" id="chartData"><?= $chartJson ?></script>

<div class="grid-2">
  <div class="card">
    <div class="card-head"><h3 class="card-title">Coming up in the next 7 days</h3></div>
    <div class="list">
<?php foreach ($deadlines as $d): ?>
      <a class="list-item" href="<?= e($d['link']) ?>">
        <span class="li-icon"><?= icon($d['icon'], 18) ?></span>
        <span class="li-main"><b><?= e($d['title']) ?></b><small><?= e($d['type']) ?></small></span>
        <?= due_chip($d['date']) ?>
      </a>
<?php endforeach; ?>
<?php if (count($deadlines) === 0): ?>
      <div class="empty-inline">Nothing due this week. Nice and calm.</div>
<?php endif; ?>
    </div>
  </div>

  <div class="stack">
    <div class="card">
      <div class="card-head"><h3 class="card-title">Recent activity</h3></div>
      <div class="list">
<?php foreach ($activity as $a): ?>
        <div class="list-item static">
          <?= avatar($a['user_name'] ? $a['user_name'] : 'System') ?>
          <span class="li-main"><b><?= e($a['action']) ?></b><small><?= e($a['user_name'] ? $a['user_name'] : 'System') ?></small></span>
          <span class="muted small"><?= e(time_ago($a['created_at'])) ?></span>
        </div>
<?php endforeach; ?>
<?php if (count($activity) === 0): ?>
        <div class="empty-inline">No activity yet.</div>
<?php endif; ?>
      </div>
    </div>
  </div>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
