# Guia do desenvolvedor

Visão técnica dos três subsistemas para quem vai programar neste projeto. Para regras de negócio (o "porquê" das validações), veja [guia-analista.md](guia-analista.md). Para deploy/operação, veja [operacoes.md](operacoes.md). Para o fluxo de contribuição/PR, veja [CONTRIBUTING.md](../CONTRIBUTING.md).

## Visão geral do sistema

```
mobile/  (React Native + Expo/Metro, Android)  ─┐
                                                  ├─→ HTTP/JSON + JWT Bearer ─→ backend/ (Laravel 12) ─→ MySQL/MariaDB
frontend/ (PHP puro, sessão server-side)        ─┘
```

- **`backend/`** é a única fonte de verdade para dados e regras financeiras. Nunca duplique cálculo de saldo/limite/regra de categoria em `frontend/` ou `mobile/` — sempre chame a API.
- **`frontend/`** nunca fala com o MySQL nem guarda o JWT no navegador; ele mantém o token na sessão PHP e faz o proxy autenticado via `public/api.php`.
- **`mobile/`** fala diretamente com a API (não existe gateway próprio como o `api.php` do frontend) e guarda os tokens no Android Keystore via `expo-secure-store`.
- Nenhum dos três subsistemas deve inventar endpoints que não existem no backend. Se uma funcionalidade precisar de um endpoint novo, documente a necessidade (como foi feito em [mobile/README.md](../mobile/README.md), seção "Dependência de API ainda não implementada") em vez de simular dados ou usar mocks.

## Backend (`backend/`)

- **Stack:** Laravel 12.69.2, PHP 8.2+, MySQL/MariaDB, `firebase/php-jwt` para JWT HS256, BCMath para dinheiro (nunca `float`).
- **Camadas:** Controllers recebem HTTP → Requests validam (`app/Http/Requests`) → Services concentram regra de negócio (`app/Services`) → Models/Enums representam o domínio → `CatalogRepository`/`RecurrenceRepository` reaproveitam consultas por proprietário. Não há camada de "Repository" para tudo — só onde havia consulta duplicada real (ver [docs/arquitetura.md](arquitetura.md) sobre por que não criar interfaces vazias por antecipação).
- **Autenticação:** access token JWT de 60 min, refresh token opaco (só o hash SHA-256 é persistido) com prazo absoluto de 30 dias, rotativo a cada uso. `AuthSession.version` permite invalidar sessões. Middleware `jwt` + `throttle:api` (60/min/IP) em quase todas as rotas; login tem throttle próprio (5/min/e-mail, 20/min/IP).
- **Dinheiro:** sempre string decimal (`"1024.77"`), validado por `App\Rules\MoneyAmount`, nunca `float`. Toda soma passa por `App\Helpers\Money` (bcadd/bcsub/bcdiv, escala 2).
- **Isolamento por usuário:** toda tabela financeira tem `user_id` e FKs compostas `(id, user_id)` — acessar um registro de outro usuário retorna 404, não 403 (evita vazar a existência do recurso).
- **Rotas:** ver `routes/api.php` — 58 caminhos / 103 operações (confirmado lendo o arquivo diretamente; a contagem exata está em `backend/docs/openapi.json`). Agrupadas por: auth, dashboard/relatórios, importação, planejamento, orçamentos, metas, empréstimos, recorrências (fixed-expenses/subscriptions/income-schedules, mesmo controller), parcelamentos, transações/transferências, faturas de cartão, catálogos (accounts/cards/categories/merchants).
- **OpenAPI:** `backend/docs/openapi.json`, gerado por `node tools/generate-openapi.mjs` (não exige pacotes npm). **Sempre regenere e copie para `backend/public/docs/openapi.json`** depois de mudar um contrato de rota — `ApiInfrastructureTest` falha se o spec sair de sincronia com `routes/api.php`.
- **Testes:** `composer test` (PHPUnit, força SQLite `:memory:`), `php vendor/bin/pint --test` (estilo), `composer validate --strict`, `composer audit`. ~76 métodos de teste atualmente — rode `composer test` para o número exato no seu ambiente. Scripts de smoke manuais contra o MySQL real: `php tests/mysql-smoke.php` e `php tests/mysql-api-smoke.php` (só `APP_ENV=local` + driver mysql, sempre em transação revertida).
- **Nunca rode `migrate:fresh` ou `rollback` no banco com dados reais** deste workspace — já há um usuário e dados de planejamento configurados.

### Convenções de código (backend)

- Fillable explícito em todos os models (nunca `$guarded = []`).
- Toda escrita financeira usa `DB::transaction` + `lockForUpdate()` na linha do `User` (mutex grosso por usuário) e, quando aplicável, na linha específica sendo alterada.
- `AuditService` registra toda mutação (`action`, `entity_type`, `entity_id`, lista de campos alterados) — nunca senha/token/payload completo.
- Exceções não tratadas: só a classe da exceção vai pro log (`Log::error('Falha interna da API.', ['exception' => $exception::class])`); nunca SQL, payload ou stack trace no log ou na resposta.

