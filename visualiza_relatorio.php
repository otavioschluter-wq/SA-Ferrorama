<?php
require __DIR__ . '/includes/proteger.php';
require __DIR__ . '/includes/permissao.php';
exigirPapel(['administrador', 'gestor']);
require __DIR__ . '/includes/seguranca.php';
require __DIR__ . '/includes/rotas_dados.php';
require __DIR__ . '/config/conexao.php';
require __DIR__ . '/includes/banco.php';

$relatorios = [
    'desempenho' => [
        'titulo' => 'Desempenho',
        'colunas' => ['Trem', 'Velocidade média (km/h)', 'Velocidade máxima (km/h)'],
        'campos' => 'ROUND(AVG(l.valor), 1) AS c1, MAX(l.valor) AS c2',
        'origem' => 'leituras l INNER JOIN sensores s ON s.id_sensor = l.id_sensor INNER JOIN trens t ON t.id_trem = s.id_trem',
        'filtro' => "s.tipo = 'velocidade'",
        'data' => 'l.data_hora',
    ],
    'consumo' => [
        'titulo' => 'Consumo de energia',
        'colunas' => ['Trem', 'Consumo total (kWh)', 'Consumo médio (kWh)'],
        'campos' => 'SUM(l.valor) AS c1, ROUND(AVG(l.valor), 1) AS c2',
        'origem' => 'leituras l INNER JOIN sensores s ON s.id_sensor = l.id_sensor INNER JOIN trens t ON t.id_trem = s.id_trem',
        'filtro' => "s.tipo = 'energia'",
        'data' => 'l.data_hora',
    ],
    'eficiencia' => [
        'titulo' => 'Eficiência operacional',
        'colunas' => ['Trem', 'Viagens concluídas', 'Viagens canceladas', 'Viagens atrasadas'],
        'campos' => "SUM(r.status = 'concluida') AS c1, SUM(r.status = 'cancelada') AS c2, SUM(r.status = 'atrasada') AS c3",
        'origem' => 'rotas r INNER JOIN trens t ON t.id_trem = r.id_trem',
        'filtro' => "r.status IN ('concluida', 'cancelada', 'atrasada')",
        'data' => 'r.data_viagem',
    ],
    'manutencao' => [
        'titulo' => 'Manutenção',
        'colunas' => ['Trem', 'Alertas de atenção', 'Alertas críticos', 'Última inspeção'],
        'campos' => "SUM(a.severidade = 'atencao') AS c1, SUM(a.severidade = 'critico') AS c2, MAX(t.ultima_inspecao) AS c3",
        'origem' => 'alertas a INNER JOIN leituras l ON l.id_leitura = a.id_leitura INNER JOIN sensores s ON s.id_sensor = l.id_sensor INNER JOIN trens t ON t.id_trem = s.id_trem',
        'filtro' => '1 = 1',
        'data' => 'a.data_hora',
    ],
];

function parametro(string $nome): string
{
    return is_string($_GET[$nome] ?? null) ? trim($_GET[$nome]) : '';
}

$tipo = parametro('tipo');
$inicio = parametro('inicio');
$fim = parametro('fim');
$idTrem = parametro('trem');
$status = parametro('status');
$validos = isset($relatorios[$tipo]) && dataValida($inicio) && dataValida($fim) && $inicio <= $fim
    && ($idTrem === '' || filter_var($idTrem, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) !== false)
    && in_array($status, ['', 'normal', 'atencao', 'critico'], true);
if (!$validos) {
    http_response_code(400);
    exit('Parametros invalidos. <a href="gera_relatorio.php">Voltar</a>');
}

$modelo = $relatorios[$tipo];
$sql = "SELECT t.prefixo, {$modelo['campos']} FROM {$modelo['origem']} WHERE {$modelo['filtro']} AND DATE({$modelo['data']}) BETWEEN ? AND ?";
$tipos = 'ss';
$valores = [$inicio, $fim];
if ($idTrem !== '') {
    $sql .= ' AND t.id_trem = ?';
    $tipos .= 'i';
    $valores[] = (int) $idTrem;
}
if ($status !== '') {
    $sql .= ' AND t.status = ?';
    $tipos .= 's';
    $valores[] = $status;
}
$sql .= ' GROUP BY t.id_trem, t.prefixo ORDER BY t.prefixo';
$linhas = array_map('array_values', buscar($conexao, $sql, $tipos, $valores));

