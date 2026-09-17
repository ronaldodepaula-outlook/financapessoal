# Finança Pessoal — App Mobile (Android)

Versão mobile, resumida e objetiva, do sistema web de gestão financeira já existente neste repositório (`backend/` + `frontend/`). Foco: dashboard rápido, consulta de movimentações/cartões, e — o principal diferencial — **captura automática de notificações bancárias do Android**, com um fluxo de "lapidação" para o usuário completar os dados antes de virarem lançamentos reais na API já existente.

React Native 0.87 + TypeScript, projeto **bare** (não Expo) — necessário porque a captura de notificações depende de um módulo nativo Android (`NotificationListenerService`), que o workflow gerenciado do Expo não expõe sem ejetar.

## ⚠️ Sobre este ambiente de desenvolvimento

Este projeto foi criado e todo o código foi escrito, tipado (`tsc`) e testado (Jest) na máquina que hospeda este repositório — **mas essa máquina não tem Android SDK, JDK nem emulador instalados**. Isso significa que, até este ponto:

- ✅ TypeScript compila sem erros (`npx tsc --noEmit`).
- ✅ ESLint roda sem erros introduzidos por este projeto.
- ✅ Testes unitários (Jest) passam — parser de notificações, deduplicação, fila local, sincronização online/offline.
- ❌ **Ainda não foi possível compilar o Gradle, instalar em um emulador/aparelho real, nem gerar um APK** — isso exige Android Studio (SDK + JDK + emulador ou aparelho físico) em uma máquina com esse ferramental, que não está disponível aqui.

Ou seja: o código está pronto e é honesto sobre suas dependências reais da API, mas a validação "de ponta a ponta" descrita mais abaixo (permissão de notificação, captura real, sincronização em um aparelho de verdade) **precisa ser feita por quem tiver Android Studio configurado** — normalmente o próprio desenvolvedor, na sua máquina de trabalho. As instruções abaixo cobrem exatamente esse passo a passo.

## Stack

- React Native 0.87 (New Architecture / Bridgeless, template padrão do RN CLI)
- TypeScript (strict)
- React Navigation (bottom tabs + native stack)
- Zustand (estado global: auth, transações, notificações capturadas)
- Axios (client HTTP único, com refresh automático de token)
- React Hook Form + Zod (formulário de lapidação e login)
- AsyncStorage (fila local de capturas e preferências não sensíveis)
- expo-secure-store (Android Keystore — tokens de sessão; escolhido em vez de react-native-keychain porque também funciona dentro do Expo Go, ver seção 3)
- @react-native-community/netinfo (detecção de conectividade)
- Jest (testes unitários)
- `expo` (pacote, não o managed workflow) — instalado só para permitir `npx expo start`/Expo Go como alternativa de desenvolvimento mais rápida (ver seção 3, Opção B); o app continua bare, com `android/` nativo próprio.

Sem SQLite/WatermelonDB: o volume de dados local (fila de notificações capturadas) é pequeno o bastante para uma lista JSON em AsyncStorage — evita mais um módulo nativo para linkar sem necessidade real (ver `src/services/captureRepository.ts`).

## Arquitetura

```text
src/
├── api/            # 1 arquivo por recurso da API real (client.ts é o único axios instance)
├── components/      # Button, Card, Input, Loading, EmptyState, TransactionItem, NotificationItem, CategoryBadge, Select, Fab, TransactionForm, DonutChart, InsightsCard
├── screens/         # Login, Dashboard, Transactions, TransactionDetail, Notifications (Pending+Lapidacao), Cards, Categories, Settings (+MonitoredApps, NotificationAccess, Diagnostics)
├── navigation/       # AuthNavigator, AppNavigator, types.ts
├── store/           # authStore, transactionStore, notificationStore (zustand)
├── services/         # notificationService (ponte nativa), syncService, storageService, captureRepository
├── hooks/            # useAuth, useTransactions, useNotifications
├── types/            # espelham 1:1 os contratos reais da API (auth, transaction, category, card, dashboard, notification, api)
├── utils/            # currency, date, notificationParser (+ testes)
├── constants/        # config (API_BASE_URL — único lugar com o IP do servidor), colors, routes
└── theme/            # tokens de cor/espaçamento/fonte

android/app/src/main/java/com/mobileapp/notifications/
├── FinancialNotificationListenerService.kt   # NotificationListenerService real
├── NotificationCaptureModule.kt              # ponte nativa <-> JS (NativeModule clássico)
├── NotificationCapturePackage.kt             # ReactPackage
└── NotificationCaptureStore.kt               # SharedPreferences: apps monitorados + fila offline
```

