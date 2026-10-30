<?php
// monitoring.php — Office Monitoring module.
// Replaces "someone has to be at the office to see what happens" with a digital visitor log
// and a live activity timeline of everything staff do in the system.
require_once __DIR__ . '/includes/auth.php';
require_login();

$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $do = post('do');
    $id = post_id('id');

    if ($do === 'log_visit') {
        $name = post('visitor_name');
        $purpose = post('purpose');
        $timeIn = preg_match('/^\d{2}:\d{2}$/', post('time_in')) ? post('time_in') . ':00' : date('H:i:s');
        if ($name === '' || $purpose === '') {
            flash('error', 'Please enter the visitor name and the purpose of the visit.');
            redirect('monitoring.php');
        }
        db_insert('INSERT INTO visit_logs (visitor_name, organization, purpose, host_id, visit_date, time_in, logged_by)
                   VALUES (?,?,?,?,?,?,?)',
            [$name, post('organization'), $purpose, post_id('host_id'), $today, $timeIn, user_id()]);
        log_activity('monitoring', 'Visitor logged: ' . $name);
        flash('success', $name . ' has been signed in.');
        redirect('monitoring.php');
    }

    if ($do === 'checkout_visit' && $id) {
        $changed = db_exec('UPDATE visit_logs SET time_out = ? WHERE id = ? AND time_out IS NULL', [date('H:i:s'), $id]);
        if ($changed > 0) {
            log_activity('monitoring', 'A visitor signed out');
            flash('success', 'Visitor signed out.');
        }
        redirect('monitoring.php');
    }

    if ($do === 'delete_visit') {
        if (!is_admin()) { forbidden(); }
        db_exec('DELETE FROM visit_logs WHERE id = ?', [$id]);
        flash('success', 'Visit record deleted.');
        redirect('monitoring.php');
    }
}

// Which day are we looking at? (default: today)
$date = (isset($_GET['date']) && is_string($_GET['date']) && valid_date($_GET['date'])) ? $_GET['date'] : $today;
$prevDate = date('Y-m-d', strtotime($date . ' -1 day'));
$nextDate = date('Y-m-d', strtotime($date . ' +1 day'));
$isToday = ($date === $today);

