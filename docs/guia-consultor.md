# Guia do consultor

Visão de negócio do sistema "Finança Pessoal" para quem precisa apresentá-lo, avaliá-lo ou orçar trabalho futuro sobre ele — sem exigir leitura de código. Para detalhes técnicos, veja [guia-desenvolvedor.md](guia-desenvolvedor.md); para regras de negócio, [guia-analista.md](guia-analista.md).

## O que é o sistema

Uma plataforma de controle financeiro pessoal com três frentes de acesso ao mesmo backend:

- **API (backend)** — o núcleo: contas, cartões, categorias, lançamentos, transferências, faturas, parcelamentos, contas fixas/assinaturas/rendas recorrentes, empréstimos (incluindo consignado/desconto em folha), orçamento mensal e quinzenal, metas de poupança, dashboard/relatórios e importação de extrato bancário (CSV/OFX).
- **Site (frontend web)** — interface completa em PHP/HTML/JS para usar tudo isso pelo navegador, com dashboard interativo, formulários guiados e exportação de relatórios.
- **App Android (mobile)** — versão mobile com um diferencial que o site não tem: **captura automática de notificações bancárias** (o app lê notificações de compra/Pix do próprio celular e sugere o lançamento, que o usuário confirma/completa antes de virar um registro real).

## Para quem serve

Pessoas físicas que quiseram ir além de uma planilha: querem separar cartão de crédito e conta corrente corretamente (saber que "gastar no crédito" não tira dinheiro da conta na hora), acompanhar parcelamentos e faturas sem duplicar contas, planejar por quinzena (comum em quem recebe salário dividido), simular orçamento mês a mês, guardar para metas específicas e, no celular, registrar gastos quase sem digitar.

## Estado atual (17/09/2026) — o que já funciona de ponta a ponta

| Frente | Situação | Observação |
|---|---|---|
| API (backend) | **Pronta e testada** | ~76 testes automatizados cobrindo autenticação, todos os módulos financeiros, importação e relatórios |
| Site (frontend) | **Pronto, em uso** | 21 telas; sem suíte de testes automatizada, validado manualmente e por revisão de código |
| App Android (mobile) | **Código pronto, validação de ponta a ponta pendente** | Testes automatizados (tipos, lint, unitários) passam; falta compilar num Android Studio real e testar em aparelho — não disponível na máquina onde foi desenvolvido |

Nenhum desses três itens usa dado fictício ("mock") em nenhuma camada — tudo que aparece na tela vem de uma chamada real à API.

## O que ainda não está pronto (limitações honestas)

- **O app mobile não foi instalado num celular real ainda.** O código passa em todas as verificações que não dependem de um ambiente Android completo (compilação de tipos, lint, testes unitários), mas o "último passo" — compilar o app de fato e testar a captura de notificação num aparelho — precisa de alguém com Android Studio configurado.
- **O frontend web não tem testes automatizados.** É validado manualmente e por revisão de código estruturada; funciona, mas uma mudança futura tem menos rede de segurança automática do que o backend.
- **Algumas decisões financeiras do usuário-piloto seguem em aberto** (datas exatas de um empréstimo consignado, se a renda informada já desconta a parcela em folha) — por design, o sistema não "assume" essas respostas: os registros relacionados ficam inativos/rascunho até serem confirmados.
- **O projeto ainda não está num repositório Git** e o pipeline de deploy automático existente (GitHub Actions) foi copiado de outro projeto e nunca adaptado — publicar automaticamente via esse pipeline hoje enviaria os arquivos para o lugar errado. Isso é uma tarefa de configuração, não um problema de arquitetura.
- **"IA financeira" no app mobile é honesta sobre o que é**: um conjunto de observações calculadas localmente a partir dos totais do mês (maior categoria de gasto, se as despesas já superaram a renda, etc.) — não é um assistente conversacional nem usa um provedor de IA externo. Adicionar isso exigiria uma chave de API de um provedor de LLM e um backend próprio para não expor essa chave num app distribuído — nenhum dos dois existe hoje.

## Estimativa de esforço para construir algo equivalente do zero

Estimativa aproximada, útil para orçar um projeto comparável — **não é um preço fechado**: ajuste as faixas de horas ao valor/hora praticado e ao nível de qualidade (testes, documentação) desejado antes de usar em uma proposta.

| Frente | Escopo | Faixa de esforço (estimativa) |
|---|---|---|
| Backend (API) | Modelagem de dados, autenticação JWT, ~15 módulos de negócio, testes automatizados, OpenAPI | 4–7 semanas de 1 desenvolvedor backend sênior |
| Frontend web | 21 telas, dashboard interativo com gráficos, importação de extrato, autenticação server-side | 3–5 semanas de 1 desenvolvedor full-stack/frontend |
| App mobile Android | Dashboard, consulta, formulários, captura de notificação nativa (módulo Kotlin), sincronização offline | 4–6 semanas de 1 desenvolvedor React Native/Android, **mais** tempo de homologação em aparelhos reais |
| Documentação + QA/homologação | O conjunto de documentos citado aqui, revisão adversarial, testes ponta a ponta com dados reais | 1–2 semanas |

Total aproximado: **um único desenvolvedor full-stack experiente cobrindo os três subsistemas, em torno de 3 a 4 meses corridos**; com uma equipe pequena dedicada (2–3 pessoas em paralelo), o calendário cai para 6–8 semanas, mas o custo total em horas-pessoa é parecido. Fatores que aumentam a estimativa: exigência de testes end-to-end automatizados no frontend/mobile (hoje inexistentes), suporte a mais de um usuário simultâneo/multi-tenant real, publicação na Play Store (revisão, assinatura de app, políticas de privacidade para acesso a notificações), ou suporte a iOS (o app mobile hoje é Android-only).

## Próximos investimentos recomendados, em ordem de valor por esforço

1. **Validar o app mobile num Android Studio real** — é o item que mais destrava valor de negócio (a captura de notificação é o principal diferencial do mobile) pelo menor esforço, já que o código está pronto.
2. **Resolver as decisões financeiras pendentes** do usuário-piloto para ativar consignado/rendas recorrentes de verdade — sem código novo, só decisão de negócio.
3. **Inicializar o repositório Git e corrigir o pipeline de deploy** — pré-requisito para qualquer processo de revisão de código em equipe ou deploy automatizado confiável.
4. **Testes automatizados de frontend** — reduz o risco de regressão à medida que mais pessoas mexem no código.
5. **Publicação na Play Store**, se o objetivo for uso por terceiros além do usuário-piloto atual.
