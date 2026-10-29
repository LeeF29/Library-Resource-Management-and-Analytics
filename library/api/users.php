<?php
require_once __DIR__ . '/../includes/api.php';
$me = api_user(['admin']);

function user_json(array $u): array
{
    return ['id' => (int)$u['id'], 'username' => $u['username'], 'full_name' => $u['full_name'], 'role' => $u['role'],
            'member_id' => $u['member_id'] ? (int)$u['member_id'] : null, 'member_no' => $u['member_no'] ?? null, 'is_active' => (int)$u['is_active']];
}

if (method() === 'GET') {
    if (get_int('id') > 0) {
        $u = db_one('SELECT u.*, m.member_no FROM users u LEFT JOIN members m ON m.id = u.member_id WHERE u.id = ?', [get_int('id')]);
        $u ? json_out(['ok' => true, 'item' => user_json($u)]) : json_fail('Account not found.', 404);
    }
    $rows = db_all('SELECT u.*, m.member_no FROM users u LEFT JOIN members m ON m.id = u.member_id ORDER BY u.role, u.username');
    json_out(['ok' => true, 'items' => array_map('user_json', $rows), 'me' => (int)$me['id']]);
}

$action = b_str('action', 12);
$id = b_int('id');
$existing = $id ? db_one('SELECT * FROM users WHERE id = ?', [$id]) : null;

if ($action === 'activate' || $action === 'deactivate') {
    if (!$existing) json_fail('Account not found.', 404);
    if ((int)$existing['id'] === (int)$me['id']) json_fail('You cannot deactivate your own account.', 409);
    $new = $action === 'activate' ? 1 : 0;
    if (!$new && $existing['role'] === 'admin'
        && (int)db_value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id <> ?", [$id]) === 0) {
        json_fail('At least one active administrator is required.', 409);
    }
    db_exec('UPDATE users SET is_active = ? WHERE id = ?', [$new, $id]);
    json_out(['ok' => true, 'message' => 'Account "' . $existing['username'] . '" was ' . ($new ? 'activated.' : 'deactivated.')]);
}

if ($action === 'create' || $action === 'update') {
    if ($action === 'update' && !$existing) json_fail('Account not found.', 404);
    $v = ['username' => b_str('username', 50), 'full_name' => b_str('full_name', 100), 'role' => b_str('role', 10), 'member_id' => b_int('member_id')];
    $pw = req_body()['password'] ?? '';
    $pw = is_string($pw) ? $pw : '';
    $errors = [];
    if (!preg_match('/^[A-Za-z0-9._-]{3,50}$/', $v['username'])) {
        $errors[] = 'Username must be 3 to 50 characters: letters, numbers, dot, dash or underscore.';
    } elseif (db_value('SELECT id FROM users WHERE username = ? AND id <> ?', [$v['username'], $id])) {
        $errors[] = 'This username is already taken.';
    }
    if ($v['full_name'] === '') $errors[] = 'Full name is required.';
    if (!in_array($v['role'], ['admin', 'librarian', 'student'], true)) $errors[] = 'Choose a valid role.';
    if ($action === 'create' && $pw === '') $errors[] = 'A password is required for a new account.';
    if ($pw !== '' && (strlen($pw) < 8 || strlen($pw) > 72)) $errors[] = 'Password must be 8 to 72 characters.';
    $memberId = null;
    if ($v['role'] === 'student') {
        if (!db_one('SELECT id FROM members WHERE id = ?', [$v['member_id']])) {
            $errors[] = 'A student account must be linked to a member record.';
        } elseif (db_one('SELECT id FROM users WHERE member_id = ? AND id <> ?', [$v['member_id'], $id])) {
            $errors[] = 'That member already has a login account.';
        } else {
            $memberId = $v['member_id'];
        }
    }
    if ($existing && (int)$existing['id'] === (int)$me['id'] && $v['role'] !== 'admin') {
        $errors[] = 'You cannot remove your own administrator role.';
    }
    if ($existing && $existing['role'] === 'admin' && $v['role'] !== 'admin'
        && (int)db_value("SELECT COUNT(*) FROM users WHERE role = 'admin' AND is_active = 1 AND id <> ?", [$id]) === 0) {
        $errors[] = 'At least one active administrator is required.';
    }
    if ($errors) json_fail('Please fix the highlighted problems.', 422, $errors);
    try {
        if ($existing) {
            db_exec('UPDATE users SET username=?, full_name=?, role=?, member_id=? WHERE id=?', [$v['username'], $v['full_name'], $v['role'], $memberId, $id]);
            if ($pw !== '') {
                db_exec('UPDATE users SET password_hash = ? WHERE id = ?', [password_hash($pw, PASSWORD_DEFAULT), $id]);
            }
            json_out(['ok' => true, 'message' => 'Account updated.']);
        }
        db_exec('INSERT INTO users (username, password_hash, full_name, role, member_id) VALUES (?,?,?,?,?)',
            [$v['username'], password_hash($pw, PASSWORD_DEFAULT), $v['full_name'], $v['role'], $memberId]);
        json_out(['ok' => true, 'message' => 'Account created.']);
    } catch (PDOException $ex) {
        error_log('users: ' . $ex->getMessage());
        json_fail('The account could not be saved (username may already exist).', 409);
    }
}
json_fail('Unknown action.', 400);
