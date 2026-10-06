<?php
require_once __DIR__ . '/proteger.php';

function escapar(?string $valor): string
{
    return htmlspecialchars($valor ?? '', ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function tokenCsrf(): string
{
    if (!isset($_SESSION['csrf']) || !is_string($_SESSION['csrf'])) {
        $_SESSION['csrf'] = bin2hex(random_bytes(32));
    }
    return $_SESSION['csrf'];
}

function exigirCsrf(): void
{
    $recebido = $_POST['csrf'] ?? null;
    if (!is_string($recebido) || !hash_equals(tokenCsrf(), $recebido)) {
        http_response_code(403);
        exit('Sessão do formulário inválida. Recarregue a página e tente novamente.');
    }
}
