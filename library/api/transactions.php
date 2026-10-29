<?php
require_once __DIR__ . '/../includes/api.php';
require_once __DIR__ . '/../includes/services.php';

function tx_json(array $t): array
{
    return ['id' => (int)$t['id'], 'full_name' => $t['full_name'] ?? null, 'member_no' => $t['member_no'] ?? null, 'title' => $t['title'],
            'author' => $t['author'] ?? null, 'borrow_date' => $t['borrow_date'], 'due_date' => $t['due_date'],
            'return_date' => $t['return_date'], 'status' => loan_status($t)];
}

if (method() === 'GET') {
    $u = api_user();
    // A student's own loans
    if (get_str('mine') === '1') {
        api_user(['student']);
        $rows = $u['member_id'] ? db_all('SELECT t.*, r.title, r.author FROM transactions t JOIN resources r ON r.id = t.resource_id
                                           WHERE t.member_id = ? ORDER BY t.id DESC', [$u['member_id']]) : [];
        json_out(['ok' => true, 'linked' => (bool)$u['member_id'], 'items' => array_map('tx_json', $rows)]);
    }
    api_user(['admin', 'librarian']);
    $status = get_str('status', 10);
    $q = get_str('q');
    $from = get_str('from', 10);
    $to = get_str('to', 10);
    $today = today();
    $where = ['1=1'];
    $p = [];
    if ($status === 'borrowed') { $where[] = 't.return_date IS NULL AND t.due_date >= ?'; $p[] = $today; }
    elseif ($status === 'overdue') { $where[] = 't.return_date IS NULL AND t.due_date < ?'; $p[] = $today; }
    elseif ($status === 'returned') { $where[] = 't.return_date IS NOT NULL'; }
    if ($q !== '') {
        $like = '%' . escape_like($q) . '%';
        $where[] = "(m.full_name LIKE ? ESCAPE '!' OR m.member_no LIKE ? ESCAPE '!' OR r.title LIKE ? ESCAPE '!')";
        array_push($p, $like, $like, $like);
    }
    if ($from !== '') { if (!valid_date($from)) json_fail('Invalid "from" date.', 422); $where[] = 't.borrow_date >= ?'; $p[] = $from; }
    if ($to !== '') { if (!valid_date($to)) json_fail('Invalid "to" date.', 422); $where[] = 't.borrow_date <= ?'; $p[] = $to; }
    $w = implode(' AND ', $where);
    $from_sql = 'FROM transactions t JOIN members m ON m.id = t.member_id JOIN resources r ON r.id = t.resource_id';
    $total = (int)db_value("SELECT COUNT(*) $from_sql WHERE $w", $p);
    $pg = paginate($total, get_int('page', 1));
    $rows = db_all("SELECT t.*, m.full_name, m.member_no, r.title $from_sql WHERE $w ORDER BY t.id DESC LIMIT " . (int)$pg['per'] . ' OFFSET ' . (int)$pg['offset'], $p);
    json_out(['ok' => true, 'items' => array_map('tx_json', $rows), 'page' => pager_json($pg)]);
}

$user = api_user(['admin', 'librarian']);
$action = b_str('action', 10);
if ($action === 'borrow') {
    $memberId = b_int('member_id');
    $resourceId = b_int('resource_id');
    if (!$memberId || !$resourceId) {
        json_fail('Please choose both a member and a resource.', 422);
    }
    [$ok, $msg] = borrow_resource($memberId, $resourceId, (int)$user['id']);
    $ok ? json_out(['ok' => true, 'message' => $msg]) : json_fail($msg, 409);
}
if ($action === 'return') {
    [$ok, $msg] = return_resource(b_int('id'), (int)$user['id']);
    $ok ? json_out(['ok' => true, 'message' => $msg]) : json_fail($msg, 409);
}
json_fail('Unknown action.', 400);
