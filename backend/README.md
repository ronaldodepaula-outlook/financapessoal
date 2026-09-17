# API de controle financeiro pessoal

Laravel 12.69.2, PHP 8.2+ e MySQL/MariaDB. **Fases 1 a 10 implementadas:** arquitetura, JWT, cadastros, movimentos, parcelamentos, recorrências, empréstimos, orçamentos, metas, dashboard/relatórios/exportação CSV e importação CSV/OFX. O frontend PHP puro (`../frontend/`) e o app mobile Android (`../mobile/`) já consomem esta API — ver [README raiz](../README.md) para o panorama dos três subsistemas e [docs/roadmap.md](../docs/roadmap.md) para o status atualizado fase a fase.

## Ambiente configurado

Banco local `db_financeiro_pessoal`, host `localhost:3306`, usuário `root`, senha vazia, conforme dados fornecidos. MariaDB 10.4.32 validado pelo driver mysql. Atualmente são 31 migrations e 26 models (a contagem de tabelas/colunas cresceu além do valor original de "29 tabelas, 337 colunas" registrado nas fases 1–8; para o número exato, use `php artisan migrate:status` ou consulte diretamente `information_schema` no ambiente local). A migration das fases 6–8 adiciona vínculos de empréstimos, tratamento de desconto em folha, âncora de assinaturas e cancelamento de contribuições; a migration `2026_09_17_000001_support_import_review.php` (fase 10) adiciona suporte à reabertura de importações confirmadas. As migrations anteriores foram preservadas.

Dependências exatas no composer.lock. Extensões: BCMath, PDO/MySQL, PDO/SQLite para testes, mbstring, OpenSSL, cURL, XML e ZIP. PHP detectado em `C:\dev\xampp\php\php.exe`.

## Instalação ou atualização

Na pasta backend:

```powershell
composer install
```

Somente em uma instalação nova, copiar `.env.example` para `.env` e executar `php artisan key:generate`. Preservar `.env` e APP_KEY de instalação existente. Em seguida:

```powershell
php artisan finance:jwt-secret
php artisan migrate
```

O comando JWT gera um segredo forte apenas quando estiver vazio e não o exibe. Neste workspace, o segredo e as migrations já estão configurados. Não executar migrate:fresh ou rollback no banco com dados reais.

## Criar o primeiro usuário

```powershell
php artisan finance:user seu-email@exemplo.com --name="Seu nome"
```

O comando solicita senha e confirmação em entrada oculta, sem argumento de senha na linha de comando. Exige pelo menos 12 caracteres, maiúsculas, minúsculas e número. Cria usuário com hash, categorias/subcategorias, metas orçamentárias de 2027/2028, duas rendas inativas e consignado em rascunho. Valores vêm de `config/initial-planning.json` e podem ser ajustados pela API. Não cria conta, saldo ou lançamento financeiro. Não existe usuário/senha padrão nem cadastro público pela API. Usuários existentes podem executar `POST /planning/initialize`, que preserva ajustes anteriores.

## Execução

XAMPP já validado:

```text
http://localhost/financapessoal/backend/public/api
```

Alternativa com servidor PHP, caso a porta esteja livre:

```powershell
php artisan serve --host=127.0.0.1 --port=8000
```

Base alternativa: `http://127.0.0.1:8000/api`. Publicar somente backend/public. O .htaccess da raiz bloqueia código/credenciais; o diretório público tem sua própria regra. Foram verificados HTTP 200 no health, 401 em accounts sem token e 403 no acesso direto a .env.

## Autenticação

```http
POST /api/auth/login
Content-Type: application/json

{"email":"seu-email@exemplo.com","password":"sua senha"}
```

Enviar o access_token retornado no cabeçalho `Authorization: Bearer TOKEN`. `GET /auth/me` retorna o usuário. `POST /auth/refresh` recebe `{"refresh_token":"TOKEN_DE_RENOVACAO"}` e rotaciona os dois tokens; os anteriores deixam de funcionar. `POST /auth/logout` revoga a sessão atual.

Access token JWT HS256, assinatura pela biblioteca firebase/php-jwt 7.1.0, expiração padrão de 60 minutos; sessão renovável por prazo absoluto de 30 dias. Refresh opaco armazenado somente como hash no banco. Usuário inativo e sessão revogada/expirada retornam 401. O frontend armazenará tokens na sessão PHP, não no navegador. API autenticada usa Cache-Control no-store.

## Rotas entregues

