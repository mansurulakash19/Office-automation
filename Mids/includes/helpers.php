<?php
// includes/helpers.php — small helper functions used on many pages.
require_once __DIR__ . '/db.php';

// ---------- Output & input ----------

// Escape text before printing it in HTML. This stops XSS attacks. Use it on EVERY value you print.
function e($value): string {
    return htmlspecialchars((string)($value ?? ''), ENT_QUOTES, 'UTF-8');
}

function redirect(string $url): void {
    header('Location: ' . $url);
    exit;
}

// Read a trimmed text value from a submitted form.
function post(string $key, string $default = ''): string {
    if (isset($_POST[$key]) && !is_array($_POST[$key])) {
        return trim($_POST[$key]);
    }
    return $default;
}

function valid_date(?string $d): bool {
    if ($d === null || $d === '') { return false; }
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt !== false && $dt->format('Y-m-d') === $d;
}

function post_date(string $key): ?string {
    $d = post($key);
    return valid_date($d) ? $d : null;
}

function post_id(string $key): ?int {
    $v = post($key);
    return (ctype_digit($v) && (int)$v > 0) ? (int)$v : null;
}

function json_response(array $data, int $code = 200): void {
    http_response_code($code);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data);
    exit;
}

// ---------- Flash messages (the little toast after saving) ----------

function flash(string $type, string $message): void {
    $_SESSION['flashes'][] = ['type' => $type, 'message' => $message];
}

function get_flashes(): array {
    $f = $_SESSION['flashes'] ?? [];
    unset($_SESSION['flashes']);
    return $f;
}

// ---------- Formatting ----------

// Bangladeshi number grouping: 482000 -> ৳4,82,000
function money($amount): string {
    $amount = (float)$amount;
    $negative = $amount < 0;
    $amount = abs($amount);
    $whole = (string)floor($amount);
    $cents = (int)round(($amount - floor($amount)) * 100);
    if (strlen($whole) > 3) {
        $last3 = substr($whole, -3);
        $rest = substr($whole, 0, -3);
        $rest = preg_replace('/\B(?=(\d{2})+(?!\d))/', ',', $rest);
        $whole = $rest . ',' . $last3;
    }
    $text = '৳' . $whole;
    if ($cents > 0) { $text .= '.' . sprintf('%02d', $cents); }
    return $negative ? '-' . $text : $text;
}

function fmt_date(?string $d): string {
    if (!$d) { return '—'; }
    $t = strtotime($d);
    return $t ? date('d M Y', $t) : '—';
}

function fmt_time(?string $t): string {
    if (!$t) { return '—'; }
    $ts = strtotime($t);
    return $ts ? date('h:i A', $ts) : '—';
}

function fmt_datetime(?string $d): string {
    if (!$d) { return '—'; }
    $t = strtotime($d);
    return $t ? date('d M, h:i A', $t) : '—';
}

function fmt_size(int $bytes): string {
    if ($bytes >= 1048576) { return round($bytes / 1048576, 1) . ' MB'; }
    if ($bytes >= 1024) { return round($bytes / 1024) . ' KB'; }
    return $bytes . ' B';
}

// How many days from today until $date? Negative = in the past.
function days_until(?string $date): ?int {
    if (!$date) { return null; }
    $a = new DateTime(date('Y-m-d'));
    $b = new DateTime($date);
    return (int)$a->diff($b)->format('%r%a');
}

function due_phrase(?string $date): string {
    $d = days_until($date);
    if ($d === null) { return 'with no due date'; }
    if ($d < 0) { return abs($d) . ' day(s) ago'; }
    if ($d === 0) { return 'today'; }
    if ($d === 1) { return 'tomorrow'; }
    return 'in ' . $d . ' days';
}

function due_tone(?string $date): string {
    $d = days_until($date);
    if ($d === null) { return 'neutral'; }
    if ($d < 0) { return 'danger'; }
    if ($d <= 3) { return 'warning'; }
    return 'neutral';
}

// A small coloured chip such as "Due in 3 days" or "2 day(s) ago".
function due_chip(?string $date, bool $done = false): string {
    if (!$date) { return '<span class="muted">—</span>'; }
    if ($done) { return '<span class="muted">' . e(fmt_date($date)) . '</span>'; }
    $d = days_until($date);
    if ($d < 0) { $text = abs($d) . ' day(s) late'; }
    elseif ($d === 0) { $text = 'Due today'; }
    elseif ($d === 1) { $text = 'Due tomorrow'; }
    else { $text = fmt_date($date); }
    return '<span class="badge badge-' . due_tone($date) . '">' . e($text) . '</span>';
}

