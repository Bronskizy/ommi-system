<?php
require_once __DIR__ . '/../includes/helpers.php';
require_once __DIR__ . '/../includes/layout.php';
require_role(['super_admin', 'admin']);
verify_csrf();

function monthly_due_months(int $memberId, float $monthlyAmount, DateTimeImmutable $startMonth, DateTimeImmutable $endMonth): array
{
    if ($monthlyAmount <= 0 || $startMonth > $endMonth) {
        return ['months' => [], 'total' => 0.0];
    }

    $stmt = db()->prepare('SELECT DATE_FORMAT(payment_date, "%Y-%m") AS paid_month, COALESCE(SUM(amount), 0) AS total FROM contributions WHERE member_id = ? AND contribution_type = "monthly" GROUP BY paid_month');
    $stmt->bind_param('i', $memberId);
    $stmt->execute();
    $payments = [];
    $result = $stmt->get_result();
    while ($row = $result->fetch_assoc()) {
        $payments[$row['paid_month']] = (float) $row['total'];
    }

    $months = [];
    $total = 0.0;
    for ($month = $startMonth; $month <= $endMonth; $month = $month->modify('+1 month')) {
        $key = $month->format('Y-m');
        $paid = $payments[$key] ?? 0.0;
        $due = max($monthlyAmount - $paid, 0.0);
        if ($due > 0) {
            $months[] = [
                'label' => $month->format('F Y'),
                'amount' => $due,
            ];
            $total += $due;
        }
    }

    return ['months' => $months, 'total' => $total];
}

function whatsapp_phone(string $phone): string
{
    $digits = preg_replace('/\D+/', '', $phone);
    if ($digits === '') {
        return '';
    }
    if (str_starts_with($digits, '0')) {
        return '255' . substr($digits, 1);
    }
    return $digits;
}

function monthly_whatsapp_message(string $memberName, array $months, float $total): string
{
    $lines = [];
    foreach ($months as $month) {
        $lines[] = '- ' . $month['label'] . ': ' . money($month['amount']);
    }

    return "Hello {$memberName},\nReminder from OMMI Company Ltd.\n\nYou have outstanding Monthly Contributions for:\n" . implode("\n", $lines) . "\n\nTotal amount due: " . money($total) . "\n\nPlease make your payment soon.\nThank you, OMM Company Ltd.";
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
        redirect('contributions/index.php');
    }

    $duplicateMessage = duplicate_contribution_message($member, $project, $type, $amount, $date);
    if ($duplicateMessage !== null) {
        flash('danger', $duplicateMessage);
        redirect('contributions/index.php');
    }

    $receipt = next_receipt_no();
    $uid = current_user()['id'];
    $stmt = db()->prepare('INSERT INTO contributions (member_id, project_id, contribution_type, amount, payment_date, payment_method, receipt_no, notes, recorded_by) VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?)');
    $stmt->bind_param('iisdssssi', $member, $project, $type, $amount, $date, $method, $receipt, $notes, $uid);
    $stmt->execute();

    audit('create', 'contribution', db()->insert_id, $receipt);
    flash('success', 'Contribution recorded: ' . $receipt);
    redirect('contributions/index.php');
}

$members = db()->query('SELECT id, full_name FROM members WHERE deleted_at IS NULL ORDER BY full_name');
$projects = db()->query('SELECT id, name FROM projects ORDER BY name');
$search = trim($_GET['search'] ?? '');
$filterType = $_GET['type'] ?? '';
$dateFrom = $_GET['date_from'] ?? '';
$dateTo = $_GET['date_to'] ?? '';
$allowedTypes = ['entry', 'monthly', 'project', 'other'];
if (!in_array($filterType, $allowedTypes, true)) {
    $filterType = '';
}