| Módulo | Rotas |
|---|---|
| Infraestrutura | GET health |
| Autenticação | POST auth/login, auth/refresh, auth/logout; GET auth/me |
| Cadastros | CRUD accounts, cards, categories e merchants |
| Movimentos | CRUD transactions e transfers |
| Faturas | GET/POST card-invoices; GET card-invoices/{id}; GET/POST card-invoices/{id}/payments; POST card-invoices/{id}/link-transactions; DELETE card-invoice-payments/{id} |
| Parcelamentos | GET/POST installments; GET/DELETE installments/{id}; baixa via PUT transactions/{id} |
| Recorrências | CRUD fixed-expenses, subscriptions e income-schedules; POST forecasts/generate |
| Empréstimos | GET/POST loans; GET/PUT/DELETE loans/{id}; POST activate; GET/POST payments e balances dentro do contrato; DELETE loan-payments/{id} |
| Orçamentos | GET/POST budgets; GET/PUT budgets/{id}; POST budgets/copy; GET budgets/comparison e budgets/fortnight |
| Planejamento | POST planning/initialize; GET/PUT preferences |
| Metas | CRUD goals; GET/POST goals/{id}/contributions; DELETE goal-contributions/{id} |
| Dashboard e relatórios | GET dashboard; GET reports/summary; GET reports/transactions; GET reports/export (CSV) |
| Importação de extratos | GET/POST imports; GET imports/{id}; GET/PUT imports/{id}/rows; PUT imports/{id}/mapping; POST imports/{id}/confirm; PUT import-rows/{id} |

CRUD significa GET lista, POST criação e GET/PUT/DELETE por id. DELETE arquiva cadastros ou cancela movimentos; não apaga histórico. São **103 operações em 58 caminhos** (contagem atual do `openapi.json`; os "90 operações em 47 caminhos" de fases anteriores não incluíam dashboard/relatórios/importação). Orçamentos preservam revisões e admitem valor zero, sem exclusão de histórico. Listas retornam `data.items` e `data.pagination`, com page e per_page até 100.

Todas as respostas seguem `{success,message,data,errors}`. Erros 422 preservam campos, 401 exige autenticação, 404 também protege registros de outros usuários, 409 sinaliza conflito com fatos existentes e 429 inclui Retry-After. Login tem limites de 5/minuto por e-mail e 20/minuto por IP; demais rotas, 60/minuto por IP.

## Regras implementadas

- Dinheiro como string decimal (`"1024.77"`), até duas casas; cálculos com BCMath. Datas financeiras YYYY-MM-DD.
- Categoria obrigatória e do mesmo tipo do lançamento; subcategoria deve pertencer à categoria. Proprietário conferido em consultas, validações e FKs.
- Receita/despesa fora de cartão exige conta; compra a crédito exige cartão e não baixa conta bancária.
- Transferência usa origem/destino diferentes e não cria receita/despesa. Pagamento de fatura não duplica a compra.
- Saldo atual e limite utilizado são derivados; saldo inicial não pode ser reescrito após movimentação.
- Parcelamentos geram cronograma de até 600 parcelas, distribuindo os centavos restantes nas primeiras parcelas e preservando o total. Meses curtos usam o último dia, sem mudar o dia-base dos meses seguintes.
- Parcela gerada permite alterar somente status, notes e card_invoice_id. Cancelamento do parcelamento preserva parcelas pagas.
- Auditoria registra autor, entidade, ação e campos alterados, sem credenciais/payload completo. Escritas usam transações e locks.

Regras detalhadas, exemplos e limites estão no [guia dos módulos](../docs/modulos-api.md). O [relatório de entrega](../docs/fases-02-a-05.md) discrimina cada fase.

## OpenAPI / Swagger

[docs/openapi.json](docs/openapi.json), OpenAPI 3.0.3, versão 0.8.0. Documento validado com @apidevtools/swagger-parser, com autenticação, corpos, parâmetros, exemplos e respostas dos endpoints existentes. Pode ser importado em visualizador Swagger/OpenAPI. A UI Swagger será consolidada na Fase 11.

O gerador opcional não exige pacotes Node:

```powershell
node tools/generate-openapi.mjs
```

Editar o gerador ao alterar contratos e regenerar o JSON; o teste de infraestrutura verifica que todas as rotas publicadas estão documentadas.

## Variáveis de ambiente

| Variáveis | Finalidade |
|---|---|
| APP_NAME, APP_ENV, APP_KEY, APP_URL | Identificação, ambiente, criptografia e URL |
| APP_DEBUG, APP_TIMEZONE | false; America/Sao_Paulo |
| DB_CONNECTION, DB_HOST, DB_PORT | mysql, localhost, 3306 |
| DB_DATABASE, DB_USERNAME, DB_PASSWORD | Credenciais locais, somente no backend |
| JWT_SECRET | Gerado por finance:jwt-secret; preservado quando já existe |
| JWT_TTL, JWT_REFRESH_TTL, JWT_ISSUER | 60 minutos, 43200 minutos e emissor configurável |
| CACHE_STORE, SESSION_DRIVER, QUEUE_CONNECTION | file, array, sync neste ambiente |

O .env está ignorado no Git. Nenhuma credencial de banco é copiada ao frontend. Exceções inesperadas registram apenas a classe, sem SQL, payload ou tokens.

## Testes

```powershell
composer test
php vendor/bin/pint --test
composer validate --strict
composer audit
```