## Frontend (`frontend/`)

Ver [frontend/README.md](../frontend/README.md) para a arquitetura completa (gateway `api.php`, allowlist, módulos JS, páginas). Pontos que quem for mexer no código precisa saber de cara:

- Sem bundler — os arquivos de `public/assets/js/` rodam como ES modules nativos no navegador. `npm run build` só compila CSS (Tailwind) e copia assets estáticos (Chart.js, fontes, Swagger UI).
- Novo recurso CRUD genérico → adicione uma entrada em `schemas.js` (campos + colunas) e uma entrada em `routes/pages.php` (menu). Não crie uma view PHP por recurso — o padrão do projeto é montar tudo em `mountCrud()`.
- Página sob medida (não-CRUD) → adicione a função `mountX()` em `pages.js` (ou um arquivo próprio, como `imports.js`/`categories.js`) e registre em `app.js`'s dispatcher.
- Qualquer novo endpoint do backend que o frontend precise chamar tem que ser adicionado à allowlist em `config/api-routes.php` — o gateway rejeita (404) qualquer path/verbo fora dela, mesmo que o backend aceite.
- Sem suíte de testes automatizada. Verificação é manual + revisão adversarial (ver seção abaixo).

## Mobile (`mobile/`)

Ver [mobile/README.md](../mobile/README.md) para a documentação completa e atualizada (é o README mais detalhado dos três, escrito durante a construção do app). Resumo para quem for contribuir:

- **Stack:** React Native 0.87.1 (New Architecture/bridgeless), React 19.2.3, TypeScript 6 estrito, React Navigation, Zustand, Axios, React Hook Form + Zod, `expo`/`expo-secure-store` (só para permitir `npx expo start` como Metro — o projeto é **bare**, não Expo managed, porque a captura de notificação depende de um módulo nativo Android que o Expo Go não expõe).
- **Ambiente de desenvolvimento atual não tem Android SDK/emulador** — só `tsc`, ESLint e Jest foram validados aqui. Compilação Gradle/APK e teste em dispositivo real dependem de alguém com Android Studio configurado. Nunca declare esses itens como "OK" sem testá-los de verdade — siga o checklist em `mobile/README.md` seção 11.
- **Um único axios instance** (`src/api/client.ts`) com refresh automático de token; nunca crie um segundo cliente HTTP.
- **`src/constants/config.ts`** é o único lugar com o IP/URL do backend — nunca hardcode a URL em outro arquivo.
- **Sem SQLite** — a fila de capturas de notificação usa AsyncStorage (JSON simples), decisão deliberada para não adicionar um módulo nativo sem necessidade real de volume.
- **Testes:** `npm test` (Jest, 42 testes/5 suítes cobrindo utils e services), `npx tsc --noEmit`, `npx eslint .`. Rode os três antes de considerar qualquer mudança concluída.
- **`npm install` nesta rede/compartilhamento (UNC/SMB) é instável** — erros `EPERM`/`ENOTEMPTY` geralmente são arquivo travado por um processo Metro/Expo ainda rodando. Pare o processo (Ctrl+C, confira o Gerenciador de Tarefas por um `node.exe` remanescente) antes de tentar de novo; em último caso, apague `node_modules` e reinstale do zero.
- **`npx expo install --fix`** pode tentar rebaixar `react-native` — isso quebra a família `@react-native/*` fixada em 0.87.1 pelo template do RN CLI. Se acontecer, reverta só a linha `react-native` manualmente no `package.json` e rode `npm install` de novo.

## Revisão adversarial (padrão usado neste projeto)

Ao concluir uma funcionalidade não trivial, este projeto usa um padrão de revisão em múltiplos agentes antes de declarar "concluído":
1. Várias "lentes" de revisão (cada uma um agente que lê o código real) procuram bugs concretos.
2. Cada achado passa por um agente de verificação independente, que tenta refutá-lo.
3. Só o que sobrevive à verificação é corrigido.

Esse processo já encontrou e corrigiu bugs reais que passariam despercebidos numa revisão superficial (ex.: um botão liberado num estado inválido, um catch genérico escondendo qual das duas operações falhou, um atributo HTML ausente quebrando um clique). Para mudanças de UI/regra de negócio não triviais, prefira repetir esse padrão a declarar "pronto" só porque compilou.

## O que NÃO fazer neste projeto (lições já aprendidas)

- Não usar `float` para dinheiro em nenhum dos três subsistemas.
- Não inventar endpoint de API que não existe — documentar a lacuna, como em `mobile/README.md` seção 8.
- Não declarar algo "testado"/"funcionando" sem ter rodado o teste de verdade — nesta máquina de desenvolvimento específica não há Android SDK, então funcionalidades nativas do mobile só podem ser verificadas estaticamente aqui.
- Não usar dados mock/fake nem credenciais hardcoded em nenhum lugar do código.
- Não criar abstrações (interfaces, repositórios genéricos) por antecipação sem um segundo caso de uso real.