## 1. Instalação

```bash
cd mobile
npm install
```

## 2. Configuração da API

Único lugar com o endereço do backend: `src/constants/config.ts`.

```ts
export const API_BASE_URL = 'http://192.168.1.9/financapessoal/backend/public/api';
```

Esse é o prefixo real confirmado nesta máquina (Laravel servido em `backend/public/`, dentro do `htdocs/financapessoal` do XAMPP). Se o IP/porta do servidor mudar, esse é o único arquivo a editar — nenhum outro módulo referencia o host diretamente.

**Importante (rede local):** o backend roda em HTTP puro (sem TLS). A partir do Android 9 (API 28), o sistema bloqueia tráfego "cleartext" por padrão. Isso já está tratado em `android/app/src/main/res/xml/network_security_config.xml`, que libera cleartext **somente** para `192.168.1.9` — qualquer outro host continua bloqueado. Se o servidor mudar de IP, edite esse XML também.

O aparelho/emulador Android precisa conseguir alcançar `192.168.1.9` na rede local (mesma Wi-Fi/rede, sem firewall bloqueando). Não é `localhost` do computador — é o IP real do servidor.

## 3. Execução

### Opção A — build nativo completo (requer Android Studio/SDK instalado)

```bash
npx react-native start          # Metro bundler
npx react-native run-android    # com um emulador rodando ou aparelho conectado via USB (depuração ativada)
```

Único caminho que dá acesso à captura de notificações de verdade (módulo nativo).

### Opção B — Metro via Expo CLI (`npx expo start`)

O pacote `expo` foi adicionado só para permitir usar `npx expo start` como uma alternativa ao `npx react-native start` — **o projeto continua sendo um app bare (não Expo managed)**, o `android/` nativo é o mesmo de sempre, ninguém rodou `expo prebuild`. Ao iniciar você vai ver um aviso:

```text
Warning: Root-level "expo" object found. Ignoring extra keys in Expo config: "name", "displayName"
```

Esse aviso é esperado e inofensivo — é só o Expo CLI avisando que `app.json` tem campos de fora do bloco `expo` (que o React Native CLI usa para `index.js`/nome do app nativo); não afeta nada.

Com `npx expo start` rodando, você pode escanear o QR code com o app **Expo Go** (da Play Store) no seu celular para testar rapidamente **a maior parte do app sem precisar de Android Studio nem de um build**: login, dashboard, movimentações, cartões, categorias, configurações e a tela de lapidação funcionam normalmente. **A única coisa que não funciona no Expo Go é a captura de notificações em si** — o módulo nativo (`NotificationCaptureModule`) não existe dentro do binário genérico do Expo Go, então todo o `src/services/notificationService.ts` degrada graciosamente (retorna `false`/lista vazia, não trava o app) quando `NativeModules.NotificationCaptureModule` não existe. Para testar a captura de verdade, é necessário um dos dois:

> **Nota histórica:** a primeira versão deste app usava `react-native-keychain` para guardar os tokens de sessão. Essa biblioteca é um módulo nativo de terceiros que o Expo Go não inclui — rodar login pelo Expo Go quebrava com `Cannot read property 'getGenericPasswordForOptions' of null`. Trocamos para `expo-secure-store` (módulo oficial do Expo, embutido no Expo Go e que também funciona em builds nativos normais) especificamente para resolver isso.

- Build local com Android Studio (Opção A acima), ou
- Um "development build" próprio via `npx expo run:android` (ainda exige Android SDK local) ou `eas build` (build na nuvem da Expo, não exige SDK local, mas exige conta Expo e configurar o `eas.json` — não configurado neste projeto ainda).

> **Nota histórica 2 — versões dos módulos nativos:** o Expo Go só funciona se os pacotes que ele já traz pré-compilados (`@react-native-async-storage/async-storage`, `react-native-gesture-handler`, `react-native-screens`, `react-native-safe-area-context`) estiverem na MESMA versão que o app JS está usando — senão dá erro tipo `Native module is null`. Rode `npx expo install --check` a qualquer momento para conferir, e `npx expo install --fix` para corrigir automaticamente (com cuidado: **não deixe ele rebaixar o pacote `react-native` em si**, isso quebra o template `@react-native/*` gerado pelo `npx react-native init`, que fica todo travado numa versão só — se isso acontecer, edite `package.json` manualmente voltando `react-native` para `0.87.1` e rode `npm install` de novo). Depois de mudar qualquer uma dessas versões, sempre reinicie o Metro com `--clear` (`npx expo start --offline --clear`) em vez de só recarregar — Fast Refresh não é suficiente para trocar módulos nativos, pode deixar o app num estado inconsistente e mascarar o problema real com erros confusos.

