<?php
require_once __DIR__ . '/../includes/api.php';

// GET ?action=me : who is logged in + a CSRF token (works even when logged out)
if (method() === 'GET') {
    $u = current_user();
    json_out(['ok' => true, 'csrf' => csrf_token(),
        'user' => $u ? ['id' => (int)$u['id'], 'username' => $u['username'], 'full_name' => $u['full_name'],
                        'role' => $u['role'], 'member_id' => $u['member_id'] ? (int)$u['member_id'] : null,
                        'home' => home_for($u['role'])] : null]);
}

api_csrf();
$action = b_str('action', 10);

if ($action === 'login') {
    $username = b_str('username', 50);
    $password = req_body()['password'] ?? '';
    if ($username === '' || !is_string($password) || $password === '') {
        json_fail('Please enter your username and password.', 422);
    }
    if (throttled()) {
        json_fail('Too many failed attempts. Please wait one minute and try again.', 429);
    }
    if (!attempt_login($username, $password)) {
        json_fail(throttled() ? 'Too many failed attempts. Please wait one minute and try again.'
                              : 'Incorrect username or password, or the account is inactive.', 401);
    }
    $u = current_user(true);
    json_out(['ok' => true, 'csrf' => csrf_token(), 'home' => home_for($u['role'])]);
}

if ($action === 'logout') {
    do_logout();
    json_out(['ok' => true]);
}

json_fail('Unknown action.', 400);
