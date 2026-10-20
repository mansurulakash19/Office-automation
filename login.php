<?php
// login.php — sign-in page.
require_once __DIR__ . '/includes/auth.php';

if (is_logged_in()) {
    redirect('dashboard.php');
}

$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    verify_csrf();
    $result = attempt_login(post('email'), isset($_POST['password']) ? (string)$_POST['password'] : '');
    if ($result === true) {
        redirect('dashboard.php');
    }
    $error = $result;        // attempt_login() returns an error message when the login fails
}

$showDemo = SHOW_DEMO_LOGINS;

// Positions for the six module icons around the centre of the illustration.
$modules = [
    ['icon' => 'wallet',  'label' => 'Accounts & HR'],
    ['icon' => 'users',   'label' => 'Clients'],
    ['icon' => 'folder',  'label' => 'Design Files'],
    ['icon' => 'eye',     'label' => 'Monitoring'],
    ['icon' => 'kanban',  'label' => 'Projects'],
    ['icon' => 'shield',  'label' => 'Approvals'],
];
$nodes = [];
foreach ($modules as $i => $m) {
    $angle = deg2rad(-90 + $i * 60);
    $nodes[] = [
        'x' => round(300 + 185 * cos($angle)),
        'y' => round(300 + 185 * sin($angle)),
        'icon' => $m['icon'],
        'label' => $m['label'],
    ];
}
?><!DOCTYPE html>
<html lang="en">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Sign in · <?= e(APP_NAME) ?></title>
<link rel="preconnect" href="https://fonts.googleapis.com">
<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
<link href="https://fonts.googleapis.com/css2?family=Bricolage+Grotesque:opsz,wght@12..96,500;12..96,600;12..96,700&family=Figtree:wght@400;500;600;700&family=IBM+Plex+Mono:wght@400;500&display=swap" rel="stylesheet">
<link rel="stylesheet" href="assets/css/app.css">
</head>
<body class="auth-body">

<div class="auth">

  <!-- ===== Left: brand + illustration ===== -->
  <section class="auth-art">
    <div class="auth-brand">
      <span class="brand-mark">A<i>2</i>Z</span>
      <span class="brand-text"><b>Central</b><small><?= e(COMPANY_NAME) ?></small></span>
    </div>

    <svg class="auth-svg" viewBox="0 0 600 600" role="img" aria-label="Six modules connected to one platform">
      <defs>
        <pattern id="grid" width="30" height="30" patternUnits="userSpaceOnUse">
          <path d="M30 0H0V30" fill="none" stroke="rgba(255,255,255,.06)" stroke-width="1"/>
        </pattern>
        <radialGradient id="glow" cx="50%" cy="50%" r="50%">
          <stop offset="0" stop-color="#F5A623" stop-opacity=".35"/>
          <stop offset="1" stop-color="#F5A623" stop-opacity="0"/>
        </radialGradient>
      </defs>
      <rect width="600" height="600" fill="url(#grid)"/>
      <circle cx="300" cy="300" r="190" fill="none" stroke="rgba(255,255,255,.08)" stroke-dasharray="3 7"/>
      <circle cx="300" cy="300" r="120" fill="url(#glow)"/>
<?php foreach ($nodes as $n): ?>
      <line class="flow" x1="300" y1="300" x2="<?= e($n['x']) ?>" y2="<?= e($n['y']) ?>"/>
<?php endforeach; ?>
      <circle class="hub-ring" cx="300" cy="300" r="52"/>
      <circle cx="300" cy="300" r="46" fill="#F5A623"/>
      <text x="300" y="308" text-anchor="middle" class="hub-text">A2Z</text>
<?php foreach ($nodes as $n): ?>
      <g transform="translate(<?= e($n['x']) ?> <?= e($n['y']) ?>)">
        <rect x="-30" y="-30" width="60" height="60" rx="16" class="node-box"/>
        <g transform="translate(-12 -12)" fill="none" stroke="#F5A623" stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round"><?= icon_paths($n['icon']) ?></g>
        <text y="52" text-anchor="middle" class="node-label"><?= e($n['label']) ?></text>
      </g>
<?php endforeach; ?>
    </svg>

    <div class="auth-copy">
      <h2>Six manual processes.<br>One automated platform.</h2>
      <p>Invoices flag themselves overdue, reminders send themselves, and an assistant answers the routine questions.</p>
    </div>
  </section>

  <!-- ===== Right: sign-in form ===== -->
  <section class="auth-panel">
    <div class="auth-card">
      <h1>Welcome back</h1>
      <p class="muted">Sign in to manage <?= e(COMPANY_NAME) ?>.</p>

      <form method="post" autocomplete="on">
        <?= csrf_field() ?>
        <div class="field">
          <label class="label" for="email">Email</label>
          <input class="input" type="email" id="email" name="email" value="<?= e(post('email')) ?>" placeholder="you@company.com" required autofocus>
        </div>
        <div class="field">
          <label class="label" for="password">Password</label>
          <div class="pw">
            <input class="input" type="password" id="password" name="password" placeholder="Your password" required>
            <button type="button" class="pw-toggle" data-toggle-password="password">Show</button>
          </div>
        </div>
<?php if ($error !== ''): ?>
        <div class="form-error"><?= icon('alert', 16) ?><span><?= e($error) ?></span></div>
<?php endif; ?>
        <button class="btn btn-accent btn-block btn-lg">Sign in <?= icon('arrow', 18) ?></button>
      </form>

<?php if ($showDemo): ?>
      <div class="demo">
        <span>Demo accounts</span>
        <button type="button" class="chip" data-fill="admin@a2z.com|Admin@123">Admin</button>
        <button type="button" class="chip" data-fill="nusrat@a2z.com|Staff@123">Staff</button>
      </div>
<?php endif; ?>
    </div>
    <p class="auth-foot">© <?= e(date('Y')) ?> <?= e(COMPANY_NAME) ?> · Solo capstone project</p>
  </section>

</div>

<script src="assets/js/app.js"></script>
</body>
</html>
