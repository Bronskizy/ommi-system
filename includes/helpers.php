<?php
require_once __DIR__ . '/auth.php';
function create_notification(?int $userId, string $title, string $body, string $level = 'info'): void { $stmt = db()->prepare('INSERT INTO notifications (user_id,title,body,level) VALUES (?,?,?,?)'); $stmt->bind_param('isss',$userId,$title,$body,$level); $stmt->execute(); }
function next_receipt_no(): string {
    $year = date('Y');
    $prefix = str_replace('{YYYY}', $year, setting('receipt_prefix', 'RCPT-{YYYY}-'));
    $like = $prefix . '%';
    $suffixStart = strlen($prefix) + 1;

    // Use the highest receipt sequence, not the number of rows. Counting rows
    // can reuse an existing receipt number after a contribution is deleted.
    $stmt = db()->prepare('SELECT COALESCE(MAX(CAST(SUBSTRING(receipt_no, ?) AS UNSIGNED)), 0) AS last_number FROM contributions WHERE receipt_no LIKE ?');
    $stmt->bind_param('is', $suffixStart, $like);
    $stmt->execute();
    $lastNumber = (int) $stmt->get_result()->fetch_assoc()['last_number'];

    return $prefix . str_pad((string) ($lastNumber + 1), 4, '0', STR_PAD_LEFT);
}
function password_policy_error(string $password): ?string { $min=(int)setting('password_min_length',8); return strlen($password) < $min ? "Password must be at least {$min} characters." : null; }

function duplicate_contribution_message(int $memberId, ?int $projectId, string $type, float $amount, string $paymentDate, ?int $excludeId = null): ?string
{
    if ($type === 'entry') {
        $sql = 'SELECT id FROM contributions WHERE member_id = ? AND contribution_type = "entry"';
        $params = [$memberId];
        $types = 'i';
        $message = 'This member already has an entry contribution.';
    } elseif ($type === 'monthly') {
        $sql = 'SELECT id FROM contributions WHERE member_id = ? AND contribution_type = "monthly" AND DATE_FORMAT(payment_date, "%Y-%m") = DATE_FORMAT(?, "%Y-%m")';
        $params = [$memberId, $paymentDate];
        $types = 'is';
        $message = 'This member already has a monthly contribution for this month.';
    } else {
        $sql = 'SELECT id FROM contributions WHERE member_id = ? AND contribution_type = ? AND amount = ? AND payment_date = ?';
        $params = [$memberId, $type, $amount, $paymentDate];
        $types = 'isds';
        $message = 'A matching contribution already exists for this member.';

        if ($projectId === null) {
            $sql .= ' AND project_id IS NULL';
        } else {
            $sql .= ' AND project_id = ?';
            $params[] = $projectId;
            $types .= 'i';
        }
    }

    if ($excludeId !== null) {
        $sql .= ' AND id <> ?';
        $params[] = $excludeId;
        $types .= 'i';
    }

    $sql .= ' LIMIT 1';
    $stmt = db()->prepare($sql);
    $stmt->bind_param($types, ...$params);
    $stmt->execute();

    return $stmt->get_result()->fetch_assoc() ? $message : null;
}
