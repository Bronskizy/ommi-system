<?php
require_once __DIR__ . '/../includes/auth.php';
require_role(['super_admin']);

$defaults = [
    'mail_driver' => 'smtp',
    'mail_from_email' => 'your-gmail-address@gmail.com',
    'mail_from_name' => 'OMMI Company Ltd',
    'smtp_host' => 'smtp.gmail.com',
    'smtp_port' => '587',
    'smtp_username' => 'your-gmail-address@gmail.com',
    'smtp_password' => '',
];

foreach ($defaults as $key => $value) {
    $stmt = db()->prepare('INSERT INTO settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = IF(setting_value = "" OR setting_value LIKE "your-%", VALUES(setting_value), setting_value)');
    $stmt->bind_param('ss', $key, $value);
    $stmt->execute();
}

audit('seed', 'mail_settings', null);
flash('success', 'Mail settings are ready. Add your Gmail address and app password below.');
redirect('settings/index.php');
