<?php
// Uso: php tests/teste_http.php [url-base]. Altera dados: rode apenas em banco recem-importado.
$base = rtrim($argv[1] ?? 'http://localhost/SA-Ferrorama-Fundos', '/');
$falhas = 0;

function sessao(): CurlHandle
{
    $c = curl_init();
    curl_setopt_array($c, [CURLOPT_RETURNTRANSFER => true, CURLOPT_HEADER => true, CURLOPT_COOKIEJAR => tempnam(sys_get_temp_dir(), 'ck'), CURLOPT_COOKIEFILE => '']);
    return $c;
}

function req(CurlHandle $c, string $metodo, string $url, array $dados = []): array
{
    global $base;
    $alvo = $base . '/' . $url;
    if ($metodo === 'GET') {
        curl_setopt($c, CURLOPT_HTTPGET, true);
        if ($dados) {
            $alvo .= '?' . http_build_query($dados);
        }
    } else {
        curl_setopt($c, CURLOPT_POST, true);
        curl_setopt($c, CURLOPT_POSTFIELDS, http_build_query($dados));
    }
    curl_setopt($c, CURLOPT_URL, $alvo);
    $resposta = curl_exec($c);
    $corte = curl_getinfo($c, CURLINFO_HEADER_SIZE);
    return ['status' => curl_getinfo($c, CURLINFO_RESPONSE_CODE), 'cabecalho' => substr($resposta, 0, $corte), 'corpo' => substr($resposta, $corte)];
}

function csrf(CurlHandle $c, string $pagina): string
{
    preg_match('/name="csrf" value="([0-9a-f]+)"/', req($c, 'GET', $pagina)['corpo'], $m);
    return $m[1] ?? '';
}

function entrar(string $login, string $senha = 'Ferro@2026'): CurlHandle
{
    $c = sessao();
    req($c, 'POST', 'index.php', ['login' => $login, 'senha' => $senha]);
    return $c;
}

function confere(string $nome, bool $ok): void
{
    global $falhas;
    $falhas += $ok ? 0 : 1;
    echo ($ok ? 'OK    ' : 'FALHA ') . $nome . "\n";
}

$anonimo = sessao();
$r = req($anonimo, 'GET', 'dashboard.php');
confere('pagina interna sem sessao redireciona ao login', $r['status'] === 302 && str_contains($r['cabecalho'], 'index.php'));

$c = entrar('admin', 'errada');
confere('login com senha errada nega acesso', req($c, 'GET', 'dashboard.php')['status'] === 302);
$c = entrar("' OR '1'='1", "' OR '1'='1");
confere('injecao SQL no login e recusada', req($c, 'GET', 'dashboard.php')['status'] === 302);
$c = entrar('desativado');
confere('login de usuario desativado e recusado', req($c, 'GET', 'dashboard.php')['status'] === 302);

$admin = entrar('admin');
$painel = req($admin, 'GET', 'dashboard.php');
confere('administrador ve os cinco indicadores', $painel['status'] === 200 && substr_count($painel['corpo'], 'class="cartao indicador"') === 5);
confere('painel sem dados nulos mostra numeros reais da carga', str_contains($painel['corpo'], 'km/h') && !str_contains($painel['corpo'], 'Sem dados'));

$maq = entrar('maquinista');
$painel = req($maq, 'GET', 'dashboard.php')['corpo'];
confere('maquinista ve apenas indicadores do seu trem', str_contains($painel, 'Meu trem') && !str_contains($painel, 'Trens por status') && !str_contains($painel, 'Velocidade média') && !str_contains($painel, 'TR-318'));

$comum = entrar('comum');
$painel = req($comum, 'GET', 'dashboard.php')['corpo'];
confere('usuario comum ve apenas viagens em andamento', str_contains($painel, 'Viagens em andamento') && !str_contains($painel, 'Alertas ativos') && !str_contains($painel, 'Sensores com leitura'));
confere('usuario comum nao acessa trens (403)', req($comum, 'GET', 'trens.php')['status'] === 403);
confere('maquinista nao acessa relatorios (403)', req($maq, 'GET', 'gera_relatorio.php')['status'] === 403);
confere('maquinista nao acessa usuarios (403)', req($maq, 'GET', 'usuarios.php')['status'] === 403);

