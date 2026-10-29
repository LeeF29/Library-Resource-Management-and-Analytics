<?php
require_once __DIR__ . '/../includes/api.php';
api_user(['admin', 'librarian']);
$today = today();

$stats = [
    'titles'    => (int)db_value('SELECT COUNT(*) FROM resources WHERE is_active = 1'),
    'copies'    => (int)db_value('SELECT COALESCE(SUM(total_copies), 0) FROM resources WHERE is_active = 1'),
    'available' => (int)db_value('SELECT COALESCE(SUM(available_copies), 0) FROM resources WHERE is_active = 1'),
    'borrowed'  => (int)db_value('SELECT COUNT(*) FROM transactions WHERE return_date IS NULL'),
    'overdue'   => (int)db_value('SELECT COUNT(*) FROM transactions WHERE return_date IS NULL AND due_date < ?', [$today]),
    'members'   => (int)db_value('SELECT COUNT(*) FROM members'),
    'members_active' => (int)db_value("SELECT COUNT(*) FROM members WHERE status = 'active'"),
];

$months = [];
for ($i = 5; $i >= 0; $i--) {
    $months[date('Y-m', strtotime("first day of -$i month"))] = 0;
}
$since = date('Y-m-01', strtotime('first day of -5 month'));
foreach (db_all('SELECT borrow_date, COUNT(*) AS c FROM transactions WHERE borrow_date >= ? GROUP BY borrow_date', [$since]) as $r) {
    $k = substr($r['borrow_date'], 0, 7);
    if (isset($months[$k])) {
        $months[$k] += (int)$r['c'];
    }
}
$activity = [];
foreach ($months as $ym => $c) {
    $activity[] = ['label' => date('M', strtotime($ym . '-01')), 'count' => $c];
}

$top = db_all('SELECT r.id, r.title, r.author, COUNT(t.id) AS c FROM transactions t JOIN resources r ON r.id = t.resource_id
               GROUP BY r.id, r.title, r.author ORDER BY c DESC, r.title LIMIT 5');
foreach ($top as &$t) { $t['c'] = (int)$t['c']; unset($t['id']); }

$overdue = db_all('SELECT t.id, t.due_date, m.full_name, m.member_no, r.title FROM transactions t
                   JOIN members m ON m.id = t.member_id JOIN resources r ON r.id = t.resource_id
                   WHERE t.return_date IS NULL AND t.due_date < ? ORDER BY t.due_date LIMIT 5', [$today]);
$recent = array_map(function ($t) {
    return ['full_name' => $t['full_name'], 'title' => $t['title'], 'status' => loan_status($t)];
}, db_all('SELECT t.*, m.full_name, r.title FROM transactions t JOIN members m ON m.id = t.member_id
           JOIN resources r ON r.id = t.resource_id ORDER BY t.id DESC LIMIT 6'));

json_out(['ok' => true, 'stats' => $stats, 'activity' => $activity, 'top' => $top, 'overdue' => $overdue, 'recent' => $recent]);
