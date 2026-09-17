# Relatório das fases 6 a 8

Entrega sobre as fases 1–5 existentes, com API Laravel e MariaDB local. Nenhuma tabela anterior foi apagada. Frontend PHP puro permanece na Fase 13. As datas e o tratamento da renda do contrato pessoal continuam aguardando confirmação; o módulo funcional já valida esses parâmetros.

## Fase 6 — contas fixas e assinaturas

1. Desenvolvido: CRUD de recorrências, ciclos mensal/trimestral/semestral/anual, limites de vigência, vencimentos em meses curtos, previsões idempotentes, arquivamento e agendamento diário.
2. Criados: RecurrenceController/Request/Service/Repository, FinancialCalendar, GenerateForecasts, RecurrenceTest, tools/install-scheduler.ps1 e tools/run-scheduled-forecasts.ps1.
3. Alterados: Subscription, TransactionRequest/Service, routes/api.php, routes/console.php, gerador OpenAPI e documentação.
4. Banco: migration `2026_09_16_000001_add_planning_and_loan_links` adiciona billing_anchor_date a subscriptions. fixed_expenses e chaves únicas por definição/competência foram reutilizadas.
5. Endpoints: CRUD fixed-expenses e subscriptions; POST forecasts/generate. A infraestrutura também atende income-schedules na Fase 8.
6. Testes: reexecução sem duplicatas, cancelamento sem recriação, preservação de valores anteriores, dias 31/fevereiro bissexto, ciclos, desativação, referências arquivadas, limite de período e comando CLI.
7. Resultado: RecurrenceTest aprovado; geração também validada no MariaDB. Scheduler Laravel lista execução diária às 03:00; tarefa Windows instalada para o usuário atual.
8. Pendências: nenhuma de implementação deste módulo; o computador/usuário e MariaDB precisam estar disponíveis para a tarefa local. Cadastro das contas reais e telas ocorrerão com os dados confirmados e frontend.
9. Próxima fase: empréstimos e consignado, entregues abaixo.

## Fase 7 — empréstimos e consignado

1. Desenvolvido: contratos em rascunho, ativação de cronograma, parcelas pagas, amortização, quitação antecipada, saldo devedor informado e estorno. Dedução em folha exige renda líquida confirmada e não reduz novamente saldo disponível.
2. Criados: LoanController, LoanRequest, LoanPaymentRequest, LoanService e LoanTest.
3. Alterados: Loan, LoanPayment, Transaction, TransactionRequest/Service e rotas. Lançamentos de empréstimos ficam protegidos contra edição direta.
4. Banco: mesma migration adiciona referências de categoria/subcategoria/conta e modo ao contrato, loan_id/cash_flow_effect a transactions, transaction_id a loan_payments e settled_by_payment_id a loan_installments. FKs incluem proprietário.
5. Endpoints: GET/POST loans; GET/PUT/DELETE loans/{id}; POST loans/{id}/activate; GET/POST loans/{id}/payments; GET/POST loans/{id}/balances; DELETE loan-payments/{id}.
6. Testes: ativação idempotente, cronograma sem desvio do dia-base, baixa sem despesa duplicada, saldo/estorno, amortização sem alteração indevida, quitação/restauração, ordem de estornos, renda líquida, rejeição de parcela parcial, referências estrangeiras e ausência de gravação parcial em erro.
7. Resultado: LoanTest aprovado; pagamento/quitação/estorno e consignado em folha validados no MariaDB.
8. Pendências: confirmar primeiro vencimento do consignado inicial e se a renda informada já é líquida. Sem essas respostas, o contrato inicial permanece rascunho. O sistema registra amortizações informadas; não estima novo prazo/juros/saldo. Pagamento parcial de parcela não é suportado.
9. Próxima fase: orçamentos e planejamento, entregues abaixo.

## Fase 8 — orçamentos e metas

