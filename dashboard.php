<?php
require __DIR__ . '/includes/proteger.php';
require __DIR__ . '/includes/permissao.php';
require __DIR__ . '/includes/seguranca.php';
require __DIR__ . '/includes/rotas_dados.php';
require __DIR__ . '/config/conexao.php';
require __DIR__ . '/includes/banco.php';

$frotaInteira = temPapel(['administrador', 'gestor']);
$restrito = temPapel(['maquinista']);
$idTrem = $restrito ? tremDoMaquinista($conexao) : 0;
$filtroTrem = $restrito ? ' AND s.id_trem = ?' : '';
$tiposTrem = $restrito ? 'i' : '';
$valoresTrem = $restrito ? [$idTrem] : [];
$janela = date('Y-m-d H:i:s', time() - 86400);

$viagens = buscar(
    $conexao,
    "SELECT t.prefixo, r.origem, r.destino, r.hora_partida FROM rotas r INNER JOIN trens t ON t.id_trem = r.id_trem WHERE r.status = 'em_andamento'" . ($restrito ? ' AND r.id_trem = ?' : '') . ' ORDER BY r.hora_partida',
    $tiposTrem,
    $valoresTrem
);

if ($frotaInteira || $restrito) {
    $totalLeituras = (int) buscar($conexao, 'SELECT COUNT(*) AS n FROM leituras l INNER JOIN sensores s ON s.id_sensor = l.id_sensor WHERE 1 = 1' . $filtroTrem, $tiposTrem, $valoresTrem)[0]['n'];
    $alertas = buscar(
        $conexao,
        'SELECT a.severidade, COUNT(*) AS quantidade FROM alertas a INNER JOIN leituras l ON l.id_leitura = a.id_leitura INNER JOIN sensores s ON s.id_sensor = l.id_sensor WHERE a.data_hora >= ?' . $filtroTrem . ' GROUP BY a.severidade ORDER BY quantidade DESC',
        's' . $tiposTrem,
        array_merge([$janela], $valoresTrem)
    );
    $criticos = buscar(
        $conexao,
        "SELECT s.codigo, t.prefixo, l.valor, l.unidade FROM sensores s INNER JOIN trens t ON t.id_trem = s.id_trem INNER JOIN leituras l ON l.id_sensor = s.id_sensor INNER JOIN alertas a ON a.id_leitura = l.id_leitura WHERE a.severidade = 'critico' AND l.id_leitura = (SELECT l2.id_leitura FROM leituras l2 WHERE l2.id_sensor = s.id_sensor ORDER BY l2.data_hora DESC, l2.id_leitura DESC LIMIT 1)" . $filtroTrem . ' ORDER BY t.prefixo, s.codigo',
        $tiposTrem,
        $valoresTrem
    );
}
if ($frotaInteira) {
    $trens = buscar($conexao, 'SELECT status, COUNT(*) AS quantidade FROM trens GROUP BY status ORDER BY quantidade DESC');
    $velocidade = buscar(
        $conexao,
        "SELECT ROUND(AVG(l.valor), 1) AS media FROM leituras l INNER JOIN sensores s ON s.id_sensor = l.id_sensor WHERE s.tipo = ? AND l.data_hora >= ?",
        'ss',
        ['velocidade', $janela]
    )[0]['media'];
}
$rotuloStatus = ['normal' => 'Normal', 'atencao' => 'Atenção', 'critico' => 'Crítico'];
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Painel | A-TRAIN</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body>
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
<main class="pagina">
    <div class="titulo-pagina"><div><p class="sobretitulo">Painel</p><h1><?= $frotaInteira ? 'Frota inteira' : ($restrito ? 'Meu trem' : 'Viagens') ?></h1></div></div>
    <?php if ($restrito && !$idTrem): ?><p class="aviso erro">Nenhum trem atribuído a este maquinista.</p><?php endif; ?>
    <div class="indicadores">
        <?php if ($frotaInteira): ?>
        <section class="cartao indicador"><h2>Trens por status</h2>
            <?php if (!$trens): ?><p class="sem-dados">Sem dados</p><?php endif; ?>
            <ul><?php foreach ($trens as $linha): ?><li><span class="estado estado-<?= escapar($linha['status']) ?>"><?= escapar($rotuloStatus[$linha['status']]) ?></span> <strong><?= (int) $linha['quantidade'] ?></strong></li><?php endforeach; ?></ul>
        </section>
        <?php endif; ?>
        <?php if ($frotaInteira || $restrito): ?>
        <section class="cartao indicador"><h2>Alertas ativos por severidade</h2><p class="ajuda">Últimas 24 horas</p>
            <?php if (!$totalLeituras): ?><p class="sem-dados">Sem dados</p>
            <?php elseif (!$alertas): ?><p class="sem-dados">Nenhum alerta ativo</p><?php endif; ?>
            <ul><?php foreach ($alertas as $linha): ?><li><span class="estado estado-<?= escapar($linha['severidade']) ?>"><?= escapar($rotuloStatus[$linha['severidade']]) ?></span> <strong><?= (int) $linha['quantidade'] ?></strong></li><?php endforeach; ?></ul>
        </section>
        <section class="cartao indicador"><h2>Sensores com leitura crítica</h2><p class="ajuda">Última leitura de cada sensor</p>
            <?php if (!$totalLeituras): ?><p class="sem-dados">Sem dados</p><?php else: ?><p class="numero"><?= count($criticos) ?></p><?php endif; ?>
            <ul><?php foreach ($criticos as $linha): ?><li><?= escapar($linha['codigo']) ?> (<?= escapar($linha['prefixo']) ?>) <strong><?= escapar(number_format((float) $linha['valor'], 2, ',', '.')) ?> <?= escapar($linha['unidade']) ?></strong></li><?php endforeach; ?></ul>
        </section>
        <?php endif; ?>
        <section class="cartao indicador"><h2>Viagens em andamento</h2>
            <p class="numero"><?= count($viagens) ?></p>
            <ul><?php foreach ($viagens as $linha): ?><li><?= escapar($linha['prefixo']) ?>: <?= escapar($linha['origem']) ?> para <?= escapar($linha['destino']) ?> <strong><?= escapar(substr($linha['hora_partida'], 0, 5)) ?></strong></li><?php endforeach; ?></ul>
        </section>
        <?php if ($frotaInteira): ?>
        <section class="cartao indicador"><h2>Velocidade média da frota</h2><p class="ajuda">Últimas 24 horas</p>
            <?php if ($velocidade === null): ?><p class="sem-dados">Sem dados</p><?php else: ?><p class="numero"><?= escapar(number_format((float) $velocidade, 1, ',', '.')) ?> km/h</p><?php endif; ?>
        </section>
        <?php endif; ?>
    </div>
</main>
</body>
</html>
