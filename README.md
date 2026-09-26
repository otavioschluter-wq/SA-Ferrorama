# A-TRAIN

Porta de entrada do sistema de mapeamento de ferrovias: banco de usuários, conexão, login, sessão, logout, criação pública de contas de maquinista e cadastro de outros papéis por administradores. Esta entrega cobre apenas as estruturas necessárias das Fases 1 e 2 e o Bloco 1 da Fase 3.

## Requisitos e instalação

Use Apache do XAMPP, PHP 8.2 ou superior com as extensões `mysqli` e `mbstring`, e MySQL ou MariaDB acessível em `127.0.0.1:3306`. Inicie o Apache no painel do XAMPP. Para usar o MariaDB incluído no XAMPP, inicie também o botão **MySQL** quando a porta 3306 estiver livre. Nesta instalação local, a porta já pertence ao serviço separado `MySQL_A_TRAIN` (MySQL Community Server 8.4.9); mantenha esse serviço ativo para usar o banco já preparado. Não inicie simultaneamente dois bancos na mesma porta.

Abra `http://localhost/phpmyadmin/` e entre com uma conta administrativa do banco. Confirme que o phpMyAdmin está conectado à mesma instância que será usada pela aplicação. Na aba **Importar**, selecione `banco/script.sql` e execute. O script cria o banco `frota_ferroviaria` e a tabela `usuario` se ainda não existirem, sem remover dados existentes. Como alternativa, utilize o cliente MySQL para executar o arquivo. Não importe sobre um banco homônimo sem verificar seu conteúdo.

A conexão utiliza a conta `atrain_app` em `127.0.0.1` e a variável de ambiente `ATRAIN_DB_PASSWORD`, acessível ao processo Apache. Em uma instalação nova, um administrador do banco pode criar a conta com:

```sql
CREATE USER 'atrain_app'@'127.0.0.1' IDENTIFIED BY 'SENHA_LOCAL';
GRANT SELECT, INSERT ON frota_ferroviaria.usuario TO 'atrain_app'@'127.0.0.1';
```

Substitua `SENHA_LOCAL` por uma senha própria, sem gravá-la no repositório. Se a conta já existir, confira suas permissões em vez de recriá-la. Configure `ATRAIN_DB_PASSWORD` nas variáveis de ambiente de usuário do Windows com a mesma senha. Abra novamente o painel do XAMPP e inicie o Apache para que o processo receba a variável. Apenas `config/conexao.php` lê esses dados.

Para instalar o primeiro administrador, escolha uma senha e gere o hash no terminal com PHP:

```powershell
C:\xampp\php\php.exe -r 'echo password_hash(readline("Senha inicial: "), PASSWORD_DEFAULT), PHP_EOL;'
```

Execute no banco `frota_ferroviaria`, substituindo `HASH_GERADO` pelo resultado:

```sql
INSERT INTO usuario (login, senha, papel)
VALUES ('admin', 'HASH_GERADO', 'administrador');
```

Não armazene a senha original no SQL ou no projeto. Se o administrador já existir, não repita a inserção.

A coluna `senha VARCHAR(255)` comporta hashes produzidos por `password_hash(..., PASSWORD_DEFAULT)` e possíveis comprimentos maiores em versões futuras do PHP. A coluna `papel VARCHAR(20) NOT NULL` aceita os valores validados no PHP: `administrador`, `maquinista` e `gestor`. O banco também impede logins duplicados com uma restrição única.

## Estrutura

- `index.php`: formulário e processamento do login.
- `principal.php`: primeira página protegida.
- `logout.php`: encerra a sessão.
- `config/conexao.php`: conexão mysqli e charset utf8mb4.
- `includes/proteger.php` e `includes/permissao.php`: sessão e autorização.
- `usuarios/cadastrar.php`: cadastro restrito ao administrador.
- `usuarios/registrar.php`: criação de conta de maquinista a partir da tela de login.
- `banco/script.sql`: estrutura do banco.
- `assets/css/estilo.css`: estilo compartilhado.

`usuarios/listar.php`, `trens/`, `leituras/` e `api/` pertencem a fases posteriores e não possuem funcionalidades neste bloco.

## Uso e verificações

Acesse o sistema pelo endereço local configurado no Apache. Na página de login, **Criar conta** permite que uma pessoa sem sessão se cadastre como `maquinista`; esse papel é definido no PHP e não pode ser escolhido no formulário público. Após o cadastro, a pessoa volta ao login e pode entrar com a nova conta. Senhas incorretas ou usuários inexistentes exibem a mesma mensagem.

Na página principal, o administrador pode abrir **Cadastrar usuário** e criar contas de qualquer um dos três papéis. Maquinistas e gestores não podem abrir ou enviar esse cadastro administrativo; visitantes são redirecionados ao login. Confira também login repetido, confirmação divergente, papel inválido no cadastro administrativo e acesso direto à página protegida após logout.

O XAMPP pode fornecer MariaDB sob o rótulo **MySQL**. A aplicação utiliza mysqli e é compatível com MySQL/MariaDB; escolha um único serviço de banco e configure o phpMyAdmin e `config/conexao.php` para a mesma instância. A disponibilidade dos recursos opcionais de armazenamento do phpMyAdmin não é necessária para este sistema.
