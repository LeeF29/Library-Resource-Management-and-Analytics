<?php
require_once __DIR__ . '/../includes/api.php';

if (method() === 'GET') {
    // The catalog list is public (read-only, active items only). Everything else needs a staff login.
    $u = current_user();
    $staff = $u && in_array($u['role'], ['admin', 'librarian'], true);

    // Borrow page: every active item that has a copy available
    if (get_str('options') === '1') {
        api_user(['admin', 'librarian']);
        $rows = db_all('SELECT id, title, author, available_copies FROM resources WHERE is_active = 1 AND available_copies > 0 ORDER BY title');
        foreach ($rows as &$r) { $r['id'] = (int)$r['id']; $r['available_copies'] = (int)$r['available_copies']; }
        json_out(['ok' => true, 'items' => $rows]);
    }
    // One record (edit form)
    if (get_int('id') > 0) {
        api_user(['admin', 'librarian']);
        $r = db_one('SELECT r.*, c.name AS category_name FROM resources r JOIN categories c ON c.id = r.category_id WHERE r.id = ?', [get_int('id')]);
        if (!$r) {
            json_fail('That record does not exist.', 404);
        }
        json_out(['ok' => true, 'item' => resource_row_json($r)]);
    }
    $f = ['q' => get_str('q'), 'category' => get_int('category'), 'type' => get_str('type', 10), 'avail' => get_str('avail', 12)];
    [$rows, $pg] = resource_search($f, get_int('page', 1), !$staff);   // the public only sees active items
    $summary = ['titles' => (int)db_value('SELECT COUNT(*) FROM resources WHERE is_active = 1'),
                'copies' => (int)db_value('SELECT COALESCE(SUM(total_copies),0) FROM resources WHERE is_active = 1'),
                'available' => (int)db_value('SELECT COALESCE(SUM(available_copies),0) FROM resources WHERE is_active = 1')];
    json_out(['ok' => true, 'items' => array_map('resource_row_json', $rows), 'page' => pager_json($pg), 'summary' => $summary]);
}

// ----- writes (staff only) -----
$user = api_user(['admin', 'librarian']);
$action = b_str('action', 10);
$id = b_int('id');

if ($action === 'create' || $action === 'update') {
    $editing = $action === 'update';
    $borrowedNow = 0;
    if ($editing) {
        $row = db_one('SELECT * FROM resources WHERE id = ?', [$id]);
        if (!$row) {
            json_fail('That record does not exist.', 404);
        }
        $borrowedNow = (int)$row['total_copies'] - (int)$row['available_copies'];
    }
    $d = [
        'title' => b_str('title', 200), 'author' => b_str('author', 150), 'isbn' => b_str('isbn', 20),
        'category_id' => b_int('category_id'), 'resource_type' => b_str('resource_type', 10),
        'publisher' => b_str('publisher', 150), 'year_published' => b_str('year_published', 4), 'total_copies' => b_str('total_copies', 4),
        'is_active' => $editing ? (b_bool('is_active') ? 1 : 0) : 1,
    ];
    $errors = [];
    if ($d['title'] === '') $errors[] = 'Title is required.';
    if ($d['author'] === '') $errors[] = 'Author is required.';
    if (!in_array($d['resource_type'], ['book', 'journal', 'other'], true)) $errors[] = 'Choose a valid resource type.';
    if (!db_one('SELECT id FROM categories WHERE id = ?', [$d['category_id']])) $errors[] = 'Choose a category.';
    $isbn = null;
    if ($d['isbn'] !== '') {
        $isbn = normalize_isbn($d['isbn']);
        if ($isbn === null) {
            $errors[] = 'ISBN is not valid. Enter a valid ISBN-10 or ISBN-13 (hyphens are allowed).';
        } elseif (db_one('SELECT id FROM resources WHERE isbn = ? AND id <> ?', [$isbn, $id])) {
            $errors[] = 'Another record already uses this ISBN.';
        }
    }
    $year = null;
    if ($d['year_published'] !== '') {
        if (!ctype_digit($d['year_published']) || (int)$d['year_published'] < 1000 || (int)$d['year_published'] > (int)date('Y') + 1) {
            $errors[] = 'Year published must be a 4-digit year.';
        } else {
            $year = (int)$d['year_published'];
        }
    }
    $total = 0;
    if (!ctype_digit($d['total_copies']) || (int)$d['total_copies'] < 1 || (int)$d['total_copies'] > 1000) {
        $errors[] = 'Number of copies must be a whole number from 1 to 1000.';
    } else {
        $total = (int)$d['total_copies'];
        if ($editing && $total < $borrowedNow) {
            $errors[] = "Copies cannot be less than the $borrowedNow currently borrowed.";
        }
    }
    if ($editing && !$d['is_active'] && $borrowedNow > 0) {
        $errors[] = 'This item has copies borrowed, so it cannot be made inactive yet.';
    }
    if ($errors) {
        json_fail('Please fix the highlighted problems.', 422, $errors);
    }
    try {
        if ($editing) {
            // available = new total - copies still out, so counts stay consistent
            db_exec('UPDATE resources SET title=?, author=?, isbn=?, category_id=?, resource_type=?, publisher=?, year_published=?,
                     total_copies=?, available_copies=?, is_active=? WHERE id=?',
                [$d['title'], $d['author'], $isbn, $d['category_id'], $d['resource_type'], $d['publisher'] ?: null, $year,
                 $total, $total - $borrowedNow, $d['is_active'], $id]);
            json_out(['ok' => true, 'message' => 'Resource updated.']);
        }
        db_exec('INSERT INTO resources (title, author, isbn, category_id, resource_type, publisher, year_published, total_copies, available_copies)
                 VALUES (?,?,?,?,?,?,?,?,?)',
            [$d['title'], $d['author'], $isbn, $d['category_id'], $d['resource_type'], $d['publisher'] ?: null, $year, $total, $total]);
        json_out(['ok' => true, 'message' => 'Resource added.']);
    } catch (PDOException $ex) {
        error_log('resources: ' . $ex->getMessage());
        json_fail('The record could not be saved (the ISBN may already exist).', 409);
    }
}

$res = db_one('SELECT id, title, is_active FROM resources WHERE id = ?', [$id]);
if (!$res) {
    json_fail('Record not found.', 404);
}
if ($action === 'delete') {
    $activeLoans = (int)db_value('SELECT COUNT(*) FROM transactions WHERE resource_id = ? AND return_date IS NULL', [$id]);
    $history = (int)db_value('SELECT COUNT(*) FROM transactions WHERE resource_id = ?', [$id]);
    if ($activeLoans > 0) {
        json_fail('"' . $res['title'] . '" still has copies borrowed. Record the returns first.', 409);
    }
    if ($history > 0) {
        db_exec('UPDATE resources SET is_active = 0 WHERE id = ?', [$id]);
        json_out(['ok' => true, 'message' => '"' . $res['title'] . '" has borrowing history, so it was marked Inactive instead of deleted. Its history is kept.']);
    }
    db_exec('DELETE FROM resources WHERE id = ?', [$id]);
    json_out(['ok' => true, 'message' => '"' . $res['title'] . '" was deleted.']);
}
if ($action === 'activate') {
    db_exec('UPDATE resources SET is_active = 1 WHERE id = ?', [$id]);
    json_out(['ok' => true, 'message' => '"' . $res['title'] . '" is active again.']);
}
json_fail('Unknown action.', 400);
