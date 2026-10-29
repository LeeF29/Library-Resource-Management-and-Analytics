<?php
/** Small reusable helpers: dates, ISBN check, loan status, paging, CSV. */

function today(): string
{
    return date('Y-m-d');
}

function get_str(string $key, int $max = 100): string
{
    $v = $_GET[$key] ?? '';
    return is_string($v) ? mb_substr(trim($v), 0, $max) : '';
}

function get_int(string $key, int $default = 0): int
{
    $v = $_GET[$key] ?? null;
    return (is_string($v) && ctype_digit($v)) ? (int)$v : $default;
}

function valid_date(string $d): bool
{
    $dt = DateTime::createFromFormat('Y-m-d', $d);
    return $dt && $dt->format('Y-m-d') === $d;
}

/** Returns the cleaned ISBN (digits, maybe trailing X) if valid ISBN-10 or ISBN-13, otherwise null. */
function normalize_isbn(string $raw): ?string
{
    $s = strtoupper(str_replace(['-', ' '], '', $raw));
    if (preg_match('/^\d{13}$/', $s)) {
        $sum = 0;
        for ($i = 0; $i < 12; $i++) {
            $sum += (int)$s[$i] * ($i % 2 === 0 ? 1 : 3);
        }
        return ((10 - $sum % 10) % 10) === (int)$s[12] ? $s : null;
    }
    if (preg_match('/^\d{9}[\dX]$/', $s)) {
        $sum = 0;
        for ($i = 0; $i < 10; $i++) {
            $val = $s[$i] === 'X' ? 10 : (int)$s[$i];
            $sum += $val * (10 - $i);
        }
        return $sum % 11 === 0 ? $s : null;
    }
    return null;
}

/** borrowed | overdue | returned */
function loan_status(array $tx): string
{
    if (!empty($tx['return_date'])) {
        return 'returned';
    }
    return $tx['due_date'] < today() ? 'overdue' : 'borrowed';
}

function paginate(int $total, int $page, int $perPage = PER_PAGE): array
{
    $pages = max(1, (int)ceil($total / $perPage));
    $page = min(max(1, $page), $pages);
    return ['page' => $page, 'pages' => $pages, 'offset' => ($page - 1) * $perPage, 'per' => $perPage, 'total' => $total];
}

function escape_like(string $s): string
{
    return str_replace(['!', '%', '_'], ['!!', '!%', '!_'], $s);
}

function type_label(string $t): string
{
    return ['book' => 'Book', 'journal' => 'Journal', 'other' => 'Other material'][$t] ?? $t;
}

function csv_cell($v): string
{
    $v = (string)$v;
    // Stop spreadsheet formula injection
    if ($v !== '' && strpos('=+-@', $v[0]) !== false) {
        $v = "'" . $v;
    }
    return $v;
}

function csv_download(string $filename, array $header, array $rows): void
{
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    $out = fopen('php://output', 'w');
    fwrite($out, "\xEF\xBB\xBF"); // so Excel reads UTF-8 correctly
    fputcsv($out, $header);
    foreach ($rows as $r) {
        fputcsv($out, array_map('csv_cell', $r));
    }
    fclose($out);
    exit;
}

function fmt_date(?string $d): string
{
    if (!$d) {
        return '—';
    }
    $t = strtotime($d);
    return $t ? date('M j, Y', $t) : $d;
}
