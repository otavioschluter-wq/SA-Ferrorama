<?php
function buscar(mysqli $conexao, string $sql, string $tipos = '', array $valores = []): array
{
    $consulta = $conexao->prepare($sql);
    if ($valores) {
        $consulta->bind_param($tipos, ...$valores);
    }
    $consulta->execute();
    $linhas = $consulta->get_result()->fetch_all(MYSQLI_ASSOC);
    $consulta->close();
    return $linhas;
}

function tremDoMaquinista(mysqli $conexao): int
{
    $linha = buscar($conexao, 'SELECT trem_atribuido_id FROM usuarios WHERE id = ?', 'i', [$_SESSION['id']]);
    return (int) ($linha[0]['trem_atribuido_id'] ?? 0);
}
