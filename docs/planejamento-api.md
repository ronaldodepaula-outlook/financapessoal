# Recorrências, empréstimos e planejamento — fases 6 a 8

Base: `http://localhost/financapessoal/backend/public/api`. Usar JWT em `Authorization: Bearer TOKEN`. IDs nos exemplos são ilustrativos; usar os retornados pelos cadastros do usuário. Dinheiro usa strings decimais. A interface PHP será entregue na Fase 13.

## Contas fixas, assinaturas e rendas

`fixed-expenses`, `subscriptions` e `income-schedules` oferecem GET/POST na coleção e GET/PUT/DELETE por ID. Listagem aceita search, active, page e per_page. DELETE desativa e arquiva a definição, preservando seus lançamentos.

Exemplo de `POST /fixed-expenses`:

```json
{"description":"Internet","amount":"99.90","due_day":10,"start_date":"2027-01-01","payment_method":"BOLETO","category_id":1,"account_id":1,"active":true}
```

Assinaturas usam name, billing_cycle (MENSAL/TRIMESTRAL/SEMESTRAL/ANUAL) e next_due_date, no lugar de description/due_day. A âncora interna preserva o dia original: 31/01 → último dia de fevereiro → 31/03. Alterar o ciclo exige informar o primeiro vencimento do novo ciclo. start_date/end_date limitam as previsões, inclusive nas pontas do período.

Despesas fora do crédito exigem conta; CREDITO exige cartão sem conta. Categoria deve ser principal e DESPESA, e a subcategoria pertencer a ela. Rendas exigem categoria principal RECEITA.

Definições ativas geram a primeira previsão disponível a partir do mês atual/início. `POST /forecasts/generate` recebe `{"year":2027,"month":1,"months":12}` e materializa até 24 meses. A tarefa automática gera o mês atual e o próximo. Em períodos já transcorridos sem execução, esse endpoint/comando permite preencher o intervalo explicitamente.

A identidade é definição + ano/mês de competência, protegida por índices únicos. Previsões já existentes, inclusive canceladas, não são recriadas. Repetir o gerador retorna created=0; referências arquivadas retornam warnings e impedem novas previsões daquela definição. Escritas usam transação e bloqueio por usuário. Alterar valor na definição afeta somente novas competências. Previsões existentes admitem status, notes e card_invoice_id; valor/data/competência são preservados. Para desfazer uma previsão, cancelar seu lançamento.

`income-schedules` inicia inativo. Ativar exige day, period, start_date, account_id e category_id. O dia deve pertencer ao período 01_15 ou 16_31; dia 31 é limitado ao último dia do mês. Exemplo de edição, depois de confirmar os dados reais:

```json
{"day":15,"period":"01_15","start_date":"2027-01-01","account_id":1,"category_id":2,"active":true}
```

