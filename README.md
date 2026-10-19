# A-TRAIN — Bloco 3

Aplicação PHP 8.2+ para login, sessão, trens, sensores, rotas, leituras e alertas. Usa Apache/XAMPP, mysqli com consultas preparadas e MySQL 8/MariaDB. As telas internas exigem sessão; o cadastro público continua criando apenas maquinistas.

## Acesso administrativo desta instalação local

- Login: `admin_fundos`
- Senha: `xuxLj_o6MfS1zikwSsEKPAsoSBL3hy-x`

Esta é a credencial do banco local `sa_ferrorama_fundos` preparado para a apresentação.

## Instalação nova

1. Inicie Apache e MySQL/MariaDB. Use uma instância de banco acessível em `127.0.0.1:3306`.
2. Com uma conta **administrativa do banco**, importe `banco/script.sql` pelo phpMyAdmin ou cliente MySQL. Ele cria o banco exclusivo `sa_ferrorama_fundos`, `trens`, `usuarios`, `sensores`, `rotas`, `leituras` e `alertas`, além dos registros de exemplo. A importação pode ser repetida: não substitui trens já cadastrados.
3. Crie a conta de aplicação e conceda somente os privilégios de execução. Substitua `SENHA_LOCAL` por senha própria, fora do repositório:

```sql
CREATE USER 'sa_fundos_app'@'127.0.0.1' IDENTIFIED BY 'SENHA_LOCAL';
GRANT SELECT, INSERT, UPDATE ON sa_ferrorama_fundos.usuarios TO 'sa_fundos_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON sa_ferrorama_fundos.trens TO 'sa_fundos_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON sa_ferrorama_fundos.sensores TO 'sa_fundos_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON sa_ferrorama_fundos.rotas TO 'sa_fundos_app'@'127.0.0.1';
GRANT SELECT, INSERT ON sa_ferrorama_fundos.leituras TO 'sa_fundos_app'@'127.0.0.1';
GRANT SELECT, INSERT ON sa_ferrorama_fundos.alertas TO 'sa_fundos_app'@'127.0.0.1';
```

Se a conta já existe, use `SHOW GRANTS FOR 'sa_fundos_app'@'127.0.0.1'` e conceda os privilégios faltantes. A conta de aplicação **não** precisa de `CREATE`, `ALTER` ou `DROP`; a instalação e a migração exigem uma conta administrativa do banco. Defina `SA_FUNDOS_DB_PASSWORD` no ambiente recebido pelo Apache e reinicie o Apache. `config/conexao.php` usa essa variável e, por padrão, o banco `sa_ferrorama_fundos`. `SA_FUNDOS_DB_NAME` e `SA_FUNDOS_DB_PORT` permitem apontar uma instância isolada para testes.

4. Para criar um administrador em outra instalação, gere um hash com PHP:

```powershell
C:\xampp\php\php.exe -r 'echo password_hash(readline("Senha inicial: "), PASSWORD_DEFAULT), PHP_EOL;'
```

```sql
INSERT INTO sa_ferrorama_fundos.usuarios (nome, login, senha, papel)
VALUES ('Administrador', 'admin', 'HASH_GERADO', 'administrador');
```

Substitua `HASH_GERADO`. Não repita a inserção se o login já existe.

## Migração do Bloco 1 sem perda de contas

Faça um backup do banco exclusivo desta SA. Com uma conta administrativa, execute **`banco/migracao_bloco2.sql`** nele caso contenha a tabela `usuario` original. O script renomeia `usuario` para `usuarios`, mantém `id`, `login`, `senha` (hash) e `papel`, preenche `nome` com o login nas contas antigas e adiciona a atribuição opcional de trem. Cria `trens` e `sensores` sem apagar registros. Pode ser repetido. Não execute `banco/script.sql` no lugar da migração sobre a instalação antiga.

Depois da migração, confira os `GRANT`s acima. Privilégios antigos concedidos à tabela singular `usuario` não substituem os privilégios necessários em `usuarios`. Trens iniciais são criados apenas na instalação nova; no banco migrado, cadastre trens pela interface.

