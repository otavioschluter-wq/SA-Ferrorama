<?php
require_once __DIR__ . '/permissao.php';
require_once __DIR__ . '/seguranca.php';
$raiz = str_contains($_SERVER['SCRIPT_NAME'] ?? '', '/usuarios/') ? '../' : '';
?>
<header class="topo topo-interno">
    <a class="marca" href="<?= $raiz ?>principal.php">A-TRAIN</a>
    <nav class="menu" aria-label="Navegação principal">
        <a href="<?= $raiz ?>dashboard.php">Painel</a>
        <?php if (temPapel(['administrador', 'gestor'])): ?><a href="<?= $raiz ?>trens.php">Trens</a><?php endif; ?>
        <?php if (temPapel(['administrador', 'gestor', 'maquinista'])): ?><a href="<?= $raiz ?>sensores.php">Sensores</a><?php endif; ?>
        <?php if (temPapel(['administrador', 'gestor', 'maquinista'])): ?><a href="<?= $raiz ?>rotas.php">Rotas</a><?php endif; ?>
        <?php if (temPapel(['administrador', 'gestor', 'maquinista'])): ?><a href="<?= $raiz ?>leituras.php">Leituras</a><?php endif; ?>
        <?php if (temPapel(['administrador', 'gestor', 'maquinista'])): ?><a href="<?= $raiz ?>notificacoes_alertas.php">Alertas</a><?php endif; ?>
        <?php if (temPapel(['administrador', 'gestor'])): ?><a href="<?= $raiz ?>simulador.php">Simulador</a><?php endif; ?>
        <?php if (temPapel(['administrador', 'gestor'])): ?><a href="<?= $raiz ?>gera_relatorio.php">Relatórios</a><?php endif; ?>
        <?php if (temPapel(['administrador'])): ?><a href="<?= $raiz ?>usuarios.php">Usuários</a><?php endif; ?>
        <?php if (temPapel(['administrador'])): ?><a href="<?= $raiz ?>usuarios/atribuir_trem.php">Atribuir trem</a><?php endif; ?>
    </nav>
    <div class="conta"><span><?= escapar($_SESSION['nome'] ?? $_SESSION['login']) ?> <small>(<?= escapar($_SESSION['papel']) ?>)</small></span>
        <form action="<?= $raiz ?>sair.php" method="post"><input type="hidden" name="csrf" value="<?= escapar(tokenCsrf()) ?>"><button class="sair" type="submit">Sair</button></form>
    </div>
</header>
