# Documentação operacional

Deploy, configuração de ambiente, backup, monitoramento e resolução de problemas dos três subsistemas neste workspace (XAMPP em `192.168.1.9`). Para arquitetura/código, veja [guia-desenvolvedor.md](guia-desenvolvedor.md); para regras de negócio, [guia-analista.md](guia-analista.md).

## Topologia de hospedagem

```
http://192.168.1.9/financapessoal/                       .htaccess raiz: só libera index.php
  └─ index.php  → redireciona 302 para frontend/public/

http://192.168.1.9/financapessoal/frontend/public/        webroot do frontend (index.php roteia por ?page=)
  └─ public/api.php  → gateway autenticado → chama a API Laravel via cURL (Authorization: Bearer)

http://192.168.1.9/financapessoal/backend/public/api      webroot da API Laravel (index.php do Laravel)
```

O `.htaccess` na raiz do projeto bloqueia acesso direto a qualquer coisa fora de `index.php` — isso inclui `backend/` (exceto seu próprio `public/`), `docs/`, `mobile/` e o conteúdo de `frontend/` fora de `public/`. **Somente `backend/public/` e `frontend/public/` devem ser expostos** pelo Apache; se algum dia migrar de host, replique essa regra (nunca aponte o `DocumentRoot` para a raiz do projeto).

O app **mobile** não passa por nenhum desses `.htaccess` — ele fala diretamente com `backend/public/api` pelo IP local (`192.168.1.9`), configurado em `mobile/src/constants/config.ts`.

## Variáveis de ambiente