$sql = 'SELECT c.*, m.full_name, p.name project_name FROM contributions c JOIN members m ON m.id = c.member_id LEFT JOIN projects p ON p.id = c.project_id WHERE 1 = 1';
$params = [];
$types = '';
if ($search !== '') {
    $sql .= ' AND (m.full_name LIKE ? OR c.receipt_no LIKE ?)';
    $term = '%' . $search . '%';
    $params[] = $term;
    $params[] = $term;
    $types .= 'ss';
}
if ($filterType !== '') {
    $sql .= ' AND c.contribution_type = ?';
    $params[] = $filterType;
    $types .= 's';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateFrom)) {
    $sql .= ' AND c.payment_date >= ?';
    $params[] = $dateFrom;
    $types .= 's';
} else {
    $dateFrom = '';
}
if (preg_match('/^\d{4}-\d{2}-\d{2}$/', $dateTo)) {
    $sql .= ' AND c.payment_date <= ?';
    $params[] = $dateTo;
    $types .= 's';
} else {
    $dateTo = '';
}
$sql .= ' ORDER BY c.payment_date DESC, c.id DESC';
$stmt = db()->prepare($sql);
if ($params) {
    $stmt->bind_param($types, ...$params);
}
$stmt->execute();
$rows = $stmt->get_result();

$monthlyAmount = (float) setting('monthly_contribution_amount', 30000);
$startDate = setting('contribution_start_date', date('Y-m-01'));
$startMonth = (new DateTimeImmutable($startDate))->modify('first day of this month');
$endMonth = (new DateTimeImmutable('today'))->modify('first day of this month');
$alertMembers = db()->query('SELECT id, full_name, phone FROM members WHERE deleted_at IS NULL AND status = "active" ORDER BY full_name');
$monthlyAlerts = [];
while ($memberRow = $alertMembers->fetch_assoc()) {
    $due = monthly_due_months((int) $memberRow['id'], $monthlyAmount, $startMonth, $endMonth);
    if ($due['total'] <= 0) {
        continue;
    }

    $phone = whatsapp_phone($memberRow['phone']);
    $message = monthly_whatsapp_message($memberRow['full_name'], $due['months'], $due['total']);
    $monthlyAlerts[] = [
        'member' => $memberRow,
        'months' => $due['months'],
        'total' => $due['total'],
        'phone' => $phone,
        'url' => $phone !== '' ? 'https://wa.me/' . $phone . '?text=' . rawurlencode($message) : '',
    ];
}

