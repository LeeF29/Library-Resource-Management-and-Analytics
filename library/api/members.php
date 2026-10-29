<?php
require_once __DIR__ . '/../includes/api.php';

function member_json(array $m): array
{
    return ['id' => (int)$m['id'], 'member_no' => $m['member_no'], 'full_name' => $m['full_name'], 'email' => $m['email'],
            'contact_number' => $m['contact_number'], 'status' => $m['status'], 'on_loan' => isset($m['on_loan']) ? (int)$m['on_loan'] : null];
}

if (method() === 'GET') {
    $user = api_user(['admin', 'librarian']);
    if (get_str('options') === '1') {
        $rows = db_all("SELECT id, member_no, full_name FROM members WHERE status = 'active' ORDER BY full_name");
        foreach ($rows as &$r) { $r['id'] = (int)$r['id']; }
        json_out(['ok' => true, 'items' => $rows]);
    }
    if (get_str('available_for_account') === '1') {   // members without a login yet (for the admin form)
        api_user(['admin']);
        $rows = db_all('SELECT m.id, m.member_no, m.full_name FROM members m
                        WHERE NOT EXISTS (SELECT 1 FROM users u WHERE u.member_id = m.id AND u.id <> ?) ORDER BY m.full_name', [get_int('user_id')]);
        foreach ($rows as &$r) { $r['id'] = (int)$r['id']; }
        json_out(['ok' => true, 'items' => $rows]);
    }
    if (get_int('id') > 0) {
        $m = db_one('SELECT * FROM members WHERE id = ?', [get_int('id')]);
        if (!$m) {
            json_fail('Member not found.', 404);
        }
        $out = ['ok' => true, 'item' => member_json($m)];
        if (get_str('history') === '1') {
            $h = db_all('SELECT t.*, r.title FROM transactions t JOIN resources r ON r.id = t.resource_id WHERE t.member_id = ? ORDER BY t.id DESC', [$m['id']]);
            $out['history'] = array_map(function ($t) {
                return ['id' => (int)$t['id'], 'title' => $t['title'], 'borrow_date' => $t['borrow_date'], 'due_date' => $t['due_date'],
                        'return_date' => $t['return_date'], 'status' => loan_status($t)];
            }, $h);
        }
        json_out($out);
    }
    $q = get_str('q');
    $status = get_str('status', 10);
    $where = ['1=1'];
    $params = [];
    if ($q !== '') {
        $like = '%' . escape_like($q) . '%';
        $where[] = "(m.full_name LIKE ? ESCAPE '!' OR m.member_no LIKE ? ESCAPE '!' OR m.email LIKE ? ESCAPE '!')";
        array_push($params, $like, $like, $like);
    }
    if (in_array($status, ['active', 'inactive'], true)) {
        $where[] = 'm.status = ?';
        $params[] = $status;
    }
    $w = implode(' AND ', $where);
    $total = (int)db_value("SELECT COUNT(*) FROM members m WHERE $w", $params);
    $pg = paginate($total, get_int('page', 1));
    $rows = db_all("SELECT m.*, (SELECT COUNT(*) FROM transactions t WHERE t.member_id = m.id AND t.return_date IS NULL) AS on_loan
                    FROM members m WHERE $w ORDER BY m.full_name LIMIT " . (int)$pg['per'] . ' OFFSET ' . (int)$pg['offset'], $params);
    json_out(['ok' => true, 'items' => array_map('member_json', $rows), 'page' => pager_json($pg), 'can_delete' => $user['role'] === 'admin']);
}

$user = api_user(['admin', 'librarian']);
$action = b_str('action', 12);
$id = b_int('id');

if ($action === 'create' || $action === 'update') {
    $existing = null;
    if ($action === 'update') {
        $existing = db_one('SELECT * FROM members WHERE id = ?', [$id]);
        if (!$existing) {
            json_fail('Member not found.', 404);
        }
    }
    $v = ['member_no' => b_str('member_no', 20), 'full_name' => b_str('full_name', 100), 'email' => b_str('email', 100), 'contact_number' => b_str('contact_number', 20)];
    $errors = [];
    if (!preg_match('/^[A-Za-z0-9-]{3,20}$/', $v['member_no'])) {
        $errors[] = 'Member ID must be 3 to 20 characters: letters, numbers and hyphens only.';
    } elseif (db_value('SELECT id FROM members WHERE member_no = ? AND id <> ?', [$v['member_no'], $id])) {
        $errors[] = 'This Member ID is already used by another member.';
    }
    if ($v['full_name'] === '') $errors[] = 'Full name is required (maximum 100 characters).';
    if ($v['email'] !== '' && !filter_var($v['email'], FILTER_VALIDATE_EMAIL)) $errors[] = 'Please enter a valid email address or leave it blank.';
    if ($v['contact_number'] !== '' && !preg_match('/^[0-9+\-\s()]{7,20}$/', $v['contact_number'])) {
        $errors[] = 'Contact number must be 7 to 20 characters (digits, spaces, + - and parentheses).';
    }
    if ($errors) {
        json_fail('Please fix the highlighted problems.', 422, $errors);
    }
    try {
        if ($existing) {
            db_exec('UPDATE members SET member_no=?, full_name=?, email=?, contact_number=? WHERE id=?',
                [$v['member_no'], $v['full_name'], $v['email'] ?: null, $v['contact_number'] ?: null, $id]);
            json_out(['ok' => true, 'message' => 'Member updated.']);
        }
        db_exec('INSERT INTO members (member_no, full_name, email, contact_number) VALUES (?,?,?,?)',
            [$v['member_no'], $v['full_name'], $v['email'] ?: null, $v['contact_number'] ?: null]);
        json_out(['ok' => true, 'message' => 'Member added.']);
    } catch (PDOException $ex) {
        error_log('members: ' . $ex->getMessage());
        json_fail('The member could not be saved (possible duplicate Member ID).', 409);
    }
}

$m = $id ? db_one('SELECT * FROM members WHERE id = ?', [$id]) : null;
if (!$m) {
    json_fail('Member not found.', 404);
}
if ($action === 'deactivate') {
    $out = (int)db_value('SELECT COUNT(*) FROM transactions WHERE member_id = ? AND return_date IS NULL', [$id]);
    if ($out > 0) {
        json_fail($m['full_name'] . " still has $out unreturned item(s). Record the return first.", 409);
    }
    db_exec("UPDATE members SET status = 'inactive' WHERE id = ?", [$id]);
    json_out(['ok' => true, 'message' => $m['full_name'] . ' was deactivated.']);
}
if ($action === 'activate') {
    db_exec("UPDATE members SET status = 'active' WHERE id = ?", [$id]);
    json_out(['ok' => true, 'message' => $m['full_name'] . ' was activated.']);
}
if ($action === 'delete') {
    if ($user['role'] !== 'admin') {
        json_fail('Only an administrator can delete members. You can deactivate them instead.', 403);
    }
    if ((int)db_value('SELECT COUNT(*) FROM transactions WHERE member_id = ?', [$id]) > 0) {
        json_fail('This member has borrowing history and cannot be deleted. Deactivate the member instead.', 409);
    }
    if ((int)db_value('SELECT COUNT(*) FROM users WHERE member_id = ?', [$id]) > 0) {
        json_fail('This member has a login account. Remove the link in User Accounts first.', 409);
    }
    db_exec('DELETE FROM members WHERE id = ?', [$id]);
    json_out(['ok' => true, 'message' => 'Member deleted.']);
}
json_fail('Unknown action.', 400);
