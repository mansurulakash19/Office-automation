<?php
// accounts.php — Accounts & HR module.
//   Tab "Invoices"   -> admins only (financial data is restricted)
//   Tab "Attendance" -> everyone can check in; admins see the whole team
require_once __DIR__ . '/includes/auth.php';
require_login();

$today = date('Y-m-d');
$uid = user_id();
$tab = isset($_GET['tab']) ? $_GET['tab'] : (is_admin() ? 'invoices' : 'attendance');
if ($tab !== 'invoices' && $tab !== 'attendance') { $tab = 'attendance'; }
if ($tab === 'invoices' && !is_admin()) { $tab = 'attendance'; }

// =====================================================================
//  Handle form submissions (POST)
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $do = post('do');

    // ---------- Invoices (admin only) ----------
    if (in_array($do, ['create_invoice', 'update_invoice', 'delete_invoice', 'mark_paid'], true)) {
        if (!is_admin()) { forbidden(); }
        $id = post_id('id');

        if ($do === 'delete_invoice' && $id) {
            $old = db_one('SELECT invoice_no FROM invoices WHERE id = ?', [$id]);
            db_exec('DELETE FROM invoices WHERE id = ?', [$id]);
            log_activity('accounts', 'Deleted invoice ' . ($old ? $old['invoice_no'] : '#' . $id));
            flash('success', 'Invoice deleted.');
            redirect('accounts.php?tab=invoices');
        }

        if ($do === 'mark_paid' && $id) {
            db_exec("UPDATE invoices SET status = 'Paid', paid_date = ? WHERE id = ?", [$today, $id]);
            $old = db_one('SELECT invoice_no FROM invoices WHERE id = ?', [$id]);
            log_activity('accounts', 'Marked invoice ' . ($old ? $old['invoice_no'] : '#' . $id) . ' as paid');
            flash('success', 'Invoice marked as paid.');
            redirect('accounts.php?tab=invoices');
        }

        // create / update: read and validate the form
        $clientId = post_id('client_id');
        $projectId = post_id('project_id');
        $amount = (float) str_replace(',', '', post('amount'));
        $issue = post_date('issue_date');
        $due = post_date('due_date');
        $status = in_array(post('status'), ['Pending', 'Paid', 'Overdue'], true) ? post('status') : 'Pending';
        $notes = post('notes');

        if (!$clientId || $amount <= 0 || !$issue || !$due) {
            flash('error', 'Please choose a client and fill in the amount and both dates.');
            redirect('accounts.php?tab=invoices');
        }
        if ($due < $issue) {
            flash('error', 'The due date cannot be earlier than the issue date.');
            redirect('accounts.php?tab=invoices');
        }

        if ($do === 'create_invoice') {
            $newId = db_insert('INSERT INTO invoices (client_id, project_id, amount, issue_date, due_date, status, paid_date, notes)
                                VALUES (?,?,?,?,?,?,?,?)',
                [$clientId, $projectId, $amount, $issue, $due, $status, $status === 'Paid' ? $today : null, $notes]);
            $number = sprintf('INV-%04d', $newId);
            db_exec('UPDATE invoices SET invoice_no = ? WHERE id = ?', [$number, $newId]);
            log_activity('accounts', 'Created invoice ' . $number . ' (' . money($amount) . ')');
            flash('success', 'Invoice ' . $number . ' created.');
        } elseif ($id) {
            $old = db_one('SELECT paid_date, invoice_no FROM invoices WHERE id = ?', [$id]);
            $paidDate = null;
            if ($status === 'Paid') { $paidDate = ($old && $old['paid_date']) ? $old['paid_date'] : $today; }
            db_exec('UPDATE invoices SET client_id = ?, project_id = ?, amount = ?, issue_date = ?, due_date = ?,
                     status = ?, paid_date = ?, notes = ? WHERE id = ?',
                [$clientId, $projectId, $amount, $issue, $due, $status, $paidDate, $notes, $id]);
            log_activity('accounts', 'Updated invoice ' . ($old ? $old['invoice_no'] : '#' . $id));
            flash('success', 'Invoice updated.');
        }
        redirect('accounts.php?tab=invoices');
    }

    // ---------- Attendance ----------
    if ($do === 'check_in') {
        $exists = db_one('SELECT id FROM attendance WHERE user_id = ? AND att_date = ?', [$uid, $today]);
        if (!$exists) {
            $status = date('H:i') > OFFICE_START ? 'Late' : 'Present';
            db_insert('INSERT INTO attendance (user_id, att_date, status, check_in) VALUES (?,?,?,?)',
                [$uid, $today, $status, date('H:i:s')]);
            log_activity('attendance', current_user()['name'] . ' checked in (' . $status . ')');
            flash('success', 'You are checked in at ' . date('h:i A') . '.');
        }
        redirect('accounts.php?tab=attendance');
    }

    if ($do === 'check_out') {
        $changed = db_exec('UPDATE attendance SET check_out = ? WHERE user_id = ? AND att_date = ? AND check_out IS NULL',
            [date('H:i:s'), $uid, $today]);
        if ($changed > 0) {
            log_activity('attendance', current_user()['name'] . ' checked out');
            flash('success', 'Checked out. See you tomorrow!');
        }
        redirect('accounts.php?tab=attendance');
    }

    if ($do === 'mark_attendance') {
        if (!is_admin()) { forbidden(); }
        $targetId = post_id('user_id');
        $status = in_array(post('status'), ['Present', 'Late', 'Absent', 'Leave'], true) ? post('status') : '';
        if ($targetId && $status !== '') {
            $row = db_one('SELECT id FROM attendance WHERE user_id = ? AND att_date = ?', [$targetId, $today]);
            if ($row) {
                db_exec('UPDATE attendance SET status = ? WHERE id = ?', [$status, (int)$row['id']]);
            } else {
                db_insert('INSERT INTO attendance (user_id, att_date, status) VALUES (?,?,?)', [$targetId, $today, $status]);
            }
            log_activity('attendance', 'Attendance set to ' . $status . ' for a team member');
            flash('success', 'Attendance saved.');
        }
        redirect('accounts.php?tab=attendance');
    }
}

