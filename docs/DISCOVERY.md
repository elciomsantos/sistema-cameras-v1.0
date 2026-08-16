# DISCOVERY — Levantamento do Estado Atual do Sistema

> **Projeto:** Sistema de Gerenciamento de Câmeras e Alarmes v1.0 
> **Fase:** 0 — Descoberta (ESPECIFICACAO-FINAL.md, seção 20)
> **Data do levantamento:** 2026-08-16
> **Autor:** Auditor (opencode)
> **Base:** Branch `main` (120 commits), árvore de trabalho com `docs/SETUP.md` e `opencode.json` modificados + `docs/ESPECIFICACAO-FINAL.md` não versionado

---

## 1. Objetivo

Compreender o estado atual do projeto antes de qualquer alteração, registrando evidências verificáveis para as fases subsequentes da auditoria (Baseline, Arquitetura, Backend, Frontend, Banco, Segurança, Performance, etc.).

---

## 2. Matriz do Ambiente

Levantado a partir de `composer.json`, `package.json`, `Dockerfile`, `docker-compose.yml`, `phpstan.neon`, `phpunit.xml` e CI.

| Componente          | Tecnologia             | Versão               | Status        | Fonte   |
| ------------------- | ---------------------- | -------------------- | ------------- | ------- |
| Linguagem (backend) | PHP                    | >= 8.4.1 (Docker: 8.4) | Suportada     | Oficial |
| Runtime host        | PHP CLI                | 8.4.24               | Atual         | `php -v` |
| Frontend            | TypeScript             | ^5.8.3 (strict)      | Suportada     | Oficial |
| Build               | Vite                   | ^8.0.16              | Atual         | Oficial |
| UI                  | Bootstrap              | ^5.3.8 (self-host)   | Atual         | Oficial |
| Charts              | Chart.js               | ^4.5.1               | Atual         | Oficial |
| Banco               | MySQL / MariaDB        | 8.0 / 10.5+          | Suportada     | Oficial |
| ORM/Driver          | PDO (`EMULATE_PREPARES=false`) | —             | —             | Oficial |
| Logging             | Monolog                | ^3.10                | Atual         | Oficial |
| Erros               | Sentry                 | ^4.29 (opcional, prod) | Inativo sem DSN | Oficial |
| Testes (PHP)        | PHPUnit                | ^10.0                | Atual         | Oficial |
| Testes (JS)         | Vitest                 | ^4.1.7               | Atual         | Oficial |
| Análise estática    | PHPStan                | ^1.10 (nível 5)      | 0 erros       | Oficial |
| Lint (PHP)          | PHP_CodeSniffer        | ^3.7                 | Não integrado ao CI | Oficial |
| Segurança de deps   | roave/security-advisories | dev-latest + `composer audit` | Ativo | Oficial |
| Docker              | PHP + Apache, multi-stage | php:8.4-apache / node:20-slim | Atual | Oficial |
| CI/CD               | GitHub Actions         | ci.yml + cd.yml      | Ativo         | Oficial |
| SO de referência    | Linux (WSL2 compatível), também Windows | — | — | — |

**Observações:**
- O CI (`ci.yml`) testa com PHP **8.3**, enquanto `composer.json` exige **>= 8.4.1** e o `Dockerfile` usa **php:8.4-apache**. Há **discrepância de versão** entre CI, composer e Docker (ver BACK/DEVOPS pendente de validação).
- Imagens em Docker rodam como usuário **não-root** (`USER www-data` no target prod).
- Sentry integrado mas **desabilitado por padrão** (sem `SENTRY_DSN`).

---

## 3. Histórico Git

| Item | Valor |
| ---- | ----- |
| Total de commits | 120 |
| Branch atual | `main` (única branch, tracking `origin/main`) |
| Tags / releases | Nenhuma tag encontrada |
| Último commit | `0838504` — "chore: atualizacoes diversas" (2026-07-18) |
| Período ativo | Julho 2026 (intenso) — alta frequência de commits 2026-07-09 a 2026-07-18 |
| Autores | `elciomsantos`, `Developer <dev@sistema-cameras.local>` |

**Análise de hotspots (evidência nos commits):**
- `config/DB/cftv_gml.sql` e `config/database.php` — vários commits de schema/trigger/perf.
- `api/v2/*` (cameras, alarmes, manutenções) — refatorações de performance e segurança.
- `inc/security.php`, `inc/single_session.php` — segurança (CSRF, sessão única).
- `docs/` — forte documentação de auditoria (Sprints 0-3 já executados).
- Migrations: 7 migrations aplicadas (thumbnails, particionamento, buffer de auditoria, índices, triggers anti-duplicidade locais).