**Backend** (`backend/.env`, nunca commitado): `DB_DATABASE=db_financeiro_pessoal`, `DB_HOST=localhost`, `DB_USERNAME=root`, `DB_PASSWORD=` (vazio neste ambiente local), `JWT_SECRET` (gerado por `php artisan finance:jwt-secret`, nunca exibido/reexibido), `JWT_TTL=60`, `JWT_REFRESH_TTL=43200` (30 dias em minutos), `APP_DEBUG=false` sempre em produção. Ver tabela completa em [backend/README.md](../backend/README.md#variáveis-de-ambiente).

**Frontend** (`frontend/.env`, opcional): `API_URL` deve apontar para `http://localhost/financapessoal/backend/public/api` neste ambiente XAMPP — **não** use os valores de `frontend/.env.example` (`127.0.0.1:8000`), que foram escritos para um `php artisan serve` que não é como este ambiente roda. Se não existir `.env`, `config/config.php` já usa o valor correto como padrão.

**Mobile** (`mobile/src/constants/config.ts`, não é `.env` — é um arquivo TypeScript): `API_BASE_URL = 'http://192.168.1.9/financapessoal/backend/public/api'`. Único lugar com o endereço do servidor; se o IP mudar, edite aqui **e** em `android/app/src/main/res/xml/network_security_config.xml` (que libera tráfego HTTP sem TLS — "cleartext" — só para esse IP específico; qualquer outro host continua bloqueado pelo Android 9+).

## Instalação / atualização (ordem recomendada)

1. **Backend:** `composer install` → (só em instalação nova) copiar `.env.example` para `.env`, `php artisan key:generate` → `php artisan finance:jwt-secret` → `php artisan migrate`. Nunca `migrate:fresh`/`rollback` neste banco com dados reais.
2. **Frontend:** `npm install` → `npm run build` (compila CSS Tailwind e copia Chart.js/fontes/Swagger UI).
3. **Mobile:** `npm install` dentro de `mobile/` (ver seção de troubleshooting abaixo — instalação nesta rede é instável). Sem passo de build para desenvolvimento — `npx expo start` ou `npx react-native start` servem o bundle diretamente.

## Usuário e dados iniciais

Não existe cadastro público nem usuário/senha padrão. Primeiro usuário:

```powershell
cd backend
php artisan finance:user seu-email@exemplo.com --name="Seu nome"
```

Cria o usuário (senha em prompt oculto, mínimo 12 caracteres com maiúscula/minúscula/número), categorias/subcategorias iniciais, orçamentos-base de 2027/2028, duas rendas recorrentes **inativas** e um consignado em **rascunho** — nenhum saldo, conta ou lançamento financeiro é criado. Um usuário já existente pode rodar `POST /planning/initialize` para obter o mesmo pacote sem perder ajustes já feitos.

## Backup e restauração (MySQL/MariaDB)

Não há rotina de backup automatizada configurada neste workspace. Procedimento manual recomendado:

```powershell
# Backup
mysqldump -u root db_financeiro_pessoal > backup_financeiro_YYYY-MM-DD.sql

# Restauração (cuidado: sobrescreve o banco de destino)
mysql -u root db_financeiro_pessoal < backup_financeiro_YYYY-MM-DD.sql
```

Faça backup **antes** de qualquer `php artisan migrate` em produção e antes de qualquer operação manual direta no banco. O `.env` (segredos JWT, credenciais de banco) não está no backup do banco — trate-o como um segredo à parte, nunca versionado (já está no `.gitignore`).

## Tarefa agendada de previsões (recorrências)

O comando `finance:generate-forecasts` materializa o próximo mês (e o atual) de contas fixas/assinaturas/rendas recorrentes ativas, de forma idempotente (não duplica se rodado de novo). Nesta máquina Windows, está instalado como tarefa agendada:

```powershell
Get-ScheduledTaskInfo -TaskName 'FinancaPessoal-Previsoes-F9C43F64A0D4'   # consultar última/próxima execução
Unregister-ScheduledTask -TaskName 'FinancaPessoal-Previsoes-F9C43F64A0D4' -Confirm:$false   # remover
```

Roda diariamente às 03:00 (horário de São Paulo), em processo oculto, com recuperação de horário perdido. Requer MySQL e PHP disponíveis nesse horário. Para instalar em outra máquina: `backend/tools/install-scheduler.ps1 -PhpPath 'C:\caminho\para\php.exe'` (idempotente, recusa recriar com uma ação diferente sob o mesmo nome).

## Monitoramento / verificação de saúde

- `GET /health` (sem autenticação) — verifica conectividade com o banco, retorna 503 se o banco estiver fora do ar. É o único endpoint público além de login/refresh.
- Logs do backend: `backend/storage/logs/` (canal `single`, nível `warning` por padrão). Erros internos só registram a **classe** da exceção — nunca SQL, payload ou tokens — então um log "limpo" não significa que não há um problema; combine com testes/smoke scripts para diagnóstico real.
- Sem APM/dashboard de monitoramento externo configurado. Para verificar rapidamente que a stack está de pé: `GET /health` no navegador + tentar logar no frontend.

## CI/CD — estado atual e o que falta decidir

Este projeto **ainda não é um repositório Git** (`git init` nunca foi executado, embora `.gitignore` e `.github/workflows/` já existam). O workflow existente, `.github/workflows/main.yml`, faz deploy por FTP a cada push em `main` — mas aponta para `server-dir: /public_html/clickresto-api/`, um nome de projeto diferente (`clickresto-api`, não `financapessoal`). Isso indica fortemente que é um boilerplate copiado de outro projeto e nunca adaptado. **Não foi alterado nem removido aqui** — é uma decisão do dono do projeto: ajustar o caminho/segredos para este projeto, ou substituir por outra estratégia de deploy. Ver [CONTRIBUTING.md](../CONTRIBUTING.md) para o checklist antes de habilitar esse workflow de verdade.

## Runbook de troubleshooting

### Backend / API

| Sintoma | Causa provável | Ação |
|---|---|---|
| 503 genérico em qualquer chamada | Backend fora do ar, ou exceção interna | Checar `GET /health`; checar `backend/storage/logs/laravel.log` pela classe da exceção |
| 401 mesmo com token recente | Sessão/refresh revogado (`AuthSession.version` mudou) ou usuário desativado | Fazer login de novo; checar `auth_sessions` no banco se persistir |
| 409 ao arquivar uma categoria | Categoria tem subcategorias ativas | Arquivar/inativar as subcategorias primeiro |
| `ApiInfrastructureTest` falhando após mudar uma rota | `openapi.json` saiu de sincronia | `node backend/tools/generate-openapi.mjs` e copiar para `backend/public/docs/openapi.json` |

### Frontend

| Sintoma | Causa provável | Ação |
|---|---|---|
| Tela fica em "carregando" para sempre | Erro de JS silencioso ou `api.php` retornando 404 | Abrir o console do navegador; checar se o path está na allowlist de `config/api-routes.php` |
| 419 ao salvar um formulário | Token CSRF expirado (sessão reiniciada) | Recarregar a página (novo token é embutido no HTML) |
| Redirecionado para login sem motivo aparente | 401 em qualquer chamada força logout no cliente | Verificar se o access token expirou e o refresh também falhou (sessão passou de 30 dias, ou usuário foi desativado) |

### Mobile (Expo/Metro) — problemas reais já enfrentados neste projeto

Esta seção documenta erros **reais** encontrados e resolvidos durante o desenvolvimento deste app, não hipóteses:

| Sintoma | Causa | Correção |
|---|---|---|
| `Cannot determine the project's Expo SDK version because the module 'expo' is not installed` | Pacote `expo` ausente | `npx expo install expo` (ou `npm install expo`) + garantir bloco `expo` em `app.json` |
| `SyntaxError: ... Export namespace should be first transformed by @babel/plugin-transform-export-namespace-from` | Babel sem suporte a export-namespace usado por uma dependência (ex.: zod) | Usar `babel-preset-expo` em `babel.config.js` (já inclui esse plugin) |
| `Invariant Violation: "main" has not been registered` | Expo Go sempre pede o componente registrado como `"main"`, mas só o nome nativo (`appName`) estava registrado | Registrar o componente sob os dois nomes em `index.js` |
| `Cannot read property 'getGenericPasswordForOptions' of null` | Módulo nativo de terceiros (`react-native-keychain`) não existe dentro do Expo Go | Trocar para `expo-secure-store` (módulo oficial, funciona em Expo Go e em build nativo) |
| `EPERM` / `ENOTEMPTY` durante `npm install` | Processo Metro/Expo anterior ainda tem arquivos travados (comum em compartilhamento de rede/SMB) | Parar o processo (Ctrl+C), checar o Gerenciador de Tarefas por um `node.exe` remanescente e encerrá-lo, tentar de novo; em último caso, apagar `node_modules` e reinstalar do zero |
| `Native module is null, cannot access legacy storage` (AsyncStorage ou outro módulo nativo) | Versão do pacote JS não bate com a versão que o Expo Go trouxe pré-compilada | `npx expo install --check` para diagnosticar, `npx expo install --fix` para corrigir — **mas confira o `package.json` depois**: se ele tentar rebaixar `react-native` em si, reverta manualmente essa linha (quebra a família `@react-native/*` do template) |
| `WARN The global process.env.EXPO_OS is not defined` | Preset Babel puro do RN CLI em vez de `babel-preset-expo` | Cosmético; some ao trocar para `babel-preset-expo` |
| `Unable to resolve "react-native-gesture-handler"` (ou outro pacote nativo) após uma instalação que falhou pela metade | `node_modules` ficou num estado inconsistente por uma instalação interrompida | Apagar `node_modules` por completo e rodar `npm install` limpo (demorado — ~15-20 min nesta rede, mas confiável) |

Regra geral para qualquer um desses: depois de trocar uma versão de módulo nativo, sempre reinicie o Metro com `--clear` (`npx expo start --clear`) em vez de confiar no Fast Refresh — ele não é suficiente para trocar módulos nativos e pode mascarar o problema real com um erro confuso subsequente.

**Limite conhecido desta máquina de desenvolvimento:** sem Android SDK/JDK/emulador instalados, não é possível compilar Gradle, gerar APK nem testar a captura de notificação nativa aqui — isso exige Android Studio configurado em outra máquina. Ver o checklist de validação pendente em [mobile/README.md](../mobile/README.md#11-o-que-falta-para-concluído-de-ponta-a-ponta).
