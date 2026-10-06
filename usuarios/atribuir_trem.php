<?php
require __DIR__ . '/../includes/proteger.php';
require __DIR__ . '/../includes/permissao.php';
exigirPapel(['administrador']);
require __DIR__ . '/../includes/seguranca.php';
require __DIR__ . '/../config/conexao.php';

$erros = []; $sucesso = '';
$trens = $conexao->query('SELECT id_trem, prefixo, modelo FROM trens ORDER BY prefixo')->fetch_all(MYSQLI_ASSOC);
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    exigirCsrf();
    $idUsuario = filter_var($_POST['id_usuario'] ?? null, FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    $idTrem = ($_POST['id_trem'] ?? '') === '' ? null : filter_var($_POST['id_trem'], FILTER_VALIDATE_INT, ['options'=>['min_range'=>1]]);
    if (!$idUsuario) { $erros[] = 'Selecione um maquinista válido.'; }
    if ($idTrem === false || ($idTrem !== null && !in_array((int) $idTrem, array_map('intval', array_column($trens, 'id_trem')), true))) { $erros[] = 'Selecione um trem existente.'; }
    if ($idUsuario) {
        $consulta = $conexao->prepare('SELECT id FROM usuarios WHERE id=? AND papel=?');
        $papel = 'maquinista'; $consulta->bind_param('is', $idUsuario, $papel); $consulta->execute();
        if (!$consulta->get_result()->fetch_assoc()) { $erros[] = 'Selecione um maquinista válido.'; }
        $consulta->close();
    }
    if (!$erros) {
        try {
            $papel = 'maquinista';
            $consulta = $conexao->prepare('UPDATE usuarios SET trem_atribuido_id=? WHERE id=? AND papel=?');
            $consulta->bind_param('iis', $idTrem, $idUsuario, $papel); $consulta->execute(); $consulta->close();
            $sucesso = 'Atribuição atualizada.';
        } catch (mysqli_sql_exception $falha) { $erros[] = 'Não foi possível atualizar a atribuição.'; }
    }
}
$maquinistas = $conexao->query("SELECT u.id, u.nome, u.login, t.prefixo FROM usuarios u LEFT JOIN trens t ON t.id_trem=u.trem_atribuido_id WHERE u.papel='maquinista' ORDER BY u.nome, u.login")->fetch_all(MYSQLI_ASSOC);
?>
<!doctype html><html lang="pt-BR"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>Atribuir trem | A-TRAIN</title><link rel="stylesheet" href="../assets/css/estilo.css"></head><body>
<?php require __DIR__ . '/../includes/cabecalho.php'; ?>
<main class="pagina"><div class="titulo-pagina"><div><p class="sobretitulo">Administração de acesso</p><h1>Atribuir trem</h1><p class="descricao">Defina qual trem cada maquinista pode consultar.</p></div></div>
<?php if ($erros): ?><div class="aviso erro" role="alert"><ul><?php foreach ($erros as $erro): ?><li><?= escapar($erro) ?></li><?php endforeach; ?></ul></div><?php endif; ?>
<?php if ($sucesso): ?><p class="aviso sucesso" role="status"><?= escapar($sucesso) ?></p><?php endif; ?>
<form class="cartao formulario" method="post"><input type="hidden" name="csrf" value="<?= escapar(tokenCsrf()) ?>"><label for="id_usuario">Maquinista</label><select id="id_usuario" name="id_usuario" required><option value="">Selecione</option><?php foreach ($maquinistas as $usuario): ?><option value="<?= (int) $usuario['id'] ?>"><?= escapar($usuario['nome'] . ' (' . $usuario['login'] . ') — ' . ($usuario['prefixo'] ?? 'sem trem')) ?></option><?php endforeach; ?></select><label for="id_trem">Trem</label><select id="id_trem" name="id_trem"><option value="">Sem trem atribuído</option><?php foreach ($trens as $trem): ?><option value="<?= (int) $trem['id_trem'] ?>"><?= escapar($trem['prefixo'] . ' — ' . $trem['modelo']) ?></option><?php endforeach; ?></select><button type="submit">Salvar atribuição</button></form></main></body></html>
