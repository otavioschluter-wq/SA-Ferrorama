<?php
require __DIR__ . '/includes/proteger.php';
require __DIR__ . '/includes/permissao.php';
exigirPapel(['administrador', 'gestor']);
require __DIR__ . '/includes/seguranca.php';
require __DIR__ . '/includes/trens_dados.php';
require __DIR__ . '/config/conexao.php';

$id = filter_var($_GET['id'] ?? ($_POST['id_trem'] ?? null), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
if ((isset($_GET['id']) && !$id) || (isset($_POST['id_trem']) && $_POST['id_trem'] !== '' && !$id)) { http_response_code(400); exit('Identificador de trem inválido.'); }
$dados = ['prefixo' => '', 'modelo' => '', 'ano' => '', 'status' => 'normal', 'capacidade_toneladas' => '', 'ultima_inspecao' => ''];
$erros = [];
if ($id && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $consulta = $conexao->prepare('SELECT prefixo, modelo, ano, status, capacidade_toneladas, ultima_inspecao FROM trens WHERE id_trem = ?');
    $consulta->bind_param('i', $id);
    $consulta->execute();
    $registro = $consulta->get_result()->fetch_assoc();
    $consulta->close();
    if (!$registro) { http_response_code(404); exit('Trem não encontrado.'); }
    foreach ($dados as $campo => $_) { $dados[$campo] = (string) ($registro[$campo] ?? ''); }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    foreach ($dados as $campo => $_) { $dados[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : ''; }
    $dados['prefixo'] = strtoupper($dados['prefixo']);
    $erros = validarTrem($dados);
    if (!$erros) {
        $consulta = $conexao->prepare('SELECT id_trem FROM trens WHERE prefixo = ? AND (? IS NULL OR id_trem <> ?) LIMIT 1');
        $consulta->bind_param('sii', $dados['prefixo'], $id, $id);
        $consulta->execute();
        if ($consulta->get_result()->fetch_assoc()) { $erros[] = 'Este prefixo já está cadastrado.'; }
        $consulta->close();
    }
    if (!$erros) {
        $ano = (int) $dados['ano'];
        $capacidade = $dados['capacidade_toneladas'];
        $inspecao = $dados['ultima_inspecao'] === '' ? null : $dados['ultima_inspecao'];
        try {
            if ($id) {
                $consulta = $conexao->prepare('UPDATE trens SET prefixo=?, modelo=?, ano=?, status=?, capacidade_toneladas=?, ultima_inspecao=? WHERE id_trem=?');
                $consulta->bind_param('ssisssi', $dados['prefixo'], $dados['modelo'], $ano, $dados['status'], $capacidade, $inspecao, $id);
            } else {
                $consulta = $conexao->prepare('INSERT INTO trens (prefixo, modelo, ano, status, capacidade_toneladas, ultima_inspecao) VALUES (?, ?, ?, ?, ?, ?)');
                $consulta->bind_param('ssisss', $dados['prefixo'], $dados['modelo'], $ano, $dados['status'], $capacidade, $inspecao);
            }
            $consulta->execute();
            $afetadas = $consulta->affected_rows;
            $consulta->close();
            if ($id && $afetadas === 0) {
                $verifica = $conexao->prepare('SELECT id_trem FROM trens WHERE id_trem=?');
                $verifica->bind_param('i', $id); $verifica->execute();
                if (!$verifica->get_result()->fetch_assoc()) { http_response_code(404); exit('Trem não encontrado.'); }
            }
            header('Location: trens.php?mensagem=salvo', true, 303); exit;
        } catch (mysqli_sql_exception $falha) {
            $erros[] = $falha->getCode() === 1062 ? 'Este prefixo já está cadastrado.' : 'Não foi possível salvar o trem.';
        }
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $id ? 'Editar' : 'Cadastrar' ?> trem | A-TRAIN</title><link rel="stylesheet" href="assets/css/estilo.css"></head><body>
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
<main class="pagina"><div class="titulo-pagina"><div><p class="sobretitulo">Gestão de trens</p><h1><?= $id ? 'Editar trem' : 'Cadastrar trem' ?></h1></div><a href="trens.php">← Voltar aos trens</a></div>
<?php if ($erros): ?><div class="aviso erro" role="alert"><ul><?php foreach ($erros as $erro): ?><li><?= escapar($erro) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form class="cartao formulario" method="post"><input type="hidden" name="csrf" value="<?= escapar(tokenCsrf()) ?>"><input type="hidden" name="id_trem" value="<?= $id ? (int) $id : '' ?>">
<label for="prefixo">Prefixo</label><input id="prefixo" name="prefixo" maxlength="6" pattern="TR-[0-9]{3}" placeholder="TR-204" value="<?= escapar($dados['prefixo']) ?>" required>
<label for="modelo">Modelo</label><input id="modelo" name="modelo" maxlength="120" value="<?= escapar($dados['modelo']) ?>" required>
<div class="grade-form"><div><label for="ano">Ano</label><input id="ano" name="ano" type="number" min="1900" max="2100" value="<?= escapar($dados['ano']) ?>" required></div><div><label for="capacidade_toneladas">Capacidade (toneladas)</label><input id="capacidade_toneladas" name="capacidade_toneladas" type="number" min="0.01" max="999999.99" step="0.01" value="<?= escapar($dados['capacidade_toneladas']) ?>" required></div></div>
<div class="grade-form"><div><label for="status">Status</label><select id="status" name="status" required><?php foreach (['normal'=>'Normal','atencao'=>'Atenção','critico'=>'Crítico'] as $valor=>$rotulo): ?><option value="<?= $valor ?>" <?= $dados['status'] === $valor ? 'selected' : '' ?>><?= $rotulo ?></option><?php endforeach; ?></select></div><div><label for="ultima_inspecao">Última inspeção (opcional)</label><input id="ultima_inspecao" name="ultima_inspecao" type="date" value="<?= escapar($dados['ultima_inspecao']) ?>"></div></div>
<div class="acoes"><a href="trens.php">Cancelar</a><button type="submit">Salvar trem</button></div></form></main></body></html>
