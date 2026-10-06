<?php
declare(strict_types=1);

// Execute apenas contra um banco descartável, com servidor PHP local de teste.
// Contas esperadas: admin_bloco2, gestor_bloco2, maq_bloco2 e sem_trem_bloco2.
$base = rtrim(getenv('SA_FUNDOS_TEST_URL') ?: 'http://127.0.0.1:8765', '/');
$senha = getenv('SA_FUNDOS_TEST_PASSWORD');
if (!is_string($senha) || $senha === '') {
    exit("Defina SA_FUNDOS_TEST_PASSWORD antes de executar.\n");
}

final class ClienteHttp
{
    private string $cookies;

    public function __construct(private readonly string $base)
    {
        $arquivo = tempnam(sys_get_temp_dir(), 'fundos_http_');
        if ($arquivo === false) { throw new RuntimeException('Não foi possível criar arquivo de cookies.'); }
        $this->cookies = $arquivo;
    }

    public function __destruct()
    {
        if (is_file($this->cookies)) { unlink($this->cookies); }
    }

    public function requisitar(string $metodo, string $caminho, array $dados = []): array
    {
        $curl = curl_init($this->base . $caminho);
        if ($curl === false) { throw new RuntimeException('Falha ao iniciar cURL.'); }
        curl_setopt_array($curl, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_HEADER => true,
            CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_COOKIEFILE => $this->cookies,
            CURLOPT_COOKIEJAR => $this->cookies,
            CURLOPT_TIMEOUT => 15,
        ]);
        if ($metodo === 'POST') {
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, http_build_query($dados));
        }
        $resposta = curl_exec($curl);
        if ($resposta === false) {
            $erro = curl_error($curl);
            curl_close($curl);
            throw new RuntimeException('Falha HTTP: ' . $erro);
        }
        $tamanhoCabecalho = curl_getinfo($curl, CURLINFO_HEADER_SIZE);
        $status = curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
        curl_close($curl);
        $cabecalho = substr($resposta, 0, $tamanhoCabecalho);
        preg_match('/^Location:\s*(.+)$/mi', $cabecalho, $localizacao);
        return [
            'status' => $status,
            'body' => substr($resposta, $tamanhoCabecalho),
            'location' => trim($localizacao[1] ?? ''),
            'headers' => $cabecalho,
        ];
    }

    public function get(string $caminho): array { return $this->requisitar('GET', $caminho); }
    public function post(string $caminho, array $dados): array { return $this->requisitar('POST', $caminho, $dados); }
}

function conferir(bool $condicao, string $mensagem): void
{
    if (!$condicao) { throw new RuntimeException('FALHOU: ' . $mensagem); }
    echo 'OK ', $mensagem, PHP_EOL;
}

function tokenCsrfTeste(string $html): string
{
    conferir(preg_match('/name="csrf" value="([a-f0-9]{64})"/', $html, $resultado) === 1, 'token CSRF presente');
    return $resultado[1];
}

function entrar(string $base, string $login, string $senha): ClienteHttp
{
    $cliente = new ClienteHttp($base);
    $resposta = $cliente->post('/index.php', ['login' => $login, 'senha' => $senha]);
    conferir($resposta['status'] === 302 && $resposta['location'] === 'principal.php', 'login ' . $login);
    return $cliente;
}

$anonimo = new ClienteHttp($base);
foreach (['/principal.php', '/trens.php', '/trens_form.php', '/sensores.php', '/sensores_form.php', '/usuarios/cadastrar.php', '/usuarios/atribuir_trem.php'] as $pagina) {
    $resposta = $anonimo->get($pagina);
    conferir($resposta['status'] === 302 && str_contains($resposta['location'], 'index.php'), 'anônimo bloqueado ' . $pagina);
}

$admin = entrar($base, 'admin_bloco2', $senha);
foreach (['/principal.php', '/trens.php', '/sensores.php', '/usuarios/cadastrar.php'] as $pagina) {
    $resposta = $admin->get($pagina);
    conferir($resposta['status'] === 200 && str_contains($resposta['body'], 'Admin Teste') && str_contains($resposta['headers'], 'no-store'), 'sessão persistente ' . $pagina);
}
conferir($admin->get('/sair.php')['status'] === 405, 'GET não encerra sessão');
conferir($admin->get('/principal.php')['status'] === 200, 'sessão mantém-se após GET sair');
foreach ([['/sair.php', []], ['/trens.php', ['excluir_id' => 1]], ['/sensores.php', ['excluir_id' => 1]], ['/trens_form.php', ['prefixo' => 'TR-999']], ['/sensores_form.php', ['codigo' => 'S-TEMP-999']]] as [$pagina, $dados]) {
    conferir($admin->post($pagina, $dados)['status'] === 403, 'POST sem CSRF bloqueado ' . $pagina);
}

