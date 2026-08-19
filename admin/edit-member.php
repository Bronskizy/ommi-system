<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_role(['super_admin', 'admin']);
verify_csrf();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash('danger', 'Invalid member selected.');
    redirect('admin/members.php');
}

function load_member(int $id): ?array
{
    $stmt = db()->prepare('SELECT m.*, u.username, u.full_name AS account_name, u.email AS account_email, u.phone AS account_phone, u.status AS account_status FROM members m LEFT JOIN users u ON u.id = m.user_id WHERE m.id = ? AND m.deleted_at IS NULL LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

$member = load_member($id);
if (!$member) {
    flash('danger', 'Member was not found.');
    redirect('admin/members.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? 'update_member';

    if ($action === 'update_member') {
        $memberNo = trim($_POST['member_no'] ?? '');
        $name = trim($_POST['full_name'] ?? '');
        $phone = trim($_POST['phone'] ?? '');
        $email = trim($_POST['email'] ?? '');
        $joinDate = $_POST['join_date'] ?: date('Y-m-d');
        $status = $_POST['status'] === 'inactive' ? 'inactive' : 'active';

        if ($memberNo === '' || $name === '' || $phone === '') {
            flash('danger', 'Member number, name, and phone are required.');
            redirect('admin/edit-member.php?id=' . $id);
        }

        $stmt = db()->prepare('UPDATE members SET member_no = ?, full_name = ?, phone = ?, email = ?, join_date = ?, status = ? WHERE id = ?');
        $stmt->bind_param('ssssssi', $memberNo, $name, $phone, $email, $joinDate, $status, $id);
        if (!$stmt->execute()) {
            flash('danger', 'Could not update member. Member number may already exist.');
            redirect('admin/edit-member.php?id=' . $id);
        }

        if (!empty($member['user_id'])) {
            $accountStatus = $status === 'active' ? 'active' : 'inactive';
            $stmt = db()->prepare('UPDATE users SET full_name = ?, email = NULLIF(?, ""), phone = ?, status = ? WHERE id = ?');
            $stmt->bind_param('ssssi', $name, $email, $phone, $accountStatus, $member['user_id']);
            $stmt->execute();
        }

        audit('update', 'member', $id, $name);
        flash('success', 'Member details updated.');
        redirect('admin/edit-member.php?id=' . $id);
    }

    if ($action === 'update_account' && !empty($member['user_id'])) {
        $username = trim($_POST['username'] ?? '');
        $accountStatus = in_array($_POST['account_status'] ?? '', ['active', 'suspended', 'inactive'], true) ? $_POST['account_status'] : 'active';

        if ($username === '') {
            flash('danger', 'Username is required.');
            redirect('admin/edit-member.php?id=' . $id);
        }

        $stmt = db()->prepare('UPDATE users SET username = ?, status = ? WHERE id = ?');
        $stmt->bind_param('ssi', $username, $accountStatus, $member['user_id']);
        if (!$stmt->execute()) {
            flash('danger', 'Could not update account. Username may already exist.');
            redirect('admin/edit-member.php?id=' . $id);
        }

        audit('update', 'member_account', (int) $member['user_id'], $username);
        flash('success', 'Member portal account updated.');
        redirect('admin/edit-member.php?id=' . $id);
    }

    if ($action === 'create_account' && empty($member['user_id'])) {
        $username = trim($_POST['username'] ?? '');
        if ($username === '') {
            $username = strtolower(preg_replace('/[^a-z0-9]+/i', '', $member['member_no']));
        }
        $tempPassword = 'Temp' . random_int(100000, 999999);
        $hash = password_hash($tempPassword, PASSWORD_BCRYPT);
        $email = trim((string) $member['email']);

        $stmt = db()->prepare('INSERT INTO users (username, full_name, email, phone, password_hash, role, status, must_change_password) VALUES (?, ?, NULLIF(?, ""), ?, ?, "member", "active", 1)');
        $stmt->bind_param('sssss', $username, $member['full_name'], $email, $member['phone'], $hash);
        if (!$stmt->execute()) {
            flash('danger', 'Could not create portal account. Username, email, or phone may already exist.');
            redirect('admin/edit-member.php?id=' . $id);
        }

        $userId = db()->insert_id;
        $stmt = db()->prepare('UPDATE members SET user_id = ? WHERE id = ?');
        $stmt->bind_param('ii', $userId, $id);
        $stmt->execute();

        audit('create', 'member_account', $userId, 'Portal account for member #' . $id);
        flash('success', 'Portal account created. Temporary password: ' . $tempPassword);
        redirect('admin/edit-member.php?id=' . $id);
    }
}

$member = load_member($id);
page_header('Edit Member');
?>
<div class="panel">
    <p><a class="btn" href="members.php">Back to Members</a></p>
</div>

<div class="panel">
    <h2>Member Details</h2>
    <form class="form-grid" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $member['id'] ?>">
        <input type="hidden" name="action" value="update_member">
        <label>Member number<input name="member_no" value="<?= e($member['member_no']) ?>" required></label>
        <label>Full name<input name="full_name" value="<?= e($member['full_name']) ?>" required></label>
        <label>Phone<input name="phone" value="<?= e($member['phone']) ?>" required></label>
        <label>Email<input type="email" name="email" value="<?= e($member['email']) ?>"></label>
        <label>Join date<input type="date" name="join_date" value="<?= e($member['join_date']) ?>"></label>
        <label>Status<select name="status"><option value="active" <?= $member['status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="inactive" <?= $member['status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></label>
        <button class="btn btn-primary">Save Changes</button>
    </form>
</div>

<div class="panel">
    <h2>Member Portal Account</h2>
    <?php if (!empty($member['user_id'])): ?>
        <form class="form-grid" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $member['id'] ?>">
            <input type="hidden" name="action" value="update_account">
            <label>Username<input name="username" value="<?= e($member['username']) ?>" required></label>
            <label>Account status<select name="account_status"><option value="active" <?= $member['account_status'] === 'active' ? 'selected' : '' ?>>Active</option><option value="suspended" <?= $member['account_status'] === 'suspended' ? 'selected' : '' ?>>Suspended</option><option value="inactive" <?= $member['account_status'] === 'inactive' ? 'selected' : '' ?>>Inactive</option></select></label>
            <button class="btn btn-primary">Update Account</button>
        </form>
        <p class="muted">Name, email, and phone are synced from the member details above.</p>
    <?php else: ?>
        <p class="muted">This member does not have a self-service login account yet.</p>
        <form class="form-grid" method="post">
            <?= csrf_field() ?>
            <input type="hidden" name="id" value="<?= (int) $member['id'] ?>">
            <input type="hidden" name="action" value="create_account">
            <label>Username<input name="username" value="<?= e(strtolower(preg_replace('/[^a-z0-9]+/i', '', $member['member_no']))) ?>"></label>
            <button class="btn btn-primary">Create Portal Account</button>
        </form>
    <?php endif; ?>
</div>
<?php page_footer(); ?>
