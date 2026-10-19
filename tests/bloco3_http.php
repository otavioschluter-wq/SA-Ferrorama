<?php
declare(strict_types=1);

// Execute somente contra uma instância descartável com migracao_bloco3.sql aplicada.
$base = rtrim(getenv('SA_FUNDOS_TEST_URL') ?: 'http://127.0.0.1:8873', '/');
$senha = getenv('SA_FUNDOS_TEST_PASSWORD');
if (!is_string($senha) || $senha === '') { exit("Defina SA_FUNDOS_TEST_PASSWORD.\n"); }

final class Navegador
{
    private string $arquivo;
    public function __construct(private string $base)
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'fundos3_');
        if ($arquivo === false) { throw new RuntimeException('Sem arquivo de cookie.'); }
        $this->arquivo = $arquivo;
    }
    public function __destruct() { @unlink($this->arquivo); }
    public function acessar(string $caminho, array $dados = null): array
    {
        $curl = curl_init($this->base . $caminho);
        curl_setopt_array($curl, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_FOLLOWLOCATION => false, CURLOPT_COOKIEFILE => $this->arquivo, CURLOPT_COOKIEJAR => $this->arquivo, CURLOPT_TIMEOUT => 15]);
        if ($dados !== null) { curl_setopt($curl, CURLOPT_POST, true); curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($dados)); }
        $resposta = curl_exec($curl);
        if ($resposta === false) { throw new RuntimeException(curl_error($curl)); }
        $cabecalho = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $resultado = ['status' => curl_getinfo($curl, CURLINFO_RESPONSE_CODE), 'body' => substr($resposta, $cabecalho), 'header' => substr($resposta, 0, $cabecalho)];
        curl_close($curl);
        return $resultado;
    }
}
function conferir3(bool $ok, string $nome): void
{
    if (!$ok) { throw new RuntimeException('FALHOU: ' . $nome); }
    echo 'OK ', $nome, PHP_EOL;
}
function csrf3(string $html): string
{
    if (!preg_match('/name="csrf" value="([a-f0-9]{64})"/', $html, $m)) { throw new RuntimeException('Token CSRF ausente.'); }
    return $m[1];
}

$anonimo = new Navegador($base);
foreach (['rotas.php', 'rotas_form.php', 'leituras.php', 'simulador.php', 'notificacoes_alertas.php'] as $pagina) {
    conferir3($anonimo->acessar('/' . $pagina)['status'] === 302, 'sessão protege ' . $pagina);
}
$admin = new Navegador($base);
conferir3($admin->acessar('/index.php', ['login' => 'admin_bloco3', 'senha' => $senha])['status'] === 302, 'login admin');
$maq = new Navegador($base);
conferir3($maq->acessar('/index.php', ['login' => 'maq_bloco3', 'senha' => $senha])['status'] === 302, 'login maquinista');
conferir3($maq->acessar('/simulador.php')['status'] === 403 && $maq->acessar('/rotas_form.php')['status'] === 403, 'maquinista sem permissão de escrita');
conferir3($admin->acessar('/rotas_form.php', ['origem' => 'X'])['status'] === 403, 'CSRF de rotas');
conferir3($admin->acessar('/simulador.php', ['quantidade' => '1'])['status'] === 403, 'CSRF do simulador');

$rotaCsrf = csrf3($admin->acessar('/rotas_form.php')['body']);
$rota = ['csrf' => $rotaCsrf, 'id_rota' => '', 'id_trem' => '1', 'origem' => 'Teste Norte', 'destino' => 'Teste Sul', 'data_viagem' => '2026-11-10', 'hora_partida' => '08:00', 'hora_chegada' => '11:00', 'status' => 'programada'];
conferir3(str_contains($admin->acessar('/rotas_form.php', array_replace($rota, ['hora_chegada' => '07:00']))['body'], 'chegada deve ocorrer após'), 'validação de horário');
conferir3($admin->acessar('/rotas_form.php', $rota)['status'] === 303, 'cadastro de rota');
$lista = $admin->acessar('/rotas.php?status=programada&data_inicio=2026-11-10&data_fim=2026-11-10&id_trem=1&busca=Teste%20Norte')['body'];
conferir3(str_contains($lista, 'Teste Norte') && str_contains($lista, 'TR-204'), 'quatro filtros combinados e prefixo');
conferir3(preg_match('/rotas_form\.php\?id=(\d+)/', $lista, $m) === 1, 'rota possui edição');
$idRota = $m[1];
conferir3($admin->acessar('/rotas_form.php', array_replace($rota, ['id_rota' => $idRota, 'status' => 'concluida']))['status'] === 303, 'edição de rota');
conferir3(str_contains($admin->acessar('/rotas.php?status=concluida&busca=Teste%20Norte')['body'], 'Teste Norte'), 'edição persistida');
conferir3(!str_contains($maq->acessar('/rotas.php?busca=Teste%20Norte&id_trem=2')['body'], '<td data-label="Origem">Teste Norte</td>'), 'maquinista restrito ao trem atribuído');