$novoLogin = 'publico_' . bin2hex(random_bytes(4));
$registro = $anonimo->post('/usuarios/registrar.php', ['nome' => 'Novo Maquinista', 'login' => $novoLogin, 'senha' => $senha, 'confirmacao' => $senha, 'papel' => 'administrador']);
conferir($registro['status'] === 302 && str_contains($registro['location'], 'index.php'), 'cadastro público funciona');
$publico = entrar($base, $novoLogin, $senha);
conferir($publico->get('/usuarios/cadastrar.php')['status'] === 403, 'cadastro público não cria administrador');
conferir(str_contains($publico->get('/sensores.php')['body'], 'Nenhum trem atribuído'), 'cadastro público inicia sem acesso aos sensores');
$atribuicao = $admin->get('/usuarios/atribuir_trem.php')['body'];
conferir(preg_match('/<option value="(\d+)">Novo Maquinista \(' . preg_quote($novoLogin, '/') . '/', $atribuicao, $encontrado) === 1, 'novo maquinista listado para atribuição');
$idPublico = $encontrado[1];
$csrfAtribuicao = tokenCsrfTeste($atribuicao);
conferir(str_contains($admin->post('/usuarios/atribuir_trem.php', ['csrf' => $csrfAtribuicao, 'id_usuario' => $idPublico, 'id_trem' => '999999'])['body'], 'Selecione um trem existente'), 'atribuição valida trem');
conferir(str_contains($admin->post('/usuarios/atribuir_trem.php', ['csrf' => $csrfAtribuicao, 'id_usuario' => $idPublico, 'id_trem' => '1'])['body'], 'Atribuição atualizada'), 'administrador atribui trem existente');

$loginCriado = 'criado_' . bin2hex(random_bytes(4));
$csrfCadastro = tokenCsrfTeste($admin->get('/usuarios/cadastrar.php')['body']);
$cadastro = $admin->post('/usuarios/cadastrar.php', ['csrf' => $csrfCadastro, 'nome' => 'Gestor Criado', 'login' => $loginCriado, 'papel' => 'gestor', 'senha' => $senha, 'confirmacao' => $senha, 'trem_atribuido_id' => '']);
conferir($cadastro['status'] === 200 && str_contains($cadastro['body'], 'Usuário cadastrado com sucesso'), 'cadastro administrativo funciona');
$gestorCriado = entrar($base, $loginCriado, $senha);
conferir($gestorCriado->get('/trens.php')['status'] === 200, 'papel criado pelo administrador funciona');

$prefixo = 'TR-' . random_int(100, 899);
while (str_contains($admin->get('/trens.php')['body'], $prefixo)) { $prefixo = 'TR-' . ((int) substr($prefixo, -3) % 800 + 100); }
$csrfTrem = tokenCsrfTeste($admin->get('/trens_form.php')['body']);
$trem = ['csrf' => $csrfTrem, 'id_trem' => '', 'prefixo' => $prefixo, 'modelo' => 'GE ES43BBi Teste', 'ano' => '2020', 'status' => 'normal', 'capacidade_toneladas' => '125.50', 'ultima_inspecao' => date('Y-m-d', strtotime('-30 days'))];
conferir(str_contains($admin->post('/trens_form.php', array_replace($trem, ['prefixo' => 'X']))['body'], 'padrão TR-204'), 'prefixo inválido');
conferir(str_contains($admin->post('/trens_form.php', array_replace($trem, ['capacidade_toneladas' => '-1']))['body'], 'capacidade positiva'), 'capacidade inválida');
conferir($admin->post('/trens_form.php', $trem)['status'] === 303, 'cadastrar trem');
conferir(str_contains($admin->get('/trens.php?busca=' . $prefixo . '&status=normal')['body'], $prefixo), 'buscar e filtrar trem');
conferir(str_contains($admin->post('/trens_form.php', $trem)['body'], 'já está cadastrado'), 'prefixo duplicado');
conferir(preg_match('/trens_form\.php\?id=(\d+)/', $admin->get('/trens.php?busca=' . $prefixo)['body'], $encontrado) === 1, 'ID do trem exibido');
$idTrem = $encontrado[1];
conferir($admin->post('/trens_form.php', array_replace($trem, ['id_trem' => $idTrem, 'modelo' => 'GE Atualizado', 'status' => 'atencao']))['status'] === 303, 'editar trem');
conferir(str_contains($admin->get('/trens.php?busca=' . $prefixo . '&status=atencao')['body'], 'GE Atualizado'), 'edição de trem persistida');

