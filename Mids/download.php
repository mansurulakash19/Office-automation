<?php
// download.php — sends a stored design file to a logged-in user.
// The uploads folder is blocked from direct web access, so files can ONLY be reached through this script.
require_once __DIR__ . '/includes/auth.php';
require_login();

$id = isset($_GET['id']) ? (int)$_GET['id'] : 0;
$file = $id > 0 ? db_one('SELECT * FROM design_files WHERE id = ?', [$id]) : null;
$path = $file ? UPLOAD_DIR . $file['stored_name'] : '';

if (!$file || !is_file($path)) {
    http_response_code(404);
    exit('File not found.');
}

// Keep the original name but add the version:  Plan.dwg  ->  Plan_v2.dwg
$info = pathinfo($file['original_name']);
$base = preg_replace('/[^A-Za-z0-9._-]/', '_', $info['filename']);
$ext = isset($info['extension']) ? '.' . preg_replace('/[^A-Za-z0-9]/', '', $info['extension']) : '';
$downloadName = $base . '_v' . $file['version'] . $ext;

log_activity('files', 'Downloaded ' . $file['original_name'] . ' (v' . $file['version'] . ')');

header('Content-Type: application/octet-stream');
header('Content-Disposition: attachment; filename="' . $downloadName . '"');
header('Content-Length: ' . filesize($path));
header('X-Content-Type-Options: nosniff');
readfile($path);
exit;
