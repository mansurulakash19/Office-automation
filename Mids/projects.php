<?php
// projects.php — Project Tracking module.
//   Tab "Projects"   -> every project with a progress bar (admins create / edit them)
//   Tab "Task board" -> kanban board; drag a card to change its status
require_once __DIR__ . '/includes/auth.php';
require_login();

$uid = user_id();
$tab = (isset($_GET['tab']) && $_GET['tab'] === 'board') ? 'board' : 'projects';
$taskStatuses = ['To Do', 'In Progress', 'Done'];

// =====================================================================
//  POST handlers
// =====================================================================
if ($_SERVER['REQUEST_METHOD'] === 'POST') {

    // Drag-and-drop on the board sends a small background request and expects JSON back.
    if (post('do') === 'move_task') {
        if (!csrf_valid()) { json_response(['ok' => false, 'error' => 'Session expired'], 419); }
        $taskId = post_id('id');
        $newStatus = post('status');
        if (!$taskId || !in_array($newStatus, $taskStatuses, true)) { json_response(['ok' => false], 400); }
        db_exec('UPDATE tasks SET status = ? WHERE id = ?', [$newStatus, $taskId]);
        $t = db_one('SELECT title FROM tasks WHERE id = ?', [$taskId]);
        log_activity('projects', 'Moved task "' . ($t ? $t['title'] : '#' . $taskId) . '" to ' . $newStatus);
        json_response(['ok' => true]);
    }

    verify_csrf();
    $do = post('do');
    $id = post_id('id');

    // ---------- Projects (admin only) ----------
    if (in_array($do, ['create_project', 'update_project', 'delete_project'], true)) {
        if (!is_admin()) { forbidden(); }

        if ($do === 'delete_project' && $id) {
            // Delete the real files from disk first (the database rows are removed automatically).
            foreach (db_all('SELECT stored_name FROM design_files WHERE project_id = ?', [$id]) as $f) {
                $path = UPLOAD_DIR . $f['stored_name'];
                if (is_file($path)) { @unlink($path); }
            }
            $old = db_one('SELECT title FROM projects WHERE id = ?', [$id]);
            db_exec('DELETE FROM projects WHERE id = ?', [$id]);
            log_activity('projects', 'Deleted project ' . ($old ? $old['title'] : '#' . $id));
            flash('success', 'Project deleted.');
            redirect('projects.php');
        }

        $clientId = post_id('client_id');
        $title = post('title');
        $status = in_array(post('status'), ['Planning', 'In Progress', 'On Hold', 'Completed'], true) ? post('status') : 'Planning';
        if (!$clientId || $title === '') {
            flash('error', 'Please choose a client and enter a project title.');
            redirect('projects.php');
        }
        $values = [$clientId, post_id('manager_id'), $title, post('description'), post_date('start_date'), post_date('deadline'), $status];

        if ($do === 'create_project') {
            db_insert('INSERT INTO projects (client_id, manager_id, title, description, start_date, deadline, status) VALUES (?,?,?,?,?,?,?)', $values);
            log_activity('projects', 'Created project ' . $title);
            flash('success', 'Project created.');
        } elseif ($id) {
            $values[] = $id;
            db_exec('UPDATE projects SET client_id = ?, manager_id = ?, title = ?, description = ?, start_date = ?, deadline = ?, status = ? WHERE id = ?', $values);
            log_activity('projects', 'Updated project ' . $title);
            flash('success', 'Project updated.');
        }
        redirect('projects.php');
    }

    // ---------- Tasks ----------
    if ($do === 'delete_task') {
        if (!is_admin()) { forbidden(); }
        db_exec('DELETE FROM tasks WHERE id = ?', [$id]);
        log_activity('projects', 'Deleted a task');
        flash('success', 'Task deleted.');
        redirect('projects.php?tab=board');
    }

    if ($do === 'create_task' || $do === 'update_task') {
        $projectId = post_id('project_id');
        $title = post('title');
        $assignee = post_id('assigned_to');
        $priority = in_array(post('priority'), ['Low', 'Medium', 'High'], true) ? post('priority') : 'Medium';
        $status = in_array(post('status'), $taskStatuses, true) ? post('status') : 'To Do';
        if (!$projectId || $title === '') {
            flash('error', 'Please choose a project and enter a task title.');
            redirect('projects.php?tab=board');
        }
        $values = [$projectId, $assignee, $title, $priority, post_date('due_date'), $status];

        if ($do === 'create_task') {
            db_insert('INSERT INTO tasks (project_id, assigned_to, title, priority, due_date, status) VALUES (?,?,?,?,?,?)', $values);
            log_activity('projects', 'Added task "' . $title . '"');
            if ($assignee && $assignee !== $uid) {
                notify($assignee, 'New task assigned to you: ' . $title, 'projects.php?tab=board', 'info');
            }
            flash('success', 'Task added.');
        } elseif ($id) {
            $values[] = $id;
            db_exec('UPDATE tasks SET project_id = ?, assigned_to = ?, title = ?, priority = ?, due_date = ?, status = ? WHERE id = ?', $values);
            log_activity('projects', 'Updated task "' . $title . '"');
            flash('success', 'Task updated.');
        }
        redirect('projects.php?tab=board');
    }
}

