<?php
session_start();

if (isset($_SESSION['id'], $_SESSION['login'], $_SESSION['papel'])) {
    header('Location: principal.php');
    exit;
}

$cadastroConcluido = !empty($_SESSION['cadastro_concluido']);
unset($_SESSION['cadastro_concluido']);

$erro = '';
$login = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $login = is_string($_POST['login'] ?? null) ? trim($_POST['login']) : '';
    $senha = is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '';
    $erro = 'Login ou senha inválidos.';

    if ($login !== '' && $senha !== '' && mb_strlen($login) <= 80) {
        require __DIR__ . '/config/conexao.php';
        try {
            $consulta = $conexao->prepare('SELECT id, nome, login, senha, papel FROM usuarios WHERE login = ? LIMIT 1');
            $consulta->bind_param('s', $login);
            $consulta->execute();
            $registro = $consulta->get_result()->fetch_assoc();
            $consulta->close();
            if ($registro && password_verify($senha, $registro['senha']) && in_array($registro['papel'], ['administrador', 'maquinista', 'gestor'], true)) {
                session_regenerate_id(true);
                $_SESSION['id'] = (int) $registro['id'];
                $_SESSION['login'] = $registro['login'];
                $_SESSION['nome'] = $registro['nome'];
                $_SESSION['papel'] = $registro['papel'];
                header('Location: principal.php');
                exit;
            }
        } catch (mysqli_sql_exception $falha) {
            http_response_code(503);
            exit('Não foi possível consultar o banco de dados. Tente novamente mais tarde.');
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Entrar | A-TRAIN</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body class="centralizado">
    <main class="cartao acesso">
        <span class="marca">A-TRAIN</span>
        <h1>Acesse sua conta</h1>
        <p class="descricao">Mapeamento de ferrovias</p>
        <?php if ($cadastroConcluido): ?><p class="aviso sucesso" role="status">Conta criada com sucesso. Entre com seu login e senha.</p><?php endif; ?>
        <?php if ($erro !== ''): ?><p class="aviso erro" role="alert"><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></p><?php endif; ?>
        <form method="post">
            <label for="login">Login</label>
            <input id="login" name="login" type="text" maxlength="80" value="<?= htmlspecialchars($login, ENT_QUOTES, 'UTF-8') ?>" required autocomplete="username">
            <label for="senha">Senha</label>
            <input id="senha" name="senha" type="password" required autocomplete="current-password">
            <button type="submit">Entrar</button>
        </form>
        <p class="acesso-registro">Ainda não tem conta? <a href="usuarios/registrar.php">Criar conta</a></p>
    </main>
</body>
</html>