$visits = db_all('SELECT v.*, u.name AS host_name FROM visit_logs v
                  LEFT JOIN users u ON u.id = v.host_id
                  WHERE v.visit_date = ? ORDER BY v.time_in DESC', [$date]);
$users = db_all('SELECT id, name FROM users WHERE is_active = 1 ORDER BY name');

$inside = 0;
foreach ($visits as $i => $v) {
    if (!$v['time_out']) { $inside++; }
    $visits[$i]['duration'] = '—';
    if ($v['time_out']) {
        $mins = (int)round((strtotime($v['time_out']) - strtotime($v['time_in'])) / 60);
        $visits[$i]['duration'] = $mins < 60 ? $mins . ' min' : floor($mins / 60) . 'h ' . ($mins % 60) . 'm';
    }
}
$presentCount = (int) db_value("SELECT COUNT(*) FROM attendance WHERE att_date = ? AND status IN ('Present','Late')", [$date]);

// Activity timeline (everything people did in the system)
$moduleIcons = ['accounts' => 'wallet', 'clients' => 'users', 'files' => 'folder', 'projects' => 'kanban', 'approvals' => 'shield',
                'attendance' => 'clock', 'auth' => 'user', 'monitoring' => 'eye', 'users' => 'user'];
$activity = db_all('SELECT a.*, u.name AS user_name FROM activity_log a LEFT JOIN users u ON u.id = a.user_id ORDER BY a.id DESC LIMIT 14');
foreach ($activity as $i => $a) {
    $activity[$i]['icon'] = isset($moduleIcons[$a['module']]) ? $moduleIcons[$a['module']] : 'bell';
}

$pageTitle = 'Office Monitoring';
$pageSubtitle = 'Visitor log and a live trail of office activity';
$activePage = 'monitoring';
?>
<?php include __DIR__ . '/includes/layout_top.php'; ?>

<div class="stat-strip">
  <div class="stat"><span class="stat-label">Visitors on <?= e($isToday ? 'today' : fmt_date($date)) ?></span><span class="stat-value"><?= e(count($visits)) ?></span></div>
  <div class="stat"><span class="stat-label">Currently inside</span><span class="stat-value text-warning"><?= e($isToday ? $inside : 0) ?></span></div>
  <div class="stat"><span class="stat-label">Staff present</span><span class="stat-value text-success"><?= e($presentCount) ?></span></div>
</div>

<div class="grid-split">
  <div class="card">
    <div class="card-head">
      <h3 class="card-title">Visitor log</h3>
      <div class="date-nav">
        <a class="icon-btn" href="monitoring.php?date=<?= e($prevDate) ?>" title="Previous day">&lsaquo;</a>
        <span class="date-label"><?= e($isToday ? 'Today · ' . date('d M') : fmt_date($date)) ?></span>
<?php if (!$isToday): ?>
        <a class="icon-btn" href="monitoring.php?date=<?= e($nextDate) ?>" title="Next day">&rsaquo;</a>
<?php endif; ?>
      </div>
    </div>
    <div class="toolbar">
      <div class="search"><?= icon('search', 17) ?><input class="input" type="search" data-filter="#visitTable" placeholder="Search visitors…"></div>
      <button class="btn btn-accent push-right" data-modal-open="visitModal"><?= icon('plus', 17) ?> Sign in a visitor</button>
    </div>
    <div class="table-wrap">
      <table class="table" id="visitTable">
        <thead><tr><th>Visitor</th><th>Purpose / host</th><th>Time</th><th></th></tr></thead>
        <tbody>
<?php foreach ($visits as $v): ?>
          <tr>
            <td class="col-name"><div class="cell-user"><?= avatar($v['visitor_name']) ?><div><b><?= e($v['visitor_name']) ?></b><small class="sub"><?= e($v['organization']) ?></small></div></div></td>
            <td><?= e($v['purpose']) ?><small class="sub">Host: <?= e($v['host_name'] ? $v['host_name'] : '—') ?></small></td>
            <td class="mono"><?= e(fmt_time($v['time_in'])) ?> <span class="muted">→</span> <?= $v['time_out'] ? e(fmt_time($v['time_out'])) . '<small class="sub">' . e($v['duration']) . ' on site</small>' : '<span class="badge badge-warning">Inside</span>' ?></td>
            <td>
              <div class="row-actions">
<?php if (!$v['time_out']): ?>
                <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="checkout_visit"><input type="hidden" name="id" value="<?= e($v['id']) ?>">
                  <button class="btn btn-sm btn-ghost"><?= icon('logout', 15) ?> Sign out</button></form>
<?php endif; ?>
<?php if (is_admin()): ?>
                <form method="post" data-confirm="Delete this visit record?"><?= csrf_field() ?><input type="hidden" name="do" value="delete_visit"><input type="hidden" name="id" value="<?= e($v['id']) ?>">
                  <button class="icon-btn danger" title="Delete"><?= icon('trash', 17) ?></button></form>
<?php endif; ?>
              </div>
            </td>
          </tr>
<?php endforeach; ?>
          <tr class="no-results" hidden><td colspan="4">No visitors match your search.</td></tr>
        </tbody>
      </table>
<?php if (count($visits) === 0): ?>
      <div class="empty-inline">No visitors recorded for this day.</div>
<?php endif; ?>
    </div>
  </div>

  <div class="card">
    <div class="card-head"><h3 class="card-title">Live activity trail</h3><span class="pill-live"><span class="dot-live"></span> Live</span></div>
    <div class="timeline">
<?php foreach ($activity as $a): ?>
      <div class="tl-item">
        <span class="tl-icon"><?= icon($a['icon'], 15) ?></span>
        <div><b><?= e($a['action']) ?></b><small><?= e($a['user_name'] ? $a['user_name'] : 'System') ?> · <?= e(time_ago($a['created_at'])) ?></small></div>
      </div>
<?php endforeach; ?>
<?php if (count($activity) === 0): ?>
      <div class="empty-inline">No activity yet.</div>
<?php endif; ?>
    </div>
  </div>
</div>

<dialog class="modal" id="visitModal">
  <form method="post" class="modal-card">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="log_visit">
    <div class="modal-head"><h3>Sign in a visitor</h3><button type="button" class="icon-btn" data-close><?= icon('x', 18) ?></button></div>
    <div class="modal-body form-grid">
      <div class="field"><label class="label">Visitor name</label><input class="input" type="text" name="visitor_name" maxlength="100" required></div>
      <div class="field"><label class="label">Organization</label><input class="input" type="text" name="organization" maxlength="120"></div>
      <div class="field span-2"><label class="label">Purpose of visit</label><input class="input" type="text" name="purpose" maxlength="255" required placeholder="e.g. Drawing review"></div>
      <div class="field"><label class="label">Meeting with</label>
        <select class="select" name="host_id"><option value="">Nobody in particular</option>
<?php foreach ($users as $u): ?>
          <option value="<?= e($u['id']) ?>"><?= e($u['name']) ?></option>
<?php endforeach; ?>
        </select></div>
      <div class="field"><label class="label">Time in</label><input class="input" type="time" name="time_in" value="<?= e(date('H:i')) ?>"></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button class="btn btn-primary">Sign in visitor</button></div>
  </form>
</dialog>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
