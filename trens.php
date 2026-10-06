<?php
require __DIR__ . '/includes/proteger.php';
require __DIR__ . '/includes/permissao.php';
exigirPapel(['administrador', 'gestor']);
require __DIR__ . '/includes/seguranca.php';
require __DIR__ . '/config/conexao.php';

$mensagem = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $id = filter_var($_POST['excluir_id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$id) { http_response_code(400); exit('Identificador de trem inválido.'); }
    try {
        $exclusao = $conexao->prepare('DELETE FROM trens WHERE id_trem = ?');
        $exclusao->bind_param('i', $id);
        $exclusao->execute();
        $mensagem = $exclusao->affected_rows ? 'Trem excluído.' : 'Trem não encontrado.';
        $exclusao->close();
    } catch (mysqli_sql_exception $falha) {
        $mensagem = $falha->getCode() === 1451 ? 'Este trem possui sensores ou maquinistas vinculados. Remova os vínculos antes de excluí-lo.' : 'Não foi possível excluir o trem.';
    }
}
if (($_GET['mensagem'] ?? '') === 'salvo') { $mensagem = 'Trem salvo com sucesso.'; }
$busca = is_string($_GET['busca'] ?? null) ? trim($_GET['busca']) : '';
$status = is_string($_GET['status'] ?? null) ? $_GET['status'] : '';
if (mb_strlen($busca) > 120) { $busca = mb_substr($busca, 0, 120); }
if (!in_array($status, ['', 'normal', 'atencao', 'critico'], true)) { http_response_code(400); exit('Filtro de status inválido.'); }
$condicoes = []; $valores = []; $tipos = '';
if ($busca !== '') { $condicoes[] = '(t.prefixo LIKE ? OR t.modelo LIKE ?)'; $termo = '%' . $busca . '%'; $valores[] = $termo; $valores[] = $termo; $tipos .= 'ss'; }
if ($status !== '') { $condicoes[] = 't.status = ?'; $valores[] = $status; $tipos .= 's'; }
$sql = 'SELECT t.*, COUNT(s.id_sensor) AS total_sensores FROM trens t LEFT JOIN sensores s ON s.id_trem = t.id_trem';
if ($condicoes) { $sql .= ' WHERE ' . implode(' AND ', $condicoes); }
$sql .= ' GROUP BY t.id_trem ORDER BY t.prefixo';
$consulta = $conexao->prepare($sql);
if ($valores) { $consulta->bind_param($tipos, ...$valores); }
$consulta->execute();
$trens = $consulta->get_result()->fetch_all(MYSQLI_ASSOC);
$consulta->close();
$rotulos = ['normal'=>'Normal', 'atencao'=>'Atenção', 'critico'=>'Crítico'];
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Trens | A-TRAIN</title><link rel="stylesheet" href="assets/css/estilo.css"></head><body>
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
<main class="pagina"><div class="titulo-pagina"><div><p class="sobretitulo">Gestão de trens</p><h1>Trens</h1><p class="descricao">Consulte e mantenha os trens cadastrados.</p></div><a class="botao-link" href="trens_form.php">Cadastrar trem</a></div>
<?php if ($mensagem): ?><p class="aviso <?= str_contains($mensagem, 'sucesso') || $mensagem === 'Trem excluído.' ? 'sucesso' : 'erro' ?>" role="status"><?= escapar($mensagem) ?></p><?php endif; ?>
<form class="filtros" method="get"><div><label for="busca">Buscar por prefixo ou modelo</label><input id="busca" name="busca" value="<?= escapar($busca) ?>" placeholder="Ex.: TR-204"></div><div><label for="status">Status</label><select id="status" name="status"><option value="">Todos</option><?php foreach ($rotulos as $valor=>$rotulo): ?><option value="<?= $valor ?>" <?= $status === $valor ? 'selected' : '' ?>><?= $rotulo ?></option><?php endforeach; ?></select></div><button type="submit">Filtrar</button></form>
<?php if (!$trens): ?><p class="cartao vazio">Nenhum trem encontrado para estes filtros.</p><?php else: ?>
<div class="lista"><table><thead><tr><th>Prefixo</th><th>Modelo</th><th>Ano</th><th>Status</th><th>Capacidade</th><th>Última inspeção</th><th>Sensores</th><th>Ações</th></tr></thead><tbody>
<?php foreach ($trens as $trem): ?><tr><td data-label="Prefixo"><strong><?= escapar($trem['prefixo']) ?></strong></td><td data-label="Modelo"><?= escapar($trem['modelo']) ?></td><td data-label="Ano"><?= (int) $trem['ano'] ?></td><td data-label="Status"><span class="estado estado-<?= escapar($trem['status']) ?>"><?= $rotulos[$trem['status']] ?></span></td><td data-label="Capacidade"><?= escapar(number_format((float) $trem['capacidade_toneladas'], 2, ',', '.')) ?> t</td><td data-label="Última inspeção"><?= $trem['ultima_inspecao'] ? escapar(date('d/m/Y', strtotime($trem['ultima_inspecao']))) : '—' ?></td><td data-label="Sensores"><?= (int) $trem['total_sensores'] ?></td><td data-label="Ações" class="acoes-tabela"><a href="trens_form.php?id=<?= (int) $trem['id_trem'] ?>">Editar</a><form method="post" onsubmit="return confirm('Confirma a exclusão deste trem?');"><input type="hidden" name="csrf" value="<?= escapar(tokenCsrf()) ?>"><input type="hidden" name="excluir_id" value="<?= (int) $trem['id_trem'] ?>"><button type="submit" class="perigo">Excluir</button></form></td></tr><?php endforeach; ?>
</tbody></table></div><?php endif; ?></main></body></html>
