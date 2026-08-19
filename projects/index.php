<?php
require_once __DIR__ . '/../includes/layout.php';
require_role(['super_admin', 'admin']);
verify_csrf();

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $cat = (int) ($_POST['category_id'] ?? 0);
    $budget = (float) ($_POST['budget'] ?? 0);
    $start = $_POST['start_date'] ?: null;
    $end = $_POST['end_date'] ?: null;
    $status = $_POST['status'] ?? 'Planned';
    $desc = trim($_POST['description'] ?? '');
    $uid = current_user()['id'];

    if ($name === '' || $cat <= 0) {
        flash('danger', 'Project name and category are required.');
        redirect('projects/index.php');
    }

    $stmt = db()->prepare('INSERT INTO projects (name, category_id, budget, start_date, end_date, status, description, created_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('sidssssi', $name, $cat, $budget, $start, $end, $status, $desc, $uid);
    $stmt->execute();

    audit('create', 'project', db()->insert_id, $name);
    flash('success', 'Project saved.');
    redirect('projects/index.php');
}

$cats = db()->query('SELECT * FROM project_categories WHERE enabled = 1 ORDER BY name');
$rows = db()->query('SELECT p.*, pc.name category, COALESCE(c.income,0) income, COALESCE(e.expense,0) expense FROM projects p JOIN project_categories pc ON pc.id = p.category_id LEFT JOIN (SELECT project_id, SUM(amount) income FROM contributions GROUP BY project_id) c ON c.project_id = p.id LEFT JOIN (SELECT project_id, SUM(amount) expense FROM expenses GROUP BY project_id) e ON e.project_id = p.id ORDER BY p.created_at DESC');
page_header('Project Management');
?>
<style>
.projects-page>.panel{padding:0;overflow:hidden;border-radius:10px}.projects-page>.panel>h2{margin:0;padding:18px 21px;border-bottom:1px solid var(--line);font-size:17px}.projects-page>.panel>h2:after{display:block;margin-top:5px;color:var(--muted);font-size:12px;font-weight:normal}.projects-page>.panel:first-child>h2:after{content:'Set up a project with its budget, schedule and category.'}.projects-page>.panel:nth-child(2)>h2:after{content:'Review funding, expenses and the remaining budget for each project.'}.projects-page>.panel>form{padding:20px 21px}.projects-page>.panel>.table-wrap{border:0;border-radius:0}.projects-page table th{background:#f4f8f5}.projects-page .form-grid .btn{height:40px;align-self:end}@media(max-width:800px){.projects-page .form-grid{grid-template-columns:1fr}.projects-page .form-grid .wide{grid-column:auto}}
</style>
<div class="projects-page"><div class="panel">
    <h2>Create Dynamic Project</h2>
    <form class="form-grid" method="post">
        <?= csrf_field() ?>
        <label>Project name<input name="name" required></label>
        <label>Category<select name="category_id"><?php while ($c = $cats->fetch_assoc()): ?><option value="<?= (int) $c['id'] ?>"><?= e($c['name']) ?></option><?php endwhile; ?></select></label>
        <label>Budget<input type="number" step="0.01" name="budget" required></label>
        <label>Start date<input type="date" name="start_date"></label>
        <label>End date<input type="date" name="end_date"></label>
        <label>Status<select name="status"><option>Planned</option><option>Active</option><option>Completed</option><option>Suspended</option></select></label>
        <label class="wide">Description<textarea name="description"></textarea></label>
        <button class="btn btn-primary">Save Project</button>
    </form>
</div>

<div class="panel">
    <h2>Project Finance</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Project</th><th>Category</th><th>Status</th><th>Budget</th><th>Funding</th><th>Expenses</th><th>Remaining</th><th></th></tr></thead>
            <tbody>
            <?php while ($p = $rows->fetch_assoc()): $remaining = (float) $p['budget'] - (float) $p['expense']; ?>
                <tr>
                    <td><?= e($p['name']) ?></td>
                    <td><?= e($p['category']) ?></td>
                    <td><span class="badge"><?= e($p['status']) ?></span></td>
                    <td><?= money($p['budget']) ?></td>
                    <td><?= money($p['income']) ?></td>
                    <td><?= money($p['expense']) ?></td>
                    <td><?= money($remaining) ?></td>
                    <td><a class="btn btn-sm" href="edit-project.php?id=<?= (int) $p['id'] ?>">Edit</a></td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div></div>
<?php page_footer(); ?>