foreach (['desempenho', 'consumo', 'eficiencia', 'manutencao'] as $tipo) {
    $r = req($admin, 'GET', 'visualiza_relatorio.php', ['tipo' => $tipo, 'inicio' => date('Y-m-d', strtotime('-30 days')), 'fim' => date('Y-m-d')]);
    confere("relatorio $tipo traz filtros, tabela e grafico", $r['status'] === 200 && str_contains($r['corpo'], 'Filtros aplicados') && str_contains($r['corpo'], '<table>') && str_contains($r['corpo'], 'class="barra"'));
}
$r = req($admin, 'GET', 'visualiza_relatorio.php', ['tipo' => 'desempenho', 'inicio' => '2026-01-10', 'fim' => '2026-01-01']);
confere('relatorio com periodo invertido retorna 400', $r['status'] === 400);
$r = req($admin, 'GET', 'visualiza_relatorio.php', ['tipo' => 'consumo', 'inicio' => date('Y-m-d', strtotime('-30 days')), 'fim' => date('Y-m-d'), 'exportar' => 'csv']);
confere('exportacao CSV baixa arquivo UTF-8 com BOM e cabecalho', str_contains($r['cabecalho'], 'text/csv') && str_contains($r['cabecalho'], 'attachment') && str_starts_with($r['corpo'], "\xEF\xBB\xBFTrem;\"Consumo total"));
$r = req($admin, 'GET', 'visualiza_relatorio.php', ['tipo' => 'desempenho', 'inicio' => '2000-01-01', 'fim' => '2000-01-02']);
confere('relatorio sem dados no periodo informa ausencia', str_contains($r['corpo'], 'Sem dados'));

$token = csrf($admin, 'simulador.php');
$r = req($admin, 'POST', 'simulador.php', ['csrf' => $token, 'id_sensor' => 1, 'quantidade' => 5, 'fora_do_limite' => 1]);
confere('simulador gera leituras e alertas fora do limite', str_contains($r['corpo'], '5 leitura(s) gravada(s); 5 alerta(s) gerado(s)'));
$r = req($admin, 'GET', 'rotas.php', ['status' => 'atrasada']);
confere('filtro de rotas por status funciona', str_contains($r['corpo'], 'Atrasada') && !str_contains($r['corpo'], 'estado-concluida'));
$r = req($admin, 'GET', 'notificacoes_alertas.php', ['severidade' => 'critico']);
confere('central de alertas filtra por severidade', str_contains($r['corpo'], 'linha-alerta-critico') && !str_contains($r['corpo'], 'estado-atencao'));
$r = req($admin, 'POST', 'trens.php', ['csrf' => csrf($admin, 'trens.php'), 'excluir_id' => 1]);
confere('exclusao de trem com vinculos e bloqueada', str_contains($r['corpo'], 'vinculados'));

$token = csrf($admin, 'usuarios.php');
$r = req($admin, 'POST', 'usuarios.php', ['csrf' => $token, 'acao' => 'desativar', 'id' => 1]);
confere('administrador nao desativa a si mesmo', str_contains($r['corpo'], 'própria conta'));
$r = req($admin, 'POST', 'usuarios.php', ['csrf' => $token, 'acao' => 'editar', 'id' => 1, 'nome' => 'Administrador', 'papel' => 'usuario']);
confere('ultimo administrador ativo nao muda de papel', str_contains($r['corpo'], 'último administrador'));
$r = req($admin, 'POST', 'usuarios.php', ['acao' => 'desativar', 'id' => 5]);
confere('desativacao sem CSRF e rejeitada (403)', $r['status'] === 403);
$r = req($admin, 'POST', 'usuarios.php', ['csrf' => $token, 'acao' => 'desativar', 'id' => 5]);
confere('administrador desativa outro usuario', str_contains($r['corpo'], 'Usuário desativado'));
confere('usuario desativado nao entra mais', req(entrar('comum'), 'GET', 'dashboard.php')['status'] === 302);

$rec = sessao();
$r = req($rec, 'POST', 'recupera_senha.php', ['etapa' => 'solicitar', 'login' => 'gerente']);
preg_match('/<strong>([0-9A-F]{8})<\/strong>/', $r['corpo'], $m);
$r = req($rec, 'POST', 'recupera_senha.php', ['etapa' => 'redefinir', 'codigo' => 'XXXXXXXX', 'senha' => 'NovaSenha#1', 'confirmacao' => 'NovaSenha#1']);
confere('recuperacao com codigo errado e recusada', str_contains($r['corpo'], 'inválido'));
$r = req($rec, 'POST', 'recupera_senha.php', ['etapa' => 'redefinir', 'codigo' => $m[1] ?? '', 'senha' => 'NovaSenha#1', 'confirmacao' => 'NovaSenha#1']);
confere('recuperacao com codigo correto redefine a senha', str_contains($r['corpo'], 'Senha redefinida'));
confere('login com a senha nova funciona', req(entrar('gerente', 'NovaSenha#1'), 'GET', 'dashboard.php')['status'] === 200);

$token = csrf($admin, 'dashboard.php');
confere('saida por GET nao encerra a sessao (405)', req($admin, 'GET', 'sair.php')['status'] === 405);
req($admin, 'POST', 'sair.php', ['csrf' => $token]);
confere('saida por POST destroi a sessao', req($admin, 'GET', 'dashboard.php')['status'] === 302);

echo $falhas ? "\n$falhas falha(s)\n" : "\nTodos os testes passaram\n";
exit($falhas ? 1 : 0);
