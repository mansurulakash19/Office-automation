<?php
// includes/layout_top.php — opens every page: <head>, sidebar and top bar.
//
// Set these variables BEFORE including this file:
//   $pageTitle    (text)  e.g. 'Clients'
//   $pageSubtitle (text)  optional one-line description
//   $activePage   (text)  which sidebar link is highlighted, e.g. 'clients'

$me = current_user();
$unread = (int) db_value('SELECT COUNT(*) FROM notifications WHERE user_id = ? AND is_read = 0', [user_id()]);
$recentNotes = db_all('SELECT * FROM notifications WHERE user_id = ? ORDER BY id DESC LIMIT 6', [user_id()]);
$flashes = get_flashes();
$pageSubtitle = isset($pageSubtitle) ? $pageSubtitle : '';
$noteIcons = ['info' => 'bell', 'reminder' => 'clock', 'success' => 'check', 'warning' => 'alert'];

// The sidebar menu. Items marked admin-only are hidden from staff.
$navGroups = [
    ['label' => 'Modules', 'items' => [
        ['key' => 'accounts',   'href' => 'accounts.php',   'label' => 'Accounts & HR',      'icon' => 'wallet',  'admin' => false],
        ['key' => 'clients',    'href' => 'clients.php',    'label' => 'Client Records',     'icon' => 'users',   'admin' => false],
        ['key' => 'files',      'href' => 'files.php',      'label' => 'Design Files',       'icon' => 'folder',  'admin' => false],
        ['key' => 'projects',   'href' => 'projects.php',   'label' => 'Project Tracking',   'icon' => 'kanban',  'admin' => false],
    ]],
    ['label' => 'Administration', 'items' => [
        ['key' => 'users',      'href' => 'users.php',      'label' => 'Users & Roles',      'icon' => 'user',    'admin' => true],
    ]],
];
foreach ($navGroups as $gi => $group) {
    $visible = [];
    foreach ($group['items'] as $item) {
        if (!$item['admin'] || is_admin()) { $visible[] = $item; }
    }
    $navGroups[$gi]['items'] = $visible;
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($pageTitle) ?> · <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=Figtree:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="app-body">

<div class="toast-stack" id="toastStack">
<?php foreach ($flashes as $f): ?>
  <div class="toast toast-<?= e($f['type']) ?>"><?= icon($f['type'] === 'error' ? 'alert' : 'check', 18) ?><span><?= e($f['message']) ?></span></div>
<?php endforeach; ?>
</div>

<div class="app">

  <!-- ================= Sidebar ================= -->
  <aside class="sidebar" id="sidebar">
    <a class="brand" href="<?= e(HOME_PAGE) ?>">
      <span class="brand-mark">A<i>2</i>Z</span>
      <span class="brand-text"><b>Central</b><small>Automation platform</small></span>
    </a>

    <nav class="nav">
<?php foreach ($navGroups as $group): ?>
      <div class="nav-group">
        <div class="nav-label"><?= e($group['label']) ?></div>
<?php foreach ($group['items'] as $item): ?>
        <a class="nav-link <?= $activePage === $item['key'] ? 'active' : '' ?>" href="<?= e($item['href']) ?>">
          <?= icon($item['icon'], 19) ?><span><?= e($item['label']) ?></span>
        </a>
<?php endforeach; ?>
      </div>
<?php endforeach; ?>
    </nav>

  </aside>

  <div class="main">

    <!-- ================= Top bar ================= -->
    <header class="topbar">
      <button class="icon-btn menu-btn" data-sidebar-toggle aria-label="Open menu"><?= icon('menu', 22) ?></button>
      <div class="topbar-title">
        <h1><?= e($pageTitle) ?></h1>
<?php if ($pageSubtitle !== ''): ?>
        <p><?= e($pageSubtitle) ?></p>
<?php endif; ?>
      </div>

      <div class="topbar-right">
        <div class="dropdown" id="notifMenu">
          <button class="icon-btn bell" data-dropdown="notifMenu" aria-label="Notifications">
            <?= icon('bell', 20) ?>
<?php if ($unread > 0): ?>
            <span class="bell-count"><?= e($unread) ?></span>
<?php endif; ?>
          </button>
          <div class="dropdown-panel">
            <div class="dd-head"><b>Notifications</b><a href="notifications.php">View all</a></div>
<?php foreach ($recentNotes as $n): ?>
            <a class="dd-item <?= $n['is_read'] ? '' : 'unread' ?>" href="notifications.php?open=<?= e($n['id']) ?>">
              <span class="dd-icon dd-<?= e($n['type']) ?>"><?= icon($noteIcons[$n['type']], 16) ?></span>
              <span class="dd-text"><?= e($n['message']) ?><small><?= e(time_ago($n['created_at'])) ?></small></span>
            </a>
<?php endforeach; ?>
<?php if (count($recentNotes) === 0): ?>
            <div class="dd-empty">You are all caught up.</div>
<?php endif; ?>
          </div>
        </div>

        <div class="user-chip">
          <?= avatar($me['name']) ?>
          <div class="user-meta"><b><?= e($me['name']) ?></b><small><?= e(ucfirst($me['role'])) ?></small></div>
          <form method="post" action="logout.php">
            <?= csrf_field() ?>
            <button class="icon-btn" title="Sign out" aria-label="Sign out"><?= icon('logout', 18) ?></button>
          </form>
        </div>
      </div>
    </header>

    <main class="content">
