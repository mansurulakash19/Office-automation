<?php
// notifications.php — everything the system has told you (approval requests, decisions, new tasks).
require_once __DIR__ . '/includes/auth.php';
require_login();

$uid = user_id();

// Clicking a notification in the bell menu lands here: mark it as read, then go to its page.
if (isset($_GET['open'])) {
    $n = db_one('SELECT * FROM notifications WHERE id = ? AND user_id = ?', [(int)$_GET['open'], $uid]);
    if ($n) {
        db_exec('UPDATE notifications SET is_read = 1 WHERE id = ?', [(int)$n['id']]);
        // Only follow links that point to one of our own pages.
        if ($n['link'] && preg_match('/^[a-z0-9_\-]+\.php(\?[A-Za-z0-9_=&\-]*)?$/i', $n['link'])) {
            redirect($n['link']);
        }
    }
    redirect('notifications.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $do = post('do');
    if ($do === 'mark_all') {
        db_exec('UPDATE notifications SET is_read = 1 WHERE user_id = ?', [$uid]);
        flash('success', 'All notifications marked as read.');
    } elseif ($do === 'clear_read') {
        db_exec('DELETE FROM notifications WHERE user_id = ? AND is_read = 1', [$uid]);
        flash('success', 'Read notifications cleared.');
    }
    redirect('notifications.php');
}

$notes = db_all('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 100', [$uid]);
$noteIcons = ['info' => 'bell', 'reminder' => 'clock', 'success' => 'check', 'warning' => 'alert'];
$unreadCount = 0;
foreach ($notes as $n) { if (!$n['is_read']) { $unreadCount++; } }

$pageTitle = 'Notifications';
$pageSubtitle = $unreadCount > 0 ? $unreadCount . ' unread' : 'You are all caught up';
$activePage = '';
?>
<?php include __DIR__ . '/includes/layout_top.php'; ?>

<div class="card">
  <div class="toolbar">
    <span class="muted">Approval requests, decisions and new tasks appear here.</span>
    <form method="post" class="push-right inline-form"><?= csrf_field() ?><input type="hidden" name="do" value="mark_all"><button class="btn btn-ghost btn-sm"><?= icon('check', 15) ?> Mark all as read</button></form>
    <form method="post" class="inline-form"><?= csrf_field() ?><input type="hidden" name="do" value="clear_read"><button class="btn btn-ghost btn-sm"><?= icon('trash', 15) ?> Clear read</button></form>
  </div>
  <div class="list">
<?php foreach ($notes as $n): ?>
    <a class="list-item <?= $n['is_read'] ? '' : 'unread' ?>" href="notifications.php?open=<?= e($n['id']) ?>">
      <span class="dd-icon dd-<?= e($n['type']) ?>"><?= icon($noteIcons[$n['type']], 17) ?></span>
      <span class="li-main"><b><?= e($n['message']) ?></b><small><?= e(fmt_datetime($n['created_at'])) ?> · <?= e(time_ago($n['created_at'])) ?></small></span>
<?php if (!$n['is_read']): ?>
      <span class="badge badge-info">New</span>
<?php endif; ?>
    </a>
<?php endforeach; ?>
<?php if (count($notes) === 0): ?>
    <div class="empty-inline">No notifications yet.</div>
<?php endif; ?>
  </div>
</div>

<?php include __DIR__ . '/includes/layout_bottom.php'; ?>
