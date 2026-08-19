<?php require_once __DIR__ . '/includes/auth.php'; if (current_user()) redirect(dashboard_for(current_user()['role'])); redirect('login.php');
