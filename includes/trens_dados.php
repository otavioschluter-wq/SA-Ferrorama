<?php
function validarTrem(array $dados): array
{
    $erros = [];
    if (!preg_match('/^TR-[0-9]{3}$/', $dados['prefixo'])) {
        $erros[] = 'Use um prefixo no padrão TR-204.';
    }
    if ($dados['modelo'] === '' || mb_strlen($dados['modelo']) > 120) {
        $erros[] = 'Informe um modelo de até 120 caracteres.';
    }
    if (!preg_match('/^[0-9]{4}$/', $dados['ano']) || (int) $dados['ano'] < 1900 || (int) $dados['ano'] > 2100) {
        $erros[] = 'Informe um ano entre 1900 e 2100.';
    }
    if (!in_array($dados['status'], ['normal', 'atencao', 'critico'], true)) {
        $erros[] = 'Selecione um status válido.';
    }
    if (!preg_match('/^[0-9]{1,6}(\.[0-9]{1,2})?$/', $dados['capacidade_toneladas']) || (float) $dados['capacidade_toneladas'] <= 0 || (float) $dados['capacidade_toneladas'] > 999999.99) {
        $erros[] = 'Informe capacidade positiva em toneladas, com até duas casas decimais.';
    }
    if ($dados['ultima_inspecao'] !== '') {
        $data = DateTimeImmutable::createFromFormat('!Y-m-d', $dados['ultima_inspecao']);
        if (!$data || $data->format('Y-m-d') !== $dados['ultima_inspecao'] || $data > new DateTimeImmutable('today')) {
            $erros[] = 'Informe uma data de inspeção válida, até hoje.';
        }
    }
    return $erros;
}
