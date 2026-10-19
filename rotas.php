<?php
require __DIR__ . '/includes/proteger.php';
require __DIR__ . '/includes/permissao.php';
exigirPapel(['administrador', 'gestor', 'maquinista']);
require __DIR__ . '/includes/seguranca.php';
require __DIR__ . '/includes/rotas_dados.php';
require __DIR__ . '/config/conexao.php';

$podeGerir = temPapel(['administrador', 'gestor']);
$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirPapel(['administrador', 'gestor']);
    exigirCsrf();
    $id = filter_var($_POST['excluir_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) { http_response_code(400); exit('Identificador de rota inválido.'); }
    $exclusao = $conexao->prepare('DELETE FROM rotas WHERE id_rota=?');
    $exclusao->bind_param('i', $id);
    $exclusao->execute();
    $mensagem = $exclusao->affected_rows ? 'Rota excluída.' : 'Rota não encontrada.';
    $exclusao->close();
}
if (($_GET['mensagem'] ?? '') === 'salvo') { $mensagem = 'Rota salva com sucesso.'; }
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
$dataInicio = is_string($_GET['data_inicio'] ?? null) ? $_GET['data_inicio'] : '';
$dataFim = is_string($_GET['data_fim'] ?? null) ? $_GET['data_fim'] : '';
$busca = is_string($_GET['busca'] ?? null) ? trim($_GET['busca']) : '';
$idTrem = $_GET['id_trem'] ?? '';
if ($status !== '' && !array_key_exists($status, statusRotas())) { http_response_code(400); exit('Filtro de status inválido.'); }
if (($dataInicio !== '' && !dataValida($dataInicio)) || ($dataFim !== '' && !dataValida($dataFim)) || ($dataInicio !== '' && $dataFim !== '' && $dataInicio > $dataFim)) { http_response_code(400); exit('Período inválido.'); }
if (!is_string($idTrem) || ($idTrem !== '' && !filter_var($idTrem, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]))) { http_response_code(400); exit('Filtro de trem inválido.'); }
if (mb_strlen($busca) > 120) { $busca = mb_substr($busca, 0, 120); }
$condicoes = []; $valores = []; $tipos = '';
if (!$podeGerir) {
    $consulta = $conexao->prepare('SELECT trem_atribuido_id FROM usuarios WHERE id=? AND papel=?');
    $papel = 'maquinista'; $consulta->bind_param('is', $_SESSION['id'], $papel); $consulta->execute();
    $idAtribuido = (int) ($consulta->get_result()->fetch_assoc()['trem_atribuido_id'] ?? 0); $consulta->close();
    $condicoes[] = 'r.id_trem=?'; $valores[] = $idAtribuido; $tipos .= 'i';
}
if ($podeGerir) { $trens = $conexao->query('SELECT id_trem, prefixo FROM trens ORDER BY prefixo')->fetch_all(MYSQLI_ASSOC); }
else { $filtro = $conexao->prepare('SELECT id_trem, prefixo FROM trens WHERE id_trem=?'); $filtro->bind_param('i', $idAtribuido); $filtro->execute(); $trens = $filtro->get_result()->fetch_all(MYSQLI_ASSOC); $filtro->close(); }
if ($status !== '') { $condicoes[] = 'r.status=?'; $valores[] = $status; $tipos .= 's'; }
if ($dataInicio !== '') { $condicoes[] = 'r.data_viagem>=?'; $valores[] = $dataInicio; $tipos .= 's'; }
if ($dataFim !== '') { $condicoes[] = 'r.data_viagem<=?'; $valores[] = $dataFim; $tipos .= 's'; }
if ($idTrem !== '') { $condicoes[] = 'r.id_trem=?'; $valores[] = (int) $idTrem; $tipos .= 'i'; }
if ($busca !== '') { $condicoes[] = '(r.origem LIKE ? OR r.destino LIKE ?)'; $termo = '%' . $busca . '%'; $valores[] = $termo; $valores[] = $termo; $tipos .= 'ss'; }
$sql = 'SELECT r.*, t.prefixo, t.modelo FROM rotas r INNER JOIN trens t ON t.id_trem=r.id_trem';
if ($condicoes) { $sql .= ' WHERE ' . implode(' AND ', $condicoes); }
$sql .= ' ORDER BY r.data_viagem DESC, r.hora_partida, r.id_rota DESC LIMIT 100';
$consulta = $conexao->prepare($sql);
if ($valores) { $consulta->bind_param($tipos, ...$valores); }
$consulta->execute(); $rotas = $consulta->get_result()->fetch_all(MYSQLI_ASSOC); $consulta->close();
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Rotas | A-TRAIN</title><link rel="stylesheet" href="assets/css/estilo.css"></head><body>
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
<main class="pagina"><div class="titulo-pagina"><div><p class="sobretitulo">Viagens programadas</p><h1>Rotas</h1><p class="descricao">Trajetos e horários dos trens.</p></div><?php if ($podeGerir): ?><a class="botao-link" href="rotas_form.php">Cadastrar rota</a><?php endif; ?></div>
<?php if ($mensagem): ?><p class="aviso sucesso" role="status"><?= escapar($mensagem) ?></p><?php endif; ?>
<?php if (!$podeGerir && !$idAtribuido): ?><p class="aviso erro">Nenhum trem atribuído.</p><?php endif; ?>
<form class="filtros" method="get"><div><label for="status">Status</label><select id="status" name="status"><option value="">Todos</option><?php foreach (statusRotas() as $valor => $rotulo): ?><option value="<?= $valor ?>" <?= $status === $valor ? 'selected' : '' ?>><?= $rotulo ?></option><?php endforeach; ?></select></div><div><label for="data_inicio">De</label><input id="data_inicio" name="data_inicio" type="date" value="<?= escapar($dataInicio) ?>"></div><div><label for="data_fim">Até</label><input id="data_fim" name="data_fim" type="date" value="<?= escapar($dataFim) ?>"></div><div><label for="id_trem">Trem</label><select id="id_trem" name="id_trem"><option value="">Todos</option><?php foreach ($trens as $trem): ?><option value="<?= (int) $trem['id_trem'] ?>" <?= $idTrem === (string) $trem['id_trem'] ? 'selected' : '' ?>><?= escapar($trem['prefixo']) ?></option><?php endforeach; ?></select></div><div><label for="busca">Rota</label><input id="busca" name="busca" value="<?= escapar($busca) ?>" placeholder="Origem ou destino"></div><button type="submit">Filtrar</button></form>
<?php if (!$rotas): ?><p class="cartao vazio">Nenhuma rota encontrada para estes filtros.</p><?php else: ?><div class="lista"><table><thead><tr><th>Trem</th><th>Origem</th><th>Destino</th><th>Data</th><th>Partida</th><th>Chegada</th><th>Status</th><?php if ($podeGerir): ?><th>Ações</th><?php endif; ?></tr></thead><tbody>
<?php foreach ($rotas as $rota): ?><tr><td data-label="Trem"><strong><?= escapar($rota['prefixo']) ?></strong></td><td data-label="Origem"><?= escapar($rota['origem']) ?></td><td data-label="Destino"><?= escapar($rota['destino']) ?></td><td data-label="Data"><?= escapar(date('d/m/Y', strtotime($rota['data_viagem']))) ?></td><td data-label="Partida"><?= escapar(substr($rota['hora_partida'], 0, 5)) ?></td><td data-label="Chegada"><?= $rota['hora_chegada'] ? escapar(substr($rota['hora_chegada'], 0, 5)) : '—' ?></td><td data-label="Status"><span class="estado estado-<?= escapar($rota['status']) ?>"><?= escapar(statusRotas()[$rota['status']]) ?></span></td><?php if ($podeGerir): ?><td data-label="Ações" class="acoes-tabela"><a href="rotas_form.php?id=<?= (int) $rota['id_rota'] ?>">Editar</a><form method="post" onsubmit="return confirm('Confirma a exclusão desta rota?');"><input type="hidden" name="csrf" value="<?= escapar(tokenCsrf()) ?>"><input type="hidden" name="excluir_id" value="<?= (int) $rota['id_rota'] ?>"><button type="submit" class="perigo">Excluir</button></form></td><?php endif; ?></tr><?php endforeach; ?></tbody></table></div><?php endif; ?></main></body></html>
