<?php
require __DIR__ . '/includes/proteger.php';
require __DIR__ . '/includes/permissao.php';
exigirPapel(['administrador']);
require __DIR__ . '/includes/seguranca.php';
require __DIR__ . '/config/conexao.php';
require __DIR__ . '/includes/banco.php';

$papeis = ['administrador' => 'Administrador', 'gestor' => 'Gerente', 'maquinista' => 'Maquinista', 'usuario' => 'Usuário comum'];
$erros = [];
$mensagem = '';

function administradoresAtivos(mysqli $conexao): int
{
    return (int) buscar($conexao, "SELECT COUNT(*) AS n FROM usuarios WHERE papel = 'administrador' AND ativo = 1")[0]['n'];
}

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $acao = is_string($_POST['acao'] ?? null) ? $_POST['acao'] : '';
    $id = filter_var($_POST['id'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
    $alvo = $id ? (buscar($conexao, 'SELECT id, papel, ativo FROM usuarios WHERE id = ?', 'i', [$id])[0] ?? null) : null;
    if (!$alvo || !in_array($acao, ['desativar', 'editar'], true)) {
        $erros[] = 'Usuário ou ação inválida.';
    } elseif ($acao === 'desativar') {
        if ($alvo['id'] == $_SESSION['id']) {
            $erros[] = 'Você não pode desativar a própria conta.';
        } elseif ($alvo['papel'] === 'administrador' && $alvo['ativo'] && administradoresAtivos($conexao) < 2) {
            $erros[] = 'O último administrador ativo não pode ser desativado.';
        } else {
            $atualiza = $conexao->prepare('UPDATE usuarios SET ativo = 0 WHERE id = ?');
            $atualiza->bind_param('i', $id);
            $atualiza->execute();
            $atualiza->close();
            $mensagem = 'Usuário desativado.';
        }
    } else {
        $nome = is_string($_POST['nome'] ?? null) ? trim($_POST['nome']) : '';
        $papel = is_string($_POST['papel'] ?? null) ? $_POST['papel'] : '';
        if ($nome === '' || mb_strlen($nome) > 120) {
            $erros[] = 'Informe um nome de até 120 caracteres.';
        }
        if (!isset($papeis[$papel])) {
            $erros[] = 'Selecione um papel válido.';
        } elseif ($alvo['papel'] === 'administrador' && $papel !== 'administrador' && $alvo['ativo'] && administradoresAtivos($conexao) < 2) {
            $erros[] = 'O último administrador ativo não pode mudar de papel.';
        }
        if (!$erros) {
            $atualiza = $conexao->prepare("UPDATE usuarios SET nome = ?, papel = ?, trem_atribuido_id = IF(? = 'maquinista', trem_atribuido_id, NULL) WHERE id = ?");
            $atualiza->bind_param('sssi', $nome, $papel, $papel, $id);
            $atualiza->execute();
            $atualiza->close();
            if ($alvo['id'] == $_SESSION['id']) {
                $_SESSION['papel'] = $papel;
                $_SESSION['nome'] = $nome;
            }
            $mensagem = 'Usuário atualizado.';
        }
    }
}

$editar = filter_var($_GET['editar'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$usuarios = buscar($conexao, 'SELECT u.id, u.nome, u.login, u.papel, u.ativo, t.prefixo FROM usuarios u LEFT JOIN trens t ON t.id_trem = u.trem_atribuido_id ORDER BY u.nome');
$emEdicao = null;
foreach ($usuarios as $usuario) {
    if ($usuario['id'] == $editar) {
        $emEdicao = $usuario;
    }
}
?>
<!doctype html>
<html lang="pt-BR">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>Usuários | A-TRAIN</title>
    <link rel="stylesheet" href="assets/css/estilo.css">
</head>
<body>
<?php require __DIR__ . '/includes/cabecalho.php'; ?>
<main class="pagina">
    <div class="titulo-pagina"><div><p class="sobretitulo">Administração de acesso</p><h1>Usuários</h1></div><a class="botao-link" href="usuarios/cadastrar.php">Cadastrar usuário</a></div>
    <?php if ($erros): ?><div class="aviso erro" role="alert"><ul><?php foreach ($erros as $erro): ?><li><?= escapar($erro) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
    <?php if ($mensagem): ?><p class="aviso sucesso" role="status"><?= escapar($mensagem) ?></p><?php endif; ?>
    <?php if ($emEdicao): ?>
    <form class="cartao formulario" method="post">
        <input type="hidden" name="csrf" value="<?= escapar(tokenCsrf()) ?>"><input type="hidden" name="acao" value="editar"><input type="hidden" name="id" value="<?= (int) $emEdicao['id'] ?>">
        <h2>Editar <?= escapar($emEdicao['login']) ?></h2>
        <label for="nome">Nome</label><input id="nome" name="nome" maxlength="120" value="<?= escapar($emEdicao['nome']) ?>" required>
        <label for="papel">Papel</label>
        <select id="papel" name="papel" required><?php foreach ($papeis as $valor => $rotulo): ?><option value="<?= $valor ?>" <?= $valor === $emEdicao['papel'] ? 'selected' : '' ?>><?= $rotulo ?></option><?php endforeach; ?></select>
        <div class="acoes"><a href="usuarios.php">Cancelar</a><button type="submit">Salvar</button></div>
    </form>
    <?php endif; ?>
    <div class="lista"><table>
        <thead><tr><th>Nome</th><th>Login</th><th>Papel</th><th>Trem</th><th>Situação</th><th>Ações</th></tr></thead>
        <tbody><?php foreach ($usuarios as $usuario): ?>
        <tr>
            <td data-label="Nome"><?= escapar($usuario['nome']) ?></td>
            <td data-label="Login"><?= escapar($usuario['login']) ?></td>
            <td data-label="Papel"><?= escapar($papeis[$usuario['papel']]) ?></td>
            <td data-label="Trem"><?= escapar($usuario['prefixo'] ?? '-') ?></td>
            <td data-label="Situação"><span class="estado <?= $usuario['ativo'] ? 'estado-normal' : 'estado-cancelada' ?>"><?= $usuario['ativo'] ? 'Ativo' : 'Desativado' ?></span></td>
            <td class="acoes-tabela" data-label="Ações"><a href="?editar=<?= (int) $usuario['id'] ?>">Editar</a>
                <?php if ($usuario['ativo']): ?><form method="post"><input type="hidden" name="csrf" value="<?= escapar(tokenCsrf()) ?>"><input type="hidden" name="acao" value="desativar"><input type="hidden" name="id" value="<?= (int) $usuario['id'] ?>"><button class="perigo" type="submit">Desativar</button></form><?php endif; ?></td>
        </tr>
        <?php endforeach; ?></tbody>
    </table></div>
</main>
</body>
</html>
