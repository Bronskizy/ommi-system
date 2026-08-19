<?php
require_once __DIR__ . '/../config/config.php';

function current_user(): ?array {
    if (empty($_SESSION['user_id'])) return null;
    static $user = null;
    if ($user !== null) return $user;
    $stmt = db()->prepare('SELECT id, username, full_name, email, phone, role, status, must_change_password FROM users WHERE id = ? LIMIT 1');
    $stmt->bind_param('i', $_SESSION['user_id']);
    $stmt->execute();
    $user = $stmt->get_result()->fetch_assoc() ?: null;
    return $user;
}
function require_login(): void {
    if (!current_user()) redirect('login.php');
    $timeout = (int) setting('session_timeout_minutes', 60);
    if (!empty($_SESSION['last_activity']) && time() - $_SESSION['last_activity'] > $timeout * 60) { session_destroy(); redirect('login.php'); }
    $_SESSION['last_activity'] = time();
}
function require_role(array $roles): void { require_login(); $role = current_user()['role'] ?? ''; if (!in_array($role, $roles, true)) { http_response_code(403); die('Access denied.'); } }
function is_admin(): bool { return in_array(current_user()['role'] ?? '', ['super_admin','admin'], true); }
function dashboard_for(string $role): string { return $role === 'member' ? 'member/dashboard.php' : 'dashboard.php'; }