## 4. Autenticação e API

A API já existente (`backend/`) usa JWT com **access token curto (60 min por padrão) + refresh token opaco de longa duração (30 dias por padrão, rotativo)** — nada disso foi inventado; é o que já está implementado em `backend/app/Services/TokenService.php`. O app mobile só consome:

- `POST /auth/login` `{email,password}` → `{access_token, refresh_token, token_type, expires_in, refresh_expires_at, user}`
- `POST /auth/refresh` `{refresh_token}` → mesmo formato, **ambos os tokens são rotacionados** (o refresh token antigo para de funcionar)
- `GET /auth/me`, `POST /auth/logout`

`src/api/client.ts` injeta `Authorization: Bearer <access_token>` em toda chamada e, ao receber um 401, tenta renovar automaticamente via `/auth/refresh` uma única vez antes de desistir e encerrar a sessão local. Tokens ficam no Android Keystore via `expo-secure-store` (`src/services/storageService.ts`) — nunca em texto puro, nunca logados (ver `DEBUG`/`[API]` logs em `client.ts`, que só imprimem método+URL, nunca corpo/headers).

## 5. Lançamento manual de movimentações

Além da captura automática por notificação, o usuário pode cadastrar uma receita ou despesa a qualquer momento pelo botão flutuante (+) no canto inferior direito do Dashboard (`src/components/Fab`). Ele abre `NewTransactionScreen` (`src/screens/Transactions/NewTransaction.tsx`) como um modal, reaproveitando o mesmo formulário e as mesmas regras de negócio já usadas na lapidação de capturas — ambos compartilham `src/components/TransactionForm`, que valida (crédito exige cartão sem conta, demais formas exigem conta sem cartão; categoria principal do mesmo tipo; subcategoria pertencente à categoria) antes de chamar `POST /transactions`. Ao salvar, a lista de Movimentações é atualizada em segundo plano e o Dashboard recarrega os totais automaticamente ao voltar para a aba (via `useFocusEffect`).

## 6. Dashboard interativo — categorias, subcategorias e gráfico

O painel "Para onde vai o seu dinheiro" no Dashboard não é mais uma lista estática:

- **Categorias sempre em ordem decrescente de valor** — isso já vinha do backend (`ReportService::categoryDetail`, `backend/app/Services/ReportService.php`), não precisou mudar nada ali.
- **Tocar numa categoria** expande (efeito sanfona) a lista de subcategorias dela, com valor e barra de proporção sobre o total da categoria. Categorias sem nenhuma subcategoria lançada pulam direto para o próximo passo.
- **Tocar numa subcategoria** (ou numa categoria sem subcategorias) abre `FilteredTransactionsScreen` (`src/screens/Transactions/FilteredTransactions.tsx`) com os lançamentos daquele mês que pertencem a ela. A API não tem filtro por `subcategory_id` em `GET /transactions`, então a tela busca por `category_id` (até 100 lançamentos) e filtra por subcategoria no aparelho — inclusive o caso "Sem subcategoria" (lançamentos da categoria sem nenhuma subcategoria marcada), tratado à parte para não misturar com os das outras subcategorias.
- **Gráfico de rosca** (`src/components/DonutChart`, via `react-native-svg`) acima da lista, com as mesmas cores dos pontinhos de cada categoria — tocar numa fatia expande a mesma categoria que tocar na linha correspondente da lista.
- **Cartão "Insights financeiros"** (`src/components/InsightsCard` + `src/utils/insights.ts`) com 2 a 4 observações computadas a partir dos totais do mês (maior categoria de gasto, se as despesas já passaram a renda, quanto sobrou, uso do orçamento planejado).
- **Top 10 estabelecimentos** — usa o endpoint já existente `GET /reports/summary?group_by=merchant` (`src/api/reports.ts`), que a API já suportava mas o Dashboard não consumia. Mesma interatividade: tocar num estabelecimento abre `FilteredTransactionsScreen` filtrando por `merchant_id` (esse sim a API já filtra nativamente, sem precisar de filtro no aparelho). Estabelecimentos sem nenhum gasto no mês, ou lançamentos sem estabelecimento vinculado, não entram na lista.
- **Seletor de mês/ano** — setas "‹ Setembro 2026 ›" no topo do Dashboard (`src/utils/date.ts`: `formatMonthYear`/`shiftCompetence`, com testes em `src/utils/__tests__/date.test.ts`). Troca o período de tudo: totais, gráfico, categorias e Top 10 estabelecimentos — não existe mais um mês "fixo" no Dashboard.

  **Sobre "IA financeira":** isso **não é** um assistente conversacional nem chama nenhuma IA externa — são heurísticas simples calculadas localmente a partir dos dados que o Dashboard já carrega. Uma IA de verdade (um chat que responde perguntas sobre suas finanças) exigiria uma chave de API de um provedor de LLM e um endpoint próprio no backend para fazer essa chamada sem expor a chave — nenhum dos dois existe hoje neste projeto, e colocar uma chave de API direto no app mobile não seria seguro (qualquer pessoa consegue extrair de um APK). Documentando aqui como uma dependência real, não implementada, seguindo a mesma regra do resto do projeto de não inventar infraestrutura que não existe.