// =====================================================================
//  Load data for the page (GET)
// =====================================================================
$invoices = []; $clients = []; $projects = []; $stats = [];
$myToday = null; $todayRows = []; $summary = []; $myHistory = [];

if ($tab === 'invoices') {
    $invoices = db_all("SELECT i.*, c.name AS client_name, p.title AS project_title
                        FROM invoices i
                        JOIN clients c ON c.id = i.client_id
                        LEFT JOIN projects p ON p.id = i.project_id
                        ORDER BY FIELD(i.status, 'Overdue', 'Pending', 'Paid'), i.due_date ASC, i.id DESC");
    $clients = db_all('SELECT id, name FROM clients ORDER BY name');
    $projects = db_all('SELECT p.id, p.title, c.name AS client_name FROM projects p JOIN clients c ON c.id = p.client_id ORDER BY p.title');

    $stats = ['total' => 0, 'paid' => 0, 'outstanding' => 0, 'overdue' => 0];
    foreach ($invoices as $inv) {
        $stats['total'] += (float)$inv['amount'];
        if ($inv['status'] === 'Paid') { $stats['paid'] += (float)$inv['amount']; }
        else { $stats['outstanding'] += (float)$inv['amount']; }
        if ($inv['status'] === 'Overdue') { $stats['overdue'] += (float)$inv['amount']; }
    }
} else {
    $myToday = db_one('SELECT * FROM attendance WHERE user_id = ? AND att_date = ?', [$uid, $today]);
    if (is_admin()) {
        $todayRows = db_all('SELECT u.id, u.name, u.designation, a.status, a.check_in, a.check_out
                             FROM users u
                             LEFT JOIN attendance a ON a.user_id = u.id AND a.att_date = ?
                             WHERE u.is_active = 1 ORDER BY u.name', [$today]);
        $summary = db_all("SELECT u.name,
                                  COALESCE(SUM(a.status = 'Present'), 0) AS present_days,
                                  COALESCE(SUM(a.status = 'Late'), 0) AS late_days,
                                  COALESCE(SUM(a.status = 'Absent'), 0) AS absent_days,
                                  COALESCE(SUM(a.status = 'Leave'), 0) AS leave_days
                           FROM users u
                           LEFT JOIN attendance a ON a.user_id = u.id AND a.att_date BETWEEN ? AND ?
                           WHERE u.is_active = 1 GROUP BY u.id, u.name ORDER BY u.name",
                          [date('Y-m-01'), $today]);
    } else {
        $myHistory = db_all('SELECT * FROM attendance WHERE user_id = ? ORDER BY att_date DESC LIMIT 14', [$uid]);
    }
}

$pageTitle = 'Accounts & HR';
$pageSubtitle = 'Invoices, payments and attendance';
$activePage = 'accounts';
?>
<?php include __DIR__ . '/includes/layout_top.php'; ?>

<div class="tabs">
<?php if (is_admin()): ?>
  <a class="tab <?= $tab === 'invoices' ? 'active' : '' ?>" href="accounts.php?tab=invoices"><?= icon('receipt', 17) ?> Invoices</a>
<?php endif; ?>
  <a class="tab <?= $tab === 'attendance' ? 'active' : '' ?>" href="accounts.php?tab=attendance"><?= icon('clock', 17) ?> Attendance</a>
</div>

<?php if ($tab === 'invoices'): ?>

<div class="stat-strip">
  <div class="stat"><span class="stat-label">Total invoiced</span><span class="stat-value"><?= e(money($stats['total'])) ?></span></div>
  <div class="stat"><span class="stat-label">Paid</span><span class="stat-value text-success"><?= e(money($stats['paid'])) ?></span></div>
  <div class="stat"><span class="stat-label">Outstanding</span><span class="stat-value text-warning"><?= e(money($stats['outstanding'])) ?></span></div>
  <div class="stat"><span class="stat-label">Overdue</span><span class="stat-value text-danger"><?= e(money($stats['overdue'])) ?></span></div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="search"><?= icon('search', 17) ?><input class="input" type="search" data-filter="#invoiceTable" placeholder="Search invoices, clients…"></div>
    <select class="select" data-filter-status="#invoiceTable">
      <option value="">All statuses</option>
      <option>Pending</option><option>Paid</option><option>Overdue</option>
    </select>
    <button class="btn btn-accent push-right" data-modal-open="invoiceModal"><?= icon('plus', 17) ?> New invoice</button>
  </div>
  <div class="table-wrap">
    <table class="table" id="invoiceTable">
      <thead><tr><th>Invoice</th><th>Client / project</th><th>Issued</th><th>Due</th><th class="right">Amount</th><th>Status</th><th></th></tr></thead>
      <tbody>
<?php foreach ($invoices as $r): ?>
        <tr data-status="<?= e($r['status']) ?>">
          <td class="mono strong"><?= e($r['invoice_no']) ?></td>
          <td><b><?= e($r['client_name']) ?></b><small class="sub"><?= e($r['project_title'] ? $r['project_title'] : 'No project linked') ?></small></td>
          <td class="nowrap"><?= e(fmt_date($r['issue_date'])) ?></td>
          <td class="nowrap"><?= $r['status'] === 'Paid' ? '<span class="muted">' . e(fmt_date($r['due_date'])) . '</span>' : due_chip($r['due_date']) ?></td>
          <td class="right mono strong"><?= e(money($r['amount'])) ?></td>
          <td><?= badge($r['status']) ?></td>
          <td>
            <div class="row-actions">
<?php if ($r['status'] !== 'Paid'): ?>
              <form method="post"><?= csrf_field() ?><input type="hidden" name="do" value="mark_paid"><input type="hidden" name="id" value="<?= e($r['id']) ?>">
                <button class="btn btn-sm btn-ghost" title="Mark as paid"><?= icon('check', 15) ?> Mark paid</button></form>
<?php endif; ?>
              <button class="icon-btn" title="Edit" data-modal-edit="invoiceModal" data-row="<?= e(json_encode($r)) ?>"><?= icon('edit', 17) ?></button>
              <form method="post" data-confirm="Delete invoice <?= e($r['invoice_no']) ?>? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="do" value="delete_invoice"><input type="hidden" name="id" value="<?= e($r['id']) ?>">
                <button class="icon-btn danger" title="Delete"><?= icon('trash', 17) ?></button></form>
            </div>
          </td>
        </tr>
<?php endforeach; ?>
        <tr class="no-results" hidden><td colspan="7">No invoices match your search.</td></tr>
      </tbody>
    </table>
  </div>
</div>

<dialog class="modal" id="invoiceModal">
  <form method="post" class="modal-card" data-create-do="create_invoice" data-update-do="update_invoice">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="create_invoice">
    <input type="hidden" name="id" value="">
    <div class="modal-head"><h3 data-title-create="New invoice" data-title-edit="Edit invoice">New invoice</h3><button type="button" class="icon-btn" data-close><?= icon('x', 18) ?></button></div>
    <div class="modal-body form-grid">
      <div class="field span-2"><label class="label">Client</label>
        <select class="select" name="client_id" required>
          <option value="">Choose a client…</option>
<?php foreach ($clients as $c): ?>
          <option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option>
<?php endforeach; ?>
        </select></div>
      <div class="field span-2"><label class="label">Project <span class="optional">(optional)</span></label>
        <select class="select" name="project_id">
          <option value="">No project</option>
<?php foreach ($projects as $p): ?>
          <option value="<?= e($p['id']) ?>"><?= e($p['title']) ?> — <?= e($p['client_name']) ?></option>
<?php endforeach; ?>
        </select></div>
      <div class="field"><label class="label">Amount (৳)</label><input class="input" type="number" name="amount" min="1" step="0.01" placeholder="250000" required></div>
      <div class="field"><label class="label">Status</label>
        <select class="select" name="status"><option>Pending</option><option>Paid</option><option>Overdue</option></select></div>
      <div class="field"><label class="label">Issue date</label><input class="input" type="date" name="issue_date" value="<?= e($today) ?>" required></div>
      <div class="field"><label class="label">Due date</label><input class="input" type="date" name="due_date" required></div>
      <div class="field span-2"><label class="label">Notes <span class="optional">(optional)</span></label><input class="input" type="text" name="notes" maxlength="255" placeholder="e.g. Phase 2 milestone payment"></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button class="btn btn-primary">Save invoice</button></div>
  </form>
</dialog>

<?php else: ?>

<div class="card attend-card">
  <div class="attend-left">
    <span class="attend-date"><?= e(date('l, d F')) ?></span>
<?php if (!$myToday): ?>
    <h3>You have not checked in yet</h3>
    <p class="muted">Office hours start at <?= e(date('h:i A', strtotime(OFFICE_START))) ?>. Checking in later is recorded as <b>Late</b>.</p>
<?php else: ?>
    <h3>Checked in at <?= e(fmt_time($myToday['check_in'])) ?> <?= badge($myToday['status']) ?></h3>
    <p class="muted"><?= $myToday['check_out'] ? 'You checked out at ' . e(fmt_time($myToday['check_out'])) . '.' : 'Remember to check out when you leave.' ?></p>
<?php endif; ?>
  </div>
  <form method="post">
    <?= csrf_field() ?>
<?php if (!$myToday): ?>
    <input type="hidden" name="do" value="check_in">
    <button class="btn btn-accent btn-lg"><?= icon('door', 18) ?> Check in now</button>
<?php elseif (!$myToday['check_out']): ?>
    <input type="hidden" name="do" value="check_out">
    <button class="btn btn-primary btn-lg"><?= icon('logout', 18) ?> Check out</button>
<?php else: ?>
    <span class="badge badge-success">Day complete</span>
<?php endif; ?>
  </form>
</div>

<?php if (is_admin()): ?>
<div class="card">
  <div class="card-head"><h3 class="card-title">Team today</h3><span class="muted small"><?= e(date('d M Y')) ?></span></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Team member</th><th>Status</th><th>Check in</th><th>Check out</th><th class="right">Set status</th></tr></thead>
      <tbody>
<?php foreach ($todayRows as $r): ?>
        <tr>
          <td><div class="cell-user"><?= avatar($r['name']) ?><div><b><?= e($r['name']) ?></b><small class="sub"><?= e($r['designation']) ?></small></div></div></td>
          <td><?= $r['status'] ? badge($r['status']) : '<span class="muted">Not marked</span>' ?></td>
          <td class="mono"><?= e(fmt_time($r['check_in'])) ?></td>
          <td class="mono"><?= e(fmt_time($r['check_out'])) ?></td>
          <td>
            <form method="post" class="inline-form">
              <?= csrf_field() ?><input type="hidden" name="do" value="mark_attendance"><input type="hidden" name="user_id" value="<?= e($r['id']) ?>">
              <select class="select select-sm" name="status"><option>Present</option><option>Late</option><option>Absent</option><option>Leave</option></select>
              <button class="btn btn-sm btn-ghost">Save</button>
            </form>
          </td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<div class="card">
  <div class="card-head"><h3 class="card-title">This month at a glance</h3><span class="muted small"><?= e(date('F Y')) ?></span></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Team member</th><th class="right">Present</th><th class="right">Late</th><th class="right">Absent</th><th class="right">Leave</th></tr></thead>
      <tbody>
<?php foreach ($summary as $s): ?>
        <tr>
          <td><div class="cell-user"><?= avatar($s['name']) ?><b><?= e($s['name']) ?></b></div></td>
          <td class="right mono"><?= e($s['present_days']) ?></td>
          <td class="right mono"><?= e($s['late_days']) ?></td>
          <td class="right mono"><?= e($s['absent_days']) ?></td>
          <td class="right mono"><?= e($s['leave_days']) ?></td>
        </tr>
<?php endforeach; ?>
      </tbody>
    </table>
  </div>
</div>

<?php else: ?>
<div class="card">
  <div class="card-head"><h3 class="card-title">My last 14 records</h3></div>
  <div class="table-wrap">
    <table class="table">
      <thead><tr><th>Date</th><th>Status</th><th>Check in</th><th>Check out</th></tr></thead>
      <tbody>
<?php foreach ($myHistory as $h): ?>
        <tr><td class="nowrap"><?= e(fmt_date($h['att_date'])) ?></td><td><?= badge($h['status']) ?></td><td class="mono"><?= e(fmt_time($h['check_in'])) ?></td><td class="mono"><?= e(fmt_time($h['check_out'])) ?></td></tr>
<?php endforeach; ?>
<?php if (count($myHistory) === 0): ?>
        <tr><td colspan="4" class="empty-inline">No attendance records yet.</td></tr>
<?php endif; ?>
      </tbody>
    </table>
  </div>
</div>
<?php endif; ?>

<?php endif; ?>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