## Papéis e atribuição de trem

| Papel gravado | Correspondência no guia | Trens | Sensores | Usuários |
|---|---|---|---|---|
| `administrador` | Administrador | Gere | Gere | Cadastra e atribui trem |
| `gestor` | Gerente | Gere | Gere | Sem acesso |
| `maquinista` | Maquinista | Sem acesso | Consulta apenas os do trem atribuído | Sem acesso |

O projeto não cria conta de usuário comum nesta etapa. Se esse papel aparecer em dados externos, não passa pela proteção das páginas internas. O registro público fixa o papel `maquinista` no servidor e começa **sem trem atribuído**. Um administrador atribui ou remove o trem em **Atribuir trem** (`usuarios/atribuir_trem.php`), inclusive para contas criadas no registro público. O cadastro administrativo também permite atribuir o trem ao criar um maquinista. Sem atribuição, a consulta de sensores do maquinista fica vazia. A restrição é aplicada na consulta SQL a cada acesso, não apenas no menu ou na sessão.

## Dados e regras

- `trens.prefixo` é único no banco e validado no PHP no padrão `TR-204`. O status aceita `normal`, `atencao` e `critico`; esses valores possuem cores verde, amarela e vermelha. Ano entre 1900 e 2100, capacidade `DECIMAL(8,2)` positiva em toneladas e última inspeção opcional até a data atual.
- `sensores.codigo` é único no padrão `S-TEMP-001` (prefixos `TEMP`, `VELO`, `ENER`, `LOCA`, coerentes com o tipo). O trem é obrigatório, selecionado do banco e verificado novamente no servidor. Localização e segmento são textos obrigatórios. O indicador mostrado em `sensores.php` é calculado pela leitura mais recente; `ultima_leitura` permanece na tabela por compatibilidade com o Bloco 2, mas não é mais editado nem consultado pela interface. Código, tipo e trem ficam fixos depois da primeira leitura, para preservar o histórico.
- As FKs de sensores e de atribuição de maquinista usam `ON DELETE RESTRICT`. Um trem com sensor ou maquinista vinculado não pode ser excluído. Isso evita perder o vínculo e preserva a possibilidade de histórico futuro. Para excluir, remova antes os sensores e as atribuições.
- Exclusões e saída usam `POST` com token CSRF; formulários de criação/edição também validam CSRF. Um `GET` em `sair.php` não encerra a sessão. A saída limpa dados, destrói a sessão no servidor e expira o cookie; páginas internas enviam `Cache-Control: no-store` para que o botão Voltar não exiba conteúdo antigo.
- Toda entrada em consulta variável usa `prepare` e `bind_param`; dados exibidos são tratados com `htmlspecialchars`. Restrições de unicidade, FK e valores no banco complementam a validação PHP.

## Arquivos e teste

`index.php` faz login; `principal.php` é a entrada interna; `trens.php` e `sensores.php` listam e filtram; `trens_form.php` e `sensores_form.php` criam e editam; `sair.php` encerra a sessão. `includes/proteger.php`, `permissao.php`, `seguranca.php` e `cabecalho.php` centralizam acesso, CSRF e navegação. `logout.php` é apenas um redirecionamento legado e não encerra a sessão. `usuarios/cadastrar.php` permanece exclusivo do administrador; `usuarios/registrar.php` é público.

## Bloco 3: instalação e migração

Em uma instalação nova, `banco/script.sql` cria também `rotas`, `leituras` e `alertas`, com pelo menos três registros de exemplo em cada tabela. A importação é repetível. Para uma instalação que já concluiu o Bloco 2, faça backup do banco **exclusivo** `sa_ferrorama_fundos` e execute `banco/migracao_bloco3.sql` com uma conta administrativa do MySQL. A migração cria somente as novas tabelas e preserva os registros antigos; não execute o script de instalação nova como migração. Depois aplique os `GRANT`s acima. A conta de aplicação não possui privilégios de alteração de esquema.

