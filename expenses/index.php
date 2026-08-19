<?php
require_once __DIR__ . '/../includes/layout.php';
require_role(['super_admin', 'admin']);
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $project = (int) ($_POST['project_id'] ?? 0);
    $cat = trim($_POST['category'] ?? '');
    $amount = (float) ($_POST['amount'] ?? 0);
    $date = $_POST['expense_date'] ?? date('Y-m-d');
    $vendor = trim($_POST['vendor'] ?? '');
    $desc = trim($_POST['description'] ?? '');
    $uid = current_user()['id'];

    if ($project <= 0 || $cat === '' || $amount <= 0) {
        flash('danger', 'Project, category, and amount are required.');
        redirect('expenses/index.php');
    }

    $stmt = db()->prepare('INSERT INTO expenses (project_id, category, amount, expense_date, vendor, description, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('isdsssi', $project, $cat, $amount, $date, $vendor, $desc, $uid);
    $stmt->execute();

    audit('create', 'expense', db()->insert_id, $cat);
    flash('success', 'Expense recorded.');
    redirect('expenses/index.php');
}

$projects = db()->query('SELECT id, name FROM projects ORDER BY name');
$rows = db()->query('SELECT e.*, p.name project_name FROM expenses e JOIN projects p ON p.id = e.project_id ORDER BY e.expense_date DESC');
page_header('Expense Management');
?>
<style>
.expenses-page>.panel{padding:0;overflow:hidden;border-radius:10px}.expenses-page>.panel>h2{margin:0;padding:18px 21px;border-bottom:1px solid var(--line);font-size:17px}.expenses-page>.panel>h2:after{display:block;margin-top:5px;color:var(--muted);font-size:12px;font-weight:normal}.expenses-page>.panel:first-child>h2:after{content:'Record project expenses with a category, vendor and supporting description.'}.expenses-page>.panel:nth-child(2)>h2:after{content:'Review all recorded project expenses.'}.expenses-page>.panel>form{padding:20px 21px}.expenses-page>.panel>.table-wrap{border:0;border-radius:0}.expenses-page table th{background:#f4f8f5}.expenses-page .form-grid .btn{height:40px;align-self:end}@media(max-width:800px){.expenses-page .form-grid{grid-template-columns:1fr}.expenses-page .form-grid .wide{grid-column:auto}}
</style>
<div class="expenses-page"><div class="panel">
    <h2>Record Expense</h2>
    <form class="form-grid" method="post">
        <?= csrf_field() ?>
        <label>Project<select name="project_id"><?php while ($p = $projects->fetch_assoc()): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option><?php endwhile; ?></select></label>
        <label>Category<input name="category" placeholder="feed, seeds, labor" required></label>
        <label>Amount<input type="number" step="0.01" name="amount" required></label>
        <label>Date<input type="date" name="expense_date" value="<?= date('Y-m-d') ?>" required></label>
        <label>Vendor<input name="vendor"></label>
        <label class="wide">Description<textarea name="description"></textarea></label>
        <button class="btn btn-primary">Save Expense</button>
    </form>
</div>

<div class="panel">
    <h2>Expenses</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Project</th><th>Category</th><th>Vendor</th><th>Amount</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php while ($e = $rows->fetch_assoc()): ?>
                <tr>
                    <td><?= e($e['project_name']) ?></td>
                    <td><?= e($e['category']) ?></td>
                    <td><?= e($e['vendor']) ?></td>
                    <td><?= money($e['amount']) ?></td>
                    <td><?= e($e['expense_date']) ?></td>
                    <td><a class="btn btn-sm" href="edit-expense.php?id=<?= (int) $e['id'] ?>">Edit</a></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div></div>
<?php page_footer(); ?>
