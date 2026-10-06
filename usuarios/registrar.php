<?php
session_start();

if (isset($_SESSION['id'], $_SESSION['login'], $_SESSION['papel'])) {
    header('Location: ../principal.php');
    exit;
}

$login = '';
$nome = '';
$erros = [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $nome = is_string($_POST['nome'] ?? null) ? trim($_POST['nome']) : '';
    $login = is_string($_POST['login'] ?? null) ? trim($_POST['login']) : '';
    $senha = is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '';
    $confirmacao = is_string($_POST['confirmacao'] ?? null) ? $_POST['confirmacao'] : '';
    $papel = 'maquinista';

    if ($nome === '' || mb_strlen($nome) > 120) {
        $erros[] = 'Informe um nome de até 120 caracteres.';
    }

    if ($login === '' || mb_strlen($login) > 80) {
        $erros[] = 'Informe um login de até 80 caracteres.';
    }
    if (strlen($senha) < 8 || strlen($senha) > 255) {
        $erros[] = 'Informe uma senha de 8 a 255 caracteres.';
    }
    if ($senha !== $confirmacao) {
        $erros[] = 'A confirmação da senha não confere.';
    }

    if (!$erros) {
        require __DIR__ . '/../config/conexao.php';
        try {
            $consulta = $conexao->prepare('SELECT id FROM usuarios WHERE login = ? LIMIT 1');
            $consulta->bind_param('s', $login);
            $consulta->execute();
            $existe = $consulta->get_result()->fetch_assoc();
            $consulta->close();

            if ($existe) {
                $erros[] = 'Este login já está cadastrado.';
            } else {
                $hash = password_hash($senha, PASSWORD_DEFAULT);
                $insercao = $conexao->prepare('INSERT INTO usuarios (nome, login, senha, papel) VALUES (?, ?, ?, ?)');
                $insercao->bind_param('ssss', $nome, $login, $hash, $papel);
                $insercao->execute();
                $insercao->close();

                $_SESSION['cadastro_concluido'] = true;
                header('Location: ../index.php');
                exit;
            }
        } catch (mysqli_sql_exception $falha) {
            $erros[] = $falha->getCode() === 1062
                ? 'Este login já está cadastrado.'
                : 'Não foi possível criar a conta. Tente novamente mais tarde.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Criar conta | A-TRAIN</title>
    <link rel="stylesheet" href="../assets/css/estilo.css">
</head>
<body class="pagina-cadastro">
    <header class="topo cadastro-topo">
        <a class="marca cadastro-marca" href="../index.php">A-TRAIN</a>
        <a class="cadastro-voltar" href="../index.php">← Voltar ao login</a>
    </header>
    <main class="cadastro-layout">
        <section class="cadastro-intro" aria-labelledby="registro-titulo">
            <span class="cadastro-etiqueta">Primeiro acesso</span>
            <h1 id="registro-titulo">Criar conta</h1>
            <p>Cadastre seu login e uma senha para acessar o A-TRAIN.</p>
            <div class="cadastro-nota">
                <span class="cadastro-nota-icone" aria-hidden="true">✓</span>
                <p>Contas criadas por esta página recebem o papel de maquinista.</p>
            </div>
        </section>

        <section class="cartao cadastro-cartao" aria-labelledby="registro-formulario-titulo">
            <div class="cadastro-cartao-cabecalho">
                <span class="cadastro-passo">Nova conta</span>
                <h2 id="registro-formulario-titulo">Seus dados de acesso</h2>
                <p>Todos os campos são obrigatórios.</p>
            </div>

            <?php if ($erros): ?>
                <div class="aviso erro cadastro-aviso" role="alert">
                    <strong>Confira os dados informados:</strong>
                    <ul>
                        <?php foreach ($erros as $erro): ?>
                            <li><?= htmlspecialchars($erro, ENT_QUOTES, 'UTF-8') ?></li>
                        <?php endforeach; ?>
                    </ul>
                </div>
            <?php endif; ?>

            <form class="cadastro-formulario" method="post">
                <div class="cadastro-campo"><label for="nome">Nome</label><input id="nome" name="nome" maxlength="120" value="<?= htmlspecialchars($nome, ENT_QUOTES, 'UTF-8') ?>" required></div>
                <div class="cadastro-campo">
                    <label for="login">Login</label>
                    <input id="login" name="login" type="text" maxlength="80" value="<?= htmlspecialchars($login, ENT_QUOTES, 'UTF-8') ?>" autocomplete="username" required autofocus>
                    <small>Nome usado para entrar no sistema.</small>
                </div>

                <div class="cadastro-divisor" aria-hidden="true"></div>

                <div class="cadastro-senhas">
                    <div class="cadastro-campo">
                        <label for="senha">Senha</label>
                        <input id="senha" name="senha" type="password" minlength="8" maxlength="255" required autocomplete="new-password">
                        <small>Use pelo menos 8 caracteres.</small>
                    </div>
                    <div class="cadastro-campo">
                        <label for="confirmacao">Confirmar senha</label>
                        <input id="confirmacao" name="confirmacao" type="password" minlength="8" maxlength="255" required autocomplete="new-password">
                    </div>
                </div>

                <div class="cadastro-acoes">
                    <a class="cadastro-cancelar" href="../index.php">Já tenho conta</a>
                    <button type="submit">Criar conta</button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
