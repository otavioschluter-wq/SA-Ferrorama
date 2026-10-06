<?php
require __DIR__ . '/includes/proteger.php';
require __DIR__ . '/includes/seguranca.php';

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Allow: POST');
    http_response_code(405);
    exit('Use o botão Sair.');
}

exigirCsrf();
$_SESSION = [];
session_unset();
if (ini_get('session.use_cookies')) {
    $opcoes = session_get_cookie_params();
    setcookie(session_name(), '', [
        'expires' => time() - 3600,
        'path' => $opcoes['path'],
        'domain' => $opcoes['domain'],
        'secure' => $opcoes['secure'],
        'httponly' => $opcoes['httponly'],
        'samesite' => $opcoes['samesite'] ?? 'Lax',
    ]);
}
session_destroy();
header('Location: index.php', true, 303);
exit;
