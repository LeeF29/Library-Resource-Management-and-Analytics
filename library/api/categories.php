<?php
require_once __DIR__ . '/../includes/api.php';

if (method() === 'GET') {
    // Category names are public (used by the catalog filter); usage counts only for staff.
    $u = current_user();
    $staff = $u && in_array($u['role'], ['admin', 'librarian'], true);
    $rows = db_all('SELECT c.id, c.name, (SELECT COUNT(*) FROM resources r WHERE r.category_id = c.id) AS used FROM categories c ORDER BY c.name');
    foreach ($rows as &$r) { $r['id'] = (int)$r['id']; $r['used'] = $staff ? (int)$r['used'] : null; }
    json_out(['ok' => true, 'items' => $rows]);
}

api_user(['admin', 'librarian']);
$action = b_str('action', 10);
$name = b_str('name', 80);
$id = b_int('id');

if ($action === 'add' || $action === 'rename') {
    if ($name === '') {
        json_fail('Category name is required (maximum 80 characters).', 422);
    }
    if (db_value('SELECT id FROM categories WHERE LOWER(name) = LOWER(?) AND id <> ?', [$name, $id])) {
        json_fail('A category with this name already exists.', 422);
    }
    if ($action === 'add') {
        db_exec('INSERT INTO categories (name) VALUES (?)', [$name]);
        json_out(['ok' => true, 'message' => 'Category added.']);
    }
    if (!$id || !db_value('SELECT id FROM categories WHERE id = ?', [$id])) {
        json_fail('Category not found.', 404);
    }
    db_exec('UPDATE categories SET name = ? WHERE id = ?', [$name, $id]);
    json_out(['ok' => true, 'message' => 'Category renamed.']);
}
if ($action === 'delete') {
    $used = (int)db_value('SELECT COUNT(*) FROM resources WHERE category_id = ?', [$id]);
    if ($used > 0) {
        json_fail("This category is used by $used resource(s). Move or edit those resources first.", 409);
    }
    db_exec('DELETE FROM categories WHERE id = ?', [$id]);
    json_out(['ok' => true, 'message' => 'Category deleted.']);
}
json_fail('Unknown action.', 400);
