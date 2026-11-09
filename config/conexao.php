<?php
mysqli_report(MYSQLI_REPORT_ERROR | MYSQLI_REPORT_STRICT);

try {
    $conexao = new mysqli('127.0.0.1', 'root', '', null, (int) (getenv('SA_FUNDOS_DB_PORT') ?: 3306));
    $conexao->set_charset('utf8mb4');
    $existe = $conexao->query("SELECT 1 FROM information_schema.SCHEMATA WHERE SCHEMA_NAME = 'sa_ferrorama_fundos'")->num_rows;
    if (!$existe) {
        $conexao->multi_query(file_get_contents(__DIR__ . '/../banco/script.sql'));
        do {
            if ($resultado = $conexao->store_result()) {
                $resultado->free();
            }
        } while ($conexao->more_results() && $conexao->next_result());
    }
    $conexao->select_db('sa_ferrorama_fundos');
} catch (mysqli_sql_exception $erro) {
    http_response_code(503);
    exit('Nao foi possivel conectar ao banco. Inicie o MySQL no painel do XAMPP e recarregue a pagina.');
}