Resultado desta entrega (fases 1–8): 63 testes aprovados, 589 assertions. **Esse número está desatualizado** — os testes de importação (`ImportTest.php`) e de relatórios/dashboard (`ReportTest.php`) foram adicionados depois; atualmente são cerca de **76 métodos de teste** entre `Feature/` e `Unit/`. Rode `composer test` para o total exato de testes e assertions no seu ambiente. Cobre regressões, autenticação, criação de usuário, cadastros, autorização, movimentos/saldos, faturas, parcelas, recorrências, empréstimos/estornos, planejamento, metas, importação de extratos e relatórios/dashboard. O PHPUnit força SQLite :memory: e rejeita outro banco antes dos hooks de migrations.

Verificações opcionais no MySQL/MariaDB local:

```powershell
php tests/mysql-smoke.php
php tests/mysql-api-smoke.php
```

Ambos limitados a APP_ENV=local e driver mysql. Usam transação revertida e não executam DDL. O segundo percorre o kernel HTTP Laravel com o MariaDB, verificando autenticação, cadastros, parcelas, recorrências, quitação/estorno, consignado líquido, inicialização, revisão/cópia de orçamento e metas. Não imprime credenciais e não persiste dados temporários. AUTO_INCREMENT pode avançar após rollback.

O smoke identificou e permitiu corrigir a atualização automática de TIMESTAMP na expiração de sessão do MariaDB. O fluxo completo passou após a migration corretiva. Os testes usam rollback e não deixam usuários temporários, sessões ou lançamentos. A conta existente foi preservada e recebeu os 168 orçamentos iniciais, duas rendas inativas e um consignado em rascunho solicitados no prompt; nenhum lançamento financeiro foi gerado nessa inicialização.

## Estrutura e próximas fases

Controllers recebem HTTP; Requests validam; Services concentram regras; CatalogRepository reutiliza consultas por proprietário; Models/Enums representam o domínio; Rules e Helpers reutilizam validação e dinheiro. Comandos ficam em app/Console/Commands. O frontend segue sem banco e sem cálculos financeiros.

[Modelo](../docs/modelo-de-dados.md), [dicionário aplicado](../docs/dicionario-de-dados.md), [roadmap](../docs/roadmap.md) e [pendências financeiras](../docs/decisoes-pendentes.md). Para regras de negócio completas (incluindo relatórios, importação e os fluxos do mobile), veja [docs/guia-analista.md](../docs/guia-analista.md).

Dashboard, relatórios/exportação CSV e importação CSV/OFX (antigas fases 9 e 10) já estão implementados — ver `ReportController`/`ReportService` e `ImportController`/`ImportService`. O que resta em aberto: consolidar exemplos/schemas nomeados no OpenAPI (fase 11), ampliar a suíte de testes end-to-end do frontend (fase 12/14 — hoje sem automação, só revisão manual/adversarial) e a homologação com os dados financeiros reais (fase 15), que depende das datas e do tratamento da renda do contrato inicial ainda pendentes de confirmação em [decisoes-pendentes.md](../docs/decisoes-pendentes.md).

## Recorrências e execução automática

O comando gera o mês atual e o seguinte por padrão, apenas para usuários e recorrências ativos. Pode ser repetido sem duplicar previsões:

```powershell
php artisan finance:generate-forecasts --from=2027-01 --months=12
php artisan schedule:list
```

O Laravel agenda a geração diariamente às 03:00 em America/Sao_Paulo. Em servidores com cron, executar `php artisan schedule:run` a cada minuto. Em desenvolvimento, `php artisan schedule:work` mantém o processo do scheduler. Referência: [agendamento Laravel 12](https://laravel.com/docs/12.x/scheduling).

No Windows deste workspace foi instalada a tarefa `FinancaPessoal-Previsoes-F9C43F64A0D4`, às 03:00 no fuso local, para o usuário atual conectado. Ela executa o comando de geração diretamente, em processo oculto, com recuperação de horário perdido (`StartWhenAvailable`). Banco/PHP precisam estar disponíveis. Em outra máquina, instalar com:

```powershell
.\tools\install-scheduler.ps1 -PhpPath 'C:\dev\xampp\php\php.exe'
```

O nome inclui um hash do diretório para isolar instalações. O script é idempotente e rejeita uma tarefa homônima com ação diferente. Para consultar: `Get-ScheduledTaskInfo -TaskName 'FinancaPessoal-Previsoes-F9C43F64A0D4'`. Para desinstalar esta tarefa: `Unregister-ScheduledTask -TaskName 'FinancaPessoal-Previsoes-F9C43F64A0D4' -Confirm:$false`.

Os contratos financeiros e exemplos de uso estão em [planejamento-api.md](../docs/planejamento-api.md). Contas fixas e assinaturas preservam a primeira data-base; vencimentos no dia 31 retornam ao dia 31 após fevereiro. Empréstimos não calculam Price/SAC nem inferem saldo após amortização. Orçamentos mensais usam competência; quinzenais usam vencimento/data. Transferências, contribuições e pagamentos de fatura não duplicam despesas.
