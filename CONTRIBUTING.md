# Contribuindo com o Finança Pessoal

Guia prático para configurar o ambiente, rodar os testes de cada subsistema e abrir uma alteração. Para arquitetura/regras de negócio, veja [docs/guia-desenvolvedor.md](docs/guia-desenvolvedor.md) e [docs/guia-analista.md](docs/guia-analista.md).

## Estado do controle de versão (leia antes de tudo)

Este projeto **ainda não é um repositório Git** — não existe pasta `.git`, embora `.gitignore` e `.github/workflows/` já estejam presentes (aparentemente preparados de antemão). Antes de qualquer fluxo de branch/PR fazer sentido, alguém precisa:

```bash
git init
git add .
git commit -m "Estado inicial do projeto"
git remote add origin <url-do-repositório>
git push -u origin main
```

Ao revisar o primeiro `git status`/`git add`, confira se nenhum segredo real está sendo versionado (`backend/.env`, credenciais) — os `.gitignore` de `backend/`, `frontend/` e a raiz já cobrem os arquivos `.env`, mas vale conferir manualmente antes do primeiro commit.

## Antes de abrir uma alteração

1. Rode a suíte de testes do subsistema que você vai alterar (comandos abaixo).
2. Se alterar uma rota/contrato da API, regenere o OpenAPI (`node backend/tools/generate-openapi.mjs`) e copie para `backend/public/docs/openapi.json`.
3. Para mudanças de UI ou de regra de negócio não triviais, prefira o padrão de revisão adversarial descrito em [docs/guia-desenvolvedor.md](docs/guia-desenvolvedor.md#revisão-adversarial-padrão-usado-neste-projeto) em vez de só rodar os testes automatizados.
4. Nunca declare uma funcionalidade mobile "testada" sem tê-la rodado de verdade num ambiente com Android SDK — nem todo ambiente de desenvolvimento tem isso disponível (ver limitação documentada em [mobile/README.md](mobile/README.md)).

## Comandos por subsistema

**Backend** (`backend/`):
```powershell
composer install
composer test                  # PHPUnit
php vendor/bin/pint --test     # estilo de código
composer validate --strict
composer audit
```

**Frontend** (`frontend/`):
```powershell
npm install
npm run build     # compila CSS (Tailwind) e copia assets estáticos
```
Sem suíte automatizada — valide manualmente as telas alteradas no navegador.

**Mobile** (`mobile/`):
```powershell
npm install
npx tsc --noEmit
npx eslint .
npm test          # Jest
```
`npm install` nesta rede (compartilhamento UNC/SMB) pode falhar com `EPERM`/`ENOTEMPTY` se houver um processo Metro/Expo ainda rodando — pare-o antes de tentar de novo (ver [docs/operacoes.md](docs/operacoes.md#mobile-expometro--problemas-reais-já-enfrentados-neste-projeto) para o runbook completo).

## Convenções de commit e branch

Não há uma convenção formal registrada ainda neste projeto. Sugestão mínima até o time decidir algo mais específico: branches `feature/<resumo-curto>` ou `fix/<resumo-curto>`, mensagens de commit no imperativo descrevendo o "porquê" da mudança, não só o "o quê".

## Checklist de PR sugerido

- [ ] Testes do(s) subsistema(s) alterado(s) passam localmente.
- [ ] `openapi.json` regenerado e copiado, se alguma rota mudou.
- [ ] Nenhum dado mock, credencial hardcoded ou endpoint inventado foi introduzido.
- [ ] Dinheiro tratado como string decimal (nunca `float`) em qualquer código novo do backend.
- [ ] Para mudanças no mobile: `tsc`, `eslint` e `jest` passam; qualquer item que dependa de Android Studio real está claramente marcado como não verificado, não como "OK".

## CI/CD existente — ação necessária antes de usar

`.github/workflows/main.yml` já existe e faz deploy por FTP a cada push em `main`, mas aponta para `server-dir: /public_html/clickresto-api/` — um caminho de **outro projeto** (`clickresto-api`), não deste (`financapessoal`). Isso indica boilerplate copiado e nunca adaptado. **Não ative esse workflow em produção sem antes**:

1. Confirmar com o dono do projeto se o destino de deploy é mesmo FTP (ou se deveria ser outra estratégia — ex.: deploy via SSH/rsync para o mesmo servidor XAMPP, ou nenhum deploy automático por enquanto).
2. Se for manter FTP, corrigir `server-dir` para o caminho real deste projeto no servidor e configurar os secrets (`ftp_server`, `ftp_username`, `ftp_password`) no repositório GitHub.
3. Considerar se o deploy deveria rodar os testes (`composer test`, `npx tsc --noEmit` etc.) como um job separado **antes** do deploy, já que o workflow atual não roda nenhum teste — hoje ele publicaria qualquer coisa que estiver na branch `main`, com erro ou sem.

## Licença

Este repositório não tem um arquivo `LICENSE` ainda. Essa é uma decisão de negócio do dono do projeto (licença proprietária, MIT, uso interno apenas, etc.) — não presuma nem adicione uma licença sem essa decisão explícita.
