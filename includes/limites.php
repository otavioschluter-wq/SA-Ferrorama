<?php
function limitesSensor(): array
{
    return [
        'temperatura' => ['limite' => 90.0, 'unidade' => '°C', 'faixa' => [45.0, 88.0]],
        'velocidade' => ['limite' => 120.0, 'unidade' => 'km/h', 'faixa' => [30.0, 115.0]],
        'energia' => ['limite' => 500.0, 'unidade' => 'kWh', 'faixa' => [100.0, 480.0]],
        'localizacao' => ['limite' => 1000.0, 'unidade' => 'km', 'faixa' => [0.0, 950.0]],
    ];
}

function classificarLeitura(string $tipo, float $valor): string
{
    $limites = limitesSensor();
    if (!isset($limites[$tipo])) { throw new InvalidArgumentException('Tipo de sensor desconhecido.'); }
    $limite = $limites[$tipo]['limite'];
    if ($valor <= $limite) { return 'normal'; }
    return $valor <= $limite * 1.10 ? 'atencao' : 'critico';
}

function gerarValorLeitura(string $tipo, bool $foraDoLimite): float
{
    $limites = limitesSensor();
    if (!isset($limites[$tipo])) { throw new InvalidArgumentException('Tipo de sensor desconhecido.'); }
    $configuracao = $limites[$tipo];
    $minimo = $foraDoLimite ? $configuracao['limite'] * 1.01 : $configuracao['faixa'][0];
    $maximo = $foraDoLimite ? $configuracao['limite'] * 1.25 : $configuracao['faixa'][1];
    return random_int((int) round($minimo * 100), (int) round($maximo * 100)) / 100;
}
