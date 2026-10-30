<?php
// clients.php — Client Records module.
// Staff can add / edit clients and log follow-ups. Only admins can delete.
require_once __DIR__ . '/includes/auth.php';
require_login();

$today = date('Y-m-d');

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $do = post('do');
    $id = post_id('id');

    if ($do === 'delete_client') {
        if (!is_admin()) { forbidden(); }
        try {
            $old = db_one('SELECT name FROM clients WHERE id = ?', [$id]);
            db_exec('DELETE FROM clients WHERE id = ?', [$id]);
            log_activity('clients', 'Deleted client ' . ($old ? $old['name'] : '#' . $id));
            flash('success', 'Client deleted.');
        } catch (mysqli_sql_exception $ex) {
            // The foreign keys stop us deleting a client that still has projects or invoices.
            flash('error', 'This client still has projects or invoices, so it cannot be deleted.');
        }
        redirect('clients.php');
    }

    if ($do === 'log_followup' && $id) {
        $next = post_date('next_followup');
        db_exec('UPDATE clients SET last_contact = ?, next_followup = ? WHERE id = ?', [$today, $next, $id]);
        $old = db_one('SELECT name FROM clients WHERE id = ?', [$id]);
        log_activity('clients', 'Logged a follow-up with ' . ($old ? $old['name'] : 'a client'));
        flash('success', 'Follow-up logged.');
        redirect('clients.php');
    }

    if ($do === 'create_client' || $do === 'update_client') {
        $name = post('name');
        $email = post('email');
        if ($name === '') {
            flash('error', 'Please enter the client name.');
            redirect('clients.php');
        }
        if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'That email address does not look right.');
            redirect('clients.php');
        }
        $values = [$name, post('contact_person'), post('phone'), $email, post('address'), post('notes'),
                   post_date('last_contact'), post_date('next_followup')];

        if ($do === 'create_client') {
            db_insert('INSERT INTO clients (name, contact_person, phone, email, address, notes, last_contact, next_followup)
                       VALUES (?,?,?,?,?,?,?,?)', $values);
            log_activity('clients', 'Added client ' . $name);
            flash('success', 'Client added.');
        } elseif ($id) {
            $values[] = $id;
            db_exec('UPDATE clients SET name = ?, contact_person = ?, phone = ?, email = ?, address = ?, notes = ?,
                     last_contact = ?, next_followup = ? WHERE id = ?', $values);
            log_activity('clients', 'Updated client ' . $name);
            flash('success', 'Client updated.');
        }
        redirect('clients.php');
    }
}