O dia acima é apenas exemplo. Os dias reais das rendas de R$ 3.780 e R$ 2.700 continuam pendentes. O agendamento Windows e os comandos de instalação/remoção estão no [README do backend](../backend/README.md#recorrências-e-execução-automática).

## Empréstimos e consignado

`GET/POST /loans`, `GET/PUT/DELETE /loans/{id}`. Tipos: PESSOAL, CONSIGNADO, FINANCIAMENTO e OUTROS. Criação salva RASCUNHO com principal, número/valor das parcelas e taxas informadas. Não credita automaticamente o principal na conta: esse crédito, quando efetivamente ocorrido, é um lançamento de receita explícito.

`POST /loans/{id}/activate` confirma primeiro vencimento, categoria/subcategoria e tratamento do pagamento. O cronograma usa parcelas mensais de valor informado, até 600, mantendo o dia-base. Data final fornecida deve coincidir com primeiro vencimento + quantidade − 1 meses. Após ativação, apenas nome, instituição e observações são editáveis; ativar novamente sem alterações retorna o contrato existente.

| Modo | Condições | Efeito |
|---|---|---|
| CONTA | Conta ativa informada | A parcela paga reduz o saldo da conta |
| FOLHA | Consignado, nenhuma conta e renda líquida confirmada | Mantém histórico da despesa, mas não reduz novamente a renda disponível |

`GET/PUT /preferences` expõe/confirma income_is_net_of_payroll_loan. FOLHA exige true. A preferência não pode virar false com contrato ativo nesse modo. A marca cash_flow_effect é definida pelo backend e preservada no lançamento; o cliente não pode alterá-la.

Pagamentos: `GET/POST /loans/{id}/payments`.

```json
{"payment_type":"PARCELA","loan_installment_id":1,"amount":"1024.77","payment_date":"2027-01-15"}
```

| Tipo | Comportamento |
|---|---|
| PARCELA | Exige parcela pendente do contrato e valor integral; baixa a previsão existente, sem nova despesa |
| AMORTIZACAO | Registra o desembolso na conta informada; preserva cronograma e último saldo informado |
| QUITACAO | Registra o desembolso e cancela somente parcelas pendentes; contrato fica QUITADO |

AMORTIZACAO e QUITACAO não recebem loan_installment_id e exigem conta, mesmo em contrato FOLHA. O valor é o informado pelo usuário, sem desconto/juros calculados. Pagamento parcial de uma parcela não é suportado.

`DELETE /loan-payments/{id}` estorna o último pagamento ativo do contrato; pagamentos posteriores precisam ser estornados primeiro. Estornar quitação restaura apenas as parcelas que ela cancelou. Pagamentos/estornos preservam registros anteriores. Lançamentos vinculados a empréstimos não podem ser alterados diretamente em transactions. Cancelar contrato exige ausência de pagamentos ativos.

`GET/POST /loans/{id}/balances` mantém o histórico de saldo devedor informado. POST recebe outstanding_balance, reported_at (não futura) e notes opcional. A consulta do contrato separa:

- principal_amount: valor originalmente contratado;
- scheduled_payables: soma exata das parcelas ainda pendentes;
- outstanding_balance: último valor informado, ou null quando desconhecido; zero se quitado;
- balance_reported_at e balance_source: data e origem da informação;
- paid_installments, remaining_installments e schedule: progresso e histórico.

Amortização não atualiza automaticamente o saldo informado nem presume um novo prazo. Registrar outro snapshot quando houver valor confirmado pelo banco. Não há cálculo Price/SAC ou estimativa de quitação.

## Orçamento mensal e histórico

`GET/POST /budgets`, `GET/PUT /budgets/{id}`. O planejamento é único por ano/mês/categoria/subcategoria. Categoria precisa ser DESPESA. Não é permitido sobrepor um orçamento da categoria inteira aos de suas subcategorias no mesmo mês.

POST cria a primeira revisão. PUT recebe planned_amount e reason opcional; ano, mês e classificação não mudam. Valor zero é permitido. Revisões guardam valor anterior, novo valor, versão e usuário. Repetir o mesmo valor não cria revisão. Meses encerrados não podem ser alterados. O histórico não oferece exclusão física.

`POST /budgets/copy` com `{"source_year":2027,"target_year":2028}` copia todos os meses, preservando a origem e ignorando destinos existentes. `overwrite:true` atualiza destinos e cria revisões. Validações ocorrem em uma única transação; o ano de destino precisa ser diferente.

`GET /budgets/comparison?year=2027&month=1` usa a competência dos lançamentos:

- planned_amount: meta configurada;
- actual_amount: despesas PAGA;
- committed_amount: PAGA + PENDENTE, incluindo previsões e parcelas;
- remaining_amount: planejado − realizado;
- projected_remaining: planejado − comprometido por item;
- usage_percent: percentual realizado, null quando a meta é zero;
- unbudgeted_actual: despesas pagas sem orçamento correspondente.

Cancelados são excluídos. Transferências e pagamentos de fatura não são somados como despesas. Em cartão, o status da compra permanece independente do pagamento da fatura, conforme a Fase 4. Por isso, a previsão de compra PENDENTE aparece como comprometida; seu pagamento de fatura não muda o realizado da compra automaticamente.

## Visão quinzenal

`GET /budgets/fortnight?year=2028&month=2` divide o mês em 01–15 e 16–último dia. A referência é due_date; se ausente, transaction_date. Essa é uma visão de planejamento por vencimento, não o extrato bancário por data de liquidação.

```text
Renda esperada = receitas PAGA + PENDENTE
Despesa comprometida = despesas PAGA + PENDENTE com efeito na renda disponível
Saldo projetado / available_to_spend = renda esperada − despesa comprometida
Saldo realizado = receitas PAGA − despesas PAGA
```

A projeção pode ser negativa. Não inclui saldo inicial bancário, não transfere automaticamente sobras entre quinzenas e não divide metas mensais pela metade. Pagamentos de fatura e transferências não criam outra despesa. Deduções já abatidas da renda líquida ficam em payroll_already_deducted e não reduzem a projeção outra vez. O comparativo mensal mantém essas despesas para acompanhar a meta do consignado.

Receitas inativas e falta de confirmação da renda líquida geram avisos. Sem gerar/configurar as previsões de renda e despesas do período, a projeção estará incompleta. Esta entrega calcula os valores a partir dos lançamentos existentes; não estima gastos que não foram cadastrados.

## Planejamento inicial e metas

`POST /planning/initialize` prepara os parâmetros do prompt por usuário, também executado ao criar usuário pelo CLI. Fonte: `backend/config/initial-planning.json`; o JSON em docs registra os parâmetros originais. Nenhum valor pessoal está embutido nos serviços.

São sete metas mensais para cada mês de 2027 e 2028: 168 orçamentos. Valores existentes são preservados; meses encerrados, categorias indisponíveis e escopos já cobertos são ignorados. A inicialização também prepara duas rendas inativas e o consignado em rascunho, com chaves internas que impedem duplicá-los após renomear. Ao concluir, grava planning_initialized_at: novas chamadas não recriam categorias, metas ou receitas, inclusive após renomear/arquivar cadastros. Ajustes posteriores devem usar os módulos correspondentes; adicionar outros anos usa cópia de orçamento. Contas fixas não recebem valor inventado.

`goals` oferece CRUD com arquivamento. `GET/POST /goals/{id}/contributions` registra reservas manuais, e `DELETE /goal-contributions/{id}` as cancela preservando histórico. A contribuição não movimenta a conta nem cria despesa. Uma transferência paga pode ser vinculada; a soma das contribuições ativas vinculadas, mesmo em metas diferentes, não pode superar o valor transferido. Cancele contribuições vinculadas antes de alterar/cancelar a transferência.

O progresso soma initial_amount + contribuições não canceladas. Saldo inicial da meta não pode mudar depois de contribuições. target_reached informa alcance, progress_percent pode superar 100%, e a conclusão da meta é explícita. Não há divisão por zero: a meta de valor é positiva. Contribuições de metas arquivadas continuam consultáveis e canceláveis.

Todas as escritas são auditadas e protegidas por proprietário e FKs. Os [contratos OpenAPI](../backend/docs/openapi.json) detalham campos, enums, erros e paginação. [Pendências financeiras](decisoes-pendentes.md) e [relatório de validação](fases-06-a-08.md).