## Decisões do Bloco 3

- **Rotas:** data, partida e chegada ficam em campos `DATE` e `TIME` separados. Isso torna o filtro inclusivo por dia simples e mantém a viagem no mesmo dia; uma viagem que cruza a meia-noite exigirá rever o modelo. A rota representa o trajeto e a viagem programada. O trem não pode ser removido enquanto houver rotas. Sobreposição de horários ainda é permitida: o bloqueio de conflitos é a extensão de nível Avançado do guia.
- **Leituras:** cada leitura guarda um valor `DECIMAL(10,2)` e a unidade; o tipo vem do sensor. Assim novos tipos podem usar a mesma estrutura. Os índices por sensor e data ajudam a busca da leitura recente, e as telas limitam a 100 registros. Excluir um sensor apaga suas leituras e, em cascata, os alertas; um trem com sensor permanece protegido pela FK do Bloco 2.
- **Limites:** ficam em `includes/limites.php`, concentrados em um único arquivo, simples de revisar para a apresentação. Alterá-los exige mudar o código; valores históricos e alertas já gravados não são recalculados. Temperatura usa °C (limite 90), velocidade km/h (120), energia kWh (500) e localização km percorridos no segmento (1000). A localização é uma medida numérica simulada, não coordenadas geográficas.
- **Severidade:** valor até o limite é normal e não gera alerta; acima do limite até 10% é atenção; acima de 10% é crítico. O simulador pode gerar valores acima do limite de propósito. Leitura e alerta são inseridos na mesma transação: ambos persistem ou nenhum persiste.
- **Alertas e notificações:** esta etapa concentra o fato detectado e o aviso na tabela `alertas`. A central `notificacoes_alertas.php` mostra o sensor e trem de origem por junções. Entrega individual de notificações e estado de leitura por destinatário ficam fora do Bloco 3.

`rotas.php` aplica status, intervalo de data, trem e busca por origem/destino de forma combinável; `rotas_form.php` cria e edita. `leituras.php` filtra por sensor e trem; `simulador.php` gera de 1 a 100 leituras por operação, com consulta preparada fora do laço. `notificacoes_alertas.php` filtra por severidade e tipo. Administrador e gestor gerem rotas e executam o simulador; o maquinista consulta apenas dados do trem atribuído. Todas as mutações usam POST e CSRF.

`tests/bloco3_http.php` cobre sessão, papéis, CSRF, CRUD e filtros de rotas, simulação, alertas, indicador e exclusão em cascata. Execute-o **somente em banco descartável**, com contas `admin_bloco3` e `maq_bloco3`, os trens de exemplo e as variáveis `SA_FUNDOS_TEST_URL` e `SA_FUNDOS_TEST_PASSWORD`. O teste altera e remove registros. `tests/bloco2_http.php` continua cobrindo os fluxos anteriores, agora com a expectativa de sensor sem leitura.

Teste manualmente com administrador, gestor e maquinista: acesso anônimo e URL direta; login entre várias páginas; saída, Voltar e recarregamento; busca e filtro de trens; CRUD de trens e sensores; prefixo/código duplicados; trem inexistente; exclusão de trem vinculado; consulta restrita do maquinista. A lista é tabela no desktop e cartões no celular. `tests/bloco2_http.php` automatiza o fluxo HTTP usando a extensão `curl` do próprio PHP, contra **um banco isolado de teste** com contas `admin_bloco2`, `gestor_bloco2`, `maq_bloco2` e `sem_trem_bloco2` preparadas e sensores de exemplo nos trens 1 e 2. Execute `C:\xampp\php\php.exe tests\bloco2_http.php`, definindo `SA_FUNDOS_TEST_PASSWORD` e, se necessário, `SA_FUNDOS_TEST_URL`. Para direcionar uma instância PHP de teste a outro banco, configure `SA_FUNDOS_DB_NAME` no ambiente do processo; sem ela, a aplicação usa `sa_ferrorama_fundos`.
