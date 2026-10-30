<?php
// files.php — Design Files module (cloud-style storage with VERSION CONTROL).
// Uploading a file with the same name into the same project saves it as the next version (v1, v2, v3 ...).
require_once __DIR__ . '/includes/auth.php';
require_login();

// Only these file types may be uploaded.
$allowedTypes = ['pdf', 'dwg', 'dxf', 'png', 'jpg', 'jpeg', 'doc', 'docx', 'xls', 'xlsx', 'zip', 'txt', 'csv'];

function upload_error_text(int $code): string {
    if ($code === UPLOAD_ERR_INI_SIZE || $code === UPLOAD_ERR_FORM_SIZE) {
        return 'That file is larger than the server allows. Check upload_max_filesize in php.ini.';
    }
    if ($code === UPLOAD_ERR_NO_FILE) { return 'Please choose a file to upload.'; }
    return 'The upload failed (error code ' . $code . '). Please try again.';
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $do = post('do');

    if ($do === 'delete_file') {
        if (!is_admin()) { forbidden(); }
        $id = post_id('id');
        $f = $id ? db_one('SELECT * FROM design_files WHERE id = ?', [$id]) : null;
        if ($f) {
            $path = UPLOAD_DIR . $f['stored_name'];
            if (is_file($path)) { @unlink($path); }
            db_exec('DELETE FROM design_files WHERE id = ?', [$id]);
            log_activity('files', 'Deleted ' . $f['original_name'] . ' (v' . $f['version'] . ')');
            flash('success', 'File deleted.');
        }
        redirect('files.php');
    }

    if ($do === 'upload_file') {
        $projectId = post_id('project_id');
        if (!$projectId) {
            flash('error', 'Please choose a project for this file.');
            redirect('files.php');
        }
        if (!isset($_FILES['file']) || $_FILES['file']['error'] !== UPLOAD_ERR_OK) {
            $code = isset($_FILES['file']) ? (int)$_FILES['file']['error'] : UPLOAD_ERR_NO_FILE;
            flash('error', upload_error_text($code));
            redirect('files.php');
        }

        $file = $_FILES['file'];
        $original = basename($file['name']);
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));

        if (!in_array($ext, $allowedTypes, true)) {
            flash('error', 'This file type is not allowed. Allowed: ' . implode(', ', $allowedTypes) . '.');
            redirect('files.php');
        }
        if ($file['size'] > MAX_UPLOAD_MB * 1024 * 1024) {
            flash('error', 'The file is larger than ' . MAX_UPLOAD_MB . ' MB.');
            redirect('files.php');
        }
        if (!is_dir(UPLOAD_DIR)) { mkdir(UPLOAD_DIR, 0775, true); }

        // The file is stored under a random name, so nobody can guess its address.
        $stored = bin2hex(random_bytes(16)) . '.' . $ext;
        if (!move_uploaded_file($file['tmp_name'], UPLOAD_DIR . $stored)) {
            flash('error', 'Could not save the file. Check that the uploads folder is writable.');
            redirect('files.php');
        }

        // VERSION CONTROL: next version = highest existing version of the same file name in this project + 1
        $version = (int) db_value('SELECT COALESCE(MAX(version), 0) + 1 FROM design_files WHERE project_id = ? AND original_name = ?',
            [$projectId, $original]);

        db_insert('INSERT INTO design_files (project_id, original_name, stored_name, version, file_size, notes, uploaded_by, uploaded_at)
                   VALUES (?,?,?,?,?,?,?,?)',
            [$projectId, $original, $stored, $version, (int)$file['size'], post('notes'), user_id(), date('Y-m-d H:i:s')]);
        log_activity('files', 'Uploaded ' . $original . ' as version ' . $version);
        flash('success', $original . ' saved as version ' . $version . '.');
        redirect('files.php');
    }
}

