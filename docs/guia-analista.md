# Guia do analista — regras de negócio

Este documento complementa [modulos-api.md](modulos-api.md) e [planejamento-api.md](planejamento-api.md) (que cobrem em detalhe autenticação, cadastros, lançamentos, transferências, faturas e parcelamentos — fases 2 a 8) com os módulos e fluxos que vieram depois: dashboard/relatórios, importação de extratos e os fluxos específicos do aplicativo mobile. Trate os três documentos como um conjunto: para autenticação/CRUD básico, vá em `modulos-api.md`; para orçamento/quinzena/empréstimos/metas, vá em `planejamento-api.md`; para o resto, este arquivo.

## Dashboard e relatórios

`GET /dashboard?year&month` retorna, para a competência informada: totais de receita/despesa/saldo/planejado, avisos (ex.: renda recorrente inativa), e agrupamentos por categoria (`by_category`) — cada grupo já vem com suas subcategorias aninhadas (`ReportService::categoryDetail`), então tanto o site quanto o app mobile mostram a mesma árvore categoria → subcategoria sem cálculo adicional no cliente. As categorias vêm sempre em ordem decrescente de valor.

`GET /reports/summary?group_by=...` aceita agrupar por categoria, subcategoria, conta, cartão, **estabelecimento** (`merchant`), lançamento fixo, mês ou ano — é o mesmo endpoint que alimenta tanto a tela de Relatórios do site quanto o "Top 10 estabelecimentos" do dashboard mobile (`group_by=merchant`). `GET /reports/transactions` devolve a lista de lançamentos por trás de um agrupamento (drill-down). `GET /reports/export` gera o mesmo relatório como CSV para download — células que começam com `=`, `+`, `-`, `@` ou tabulação/CR recebem um `'` na frente para bloquear injeção de fórmula ao abrir no Excel/Sheets (regra de segurança, não de negócio, mas relevante para quem valida a exportação).

Nenhum desses três endpoints considera lançamentos `CANCELADA` nem dados de outro usuário — a soma é sempre sobre `PAGA` (e, quando fizer sentido para o indicador, `PENDENTE` para "planejado").

## Importação de extratos (CSV/OFX)

Fluxo: `POST /imports` (upload) → `PUT /imports/{id}/mapping` (mapear colunas do arquivo para campos semânticos: data, descrição, valor, tipo) → revisão de linhas (`GET/PUT /imports/{id}/rows`, `PUT /import-rows/{id}`) → `POST /imports/{id}/confirm` (materializa as linhas selecionadas como `transactions` reais).

Regras de negócio que um analista precisa saber para validar esse módulo:

- **Extrato de cartão é sempre despesa.** Se uma linha de um arquivo associado a um cartão (`card_id`) vier classificada como receita, o sistema força para despesa — a leitura de negócio é que um cartão de crédito, neste sistema, só registra compras; estornos/créditos no cartão exigem revisão manual (ficam marcados com uma flag `review`, não são descartados nem forçados).
- **Linhas que parecem "pagamento de fatura" ou "transferência entre contas"** (por heurística de texto) chegam **desmarcadas** por padrão na revisão — o objetivo é evitar que o usuário registre como despesa um movimento que, na visão do sistema, já é tratado por outro módulo (pagamento de fatura não é uma despesa nova; transferência entre contas próprias não é receita/despesa).
- **Deduplicação por impressão digital** (`sha256` de data|valor|descrição|tipo|conta|cartão): tanto entre linhas do próprio arquivo quanto contra lançamentos manuais já existentes na mesma conta/cartão. Linhas duplicadas são marcadas `DUPLICADA` e ficam fora da confirmação — mas o usuário pode reabrir uma importação já confirmada para revisar/importar linhas que ficaram de fora da primeira vez.
- **Confirmação é tolerante a falha parcial**: uma linha inválida não derruba o lote inteiro — cada linha é validada e confirmada independentemente, e só as que falharem ficam sinalizadas. Isso é diferente do padrão "tudo ou nada" usado em outros módulos financeiros do sistema (ex.: gerar um parcelamento é atômico) — vale a pena checar esse comportamento especificamente ao testar o módulo de importação.
- Formato de número é detectado automaticamente (pt-BR `1.234,56` vs. en-US `1,234.56`) por amostragem do arquivo, nunca por conversão "otimista".

