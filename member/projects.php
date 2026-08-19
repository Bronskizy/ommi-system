<?php
require_once __DIR__ . '/../includes/layout.php';
require_role(['member']);
$rows=db()->query('SELECT p.*, pc.name category FROM projects p JOIN project_categories pc ON pc.id=p.category_id ORDER BY p.created_at DESC');
page_header('Project Summaries');
?>
<style>.member-projects .projects-intro{padding:20px 23px;margin-bottom:19px;border-radius:10px;background:#eef8f1}.member-projects .projects-intro h2{margin:0 0 5px;font-size:19px}.member-projects .projects-intro p{margin:0;color:var(--muted)}.member-projects .project-card{margin:0;padding:0;overflow:hidden;border-radius:10px}.member-projects .project-card-head{padding:18px 20px;border-bottom:1px solid var(--line)}.member-projects .project-card h2{margin:0;font-size:17px}.member-projects .project-card-body{padding:17px 20px}.member-projects .project-budget{font-size:18px;color:var(--ink)}.member-projects .project-description{min-height:40px;line-height:1.55}</style>
<div class="member-projects"><section class="projects-intro"><h2>Organisation projects</h2><p>Follow the projects being delivered by OMMI and their planned budgets.</p></section><section class="grid two"><?php while($p=$rows->fetch_assoc()): ?><article class="panel project-card"><div class="project-card-head"><h2><?= e($p['name']) ?></h2></div><div class="project-card-body"><p><span class="badge"><?= e($p['category']) ?></span> <span class="badge"><?= e($p['status']) ?></span></p><p class="project-budget">Budget: <strong><?= money($p['budget']) ?></strong></p><p class="muted project-description"><?= e($p['description']) ?></p></div></article><?php endwhile; ?></section></div>
<?php page_footer(); ?>