$files = db_all("SELECT f.*, p.title AS project_title, u.name AS uploader,
        (f.version = (SELECT MAX(f2.version) FROM design_files f2
                      WHERE f2.project_id = f.project_id AND f2.original_name = f.original_name)) AS is_latest
    FROM design_files f
    JOIN projects p ON p.id = f.project_id
    LEFT JOIN users u ON u.id = f.uploaded_by
    ORDER BY f.uploaded_at DESC");
$projects = db_all('SELECT p.id, p.title, c.name AS client_name FROM projects p JOIN clients c ON c.id = p.client_id ORDER BY p.title');

$totalSize = 0;
foreach ($files as $f) { $totalSize += (int)$f['file_size']; }
$latestCount = 0;
foreach ($files as $f) { if ($f['is_latest']) { $latestCount++; } }

$pageTitle = 'Design Files';
$pageSubtitle = 'Shared storage with automatic version history';
$activePage = 'files';
?>
<?php include __DIR__ . '/includes/layout_top.php'; ?>

<div class="stat-strip">
  <div class="stat"><span class="stat-label">Files stored</span><span class="stat-value"><?= e($latestCount) ?></span></div>
  <div class="stat"><span class="stat-label">Total versions</span><span class="stat-value"><?= e(count($files)) ?></span></div>
  <div class="stat"><span class="stat-label">Storage used</span><span class="stat-value"><?= e(fmt_size($totalSize)) ?></span></div>
</div>

<div class="card">
  <div class="toolbar">
    <div class="search"><?= icon('search', 17) ?><input class="input" type="search" data-filter="#fileTable" placeholder="Search files, projects…"></div>
    <select class="select" data-filter-status="#fileTable">
      <option value="">All projects</option>
<?php foreach ($projects as $p): ?>
      <option><?= e($p['title']) ?></option>
<?php endforeach; ?>
    </select>
    <button class="btn btn-accent push-right" data-modal-open="uploadModal"><?= icon('upload', 17) ?> Upload file</button>
  </div>
  <div class="table-wrap">
    <table class="table" id="fileTable">
      <thead><tr><th>File</th><th>Project</th><th>Size</th><th>Uploaded by</th><th>Date</th><th></th></tr></thead>
      <tbody>
<?php foreach ($files as $f): ?>
        <tr data-status="<?= e($f['project_title']) ?>">
          <td>
            <div class="file-cell">
              <span class="file-icon"><?= icon('file', 20) ?></span>
              <div>
                <b><?= e($f['original_name']) ?></b>
                <small class="sub"><span class="badge badge-info">v<?= e($f['version']) ?></span><?= $f['is_latest'] ? ' <span class="badge badge-success">Latest</span>' : '' ?><?= $f['notes'] ? ' · ' . e($f['notes']) : '' ?></small>
              </div>
            </div>
          </td>
          <td><?= e($f['project_title']) ?></td>
          <td class="mono"><?= e(fmt_size((int)$f['file_size'])) ?></td>
          <td><?= e($f['uploader'] ? $f['uploader'] : '—') ?></td>
          <td class="nowrap"><?= e(fmt_date($f['uploaded_at'])) ?></td>
          <td>
            <div class="row-actions">
              <a class="icon-btn" title="Download" href="download.php?id=<?= e($f['id']) ?>"><?= icon('download', 17) ?></a>
<?php if (is_admin()): ?>
              <form method="post" data-confirm="Delete <?= e($f['original_name']) ?> (version <?= e($f['version']) ?>)?"><?= csrf_field() ?><input type="hidden" name="do" value="delete_file"><input type="hidden" name="id" value="<?= e($f['id']) ?>">
                <button class="icon-btn danger" title="Delete"><?= icon('trash', 17) ?></button></form>
<?php endif; ?>
            </div>
          </td>
        </tr>
<?php endforeach; ?>
        <tr class="no-results" hidden><td colspan="6">No files match your search.</td></tr>
      </tbody>
    </table>
  </div>
</div>

<dialog class="modal" id="uploadModal">
  <form method="post" enctype="multipart/form-data" class="modal-card">
    <?= csrf_field() ?>
    <input type="hidden" name="do" value="upload_file">
    <div class="modal-head"><h3>Upload a design file</h3><button type="button" class="icon-btn" data-close><?= icon('x', 18) ?></button></div>
    <div class="modal-body form-grid">
      <div class="field span-2"><label class="label">Project</label>
        <select class="select" name="project_id" required><option value="">Choose a project…</option>
<?php foreach ($projects as $p): ?>
          <option value="<?= e($p['id']) ?>"><?= e($p['title']) ?> — <?= e($p['client_name']) ?></option>
<?php endforeach; ?>
        </select></div>
      <div class="field span-2">
        <label class="dropzone" id="dropzone">
          <input class="sr-input" type="file" name="file" id="fileInput" required>
          <span class="dz-icon"><?= icon('upload', 26) ?></span>
          <b class="dz-title">Click to choose a file or drag it here</b>
          <span class="dz-name" id="dzName">PDF, DWG, DXF, images, Office files, ZIP · up to <?= e(MAX_UPLOAD_MB) ?> MB</span>
        </label>
      </div>
      <div class="field span-2"><label class="label">Version note <span class="optional">(optional)</span></label><input class="input" type="text" name="notes" maxlength="255" placeholder="e.g. Updated after client review"></div>
      <p class="span-2 muted small">If a file with the same name already exists in this project, it is saved as the next version automatically.</p>
    </div>
    <div class="modal-foot"><button type="button" class="btn btn-ghost" data-close>Cancel</button><button class="btn btn-primary">Upload</button></div>
  </form>
</dialog>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
