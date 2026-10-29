<?php
/** Shared start-up for every JSON endpoint in api/. */

require_once __DIR__ . '/auth.php';

header('Content-Type: application/json; charset=utf-8');
header('Cache-Control: no-store');
header('X-Content-Type-Options: nosniff');
header('X-Frame-Options: DENY');

function json_out(array $data, int $code = 200): void
{
    http_response_code($code);
    echo json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_INVALID_UTF8_SUBSTITUTE);
    exit;
}

function json_fail(string $message, int $code = 400, array $errors = []): void
{
    json_out(['ok' => false, 'error' => $message, 'errors' => $errors ?: [$message]], $code);
}

/** Request body (JSON object) as array. */
function req_body(): array
{
    static $b = null;
    if ($b === null) {
        $raw = file_get_contents('php://input');
        $b = [];
        if ($raw !== '' && $raw !== false) {
            $d = json_decode($raw, true);
            $b = is_array($d) ? $d : [];
        }
    }
    return $b;
}

function b_str(string $key, int $max = 255): string
{
    $v = req_body()[$key] ?? '';
    return is_string($v) || is_numeric($v) ? mb_substr(trim((string)$v), 0, $max) : '';
}

function b_int(string $key): int
{
    $v = req_body()[$key] ?? null;
    return (is_int($v) || (is_string($v) && ctype_digit($v))) ? (int)$v : 0;
}

function b_bool(string $key): bool
{
    $v = req_body()[$key] ?? false;
    return $v === true || $v === 1 || $v === '1';
}

/**
 * Require login (and optionally one of the roles). For POST requests the JSON content type and CSRF token are also checked.
 * Returns the current user.
 */
function api_user(array $roles = [], bool $checkCsrf = true): array
{
    $u = current_user();
    if (!$u) {
        json_fail('Please log in to continue.', 401);
    }
    if ($roles && !in_array($u['role'], $roles, true)) {
        json_fail('Your account does not have permission to do this.', 403);
    }
    if ($checkCsrf && $_SERVER['REQUEST_METHOD'] !== 'GET') {
        api_csrf();
    }
    return $u;
}

function api_csrf(): void
{
    if (stripos($_SERVER['CONTENT_TYPE'] ?? '', 'application/json') !== 0) {
        json_fail('Invalid request type.', 415);
    }
    $sent = $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    $known = $_SESSION['csrf'] ?? '';
    if (!is_string($sent) || $known === '' || !hash_equals($known, $sent)) {
        json_fail('Your session token is invalid or expired. Please refresh the page and try again.', 400);
    }
}

function method(): string
{
    return $_SERVER['REQUEST_METHOD'];
}

function pager_json(array $pg): array
{
    return ['page' => $pg['page'], 'pages' => $pg['pages'], 'total' => $pg['total']];
}

/** Search resources (shared by staff list and student search). */
function resource_search(array $f, int $page, bool $onlyActive): array
{
    $where = ['1=1'];
    $p = [];
    if ($onlyActive) {
        $where[] = 'r.is_active = 1';
    }
    if ($f['q'] !== '') {
        $like = '%' . escape_like($f['q']) . '%';
        $likeIsbn = '%' . escape_like(str_replace('-', '', $f['q'])) . '%';
        $where[] = "(r.title LIKE ? ESCAPE '!' OR r.author LIKE ? ESCAPE '!' OR r.isbn LIKE ? ESCAPE '!')";
        array_push($p, $like, $like, $likeIsbn);
    }
    if ($f['category'] > 0) {
        $where[] = 'r.category_id = ?';
        $p[] = $f['category'];
    }
    if (in_array($f['type'], ['book', 'journal', 'other'], true)) {
        $where[] = 'r.resource_type = ?';
        $p[] = $f['type'];
    }
    if ($f['avail'] === 'available') {
        $where[] = 'r.is_active = 1 AND r.available_copies > 0';
    } elseif ($f['avail'] === 'unavailable') {
        $where[] = 'r.is_active = 1 AND r.available_copies = 0';
    }
    $w = implode(' AND ', $where);
    $total = (int)db_value("SELECT COUNT(*) FROM resources r WHERE $w", $p);
    $pg = paginate($total, $page);
    $rows = db_all(
        "SELECT r.*, c.name AS category_name FROM resources r JOIN categories c ON c.id = r.category_id
         WHERE $w ORDER BY r.title LIMIT " . (int)$pg['per'] . ' OFFSET ' . (int)$pg['offset'], $p);
    return [$rows, $pg];
}

function resource_row_json(array $r): array
{
    return [
        'id' => (int)$r['id'], 'title' => $r['title'], 'author' => $r['author'], 'isbn' => $r['isbn'],
        'category_id' => (int)$r['category_id'], 'category_name' => $r['category_name'] ?? null,
        'resource_type' => $r['resource_type'], 'publisher' => $r['publisher'],
        'year_published' => $r['year_published'] !== null ? (int)$r['year_published'] : null,
        'total_copies' => (int)$r['total_copies'], 'available_copies' => (int)$r['available_copies'],
        'is_active' => (int)$r['is_active'],
    ];
}
