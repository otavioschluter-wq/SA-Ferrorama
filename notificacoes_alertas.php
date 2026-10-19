<?php
require __DIR__ . '/includes/proteger.php';
require __DIR__ . '/includes/permissao.php';
exigirPapel(['administrador', 'gestor', 'maquinista']);
require __DIR__ . '/includes/seguranca.php';
require __DIR__ . '/includes/sensores_dados.php';
require __DIR__ . '/config/conexao.php';

$severidade = is_string($_GET['severidade'] ?? null) ? $_GET['severidade'] : '';
$tipo = is_string($_GET['tipo'] ?? null) ? $_GET['tipo'] : '';
if (!in_array($severidade, ['', 'atencao', 'critico'], true) || ($tipo !== '' && !array_key_exists($tipo, tiposSensor()))) { http_response_code(400); exit('Filtro inválido.'); }
$condicoes = []; $valores = []; $tipos = '';
if (temPapel(['maquinista'])) {
    $consulta = $conexao->prepare('SELECT trem_atribuido_id FROM usuarios WHERE id=? AND papel=?');
    $papel = 'maquinista'; $consulta->bind_param('is', $_SESSION['id'], $papel); $consulta->execute();
    $idAtribuido = (int) ($consulta->get_result()->fetch_assoc()['trem_atribuido_id'] ?? 0); $consulta->close();
    $condicoes[] = 's.id_trem=?'; $valores[] = $idAtribuido; $tipos .= 'i';
}
if ($severidade !== '') { $condicoes[] = 'a.severidade=?'; $valores[] = $severidade; $tipos .= 's'; }
if ($tipo !== '') { $condicoes[] = 'a.tipo=?'; $valores[] = $tipo; $tipos .= 's'; }
$sql = 'SELECT a.id_alerta, a.tipo, a.severidade, a.mensagem, a.data_hora, s.codigo, t.prefixo, l.valor, l.unidade FROM alertas a INNER JOIN leituras l ON l.id_leitura=a.id_leitura INNER JOIN sensores s ON s.id_sensor=l.id_sensor INNER JOIN trens t ON t.id_trem=s.id_trem';
if ($condicoes) { $sql .= ' WHERE ' . implode(' AND ', $condicoes); }
$sql .= ' ORDER BY a.data_hora DESC, a.id_alerta DESC LIMIT 100';
$consulta = $conexao->prepare($sql);
if ($valores) { $consulta->bind_param($tipos, ...$valores); }
$consulta->execute(); $alertas = $consulta->get_result()->fetch_all(MYSQLI_ASSOC); $consulta->close();
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Alertas | A-TRAIN</title><link rel="stylesheet" href="assets/css/estilo.css"></head><body>
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
<main class="pagina"><div class="titulo-pagina"><div><p class="sobretitulo">Monitoramento</p><h1>Central de alertas</h1><p class="descricao">Leituras acima dos limites de segurança.</p></div><a href="leituras.php">Ver leituras</a></div>
<?php if (isset($idAtribuido) && !$idAtribuido): ?><p class="aviso erro">Nenhum trem atribuído.</p><?php endif; ?>
<form class="filtros" method="get"><div><label for="severidade">Severidade</label><select id="severidade" name="severidade"><option value="">Todas</option><option value="atencao" <?= $severidade === 'atencao' ? 'selected' : '' ?>>Atenção</option><option value="critico" <?= $severidade === 'critico' ? 'selected' : '' ?>>Crítico</option></select></div><div><label for="tipo">Tipo de sensor</label><select id="tipo" name="tipo"><option value="">Todos</option><?php foreach (tiposSensor() as $valor => $rotulo): ?><option value="<?= $valor ?>" <?= $tipo === $valor ? 'selected' : '' ?>><?= $rotulo ?></option><?php endforeach; ?></select></div><button type="submit">Filtrar</button></form>
<?php if (!$alertas): ?><p class="cartao vazio">Nenhum alerta encontrado para esta consulta.</p><?php else: ?><div class="lista"><table><thead><tr><th>Data e hora</th><th>Severidade</th><th>Tipo</th><th>Sensor</th><th>Trem</th><th>Valor</th><th>Mensagem</th></tr></thead><tbody><?php foreach ($alertas as $alerta): ?><tr class="linha-alerta-<?= escapar($alerta['severidade']) ?>"><td data-label="Data e hora"><?= escapar(date('d/m/Y H:i:s', strtotime($alerta['data_hora']))) ?></td><td data-label="Severidade"><span class="estado estado-<?= escapar($alerta['severidade']) ?>"><?= $alerta['severidade'] === 'critico' ? 'Crítico' : 'Atenção' ?></span></td><td data-label="Tipo"><?= escapar(tiposSensor()[$alerta['tipo']] ?? $alerta['tipo']) ?></td><td data-label="Sensor"><?= escapar($alerta['codigo']) ?></td><td data-label="Trem"><?= escapar($alerta['prefixo']) ?></td><td data-label="Valor"><?= escapar(number_format((float) $alerta['valor'], 2, ',', '.')) ?> <?= escapar($alerta['unidade']) ?></td><td data-label="Mensagem"><?= escapar($alerta['mensagem']) ?></td></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></main></body></html>
