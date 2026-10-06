<?php
$servidor = '127.0.0.1';
$usuario = 'sa_fundos_app';
$senha_banco = getenv('SA_FUNDOS_DB_PASSWORD');
$banco = getenv('SA_FUNDOS_DB_NAME') ?: 'sa_ferrorama_fundos';

try {
    if (!is_string($senha_banco) || $senha_banco === '') {
        throw new RuntimeException('Credencial indisponível');
    }
    if (!is_string($banco) || !preg_match('/^[a-zA-Z0-9_]+$/', $banco)) {
        throw new RuntimeException('Nome de banco inválido');
    }
    $conexao = new mysqli($servidor, $usuario, $senha_banco, $banco);
    if ($conexao->connect_error || !$conexao->set_charset('utf8mb4')) {
        throw new RuntimeException('Conexão indisponível');
    }
} catch (mysqli_sql_exception | RuntimeException $erro) {
    http_response_code(503);
    exit('Não foi possível conectar ao banco de dados. Verifique se o serviço está disponível.');
}
