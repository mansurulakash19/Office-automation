<?php
// approvals.php — Approvals module (replaces paper signatures and email chains).
// Anyone can REQUEST an approval. Only admins can APPROVE or REJECT. Every decision is kept with a date and remarks.
require_once __DIR__ . '/includes/auth.php';
require_login();

$uid = user_id();
$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $do = post('do');
    $id = post_id('id');

    // ---------- New request ----------
    if ($do === 'create_approval') {
        $title = post('title');
        if ($title === '') {
            flash('error', 'Please enter what you need approved.');
            redirect('approvals.php');
        }
        $approverId = post_id('approver_id');
        $due = post_date('due_date');
        db_insert('INSERT INTO approvals (title, description, project_id, requested_by, approver_id, due_date)
                   VALUES (?,?,?,?,?,?)',
            [$title, post('description'), post_id('project_id'), $uid, $approverId, $due]);

        $text = current_user()['name'] . ' requested approval: ' . $title;
        if ($approverId) { notify($approverId, $text, 'approvals.php', 'info'); }
        else { notify_admins($text, 'approvals.php', 'info'); }
        log_activity('approvals', 'Requested approval: ' . $title);
        flash('success', 'Approval request sent.');
        redirect('approvals.php');
    }

    // ---------- Decision (admin only) ----------
    if ($do === 'decide_approval') {
        if (!is_admin()) { forbidden(); }
        $decision = post('decision');
        if ($id && in_array($decision, ['Approved', 'Rejected'], true)) {
            $changed = db_exec("UPDATE approvals SET status = ?, remarks = ?, decided_at = ?
                                WHERE id = ? AND status IN ('Pending', 'Overdue')",
                [$decision, post('remarks'), date('Y-m-d H:i:s'), $id]);
            $row = db_one('SELECT title, requested_by FROM approvals WHERE id = ?', [$id]);
            if ($changed > 0 && $row) {
                notify((int)$row['requested_by'], 'Your request "' . $row['title'] . '" was ' . strtolower($decision) . '.',
                    'approvals.php', $decision === 'Approved' ? 'success' : 'warning');
                log_activity('approvals', $decision . ': ' . $row['title']);
                flash('success', 'Request ' . strtolower($decision) . '.');
            }
        }
        redirect('approvals.php');
    }

    // ---------- Delete ----------
    if ($do === 'delete_approval' && $id) {
        $row = db_one('SELECT requested_by, status FROM approvals WHERE id = ?', [$id]);
        $ownPending = $row && (int)$row['requested_by'] === $uid && $row['status'] === 'Pending';
        if (!is_admin() && !$ownPending) { forbidden(); }
        db_exec('DELETE FROM approvals WHERE id = ?', [$id]);
        log_activity('approvals', 'Deleted an approval request');
        flash('success', 'Request deleted.');
        redirect('approvals.php');
    }
}

// Admins see every request. Staff only see the ones they submitted.
$sql = 'SELECT a.*, p.title AS project_title, r.name AS requester, ap.name AS approver_name
        FROM approvals a
        LEFT JOIN projects p ON p.id = a.project_id
        JOIN users r ON r.id = a.requested_by
        LEFT JOIN users ap ON ap.id = a.approver_id';
$params = [];
if (!is_admin()) { $sql .= ' WHERE a.requested_by = ?'; $params[] = $uid; }
$approvals = db_all($sql . " ORDER BY FIELD(a.status, 'Overdue', 'Pending', 'Approved', 'Rejected'), a.due_date ASC, a.id DESC", $params);

$counts = ['Pending' => 0, 'Overdue' => 0, 'Approved' => 0, 'Rejected' => 0];
foreach ($approvals as $i => $a) {
    $counts[$a['status']]++;
    $approvals[$i]['decide_json'] = json_encode(['id' => $a['id'], 'title' => $a['title'], 'requester' => $a['requester']]);
}

$projects = db_all('SELECT id, title FROM projects ORDER BY title');
$admins = db_all("SELECT id, name FROM users WHERE role = 'admin' AND is_active = 1 ORDER BY name");

$pageTitle = 'Approvals';
$pageSubtitle = is_admin() ? 'Requests waiting for your decision' : 'Your approval requests';
$activePage = 'approvals';
?>
<?php include __DIR__ . '/includes/layout_top.php'; ?>

<div class="stat-strip">
  <div class="stat"><span class="stat-label">Pending</span><span class="stat-value text-warning"><?= e($counts['Pending']) ?></span></div>
  <div class="stat"><span class="stat-label">Overdue</span><span class="stat-value text-danger"><?= e($counts['Overdue']) ?></span></div>
  <div class="stat"><span class="stat-label">Approved</span><span class="stat-value text-success"><?= e($counts['Approved']) ?></span></div>
  <div class="stat"><span class="stat-label">Rejected</span><span class="stat-value"><?= e($counts['Rejected']) ?></span></div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="search"><?= icon('search', 17) ?><input class="input" type="search" data-filter="#approvalTable" placeholder="Search requests…"></div>
    <select class="select" data-filter-status="#approvalTable">
      <option value="">All statuses</option>
      <option>Pending</option><option>Overdue</option><option>Approved</option><option>Rejected</option>
    </select>
    <button class="btn btn-accent push-right" data-modal-open="approvalModal"><?= icon('plus', 17) ?> Request approval</button>
  </div>
  <div class="table-wrap">
    <table class="table" id="approvalTable">
      <thead><tr><th>Request</th><th>Requested by</th><th>Needed by</th><th>Status</th><th></th></tr></thead>
      <tbody>
