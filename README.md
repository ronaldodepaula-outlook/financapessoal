# Finança Pessoal

Sistema de controle financeiro pessoal com três subsistemas consumindo a mesma API: uma **API Laravel** (`backend/`), um **site em PHP puro** (`frontend/`) e um **app Android** em React Native/Expo (`mobile/`).

```
mobile/  (React Native + Expo/Metro) ─┐
                                       ├─→ HTTP/JSON + JWT Bearer ─→ backend/ (Laravel 12) ─→ MySQL/MariaDB
frontend/ (PHP puro, sessão server)  ─┘
```

> **Nota (17/09/2026):** este README e vários outros documentos do projeto ficaram desatualizados por um tempo — em particular, os READMEs de `backend/` e `frontend/` e o [roadmap](docs/roadmap.md) chegaram a afirmar que o frontend "ainda não existe" e que fases inteiras (dashboard, relatórios, importação) estavam "planejadas" quando já estavam implementadas. Tudo abaixo foi conferido diretamente no código nesta data.

## Estado atual

| Subsistema | Situação |
|---|---|
| **Backend** (API) | Fases 1–10 concluídas: autenticação JWT, cadastros, lançamentos, transferências, faturas, parcelamentos, contas fixas/assinaturas/rendas recorrentes, empréstimos/consignado, orçamentos, metas, dashboard/relatórios/exportação CSV e importação CSV/OFX. ~76 testes automatizados. |
| **Frontend** (site) | 21 telas implementadas e em uso, cobrindo todos os módulos do backend, com dashboard interativo (categorias/subcategorias navegáveis, gráficos). Sem suíte de testes automatizada — validado manualmente e por revisão de código. |
| **Mobile** (Android) | App completo: dashboard interativo (categorias/subcategorias, gráfico de rosca, Top 10 estabelecimentos, seletor de mês/ano, insights locais), consulta de lançamentos/cartões, lançamento manual, e captura automática de notificações bancárias com fluxo de revisão ("lapidação") antes de virar lançamento real. TypeScript/ESLint/Jest (42 testes) passam; **compilação Gradle e teste em aparelho Android real ainda não foram feitos** — não há Android SDK/emulador na máquina onde foi desenvolvido. |

Ver [docs/roadmap.md](docs/roadmap.md) para o detalhamento fase a fase.

## Documentação

| Documento | Para quem |
|---|---|
| [docs/guia-desenvolvedor.md](docs/guia-desenvolvedor.md) | Desenvolvedores — arquitetura, convenções, setup técnico dos três subsistemas |
| [docs/guia-analista.md](docs/guia-analista.md) | Analistas de negócio — regras de validação, fluxos, o que cada módulo garante |
| [docs/guia-consultor.md](docs/guia-consultor.md) | Consultores/stakeholders — visão de negócio, o que já funciona, estimativa de esforço |
| [docs/operacoes.md](docs/operacoes.md) | Operação — deploy, variáveis de ambiente, backup, monitoramento, runbook de problemas |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Quem for abrir uma alteração/PR — comandos de teste, checklist, estado do CI/CD |
| [backend/README.md](backend/README.md) | Instalação, execução e testes da API |
| [frontend/README.md](frontend/README.md) | Arquitetura e execução do site |
| [mobile/README.md](mobile/README.md) | Setup, execução e limitações do app Android (o mais detalhado dos três) |
| [docs/modulos-api.md](docs/modulos-api.md) + [docs/planejamento-api.md](docs/planejamento-api.md) | Regras de negócio detalhadas por módulo (histórico das fases 2–8, ainda válido) |
| [docs/arquitetura.md](docs/arquitetura.md) | Decisões de arquitetura registradas no dia 1 do projeto |
| [docs/modelo-de-dados.md](docs/modelo-de-dados.md) / [docs/dicionario-de-dados.md](docs/dicionario-de-dados.md) | Modelo de dados e dicionário aplicado ao banco |
| [docs/decisoes-pendentes.md](docs/decisoes-pendentes.md) | Decisões financeiras do usuário-piloto ainda em aberto |
| [docs/fase-01.md](docs/fase-01.md), [docs/fases-02-a-05.md](docs/fases-02-a-05.md), [docs/fases-06-a-08.md](docs/fases-06-a-08.md) | Relatórios de entrega das fases iniciais |
| [docs/inventario-arquivos.md](docs/inventario-arquivos.md) | Inventário de arquivos — **desatualizado** (cobre só as fases 1–8; não inclui `mobile/` nem os arquivos das fases 9–10). Mantido como registro histórico. |

## Primeiro acesso

O banco local `db_financeiro_pessoal` já está atualizado. Para criar um usuário, na pasta `backend`:

```powershell
php artisan finance:user seu-email@exemplo.com --name="Seu nome"
```

A senha é pedida em entrada oculta (mínimo 12 caracteres, com maiúscula, minúscula e número). O comando prepara categorias, orçamentos-base, duas rendas recorrentes inativas e um consignado em rascunho — nenhum saldo, conta ou lançamento financeiro é criado. Datas e o tratamento da renda do contrato inicial precisam ser confirmados (ver [decisões pendentes](docs/decisoes-pendentes.md)) antes de ativar essas previsões.

- API: `http://localhost/financapessoal/backend/public/api` — login em `POST /auth/login`, demais rotas com `Authorization: Bearer <access_token>`.
- Site: `http://192.168.1.9/financapessoal/` (redireciona para `frontend/public/`).
- App mobile: aponta para `http://192.168.1.9/financapessoal/backend/public/api` (mesma rede local) — ver [mobile/README.md](mobile/README.md) para instruções de execução via Expo.

A geração diária de previsões de recorrências está agendada no Windows às 03:00 — detalhes em [docs/operacoes.md](docs/operacoes.md#tarefa-agendada-de-previsões-recorrências).

## Separação obrigatória entre subsistemas

```text
frontend/  PHP puro → HTTP/JSON → backend/ Laravel → MySQL/MariaDB
mobile/    React Native/Expo → HTTP/JSON → backend/ Laravel → MySQL/MariaDB
docs/      Arquitetura, modelo, decisões, guias por audiência e relatórios de entrega
```

Nem o frontend nem o mobile acessam o banco diretamente ou implementam cálculo financeiro — isso é responsabilidade exclusiva do backend. Apenas os diretórios `public/` de `backend/` e `frontend/` devem ser publicados pelo servidor web (o `.htaccess` da raiz já impõe isso).

## Repositório e licença

Este projeto ainda não é um repositório Git nem tem uma licença definida — ver [CONTRIBUTING.md](CONTRIBUTING.md) para o que falta configurar antes de usar o fluxo de PR/CI-CD já presente em `.github/workflows/`.
