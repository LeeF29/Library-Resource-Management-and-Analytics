<?php
/** Business rules for borrowing and returning. Each action runs inside a database transaction. */

/** @return array [bool ok, string message] */
function borrow_resource(int $memberId, int $resourceId, int $staffId): array
{
    $pdo = db();
    try {
        $pdo->beginTransaction();

        // Touching the member row locks it, so two staff cannot borrow for the same member at the same moment.
        db_exec('UPDATE members SET status = status WHERE id = ?', [$memberId]);
        $member = db_one('SELECT id, full_name, status FROM members WHERE id = ?', [$memberId]);
        if (!$member || $member['status'] !== 'active') {
            $pdo->rollBack();
            return [false, 'The selected member was not found or is inactive.'];
        }

        $res = db_one('SELECT id, title, is_active FROM resources WHERE id = ?', [$resourceId]);
        if (!$res || !(int)$res['is_active']) {
            $pdo->rollBack();
            return [false, 'The selected resource was not found or is inactive.'];
        }

        $already = (int)db_value('SELECT COUNT(*) FROM transactions WHERE member_id = ? AND resource_id = ? AND return_date IS NULL', [$memberId, $resourceId]);
        if ($already > 0) {
            $pdo->rollBack();
            return [false, $member['full_name'] . ' already has a copy of "' . $res['title'] . '" borrowed.'];
        }

        $active = (int)db_value('SELECT COUNT(*) FROM transactions WHERE member_id = ? AND return_date IS NULL', [$memberId]);
        if ($active >= MAX_ACTIVE_LOANS) {
            $pdo->rollBack();
            return [false, $member['full_name'] . ' has reached the limit of ' . MAX_ACTIVE_LOANS . ' borrowed items.'];
        }

        // Only succeeds if a copy is really available (safe even with two staff clicking at once).
        $changed = db_exec('UPDATE resources SET available_copies = available_copies - 1 WHERE id = ? AND available_copies > 0', [$resourceId]);
        if ($changed !== 1) {
            $pdo->rollBack();
            return [false, 'No copy of "' . $res['title'] . '" is available right now.'];
        }

        $due = date('Y-m-d', strtotime('+' . LOAN_DAYS . ' days'));
        db_exec('INSERT INTO transactions (member_id, resource_id, borrow_date, due_date, processed_by) VALUES (?, ?, ?, ?, ?)',
            [$memberId, $resourceId, today(), $due, $staffId]);

        $pdo->commit();
        return [true, '"' . $res['title'] . '" was borrowed by ' . $member['full_name'] . '. Due date: ' . fmt_date($due) . '.'];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('borrow_resource: ' . $ex->getMessage());
        return [false, 'Something went wrong while saving the transaction. Nothing was changed.'];
    }
}

/** @return array [bool ok, string message] */
function return_resource(int $transactionId, int $staffId): array
{
    $pdo = db();
    try {
        $pdo->beginTransaction();
        $tx = db_one('SELECT t.id, t.resource_id, t.return_date, r.title FROM transactions t JOIN resources r ON r.id = t.resource_id WHERE t.id = ?', [$transactionId]);
        if (!$tx) {
            $pdo->rollBack();
            return [false, 'Transaction not found.'];
        }
        // Only one request can flip return_date from NULL, so a double click cannot return twice.
        $changed = db_exec('UPDATE transactions SET return_date = ?, returned_by = ? WHERE id = ? AND return_date IS NULL', [today(), $staffId, $transactionId]);
        if ($changed !== 1) {
            $pdo->rollBack();
            return [false, 'This item was already returned.'];
        }
        $back = db_exec('UPDATE resources SET available_copies = available_copies + 1 WHERE id = ? AND available_copies < total_copies', [$tx['resource_id']]);
        if ($back !== 1) {
            $pdo->rollBack();
            return [false, 'Inventory count is inconsistent for this resource. Nothing was changed; please check the book record.'];
        }
        $pdo->commit();
        return [true, '"' . $tx['title'] . '" was marked as returned.'];
    } catch (Throwable $ex) {
        if ($pdo->inTransaction()) {
            $pdo->rollBack();
        }
        error_log('return_resource: ' . $ex->getMessage());
        return [false, 'Something went wrong while saving the return. Nothing was changed.'];
    }
}
