<?php
require_once __DIR__ . '/../includes/layout.php';
require_role(['super_admin', 'admin']);
verify_csrf();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash('danger', 'Invalid expense selected.');
    redirect('expenses/index.php');
}

function load_expense(int $id): ?array
{
    $stmt = db()->prepare('SELECT e.*, p.name AS project_name FROM expenses e JOIN projects p ON p.id = e.project_id WHERE e.id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

$expense = load_expense($id);
if (!$expense) {
    flash('danger', 'Expense was not found.');
    redirect('expenses/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project = (int) ($_POST['project_id'] ?? 0);
    $cat = trim($_POST['category'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);
    $date = $_POST['expense_date'] ?? date('Y-m-d');
    $vendor = trim($_POST['vendor'] ?? '');
    $desc = trim($_POST['description'] ?? '');

    if ($project <= 0 || $cat === '' || $amount <= 0) {
        flash('danger', 'Project, category, and amount are required.');
        redirect('expenses/edit-expense.php?id=' . $id);
    }

    $stmt = db()->prepare('UPDATE expenses SET project_id = ?, category = ?, amount = ?, expense_date = ?, vendor = ?, description = ? WHERE id = ?');
    $stmt->bind_param('isdsssi', $project, $cat, $amount, $date, $vendor, $desc, $id);
    $stmt->execute();

    audit('update', 'expense', $id, $cat);
    flash('success', 'Expense updated.');
    redirect('expenses/edit-expense.php?id=' . $id);
}

$expense = load_expense($id);
$projects = db()->query('SELECT id, name FROM projects ORDER BY name');
page_header('Edit Expense');
?>
<div class="panel"><p><a class="btn" href="index.php">Back to Expenses</a></p></div>
<div class="panel">
    <h2>Edit Expense</h2>
    <form class="form-grid" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $expense['id'] ?>">
        <label>Project<select name="project_id"><?php while ($p = $projects->fetch_assoc()): ?><option value="<?= (int) $p['id'] ?>" <?= (int) $p['id'] === (int) $expense['project_id'] ? 'selected' : '' ?>><?= e($p['name']) ?></option><?php endwhile; ?></select></label>
        <label>Category<input name="category" value="<?= e($expense['category']) ?>" required></label>
        <label>Amount<input type="number" step="0.01" name="amount" value="<?= e((string) $expense['amount']) ?>" required></label>
        <label>Date<input type="date" name="expense_date" value="<?= e($expense['expense_date']) ?>" required></label>
        <label>Vendor<input name="vendor" value="<?= e($expense['vendor']) ?>"></label>
        <label class="wide">Description<textarea name="description"><?= e($expense['description']) ?></textarea></label>
        <button class="btn btn-primary">Save Changes</button>
    </form>
</div>
<?php page_footer(); ?>
