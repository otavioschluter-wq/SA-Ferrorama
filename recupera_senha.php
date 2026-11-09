<?php
session_start();
function escapar(string $valor): string
{
    return htmlspecialchars($valor, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

require __DIR__ . '/config/conexao.php';
require __DIR__ . '/includes/banco.php';

$erro = '';
$sucesso = false;
$codigo = '';
$login = '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $etapa = is_string($_POST['etapa'] ?? null) ? $_POST['etapa'] : '';
    if ($etapa === 'solicitar') {
        $login = is_string($_POST['login'] ?? null) ? trim($_POST['login']) : '';
        if ($login === '' || mb_strlen($login) > 80) {
            $erro = 'Informe o login.';
        } else {
            $codigo = strtoupper(bin2hex(random_bytes(4)));
            $existe = buscar($conexao, 'SELECT id FROM usuarios WHERE login = ? AND ativo = 1', 's', [$login]);
            unset($_SESSION['recuperacao']);
            if ($existe) {
                $_SESSION['recuperacao'] = ['login' => $login, 'hash' => password_hash($codigo, PASSWORD_DEFAULT), 'expira' => time() + 600];
            }
        }
    } elseif ($etapa === 'redefinir') {
        $informado = is_string($_POST['codigo'] ?? null) ? strtoupper(trim($_POST['codigo'])) : '';
        $senha = is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '';
        $confirmacao = is_string($_POST['confirmacao'] ?? null) ? $_POST['confirmacao'] : '';
        $pedido = $_SESSION['recuperacao'] ?? null;
        if (strlen($senha) < 8 || strlen($senha) > 255) {
            $erro = 'Informe uma senha de 8 a 255 caracteres.';
        } elseif ($senha !== $confirmacao) {
            $erro = 'A confirmação da senha não confere.';
        } elseif (!$pedido || $pedido['expira'] < time() || !password_verify($informado, $pedido['hash'])) {
            $erro = 'Código inválido ou expirado.';
        } else {
            $hash = password_hash($senha, PASSWORD_DEFAULT);
            $atualiza = $conexao->prepare('UPDATE usuarios SET senha = ? WHERE login = ? AND ativo = 1');
            $atualiza->bind_param('ss', $hash, $pedido['login']);
            $atualiza->execute();
            $atualiza->close();
            unset($_SESSION['recuperacao']);
            $sucesso = true;
        }
    }
}
$aguardandoCodigo = isset($_SESSION['recuperacao']) && !$sucesso;
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Recuperar senha | A-TRAIN</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body class="centralizado">
    <main class="cartao acesso">
        <span class="marca">A-TRAIN</span>
        <h1>Recuperar senha</h1>
        <?php if ($erro !== ''): ?><p class="aviso erro" role="alert"><?= escapar($erro) ?></p><?php endif; ?>
        <?php if ($sucesso): ?>
            <p class="aviso sucesso" role="status">Senha redefinida.</p>
            <p class="acesso-registro"><a href="index.php">Entrar</a></p>
        <?php elseif ($codigo !== ''): ?>
            <p class="aviso sucesso" role="status">Código de recuperação (simula o envio por e-mail): <strong><?= escapar($codigo) ?></strong></p>
        <?php endif; ?>
        <?php if (!$sucesso && ($aguardandoCodigo || $codigo !== '')): ?>
        <form method="post">
            <input type="hidden" name="etapa" value="redefinir">
            <label for="codigo">Código</label><input id="codigo" name="codigo" maxlength="8" required autocomplete="off">
            <label for="senha">Nova senha</label><input id="senha" name="senha" type="password" minlength="8" required autocomplete="new-password">
            <label for="confirmacao">Confirmar senha</label><input id="confirmacao" name="confirmacao" type="password" minlength="8" required autocomplete="new-password">
            <button type="submit">Redefinir senha</button>
        </form>
        <?php elseif (!$sucesso): ?>
        <form method="post">
            <input type="hidden" name="etapa" value="solicitar">
            <label for="login">Login</label><input id="login" name="login" maxlength="80" required autocomplete="username">
            <button type="submit">Gerar código</button>
        </form>
        <?php endif; ?>
        <p class="acesso-registro"><a href="index.php">Voltar ao login</a></p>
    </main>
</body>
</html>
