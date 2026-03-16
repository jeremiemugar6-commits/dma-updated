<?php
require_once __DIR__ . '/../../includes/auth.php';
$session = getSession();
if ($session) {
    logAudit($session['userId'], 'ACCOUNT_LOGOUT', 'User logged out');
}
deleteSession();
setcookie('dms_name', '', time() - 3600, '/');
header('Location: /dms-php/login.php');
exit;
