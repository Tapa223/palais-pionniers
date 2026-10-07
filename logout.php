<?php
require_once __DIR__ . '/includes/auth.php';
$_SESSION = [];
if (ini_get('session.use_cookies')) {
    $p = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires'  => time() - 3600,
        'path'     => $p['path'],
        'secure'   => $p['secure'],
        'httponly' => true,
        'samesite' => 'Lax',
    ]);
}
session_destroy();
header('Location: index.php');
exit;
