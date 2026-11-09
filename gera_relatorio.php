<?php
require __DIR__ . '/includes/proteger.php';
require __DIR__ . '/includes/permissao.php';
exigirPapel(['administrador', 'gestor']);
require __DIR__ . '/includes/seguranca.php';
require __DIR__ . '/config/conexao.php';
require __DIR__ . '/includes/banco.php';

$trens = buscar($conexao, 'SELECT id_trem, prefixo FROM trens ORDER BY prefixo');
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Gerar relatório | A-TRAIN</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body>
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
<main class="pagina">
    <div class="titulo-pagina"><div><p class="sobretitulo">Relatórios</p><h1>Gerar relatório</h1></div></div>
    <form class="cartao formulario" method="get" action="visualiza_relatorio.php">
        <label for="tipo">Tipo de relatório</label>
        <select name="tipo" id="tipo" required>
            <option value="desempenho">Desempenho</option>
            <option value="consumo">Consumo de energia</option>
            <option value="eficiencia">Eficiência operacional</option>
            <option value="manutencao">Manutenção</option>
        </select>
        <div class="grade-form">
            <div><label for="inicio">Início do período</label><input type="date" name="inicio" id="inicio" value="<?= date('Y-m-d', strtotime('-30 days')) ?>" required></div>
            <div><label for="fim">Fim do período</label><input type="date" name="fim" id="fim" value="<?= date('Y-m-d') ?>" required></div>
            <div><label for="trem">Trem</label>
                <select name="trem" id="trem"><option value="">Todos</option><?php foreach ($trens as $trem): ?><option value="<?= (int) $trem['id_trem'] ?>"><?= escapar($trem['prefixo']) ?></option><?php endforeach; ?></select></div>
            <div><label for="status">Status do trem</label>
                <select name="status" id="status"><option value="">Todos</option><option value="normal">Normal</option><option value="atencao">Atenção</option><option value="critico">Crítico</option></select></div>
        </div>
        <div class="acoes"><button type="submit">Gerar relatório</button></div>
    </form>
</main>
</body>
</html>
