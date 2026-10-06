<?php
require __DIR__ . '/../includes/permissao.php';
exigirPapel(['administrador']);
require __DIR__ . '/../includes/seguranca.php';
require __DIR__ . '/../config/conexao.php';

$nome = '';
$login = '';
$papel = '';
$tremAtribuido = '';
$erros = [];
$sucesso = '';
$trens = $conexao->query('SELECT id_trem, prefixo, modelo FROM trens ORDER BY prefixo')->fetch_all(MYSQLI_ASSOC);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $nome = is_string($_POST['nome'] ?? null) ? trim($_POST['nome']) : '';
    $login = is_string($_POST['login'] ?? null) ? trim($_POST['login']) : '';
    $papel = is_string($_POST['papel'] ?? null) ? $_POST['papel'] : '';
    $senha = is_string($_POST['senha'] ?? null) ? $_POST['senha'] : '';
    $confirmacao = is_string($_POST['confirmacao'] ?? null) ? $_POST['confirmacao'] : '';
    $tremAtribuido = is_string($_POST['trem_atribuido_id'] ?? null) ? $_POST['trem_atribuido_id'] : '';

    if ($nome === '' || mb_strlen($nome) > 120) {
        $erros[] = 'Informe um nome de até 120 caracteres.';
    }

    if ($login === '' || mb_strlen($login) > 80) {
        $erros[] = 'Informe um login de até 80 caracteres.';
    }
    if (!in_array($papel, ['administrador', 'maquinista', 'gestor'], true)) {
        $erros[] = 'Selecione um papel válido.';
    }
    if (strlen($senha) < 8 || strlen($senha) > 255) {
        $erros[] = 'Informe uma senha de 8 a 255 caracteres.';
    }
    if ($senha !== $confirmacao) {
        $erros[] = 'A confirmação da senha não confere.';
    }
    if ($tremAtribuido !== '' && ($papel !== 'maquinista' || filter_var($tremAtribuido, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]) === false || !in_array((int) $tremAtribuido, array_map('intval', array_column($trens, 'id_trem')), true))) {
        $erros[] = 'Selecione um trem válido apenas para maquinistas.';
    }

    if (!$erros) {
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
                $idTrem = $tremAtribuido === '' ? null : (int) $tremAtribuido;
                $insercao = $conexao->prepare('INSERT INTO usuarios (nome, login, senha, papel, trem_atribuido_id) VALUES (?, ?, ?, ?, ?)');
                $insercao->bind_param('ssssi', $nome, $login, $hash, $papel, $idTrem);
                $insercao->execute();
                $insercao->close();
                $sucesso = 'Usuário cadastrado com sucesso.';
                $login = '';
                $nome = '';
                $papel = '';
                $tremAtribuido = '';
            }
        } catch (mysqli_sql_exception $falha) {
            $erros[] = $falha->getCode() === 1062 ? 'Este login já está cadastrado.' : 'Não foi possível cadastrar o usuário.';
        }
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Cadastrar usuário | A-TRAIN</title>
    <link rel="stylesheet" href="../assets/css/estilo.css">
</head>
<body class="pagina-cadastro">
    <?php require __DIR__ . '/../includes/cabecalho.php'; ?>
    <main class="cadastro-layout">
        <section class="cadastro-intro" aria-labelledby="cadastro-titulo">
            <span class="cadastro-etiqueta">Administração de acesso</span>
            <h1 id="cadastro-titulo">Cadastrar usuário</h1>
            <p>Crie uma conta para acessar o A-TRAIN. Defina o papel de acordo com a função da pessoa no sistema.</p>
            <div class="cadastro-nota">
                <span class="cadastro-nota-icone" aria-hidden="true">✓</span>
                <p>O cadastro é permitido apenas para administradores.</p>
            </div>
        </section>

        <section class="cartao cadastro-cartao" aria-labelledby="formulario-titulo">
            <div class="cadastro-cartao-cabecalho">
                <span class="cadastro-passo">Nova conta</span>
                <h2 id="formulario-titulo">Dados do usuário</h2>
                <p>Preencha os campos abaixo para criar o acesso.</p>
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
            <?php if ($sucesso !== ''): ?>
                <p class="aviso sucesso cadastro-aviso" role="status"><?= htmlspecialchars($sucesso, ENT_QUOTES, 'UTF-8') ?></p>
            <?php endif; ?>

            <form class="cadastro-formulario" method="post">
                <input type="hidden" name="csrf" value="<?= escapar(tokenCsrf()) ?>">
                <div class="cadastro-campo"><label for="nome">Nome</label><input id="nome" name="nome" maxlength="120" value="<?= escapar($nome) ?>" required></div>
                <div class="cadastro-campo">
                    <label for="login">Login</label>
                    <input id="login" name="login" type="text" maxlength="80" value="<?= htmlspecialchars($login, ENT_QUOTES, 'UTF-8') ?>" autocomplete="username" required autofocus>
                    <small>Nome usado para entrar no sistema.</small>
                </div>

                <div class="cadastro-campo">
                    <label for="papel">Papel</label>
                    <select id="papel" name="papel" required>
                        <option value="">Selecione um papel</option>
                        <?php foreach (['administrador', 'maquinista', 'gestor'] as $opcao): ?>
                            <option value="<?= $opcao ?>" <?= $papel === $opcao ? 'selected' : '' ?>><?= ucfirst($opcao) ?></option>
                        <?php endforeach; ?>
                    </select>
                    <small>O papel define as permissões desta conta.</small>
                </div>

                <div class="cadastro-divisor" aria-hidden="true"></div>

                <div class="cadastro-campo"><label for="trem_atribuido_id">Trem atribuído ao maquinista (opcional)</label><select id="trem_atribuido_id" name="trem_atribuido_id"><option value="">Sem trem atribuído</option><?php foreach ($trens as $trem): ?><option value="<?= (int) $trem['id_trem'] ?>" <?= (string) $trem['id_trem'] === $tremAtribuido ? 'selected' : '' ?>><?= escapar($trem['prefixo'] . ' — ' . $trem['modelo']) ?></option><?php endforeach; ?></select><small>Para atribuir ou alterar após o cadastro, use SQL administrativo conforme o README.</small></div>

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
                    <a class="cadastro-cancelar" href="../principal.php">Cancelar</a>
                    <button type="submit">Cadastrar usuário</button>
                </div>
            </form>
        </section>
    </main>
</body>
</html>
