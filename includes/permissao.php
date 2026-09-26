<?php
require_once __DIR__ . '/proteger.php';

if ($_SESSION['papel'] !== 'administrador') {
    http_response_code(403);
    exit('Acesso negado. Apenas administradores podem cadastrar usuários.');
}
