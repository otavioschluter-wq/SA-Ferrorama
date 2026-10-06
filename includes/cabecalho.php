<?php
require_once __DIR__ . '/permissao.php';
require_once __DIR__ . '/seguranca.php';
$raiz = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/usuarios/') ? '../' : '';
?>
<header class="topo topo-interno">
    <a class="marca" href="<?= $raiz ?>principal.php">A-TRAIN</a>
    <nav class="menu" aria-label="Navegação principal">
        <a href="<?= $raiz ?>principal.php">Início</a>
        <?php if (temPapel(['administrador', 'gestor'])): ?><a href="<?= $raiz ?>trens.php">Trens</a><?php endif; ?>
        <?php if (temPapel(['administrador', 'gestor', 'maquinista'])): ?><a href="<?= $raiz ?>sensores.php">Sensores</a><?php endif; ?>
        <?php if (temPapel(['administrador'])): ?><a href="<?= $raiz ?>usuarios/cadastrar.php">Cadastrar usuário</a><?php endif; ?>
        <?php if (temPapel(['administrador'])): ?><a href="<?= $raiz ?>usuarios/atribuir_trem.php">Atribuir trem</a><?php endif; ?>
    </nav>
    <div class="conta"><span><?= escapar($_SESSION['nome'] ?? $_SESSION['login']) ?> <small>(<?= escapar($_SESSION['papel']) ?>)</small></span>
        <form action="<?= $raiz ?>sair.php" method="post"><input type="hidden" name="csrf" value="<?= escapar(tokenCsrf()) ?>"><button class="sair" type="submit">Sair</button></form>
    </div>
</header>