if (parametro('exportar') === 'csv') {
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=relatorio_' . $tipo . '.csv');
    $saida = fopen('php://output', 'w');
    fwrite($saida, "\xEF\xBB\xBF");
    fputcsv($saida, $modelo['colunas'], ';', '"', '');
    foreach ($linhas as $linha) {
        fputcsv($saida, $linha, ';', '"', '');
    }
    fclose($saida);
    exit;
}

$registro = $conexao->prepare('INSERT INTO relatorios (id_usuario, tipo, periodo_inicio, periodo_fim, gerado_em) VALUES (?, ?, ?, ?, NOW())');
$registro->bind_param('isss', $_SESSION['id'], $tipo, $inicio, $fim);
$registro->execute();
$registro->close();

$maximo = 0.0;
foreach ($linhas as $linha) {
    $maximo = max($maximo, (float) $linha[1]);
}
$nomeTrem = 'Todos';
if ($idTrem !== '') {
    $nomeTrem = buscar($conexao, 'SELECT prefixo FROM trens WHERE id_trem = ?', 'i', [(int) $idTrem])[0]['prefixo'] ?? 'Inexistente';
}
$rotuloStatus = ['' => 'Todos', 'normal' => 'Normal', 'atencao' => 'Atenção', 'critico' => 'Crítico'][$status];
$consultaExportar = http_build_query(['tipo' => $tipo, 'inicio' => $inicio, 'fim' => $fim, 'trem' => $idTrem, 'status' => $status, 'exportar' => 'csv']);
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Relatório | A-TRAIN</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body>
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
<main class="pagina">
    <div class="titulo-pagina"><div><p class="sobretitulo">Relatório</p><h1><?= escapar($modelo['titulo']) ?></h1></div>
        <div class="sem-impressao"><a class="botao-link" href="?<?= escapar($consultaExportar) ?>">Exportar CSV</a> <button type="button" onclick="window.print()">Imprimir</button> <a href="gera_relatorio.php">Novo relatório</a></div></div>
    <section class="cartao filtros-aplicados">
        <strong>Filtros aplicados</strong>
        <p>Período: <?= escapar(date('d/m/Y', strtotime($inicio))) ?> a <?= escapar(date('d/m/Y', strtotime($fim))) ?> | Trem: <?= escapar($nomeTrem) ?> | Status do trem: <?= escapar($rotuloStatus) ?></p>
    </section>
    <?php if (!$linhas): ?><p class="cartao vazio">Sem dados para os filtros informados.</p><?php else: ?>
    <div class="lista"><table>
        <thead><tr><?php foreach ($modelo['colunas'] as $coluna): ?><th><?= escapar($coluna) ?></th><?php endforeach; ?></tr></thead>
        <tbody><?php foreach ($linhas as $linha): ?><tr><?php foreach ($linha as $i => $celula): ?><td data-label="<?= escapar($modelo['colunas'][$i]) ?>"><?= escapar((string) $celula) ?></td><?php endforeach; ?></tr><?php endforeach; ?></tbody>
    </table></div>
    <section class="cartao grafico">
        <strong><?= escapar($modelo['colunas'][1]) ?> por trem</strong>
        <?php foreach ($linhas as $linha): ?>
        <div class="barra-linha"><span><?= escapar($linha[0]) ?></span><div class="barra-fundo"><div class="barra" style="width: <?= $maximo > 0 ? round((float) $linha[1] / $maximo * 100, 1) : 0 ?>%"></div></div><span><?= escapar((string) $linha[1]) ?></span></div>
        <?php endforeach; ?>
    </section>
    <?php endif; ?>
</main>
</body>
</html>