- **Paleta de cores diversificada** (`src/constants/colors.ts`, `categoricalColors`) — 10 cores distintas para o gráfico/categorias, além de tons extras (`blue`, `purple`, `amber`) usados no cartão de insights, para o app ficar visualmente mais rico sem perder a identidade visual do app web.

## 7. Fluxo de captura de notificações — passo a passo

1. Instale o app (`npx react-native run-android`) e faça login.
2. Vá em **Mais → Captura de notificações**.
3. Toque em **Ativar captura** — isso abre a tela de configurações do próprio Android (`Configurações → Apps → Acesso especial → Acesso a notificações`). **Não existe forma de conceder essa permissão programaticamente**; o app só leva o usuário até lá.
4. Marque "Finança Pessoal" na lista de apps com acesso a notificações.
5. Volte ao app e vá em **Mais → Aplicativos monitorados** — marque os apps de banco/carteira que você quer que sejam observados (ex.: seu app do banco). Por privacidade, **nenhum app é monitorado por padrão**; o serviço nativo descarta silenciosamente qualquer notificação de um pacote fora dessa lista, antes mesmo de tentar interpretar o conteúdo.
6. Realize uma compra de teste (ou peça para alguém te mandar um Pix) para gerar uma notificação real do app monitorado.
7. Se o app estiver aberto, a notificação aparece imediatamente em **Pendências** (via evento nativo ao vivo). Se o app estiver fechado, o serviço nativo guarda o evento localmente (`NotificationCaptureStore.kt`, SharedPreferences) e ele é recuperado na próxima vez que o app for aberto (`drainQueuedEvents`).
8. Em **Pendências**, toque em **Lapidar** na movimentação capturada.
9. Ajuste categoria, subcategoria, cartão/conta, forma de pagamento, descrição e observação (valor e data já vêm pré-preenchidos quando o parser conseguiu identificá-los).
10. Toque em **Salvar** — isso chama `POST /transactions` (endpoint já existente, real, documentado no OpenAPI do backend) e, se o aparelho estiver online, a movimentação vira um lançamento definitivo imediatamente. Se estiver offline, fica marcada como "Aguardando conexão" e é enviada automaticamente assim que a internet voltar (`src/services/syncService.ts`, ouvindo `NetInfo`).

## 8. Dependência de API ainda não implementada (documentada, não inventada)

Seguindo a instrução explícita de não inventar endpoints: o parser e a fila de "Pendências" funcionam **inteiramente no dispositivo** até a lapidação. Isso significa que, hoje, se o usuário desinstalar o app ou trocar de aparelho **antes** de lapidar uma captura, ela se perde — não existe backup em nuvem do estado "ainda não lapidado".

Se no futuro isso for um problema real, o endpoint que resolveria é algo como:

```
POST /api/captured-transactions
Body: {source: "notification", source_app, merchant, amount, occurred_at, card_last_digits, raw_payload, dedupe_key}
Resposta: {id, status: "PENDENTE_LAPIDACAO", ...}

PUT /api/captured-transactions/{id}/lapidar
Body: LapidacaoInput (mesmo formato hoje enviado direto para POST /transactions)
Resposta: a Transaction criada
```

Isso permitiria: (a) sincronizar capturas entre aparelhos, (b) sobreviver a uma desinstalação, (c) mover a deduplicação para o servidor. **Não foi implementado** porque não existe hoje no backend e o escopo desta tarefa foi explicitamente não alterar o backend sem necessidade nem inventar contratos. O app funciona corretamente sem isso — só não tem esse backup entre a captura e a lapidação.

## 9. Testes

```bash
npm test
```

Cobertura atual (42 testes, 5 suítes — todos passando):

