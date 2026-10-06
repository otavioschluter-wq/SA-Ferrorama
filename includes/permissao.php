<?php
require_once __DIR__ . '/proteger.php';

function temPapel(array $papeisPermitidos): bool
{
    return in_array($_SESSION['papel'], $papeisPermitidos, true);
}

function exigirPapel(array $papeisPermitidos): void
{
    if (!temPapel($papeisPermitidos)) {
        http_response_code(403);
        exit('Acesso negado para este perfil.');
    }
}
