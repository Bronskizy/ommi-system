<?php
require_once __DIR__ . '/../includes/layout.php';
require_role(['super_admin', 'admin']);
verify_csrf();

$id = (int) ($_GET['id'] ?? $_POST['id'] ?? 0);
if ($id <= 0) {
    flash('danger', 'Invalid project selected.');
    redirect('projects/index.php');
}

function load_project(int $id): ?array
{
    $stmt = db()->prepare('SELECT p.*, pc.name AS category FROM projects p JOIN project_categories pc ON pc.id = p.category_id WHERE p.id = ? LIMIT 1');
    $stmt->bind_param('i', $id);
    $stmt->execute();
    return $stmt->get_result()->fetch_assoc() ?: null;
}

$project = load_project($id);
if (!$project) {
    flash('danger', 'Project was not found.');
    redirect('projects/index.php');
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $name = trim($_POST['name'] ?? '');
    $cat = (int) ($_POST['category_id'] ?? 0);
    $budget = (float) ($_POST['budget'] ?? 0);
    $start = $_POST['start_date'] ?: null;
    $end = $_POST['end_date'] ?: null;
    $status = $_POST['status'] ?? 'Planned';
    $desc = trim($_POST['description'] ?? '');

    if ($name === '' || $cat <= 0) {
        flash('danger', 'Project name and category are required.');
        redirect('projects/edit-project.php?id=' . $id);
    }

    $stmt = db()->prepare('UPDATE projects SET name = ?, category_id = ?, budget = ?, start_date = ?, end_date = ?, status = ?, description = ? WHERE id = ?');
    $stmt->bind_param('sidssssi', $name, $cat, $budget, $start, $end, $status, $desc, $id);
    $stmt->execute();

    audit('update', 'project', $id, $name);
    flash('success', 'Project updated.');
    redirect('projects/edit-project.php?id=' . $id);
}

$project = load_project($id);
$cats = db()->query('SELECT * FROM project_categories ORDER BY name');
$financeStmt = db()->prepare('SELECT COALESCE((SELECT SUM(amount) FROM contributions WHERE project_id = ?), 0) income, COALESCE((SELECT SUM(amount) FROM expenses WHERE project_id = ?), 0) expense');
$financeStmt->bind_param('ii', $id, $id);
$financeStmt->execute();
$finance = $financeStmt->get_result()->fetch_assoc();
page_header('Edit Project');
?>
<div class="panel"><p><a class="btn" href="index.php">Back to Projects</a></p></div>

<section class="stats-grid">
    <div class="stat-card gold"><span>Budget</span><strong><?= money($project['budget']) ?></strong></div>
    <div class="stat-card"><span>Funding</span><strong><?= money($finance['income']) ?></strong></div>
    <div class="stat-card soil"><span>Expenses</span><strong><?= money($finance['expense']) ?></strong></div>
    <div class="stat-card"><span>Remaining</span><strong><?= money((float) $project['budget'] - (float) $finance['expense']) ?></strong></div>
</section>

<div class="panel">
    <h2>Project Details</h2>
    <form class="form-grid" method="post">
        <?= csrf_field() ?>
        <input type="hidden" name="id" value="<?= (int) $project['id'] ?>">
        <label>Project name<input name="name" value="<?= e($project['name']) ?>" required></label>
        <label>Category<select name="category_id"><?php while ($c = $cats->fetch_assoc()): ?><option value="<?= (int) $c['id'] ?>" <?= (int) $c['id'] === (int) $project['category_id'] ? 'selected' : '' ?>><?= e($c['name']) ?><?= $c['enabled'] ? '' : ' (disabled)' ?></option><?php endwhile; ?></select></label>
        <label>Budget<input type="number" step="0.01" name="budget" value="<?= e((string) $project['budget']) ?>" required></label>
        <label>Start date<input type="date" name="start_date" value="<?= e($project['start_date']) ?>"></label>
        <label>End date<input type="date" name="end_date" value="<?= e($project['end_date']) ?>"></label>
        <label>Status<select name="status"><?php foreach (['Planned','Active','Completed','Suspended'] as $status): ?><option value="<?= e($status) ?>" <?= $project['status'] === $status ? 'selected' : '' ?>><?= e($status) ?></option><?php endforeach; ?></select></label>
        <label class="wide">Description<textarea name="description"><?= e($project['description']) ?></textarea></label>
        <button class="btn btn-primary">Save Changes</button>
    </form>
</div>
<?php page_footer(); ?>