1. Desenvolvido: orçamento mensal por categoria/subcategoria, histórico de revisões, cópia entre anos, planejado/realizado/comprometido, visão quinzenal, receitas recorrentes, preferências de renda, parâmetros iniciais 2027/2028 e metas com contribuições.
2. Criados: BudgetController/Request/Service, PlanningController, InitialPlanningService, GoalController/Request/Service, FinancialTotals, config/initial-planning.php/json, PlanningTest e GoalTest. Documentação adicional: tools/openapi-planning.mjs, docs/planejamento-api.md e este relatório.
3. Alterados: CreateFinanceUser, GoalContribution, TransferService, modelos já indicados, rotas, OpenAPI, mysql-api-smoke.php, READMEs, roadmap, decisões, dicionário e inventário. Transferência vinculada a contribuições fica protegida contra alteração/cancelamento.
4. Banco: tabelas budgets, budget_revisions, income_schedules, user_preferences, financial_goals e goal_contributions reutilizadas. A primeira migration acrescenta template_key único por usuário a rendas/contratos e cancelled_at às contribuições. A segunda, `2026_09_16_000002_record_initial_planning_completion`, registra planning_initialized_at em user_preferences, evitando recriar metas/categorias depois de renomeá-las.
5. Endpoints: CRUD income-schedules e goals; GET/POST budgets; GET/PUT budgets/{id}; POST budgets/copy; GET budgets/comparison e budgets/fortnight; POST planning/initialize; GET/PUT preferences; GET/POST goals/{id}/contributions; DELETE goal-contributions/{id}.
6. Testes: inicialização/reexecução preservando ajustes, 168 metas iniciais, revisão com valor anterior, cópia sem sobrescrita implícita, proteção de meses encerrados e escopos sobrepostos, comparação e limites quinzenais em fevereiro, tratamento do consignado líquido, metas sem despesa e limite de alocação de transferências.
7. Resultado: PlanningTest e GoalTest aprovados; inicialização/revisão/cópia, visão quinzenal e contribuições validadas no MariaDB.
8. Pendências: configurar dias/início/contas das rendas e meta de contas fixas. As rendas iniciais estão inativas. A visão quinzenal é projeção dos lançamentos por vencimento, não saldo bancário nem distribuição automática de metas mensais. Frontend na Fase 13.
9. Próxima fase: **Fase 9 — dashboard, relatórios, filtros e exportação CSV**.

## Validação consolidada

- PHPUnit: **63 testes aprovados, 589 assertions**, incluindo regressões das fases anteriores e rollback/reaplicação de migrations somente em SQLite em memória.
- API: **90 operações em 47 caminhos**, documentadas em OpenAPI 3.0.3, versão 0.8.0, validada com swagger-parser.
- MariaDB local: **30 migrations aplicadas, 29 tabelas, 337 colunas e 26 models**. Migration nova aplicada por migrate, preservando banco existente.
- `mysql-smoke.php`: DECIMAL, unicidade com NULL e referências por proprietário aprovados.
- `mysql-api-smoke.php`: kernel HTTP Laravel usando MariaDB, com fluxos das fases 2–8 e transação revertida; nenhuma credencial ou dado temporário persistido.
- Laravel Pint aprovado; Composer validado; auditoria Composer sem vulnerabilidades reportadas.
- Tarefa Windows: `FinancaPessoal-Previsoes-F9C43F64A0D4`, diariamente às 03:00 no fuso do Windows, execução oculta e usuário atual conectado. Execução de teste concluída com LastTaskResult=0 e estado Ready. Configuração e remoção no README do backend.
- Apache/XAMPP: health retornou 200, loans/budgets sem autenticação retornaram 401 e .env permaneceu protegido com 403.
- Inicialização da conta real existente: 168 orçamentos (84 por ano), duas receitas inativas e um consignado RASCUNHO. Nenhum lançamento financeiro gerado; primeiro vencimento e renda líquida continuam pendentes. Não foi criado outro usuário nem alterada a senha.

Os [arquivos entregues](inventario-arquivos.md), o [dicionário aplicado](dicionario-de-dados.md) e os [contratos/regras](planejamento-api.md) complementam este relatório.
