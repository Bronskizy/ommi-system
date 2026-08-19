<?php
require_once __DIR__ . '/../includes/helpers.php';
require_role(['super_admin', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('admin/members.php');
}

verify_csrf();
$memberId = (int) ($_POST['member_id'] ?? 0);
if ($memberId <= 0) {
    flash('danger', 'Invalid member selected.');
    redirect('admin/members.php');
}

$stmt = db()->prepare('SELECT id, member_no, full_name, email, phone FROM members WHERE id = ? AND user_id IS NULL AND deleted_at IS NULL LIMIT 1');
$stmt->bind_param('i', $memberId);
$stmt->execute();
$member = $stmt->get_result()->fetch_assoc();
if (!$member) {
    flash('danger', 'This member already has an account or is unavailable.');
    redirect('admin/members.php');
}

$baseUsername = strtolower(preg_replace('/[^a-z0-9]+/i', '', $member['member_no'])) ?: 'member' . $memberId;
$username = $baseUsername;
$suffix = 2;
do {
    $stmt = db()->prepare('SELECT id FROM users WHERE username = ? LIMIT 1');
    $stmt->bind_param('s', $username);
    $stmt->execute();
    $exists = (bool) $stmt->get_result()->fetch_assoc();
    if ($exists) {
        $username = $baseUsername . $suffix++;
    }
} while ($exists);

$tempPassword = 'Temp' . random_int(100000, 999999);
$passwordHash = password_hash($tempPassword, PASSWORD_BCRYPT);
$email = trim((string) $member['email']);

try {
    $stmt = db()->prepare('INSERT INTO users (username, full_name, email, phone, password_hash, role, status, must_change_password) VALUES (?, ?, NULLIF(?, ""), ?, ?, "member", "active", 1)');
    $stmt->bind_param('sssss', $username, $member['full_name'], $email, $member['phone'], $passwordHash);
    $stmt->execute();

    $userId = db()->insert_id;
    $stmt = db()->prepare('UPDATE members SET user_id = ? WHERE id = ?');
    $stmt->bind_param('ii', $userId, $memberId);
    $stmt->execute();

    audit('create', 'member_account', $userId, 'Portal account for member #' . $memberId);
    flash('success', 'Account created for ' . $member['full_name'] . '. Username: ' . $username . '. Temporary password: ' . $tempPassword);
} catch (mysqli_sql_exception $exception) {
    flash('danger', 'Could not create account. The member email or phone may already be used by another account.');
}

redirect('admin/members.php');