**Contexto importante:** o projeto já passou por **uma auditoria anterior** (julho/2026) cujos artefatos estão em `docs/concluidos/`:
- `Auditoria/Relatorio_Auditoria_Final.md`
- `Auditoria/Avaliacao_Risco_Sprints0-3.md`
- `Backlog_Arquitetural.md`
- `Codigo_Morto_Encontrado.md` / `Codigo_Morto_Executado.md`
- `Relatorio_Performance.md`

Essa auditoria anterior concluiu Sprints 0–3 e zerou riscos críticos. Este levantamento registra o **estado atual pós-sprints**.

---

## 4. Estrutura de Diretórios (mapeamento)

| Diretório | Função | Observações |
| --------- | ------ | ----------- |
| `public/` | DocumentRoot; `index.php` roteador único, `.htaccess`, `sw.js` (PWA), uploads, assets buildados | Uploads com `.htaccess` protetivo |
| `api/` | Bootstrap da API (`bootstrap-api.php`) + 33 endpoints em `api/v2/` | Envelope padronizado `ApiResponse` |
| `auth/` | Login, logout, recuperação de senha | — |
| `accounts/` | Gestão de usuários (CRUD, admin) | — |
| `resources/` | Páginas de apresentação (cadastro/listagem/edição/relatórios/auditoria) | — |
| `inc/` | Includes compartilhados (security, session, header/footer/navbar, assets, pages, access_rules, check_access) | — |
| `src/` | Classes PHP (Api, Database, Services, Validation, Logging, Exceptions, ErrorHandler) + frontend TS/CSS/SCSS | Services: Alarme/Equipamento/Locais/Lookup/Notificacao/Auth |
| `config/` | `app.php`, `config.php`, `database.php`, `logger.php`, `sentry.php`, `DB/cftv_gml.sql` | Schema completo + seeds |
| `database/` | Runner de migrations (`migrate.php`, `runner.php`) + `migrations/` | 1 migration PHP restante |
| `tests/` | PHPUnit (Unit, Integration, Api) + Vitest (Js) | — |
| `scripts/` | Backup, copy-assets, optimize-database, migrations, opcache check, route collision check | — |
| `docker/` | Dockerfile.nginx, conf.d, dev-entrypoint | — |
| `docs/` | Documentação (SETUP, DEPLOY, SECURITY_ENV) + artefatos de auditoria (concluidos/) | ESPECIFICACAO-FINAL.md (novo, não versionado) |
| `storage/` | cep_cache | Rate limit agora em DB (`rate_limits`) |
| `src/assets/js/` | 16 entry points Vite + módulos page + utils + shared/core | — |
| `.github/workflows/` | `ci.yml` (PHP+Frontend+Docker) e `cd.yml` (push GHCR em tag v*) | — |

---

## 5. Inventário de Artefatos de Código

| Categoria | Quantidade (aproximada) |
| --------- | ----------------------- |
| Arquivos PHP versionados | ~120 |
| Endpoints API (`api/v2/*.php`) | 33 |
| Arquivos TypeScript (`src/assets/js/`) | ~52 (incl. 16 entries) |
| CSS/SCSS | ~14 CSS + 2 SCSS |
| Testes PHPUnit | 170 (com 28 erros pré-existentes da trait `ParsesInput` relatados em julho) |
| Testes Vitest | 88 |
| Páginas web (`resources/`) | ~30 |
| Services (src/Services) | 6 |

---

## 6. Arquitetura Observada (resumo)

- **Híbrida**: camada de apresentação procedural (`auth/`, `accounts/`, `resources/`, `inc/`) + camada de serviços OO (`src/Services/`).
- **Roteamento único**: `public/index.php` (web) e `api/bootstrap-api.php` (API v2).
- **Services** já refatorados parcialmente para `ServiceException` (COI parcial — Sprints 3.3/3.2): `AlarmeService`, `EquipamentoService` lançam exceções; controllers traduzem para HTTP.
- **`DatabaseInterface`** criada; `database` (singleton PDO) a implementa. `LocaisRepository` foi removido como código morto (decisão da auditoria anterior).
- **SQL ainda inline** em vários endpoints API (ex.: `api_cameras.php`, `api_alarmes.php`) — Repository Pattern não completo.
- **Migrations**: runner `database/migrate.php` ativo; schema evoluindo também via `config/DB/cftv_gml.sql`.
- **Auditoria de dados**: triggers + buffer `auditoria_buffer` + EVENT 10s + particionamento mensal.

