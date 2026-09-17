# Frontend PHP puro

> **Nota (17/09/2026):** este README foi reescrito porque a versão anterior dizia "ainda não existe login ou dashboard funcional" e "implementação na Fase 13" — isso estava correto quando foi escrito (fase 1, projeto vazio), mas o frontend está **implementado e em uso** há vários ciclos de trabalho. Este documento descreve o que existe hoje, lido diretamente do código.

PHP 8.2+ puro (sem framework), HTML5, CSS (Tailwind v4 + CSS bespoke), JavaScript nativo (ES modules, sem bundler) e Chart.js. Consome exclusivamente a API do `../backend/` via HTTP/JSON — nunca acessa o MySQL/MariaDB diretamente e não implementa nenhuma regra de cálculo financeiro (isso é proibido por design, ver [docs/arquitetura.md](../docs/arquitetura.md)).

## Como está hospedado

```
http://192.168.1.9/financapessoal/          (raiz — .htaccess só libera index.php)
  → redireciona 302 para
http://192.168.1.9/financapessoal/frontend/public/   (webroot real deste app)
```

O `.htaccess` da raiz do projeto bloqueia qualquer acesso direto a `backend/`, `frontend/` (fora de `public/`), `docs/` e `mobile/` — só `index.php` na raiz responde, e ele só redireciona. Dentro de `frontend/public/.htaccess`, `RewriteRule` mapeia URLs limpas (`/dashboard`, `/transactions`, `/login`) para `index.php?page=<nome>`, mas os links internos do próprio app usam a forma explícita `?page=...`.

## Build (assets estáticos)

Não existe bundler nem transpilador — o navegador executa os arquivos de `public/assets/js/` diretamente como ES modules. `package.json` tem só devDependencies (Tailwind CLI, Chart.js, Swagger UI, fonte Manrope) usadas exclusivamente para gerar assets estáticos:

```bash
cd frontend
npm install
npm run build
```

`tools/build.mjs` faz quatro coisas:
1. Roda o Tailwind CLI (`styles/app.css` → `public/assets/css/app.css`, minificado). O CSS é majoritariamente escrito à mão (variáveis CSS, classes tipo `.sidebar`/`.stat-card`/`.badge`) — Tailwind v4 está importado (configuração CSS-first via `@import "tailwindcss"` + `@source`), mas é usado como complemento, não como o sistema de design principal.
2. Copia o bundle UMD do Chart.js para `public/assets/vendor/chart.umd.js` (carregado como `<script>` clássico, exposto como `window.Chart`).
3. Copia as fontes variáveis Manrope para `public/assets/fonts/`.
4. Copia os assets do Swagger UI + o `openapi.json` do backend para **`../backend/public/docs/`** — ou seja, este mesmo script também gera a página de documentação Swagger servida pelo backend.

Rode `npm run build` sempre que mudar `styles/app.css` ou depois de atualizar o `openapi.json` do backend.

## Arquitetura

```
public/index.php   → roteador de páginas (?page=x), sem framework
public/api.php     → gateway/proxy autenticado para a API Laravel
config/api-routes.php → allowlist de path+verbo que o gateway aceita repassar
routes/pages.php   → registro de páginas (título, grupo no menu, ícone, recurso REST, filtros padrão)
views/layout.php   → único shell HTML para todas as páginas autenticadas
views/auth/login.php → página de login (full-page, fora do shell)
components/        → header, sidebar, modal/toast, ícones (partials PHP reutilizados)
public/assets/js/  → toda a lógica client-side (ES modules)
```

**`public/index.php`** decide `$page` a partir de `?page=`, trata `login`/`logout` como casos especiais (POST), busca a definição da página em `routes/pages.php` (404 "não encontrada" se não existir) e sempre renderiza `views/layout.php` — o conteúdo real da página **não** é PHP: fica num `<div id="page-content">` vazio que o JavaScript preenche depois, buscando dados via `api.php`. As pastas `views/<recurso>/` (accounts, budgets, cards, categories, dashboard, etc.) contêm só `.gitkeep` — não há views PHP por recurso; tudo é montado em JS a partir de `schemas.js`/`crud.js`/`pages.js`.

**`public/api.php`** é o único ponto de contato entre o JS e a API Laravel:
1. Exige sessão autenticada (401 se não logado).
2. Valida `path` + método HTTP contra a allowlist de `config/api-routes.php` (regex por verbo) — requisições fora da allowlist recebem 404, mesmo que o backend aceitasse. Isso é uma camada de segurança adicional: mesmo se o JS do cliente fosse adulterado, o gateway não repassa nada fora da lista.
3. Exige CSRF válido em métodos não-GET (419 se inválido/expirado).
4. Repassa a chamada via `SessionAuth::call()` (injeta `Authorization: Bearer` no servidor — o JWT nunca chega ao navegador), tratando upload de arquivo (import CSV/OFX) e exportação CSV (`Content-Disposition: attachment`) como casos especiais.
5. Nunca deixa vazar exceção/stack trace — erro interno vira 503 genérico ("Verifique o Apache e o MySQL").

## Autenticação (sessão PHP, nunca o JWT no navegador)

