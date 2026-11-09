# A-TRAIN - Mapeamento de Ferrovias (SA Fundos)

Sistema PHP puro com MySQL (mysqli, consultas preparadas) para trens, sensores, rotas, leituras, alertas, painel e relatorios. Projeto da Situacao de Aprendizagem de Desenvolvimento de Sistemas, SESI Joinville Moinho.

## Instalacao

1. Extraia a pasta do projeto em `C:\xampp\htdocs\SA-Ferrorama-Fundos`.
2. No painel do XAMPP, inicie Apache e MySQL.
3. Abra `http://localhost/SA-Ferrorama-Fundos/`.

Na primeira abertura o sistema cria sozinho o banco `sa_ferrorama_fundos` importando `banco/script.sql` (usuario `root` sem senha, padrao do XAMPP). Para recriar o banco do zero, apague-o no phpMyAdmin e recarregue a pagina, ou importe `banco/script.sql` manualmente (ele executa `DROP DATABASE IF EXISTS`).

## Usuarios de teste (senha de todos: `Ferro@2026`)

| Login | Perfil do guia | Papel gravado |
|---|---|---|
| `admin` | Administrador | `administrador` |
| `gerente` | Gerente | `gestor` |
| `maquinista` | Maquinista (trem TR-204) | `maquinista` |
| `maquinista2` | Maquinista (trem TR-318) | `maquinista` |
| `comum` | Usuario comum | `usuario` |
| `desativado` | Usuario comum desativado (nao entra) | `usuario` |

## Telas

| Arquivo | Funcao | Acesso |
|---|---|---|
| `index.php` | Login (recusa senha errada e usuario desativado) | publico |
| `usuarios/registrar.php` | Cadastro publico (cria maquinista) | publico |
| `recupera_senha.php` | Codigo de recuperacao exibido na tela e redefinicao | publico |
| `dashboard.php` | Cinco indicadores, conforme o perfil | todos |
| `trens.php`, `trens_form.php` | Listar, buscar, cadastrar, editar, excluir trens | admin, gerente |
| `sensores.php`, `sensores_form.php` | CRUD de sensores com trem por chave estrangeira | admin, gerente (maquinista consulta o seu trem) |
| `rotas.php`, `rotas_form.php` | CRUD de rotas com filtros de status, data, trem e rota | admin, gerente (maquinista consulta o seu trem) |
| `leituras.php` | Consulta de leituras | admin, gerente, maquinista |
| `simulador.php` | Gera leituras em lote e os alertas fora do limite | admin, gerente |
| `notificacoes_alertas.php` | Central de alertas por severidade e tipo | admin, gerente, maquinista |
| `gera_relatorio.php` | Formulario GET de tipo, periodo e filtros | admin, gerente |
| `visualiza_relatorio.php` | Filtros aplicados, tabela, grafico, CSV e impressao | admin, gerente |
| `usuarios.php` | Listar, editar e desativar usuarios | admin |
| `usuarios/cadastrar.php`, `usuarios/atribuir_trem.php` | Cadastro administrativo e vinculo maquinista-trem | admin |
| `sair.php` | Encerra e destroi a sessao (somente POST com CSRF) | logado |

## Decisoes

- **Perfis:** o guia fala em gerente e usuario comum; o banco usa `gestor` e `usuario`. A verificacao de perfil e feita no servidor em cada pagina (`exigirPapel`) e nas consultas, nao apenas no menu.
- **Instalacao sem passos manuais:** `config/conexao.php` conecta como `root` sem senha e importa o script se o banco nao existir. Em producao seria usada uma conta propria com privilegios minimos.
- **Prefixo e codigo unicos:** `trens.prefixo` e `sensores.codigo` sao `UNIQUE` e validados por padrao (`TR-204`, `S-TEMP-001`).
- **ON DELETE:** sensores, rotas e maquinistas usam `RESTRICT` para nao perder o vinculo com o trem; leituras e alertas usam `CASCADE` a partir do sensor e da leitura.
- **Leitura:** valor `DECIMAL(10,2)` mais unidade; o tipo da medida vem do sensor. O indicador de cada sensor e calculado pela leitura mais recente.
- **Limites e severidade:** ficam em `includes/limites.php`. Ate o limite e normal; ate 10% acima e atencao; acima disso e critico. Leitura e alerta sao gravados na mesma transacao.
- **Alertas e notificacoes:** o alerta e o fato detectado e tambem o aviso exibido; nao ha tabela de entrega de notificacoes.
- **Rotas:** data, partida e chegada em campos `DATE` e `TIME`; o status `atrasada` foi acrescentado porque o relatorio de eficiencia o exige.
- **Vinculo do maquinista com o trem:** coluna `usuarios.trem_atribuido_id`. O painel, os alertas, os sensores e as rotas do maquinista sao filtrados por ela no servidor.
- **Alerta ativo:** alerta das ultimas 24 horas. A velocidade media usa a mesma janela.
- **Ausencia de dados:** cartoes sem leituras mostram "Sem dados", nunca zero. Velocidade media nula e tratada como ausencia.
- **Grafico:** barras em CSS com largura proporcional ao maior valor, sem biblioteca, funciona offline e sempre reflete a coluna da tabela. Limitacao: sem eixos nem interacao.
- **Recuperacao de senha:** o codigo e gerado na tela, guardado como hash na sessao e expira em 10 minutos, simulando o e-mail. O hash novo da senha usa `password_hash`. Limitacao: em producao o codigo iria por e-mail e seria guardado no banco.
- **Usuarios:** nunca sao excluidos, so desativados (preserva o historico, inclusive `relatorios.id_usuario`). Travas: nao desativar a si mesmo; o ultimo administrador ativo nao pode ser desativado nem mudar de papel; edicao so por administrador.
- **Relatorios:** formulario por GET (consulta, pode ser salvo e recarregado); exportacao CSV com BOM UTF-8 e separador `;` para abrir acentuado no Excel pt-BR; periodo inclusivo (`DATE(coluna) BETWEEN`). Cada relatorio visualizado e registrado em `relatorios`.
- **Impressao:** `window.print()` com `@media print` escondendo menu e botoes.