- `src/utils/__tests__/currency.test.ts` — parsing de valores em formatos variados ("152,80", "1.250,99", "R$ 89,90", "152.80"), formatação BRL.
- `src/utils/__tests__/notificationParser.test.ts` — reconhecimento de notificações financeiras (com base nos exemplos literais do briefing: "Compra aprovada", "Compra no cartão", valores com/sem prefixo R$, com/sem separador de milhar), extração de estabelecimento/final de cartão, casos onde o parser corretamente devolve `null` (sem palavra-chave financeira, campos incertos), e estabilidade/unicidade da chave de deduplicação.
- `src/services/__tests__/captureRepository.test.ts` — a fila local rejeita uma segunda captura com a mesma `dedupeKey`, aceita chaves diferentes, filtra pendentes corretamente, preserva campos em atualizações de status.
- `src/services/__tests__/syncService.test.ts` — simulação online/offline: nada é enviado sem conexão; uma captura em fila é enviada quando a conexão volta; um erro de rede no meio do lote para o processamento (para não perder o restante); um erro de validação (não-rede) devolve o item para revisão com a mensagem da API.
- `src/utils/__tests__/date.test.ts` — navegação de competência do seletor de mês/ano do Dashboard (`shiftCompetence`), inclusive troca de ano para frente e para trás, saltos de vários meses e formatação "Mês Ano".

**Não coberto por testes automatizados** (exige Android Studio/aparelho real, indisponível neste ambiente): o módulo nativo Kotlin (`NotificationListenerService`, `NotificationCaptureModule`) não foi compilado nem testado em runtime — foi escrito seguindo os padrões documentados oficiais do React Native (NativeModule clássico + ReactPackage, compatível com a New Architecture via camada de interop), mas só um build real no Android Studio confirma que compila e funciona. As 21 telas/componentes de UI também não foram renderizadas em um dispositivo real — só validadas estaticamente (TypeScript + ESLint).

## 10. Verificação estática já realizada

```bash
npx tsc --noEmit     # 0 erros
npx eslint .          # ver relatório final da tarefa
npm test              # 42/42 testes passando
```

## 11. O que falta para "concluído" de ponta a ponta

Seguindo o critério de conclusão do briefing original, o que só pode ser confirmado por alguém com Android Studio (ou testado agora mesmo pelo Expo Go, no caso dos itens de UI):

- [ ] `npx react-native run-android` compila e instala sem erros de Gradle.
- [ ] Login real contra `http://192.168.1.9/financapessoal/backend/public/api` a partir de um aparelho/emulador na mesma rede.
- [ ] Conceder o acesso a notificações e confirmar que `isServiceEnabled()` reflete isso corretamente.
- [ ] Capturar uma notificação real de um app de banco monitorado e ver o registro aparecer em Pendências.
- [ ] Lapidar e confirmar que a movimentação aparece no Dashboard/Transações (tanto no app quanto no site).
- [ ] Testar o ciclo offline→online (modo avião ligado durante a lapidação, depois desligado).
- [ ] Testar o botão flutuante do Dashboard: abrir o modal, criar uma receita e uma despesa (inclusive no crédito, para exercitar a seleção de cartão) e confirmar que os totais do Dashboard atualizam ao fechar o modal.
- [ ] Testar a interatividade do Dashboard: tocar numa categoria com subcategorias (deve expandir), tocar numa categoria sem subcategorias (deve ir direto para a lista de lançamentos), tocar numa fatia do gráfico (deve expandir a mesma categoria da lista) e tocar em "Sem subcategoria" dentro de uma categoria expandida (deve mostrar só os lançamentos sem subcategoria, não todos da categoria).
- [ ] Testar o Top 10 estabelecimentos e o seletor de mês/ano: trocar de mês e confirmar que totais, gráfico, categorias e estabelecimentos mudam juntos; tocar num estabelecimento e confirmar que a lista de lançamentos é só dele.
- [ ] Gerar o APK debug (`cd android && ./gradlew assembleDebug`, ou `npx react-native build-android --mode=debug`) e instalar manualmente.

Este README é o guia para quem for rodar esses passos.

## 12. Documentação relacionada

- [README raiz](../README.md) — panorama dos três subsistemas (backend/frontend/mobile).
- [docs/guia-desenvolvedor.md](../docs/guia-desenvolvedor.md) — convenções técnicas compartilhadas entre os três subsistemas.
- [docs/guia-analista.md](../docs/guia-analista.md) — regras de negócio, incluindo o fluxo de captura/lapidação descrito na seção 7 deste documento.
- [docs/operacoes.md](../docs/operacoes.md) — runbook de troubleshooting (inclui os erros de Expo/Metro/npm já enfrentados neste projeto).
