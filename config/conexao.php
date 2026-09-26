<?php
$servidor = '127.0.0.1';
$usuario = 'atrain_app';
$senha_banco = getenv('ATRAIN_DB_PASSWORD');
$banco = 'frota_ferroviaria';

try {
    if (!is_string($senha_banco) || $senha_banco === '') {
        throw new RuntimeException('Credencial indisponível');
    }
    $conexao = new mysqli($servidor, $usuario, $senha_banco, $banco);
    if ($conexao->connect_error || !$conexao->set_charset('utf8mb4')) {
        throw new RuntimeException('Conexão indisponível');
    }
} catch (mysqli_sql_exception | RuntimeException $erro) {
    http_response_code(503);
    exit('Não foi possível conectar ao banco de dados. Verifique se o serviço está disponível.');
}
