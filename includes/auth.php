<?php
// includes/auth.php — login sessions, roles (admin / staff) and CSRF protection.
// Every page (except login and setup) starts with:  require_once 'includes/auth.php'; require_login();

require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

if (session_status() === PHP_SESSION_NONE) {
    session_name('a2z_central');
    session_set_cookie_params([
        'lifetime' => 0,          // cookie lasts until the browser closes
        'path'     => '/',
        'httponly' => true,       // JavaScript cannot read the session cookie
        'samesite' => 'Lax',
    ]);
    session_start();
}

// ---------- Who is logged in? ----------

function is_logged_in(): bool {
    return !empty($_SESSION['user']);
}

function current_user(): ?array {
    return isset($_SESSION['user']) ? $_SESSION['user'] : null;
}

function user_id(): int {
    return isset($_SESSION['user']['id']) ? (int)$_SESSION['user']['id'] : 0;
}

function is_admin(): bool {
    return isset($_SESSION['user']['role']) && $_SESSION['user']['role'] === 'admin';
}

// ---------- Guards ----------

function require_login(): void {
    if (!is_logged_in()) {
        redirect('login.php');
    }
}

// Shows a friendly "access restricted" page (HTTP 403).
function forbidden(): void {
    http_response_code(403);
    $pageTitle = 'Access restricted';
    $pageSubtitle = 'You do not have permission to open this page.';
    $activePage = '';
    include __DIR__ . '/layout_top.php';
    echo '<div class="card empty"><div class="empty-icon">' . icon('shield', 28) . '</div>'
       . '<h3>This area is for administrators only</h3>'
       . '<p>Your role is <b>' . e(current_user()['role'] ?? 'guest') . '</b>. Ask an admin if you need access.</p>'
       . '<a class="btn btn-primary" href="dashboard.php">Back to dashboard</a></div>';
    include __DIR__ . '/layout_bottom.php';
    exit;
}

function require_admin(): void {
    require_login();
    if (!is_admin()) {
        forbidden();
    }
}

// ---------- Login / logout ----------

// Returns true on success, or an error message (string) on failure.
function attempt_login(string $email, string $password) {
    $now = time();
    if (isset($_SESSION['lock_until']) && $_SESSION['lock_until'] > $now) {
        return 'Too many failed attempts. Please wait ' . ($_SESSION['lock_until'] - $now) . ' seconds.';
    }

    $user = db_one('SELECT * FROM users WHERE email = ? AND is_active = 1', [strtolower($email)]);

    if ($user && password_verify($password, $user['password_hash'])) {
        session_regenerate_id(true);       // new session id after login (prevents session fixation)
        $_SESSION['user'] = [
            'id'    => (int)$user['id'],
            'name'  => $user['name'],
            'email' => $user['email'],
            'role'  => $user['role'],
        ];
        $_SESSION['fails'] = 0;
        db_exec('UPDATE users SET last_login = ? WHERE id = ?', [date('Y-m-d H:i:s'), (int)$user['id']]);
        log_activity('auth', $user['name'] . ' signed in', (int)$user['id']);
        return true;
    }

    // Wrong details: count the failure and lock the form for 60 seconds after 5 tries.
    $_SESSION['fails'] = (isset($_SESSION['fails']) ? $_SESSION['fails'] : 0) + 1;
    if ($_SESSION['fails'] >= 5) {
        $_SESSION['lock_until'] = $now + 60;
        $_SESSION['fails'] = 0;
    }
    return 'Incorrect email or password.';
}

function do_logout(): void {
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'], $p['secure'], $p['httponly']);
    }
    session_destroy();
}

// ---------- CSRF protection ----------
// A hidden random token is placed in every form. A forged form from another website
// cannot know the token, so the server rejects it.

function csrf_token(): string {
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function csrf_field(): string {
    return '<input type="hidden" name="csrf" value="' . csrf_token() . '">';
}

function csrf_valid(): bool {
    return isset($_POST['csrf'], $_SESSION['csrf']) && hash_equals($_SESSION['csrf'], (string)$_POST['csrf']);
}

function verify_csrf(): void {
    if (!csrf_valid()) {
        http_response_code(419);
        die('Your session expired. Please go back, refresh the page and try again.');
    }
}