---

## 7. Segurança — Postura Atual (evidência rápida)

Defesas já verificadas em código:
- CSRF token por sessão, consumo imediato (sem replay window) — `inc/security.php`.
- Sessão única por usuário + binding IP/User-Agent — `inc/single_session.php`.
- Rate limit: login (tentativas por IP/usuário) + API (`RateLimiter`: token_bucket GET / sliding_window mutation).
- 100% prepared statements (`EMULATE_PREPARES=false`), `database::insert` com whitelist de tabelas/colunas.
- Senhas Argon2id com auto-rehash de bcrypt legado; validação de política de senha.
- Upload: `finfo` MIME + magic-bytes + reprocessamento GD + nomes hasheados + limites.
- CSP nonce-based; headers de segurança (nosniff, frame-options, referrer, permissions, HSTS).
- Secrets fora do repo (`/etc/sistema-cameras/.env` / `C:\env\...`).

Pendências de segurança a validar na Fase 6 (não confirmadas nesta fase):
- CSRF nos endpoints de escrita via header `X-CSRF-Token` (confirmar cobertura 100%).
- CORS fallback same-origin quando `APP_ALLOWED_ORIGINS` vazio.
- Logs: confirmar ausência de dados sensíveis.
- Upload: risco de exaustão de disco/armazenamento (sem quota global).

---

## 8. Performance — Otimizações Aplicadas (evidência em commits/docs)

| Tier | Status |
| ---- | ------ |
| Tier 1 (quick wins) | ✅ Aplicado (Cache-Control, dashboard APCu, UPPER() removido, `.htaccess`, Vary/immutable) |
| Tier 2 (APCu/ETag) | ✅ Aplicado (api_cameras/alarmes/relatorios/manutencao, ETag anexos, vendor-chart chunk) |
| Tier 3 (índices/infra) | ✅ Aplicado (índices DB, modulepreload, Brotli, Font Awesome self-host, OPcache smoke test) |

Cache atual: APCu (dashboard 60s, lookups 300s, endpoints 30-60s), HTTP Cache-Control (14 GETs), ETag/304 anexos, Service Worker (cache-first assets), Brotli+gzip.

---

## 9. Infraestrutura / Docker / CI-CD

- **Dockerfile** multi-stage: `base` (php:8.4-apache + APCu + OPcache + locale pt_BR) → `dev` (bind mount + xdebug) → `build` (node:20 Vite) → `prod` (self-contained, `USER www-data`, composer `--no-dev`).
- **docker-compose.yml**: MySQL 8.0 + app (dev, `network_mode: host`, healthchecks). `.dockerignore`/`.env.docker` presentes.
- **CI (`ci.yml`)**: PHP 8.3 — syntax check, PHPStan, PHPUnit (com MySQL 8.0 service); Frontend — Vitest, tsc, Vite build; Docker — buildx.
- **CD (`cd.yml`)**: push de tag `v*` → imagem GHCR.
- **Healthcheck** no Dockerfile (curl na porta 8090/8080).

**Inconsistências observadas (validar):**
- CI usa PHP 8.3; composer exige PHP 8.4.1; Docker usa 8.4. (Potential DEVOPS finding.)
- `docker-compose.yml` usa `mysql:8.0`, mas `cftv_gml.sql` usa features MariaDB/MySQL compatíveis (verificar `<=>`/triggers/partição).
- CD só publica imagem; não há deploy automatizado (arquivo `docker-compose.prod.yml` presente).

---

## 10. Testes — Estado Atual

- **PHPUnit**: 170 testes relatados (julho), com 28 erros pré-existentes apontados como "Trait ParsesInput not found" em `EquipamentoServiceTest`/`AlarmeServiceTest` — **estado atual a revalidar** (pode estar corrigido após refatoração da trait).
- **Vitest**: 88 testes frontend.
- **PHPStan**: nível 5, **0 erros** (baseline removido em julho).
- **CI**: executa PHPUnit, PHPStan, Vitest, tsc e build.
- Smoke scripts: `tests/smoke_security.ps1`, `tests/smoke_manutencao.ps1`.

---

## 11. Documentação Existente

