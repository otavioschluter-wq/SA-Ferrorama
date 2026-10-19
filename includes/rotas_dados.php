<?php
function statusRotas(): array
{
    return ['programada' => 'Programada', 'em_andamento' => 'Em andamento', 'concluida' => 'Concluída', 'cancelada' => 'Cancelada'];
}

function dataValida(string $data): bool
{
    $objeto = DateTimeImmutable::createFromFormat('!Y-m-d', $data);
    return $objeto !== false && $objeto->format('Y-m-d') === $data;
}

function horaValida(string $hora): bool
{
    return preg_match('/^(?:[01][0-9]|2[0-3]):[0-5][0-9]$/', $hora) === 1;
}

function validarRota(array $dados): array
{
    $erros = [];
    foreach (['origem' => 'origem', 'destino' => 'destino'] as $campo => $rotulo) {
        if ($dados[$campo] === '' || mb_strlen($dados[$campo]) > 80) {
            $erros[] = 'Informe ' . $rotulo . ' de até 80 caracteres.';
        }
    }
    if ($dados['origem'] !== '' && mb_strtolower($dados['origem']) === mb_strtolower($dados['destino'])) {
        $erros[] = 'Origem e destino devem ser diferentes.';
    }
    if (!dataValida($dados['data_viagem'])) { $erros[] = 'Informe uma data de viagem válida.'; }
    if (!horaValida($dados['hora_partida']) || ($dados['hora_chegada'] !== '' && !horaValida($dados['hora_chegada']))) {
        $erros[] = 'Informe horários válidos.';
    }
    if ($dados['hora_chegada'] !== '' && $dados['hora_chegada'] <= $dados['hora_partida']) {
        $erros[] = 'A chegada deve ocorrer após a partida, no mesmo dia.';
    }
    if (!array_key_exists($dados['status'], statusRotas())) { $erros[] = 'Selecione um status válido.'; }
    return $erros;
}
