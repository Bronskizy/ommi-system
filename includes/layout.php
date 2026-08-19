<?php
require_once __DIR__ . '/auth.php';

function page_header(string $title): void
{
    $u = current_user();
    $role = $u['role'] ?? 'guest';
    $base = BASE_URL;
    $styleVersion = (string) (filemtime(__DIR__ . '/../assets/css/style.css') ?: time());
    $navigationVersion = (string) (filemtime(__DIR__ . '/../assets/css/navigation.css') ?: time());
    $initial = strtoupper(substr((string) ($u['full_name'] ?? 'U'), 0, 1));
    ?>
<!doctype html>
<html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= e($title) ?> | <?= APP_NAME ?></title><link rel="stylesheet" href="<?= $base ?>/assets/css/style.css?v=<?= e($styleVersion) ?>"><link rel="stylesheet" href="<?= $base ?>/assets/css/navigation.css?v=<?= e($navigationVersion) ?>"></head>
<body><header class="site-header" data-site-header><div class="header-inner"><a class="site-brand" href="<?= $base ?>/<?= $role === 'member' ? 'member/dashboard.php' : 'dashboard.php' ?>" aria-label="<?= e(APP_NAME) ?> home"><span class="brand-badge" aria-hidden="true">OM</span><span>OMMI <small>Management System</small></span></a><button class="nav-toggle" type="button" aria-label="Open navigation menu" aria-controls="primary-navigation" aria-expanded="false" data-nav-toggle><span></span><span></span><span></span></button><nav class="main-nav" id="primary-navigation" aria-label="Primary navigation" data-main-nav><div class="nav-mobile-head"><span>Navigation</span><button type="button" aria-label="Close navigation menu" data-nav-close>&times;</button></div><?php if ($role === 'member'): ?><a href="<?= $base ?>/member/dashboard.php">Dashboard</a><a href="<?= $base ?>/member/contributions.php">My Contributions</a><a href="<?= $base ?>/member/projects.php">Projects</a><a href="<?= $base ?>/member/account-settings.php">Account Settings</a><?php else: ?><a href="<?= $base ?>/dashboard.php">Dashboard</a><a href="<?= $base ?>/admin/members.php">Members</a><a href="<?= $base ?>/contributions/index.php">Contributions</a><a href="<?= $base ?>/projects/index.php">Projects</a><a href="<?= $base ?>/expenses/index.php">Expenses</a><a href="<?= $base ?>/reports/index.php">Reports</a><span class="nav-divider" aria-hidden="true"></span><a href="<?= $base ?>/settings/index.php">Settings</a><a href="<?= $base ?>/logs/audit.php">Audit Logs</a><?php endif; ?></nav><div class="user-menu"><span class="user-avatar" aria-hidden="true"><?= e($initial) ?></span><span class="user-details"><strong><?= e($u['full_name'] ?? 'Guest') ?></strong><small><?= e(ucwords(str_replace('_', ' ', $role))) ?></small></span><a class="logout" href="<?= $base ?>/logout.php">Log out</a></div></div></header><div class="nav-backdrop" data-nav-backdrop></div><main class="container"><div class="page-heading"><div><p class="page-kicker">OMMI MANAGEMENT</p><h1><?= e($title) ?></h1></div></div><?php foreach (flashes() as $f): ?><div class="alert alert-<?= e($f['type']) ?>"><?= e($f['message']) ?></div><?php endforeach; ?>
<?php
}

function page_footer(): void
{ ?>
</main><footer class="site-footer"><span>&copy; <?= date('Y') ?> OMMI Company Ltd</span><span>Management made simple</span></footer><script src="<?= BASE_URL ?>/assets/js/app.js"></script></body></html>
<?php }