// =====================================================================
//  Load data
// =====================================================================
$clients = db_all('SELECT id, name FROM clients ORDER BY name');
$users = db_all('SELECT id, name FROM users WHERE is_active = 1 ORDER BY name');
$projectList = db_all('SELECT p.id, p.title, c.name AS client_name FROM projects p JOIN clients c ON c.id = p.client_id ORDER BY p.title');
$projects = []; $columns = []; $filterProject = 0;

if ($tab === 'projects') {
    $projects = db_all("SELECT p.*, c.name AS client_name, u.name AS manager_name,
            (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id) AS tasks_total,
            (SELECT COUNT(*) FROM tasks t WHERE t.project_id = p.id AND t.status = 'Done') AS tasks_done
        FROM projects p
        JOIN clients c ON c.id = p.client_id
        LEFT JOIN users u ON u.id = p.manager_id
        ORDER BY FIELD(p.status, 'In Progress', 'Planning', 'On Hold', 'Completed'), p.deadline ASC");
    foreach ($projects as $i => $p) {
        $total = (int)$p['tasks_total'];
        $projects[$i]['percent'] = $total > 0 ? (int)round(((int)$p['tasks_done'] / $total) * 100) : 0;
    }
} else {
    $filterProject = isset($_GET['project']) ? (int)$_GET['project'] : 0;
    $sql = 'SELECT t.*, p.title AS project_title, u.name AS assignee_name
            FROM tasks t
            JOIN projects p ON p.id = t.project_id
            LEFT JOIN users u ON u.id = t.assigned_to';
    $params = [];
    if ($filterProject > 0) { $sql .= ' WHERE t.project_id = ?'; $params[] = $filterProject; }
    $tasks = db_all($sql . ' ORDER BY FIELD(t.priority, \'High\', \'Medium\', \'Low\'), t.due_date ASC', $params);

    $columns = ['To Do' => [], 'In Progress' => [], 'Done' => []];
    foreach ($tasks as $t) {
        $columns[$t['status']][] = $t;
    }
}

$pageTitle = 'Project Tracking';
$pageSubtitle = 'Projects, progress and the shared task board';
$activePage = 'projects';
?>
<?php include __DIR__ . '/includes/layout_top.php'; ?>

<div class="tabs">
  <a class="tab <?= $tab === 'projects' ? 'active' : '' ?>" href="projects.php"><?= icon('briefcase', 17) ?> Projects</a>
  <a class="tab <?= $tab === 'board' ? 'active' : '' ?>" href="projects.php?tab=board"><?= icon('kanban', 17) ?> Task board</a>
</div>

<?php if ($tab === 'projects'): ?>

<div class="section-bar">
  <div class="search"><?= icon('search', 17) ?><input class="input" type="search" data-filter-cards=".project-card" placeholder="Search projects…"></div>
<?php if (is_admin()): ?>
  <button class="btn btn-accent" data-modal-open="projectModal"><?= icon('plus', 17) ?> New project</button>
<?php endif; ?>
</div>

<div class="project-grid">
<?php foreach ($projects as $p): ?>
  <article class="project-card">
    <div class="pc-top">
      <?= badge($p['status']) ?>
<?php if (is_admin()): ?>
      <div class="row-actions">
        <button class="icon-btn" title="Edit" data-modal-edit="projectModal" data-row="<?= e(json_encode($p)) ?>"><?= icon('edit', 17) ?></button>
        <form method="post" data-confirm="Delete project <?= e($p['title']) ?> and all its tasks and files?"><?= csrf_field() ?><input type="hidden" name="do" value="delete_project"><input type="hidden" name="id" value="<?= e($p['id']) ?>">
          <button class="icon-btn danger" title="Delete"><?= icon('trash', 17) ?></button></form>
      </div>
<?php endif; ?>
    </div>
    <h3><?= e($p['title']) ?></h3>
    <p class="pc-client"><?= e($p['client_name']) ?></p>
    <p class="pc-desc"><?= e($p['description']) ?></p>
    <div class="progress-row"><span>Progress</span><b><?= e($p['percent']) ?>%</b></div>
    <div class="progress"><span style="width: <?= e($p['percent']) ?>%"></span></div>
    <div class="pc-foot">
      <span class="muted small"><?= e($p['tasks_done']) ?> of <?= e($p['tasks_total']) ?> tasks done</span>
      <?= $p['status'] === 'Completed' ? '<span class="muted small">' . e(fmt_date($p['deadline'])) . '</span>' : due_chip($p['deadline']) ?>
    </div>
    <div class="pc-manager"><?= $p['manager_name'] ? avatar($p['manager_name'], 'sm') : '' ?><span><?= e($p['manager_name'] ? $p['manager_name'] : 'No manager assigned') ?></span></div>
  </article>
<?php endforeach; ?>
</div>
<?php if (count($projects) === 0): ?>
<div class="card empty"><div class="empty-icon"><?= icon('briefcase', 28) ?></div><h3>No projects yet</h3><p>Create the first project to start tracking work.</p></div>
<?php endif; ?>

<?php if (is_admin()): ?>
<dialog class="modal" id="projectModal">
  <form method="post" class="modal-card" data-create-do="create_project" data-update-do="update_project">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="create_project">
    <input type="hidden" name="id" value="">
    <div class="modal-head"><h3 data-title-create="New project" data-title-edit="Edit project">New project</h3><button type="button" class="icon-btn" data-close><?= icon('x', 18) ?></button></div>
    <div class="modal-body form-grid">
      <div class="field span-2"><label class="label">Project title</label><input class="input" type="text" name="title" maxlength="150" required></div>
      <div class="field"><label class="label">Client</label>
        <select class="select" name="client_id" required><option value="">Choose…</option>
<?php foreach ($clients as $c): ?>
          <option value="<?= e($c['id']) ?>"><?= e($c['name']) ?></option>
<?php endforeach; ?>
        </select></div>
      <div class="field"><label class="label">Project manager</label>
        <select class="select" name="manager_id"><option value="">Not assigned</option>
<?php foreach ($users as $u): ?>
          <option value="<?= e($u['id']) ?>"><?= e($u['name']) ?></option>
<?php endforeach; ?>
        </select></div>
      <div class="field"><label class="label">Start date</label><input class="input" type="date" name="start_date"></div>
      <div class="field"><label class="label">Deadline</label><input class="input" type="date" name="deadline"></div>
      <div class="field span-2"><label class="label">Status</label>
        <select class="select" name="status"><option>Planning</option><option>In Progress</option><option>On Hold</option><option>Completed</option></select></div>
      <div class="field span-2"><label class="label">Description <span class="optional">(optional)</span></label><textarea class="textarea" name="description" rows="3" maxlength="500"></textarea></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button class="btn btn-primary">Save project</button></div>
  </form>
</dialog>
<?php endif; ?>

<?php else: ?>

<div class="section-bar">
  <form method="get" class="inline-form">
    <input type="hidden" name="tab" value="board">
    <select class="select" name="project" data-autosubmit>
      <option value="0">All projects</option>
<?php foreach ($projectList as $p): ?>
      <option value="<?= e($p['id']) ?>" <?= $filterProject === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['title']) ?></option>
