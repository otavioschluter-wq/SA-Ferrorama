<?php
require __DIR__ . '/includes/proteger.php';
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Início | A-TRAIN</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body>
    <?php require __DIR__ . '/includes/cabecalho.php'; ?>
    <main class="cartao conteudo">
        <h1>Boas-vindas, <?= escapar($_SESSION['nome'] ?? $_SESSION['login']) ?></h1>
        <p class="descricao">Selecione uma opção no menu para continuar.</p>
    </main>
</body>
</html>
