<?php
require_once __DIR__ . '/../includes/api.php';
api_user(['admin', 'librarian']);

$type = get_str('type', 10);
if (!in_array($type, ['inventory', 'borrowing', 'popular'], true)) {
    $type = 'inventory';
}
$category = get_int('category');
$rtype = get_str('rtype', 10);
$from = get_str('from', 10);
$to = get_str('to', 10);
$export = get_str('export', 3) === 'csv';
if ($from !== '' && !valid_date($from)) json_fail('The "from" date is not valid.', 422);
if ($to !== '' && !valid_date($to)) json_fail('The "to" date is not valid.', 422);
if ($from !== '' && $to !== '' && $from > $to) json_fail('The "from" date must not be after the "to" date.', 422);

$rw = ['1=1'];
$rp = [];
if ($category > 0) { $rw[] = 'r.category_id = ?'; $rp[] = $category; }
if (in_array($rtype, ['book', 'journal', 'other'], true)) { $rw[] = 'r.resource_type = ?'; $rp[] = $rtype; }

$rows = [];
if ($type === 'inventory') {
    $title = 'Book inventory report';
    $header = ['Title', 'Author', 'ISBN', 'Category', 'Type', 'Total copies', 'Available', 'Borrowed', 'Status'];
    $w = implode(' AND ', $rw);
    foreach (db_all("SELECT r.*, c.name AS category_name FROM resources r JOIN categories c ON c.id = r.category_id WHERE $w ORDER BY c.name, r.title", $rp) as $r) {
        $rows[] = [$r['title'], $r['author'], $r['isbn'] ?: '', $r['category_name'], type_label($r['resource_type']),
                   (int)$r['total_copies'], (int)$r['available_copies'], (int)$r['total_copies'] - (int)$r['available_copies'],
                   (int)$r['is_active'] ? 'Active' : 'Inactive'];
    }
    $summary = ['Titles' => count($rows), 'Total copies' => array_sum(array_column($rows, 5)),
                'Available copies' => array_sum(array_column($rows, 6)), 'Borrowed copies' => array_sum(array_column($rows, 7))];
} else {
    $tw = $rw; $tp = $rp;
    if ($from !== '') { $tw[] = 't.borrow_date >= ?'; $tp[] = $from; }
    if ($to !== '') { $tw[] = 't.borrow_date <= ?'; $tp[] = $to; }
    $w = implode(' AND ', $tw);
    $join = 'FROM transactions t JOIN resources r ON r.id = t.resource_id JOIN categories c ON c.id = r.category_id JOIN members m ON m.id = t.member_id';
    if ($type === 'borrowing') {
        $title = 'Borrowing activity report';
        $header = ['Borrowed', 'Due', 'Returned', 'Member ID', 'Member', 'Title', 'Category', 'Status'];
        $counts = ['borrowed' => 0, 'overdue' => 0, 'returned' => 0];
        foreach (db_all("SELECT t.*, m.full_name, m.member_no, r.title, c.name AS category_name $join WHERE $w ORDER BY t.borrow_date DESC, t.id DESC", $tp) as $t) {
            $st = loan_status($t);
            $counts[$st]++;
            $rows[] = [$t['borrow_date'], $t['due_date'], $t['return_date'] ?: '', $t['member_no'], $t['full_name'], $t['title'], $t['category_name'], ucfirst($st)];
        }
        $summary = ['Transactions' => count($rows), 'Still borrowed' => $counts['borrowed'], 'Overdue' => $counts['overdue'], 'Returned' => $counts['returned']];
    } else {
        $title = 'Most frequently borrowed';
        $header = ['Rank', 'Title', 'Author', 'Category', 'Times borrowed'];
        $rank = 0;
        foreach (db_all("SELECT r.title, r.author, c.name AS category_name, COUNT(t.id) AS n $join WHERE $w
                         GROUP BY r.id, r.title, r.author, c.name ORDER BY n DESC, r.title LIMIT 20", $tp) as $r) {
            $rows[] = [++$rank, $r['title'], $r['author'], $r['category_name'], (int)$r['n']];
        }
        $summary = ['Titles listed' => count($rows), 'Total borrowings' => array_sum(array_column($rows, 4))];
    }
}

if ($export) {
    csv_download(str_replace(' ', '_', strtolower($title)) . '_' . today() . '.csv', $header, $rows);
}
json_out(['ok' => true, 'title' => $title, 'header' => $header, 'rows' => $rows, 'summary' => $summary]);
