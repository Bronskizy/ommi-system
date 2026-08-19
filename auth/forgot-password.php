<?php
require_once __DIR__ . '/../includes/mailer.php';
verify_csrf();

$message = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = trim($_POST['login'] ?? '');
    $stmt = db()->prepare('SELECT id, full_name, email FROM users WHERE email = ? OR phone = ? OR username = ? LIMIT 1');
    $stmt->bind_param('sss', $login, $login, $login);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc();

    if ($user && !empty($user['email'])) {
        $token = bin2hex(random_bytes(32));
        $expires = date('Y-m-d H:i:s', time() + 900);
        $stmt = db()->prepare('INSERT INTO password_resets (user_id, token, expiry_time) VALUES (?, ?, ?)');
        $stmt->bind_param('iss', $user['id'], $token, $expires);
        $stmt->execute();

        $resetLink = app_absolute_url('auth/reset-password.php?token=' . urlencode($token));
        $sent = send_password_reset_email($user['email'], $user['full_name'], $resetLink);
        audit($sent ? 'password_reset_email_sent' : 'password_reset_email_failed', 'user', (int) $user['id']);
    } elseif ($user) {
        audit('password_reset_no_email', 'user', (int) $user['id']);
    } else {
        audit('password_reset_unknown_account', 'user', null, $login);
    }

    $message = 'If that account exists and has an email address, a password reset link has been sent.';
}
?>
<!DOCTYPE html>
<html>
<head>
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Forgot Password</title>
    <link rel="stylesheet" href="<?= BASE_URL ?>/assets/css/style.css">
</head>
<body class="auth-body">
<section class="auth-card">
    <h1>Forgot Password</h1>
    <?php if ($message): ?><div class="alert alert-success"><?= e($message) ?></div><?php endif; ?>
    <form method="post">
        <?= csrf_field() ?>
        <label>Email, phone or username<input name="login" required></label>
        <button class="btn btn-primary full">Send reset link</button>
    </form>
    <div class="auth-links"><a href="../login.php">Back to login</a></div>
</section>
</body>
</html>