## Limitacoes conhecidas

- Nao ha reativacao de usuario pela interface (o guia pede so editar e desativar); use o phpMyAdmin.
- Uma sessao ja aberta de um usuario desativado so termina ao sair; o bloqueio ocorre no login.
- Sobreposicao de horarios de rotas e permitida.
- Os limites dos sensores so mudam no codigo.
- A conta `root` sem senha serve apenas para ambiente local de estudo.

## Registro de uso de IA

O Claude Code (Anthropic) foi usado para adaptar o Bloco 4 sobre o codigo existente, escrever `banco/script.sql`, `tests/teste_http.php` e este README.

## Testes

`C:\xampp\php\php.exe tests\teste_http.php` roda os cenarios abaixo contra o sistema em execucao (altera dados; use banco recem-importado). Resultado da ultima execucao: 32 de 32 verificacoes passaram. Nenhum cenario falhou na execucao final; uma asserção do teste de CSV foi corrigida durante a escrita (os cabecalhos saem entre aspas) e um defeito real foi corrigido antes: a conexao lia uma senha de ambiente e recusava o `root` padrao do XAMPP.

| Nº | O que se testa | Resultado esperado | Obtido | Situacao |
|---|---|---|---|---|
| 1 | Pagina interna sem sessao | Redireciona ao login | Redirecionou | Passou |
| 2 | Login com senha errada | Acesso negado | Negado | Passou |
| 3 | Login com injecao SQL (`' OR '1'='1`) | Acesso negado | Negado | Passou |
| 4 | Login de usuario desativado | Acesso negado | Negado | Passou |
| 5 | Painel do administrador | Cinco indicadores com dados reais | 5 cartoes | Passou |
| 6 | Painel do maquinista | Apenas alertas, sensores criticos e viagem do seu trem | Sem dados da frota | Passou |
| 7 | Painel do usuario comum | Apenas viagens em andamento | Sem dados tecnicos | Passou |
| 8 | Usuario comum abre `trens.php` | 403 | 403 | Passou |
| 9 | Maquinista abre relatorios e usuarios | 403 | 403 | Passou |
| 10 | Quatro tipos de relatorio | Filtros, tabela e grafico | Todos exibidos | Passou |
| 11 | Relatorio com periodo invertido | 400 | 400 | Passou |
| 12 | Exportar CSV | Download `text/csv` UTF-8 com BOM | Baixou, acentos corretos | Passou |
| 13 | Relatorio em periodo sem dados | Mensagem de ausencia | "Sem dados" | Passou |
| 14 | Painel sem leituras (banco com `leituras` vazia) | "Sem dados" no lugar de zero | "Sem dados" | Passou |
| 15 | Simulador com 5 leituras fora do limite | 5 leituras e 5 alertas | 5 e 5 | Passou |
| 16 | Filtro de rotas por status | Somente rotas atrasadas | Filtrou | Passou |
| 17 | Central de alertas por severidade critica | Somente criticos, em destaque | Filtrou | Passou |
| 18 | Excluir trem com sensores | Bloqueado com mensagem | Bloqueado | Passou |
| 19 | Administrador desativa a si mesmo | Recusado | Recusado | Passou |
| 20 | Ultimo administrador muda de papel | Recusado | Recusado | Passou |
| 21 | Desativar sem token CSRF | 403 | 403 | Passou |
| 22 | Desativar outro usuario e tentar entrar | Desativa; login recusado | Recusado | Passou |
| 23 | Recuperacao com codigo errado | Recusada | Recusada | Passou |
| 24 | Recuperacao com codigo correto e login com a senha nova | Senha trocada; login funciona | Funcionou | Passou |
| 25 | Sair por GET | 405, sessao mantida | 405 | Passou |
| 26 | Sair por POST | Sessao destruida | Redireciona ao login | Passou |
| 27 | Instalacao limpa (banco inexistente, abrir o sistema) | Banco criado e login funcionando | Criado e funcionando | Passou |

O cenario 27 foi executado em MariaDB 10.4 do XAMPP (porta alternativa) com a pasta do repositorio, sem nenhum outro passo.
