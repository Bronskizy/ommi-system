<?php
require_once __DIR__ . '/../includes/helpers.php';
require_role(['super_admin', 'admin']);

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    redirect('contributions/index.php');
}

verify_csrf();
$id = (int) ($_POST['id'] ?? 0);
if ($id <= 0) {
    flash('danger', 'Invalid contribution selected.');
    redirect('contributions/index.php');
}

$stmt = db()->prepare('SELECT receipt_no FROM contributions WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $id);
$stmt->execute();
$contribution = $stmt->get_result()->fetch_assoc();

if (!$contribution) {
    flash('danger', 'Contribution was not found.');
    redirect('contributions/index.php');
}

$stmt = db()->prepare('DELETE FROM contributions WHERE id = ?');
$stmt->bind_param('i', $id);
$stmt->execute();

audit('delete', 'contribution', $id, 'Receipt ' . $contribution['receipt_no']);
flash('success', 'Contribution deleted: ' . $contribution['receipt_no']);
redirect('contributions/index.php');
