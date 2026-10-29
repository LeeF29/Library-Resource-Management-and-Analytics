<?php
/** Sessions, login state and CSRF token. Used by the JSON API only. */

require_once __DIR__ . '/config.php';
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/helpers.php';

function start_secure_session(): void
{
    if (session_status() === PHP_SESSION_ACTIVE) {
        return;
    }
    $https = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off');
    ini_set('session.use_strict_mode', '1');
    ini_set('session.use_only_cookies', '1');
    session_name('LIBSESSID');
    session_set_cookie_params([
        'lifetime' => 0,
        'path'     => BASE_URL . '/',
        'secure'   => $https,
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
    session_start();
}

start_secure_session();

function csrf_token(): string
{
    if (empty($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

/** The logged-in user (fresh from the database) or null. */
function current_user(bool $refresh = false): ?array
{
    static $user = false;
    if ($refresh) {
        $user = false;
    }
    if ($user !== false) {
        return $user;
    }
    $user = null;
    if (!empty($_SESSION['uid'])) {
        if (time() - ($_SESSION['last_seen'] ?? 0) > SESSION_IDLE_SEC) {
            unset($_SESSION['uid'], $_SESSION['last_seen']);
            return $user;
        }
        $row = db_one('SELECT id, username, full_name, role, member_id, is_active FROM users WHERE id = ?', [$_SESSION['uid']]);
        if ($row && (int)$row['is_active'] === 1) {
            $_SESSION['last_seen'] = time();
            $user = $row;
        } else {
            unset($_SESSION['uid'], $_SESSION['last_seen']);
        }
    }
    return $user;
}

function throttled(): bool
{
    return ($_SESSION['fails'] ?? 0) >= 5 && time() - ($_SESSION['fail_time'] ?? 0) < 60;
}

function attempt_login(string $username, string $password): bool
{
    if (throttled()) {
        return false;
    }
    $row = db_one('SELECT id, password_hash, is_active FROM users WHERE username = ?', [$username]);
    // Always run password_verify so the time taken does not reveal if the username exists
    $hash = $row['password_hash'] ?? '$2y$10$vNL.NTFfbkzCt1cm3g.vt.lvwO3wPBdJ8c.OUmBjSRQEND6AkEZBq';
    $ok = password_verify($password, $hash) && $row && (int)$row['is_active'] === 1;
    if (!$ok) {
        $_SESSION['fails'] = ($_SESSION['fails'] ?? 0) + 1;
        $_SESSION['fail_time'] = time();
        return false;
    }
    session_regenerate_id(true);
    $_SESSION = ['uid' => (int)$row['id'], 'last_seen' => time(), 'csrf' => bin2hex(random_bytes(32))];
    if (password_needs_rehash($row['password_hash'], PASSWORD_DEFAULT)) {
        db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($password, PASSWORD_DEFAULT), $row['id']]);
    }
    return true;
}

function do_logout(): void
{
    $_SESSION = [];
    if (ini_get('session.use_cookies')) {
        $p = session_get_cookie_params();
        setcookie(session_name(), '', time() - 42000, $p['path'], $p['domain'] ?? '', $p['secure'], $p['httponly']);
    }
    session_destroy();
}

function home_for(string $role): string
{
    return $role === 'student' ? 'student/my_loans.html' : 'staff/dashboard.html';
}