function time_ago(?string $dt): string {
    if (!$dt) { return 'never'; }
    $diff = time() - strtotime($dt);
    if ($diff < 60) { return 'just now'; }
    if ($diff < 3600) { return floor($diff / 60) . ' min ago'; }
    if ($diff < 86400) { return floor($diff / 3600) . ' h ago'; }
    if ($diff < 172800) { return 'yesterday'; }
    if ($diff < 2592000) { return floor($diff / 86400) . ' days ago'; }
    return fmt_date($dt);
}

function first_name(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    return $parts[0] !== '' ? $parts[0] : $name;
}

function initials(string $name): string {
    $parts = preg_split('/\s+/', trim($name));
    $out = '';
    foreach (array_slice($parts, 0, 2) as $p) {
        if (preg_match('/^./u', $p, $m)) { $out .= strtoupper($m[0]); }
    }
    return $out !== '' ? $out : '?';
}

function avatar(string $name, string $size = ''): string {
    $colors = ['#2563EB', '#0F766E', '#B45309', '#7C3AED', '#BE123C', '#0369A1', '#4D7C0F'];
    $color = $colors[abs(crc32($name)) % count($colors)];
    return '<span class="avatar ' . e($size) . '" style="background:' . $color . '">' . e(initials($name)) . '</span>';
}

// A coloured status pill. The map says which colour each status gets.
function badge(string $status, string $label = ''): string {
    static $map = [
        'Paid' => 'success', 'Approved' => 'success', 'Done' => 'success', 'Completed' => 'success',
        'Present' => 'success', 'Sent' => 'success', 'Active' => 'success', 'On track' => 'success',
        'Pending' => 'warning', 'Queued' => 'warning', 'Late' => 'warning', 'Medium' => 'warning', 'Due soon' => 'warning',
        'Planning' => 'info', 'In Progress' => 'info', 'Leave' => 'info', 'Admin' => 'info',
        'Overdue' => 'danger', 'Rejected' => 'danger', 'Absent' => 'danger', 'High' => 'danger',
        'To Do' => 'neutral', 'On Hold' => 'neutral', 'Low' => 'neutral', 'Inactive' => 'neutral', 'Staff' => 'neutral', 'No date' => 'neutral',
    ];
    $tone = isset($map[$status]) ? $map[$status] : 'neutral';
    $text = $label !== '' ? $label : $status;
    return '<span class="badge badge-' . $tone . '">' . e($text) . '</span>';
}

// Client follow-up status, worked out from the next follow-up date.
function followup_status(?string $date): string {
    $d = days_until($date);
    if ($d === null) { return 'No date'; }
    if ($d < 0) { return 'Overdue'; }
    if ($d <= 3) { return 'Due soon'; }
    return 'On track';
}

// ---------- Icons (inline SVG so the project works without internet) ----------

