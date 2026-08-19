<?php require_once __DIR__ . '/includes/auth.php'; audit('logout','user',$_SESSION['user_id'] ?? null); session_destroy(); redirect('login.php');
