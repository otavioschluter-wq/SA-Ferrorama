# A-TRAIN — Bloco 2

Aplicação PHP 8.2+ para login, sessão, trens e sensores. Usa Apache/XAMPP, mysqli com consultas preparadas e MySQL 8/MariaDB. As telas internas exigem sessão; o cadastro público continua criando apenas maquinistas.

## Instalação nova

1. Inicie Apache e MySQL/MariaDB. Use uma instância de banco acessível em `127.0.0.1:3306`.
2. Com uma conta **administrativa do banco**, importe `banco/script.sql` pelo phpMyAdmin ou cliente MySQL. Ele cria `frota_ferroviaria`, `trens`, `usuarios`, `sensores` e três trens plausíveis. A importação pode ser repetida: não substitui trens já cadastrados.
3. Crie a conta de aplicação e conceda somente os privilégios de execução. Substitua `SENHA_LOCAL` por senha própria, fora do repositório:

```sql
CREATE USER 'atrain_app'@'127.0.0.1' IDENTIFIED BY 'SENHA_LOCAL';
GRANT SELECT, INSERT, UPDATE ON frota_ferroviaria.usuarios TO 'atrain_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON frota_ferroviaria.trens TO 'atrain_app'@'127.0.0.1';
GRANT SELECT, INSERT, UPDATE, DELETE ON frota_ferroviaria.sensores TO 'atrain_app'@'127.0.0.1';
```

Se a conta já existe, use `SHOW GRANTS FOR 'atrain_app'@'127.0.0.1'` e conceda os privilégios faltantes. A conta de aplicação **não** precisa de `CREATE`, `ALTER` ou `DROP`; a instalação e a migração exigem uma conta administrativa do banco. Defina `ATRAIN_DB_PASSWORD` no ambiente recebido pelo Apache e reinicie o Apache. `config/conexao.php` usa essa variável e, por padrão, o banco `frota_ferroviaria`.

4. Para o primeiro administrador, gere um hash com PHP, sem armazenar a senha em texto no projeto:

```powershell
C:\xampp\php\php.exe -r 'echo password_hash(readline("Senha inicial: "), PASSWORD_DEFAULT), PHP_EOL;'
```

```sql
INSERT INTO frota_ferroviaria.usuarios (nome, login, senha, papel)
VALUES ('Administrador', 'admin', 'HASH_GERADO', 'administrador');
```

Substitua `HASH_GERADO`. Não repita a inserção se o login já existe.

## Migração do Bloco 1 sem perda de contas

Faça um backup do banco. Com uma conta administrativa, execute **`banco/migracao_bloco2.sql`** no banco que contém a tabela `usuario` original. O script renomeia `usuario` para `usuarios`, mantém `id`, `login`, `senha` (hash) e `papel`, preenche `nome` com o login nas contas antigas e adiciona a atribuição opcional de trem. Cria `trens` e `sensores` sem apagar registros. Pode ser repetido. Não execute `banco/script.sql` no lugar da migração sobre a instalação antiga.

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
- `sensores.codigo` é único no padrão `S-TEMP-001` (prefixos `TEMP`, `VELO`, `ENER`, `LOCA`, coerentes com o tipo). O trem é obrigatório, selecionado do banco e verificado novamente no servidor. Localização e segmento são textos obrigatórios. `ultima_leitura` é **indicador manual** neste bloco; não há tabela de leituras reais.
- As FKs de sensores e de atribuição de maquinista usam `ON DELETE RESTRICT`. Um trem com sensor ou maquinista vinculado não pode ser excluído. Isso evita perder o vínculo e preserva a possibilidade de histórico futuro. Para excluir, remova antes os sensores e as atribuições.
- Exclusões e saída usam `POST` com token CSRF; formulários de criação/edição também validam CSRF. Um `GET` em `sair.php` não encerra a sessão. A saída limpa dados, destrói a sessão no servidor e expira o cookie; páginas internas enviam `Cache-Control: no-store` para que o botão Voltar não exiba conteúdo antigo.
- Toda entrada em consulta variável usa `prepare` e `bind_param`; dados exibidos são tratados com `htmlspecialchars`. Restrições de unicidade, FK e valores no banco complementam a validação PHP.

## Arquivos e teste

`index.php` faz login; `principal.php` é a entrada interna; `trens.php` e `sensores.php` listam e filtram; `trens_form.php` e `sensores_form.php` criam e editam; `sair.php` encerra a sessão. `includes/proteger.php`, `permissao.php`, `seguranca.php` e `cabecalho.php` centralizam acesso, CSRF e navegação. `logout.php` é apenas um redirecionamento legado e não encerra a sessão. `usuarios/cadastrar.php` permanece exclusivo do administrador; `usuarios/registrar.php` é público.

Teste manualmente com administrador, gestor e maquinista: acesso anônimo e URL direta; login entre várias páginas; saída, Voltar e recarregamento; busca e filtro de trens; CRUD de trens e sensores; prefixo/código duplicados; trem inexistente; exclusão de trem vinculado; consulta restrita do maquinista. A lista é tabela no desktop e cartões no celular. `tests/bloco2_http.py` automatiza o fluxo HTTP contra **um banco isolado de teste** com contas de teste preparadas; recebe a senha pela variável `ATRAIN_TEST_PASSWORD` e a URL por `ATRAIN_TEST_URL`. Para direcionar uma instância PHP de teste a outro banco, configure `ATRAIN_DB_NAME` no ambiente do processo; sem ela, a aplicação usa `frota_ferroviaria`.