function icon_paths(string $name): string {
    static $icons = [
        'dashboard' => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'wallet' => '<path d="M3 7a2 2 0 0 1 2-2h13v4"/><path d="M3 7v10a2 2 0 0 0 2 2h14a1 1 0 0 0 1-1v-3"/><path d="M21 10h-5a2 2 0 0 0 0 4h5z"/>',
        'users' => '<circle cx="9" cy="8" r="3.2"/><path d="M3 20c0-3.3 2.7-5.5 6-5.5s6 2.2 6 5.5"/><circle cx="17.5" cy="9" r="2.3"/><path d="M16.5 14.6c2.6.2 4.5 2 4.5 4.9"/>',
        'user' => '<circle cx="12" cy="8" r="3.5"/><path d="M5 20c0-3.6 3.1-6 7-6s7 2.4 7 6"/>',
        'kanban' => '<rect x="3" y="4" width="5" height="16" rx="1.2"/><rect x="10" y="4" width="5" height="10" rx="1.2"/><rect x="17" y="4" width="4" height="13" rx="1.2"/>',
        'folder' => '<path d="M3 6.5A1.5 1.5 0 0 1 4.5 5H9l2 2.5h8.5A1.5 1.5 0 0 1 21 9v9.5a1.5 1.5 0 0 1-1.5 1.5h-15A1.5 1.5 0 0 1 3 18.5z"/>',
        'eye' => '<path d="M2 12s3.6-6.5 10-6.5S22 12 22 12s-3.6 6.5-10 6.5S2 12 2 12z"/><circle cx="12" cy="12" r="3"/>',
        'shield' => '<path d="M12 3l7.5 3v5.5c0 4.5-3.1 7.9-7.5 9.5-4.4-1.6-7.5-5-7.5-9.5V6z"/><path d="M8.8 12l2.2 2.2 4.2-4.4"/>',
        'bolt' => '<path d="M13 2L4.5 13.5H11L10 22l8.5-11.5H12z"/>',
        'bell' => '<path d="M6 9a6 6 0 0 1 12 0c0 5.5 2 7 2 7H4s2-1.5 2-7"/><path d="M10 20a2 2 0 0 0 4 0"/>',
        'search' => '<circle cx="11" cy="11" r="6.5"/><path d="M20 20l-4-4"/>',
        'plus' => '<path d="M12 5v14M5 12h14"/>',
        'edit' => '<path d="M4 20h4L19 9a2.1 2.1 0 0 0-3-3L5 17z"/><path d="M14.5 7.5l3 3"/>',
        'trash' => '<path d="M4 7h16"/><path d="M9 7V4.5h6V7"/><path d="M6.5 7l1 13h9l1-13"/><path d="M10 11v5M14 11v5"/>',
        'download' => '<path d="M12 4v11"/><path d="M7.5 10.5L12 15l4.5-4.5"/><path d="M5 19h14"/>',
        'upload' => '<path d="M12 16V5"/><path d="M7.5 9.5L12 5l4.5 4.5"/><path d="M5 19h14"/>',
        'logout' => '<path d="M9 4H5.5A1.5 1.5 0 0 0 4 5.5v13A1.5 1.5 0 0 0 5.5 20H9"/><path d="M15 8l4 4-4 4"/><path d="M19 12H9"/>',
        'menu' => '<path d="M4 7h16M4 12h16M4 17h16"/>',
        'x' => '<path d="M6 6l12 12M18 6L6 18"/>',
        'chat' => '<path d="M4 5h16v11H9l-5 4z"/><path d="M8.5 9.5h7M8.5 12.5h4"/>',
        'send' => '<path d="M21 3L10 14"/><path d="M21 3l-7 18-4-7-7-4z"/>',
        'calendar' => '<rect x="3.5" y="5" width="17" height="15" rx="2"/><path d="M3.5 10h17M8 3v4M16 3v4"/>',
        'clock' => '<circle cx="12" cy="12" r="8.5"/><path d="M12 7.5V12l3 2"/>',
        'alert' => '<path d="M12 3.5l9 16H3z"/><path d="M12 10v4.5M12 17.5v.01"/>',
        'check' => '<path d="M5 12.5l4.5 4.5L19 7.5"/>',
        'file' => '<path d="M6 3h8l4 4v14H6z"/><path d="M14 3v4h4"/>',
        'receipt' => '<path d="M6 3h12v18l-3-2-3 2-3-2-3 2z"/><path d="M9 8h6M9 12h6"/>',
        'trend' => '<path d="M3 17l6-6 4 4 8-8"/><path d="M15 7h6v6"/>',
        'sparkle' => '<path d="M12 3l1.8 5.2L19 10l-5.2 1.8L12 17l-1.8-5.2L5 10l5.2-1.8z"/>',
        'briefcase' => '<rect x="3" y="7" width="18" height="13" rx="2"/><path d="M9 7V5a1.5 1.5 0 0 1 1.5-1.5h3A1.5 1.5 0 0 1 15 5v2"/>',
        'arrow' => '<path d="M5 12h14M13 6l6 6-6 6"/>',
        'play' => '<path d="M7 4.5v15L19 12z"/>',
        'door' => '<path d="M6 21V4.5A1.5 1.5 0 0 1 7.5 3h9A1.5 1.5 0 0 1 18 4.5V21"/><path d="M4 21h16"/><path d="M14 12v.01"/>',
    ];
    return isset($icons[$name]) ? $icons[$name] : '';
}

function icon(string $name, int $size = 18): string {
    return '<svg class="icon" width="' . $size . '" height="' . $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" '
         . 'stroke-width="1.7" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . icon_paths($name) . '</svg>';
}

// ---------- Activity log & notifications ----------

// Writes a line to the activity log (an audit trail of what users did).
function log_activity(string $module, string $action, ?int $userId = null): void {
    if ($userId === null && function_exists('user_id')) {
        $uid = user_id();
        $userId = $uid > 0 ? $uid : null;
    }
    db_exec('INSERT INTO activity_log (user_id, module, action, created_at) VALUES (?,?,?,?)',
        [$userId, $module, $action, date('Y-m-d H:i:s')]);
}

// Creates an in-app notification (the bell icon) for one user.
function notify(int $userId, string $message, string $link = '', string $type = 'info'): void {
    $message = preg_replace('/^(.{0,240}).*$/su', '$1', $message);   // keep it within the column size
    db_exec('INSERT INTO notifications (user_id, message, link, type, created_at) VALUES (?,?,?,?,?)',
        [$userId, $message, $link, $type, date('Y-m-d H:i:s')]);
}

function notify_admins(string $message, string $link = '', string $type = 'info'): void {
    foreach (db_all("SELECT id FROM users WHERE role = 'admin' AND is_active = 1") as $a) {
        notify((int)$a['id'], $message, $link, $type);
    }
}