$csrfSensor = tokenCsrfTeste($admin->get('/sensores_form.php')['body']);
$codigo = 'S-TEMP-' . substr($prefixo, -3);
$sensor = ['csrf' => $csrfSensor, 'id_sensor' => '', 'id_trem' => $idTrem, 'codigo' => $codigo, 'tipo' => 'temperatura', 'localizacao' => 'Motor', 'segmento' => 'Trecho Norte', 'ultima_leitura' => 'normal'];
conferir(str_contains($admin->post('/sensores_form.php', array_replace($sensor, ['id_trem' => '999999']))['body'], 'não existe'), 'trem inexistente');
conferir($admin->post('/sensores_form.php', $sensor)['status'] === 303, 'cadastrar sensor');
$listaSensor = $admin->get('/sensores.php?tipo=temperatura')['body'];
conferir(str_contains($listaSensor, $codigo) && str_contains($listaSensor, $prefixo), 'filtro por tipo e JOIN com prefixo');
conferir(!str_contains($admin->get('/sensores.php?tipo=energia')['body'], $codigo), 'filtro exclui outro tipo');
$posicao = strpos($listaSensor, $codigo);
conferir($posicao !== false && preg_match('/sensores_form\.php\?id=(\d+)/', substr($listaSensor, $posicao), $encontrado) === 1, 'ID do sensor exibido');
$idSensor = $encontrado[1];
conferir($admin->post('/sensores_form.php', array_replace($sensor, ['id_sensor' => $idSensor, 'ultima_leitura' => 'critico']))['status'] === 303, 'editar sensor');
conferir(str_contains($admin->get('/sensores.php?tipo=temperatura')['body'], 'estado-critico'), 'indicador editado');
conferir(str_contains($admin->post('/trens.php', ['csrf' => $csrfTrem, 'excluir_id' => $idTrem])['body'], 'possui sensores'), 'exclusão de trem com sensor bloqueada');
conferir(str_contains($admin->post('/sensores.php', ['csrf' => $csrfSensor, 'excluir_id' => $idSensor])['body'], 'Sensor excluído'), 'excluir sensor');
conferir(str_contains($admin->post('/trens.php', ['csrf' => $csrfTrem, 'excluir_id' => $idTrem])['body'], 'Trem excluído'), 'excluir trem');

$gestor = entrar($base, 'gestor_bloco2', $senha);
conferir($gestor->get('/trens.php')['status'] === 200 && $gestor->get('/sensores_form.php')['status'] === 200, 'gestor gere trens e sensores');
conferir($gestor->get('/usuarios/cadastrar.php')['status'] === 403, 'gestor não cadastra usuários');
$maquinista = entrar($base, 'maq_bloco2', $senha);
conferir($maquinista->get('/trens.php')['status'] === 403 && $maquinista->get('/trens_form.php')['status'] === 403, 'maquinista sem gestão de trens');
conferir($maquinista->get('/sensores_form.php')['status'] === 403 && $maquinista->post('/sensores.php', ['excluir_id' => 1])['status'] === 403, 'maquinista sem mutação de sensores');
$listaMaquinista = $maquinista->get('/sensores.php')['body'];
conferir(str_contains($listaMaquinista, 'S-TEMP-001') && !str_contains($listaMaquinista, 'S-VELO-002'), 'maquinista vê somente sensores do trem atribuído');
conferir($maquinista->get('/usuarios/atribuir_trem.php')['status'] === 403, 'maquinista não atribui trem');
$listaPublico = $publico->get('/sensores.php')['body'];
conferir(str_contains($listaPublico, 'S-TEMP-001') && !str_contains($listaPublico, 'S-VELO-002'), 'nova atribuição aplica-se sem novo login');
$semTrem = entrar($base, 'sem_trem_bloco2', $senha);
conferir(str_contains($semTrem->get('/sensores.php')['body'], 'Nenhum trem atribuído'), 'sem trem não vê todos os sensores');

$csrfSaida = tokenCsrfTeste($admin->get('/principal.php')['body']);
conferir($admin->post('/sair.php', ['csrf' => $csrfSaida])['status'] === 303, 'logout POST');
foreach (['/principal.php', '/trens.php', '/sensores.php'] as $pagina) {
    conferir($admin->get($pagina)['status'] === 302, 'acesso negado após logout ' . $pagina);
}
echo "Todos os testes HTTP passaram.\n";
