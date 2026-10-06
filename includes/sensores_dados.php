<?php
function tiposSensor(): array
{
    return ['temperatura'=>'Temperatura', 'velocidade'=>'Velocidade', 'energia'=>'Energia', 'localizacao'=>'Localização'];
}

function validarSensor(array $dados): array
{
    $erros = [];
    $prefixos = ['temperatura'=>'TEMP', 'velocidade'=>'VELO', 'energia'=>'ENER', 'localizacao'=>'LOCA'];
    if (!isset($prefixos[$dados['tipo']])) { $erros[] = 'Selecione um tipo válido.'; }
    if (!preg_match('/^S-(TEMP|VELO|ENER|LOCA)-[0-9]{3}$/', $dados['codigo']) || (isset($prefixos[$dados['tipo']]) && !str_starts_with($dados['codigo'], 'S-' . $prefixos[$dados['tipo']] . '-'))) {
        $erros[] = 'Use um código coerente com o tipo, como S-TEMP-001.';
    }
    foreach (['localizacao'=>'localização', 'segmento'=>'segmento'] as $campo=>$rotulo) {
        if ($dados[$campo] === '' || mb_strlen($dados[$campo]) > 80) { $erros[] = 'Informe ' . $rotulo . ' de até 80 caracteres.'; }
    }
    if (!in_array($dados['ultima_leitura'], ['normal', 'atencao', 'critico'], true)) { $erros[] = 'Selecione um indicador de leitura válido.'; }
    return $erros;
}