| Artefato | Status |
| -------- | ------ |
| `README.md` | Atualizado em julho (seções Migrations, Performance, deploy) |
| `docs/SETUP.md` | **Modificado (não commitado)** |
| `docs/DEPLOY.md` | Presente |
| `docs/SECURITY_ENV.md` | Presente |
| `docs/ESPECIFICACAO-FINAL.md` | Novo, **não versionado** (origem desta auditoria) |
| `docs/concluidos/Relatorio_Auditoria_Final.md` | Histórico da auditoria anterior |
| `docs/concluidos/Relatorio_Performance.md` | Histórico dos 3 tiers |
| `docs/concluidos/Backlog_Arquitetural.md` | Backlog de refatoração de longo prazo |
| `docs/concluidos/Codigo_Morto_*.md` | Inventário + execução de código morto |
| `docs/concluidos/Auditoria/*` | Relatório final + avaliação de risco Sprints 0-3 |

---

## 12. Código Morto / Dívida Técnica Conhecida (de auditoria anterior)

- Auditoria anterior removeu 15 itens de código morto (~690 linhas) em julho.
- Backlog arquitetural pendente: (1) COI completo de services, (2) Repository Pattern, (3) Migrations versionadas como fluxo oficial. Pré-requisitos apontados: Sentry/APM, cobertura >80%, quality gates.
- `phpstan.neon` hoje inclui `api`, `src`, `inc`, `resources`, `auth`, `accounts`, `config` (exclui `config/sentry.php`).

---

## 13. Variáveis de Ambiente (config/)

- Carregadas de arquivo externo (`/etc/sistema-cameras/.env` ou `C:\env\sistema-cameras\.env`), com exceção quando já definidas (Docker).
- Chaves: `CAMERAS_ENV`, `DB_HOST/NAME/USER/PASS`, `DB_SSL_CA`, `PROXY_TRUSTED_IPS`, `APP_ALLOWED_ORIGINS`, `APP_TIMEZONE`, `CAMERAS_SESSION_TIMEOUT`, `CAMERAS_SESSION_ABSOLUTE_TIMEOUT`, `ARGON2_*`, `BCRYPT_COST`, `SENTRY_DSN`, `CAMERAS_CSP_ALLOW_INLINE_STYLES`, `VITE_API_URL`.
- `config/DB/private/inserts-proprios.sql` e `seed_dev.sql` presentes (dados sensíveis/proprietários — não analisados nesta fase).

---

## 14. Serviços Externos

| Serviço | Uso | Evidência |
| ------- | --- | --------- |
| ViaCEP | Consulta de CEP (`api_cep_lookup.php`) | cache APCu+disco |
| Nominatim/Photon | Geocodificação (`api_geocode.php`) | tabela `geocode_cache` |
| Sentry (opcional) | Error tracking em produção | `config/sentry.php` |
| GHCR | Registro de imagem Docker | `cd.yml` |

**Limitação registrada:** serviços externos não foram exercitados (rede/credenciais) — classificação de disponibilidade fica como **Não auditado / Bloqueado** para Fase 6.

---

## 15. Limitações Desta Fase

- Ambiente de **produção inacessível** — não há evidência de instância ativa.
- Banco **não acessado** nesta fase (sem conexão): inventário baseado no schema SQL versionado, não no schema real de produção.
- Serviços externos (ViaCEP, geocode, Sentry) **não exercitados**.
- `composer.lock`/`package-lock.json` presentes mas versões resolvidas não auditadas em profundidade nesta fase (Fase 9 fará).
- Testes **não reexecutados** nesta fase (apenas inventariados) — Fase 1 registrará baseline executável.

---

## 16. Escopo Efetivamente Coberto na Fase 0

Código-fonte, estrutura, histórico Git, Docker/DevOps, CI/CD, dependências declaradas, documentação, configuração, arquitetura, endpoints API, postura de segurança declarada, otimizações de performance aplicadas, testes inventariados, código morto histórico.

---

## 17. Próximos Passos (Fase 1 — Inventário e Baseline)

1. Executar suíte de testes (PHPUnit + Vitest) para registrar baseline real.
2. Rodar PHPStan, `composer audit`, `composer validate --strict`, e lint.
3. Revalidar os "28 erros pré-existentes" da trait `ParsesInput`.
4. Registrar métricas de baseline (LOC, arquivos, complexidade).
5. Produzir `AUDIT_MATRIX.md` com escopo da auditoria atual.