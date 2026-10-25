<?php
// users.php — Users & Roles (administrators only).
// This is where ROLE-BASED ACCESS is managed: 'admin' sees everything, 'staff' sees a limited view.
require_once __DIR__ . '/includes/auth.php';
require_admin();

$myId = user_id();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $do = post('do');
    $id = post_id('id');

    if ($do === 'delete_user') {
        if (!$id || $id === $myId) {
            flash('error', 'You cannot delete your own account.');
        } else {
            $old = db_one('SELECT name FROM users WHERE id = ?', [$id]);
            db_exec('DELETE FROM users WHERE id = ?', [$id]);
            log_activity('users', 'Deleted user ' . ($old ? $old['name'] : '#' . $id));
            flash('success', 'User deleted. (Tip: deactivating is usually better than deleting.)');
        }
        redirect('users.php');
    }

    if ($do === 'create_user' || $do === 'update_user') {
        $name = post('name');
        $email = strtolower(post('email'));
        $role = post('role') === 'admin' ? 'admin' : 'staff';
        $active = post('is_active') === '1' ? 1 : 0;
        $password = isset($_POST['password']) ? (string)$_POST['password'] : '';

        if ($name === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            flash('error', 'Please enter a name and a valid email address.');
            redirect('users.php');
        }
        $dup = db_one('SELECT id FROM users WHERE email = ? AND id <> ?', [$email, $id ? $id : 0]);
        if ($dup) {
            flash('error', 'Another user already has that email address.');
            redirect('users.php');
        }

        if ($do === 'create_user') {
            if (strlen($password) < 8) {
                flash('error', 'The password must be at least 8 characters long.');
                redirect('users.php');
            }
            db_insert('INSERT INTO users (name, email, password_hash, role, designation, is_active) VALUES (?,?,?,?,?,?)',
                [$name, $email, password_hash($password, PASSWORD_DEFAULT), $role, post('designation'), $active]);
            log_activity('users', 'Created user ' . $name . ' (' . $role . ')');
            flash('success', 'User created.');
        } elseif ($id) {
            if ($id === $myId && ($role !== 'admin' || $active !== 1)) {
                flash('error', 'You cannot remove your own admin access or deactivate yourself.');
                redirect('users.php');
            }
            db_exec('UPDATE users SET name = ?, email = ?, role = ?, designation = ?, is_active = ? WHERE id = ?',
                [$name, $email, $role, post('designation'), $active, $id]);
            if ($password !== '') {
                if (strlen($password) < 8) {
                    flash('error', 'Details saved, but the new password must be at least 8 characters.');
                    redirect('users.php');
                }
                db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $id]);
            }
            log_activity('users', 'Updated user ' . $name);
            flash('success', 'User updated.');
        }
        redirect('users.php');
    }
}

$users = db_all('SELECT * FROM users ORDER BY is_active DESC, role ASC, name ASC');
foreach ($users as $i => $u) {
    unset($users[$i]['password_hash']);      // never send password hashes to the page
}
$adminCount = 0;
foreach ($users as $u) { if ($u['role'] === 'admin' && $u['is_active']) { $adminCount++; } }

$pageTitle = 'Users & Roles';
$pageSubtitle = 'Who can sign in and what they can do';
$activePage = 'users';
?>
<?php include __DIR__ . '/includes/layout_top.php'; ?>

<div class="card role-card">
    <div class="card-head"><h3 class="card-title">What each role can do</h3></div>
    <div class="card-body role-grid">
      <div><span class="badge badge-info">Admin</span>
        <ul><li>Everything staff can do</li><li>Invoices &amp; financial data</li><li>Approve / reject requests</li><li>Delete records</li><li>Manage users &amp; run automation</li></ul></div>
      <div><span class="badge badge-neutral">Staff</span>
        <ul><li>Check in &amp; view own attendance</li><li>Clients, tasks &amp; design files</li><li>Request approvals</li><li>Log visitors</li><li>No access to invoices</li></ul></div>
    </div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="search"><?= icon('search', 17) ?><input class="input" type="search" data-filter="#userTable" placeholder="Search users…"></div>
    <button class="btn btn-accent push-right" data-modal-open="userModal"><?= icon('plus', 17) ?> Add user</button>
  </div>
  <div class="table-wrap">
    <table class="table" id="userTable">
      <thead><tr><th>User</th><th>Role</th><th>Status</th><th>Last sign-in</th><th></th></tr></thead>
      <tbody>
<?php foreach ($users as $u): ?>
        <tr>
          <td><div class="cell-user"><?= avatar($u['name']) ?><div><b><?= e($u['name']) ?></b><small class="sub"><?= e($u['email']) ?><?= $u['designation'] ? ' · ' . e($u['designation']) : '' ?></small></div></div></td>
          <td><?= badge(ucfirst($u['role'])) ?></td>
          <td><?= $u['is_active'] ? badge('Active') : badge('Inactive') ?></td>
          <td class="nowrap"><?= e(time_ago($u['last_login'])) ?></td>
          <td>
            <div class="row-actions">
              <button class="icon-btn" title="Edit" data-modal-edit="userModal" data-row="<?= e(json_encode($u)) ?>"><?= icon('edit', 17) ?></button>
<?php if ((int)$u['id'] !== $myId): ?>
              <form method="post" data-confirm="Delete <?= e($u['name']) ?>? Their attendance records are deleted too."><?= csrf_field() ?><input type="hidden" name="do" value="delete_user"><input type="hidden" name="id" value="<?= e($u['id']) ?>">
                <button class="icon-btn danger" title="Delete"><?= icon('trash', 17) ?></button></form>
<?php endif; ?>
            </div>
          </td>
        </tr>
<?php endforeach; ?>
        <tr class="no-results" hidden><td colspan="5">No users match your search.</td></tr>
      </tbody>
    </table>
  </div>
</div>

<dialog class="modal" id="userModal">
  <form method="post" class="modal-card" data-create-do="create_user" data-update-do="update_user">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="create_user">
    <input type="hidden" name="id" value="">
    <div class="modal-head"><h3 data-title-create="Add user" data-title-edit="Edit user">Add user</h3><button type="button" class="icon-btn" data-close><?= icon('x', 18) ?></button></div>
    <div class="modal-body form-grid">
      <div class="field"><label class="label">Full name</label><input class="input" type="text" name="name" maxlength="100" required></div>
      <div class="field"><label class="label">Designation</label><input class="input" type="text" name="designation" maxlength="100" placeholder="e.g. Site Engineer"></div>
      <div class="field span-2"><label class="label">Email</label><input class="input" type="email" name="email" maxlength="150" required></div>
      <div class="field"><label class="label">Role</label><select class="select" name="role"><option value="staff">Staff</option><option value="admin">Admin</option></select></div>
      <div class="field"><label class="label">Account</label><select class="select" name="is_active"><option value="1">Active</option><option value="0">Inactive</option></select></div>
      <div class="field span-2"><label class="label">Password <span class="optional" data-edit-hint>(minimum 8 characters; leave blank when editing to keep the current one)</span></label>
        <input class="input" type="password" name="password" autocomplete="new-password" placeholder="••••••••"></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button class="btn btn-primary">Save user</button></div>
  </form>
</dialog>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