<?php foreach ($approvals as $a): ?>
        <tr data-status="<?= e($a['status']) ?>">
          <td>
            <b><?= e($a['title']) ?></b>
            <small class="sub"><?= e($a['project_title'] ? $a['project_title'] : 'No project') ?><?= $a['description'] ? ' · ' . e($a['description']) : '' ?></small>
            <?= $a['remarks'] ? '<small class="sub remark">“' . e($a['remarks']) . '”</small>' : '' ?>
          </td>
          <td><div class="cell-user"><?= avatar($a['requester'], 'sm') ?><span><?= e($a['requester']) ?></span></div></td>
          <td><?= in_array($a['status'], ['Approved', 'Rejected'], true) ? '<span class="muted">' . e(fmt_date($a['decided_at'])) . '</span>' : due_chip($a['due_date']) ?></td>
          <td><?= badge($a['status']) ?></td>
          <td>
            <div class="row-actions">
<?php if (is_admin() && ($a['status'] === 'Pending' || $a['status'] === 'Overdue')): ?>
              <button class="btn btn-sm btn-primary" data-modal-edit="decideModal" data-row="<?= e($a['decide_json']) ?>"><?= icon('shield', 15) ?> Decide</button>
<?php endif; ?>
<?php if (is_admin() || ($a['status'] === 'Pending' && (int)$a['requested_by'] === $uid)): ?>
              <form method="post" data-confirm="Delete this request?"><?= csrf_field() ?><input type="hidden" name="do" value="delete_approval"><input type="hidden" name="id" value="<?= e($a['id']) ?>">
                <button class="icon-btn danger" title="Delete"><?= icon('trash', 17) ?></button></form>
<?php endif; ?>
            </div>
          </td>
        </tr>
<?php endforeach; ?>
        <tr class="no-results" hidden><td colspan="5">No requests match your search.</td></tr>
      </tbody>
    </table>
<?php if (count($approvals) === 0): ?>
    <div class="empty-inline">No approval requests yet.</div>
<?php endif; ?>
  </div>
</div>

<dialog class="modal" id="approvalModal">
  <form method="post" class="modal-card">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="create_approval">
    <div class="modal-head"><h3>Request an approval</h3><button type="button" class="icon-btn" data-close><?= icon('x', 18) ?></button></div>
    <div class="modal-body form-grid">
      <div class="field span-2"><label class="label">What needs approval?</label><input class="input" type="text" name="title" maxlength="150" required placeholder="e.g. Steel beam supplier selection"></div>
      <div class="field"><label class="label">Project <span class="optional">(optional)</span></label>
        <select class="select" name="project_id"><option value="">No project</option>
<?php foreach ($projects as $p): ?>
          <option value="<?= e($p['id']) ?>"><?= e($p['title']) ?></option>
<?php endforeach; ?>
        </select></div>
      <div class="field"><label class="label">Send to</label>
        <select class="select" name="approver_id"><option value="">Any administrator</option>
<?php foreach ($admins as $ad): ?>
          <option value="<?= e($ad['id']) ?>"><?= e($ad['name']) ?></option>
<?php endforeach; ?>
        </select></div>
      <div class="field"><label class="label">Needed by</label><input class="input" type="date" name="due_date" min="<?= e($today) ?>"></div>
      <div class="field span-2"><label class="label">Details <span class="optional">(optional)</span></label><textarea class="textarea" name="description" rows="3" maxlength="500"></textarea></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button class="btn btn-primary">Send request</button></div>
  </form>
</dialog>

<?php if (is_admin()): ?>
<dialog class="modal modal-sm" id="decideModal">
  <form method="post" class="modal-card" data-create-do="decide_approval" data-update-do="decide_approval">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="decide_approval">
    <input type="hidden" name="id" value="">
    <div class="modal-head"><h3 data-title-create="Decide" data-title-edit="Make a decision">Make a decision</h3><button type="button" class="icon-btn" data-close><?= icon('x', 18) ?></button></div>
    <div class="modal-body form-grid">
      <div class="span-2 decide-box"><b data-bind="title"></b><small>Requested by <span data-bind="requester"></span></small></div>
      <div class="field span-2"><label class="label">Remarks <span class="optional">(optional)</span></label><textarea class="textarea" name="remarks" rows="3" maxlength="255" placeholder="Add a reason or instruction"></textarea></div>
    </div>
    <div class="modal-foot">
      <button type="button" class="btn btn-ghost" data-close>Cancel</button>
      <button class="btn btn-danger" name="decision" value="Rejected">Reject</button>
      <button class="btn btn-success" name="decision" value="Approved"><?= icon('check', 16) ?> Approve</button>
    </div>
  </form>
</dialog>
<?php endif; ?>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