page_header('Contribution Management');
?>
<style>
.contribution-page{--cgreen:#1c704a;--cline:#dfe8e2;--cmuted:#6e7b73}.contribution-page>.panel{border:1px solid var(--cline);border-radius:10px;overflow:hidden;padding:0}.contribution-page>.panel>h2{padding:18px 21px;margin:0;border-bottom:1px solid var(--cline);font-size:17px}.contribution-page>.panel>form{padding:20px 21px}.contribution-page>.panel>.table-wrap{margin:0;border:0;border-radius:0}.contribution-page>.panel>.notice{margin:20px}.contribution-page>.panel:nth-child(1)>h2:after{content:'Record a new payment and generate its receipt.';display:block;margin-top:5px;color:var(--cmuted);font-size:12px;font-weight:normal}.contribution-page>.panel:nth-child(2)>h2:after{content:'Send payment reminders to members with outstanding monthly contributions.';display:block;margin-top:5px;color:var(--cmuted);font-size:12px;font-weight:normal}.contribution-page>.panel:nth-child(3)>h2:after{content:'Search, review and manage all payment records.';display:block;margin-top:5px;color:var(--cmuted);font-size:12px;font-weight:normal}.contribution-page .form-grid{padding:20px 21px;grid-template-columns:repeat(3,1fr)}.contribution-page .form-grid .btn{height:40px;align-self:end}.contribution-page .form-grid .wide{grid-column:span 2}.contribution-page table th{background:#f4f8f5}.contribution-page table td{vertical-align:middle}.contribution-page .btn-danger{background:#b93636;border-color:#b93636}@media(max-width:800px){.contribution-page .form-grid{grid-template-columns:1fr}.contribution-page .form-grid .wide{grid-column:auto}}@media(max-width:500px){.contribution-page>.panel>h2{padding:16px}.contribution-page>.panel>form,.contribution-page .form-grid{padding:16px}}
</style>
<div class="contribution-page"><div class="panel">
    <h2>Record Payment</h2>
    <form class="form-grid" method="post">
        <?= csrf_field() ?>
        <label>Member<select name="member_id" required><?php while ($m = $members->fetch_assoc()): ?><option value="<?= (int) $m['id'] ?>"><?= e($m['full_name']) ?></option><?php endwhile; ?></select></label>
        <label>Project<select name="project_id"><option value="">General fund</option><?php while ($p = $projects->fetch_assoc()): ?><option value="<?= (int) $p['id'] ?>"><?= e($p['name']) ?></option><?php endwhile; ?></select></label>
        <label>Type<select name="contribution_type"><option value="entry">Entry</option><option value="monthly">Monthly</option><option value="project">Project</option><option value="other">Other</option></select></label>
        <label>Amount<input type="number" step="0.01" name="amount" required></label>
        <label>Date<input type="date" name="payment_date" value="<?= date('Y-m-d') ?>" required></label>
        <label>Method<input name="payment_method" value="cash" required></label>
        <label class="wide">Notes<textarea name="notes"></textarea></label>
        <button class="btn btn-primary">Generate Receipt</button>
    </form>
</div>

<div class="panel">
    <h2>WhatsApp Monthly Alerts</h2>
    <?php if (!$monthlyAlerts): ?>
        <div class="notice">No outstanding monthly contributions found.</div>
    <?php else: ?>
        <div class="table-wrap">
            <table>
                <thead><tr><th>Member</th><th>Phone</th><th>Outstanding Months</th><th>Total Due</th><th></th></tr></thead>
                <tbody>
                <?php foreach ($monthlyAlerts as $alert): ?>
                    <tr>
                        <td><?= e($alert['member']['full_name']) ?></td>
                        <td><?= e($alert['member']['phone']) ?></td>
                        <td><?= e(implode(', ', array_column($alert['months'], 'label'))) ?></td>
                        <td><?= money($alert['total']) ?></td>
                        <td>
                            <?php if ($alert['url']): ?>
                                <a class="btn btn-primary btn-sm" target="_blank" rel="noopener" href="<?= e($alert['url']) ?>">Send WhatsApp</a>
                            <?php else: ?>
                                <span class="muted">No phone</span>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    <?php endif; ?>
</div>

<div class="panel">
    <h2>Contribution History</h2>
    <form class="form-grid" method="get">
        <label>Search member or receipt<input name="search" value="<?= e($search) ?>" placeholder="Member name or receipt number"></label>
        <label>Contribution type<select name="type"><option value="">All types</option><?php foreach ($allowedTypes as $type): ?><option value="<?= e($type) ?>" <?= $filterType === $type ? 'selected' : '' ?>><?= e(ucfirst($type)) ?></option><?php endforeach; ?></select></label>
        <label>From date<input type="date" name="date_from" value="<?= e($dateFrom) ?>"></label>
        <label>To date<input type="date" name="date_to" value="<?= e($dateTo) ?>"></label>
        <div><button class="btn btn-primary" type="submit">Search</button> <a class="btn" href="index.php">Clear</a></div>
    </form>
    <div class="table-wrap">
        <table>
            <thead><tr><th>Receipt</th><th>Member</th><th>Type</th><th>Project</th><th>Amount</th><th>Date</th><th></th></tr></thead>
            <tbody>
            <?php while ($r = $rows->fetch_assoc()): ?>
                <tr>
                    <td><?= e($r['receipt_no']) ?></td>
                    <td><?= e($r['full_name']) ?></td>
                    <td><?= e($r['contribution_type']) ?></td>
                    <td><?= e($r['project_name'] ?? 'General') ?></td>
                    <td><?= money($r['amount']) ?></td>
                    <td><?= e($r['payment_date']) ?></td>
                    <td>
                        <a class="btn btn-sm" href="edit-contribution.php?id=<?= (int) $r['id'] ?>">Edit</a>
                        <form method="post" action="delete-contribution.php" style="display:inline-block;margin-left:6px" onsubmit="return confirm('Delete contribution <?= e($r['receipt_no']) ?>? This cannot be undone.');">
                            <?= csrf_field() ?>
                            <input type="hidden" name="id" value="<?= (int) $r['id'] ?>">
                            <button class="btn btn-sm btn-danger" type="submit">Delete</button>
                        </form>
                    </td>
                </tr>
            <?php endwhile; ?>
            </tbody>
        </table>
    </div>
</div></div>
<?php page_footer(); ?>