// Each client row also carries two numbers taken from OTHER modules (see the sub-queries).
$clients = db_all("SELECT c.*,
        (SELECT COUNT(*) FROM projects p WHERE p.client_id = c.id) AS projects_count,
        (SELECT COALESCE(SUM(i.amount), 0) FROM invoices i
          WHERE i.client_id = c.id AND i.status IN ('Pending', 'Overdue')) AS outstanding
    FROM clients c ORDER BY c.name");

foreach ($clients as $i => $c) {
    $clients[$i]['fu_status'] = followup_status($c['next_followup']);
    $clients[$i]['follow_json'] = json_encode(['id' => $c['id'], 'name' => $c['name']]);
}

$pageTitle = 'Client Records';
$pageSubtitle = 'Contacts, follow-ups and linked projects';
$activePage = 'clients';
?>
<?php include __DIR__ . '/includes/layout_top.php'; ?>

<div class="card">
  <div class="toolbar">
    <div class="search"><?= icon('search', 17) ?><input class="input" type="search" data-filter="#clientTable" placeholder="Search clients, contacts, phone…"></div>
    <select class="select" data-filter-status="#clientTable">
      <option value="">All follow-ups</option>
      <option>Overdue</option><option>Due soon</option><option>On track</option>
    </select>
    <button class="btn btn-accent push-right" data-modal-open="clientModal"><?= icon('plus', 17) ?> Add client</button>
  </div>
  <div class="table-wrap">
    <table class="table" id="clientTable">
      <thead><tr><th>Client</th><th>Contact</th><th>Last contact</th><th>Next follow-up</th><th class="right">Projects</th><?php if (is_admin()): ?><th class="right">Outstanding</th><?php endif; ?><th></th></tr></thead>
      <tbody>
<?php foreach ($clients as $r): ?>
        <tr data-status="<?= e($r['fu_status']) ?>">
          <td><div class="cell-user"><?= avatar($r['name']) ?><div><b><?= e($r['name']) ?></b><small class="sub"><?= e($r['address']) ?></small></div></div></td>
          <td><b class="soft"><?= e($r['contact_person']) ?></b><small class="sub"><?= e($r['phone']) ?><?= $r['email'] ? ' · ' . e($r['email']) : '' ?></small></td>
          <td class="nowrap"><?= e(fmt_date($r['last_contact'])) ?></td>
          <td><?= $r['next_followup'] ? due_chip($r['next_followup']) : badge('No date') ?></td>
          <td class="right mono"><?= e($r['projects_count']) ?></td>
<?php if (is_admin()): ?>
          <td class="right mono"><?= (float)$r['outstanding'] > 0 ? e(money($r['outstanding'])) : '<span class="muted">—</span>' ?></td>
<?php endif; ?>
          <td>
            <div class="row-actions">
              <button class="btn btn-sm btn-ghost" title="Log a follow-up" data-modal-edit="followModal" data-row="<?= e($r['follow_json']) ?>"><?= icon('calendar', 15) ?> Follow-up</button>
              <button class="icon-btn" title="Edit" data-modal-edit="clientModal" data-row="<?= e(json_encode($r)) ?>"><?= icon('edit', 17) ?></button>
<?php if (is_admin()): ?>
              <form method="post" data-confirm="Delete <?= e($r['name']) ?>? This cannot be undone."><?= csrf_field() ?><input type="hidden" name="do" value="delete_client"><input type="hidden" name="id" value="<?= e($r['id']) ?>">
                <button class="icon-btn danger" title="Delete"><?= icon('trash', 17) ?></button></form>
<?php endif; ?>
            </div>
          </td>
        </tr>
<?php endforeach; ?>
        <tr class="no-results" hidden><td colspan="7">No clients match your search.</td></tr>
      </tbody>
    </table>
  </div>
</div>

<dialog class="modal" id="clientModal">
  <form method="post" class="modal-card" data-create-do="create_client" data-update-do="update_client">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="create_client">
    <input type="hidden" name="id" value="">
    <div class="modal-head"><h3 data-title-create="Add client" data-title-edit="Edit client">Add client</h3><button type="button" class="icon-btn" data-close><?= icon('x', 18) ?></button></div>
    <div class="modal-body form-grid">
      <div class="field span-2"><label class="label">Company / client name</label><input class="input" type="text" name="name" maxlength="150" required></div>
      <div class="field"><label class="label">Contact person</label><input class="input" type="text" name="contact_person" maxlength="100"></div>
      <div class="field"><label class="label">Phone</label><input class="input" type="text" name="phone" maxlength="30" placeholder="01XXX-XXXXXX"></div>
      <div class="field span-2"><label class="label">Email</label><input class="input" type="email" name="email" maxlength="150"></div>
      <div class="field span-2"><label class="label">Address</label><input class="input" type="text" name="address" maxlength="255"></div>
      <div class="field"><label class="label">Last contact</label><input class="input" type="date" name="last_contact"></div>
      <div class="field"><label class="label">Next follow-up</label><input class="input" type="date" name="next_followup"></div>
      <div class="field span-2"><label class="label">Notes <span class="optional">(optional)</span></label><input class="input" type="text" name="notes" maxlength="255"></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button class="btn btn-primary">Save client</button></div>
  </form>
</dialog>

<dialog class="modal modal-sm" id="followModal">
  <form method="post" class="modal-card" data-create-do="log_followup" data-update-do="log_followup">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="log_followup">
    <input type="hidden" name="id" value="">
    <div class="modal-head"><h3 data-title-create="Log a follow-up" data-title-edit="Log a follow-up">Log a follow-up</h3><button type="button" class="icon-btn" data-close><?= icon('x', 18) ?></button></div>
    <div class="modal-body form-grid">
      <p class="span-2 muted">This records <b>today</b> as the last contact. When should you speak to them next?</p>
      <div class="field span-2"><label class="label">Next follow-up date</label><input class="input" type="date" name="next_followup" min="<?= e($today) ?>"></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button class="btn btn-primary">Save follow-up</button></div>
  </form>
</dialog>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