## O que o aplicativo mobile adiciona (fluxo que não existe no site)

O mobile consome a mesma API — não há nenhum endpoint exclusivo para ele — mas introduz um fluxo de **captura de notificações bancárias do Android** que vale entender do ponto de vista de negócio, mesmo sendo implementado inteiramente no aparelho:

1. O usuário concede acesso de notificações ao app e escolhe quais apps (banco, carteira) devem ser observados — **nenhum app é monitorado por padrão**, por privacidade.
2. Quando chega uma notificação de um app monitorado (ex.: "Compra aprovada... R$ 89,90"), o serviço nativo tenta extrair valor, estabelecimento e final do cartão por reconhecimento de texto (`notificationParser`), e a captura entra numa fila local como **"pendente de lapidação"**.
3. **Nada vira lançamento financeiro automaticamente.** O usuário sempre revisa/completa os dados (categoria, subcategoria, conta ou cartão, forma de pagamento) na tela de "lapidação" antes de qualquer coisa ser enviada para a API — quando confirma, é a mesma chamada `POST /transactions` usada em qualquer lançamento manual, sujeita às mesmas regras de categoria/conta/cartão descritas em `modulos-api.md`.
4. Se o aparelho estiver offline no momento da lapidação, o lançamento fica marcado como "aguardando conexão" e é enviado automaticamente quando a internet voltar.
5. **Lacuna de negócio conhecida e documentada:** enquanto uma captura está "pendente de lapidação" (passos 2–3), ela existe só no aparelho — se o app for desinstalado ou o aparelho trocado antes de lapidar, essa captura específica se perde. Isso é aceitável no escopo atual (o valor de negócio é a conveniência da captura, não um cofre de eventos brutos) e está documentado como uma dependência de API futura, não implementada, em [mobile/README.md](../mobile/README.md#8-dependência-de-api-ainda-não-implementada-documentada-não-inventada).

O restante da experiência mobile (dashboard, consulta de lançamentos/cartões/categorias, lançamento manual pelo botão flutuante) segue exatamente as mesmas regras de negócio do site, só com uma interface diferente — não há regra de negócio duplicada ou divergente entre os dois clientes.

## Decisões financeiras ainda pendentes

Continuam abertas (ver [decisoes-pendentes.md](decisoes-pendentes.md), sem mudança até esta data): datas exatas de vencimento/crédito do consignado e das duas rendas recorrentes quinzenais, se a renda informada já é líquida do desconto em folha, e o saldo devedor real do consignado. Enquanto isso, o consignado e as rendas recorrentes do usuário inicial permanecem em rascunho/inativos por design — nenhuma dessas suposições foi "resolvida silenciosamente" no código; ativar qualquer um desses registros exige confirmação explícita via `PUT /preferences` ou pela tela de Configurações.

## Índice de onde está cada regra

| Assunto | Documento |
|---|---|
| Autenticação, cadastros, lançamentos, transferências, faturas, parcelamentos | [modulos-api.md](modulos-api.md) |
| Contas fixas, assinaturas, rendas recorrentes, empréstimos/consignado, orçamentos, metas, quinzena | [planejamento-api.md](planejamento-api.md) |
| Dashboard, relatórios, exportação CSV, importação de extratos, fluxo mobile | Este documento |
| Decisões financeiras em aberto | [decisoes-pendentes.md](decisoes-pendentes.md) |
| Modelo de dados / dicionário aplicado | [modelo-de-dados.md](modelo-de-dados.md) / [dicionario-de-dados.md](dicionario-de-dados.md) |
