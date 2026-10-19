<?php
require __DIR__ . '/includes/proteger.php';
require __DIR__ . '/includes/permissao.php';
exigirPapel(['administrador', 'gestor']);
require __DIR__ . '/includes/seguranca.php';
require __DIR__ . '/includes/limites.php';
require __DIR__ . '/config/conexao.php';

$erros = []; $mensagem = '';
$idSensor = is_string($_POST['id_sensor'] ?? null) ? $_POST['id_sensor'] : '';
$quantidade = is_string($_POST['quantidade'] ?? null) ? $_POST['quantidade'] : '1';
$foraDoLimite = isset($_POST['fora_do_limite']);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $id = filter_var($idSensor, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $total = filter_var($quantidade, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);
    if (!$id) { $erros[] = 'Selecione um sensor válido.'; }
    if (!$total) { $erros[] = 'Informe uma quantidade de 1 a 100.'; }
    if (!$erros) {
        $consulta = $conexao->prepare('SELECT id_sensor, codigo, tipo FROM sensores WHERE id_sensor=?');
        $consulta->bind_param('i', $id); $consulta->execute();
        $sensor = $consulta->get_result()->fetch_assoc(); $consulta->close();
        if (!$sensor || !isset(limitesSensor()[$sensor['tipo']])) { $erros[] = 'O sensor selecionado não existe ou não tem tipo suportado.'; }
    }
    if (!$erros) {
        try {
            $conexao->begin_transaction();
            $inserirLeitura = $conexao->prepare('INSERT INTO leituras (id_sensor, data_hora, valor, unidade) VALUES (?, ?, ?, ?)');
            $inserirAlerta = $conexao->prepare('INSERT INTO alertas (id_leitura, tipo, severidade, mensagem, data_hora) VALUES (?, ?, ?, ?, ?)');
            $tipoSensor = $sensor['tipo'];
            $unidade = limitesSensor()[$tipoSensor]['unidade'];
            $alertasGerados = 0;
            for ($i = $total - 1; $i >= 0; $i--) {
                $dataHora = date('Y-m-d H:i:s', time() - ($i * 300));
                $valor = gerarValorLeitura($tipoSensor, $foraDoLimite);
                $inserirLeitura->bind_param('isds', $id, $dataHora, $valor, $unidade);
                $inserirLeitura->execute();
                $severidade = classificarLeitura($tipoSensor, $valor);
                if ($severidade !== 'normal') {
                    $idLeitura = $conexao->insert_id;
                    $texto = sprintf('%s: %.2f %s acima do limite de %.2f %s.', $sensor['codigo'], $valor, $unidade, limitesSensor()[$tipoSensor]['limite'], $unidade);
                    $inserirAlerta->bind_param('issss', $idLeitura, $tipoSensor, $severidade, $texto, $dataHora);
                    $inserirAlerta->execute();
                    $alertasGerados++;
                }
            }
            $inserirLeitura->close(); $inserirAlerta->close();
            $conexao->commit();
            $mensagem = $total . ' leitura(s) gravada(s); ' . $alertasGerados . ' alerta(s) gerado(s).';
        } catch (Throwable $falha) {
            $conexao->rollback();
            $erros[] = 'Não foi possível gerar as leituras. Nenhuma alteração foi gravada.';
        }
    }
}
$sensores = $conexao->query('SELECT s.id_sensor, s.codigo, s.tipo, t.prefixo FROM sensores s INNER JOIN trens t ON t.id_trem=s.id_trem ORDER BY t.prefixo, s.codigo')->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Simulador | A-TRAIN</title><link rel="stylesheet" href="assets/css/estilo.css"></head><body>
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
<main class="pagina"><div class="titulo-pagina"><div><p class="sobretitulo">Monitoramento</p><h1>Simulador de leituras</h1><p class="descricao">Gere valores plausíveis e teste alertas fora dos limites.</p></div><a href="leituras.php">← Ver leituras</a></div>
<?php if ($erros): ?><div class="aviso erro" role="alert"><ul><?php foreach ($erros as $erro): ?><li><?= escapar($erro) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($mensagem): ?><p class="aviso sucesso" role="status"><?= escapar($mensagem) ?></p><?php endif; ?>
<form class="cartao formulario" method="post"><input type="hidden" name="csrf" value="<?= escapar(tokenCsrf()) ?>"><label for="id_sensor">Sensor</label><select id="id_sensor" name="id_sensor" required><option value="">Selecione um sensor</option><?php foreach ($sensores as $sensor): ?><option value="<?= (int) $sensor['id_sensor'] ?>" <?= $idSensor === (string) $sensor['id_sensor'] ? 'selected' : '' ?>><?= escapar($sensor['codigo'] . ' — ' . $sensor['prefixo'] . ' (' . $sensor['tipo'] . ')') ?></option><?php endforeach; ?></select><label for="quantidade">Quantidade de leituras (1 a 100)</label><input id="quantidade" name="quantidade" type="number" min="1" max="100" value="<?= escapar($quantidade) ?>" required><label class="opcao"><input type="checkbox" name="fora_do_limite" value="1" <?= $foraDoLimite ? 'checked' : '' ?>> Gerar valores acima do limite</label><div class="acoes"><a href="leituras.php">Cancelar</a><button type="submit">Gerar leituras</button></div></form></main></body></html>
