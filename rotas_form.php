<?php
require __DIR__ . '/includes/proteger.php';
require __DIR__ . '/includes/permissao.php';
exigirPapel(['administrador', 'gestor']);
require __DIR__ . '/includes/seguranca.php';
require __DIR__ . '/includes/rotas_dados.php';
require __DIR__ . '/config/conexao.php';

$id = filter_var($_GET['id'] ?? ($_POST['id_rota'] ?? null), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
if ((isset($_GET['id']) && !$id) || (isset($_POST['id_rota']) && $_POST['id_rota'] !== '' && !$id)) { http_response_code(400); exit('Identificador de rota inválido.'); }
$dados = ['id_trem' => '', 'origem' => '', 'destino' => '', 'data_viagem' => '', 'hora_partida' => '', 'hora_chegada' => '', 'status' => 'programada'];
$erros = [];
$trens = $conexao->query('SELECT id_trem, prefixo, modelo FROM trens ORDER BY prefixo')->fetch_all(MYSQLI_ASSOC);
if ($id && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $consulta = $conexao->prepare('SELECT id_trem, origem, destino, data_viagem, hora_partida, hora_chegada, status FROM rotas WHERE id_rota=?');
    $consulta->bind_param('i', $id); $consulta->execute();
    $registro = $consulta->get_result()->fetch_assoc(); $consulta->close();
    if (!$registro) { http_response_code(404); exit('Rota não encontrada.'); }
    foreach ($dados as $campo => $_) { $dados[$campo] = (string) ($registro[$campo] ?? ''); }
    $dados['hora_partida'] = substr($dados['hora_partida'], 0, 5);
    $dados['hora_chegada'] = substr($dados['hora_chegada'], 0, 5);
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    foreach ($dados as $campo => $_) { $dados[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : ''; }
    $erros = validarRota($dados);
    $idTrem = filter_var($dados['id_trem'], FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    if (!$idTrem) { $erros[] = 'Selecione um trem válido.'; }
    else {
        $consulta = $conexao->prepare('SELECT id_trem FROM trens WHERE id_trem=?');
        $consulta->bind_param('i', $idTrem); $consulta->execute();
        if (!$consulta->get_result()->fetch_assoc()) { $erros[] = 'O trem selecionado não existe.'; }
        $consulta->close();
    }
    if (!$erros) {
        try {
            $chegada = $dados['hora_chegada'] === '' ? null : $dados['hora_chegada'];
            if ($id) {
                $consulta = $conexao->prepare('UPDATE rotas SET id_trem=?, origem=?, destino=?, data_viagem=?, hora_partida=?, hora_chegada=?, status=? WHERE id_rota=?');
                $consulta->bind_param('issssssi', $idTrem, $dados['origem'], $dados['destino'], $dados['data_viagem'], $dados['hora_partida'], $chegada, $dados['status'], $id);
            } else {
                $consulta = $conexao->prepare('INSERT INTO rotas (id_trem, origem, destino, data_viagem, hora_partida, hora_chegada, status) VALUES (?, ?, ?, ?, ?, ?, ?)');
                $consulta->bind_param('issssss', $idTrem, $dados['origem'], $dados['destino'], $dados['data_viagem'], $dados['hora_partida'], $chegada, $dados['status']);
            }
            $consulta->execute(); $afetadas = $consulta->affected_rows; $consulta->close();
            if ($id && !$afetadas) {
                $verifica = $conexao->prepare('SELECT id_rota FROM rotas WHERE id_rota=?');
                $verifica->bind_param('i', $id); $verifica->execute();
                if (!$verifica->get_result()->fetch_assoc()) { http_response_code(404); exit('Rota não encontrada.'); }
                $verifica->close();
            }
            header('Location: rotas.php?mensagem=salvo', true, 303); exit;
        } catch (mysqli_sql_exception $falha) {
            $erros[] = $falha->getCode() === 1452 ? 'O trem selecionado não existe.' : 'Não foi possível salvar a rota.';
        }
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $id ? 'Editar' : 'Cadastrar' ?> rota | A-TRAIN</title><link rel="stylesheet" href="assets/css/estilo.css"></head><body>
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
<main class="pagina"><div class="titulo-pagina"><div><p class="sobretitulo">Viagens programadas</p><h1><?= $id ? 'Editar rota' : 'Cadastrar rota' ?></h1></div><a href="rotas.php">← Voltar às rotas</a></div>
<?php if ($erros): ?><div class="aviso erro" role="alert"><ul><?php foreach ($erros as $erro): ?><li><?= escapar($erro) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form class="cartao formulario" method="post"><input type="hidden" name="csrf" value="<?= escapar(tokenCsrf()) ?>"><input type="hidden" name="id_rota" value="<?= $id ? (int) $id : '' ?>">
<label for="id_trem">Trem</label><select id="id_trem" name="id_trem" required><option value="">Selecione um trem</option><?php foreach ($trens as $trem): ?><option value="<?= (int) $trem['id_trem'] ?>" <?= $dados['id_trem'] === (string) $trem['id_trem'] ? 'selected' : '' ?>><?= escapar($trem['prefixo'] . ' — ' . $trem['modelo']) ?></option><?php endforeach; ?></select>
<div class="grade-form"><div><label for="origem">Origem</label><input id="origem" name="origem" maxlength="80" value="<?= escapar($dados['origem']) ?>" required></div><div><label for="destino">Destino</label><input id="destino" name="destino" maxlength="80" value="<?= escapar($dados['destino']) ?>" required></div></div>
<label for="data_viagem">Data da viagem</label><input id="data_viagem" name="data_viagem" type="date" value="<?= escapar($dados['data_viagem']) ?>" required>
<div class="grade-form"><div><label for="hora_partida">Partida</label><input id="hora_partida" name="hora_partida" type="time" value="<?= escapar($dados['hora_partida']) ?>" required></div><div><label for="hora_chegada">Chegada (opcional)</label><input id="hora_chegada" name="hora_chegada" type="time" value="<?= escapar($dados['hora_chegada']) ?>"></div></div>
<label for="status">Status</label><select id="status" name="status" required><?php foreach (statusRotas() as $valor => $rotulo): ?><option value="<?= $valor ?>" <?= $dados['status'] === $valor ? 'selected' : '' ?>><?= $rotulo ?></option><?php endforeach; ?></select>
<div class="acoes"><a href="rotas.php">Cancelar</a><button type="submit">Salvar rota</button></div></form></main></body></html>