<?php endforeach; ?>
    </select>
  </form>
  <button class="btn btn-accent" data-modal-open="taskModal"><?= icon('plus', 17) ?> New task</button>
</div>
<p class="board-tip muted small">Tip: drag a card to another column to change its status.</p>

<div class="board">
<?php foreach ($columns as $status => $cards): ?>
  <div class="column" data-status="<?= e($status) ?>">
    <div class="column-head"><span class="col-dot col-<?= e(str_replace(' ', '', $status)) ?>"></span><b><?= e($status) ?></b><span class="count"><?= e(count($cards)) ?></span></div>
    <div class="column-body">
<?php foreach ($cards as $t): ?>
      <div class="k-card" draggable="true" data-id="<?= e($t['id']) ?>">
        <div class="k-top"><?= badge($t['priority']) ?>
          <div class="row-actions">
            <button class="icon-btn" title="Edit" data-modal-edit="taskModal" data-row="<?= e(json_encode($t)) ?>"><?= icon('edit', 15) ?></button>
<?php if (is_admin()): ?>
            <form method="post" data-confirm="Delete this task?"><?= csrf_field() ?><input type="hidden" name="do" value="delete_task"><input type="hidden" name="id" value="<?= e($t['id']) ?>">
              <button class="icon-btn danger" title="Delete"><?= icon('trash', 15) ?></button></form>
