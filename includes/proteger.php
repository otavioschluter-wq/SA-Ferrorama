<?php
if (session_status() !== PHP_SESSION_ACTIVE) {
    session_start();
}

header('Cache-Control: no-store, no-cache, must-revalidate, max-age=0');
header('Pragma: no-cache');
header('Expires: 0');

if (!isset($_SESSION['id'], $_SESSION['login'], $_SESSION['papel']) || !is_int($_SESSION['id']) || $_SESSION['id'] < 1 || !is_string($_SESSION['login']) || $_SESSION['login'] === '' || !in_array($_SESSION['papel'], ['administrador', 'maquinista', 'gestor'], true)) {
    header('Location: ' . (str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/usuarios/') ? '../index.php' : 'index.php'));
    exit;
}