$sensorCsrf = csrf3($admin->acessar('/sensores_form.php')['body']);
$codigo = 'S-TEMP-' . random_int(100, 999);
$sensor = ['csrf' => $sensorCsrf, 'id_sensor' => '', 'id_trem' => '1', 'codigo' => $codigo, 'tipo' => 'temperatura', 'localizacao' => 'Motor de teste', 'segmento' => 'Teste'];
conferir3($admin->acessar('/sensores_form.php', $sensor)['status'] === 303, 'cadastro de sensor');
$listaSensor = $admin->acessar('/sensores.php?tipo=temperatura')['body'];
conferir3(str_contains($listaSensor, $codigo) && str_contains($listaSensor, 'Sem leitura'), 'sensor sem leitura visível');
$pos = strpos($listaSensor, $codigo);
conferir3($pos !== false && preg_match('/sensores_form\.php\?id=(\d+)/', substr($listaSensor, $pos), $m) === 1, 'sensor possui ID');
$idSensor = $m[1];
$simCsrf = csrf3($admin->acessar('/simulador.php')['body']);
conferir3(str_contains($admin->acessar('/simulador.php', ['csrf' => $simCsrf, 'id_sensor' => $idSensor, 'quantidade' => '3'])['body'], '3 leitura(s) gravada(s); 0 alerta(s)'), 'simulador gera leituras normais');
$leituras = $admin->acessar('/leituras.php?id_sensor=' . $idSensor . '&id_trem=1')['body'];
conferir3(str_contains($leituras, $codigo) && str_contains($leituras, 'TR-204'), 'leitura alcança sensor e trem');
conferir3(str_contains($admin->acessar('/simulador.php', ['csrf' => $simCsrf, 'id_sensor' => $idSensor, 'quantidade' => '10', 'fora_do_limite' => '1'])['body'], '10 alerta(s) gerado(s)'), 'valores fora do limite geram alertas');
$criticos = $admin->acessar('/notificacoes_alertas.php?severidade=critico&tipo=temperatura')['body'];
conferir3(str_contains($criticos, $codigo) && str_contains($criticos, 'TR-204') && str_contains($criticos, 'linha-alerta-critico'), 'alerta crítico com filtros e destaque');
conferir3(!str_contains($admin->acessar('/notificacoes_alertas.php?severidade=critico&tipo=energia')['body'], $codigo), 'filtro de tipo remove outro sensor');
$listaSensor = $admin->acessar('/sensores.php?tipo=temperatura')['body'];
$pos = strpos($listaSensor, $codigo);
conferir3($pos !== false && str_contains(substr($listaSensor, $pos, 600), 'estado-'), 'indicador calculado no sensor');
conferir3(str_contains($admin->acessar('/sensores_form.php', array_replace($sensor, ['id_sensor' => $idSensor, 'tipo' => 'velocidade', 'codigo' => 'S-VELO-' . substr($codigo, -3)]))['body'], 'já possui leituras'), 'histórico impede troca de tipo');
conferir3(!str_contains($maq->acessar('/leituras.php?id_trem=2')['body'], '<td data-label="Sensor">' . $codigo . '</td>'), 'leituras de outro trem ocultas do maquinista');
conferir3(!str_contains($maq->acessar('/notificacoes_alertas.php?tipo=energia')['body'], $codigo), 'alertas de outro trem ocultos do maquinista');

$rotaCsrf = csrf3($admin->acessar('/rotas.php')['body']);
conferir3(str_contains($admin->acessar('/rotas.php', ['csrf' => $rotaCsrf, 'excluir_id' => $idRota])['body'], 'Rota excluída'), 'exclusão de rota');
$sensorCsrf = csrf3($admin->acessar('/sensores.php')['body']);
conferir3(str_contains($admin->acessar('/sensores.php', ['csrf' => $sensorCsrf, 'excluir_id' => $idSensor])['body'], 'Sensor excluído'), 'exclusão em cascata do sensor');
echo "Bloco 3 HTTP OK.\n";