<?php endif; ?>
          </div>
        </div>
        <div class="k-title"><?= e($t['title']) ?></div>
        <div class="k-project"><?= e($t['project_title']) ?></div>
        <div class="k-foot">
          <?= due_chip($t['due_date'], $t['status'] === 'Done') ?>
          <?= $t['assignee_name'] ? avatar($t['assignee_name'], 'sm') : '<span class="muted small">Unassigned</span>' ?>
        </div>
      </div>
<?php endforeach; ?>
    </div>
  </div>
<?php endforeach; ?>
</div>

<dialog class="modal" id="taskModal">
  <form method="post" class="modal-card" data-create-do="create_task" data-update-do="update_task">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="create_task">
    <input type="hidden" name="id" value="">
    <div class="modal-head"><h3 data-title-create="New task" data-title-edit="Edit task">New task</h3><button type="button" class="icon-btn" data-close><?= icon('x', 18) ?></button></div>
    <div class="modal-body form-grid">
      <div class="field span-2"><label class="label">Task title</label><input class="input" type="text" name="title" maxlength="150" required></div>
      <div class="field span-2"><label class="label">Project</label>
        <select class="select" name="project_id" required><option value="">Choose…</option>
<?php foreach ($projectList as $p): ?>
          <option value="<?= e($p['id']) ?>" <?= $filterProject === (int)$p['id'] ? 'selected' : '' ?>><?= e($p['title']) ?> — <?= e($p['client_name']) ?></option>
<?php endforeach; ?>
        </select></div>
      <div class="field"><label class="label">Assigned to</label>
        <select class="select" name="assigned_to"><option value="">Unassigned</option>
<?php foreach ($users as $u): ?>
          <option value="<?= e($u['id']) ?>"><?= e($u['name']) ?></option>
<?php endforeach; ?>
        </select></div>
      <div class="field"><label class="label">Due date</label><input class="input" type="date" name="due_date"></div>
      <div class="field"><label class="label">Priority</label>
        <select class="select" name="priority"><option>Low</option><option selected>Medium</option><option>High</option></select></div>
      <div class="field"><label class="label">Status</label>
        <select class="select" name="status"><option>To Do</option><option>In Progress</option><option>Done</option></select></div>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button class="btn btn-primary">Save task</button></div>
  </form>
</dialog>

<?php endif; ?>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
