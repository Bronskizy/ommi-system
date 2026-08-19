<?php
require_once __DIR__ . '/../includes/layout.php';
require_role(['member']);
$uid=current_user()['id']; $stmt=db()->prepare('SELECT id FROM members WHERE user_id=? LIMIT 1'); $stmt->bind_param('i',$uid); $stmt->execute(); $mid=(int)($stmt->get_result()->fetch_assoc()['id'] ?? 0); $stmt=db()->prepare('SELECT * FROM contributions WHERE member_id=? ORDER BY payment_date DESC'); $stmt->bind_param('i',$mid); $stmt->execute(); $rows=$stmt->get_result();
page_header('My Contributions');
?>
<style>.my-contributions>.panel{padding:0;overflow:hidden;border-radius:10px}.my-contributions .member-section-head{padding:20px 21px;border-bottom:1px solid var(--line)}.my-contributions .member-section-head h2{margin:0 0 5px;font-size:17px}.my-contributions .member-section-head p{margin:0;color:var(--muted);font-size:12px}.my-contributions .table-wrap{border:0;border-radius:0}.my-contributions table th{background:#f4f8f5}</style>
<div class="my-contributions"><section class="panel"><div class="member-section-head"><h2>Contribution history</h2><p>All payments recorded under your member account.</p></div><div class="table-wrap"><table><thead><tr><th>Receipt</th><th>Type</th><th>Amount</th><th>Date</th></tr></thead><tbody><?php while($r=$rows->fetch_assoc()): ?><tr><td><strong><?= e($r['receipt_no']) ?></strong></td><td><span class="badge"><?= e($r['contribution_type']) ?></span></td><td><strong><?= money($r['amount']) ?></strong></td><td><?= e(date('d M Y', strtotime($r['payment_date']))) ?></td></tr><?php endwhile; ?></tbody></table></div></section></div>
<?php page_footer(); ?>
