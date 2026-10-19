<?php
require __DIR__ . '/includes/proteger.php';
require __DIR__ . '/includes/permissao.php';
exigirPapel(['administrador', 'gestor', 'maquinista']);
require __DIR__ . '/includes/seguranca.php';
require __DIR__ . '/config/conexao.php';

$podeGerir = temPapel(['administrador', 'gestor']);
$idSensor = $_GET['id_sensor'] ?? '';
$idTrem = $_GET['id_trem'] ?? '';
foreach (['sensor' => $idSensor, 'trem' => $idTrem] as $nome => $id) {
    if (!is_string($id) || ($id !== '' && !filter_var($id, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]))) { http_response_code(400); exit('Filtro de ' . $nome . ' inválido.'); }
}
$condicoes = []; $valores = []; $tipos = '';
if (!$podeGerir) {
    $consulta = $conexao->prepare('SELECT trem_atribuido_id FROM usuarios WHERE id=? AND papel=?');
    $papel = 'maquinista'; $consulta->bind_param('is', $_SESSION['id'], $papel); $consulta->execute();
    $idAtribuido = (int) ($consulta->get_result()->fetch_assoc()['trem_atribuido_id'] ?? 0); $consulta->close();
    $condicoes[] = 's.id_trem=?'; $valores[] = $idAtribuido; $tipos .= 'i';
}
if ($idSensor !== '') { $condicoes[] = 'l.id_sensor=?'; $valores[] = (int) $idSensor; $tipos .= 'i'; }
if ($idTrem !== '') { $condicoes[] = 's.id_trem=?'; $valores[] = (int) $idTrem; $tipos .= 'i'; }
$sql = 'SELECT l.id_leitura, l.data_hora, l.valor, l.unidade, s.codigo, s.tipo, t.prefixo FROM leituras l INNER JOIN sensores s ON s.id_sensor=l.id_sensor INNER JOIN trens t ON t.id_trem=s.id_trem';
if ($condicoes) { $sql .= ' WHERE ' . implode(' AND ', $condicoes); }
$sql .= ' ORDER BY l.data_hora DESC, l.id_leitura DESC LIMIT 100';
$consulta = $conexao->prepare($sql);
if ($valores) { $consulta->bind_param($tipos, ...$valores); }
$consulta->execute(); $leituras = $consulta->get_result()->fetch_all(MYSQLI_ASSOC); $consulta->close();
if ($podeGerir) {
    $trens = $conexao->query('SELECT id_trem, prefixo FROM trens ORDER BY prefixo')->fetch_all(MYSQLI_ASSOC);
    $sensores = $conexao->query('SELECT id_sensor, codigo FROM sensores ORDER BY codigo')->fetch_all(MYSQLI_ASSOC);
} else {
    $filtro = $conexao->prepare('SELECT id_trem, prefixo FROM trens WHERE id_trem=?');
    $filtro->bind_param('i', $idAtribuido); $filtro->execute(); $trens = $filtro->get_result()->fetch_all(MYSQLI_ASSOC); $filtro->close();
    $filtro = $conexao->prepare('SELECT id_sensor, codigo FROM sensores WHERE id_trem=? ORDER BY codigo');
    $filtro->bind_param('i', $idAtribuido); $filtro->execute(); $sensores = $filtro->get_result()->fetch_all(MYSQLI_ASSOC); $filtro->close();
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Leituras | A-TRAIN</title><link rel="stylesheet" href="assets/css/estilo.css"></head><body>
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
<main class="pagina"><div class="titulo-pagina"><div><p class="sobretitulo">Monitoramento</p><h1>Leituras dos sensores</h1><p class="descricao">Até 100 leituras, da mais recente para a mais antiga.</p></div><?php if ($podeGerir): ?><a class="botao-link" href="simulador.php">Abrir simulador</a><?php endif; ?></div>
<?php if (!$podeGerir && !$idAtribuido): ?><p class="aviso erro">Nenhum trem atribuído.</p><?php endif; ?>
<form class="filtros" method="get"><div><label for="id_sensor">Sensor</label><select id="id_sensor" name="id_sensor"><option value="">Todos</option><?php foreach ($sensores as $sensor): ?><option value="<?= (int) $sensor['id_sensor'] ?>" <?= $idSensor === (string) $sensor['id_sensor'] ? 'selected' : '' ?>><?= escapar($sensor['codigo']) ?></option><?php endforeach; ?></select></div><div><label for="id_trem">Trem</label><select id="id_trem" name="id_trem"><option value="">Todos</option><?php foreach ($trens as $trem): ?><option value="<?= (int) $trem['id_trem'] ?>" <?= $idTrem === (string) $trem['id_trem'] ? 'selected' : '' ?>><?= escapar($trem['prefixo']) ?></option><?php endforeach; ?></select></div><button type="submit">Filtrar</button></form>
<?php if (!$leituras): ?><p class="cartao vazio">Nenhuma leitura encontrada para esta consulta.</p><?php else: ?><div class="lista"><table><thead><tr><th>Data e hora</th><th>Sensor</th><th>Tipo</th><th>Trem</th><th>Valor</th></tr></thead><tbody><?php foreach ($leituras as $leitura): ?><tr><td data-label="Data e hora"><?= escapar(date('d/m/Y H:i:s', strtotime($leitura['data_hora']))) ?></td><td data-label="Sensor"><?= escapar($leitura['codigo']) ?></td><td data-label="Tipo"><?= escapar($leitura['tipo']) ?></td><td data-label="Trem"><?= escapar($leitura['prefixo']) ?></td><td data-label="Valor"><strong><?= escapar(number_format((float) $leitura['valor'], 2, ',', '.')) ?> <?= escapar($leitura['unidade']) ?></strong></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></main></body></html>
