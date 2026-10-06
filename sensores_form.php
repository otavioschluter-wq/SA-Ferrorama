<?php
require __DIR__ . '/includes/proteger.php';
require __DIR__ . '/includes/permissao.php';
exigirPapel(['administrador', 'gestor']);
require __DIR__ . '/includes/seguranca.php';
require __DIR__ . '/includes/sensores_dados.php';
require __DIR__ . '/config/conexao.php';

$id = filter_var($_GET['id'] ?? ($_POST['id_sensor'] ?? null), FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) ?: null;
if ((isset($_GET['id']) && !$id) || (isset($_POST['id_sensor']) && $_POST['id_sensor'] !== '' && !$id)) { http_response_code(400); exit('Identificador de sensor inválido.'); }
$dados = ['id_trem'=>'', 'codigo'=>'', 'tipo'=>'temperatura', 'localizacao'=>'', 'segmento'=>'', 'ultima_leitura'=>'normal'];
$erros = [];
$trens = $conexao->query('SELECT id_trem, prefixo, modelo FROM trens ORDER BY prefixo')->fetch_all(MYSQLI_ASSOC);
if ($id && $_SERVER['REQUEST_METHOD'] !== 'POST') {
    $consulta = $conexao->prepare('SELECT id_trem, codigo, tipo, localizacao, segmento, ultima_leitura FROM sensores WHERE id_sensor = ?');
    $consulta->bind_param('i', $id); $consulta->execute();
    $registro = $consulta->get_result()->fetch_assoc(); $consulta->close();
    if (!$registro) { http_response_code(404); exit('Sensor não encontrado.'); }
    foreach ($dados as $campo=>$_) { $dados[$campo] = (string) $registro[$campo]; }
}
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    foreach ($dados as $campo=>$_) { $dados[$campo] = is_string($_POST[$campo] ?? null) ? trim($_POST[$campo]) : ''; }
    $dados['codigo'] = strtoupper($dados['codigo']);
    $erros = validarSensor($dados);
    $idTrem = filter_var($dados['id_trem'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if (!$idTrem) { $erros[] = 'Selecione um trem válido.'; }
    else {
        $consulta = $conexao->prepare('SELECT id_trem FROM trens WHERE id_trem=?');
        $consulta->bind_param('i', $idTrem); $consulta->execute();
        if (!$consulta->get_result()->fetch_assoc()) { $erros[] = 'O trem selecionado não existe.'; }
        $consulta->close();
    }
    if (!$erros) {
        $consulta = $conexao->prepare('SELECT id_sensor FROM sensores WHERE codigo = ? AND (? IS NULL OR id_sensor <> ?) LIMIT 1');
        $consulta->bind_param('sii', $dados['codigo'], $id, $id); $consulta->execute();
        if ($consulta->get_result()->fetch_assoc()) { $erros[] = 'Este código de sensor já está cadastrado.'; }
        $consulta->close();
    }
    if (!$erros) {
        try {
            if ($id) {
                $consulta = $conexao->prepare('UPDATE sensores SET id_trem=?, codigo=?, tipo=?, localizacao=?, segmento=?, ultima_leitura=? WHERE id_sensor=?');
                $consulta->bind_param('isssssi', $idTrem, $dados['codigo'], $dados['tipo'], $dados['localizacao'], $dados['segmento'], $dados['ultima_leitura'], $id);
            } else {
                $consulta = $conexao->prepare('INSERT INTO sensores (id_trem, codigo, tipo, localizacao, segmento, ultima_leitura) VALUES (?, ?, ?, ?, ?, ?)');
                $consulta->bind_param('isssss', $idTrem, $dados['codigo'], $dados['tipo'], $dados['localizacao'], $dados['segmento'], $dados['ultima_leitura']);
            }
            $consulta->execute(); $afetadas = $consulta->affected_rows; $consulta->close();
            if ($id && !$afetadas) {
                $verifica = $conexao->prepare('SELECT id_sensor FROM sensores WHERE id_sensor=?');
                $verifica->bind_param('i', $id); $verifica->execute();
                if (!$verifica->get_result()->fetch_assoc()) { http_response_code(404); exit('Sensor não encontrado.'); }
            }
            header('Location: sensores.php?mensagem=salvo', true, 303); exit;
        } catch (mysqli_sql_exception $falha) {
            $erros[] = $falha->getCode() === 1062 ? 'Este código de sensor já está cadastrado.' : ($falha->getCode() === 1452 ? 'O trem selecionado não existe.' : 'Não foi possível salvar o sensor.');
        }
    }
}
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title><?= $id ? 'Editar' : 'Cadastrar' ?> sensor | A-TRAIN</title><link rel="stylesheet" href="assets/css/estilo.css"></head><body>
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
<main class="pagina"><div class="titulo-pagina"><div><p class="sobretitulo">Gestão de sensores</p><h1><?= $id ? 'Editar sensor' : 'Cadastrar sensor' ?></h1></div><a href="sensores.php">← Voltar aos sensores</a></div>
<?php if ($erros): ?><div class="aviso erro" role="alert"><ul><?php foreach ($erros as $erro): ?><li><?= escapar($erro) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<form class="cartao formulario" method="post"><input type="hidden" name="csrf" value="<?= escapar(tokenCsrf()) ?>"><input type="hidden" name="id_sensor" value="<?= $id ? (int) $id : '' ?>">
<label for="codigo">Código</label><input id="codigo" name="codigo" maxlength="20" placeholder="S-TEMP-001" value="<?= escapar($dados['codigo']) ?>" required>
<div class="grade-form"><div><label for="tipo">Tipo</label><select id="tipo" name="tipo" required><?php foreach (tiposSensor() as $valor=>$rotulo): ?><option value="<?= $valor ?>" <?= $dados['tipo'] === $valor ? 'selected' : '' ?>><?= $rotulo ?></option><?php endforeach; ?></select></div><div><label for="id_trem">Trem</label><select id="id_trem" name="id_trem" required><option value="">Selecione um trem</option><?php foreach ($trens as $trem): ?><option value="<?= (int) $trem['id_trem'] ?>" <?= $dados['id_trem'] === (string) $trem['id_trem'] ? 'selected' : '' ?>><?= escapar($trem['prefixo'] . ' — ' . $trem['modelo']) ?></option><?php endforeach; ?></select></div></div>
<div class="grade-form"><div><label for="localizacao">Localização no trem</label><input id="localizacao" name="localizacao" maxlength="80" placeholder="Ex.: motor" value="<?= escapar($dados['localizacao']) ?>" required></div><div><label for="segmento">Segmento</label><input id="segmento" name="segmento" maxlength="80" placeholder="Ex.: trecho Norte" value="<?= escapar($dados['segmento']) ?>" required></div></div>
<label for="ultima_leitura">Indicador manual da última leitura</label><select id="ultima_leitura" name="ultima_leitura" required><?php foreach (['normal'=>'Normal','atencao'=>'Atenção','critico'=>'Crítico'] as $valor=>$rotulo): ?><option value="<?= $valor ?>" <?= $dados['ultima_leitura'] === $valor ? 'selected' : '' ?>><?= $rotulo ?></option><?php endforeach; ?></select><p class="ajuda">Este indicador é preenchido manualmente neste bloco.</p>
<div class="acoes"><a href="sensores.php">Cancelar</a><button type="submit">Salvar sensor</button></div></form></main></body></html>
