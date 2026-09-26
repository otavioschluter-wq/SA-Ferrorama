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
    <header class="topo"><span class="marca">A-TRAIN</span><a href="logout.php">Sair</a></header>
    <main class="cartao conteudo">
        <h1>Boas-vindas, <?= htmlspecialchars($_SESSION['login'], ENT_QUOTES, 'UTF-8') ?></h1>
        <p class="descricao">Papel: <?= htmlspecialchars($_SESSION['papel'], ENT_QUOTES, 'UTF-8') ?></p>
        <?php if ($_SESSION['papel'] === 'administrador'): ?><a class="botao-link" href="usuarios/cadastrar.php">Cadastrar usuário</a><?php endif; ?>
    </main>
</body>
</html>
