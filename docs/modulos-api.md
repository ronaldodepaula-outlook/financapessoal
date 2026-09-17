> **Nota (17/09/2026):** este documento cobre em detalhe as fases 2 a 8 (autenticação, cadastros, lançamentos, transferências, faturas, parcelamentos) e continua correto para esses módulos. Dashboard, relatórios, importação de extratos e os fluxos específicos do app mobile — implementados depois — estão em [guia-analista.md](guia-analista.md).

# Módulos da API — fases 2 a 8

Base XAMPP: `http://localhost/financapessoal/backend/public/api`. Base do servidor PHP: `http://127.0.0.1:8000/api`. Todos os endpoints financeiros exigem JWT. Os contratos de campos, parâmetros, exemplos e respostas estão em `backend/docs/openapi.json`.

Contas fixas, assinaturas, rendas recorrentes, empréstimos/consignado, orçamentos e metas estão detalhados no [guia de planejamento](planejamento-api.md), incluindo ativação, pagamentos, revisões e visão quinzenal.

## Autenticação e usuários

`POST /auth/login` recebe email/password. Retorna access_token JWT HS256, token_type, expires_in, refresh_token opaco, refresh_expires_at e usuário sem senha. A biblioteca firebase/php-jwt verifica assinatura e datas; o serviço valida emissor, audiência, usuário, sessão e versão. Referência da biblioteca: [documentação oficial](https://github.com/googleapis/php-jwt).

`GET /auth/me` identifica o usuário. `POST /auth/logout` revoga a sessão atual. `POST /auth/refresh` recebe refresh_token, troca os dois tokens e invalida os anteriores, inclusive para acesso. O acesso dura 60 minutos e a sessão renovável tem prazo absoluto de 30 dias por padrão, configuráveis. O prazo absoluto não é prorrogado na renovação. Desativar o usuário invalida imediatamente acesso e renovação.

Apenas o hash SHA-256 do refresh é persistido. JWT não contém senha ou informações financeiras. Respostas autenticadas usam no-store. Login é limitado a 5 tentativas/minuto por e-mail e 20 por IP; os demais endpoints a 60/minuto por IP. Token inválido/expirado retorna 401. Não há registro público de usuário; o comando `finance:user` cria o usuário com senha oculta, hash e categorias iniciais.

`auth_sessions.expires_at` usa DATETIME: no MariaDB local, o primeiro TIMESTAMP poderia receber atualização automática e expirar a sessão ao renovar. A migration corretiva impede esse comportamento e foi verificada no banco real.

## Cadastros

Contas, cartões, categorias e estabelecimentos oferecem `GET /recurso`, `POST /recurso`, `GET /recurso/{id}`, `PUT /recurso/{id}` e `DELETE /recurso/{id}`. Os recursos são `accounts`, `cards`, `categories` e `merchants`. PUT altera somente os campos enviados.

- Listas: search, status, page e per_page (1–100). Categorias também permitem type e parent_id. `data.items` contém os registros; `data.pagination` informa a paginação.
- Toda consulta/alteração é limitada ao proprietário. IDs de outro usuário retornam 404; referências estrangeiras recebem 422. user_id não pode ser imposto pelo cliente.
- DELETE arquiva o cadastro. Categorias com filhas não arquivadas precisam ter suas filhas tratadas antes, retornando 409 enquanto existirem. Histórico continua referenciando os cadastros arquivados.
- Categorias têm dois níveis: categoria e subcategoria, sempre do mesmo tipo. Tipo e pai são imutáveis após criação para preservar classificação histórica. Nome e status são editáveis.
- Estabelecimentos normalizam caixa, acentos e espaços. Nomes apenas parecidos não são fundidos automaticamente: “COMETA” e “MERCADO COMETA” permanecem distintos até futura regra de reconhecimento.
- Conta expõe current_balance calculado. Saldo inicial pode ser negativo, mas fica bloqueado após qualquer movimentação. Cartão expõe used_limit e available_limit; limite negativo indica excesso, sem impedir registro de uma compra já ocorrida.
- Cartões armazenam apenas identificação e quatro últimos dígitos; não há número completo ou CVV.

## Lançamentos e saldo

`transactions` oferece CRUD com cancelamento lógico. Filtros: busca, datas inicial/final, categoria, conta, cartão, estabelecimento, tipo, status e competência. Datas financeiras são `YYYY-MM-DD`; valores são strings decimais com ponto e até duas casas, como `"1024.77"`. A API aceita também inteiros e rejeita valores monetários float, negativos ou iguais a zero em movimentos.

Cada lançamento possui categoria principal do mesmo tipo (RECEITA/DESPESA) e subcategoria opcional pertencente a ela. PIX, dinheiro, débito, boleto e demais pagamentos fora do crédito exigem conta. Compra CREDITO exige cartão e não recebe account_id. Receitas não são lançadas em cartão. Competência é explícita, independente da data do lançamento.

PENDENTE representa previsão; PAGA confirma o movimento; CANCELADA retira seu efeito. paid_at é gerenciado pelo backend. Mudanças e cancelamentos são auditados. Em lançamentos de cartão, o status da compra e o pagamento da fatura são fatos distintos: pagar a fatura não cria outra despesa nem altera automaticamente o status da compra.

```text
Saldo de conta = saldo inicial
              + receitas PAGA fora de cartão
              - despesas PAGA fora de cartão
              + transferências recebidas PAGA
              - transferências enviadas PAGA
              - pagamentos de fatura PAGA
```

O saldo é derivado, sem coluna atualizada paralelamente. Somas usam BCMath com decimais e cursores para evitar floats. No volume atual, contas/cartões carregam seus movimentos para cálculo; agregação otimizada e medição com grande histórico ficam para a fase de relatórios/performance.

## Transferências

`transfers` oferece CRUD. Origem e destino precisam ser contas diferentes do mesmo usuário. Valor positivo, data, descrição, status e notas são configuráveis. Uma transferência paga reduz a origem e aumenta o destino, sem criar transaction de receita ou despesa. Cancelar restaura seu efeito nos dois saldos.

## Faturas e pagamentos

`GET/POST /card-invoices`, `GET /card-invoices/{id}`, `GET/POST /card-invoices/{id}/payments` e `DELETE /card-invoice-payments/{id}`.

Uma fatura é única por cartão/competência. Fechamento e vencimento são informados explicitamente, evitando supor a regra do banco para compras no dia do fechamento. Compras sem card_invoice_id ficam não vinculadas a fatura, mas já ocupam limite do cartão. Para vinculá-las, atualizar card_invoice_id no lançamento. A fatura precisa pertencer ao mesmo cartão.

Pagamentos podem ser parciais ou integrais e nunca superar o saldo em aberto. São registrados como pagamentos efetivos (PAGA). A fatura só fica PAGA quando seu saldo é zerado. Nenhuma transaction adicional é gerada. Compras de faturas com pagamentos ativos não podem ser alteradas/canceladas; primeiro cancelar o pagamento incorreto. Cancelar pagamento restaura saldo bancário e dívida da fatura.

## Parcelamentos

`GET/POST /installments`, `GET /installments/{id}` e `DELETE /installments/{id}`. O cadastro recebe descrição, total_amount, total_installments, start_date (primeiro vencimento), categoria/subcategoria e conta ou cartão conforme payment_method.

Geração atômica de 1 a 600 parcelas, cada uma com pelo menos um centavo. Exemplo: R$ 100 em três parcelas gera 33,34 + 33,33 + 33,33. O campo installment_amount do cabeçalho é o valor-base; o cronograma contém os valores exatos. O total original é preservado, sem despesa adicional no cabeçalho.

O cronograma preserva o dia original e limita o vencimento ao último dia de meses mais curtos: 31/01/2028 → 29/02/2028 → 31/03/2028. A competência corresponde ao mês de vencimento da parcela. Os lançamentos são criados PENDENTE. Parcelas em cartão podem ser vinculadas posteriormente às faturas correspondentes.

Baixa de parcela: `PUT /transactions/{id}` com `{"status":"PAGA"}`. Para parcelas geradas, somente status, notes e card_invoice_id são editáveis; alterar valores/datas isoladamente quebraria o total. O progresso e saldo em aberto são derivados dos lançamentos. Saldo em aberto inclui parcelas vencidas ainda pendentes.

DELETE no parcelamento cancela apenas parcelas pendentes e preserva as pagas. O cadastro é mantido. Não há cálculo de juros ou amortização: este módulo distribui o total informado.

## Segurança e limites da entrega

Validação por requests, fillable explícito, FKs por proprietário, escopo de consultas e middleware JWT protegem o acesso. Escritas financeiras usam transação e serializam alterações por usuário com lock. Auditoria registra ator, entidade, ação e campos alterados, sem valores de senha, tokens ou payload completo. Não é um histórico completo dos valores anteriores de cada lançamento; versionamento específico de orçamento segue na Fase 8.

As fases 6–15 continuam no roadmap. O frontend ainda é estrutura reservada. Nenhuma conta fixa, assinatura, renda ou consignado foi ativado automaticamente, e nenhum usuário padrão foi persistido. As decisões financeiras pendentes continuam em `decisoes-pendentes.md`.