- Login (`app/Auth/SessionAuth.php`) chama `POST /auth/login`, regenera o ID de sessão (proteção contra fixation), guarda `access_token`/`refresh_token`/`token_expires_at` **só na sessão PHP no servidor** e busca `/auth/me` para exibir o nome do usuário.
- Toda chamada via `api.php` passa por `SessionAuth::call()`: se o access token expira em menos de 20s, renova proativamente (`POST /auth/refresh`); se o backend ainda assim devolver 401, tenta renovar mais uma vez e desiste (limpa a sessão) se falhar de novo.
- CSRF: token gerado por sessão (`bin2hex(random_bytes(32))`), enviado pelo JS no header `X-CSRF-Token` (lido do `<script id="app-config">` embutido no HTML) e validado com `hash_equals()`.
- Cookie de sessão: `HttpOnly` + `SameSite=Lax` + `Secure` condicional, timeout de inatividade de 7200s (2h).
- Qualquer 401 recebido pelo `fetch()` do JS força um hard-redirect para `?page=login&expired=1`, descartando o estado da SPA.
- Logout sempre limpa a sessão local mesmo se a chamada `/auth/logout` ao backend falhar.

## Módulos JavaScript (`public/assets/js/`)

Carregados como ES modules nativos, sem bundler:

| Arquivo | Responsabilidade |
|---|---|
| `core.js` | Base: config embutido, `fetch()` autenticado (`api()`), formatação de moeda/data, labels/badges de enums, toasts, modal (`<dialog>`), cache de lookups (contas/cartões/categorias/estabelecimentos), renderizador genérico de campos de formulário, seletor de período mês/ano, paginação. |
| `app.js` | Bootstrap/dispatcher: decide qual `mountX()` monta `#page-content` conforme a página atual. |
| `crud.js` | Motor de CRUD genérico (modais de criação/edição, tabela de listagem, paginação, ações de detalhe) — usado por 13 dos ~19 recursos, dirigido por `schemas.js`. |
| `schemas.js` | Metadados de formulário/tabela por recurso (campos, tipos, colunas, lookups) — fonte única de verdade consumida por `crud.js`. |
| `pages.js` | Páginas sob medida: dashboard (com detalhamento de categoria/subcategoria e gráficos), orçamento mensal, visão quinzenal, relatórios com exportação CSV, configurações. |
| `imports.js` | Assistente de importação CSV/OFX: upload → mapeamento de colunas → revisão/edição de linhas → confirmação. |
| `categories.js` | Árvore de categorias/subcategorias (não usa `crud.js`/`schemas.js` porque a hierarquia e o bloqueio de tipo/pai não cabem no padrão genérico de lista). |
| `charts.js` | Wrapper fino sobre Chart.js: paleta de cores compartilhada, gerenciamento de canvases, os 5 gráficos do dashboard. |

## Páginas (`routes/pages.php`)

21 páginas registradas, agrupadas no menu lateral: Visão geral (dashboard), Lançamentos/Receitas/Despesas/Transferências, Contas bancárias/Cartões/Faturas, Orçamento mensal/Por quinzena/Contas fixas/Assinaturas/Receitas recorrentes/Metas, Empréstimos e consignado/Parcelamentos, Relatórios/Importar extrato, Configurações/Categorias/Estabelecimentos — mais `login` e `logout`, tratados fora do registro. A maioria usa o CRUD genérico (`resource` na definição da página); dashboard, orçamento, quinzena, relatórios e importação têm montagem própria em `pages.js`/`imports.js`.

## Variáveis de ambiente (`.env`, opcional)

| Variável | Padrão em `config/config.php` | Observação |
|---|---|---|
| `API_URL` | `http://localhost/financapessoal/backend/public/api` | Aponta para o backend local via XAMPP |
| `APP_NAME` | `Finança Pessoal` | |
| `SESSION_NAME` | `FINANCA_SESSION` | Nome do cookie |
| `idle_timeout` | `7200` (fixo no código, não configurável via `.env`) | |

**Atenção:** `.env.example` traz valores diferentes (`API_URL=http://127.0.0.1:8000/api`, `SESSION_NAME=financas_session`) pensados para `php artisan serve`. O ambiente real deste workspace usa XAMPP e os padrões hardcoded em `config/config.php` (primeira coluna acima), não os do `.env.example`. Ajuste conforme o seu ambiente ao copiar `.env.example` para `.env`.

## Testes

**Não há suíte automatizada para o frontend** (nem PHPUnit nem um test runner JS). A verificação até aqui foi manual (uso real das telas) e por revisão adversarial de código via agentes especializados — esse processo encontrou e corrigiu bugs reais na tela de categorias e na interatividade do dashboard (guardas de estado ausentes, mensagens de erro incorretas, elementos sem os atributos que o JS esperava). Para mudanças neste subsistema, pelo menos: verificar manualmente o fluxo alterado no navegador e, quando possível, repetir esse tipo de revisão adversarial antes de considerar concluído.

## Documentação relacionada

- [README raiz](../README.md) — panorama dos três subsistemas.
- [docs/guia-desenvolvedor.md](../docs/guia-desenvolvedor.md) — setup e convenções técnicas dos três subsistemas.
- [docs/guia-analista.md](../docs/guia-analista.md) — regras de negócio, incluindo as que este frontend precisa respeitar (ex.: crédito exige cartão sem conta).
- [docs/operacoes.md](../docs/operacoes.md) — deploy, `.htaccess`, variáveis de ambiente, troubleshooting.
- [backend/README.md](../backend/README.md) — a API consumida por este frontend.
