<?php
require __DIR__ . '/includes/proteger.php';
require __DIR__ . '/includes/permissao.php';
exigirPapel(['administrador', 'gestor', 'maquinista']);
require __DIR__ . '/includes/seguranca.php';
require __DIR__ . '/includes/sensores_dados.php';
require __DIR__ . '/config/conexao.php';

$podeGerir = temPapel(['administrador', 'gestor']);
$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirPapel(['administrador', 'gestor']);
    exigirCsrf();
    $id = filter_var($_POST['excluir_id'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if (!$id) { http_response_code(400); exit('Identificador de sensor inválido.'); }
    try {
        $exclusao = $conexao->prepare('DELETE FROM sensores WHERE id_sensor=?');
        $exclusao->bind_param('i', $id); $exclusao->execute();
        $mensagem = $exclusao->affected_rows ? 'Sensor excluído.' : 'Sensor não encontrado.';
        $exclusao->close();
    } catch (mysqli_sql_exception $falha) { $mensagem = 'Não foi possível excluir o sensor.'; }
}
if (($_GET['mensagem'] ?? '') === 'salvo') { $mensagem = 'Sensor salvo com sucesso.'; }
$tipo = is_string($_GET['tipo'] ?? null) ? $_GET['tipo'] : '';
if ($tipo !== '' && !array_key_exists($tipo, tiposSensor())) { http_response_code(400); exit('Filtro de tipo inválido.'); }
$condicoes = []; $valores = []; $tipos = '';
if (!$podeGerir) {
    $consulta = $conexao->prepare('SELECT trem_atribuido_id FROM usuarios WHERE id=? AND papel=?');
    $papel = 'maquinista'; $consulta->bind_param('is', $_SESSION['id'], $papel); $consulta->execute();
    $usuario = $consulta->get_result()->fetch_assoc(); $consulta->close();
    $idTrem = $usuario['trem_atribuido_id'] ?? 0;
    $condicoes[] = 's.id_trem = ?'; $valores[] = (int) $idTrem; $tipos .= 'i';
}
if ($tipo !== '') { $condicoes[] = 's.tipo = ?'; $valores[] = $tipo; $tipos .= 's'; }
$sql = 'SELECT s.id_sensor, s.codigo, s.tipo, s.localizacao, s.segmento, s.ultima_leitura, t.prefixo, t.modelo FROM sensores s INNER JOIN trens t ON t.id_trem=s.id_trem';
if ($condicoes) { $sql .= ' WHERE ' . implode(' AND ', $condicoes); }
$sql .= ' ORDER BY t.prefixo, s.codigo';
$consulta = $conexao->prepare($sql);
if ($valores) { $consulta->bind_param($tipos, ...$valores); }
$consulta->execute(); $sensores = $consulta->get_result()->fetch_all(MYSQLI_ASSOC); $consulta->close();
$rotulos = ['normal'=>'Normal','atencao'=>'Atenção','critico'=>'Crítico'];
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Sensores | A-TRAIN</title><link rel="stylesheet" href="assets/css/estilo.css"></head><body>
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
<main class="pagina"><div class="titulo-pagina"><div><p class="sobretitulo"><?= $podeGerir ? 'Gestão de sensores' : 'Consulta de sensores' ?></p><h1>Sensores</h1><p class="descricao"><?= $podeGerir ? 'Sensores instalados nos trens.' : 'Sensores do trem atribuído à sua conta.' ?></p></div><?php if ($podeGerir): ?><a class="botao-link" href="sensores_form.php">Cadastrar sensor</a><?php endif; ?></div>
<?php if ($mensagem): ?><p class="aviso <?= str_contains($mensagem, 'sucesso') || $mensagem === 'Sensor excluído.' ? 'sucesso' : 'erro' ?>" role="status"><?= escapar($mensagem) ?></p><?php endif; ?>
<?php if (!$podeGerir && !$idTrem): ?><p class="aviso erro">Nenhum trem atribuído. Peça a um administrador para fazer a atribuição.</p><?php endif; ?>
<form class="filtros" method="get"><div><label for="tipo">Filtrar por tipo</label><select id="tipo" name="tipo"><option value="">Todos</option><?php foreach (tiposSensor() as $valor=>$rotulo): ?><option value="<?= $valor ?>" <?= $tipo === $valor ? 'selected' : '' ?>><?= $rotulo ?></option><?php endforeach; ?></select></div><button type="submit">Filtrar</button></form>
<?php if (!$sensores): ?><p class="cartao vazio">Nenhum sensor encontrado para esta consulta.</p><?php else: ?><div class="lista"><table><thead><tr><th>Código</th><th>Tipo</th><th>Trem</th><th>Localização</th><th>Segmento</th><th>Última leitura</th><?php if ($podeGerir): ?><th>Ações</th><?php endif; ?></tr></thead><tbody>
<?php foreach ($sensores as $sensor): ?><tr><td data-label="Código"><strong><?= escapar($sensor['codigo']) ?></strong></td><td data-label="Tipo"><?= escapar(tiposSensor()[$sensor['tipo']] ?? $sensor['tipo']) ?></td><td data-label="Trem"><?= escapar($sensor['prefixo'] . ' — ' . $sensor['modelo']) ?></td><td data-label="Localização"><?= escapar($sensor['localizacao']) ?></td><td data-label="Segmento"><?= escapar($sensor['segmento']) ?></td><td data-label="Última leitura"><span class="estado estado-<?= escapar($sensor['ultima_leitura']) ?>"><?= $rotulos[$sensor['ultima_leitura']] ?></span></td><?php if ($podeGerir): ?><td data-label="Ações" class="acoes-tabela"><a href="sensores_form.php?id=<?= (int) $sensor['id_sensor'] ?>">Editar</a><form method="post" onsubmit="return confirm('Confirma a exclusão deste sensor?');"><input type="hidden" name="csrf" value="<?= escapar(tokenCsrf()) ?>"><input type="hidden" name="excluir_id" value="<?= (int) $sensor['id_sensor'] ?>"><button type="submit" class="perigo">Excluir</button></form></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></main></body></html>
