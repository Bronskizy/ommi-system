<?php
require_once __DIR__ . '/../includes/layout.php';
require_role(['member']);
verify_csrf();

$user = current_user();
$userId = (int) $user['id'];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $username = trim($_POST['username'] ?? '');
    $email = trim($_POST['email'] ?? '');
    $phone = trim($_POST['phone'] ?? '');

    if ($username === '' || $phone === '') {
        flash('danger', 'Username and phone number are required.');
        redirect('member/account-settings.php');
    }
    if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        flash('danger', 'Enter a valid email address.');
        redirect('member/account-settings.php');
    }

    try {
        db()->begin_transaction();
        $stmt = db()->prepare('UPDATE users SET username = ?, email = NULLIF(?, ""), phone = ? WHERE id = ?');
        $stmt->bind_param('sssi', $username, $email, $phone, $userId);
        $stmt->execute();

        $stmt = db()->prepare('UPDATE members SET email = NULLIF(?, ""), phone = ? WHERE user_id = ?');
        $stmt->bind_param('ssi', $email, $phone, $userId);
        $stmt->execute();
        db()->commit();

        audit('update', 'member_account', $userId, 'Member updated account contact details.');
        flash('success', 'Your account details have been updated.');
    } catch (mysqli_sql_exception $exception) {
        db()->rollback();
        flash('danger', 'Could not update your account. The username, email, or phone number may already be in use.');
    }

    redirect('member/account-settings.php');
}

$stmt = db()->prepare('SELECT username, email, phone FROM users WHERE id = ? LIMIT 1');
$stmt->bind_param('i', $userId);
$stmt->execute();
$account = $stmt->get_result()->fetch_assoc() ?: $user;

page_header('My Account Settings');
?>
<style>.account-page>.panel{padding:0;overflow:hidden;border-radius:10px}.account-page .account-head{padding:20px 21px;border-bottom:1px solid var(--line)}.account-page .account-head h2{margin:0 0 6px;font-size:18px}.account-page .account-head p{margin:0;color:var(--muted)}.account-page form{padding:20px 21px}.account-page .form-grid .btn{height:40px;align-self:end}@media(max-width:800px){.account-page .form-grid{grid-template-columns:1fr}}</style>
<div class="account-page"><div class="panel">
    <div class="account-head"><h2>Login and contact details</h2>
    <p>Update the username, email address, and phone number used for your member account.</p></div>
    <form class="form-grid" method="post">
        <?= csrf_field() ?>
        <label>Username<input name="username" value="<?= e($account['username']) ?>" required></label>
        <label>Email<input type="email" name="email" value="<?= e($account['email'] ?? '') ?>"></label>
        <label>Phone number<input name="phone" value="<?= e($account['phone'] ?? '') ?>" required></label>
        <button class="btn btn-primary" type="submit">Save Changes</button>
    </form>
</div></div>
<?php page_footer(); ?>
