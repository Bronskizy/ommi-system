<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_role(['super_admin', 'admin']);
verify_csrf();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash('danger', 'Invalid contribution selected.');
    redirect('contributions/index.php');
}

function load_contribution(int $id): ?array
{
    $stmt = db()->prepare('SELECT c.*, m.full_name, p.name AS project_name FROM contributions c JOIN members m ON m.id = c.member_id LEFT JOIN projects p ON p.id = c.project_id WHERE c.id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

$contribution = load_contribution($id);
if (!$contribution) {
    flash('danger', 'Contribution was not found.');
    redirect('contributions/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $member = (int) ($_POST['member_id'] ?? 0);
    $project = !empty($_POST['project_id']) ? (int) $_POST['project_id'] : null;
    $type = $_POST['contribution_type'] ?? 'monthly';
    $amount = (float) ($_POST['amount'] ?? 0);
    $date = $_POST['payment_date'] ?? date('Y-m-d');
    $method = trim($_POST['payment_method'] ?? 'cash');
    $notes = trim($_POST['notes'] ?? '');

    if ($member <= 0 || $amount <= 0) {
        flash('danger', 'Member and amount are required.');
        redirect('contributions/edit-contribution.php?id=' . $id);
    }

    $duplicateMessage = duplicate_contribution_message($member, $project, $type, $amount, $date, $id);
    if ($duplicateMessage !== null) {
        flash('danger', $duplicateMessage);
        redirect('contributions/edit-contribution.php?id=' . $id);
    }

    $stmt = db()->prepare('UPDATE contributions SET member_id = ?, project_id = ?, contribution_type = ?, amount = ?, payment_date = ?, payment_method = ?, notes = ? WHERE id = ?');
    $stmt->bind_param('iisdsssi', $member, $project, $type, $amount, $date, $method, $notes, $id);
    $stmt->execute();

    audit('update', 'contribution', $id, 'Receipt ' . $contribution['receipt_no']);
    flash('success', 'Contribution updated: ' . $contribution['receipt_no']);
    redirect('contributions/edit-contribution.php?id=' . $id);
}

$contribution = load_contribution($id);
$members = db()->query('SELECT id, full_name FROM members WHERE deleted_at IS NULL ORDER BY full_name');
$projects = db()->query('SELECT id, name FROM projects ORDER BY name');
page_header('Edit Contribution');
?>
<div class="panel">
    <p><a class="btn" href="index.php">Back to Contributions</a></p>
</div>

<div class="panel">
    <h2>Receipt <?= e($contribution['receipt_no']) ?></h2>
    <form class="form-grid" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $contribution['id'] ?>">
        <label>Member
            <select name="member_id" required>
                <?php while ($m = $members->fetch_assoc()): ?>
                    <option value="<?= (int) $m['id'] ?>" <?= (int) $m['id'] === (int) $contribution['member_id'] ? 'selected' : '' ?>><?= e($m['full_name']) ?></option>
                <?php endwhile; ?>
            </select>
        </label>
        <label>Project
            <select name="project_id">
                <option value="">General fund</option>
                <?php while ($p = $projects->fetch_assoc()): ?>
                    <option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === (int) ($contribution['project_id'] ?? 0) ? 'selected' : '' ?>><?= e($p['name']) ?></option>
                <?php endwhile; ?>
            </select>
        </label>
        <label>Type
            <select name="contribution_type">
                <?php foreach (['entry' => 'Entry', 'monthly' => 'Monthly', 'project' => 'Project', 'other' => 'Other'] as $value => $label): ?>
                    <option value="<?= e($value) ?>" <?= $contribution['contribution_type'] === $value ? 'selected' : '' ?>><?= e($label) ?></option>
                <?php endforeach; ?>
            </select>
        </label>
        <label>Amount<input type="number" step="0.01" name="amount" value="<?= e((string) $contribution['amount']) ?>" required></label>
        <label>Date<input type="date" name="payment_date" value="<?= e($contribution['payment_date']) ?>" required></label>
        <label>Method<input name="payment_method" value="<?= e($contribution['payment_method']) ?>" required></label>
        <label class="wide">Notes<textarea name="notes"><?= e($contribution['notes']) ?></textarea></label>
        <button class="btn btn-primary">Save Changes</button>
    </form>
    <p class="muted">Receipt number is kept unchanged for audit consistency.</p>
</div>
<?php page_footer(); ?>
