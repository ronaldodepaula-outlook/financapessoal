# Relatório das fases 2 a 5

Entrega incremental sobre a Fase 1, preservando os arquivos do projeto e o banco informado. A interface PHP puro continua programada para a Fase 13; nesta entrega a integração aplicável é da API com MariaDB.

## Fase 2 — autenticação

1. Desenvolvido: login/logout/refresh/me, JWT HS256, sessões revogáveis, refresh rotativo com hash, verificação de usuário ativo, limite de tentativas, autorização por proprietário, auditoria e comandos para segredo JWT/primeiro usuário.
2. Criados: AuthController, AuthenticateJwt, LoginRequest, TokenService, AuditService, AuthSession, ApiResponse, config/jwt.php, comandos GenerateJwtSecret/CreateFinanceUser e AuthenticationTest/CreateUserCommandTest.
3. Alterados: composer.json/lock, bootstrap/app.php, AppServiceProvider, routes/api.php, routes/console.php, .env.example, .env local e bootstrap de testes. Segredo existente é preservado pelo comando; não é exibido.
4. Banco: migrations `2026_09_15_000001_create_auth_sessions_table` e `2026_09_15_000002_make_session_expiry_explicit`. A segunda corrige atualização automática de TIMESTAMP observada no MariaDB. Nenhuma tabela anterior foi apagada.
5. Endpoints: POST auth/login, POST auth/logout, POST auth/refresh, GET auth/me.
6. Testes: autenticação, expiração/assinatura/emissor inválidos, revogação, rotação/replay, usuário inativo, dados malformados e criação de usuário com senha hash.
7. Resultado: testes aprovados e fluxo também validado no MariaDB.
8. Pendências: tela de login e armazenamento do token na sessão PHP entram na Fase 13; nenhum usuário real foi criado automaticamente.
9. Próxima fase: cadastros, implementados abaixo.

## Fase 3 — cadastros

1. Desenvolvido: CRUD de contas, cartões, categorias/subcategorias e estabelecimentos; paginação, busca, validação, arquivamento e carga inicial de categorias por usuário.
2. Criados: CatalogController, CatalogRequest, CatalogRepository, CatalogService, InitialCatalogService, OwnedRecord, MoneyAmount, categories.php e CatalogTest.
3. Alterados: rotas, documentação e modelos de datas. O formato serializado das datas financeiras foi padronizado como YYYY-MM-DD.
4. Banco: tabelas da Fase 1 reutilizadas; nenhuma migration destrutiva ou carga fictícia.
5. Endpoints: cinco operações GET lista/POST/GET id/PUT/DELETE para cada recurso accounts, cards, categories e merchants (20 operações).
6. Testes: CRUD autenticado, isolamento por usuário, paginação, tentativas de impor user_id, dias/valores inválidos, hierarquia, unicidade e normalização de estabelecimentos; carga idempotente.
7. Resultado: testes aprovados. Contas também expõem saldo calculado, integrado na Fase 4.
8. Pendências: telas/modais na Fase 13. Reconhecimento aproximado de estabelecimentos permanece futuro.
9. Próxima fase: movimentos financeiros, implementados abaixo.

## Fase 4 — movimentos

1. Desenvolvido: receitas/despesas, transferências, faturas, pagamentos parciais/integral, cancelamentos e cálculo de saldo/limite sem duplicar despesas.
2. Criados: TransactionController/Request/Service, TransferController/Request/Service, InvoiceController/Request/PaymentRequest/Service, BalanceService, FinancialReferences, Money e FinancialMovementsTest.
3. Alterados: CatalogController para apresentar saldos/limites, CatalogService para bloquear alterações do saldo inicial após movimentos, rotas e OpenAPI.
4. Banco: tabelas existentes transactions, transfers, card_invoices e card_invoice_payments; escritas atômicas e auditoria.
5. Endpoints: CRUD de transactions/transfers (10 operações), listagem/criação/consulta de fatura, listagem/registro/cancelamento de pagamentos (6 operações).
6. Testes: efeito de receitas/despesas e edições no saldo, cancelamento repetido, transferência nas duas contas, compra/pagamento de cartão sem duplicidade, excesso de pagamento, fatura com pagamento bloqueando edição de compra e validações de referências/datas/valores.
7. Resultado: testes aprovados e cálculo de saldo validado também no MariaDB.
8. Pendências: dashboard/relatórios na Fase 9. Vínculo de compra à fatura é explícito; nenhuma regra de fechamento do emissor foi inventada.
9. Próxima fase: parcelamentos, implementados abaixo.

## Fase 5 — parcelamentos

1. Desenvolvido: geração atômica de parcelas, distribuição de centavos, datas sem ultrapassar mês, progresso, saldo em aberto, baixas e cancelamento somente das pendentes.
2. Criados: InstallmentController/Request/Service, InstallmentMathTest e InstallmentTest.
3. Alterados: TransactionService para proteger total/cronograma e atualizar status do parcelamento; rotas e OpenAPI.
4. Banco: installments e transactions existentes. Cada parcela possui número, vencimento, competência, valor e status; cabeçalho não cria despesa adicional.
5. Endpoints: GET/POST installments, GET/DELETE installments/{id}; baixa pela atualização de status do transaction da parcela.
6. Testes: divisão exata inclusive em valores altos, limite mínimo de um centavo, virada janeiro/fevereiro/março em ano bissexto, baixa, cancelamento preservando pagas, imutabilidade do cronograma, propriedade e ausência de gravação parcial em entradas inválidas.
7. Resultado: testes aprovados e fluxo de criação/baixa validado no MariaDB.
8. Pendências: tela de parcelamentos na Fase 13. Empréstimos e consignado têm módulo próprio na Fase 7, sem reutilizar este parcelamento como cálculo de financiamento.
9. Próxima fase: Fase 6 — contas fixas, assinaturas e geração idempotente de previsões.

## Verificação consolidada

- API: 45 operações em 23 caminhos, incluindo health; todas documentadas em OpenAPI 3.0.3, versão 0.5.0.
- Banco local: 28 migrations aplicadas, 29 tabelas e 324 colunas, documentadas no dicionário atualizado.
- PHPUnit: 49 testes aprovados e 349 assertions, incluindo regressões da Fase 1.
- Smoke no kernel HTTP Laravel com MariaDB: login → cadastros → parcelamento → baixa → saldo → refresh → logout, em transação revertida.
- XAMPP real: health 200, accounts sem autenticação 401 e tentativa de acessar .env 403.
- OpenAPI validado; Composer validado/auditado; código conferido com Laravel Pint.
- Conferência do banco após os testes: zero usuários, lançamentos e sessões persistidos pelos testes.

O [inventário atualizado](inventario-arquivos.md) relaciona os arquivos entregues. O [guia dos módulos](modulos-api.md) documenta contratos e regras. As dúvidas sobre consignado/renda líquida/dias de recebimento seguem registradas e não impediram estas fases.
