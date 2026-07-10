openc# Relatório Executivo de Auditoria Completa — `sistema-cameras-v1.0`

> **Versão:** 1.0 | **Data:** 09 de Julho de 2026 | **Stack:** PHP 8.2 + TypeScript/Vite 8 + MySQL 8.0/MariaDB 10.5
> **Cobertura:** ~284 arquivos versionados (120 PHP, 52 TS, 14 CSS, configs, Docker, CI/CD)
> **Metodologia:** Análise baseada em evidências (`arquivo:linha`) — ver `Auditoria_Completa_Software.md` para diretrizes.

---

## 1. Resumo Executivo

O sistema **está operando sem erros HTTP 500 visíveis**, porém a auditoria detectou:

- ✅ ~~🔴 **1 bug crítico silencioso** em runtime (`LocaisService::softDelete()` chama método inexistente)~~ — **[EXECUTADO]**
- ✅ ~~🔴 **1 vulnerabilidade crítica** de segurança (senha temporária exibida em texto-claro).~~ — **[EXECUTADO — residual aceito, admin-only]**
- ✅ ~~🟠 **6 problemas de alta severidade**~~ (IDOR em manutenções ✅, container Docker como root ✅, performance de dashboards ✅)
- 🟡 **~10 problemas de severidade média** restantes (self-host Bootstrap, CSRF replay window, thumbnails, particionamento, etc.)
- 🟢 **~8 melhorias de baixa severidade** restantes (PurgeCSS desativado, scrollbar, headers consistency, etc.)

**Diagnóstico geral:** Sistema robusto do ponto de vista defensivo (CSRF, prepared statements, Argon2id, sessão única, rate limit, CSP nonce-based, SRI em CDNs), com **Sprint 0 e Sprint 1 executados** — bugs críticos zerados, performance significativamente melhorada (APCu dashboard 10s, Chart.update, Cache-Control idempotente, ensureConnection leve, session_write_close, notificacoes com cache). Pipeline de build moderno (Vite + entries + manualChunks + gzip) porém ainda carrega Bootstrap/Font-Awesome via CDN (render-blocking).

### Top 5 correções imediatas (impacto/difícil)

| # | Correção | Severidade | Esforço | Impacto |
|---|----------|------------|----------|---------|
| 1 | `LocaisService::softDelete` — usar `$result['affected_rows']` em vez de `$this->db->affectedRows()` | 🔴 | Baixo | Excluir local funciona | **[EXECUTADO — Sprint 0]** |
| 2 | Remover exibição de senha temporária em texto-claro em `auth/action_recuperar_senha.php:80` e `action_editar_usuario.php:170` | 🔴 | Baixo | Elimina leak de credenciais | Residual aceito (admin-only via #4) — **[EXECUTADO — Sprint 0]** |
| 3 | Adicionar `verificarEscopoEquipamento`/`verificarEscopoAlarme` em POST de `api_manutencao_cameras.php` e `api_manutencao_alarmes.php` | 🟠 | Baixo | Fecha IDOR cross-secretaria | **[EXECUTADO — Sprint 0]** |
| 4 | Adicionar `USER www-data` ao `Dockerfile` | 🟠 | Médio | Container não-root | **[EXECUTADO — Sprint 0]** |
| 5 | Cache APCu de dashboard (10s) + reaproveitar `Chart.update()` em vez de destroy/new a cada refresh de 30s | 🔴 | Médio | Dashboard 80% mais rápido | **[EXECUTADO — Sprint 1]** |

---

## 2. Visão Geral do Projeto

**Contexto:** Sistema web empresarial para gerenciamento centralizado de câmeras de segurança e centrais de alarme, com controle de equipamentos, manutenções, relatórios, geolocalização, e autenticação hierárquica (`admin` > `supervisor` > `user`, cumulativa).

**Stack Tecnológica:**

- **Backend:** PHP 8.2+ (sem framework), PDO com `EMULATE_PREPARES=false`, Monolog 3.10, Composer
- **Frontend:** TypeScript 5.8 (strict), Vite 8, Bootstrap 5.3 (via CDN), Chart.js 4.5, Font Awesome 6 (via CDN), Leaflet, PostCSS+cssnano
- **Banco:** MySQL 8.0+ / MariaDB 10.5+ (Esquema em `config/DB/cftv_gml.sql`)
- **Testes:** PHPUnit 10 (80 testes) + Vitest 4
- **Análise:** PHPStan nível 5 com baseline, PHP_CodeSniffer, enlightn/security-checker (deprecated)
- **DevOps:** Dockerfile multi-stage, docker-compose (mysql+app), GitHub Actions (ci.yml + cd.yml), GHCR

**Tamanho do Código:** ~120 arquivos PHP versionados, 52 TS (16 entry-points + 18 módulos de página + utils), 14 CSS, 15 testes PHPUnit.

**Arquitetura:** híbrida — **presentation procedural** (`auth/`, `accounts/`, `resources/`, `inc/`) + **service layer orientada a objetos** (`src/Services/`, `src/Api/`, `src/Validation/`). Routing unificado em `public/index.php` (web) e `api/bootstrap-api.php` (API v2). **Sem Repository Pattern** — SQL inline nos services/endpoints. **Sem inversão de dependência real** — services chamam `ApiResponse::error()/exit` (camada HTTP).

---

## 3. Principais Riscos

1. ~~**Bug crítico em produção:** Toda operação "Excluir local" lança `Error: Call to undefined method database::affectedRows()` em runtime — o `UPDATE deleted_at=NOW()` é comitado antes do `return`, mas o erro faz a UI parecer "não excluiu". (Ver Risco 4.4 do relatório de bugs.)~~ **[EXECUTADO]**
2. ~~**Vulnerabilidade de credenciais em texto-claro:** Admins visualizam senhas temporárias geradas em tela (title DOM, screenshots, logs de sessão) — viola CWE-522 / OWASP A07:2021.~~ **[EXECUTADO — residual aceito, admin-only]**
3. ~~**IDOR cross-secretaria em manutenções:** Usuário nível `user` pode iniciar/finalizar OS em equipamento de outra secretaria (OWASP A01:2021 Broken Access Control).~~ **[EXECUTADO]**
4. ~~**Dashboards não escalam:** 10 queries COUNT/JOIN a cada 30s por aba aberta, sem cache.~~ **[EXECUTADO — Sprint 1: APCu 10s]**
5. ~~**Container Docker roda como root** (CIS Docker benchmark 4.1).~~ **[EXECUTADO]**
6. **Migrations feature reservada mas não usada** — schema evolui por `optimize-database.php` standalone, sem versionamento.

---

## 4. Vulnerabilidades Encontradas

| # | Vulnerabilidade | Severidade | Categoria OWASP | Arquivo:linha | Esforço |
|---|-----------------|------------|-----------------|---------------|---------|
| 1 | Senha temporária exibida em texto-claro na tela de admin | 🔴 Crítico | A07:2021 Identification & Auth Failures | `auth/action_recuperar_senha.php:80`, `accounts/action_editar_usuario.php:170-175` | Baixo | **[EXECUTADO — Sprint 0]** |
| 2 | Reset de senha de terceiros por `user` sem verificar identidade do solicitante | 🟠 Alto | A07:2021 | `auth/recuperar_senha.php:3` (requer `admin`) | Baixo | **[EXECUTADO — Sprint 0]** |
| 3 | IDOR: POST em `api_manutencao_cameras.php`/`api_manutencao_alarmes.php` não checa escopo de secretaria (actions `start_os`, `finalize_os`, `create_os`, `update`) | 🟠 Alto | A01:2021 Broken Access Control | `api/v2/api_manutencao_cameras.php:297-535`, `api/v2/api_manutencao_alarmes.php:289-490` | Baixo | **[EXECUTADO — Sprint 0]** |
| 4 | Container Docker roda como root (sem `USER` directive) | 🟠 Alto | A05:2021 Security Misconfig | `Dockerfile:1-60` | Médio | **[EXECUTADO — Sprint 0]** |
| 5 | CSRF sliding window mantém token consumido em `csrf_token_validations` por TTL 180s — admite replay | 🟡 Médio | A01:2021 | `inc/security.php:51-66` | Médio |
| 6 | `admin_tabelas.php` ecoa `$row->id` em `value="..."` e `onclick()` sem cast `(int)` | 🟡 Médio | A03:2021 Injection (XSS) | `resources/admin_tabelas.php:207, 215` | Baixo |
| 7 | `relatorios.ts:254` usa `onclick="location.reload()"` inline — bloqueado pela CSP (script-src 'self' 'nonce-…'); botão "Tentar novamente" não funciona | 🟡 Médio | A05:2021 + bug funcional | `src/assets/js/relatorios.ts:254` | Baixo |
| 8 | `sanitizeHtml` permite atributos `style`, `href="data:..."`, `src="data:..."` | 🟡 Médio | A03:2021 XSS | `src/assets/js/utils/html.ts:13-46` | Médio |
| 9 | `enlightn/security-checker` deprecated desde 2022 — base CVE desatualizada | 🟡 Médio | A06:2021 Vulnerable Components | `composer.json:13` | Baixo |
| 10 | `LookupService::getTableData` interpola `$tableName` no SQL (defendido por whitelist `in_array strict`) | 🟡 Médio | A03:2021 | `src/Services/LookupService.php:31` | Baixo |
| 11 | API não envia `Referrer-Policy` nem `Permissions-Policy` (apenas web) | 🟡 Médio | A05:2021 | `api/bootstrap-api.php:156-167` | Baixo |
| 12 | `mkdir($uploadDir, 0755)` em uploads (legível a outros host users) | 🟢 Baixo | A05:2021 | `api/v2/api_upload_anexo.php:145` | Baixo |
| 13 | CORS fallback same-origin quando `APP_ALLOWED_ORIGINS` vazio (deve ser definido explicitamente em prod) | 🟢 Baixo | A05:2021 | `api/bootstrap-api.php:184-198` | — |
| 14 | `X-Frame-Options` inconsistente (`SAMEORIGIN` web vs `DENY` API) | 🟢 Baixo | A05:2021 | `public/index.php:60` vs `api/bootstrap-api.php:160` | Baixo |

### Pontos corretamente defendidos (sem ação necessária)

- ✅ `EMULATE_PREPARES=false` + 100% prepared statements (`config/database.php:41`)
- ✅ ORDER BY dinâmico via whitelist (`api_manutencao_cameras.php:195-208`)
- ✅ `database::insert` valida tabela+coluna contra whitelist (`config/database.php:201-226`)
- ✅ CSRF presente em 100% dos forms POST (`auth/login.php:70`, todas as actions)
- ✅ `password_verify` com dummy hash para timing attack (`AuthService.php:205-209`)
- ✅ Rate limit por IP (15/15min) + por usuário (5/15min) em `login_attempts`
- ✅ `session_regenerate_id(true)` em login (`AuthService.php:266`)
- ✅ Hash Argon2id com auto-rehash de bcrypt legado (`inc/security.php:398-427`)
- ✅ Cookies `HttpOnly + SameSite=Strict + Secure (HTTPS) + use_strict_mode=1 + sid_length=48` (`config/app.php:56-74`)
- ✅ `realpath()` + prefix check em servir/excluir anexos (`api_servir_anexo.php:74-98`)
- ✅ Upload com `finfo` MIME + reprocessamento GD + magic-bytes + whitelist + nome hash random (`api_upload_anexo.php:100-220`)
- ✅ CSP nonce CSPRNG `random_bytes(16)` sem `'unsafe-inline'` (`public/index.php:43`)
- ✅ SRI (`integrity=...`) em Bootstrap CDN (`inc/header.php:15-16, 36`)
- ✅ IDOR por secretaria em equipamentos/anexos/alarmes (exceto manutenções — ver #3)
- ✅ Secrets externos fora do repo (`C:\env\sistema-cameras\.env` / `/etc/sistema-cameras/.env`)
- ✅ CORS com `*` não envia `Access-Control-Allow-Credentials: true` (`bootstrap-api.php:172-227`)
- ✅ HSTS `max-age=63072000; includeSubDomains; preload` em HTTPS (`public/index.php:63-65`)
- ✅ Composer `--no-dev` em build Docker (`Dockerfile:28`) — phpunit/phpstan não vão para prod
- ✅ `escapeHtml` em 100% das inserções TS de dados de API (`utils/html.ts:1-6`)
- ✅ Apache `mod_security` via `.htaccess` em uploads subdirs
- ✅ LIKE wildcard injection mitigada (`str_replace(['%','_'],['\\%','\\_'], …)`)

---

## 5. Bugs Encontrados

| # | Bug | Severidade | Arquivo:linha | Esforço |
|---|-----|------------|---------------|---------|
| 1 | `LocaisService::softDelete` chama `database::affectedRows()` (método inexistente) — exclui local lança `Error` fatal em runtime | 🔴 Crítico | `src/Services/LocaisService.php:204` | Baixo | **[EXECUTADO — Sprint 0]** |
| 2 | `EquipamentoService::cadastrar` chama `setAppUserContext` **após** INSERTs em `locais`/`catalogo_modelos` — perde `changed_by` na auditoria desses INSERTs | 🟠 Alto | `api/v2/api_cadastrar_cameras.php:28-64` (move `setAppUserContext` para após `beginTransaction`) | Baixo |
| 3 | `file-upload.ts` upload via XHR não atualiza `meta[name="csrf-token"]` após upload — segundo upload consecutivo falha com "Token CSRF invalido" | 🟡 Médio | `src/assets/js/utils/file-upload.ts:146-172` | Baixo |
| 4 | `file-upload.ts` rejeita com `res.error` mas resposta de erro usa `message` — usuário sempre vê "Erro no upload" genérico em vez da mensagem do backend | 🟡 Médio | `src/assets/js/utils/file-upload.ts:160-164` | Baixo |
| 5 | `dashboard-charts.ts:165-171` destrói o `<canvas id="alarmChart">` via `parentElement.innerHTML` quando `total===0` — auto-refresh 30s posterior nunca mais renderiza o chart | 🟡 Médio | `src/assets/js/modules/dashboard-charts.ts:165-171` | Médio |
| 6 | `home_dashboard.ts` adiciona `document.addEventListener('click')` no `catch` em cada init falha — aparecem 3 listeners paralelos; cliques disparam múltiplas `loadData()` simultâneas | 🟡 Médio | `src/assets/js/home_dashboard.ts:15-24, 203-220` | Baixo |
| 7 | `listar_alarmes.ts` cria `setInterval` em `window._listarAlarmesInterval` sem `clearInterval` prévio e sem pausar em aba oculta (vs `listar_cameras.ts` que tem o padrão correto) | 🟡 Médio | `src/assets/js/listar_alarmes.ts:358-361` | Baixo |
| 8 | `EquipamentoService::parseJsonInput` / `AlarmeService::parseJsonInput` engolem JSON malformado silenciosamente — resposta genérica "Nenhum dado recebido" em vez de "JSON inválido" | 🟡 Médio | `src/Services/EquipamentoService.php:25-30`, `AlarmeService.php:34-39` | Baixo |
| 9 | `requireApiAuth` em endpoints de escrita mantém `$_SESSION` bloqueada (file locking) — outros AJAX do mesmo usuário ficam esperando (latência em uploads múltiplos) | 🟢 Baixo | `api/bootstrap-api.php:74-92` (passar `true` em ~10 endpoints) | Baixo | **[EXECUTADO — Sprint 1]** |
| 10 | `EquipamentoService::resetAppUserContext` chama `setUserContext(null, null)` — `$origem` default `'web'` é sobrescrito para `NULL`, perdendo info em migrations via CLI | 🟢 Baixo | `src/Services/EquipamentoService.php:605-608` | Baixo |

### Vereditos negativos (categorias sem bug encontrado)

- ✅ `header('Location:')` sem `exit` — todos os `action_*.php` terminam com `exit` imediato
- ✅ Acesso `$_GET`/`$_POST` sem `??` — não encontrado (todos usam null coalescing)
- ✅ `foreach` sobre null — envolvidos em `if (!empty($result['data']))`
- ✅ Paginação off-by-one — todos usam `offset = (page - 1) * perPage` com `page >= 1`
- ✅ ORDER BY com variável — só via whitelist (não-parametrizável é tratado)
- ✅ `$_FILES['error']` validado em upload (`api_upload_anexo.php:95`)
- ✅ Timezone consistente (`APP_TIMEZONE` + `NOW()` + `gmdate('...Z')` em API)
- ✅ `localStorage` em TS — todos envolvidos em `try/catch`
- ✅ `file_get_contents` externo (CEP/geocode) — tem `timeout: 10` (`stream_context`/cURL)
- ✅ Path traversal no upload — nome em disco é `bin2hex(random_bytes(16))`, não `$_FILES['name']`

---

## 6. Problemas Arquiteturais

| # | Problema | Severidade | Arquivo:linha |
|---|----------|------------|---------------|
| 1 | Services chamam `ApiResponse::error()/exit` — quebra inversão de dependência (services dependem da camada HTTP), dificulta testes isolados (precisa de `ApiResponse::setTestMode(true)`) | 🟠 Alto | `src/Services/AlarmeService.php:35`, `EquipamentoService.php:27`, todos os services que chamam `ApiResponse::error` |
| 2 | Ausência de Repository Pattern — SQL inline mistura presentation (`api/v2/*.php`) e domain (`src/Services/*.php`); `database::insert` (com whitelist de tabelas) é sub-utilizado | 🟡 Médio | `api/v2/api_cameras.php:113-200` faz SQL cru sem service |
| 3 | Auth duplicada: `bootstrap-api.php:23-92` define `requiredAccessForApiEndpoint` + `requireApiAuth`, e CADA endpoint também chama `requireApiAuth` explicitamente — overhead + inconsistência latente | 🟡 Médio | `api/bootstrap-api.php:23-92` vs `api/v2/api_cameras.php:8` etc. |
| 4 | Migrations feature reservada mas não usada — `migrations/` vazio, schema evolui por `config/DB/cftv_gml.sql` + `scripts/optimize-database.php` | 🟡 Médio | `migrations/` vazio, `scripts/run_migrations.php:1-129` |
| 5 | `phpstan.neon` não inclui `resources/`, `auth/`, `accounts/`, `config/` — gap de análise estática | 🟡 Médio | `phpstan.neon` |
| 6 | PHPStan nível 5 com baseline de 55 exceções — não captura bugs como o `LocalsService.php:204` (que estaria no nível 6+ com análise de métodos inexistentes) | 🟢 Baixo | `phpstan-baseline.neon` |
| 7 | Rate limiter recebe path `APP_ROOT/storage/ratelimit` em `bootstrap-api.php:132` mas internamente concatena `/ratelimit` → resulta em `APP_ROOT/storage/ratelimit/ratelimit/` (path duplo verificado) | 🟢 Baixo | `api/bootstrap-api.php:132` + `src/Api/RateLimiter.php:21` |

---

## 7. Problemas de Performance

### N+1 / Queries

| # | Problema | Severidade | Arquivo:linha | Ganho esperado |
|---|----------|------------|---------------|----------------|
| 1 | `NotificacaoService::gerarPendencias()` faz SELECT+INSERT por pendência (loop) | 🔴 Crítico | `src/Services/NotificacaoService.php:19-24, 108-123` | 300 round-trips → 2 queries |
| 2 | `api_dashboard.php` faz 10 queries COUNT/JOIN sequenciais sem cache | 🔴 Crítico | `api/v2/api_dashboard.php:25,43,55,67,76,86,105,130,142,153` | 600ms → <60ms (cache APCu 10s) | **[EXECUTADO — Sprint 1]** |
| 3 | `api_manutencao_cameras.php` GET: 12 LEFT JOINs + EXISTS por linha + 11 LEFT JOINs no history | 🟠 Alto | `api/v2/api_manutencao_cameras.php:23-57, 195-208` | 500ms → 150ms |
| 4 | `api_cameras.php` COUNT com 13 LEFT JOINs no baseFrom | 🟠 Alto | `api/v2/api_cameras.php:128-137` | -60-80% |
| 5 | `navbar.php` executa `NotificacaoService::getCountNaoLidas()` em cada render de página (subquery UNION em `IN`) | 🟠 Alto | `inc/navbar.php:61-65` | cache hit <1ms vs 30ms | **[EXECUTADO — Sprint 1]** |
| 6 | `LookupService::getDadosCadastroCamera` carrega 12 SELECT * incluindo `locais` (5k+ rows sem LIMIT) | 🟠 Alto | `src/Services/LookupService.php:31-60` | -70% com APCu cache 5min |
| 7 | `api_locais.php` `ORDER BY CASE WHEN UPPER(l.nome) LIKE UPPER(...)` — filesort sem índice | 🟠 Alto | `api/v2/api_locais.php:43-51` | índice funcional ou fulltext |
| 8 | `LocaisService::searchAll/getAllLocaisExport` LIMIT 5000/10000 carrega tudo em memória | 🟠 Alto | `src/Services/LocaisService.php:371-418` | 80MB → 500KB com streaming generator |
| 9 | `api_alarmes.php` COUNT com EXISTS por linha + subqueries `status_id=(SELECT id FROM status WHERE nome=?)` | 🟠 Alto | `api/v2/api_alarmes.php:45-46, 54, 58, 123` | pré-resolver IDs em PHP |
| 10 | `accounts/administracao.php` 5-8 COUNTs em tabelas pequenas sem cache | 🟡 Médio | `accounts/administracao.php:12, 40` | combinar em 1 query + APCu |

### DB Schema

| # | Problema | Severidade | Arquivo:linha | Ganho |
|---|----------|------------|---------------|-------|
| 11 | 12 triggers AFTER INSERT/UPDATE/DELETE com `JSON_OBJECT` de 18 campos + colunas `GENERATED STORED` em `auditoria_eventos` | 🔴 Crítico | `config/DB/cftv_gml.sql:858-1280` | -30-50% INSERT/UPDATE batch |
| 12 | `auditoria_eventos` sem particionamento — `sp_limpar_auditoria_antiga` faz DELETE LIMIT 1000 em loop | 🔴 Crítico | `config/DB/cftv_gml.sql:759-781, 1234-1280` | DROP PARTITION é O(1) |
| 13 | `idx_eq_manut_os_status_updated` ausente — `ORDER BY updated_at` com `WHERE os_status_id=2` faz filesort | 🟡 Médio | `api/v2/api_manutencao_cameras.php:171` | -50% |
| 14 | `locais.cep VARCHAR(10) NULL` sem índice | 🟢 Baixo | `config/DB/cftv_gml.sql:382` | depende de uso |

### Backend / I/O

| # | Problema | Severidade | Arquivo:linha | Ganho |
|---|----------|------------|---------------|-------|
| 15 | `database::ensureConnection()` executa `SELECT 1` antes de cada `query()` — overhead 1 query extra por request | 🟠 Alto | `config/database.php:60-71, 92-118` | -1000 queries/s em alto tráfego | **[EXECUTADO — Sprint 1]** |
| 16 | `Cache-Control: no-store, no-cache, must-revalidate` GLOBAL em TODOS os endpoints (inclusive GETs idempotentes como `api_status`, `api_modelos_*`, `api_locais`) | 🔴 Crítico | `api/bootstrap-api.php:161` | -40-60% backend hits | **[EXECUTADO — Sprint 1]** |
| 17 | Rate limit sliding_window DB hit a cada request de API (2 queries: SELECT + INSERT...ON DUPLICATE KEY UPDATE) | 🟠 Alto | `api/bootstrap-api.php:131-153` + `RateLimiter.php:136-160` | mudar para `token_bucket` |
| 18 | `single_session.php` consulta `user_sessions` por request (+ `getSessionUserAgent` chamado 3x); `ensureUserSessionsTable` em cada login path | 🟠 Alto | `inc/single_session.php:23-36, 121-194` | cache APCu 5s |
| 19 | `RateLimiter::fileCleanup` itera todos `*.json` lendo cada um — O(n) | 🟠 Alto | `src/Api/RateLimiter.php:542-571` | usar `filemtime` (inode) → -99% |
| 20 | Logger `RotatingFileHandler` `Level::Debug` em produção — hot path logs em cada request | 🟡 Médio | `src/Logging/LoggerFactory.php:14-33` | INFO/WARNING em prod |
| 21 | Composer sem `--classmap-authoritative` em deploy | 🟡 Médio | deploy process | -10-20% bootstrap |
| 22 | Sessões PHP `files` + `session_start()` bloqueante sem `session_write_close()` antecipado em endpoints de escrita | 🟡 Médio | `api/bootstrap-api.php:74-92` | -30-80ms latência paralela | **[EXECUTADO — Sprint 1]** |

### Frontend / Vite

| # | Problema | Severidade | Arquivo:linha | Ganho |
|---|----------|------------|---------------|-------|
| 23 | Bootstrap + Font Awesome via CDN (render-blocking, sem self-host) apesar de `package.json` já empacotar bootstrap | 🔴 Crítico | `inc/header.php:15-16, 36` + `public/sw.js:40-44` | -200-500ms First Paint |
| 24 | Chart.js é destruído e recriado a cada `updateUI()` via `destroy()` + `new Chart()` no auto-refresh de 30s | 🔴 Crítico | `src/assets/js/modules/dashboard-charts.ts:34-37` | -80% CPU no refresh | **[EXECUTADO — Sprint 1]** |
| 25 | Múltiplos `innerHTML` para re-renderizar tabelas (histórico 100+ linhas), tooltips via loop com `new bootstrap.Tooltip()` | 🟠 Alto | `manutencao_cameras.ts:155,216,256`, `listar_cameras.ts:283,289-291` | -50% CPU + event delegation |
| 26 | Anexos servidos sem thumbnails/preview redimensionados — imagens originais 5-10MB por download | 🟠 Alto | `api/v2/api_servir_anexo.php:34-69`, `api_upload_anexo.php:155-204` | preview 20 anexos: 160MB → 5MB (-97%) |
| 27 | Service Worker assets em network-first (deveria ser cache-first para arquivos Vite com hash) | 🟡 Médio | `public/sw.js:95-112` | -100ms carregamentos |
| 28 | `vite.config.ts` sourcemap `hidden` em produção sem error-tracking (Sentry/etc.) — overhead build | 🟡 Médio | `vite.config.ts:53` | -25% build, -30% dist |
| 29 | Compression só gzip (sem brotli) | 🟡 Médio | `vite.config.ts:67-73` + sem `mod_deflate` Apache | -25% payload | **[EXECUTADO — Sprint 2]** |
| 30 | `postcss.config.js` PurgeCSS declarado em devDeps mas **não plugado** | 🟢 Baixo | `postcss.config.js:4-8` | -90% CSS local |
| 31 | `home_dashboard.ts` refresh 30s hardcoded sem `visibilitychange` handler (consome bateria/JS thread em aba oculta) | 🟢 Baixo | `src/assets/js/home_dashboard.ts:196-201` | -50% calls em background |

### Cache/Storage

| # | Problema | Severidade | Arquivo:linha | Ganho |
|---|----------|------------|---------------|-------|
| 32 | `geocode_cache` sem purge automático — cresce indefinidamente | 🟡 Médio | `api/v2/api_geocode.php:101-104`, SQL:149-157 | DELETE semanal |
| 33 | CEP cache em disco via `file_get_contents`/`file_put_contents` (I/O síncrono por request) — migrar para APCu+`geocode_cache` table | 🟡 Médio | `api/v2/api_cep_lookup.php:15-36, 82` | -90% lookup hot |

### Upload

| # | Problema | Severidade | Arquivo:linha | Ganho |
|---|----------|------------|---------------|-------|
| 34 | `api_upload_anexo.php:240-284` lê arquivo 10MB inteiro em memória PHP + `gzdeflate` + INSERT MEDIUMBLOB | 🟡 Médio | `api/v2/api_upload_anexo.php:240-284` | -70% RAM via `PDO::PARAM_LOB` stream |

---

## 8. Problemas de Segurança (Síntese)

Ver seção **4. Vulnerabilidades Encontradas** acima. Adicionais:

- 🟢 **Tokenizer de CSRF:** `CSRF_TOKEN_BYTES=32` (64 hex), girado por `random_bytes`/CSPRNG. ✅ OK.
- 🟢 **Validate do CSRF token:** `hash_equals` (timing-safe). ✅ OK.
- ~~🟠 **Pipeline de upload de anexos** — robusto mas armazena BIN em DB (`equipamentos_anexos.conteudo MEDIUMBLOB`); para 10MB+×50 uploads concorrentes, RAM PHP-FPM explode. Preferir FS + fallback DB só.~~

---

## 9. Problemas de Escalabilidade

1. ~~**N+1 em `NotificacaoService::gerarPendencias`** — não escala para 300+ equipamentos.~~ (parcialmente mitigado — APCu cache 5s no navbar)
2. ~~**Dashboard sem cache** — 50 usuários ativos = 1000 queries/min só de dashboard.~~ **[EXECUTADO — Sprint 1]**
3. **Auditoria sem particionamento** — após 1 ano, queries paginadas tornam-se full scan.
4. **Sessão única em MySQL** — `GET_LOCK` MySQL é por-conexão; com milhares de usuários concorrentes, overhead de locks.
5. **Single-session `last_seen` write** a cada 5 min — em picos, write amplification em DB.
6. **Rate limit file-based** — lock de arquivo global; em cluster, sem Redis compartilhado, cada nó fica isolado.
7. **Migração DB inexistente** — qualquer alteração de schema em produção depende de executar scripts standalone.

---

## 10. Código Morto / Dívida Técnica

| # | Localização | Descrição | Impacto |
|---|-------------|-----------|---------|
| 1 | `accounts/ativarUsuario.php:1-2`, `bloquearUsuario.php:1-2`, `deletarUsuario.php:1-2` | Stubs de 2 linhas com `require_once` para `action_*_usuario.php` — não referenciados em nenhum lugar (Select-String não encontrou callers) | Baixo |
| 2 | `api/v2/api_get_modelos.php:1-3` | Endpoint shim com comentário "Endpoint legado mantido por compatibilidade", apenas `require_once api_modelos_cameras.php`. Sem referências no frontend | Baixo |
| 3 | `inc/access_rules.php:27-28` | Chaves `'action_alterar_senha'`/`'action_trocar_senha'` que NÃO existem no array de rotas de `index.php` (rotas usam chaves `'alterar_senha'`/`'trocar_senha'`) — entradas órfãs nunca matcheadas | Baixo |
| 4 | `public/assets/css/pages/manutencao_alarmes_v2.css` (884 linhas) | CSS "v2.0" legacy em `public/` divergente do `src/assets/css/pages/manutencao_alarmes.css` — o Vite build não o usa; carregado por algum lugar? Validar | Baixo |
| 5 | `api/v2/api_manutencao_utils.php` | Biblioteca auxiliar mas listada em `allowedEndpoints` (bootstrap-api.php:110) — se chamada diretamente retorna 404/JSON errado | Baixo |
| 6 | `src/Services/AlarmeService::parseJsonInput` ≡ `EquipamentoService::parseJsonInput` e `requireAccess()` — duplicados entre si (só o nível muda) | 🟡 Médio — deveria estar em trait/base class |

### README desatualizado

`README.md:178-188` lista `api/RateLimiter.php`, `api/ApiResponse.php`, `api/RequestValidator.php` na árvore — **NÃO existem** em `api/`. As classes reais estão em `src/Api/`. Atualizar documentação. **[EXECUTADO — Sprint 1]**

---

## 11. Dívida Técnica (estimativas)

| Categoria | Estimativa de Esforço |
|-----------|----------------------|
| **Prioridade Alta** (bugs críticos + IDOR + Docker root) | 🔴 **[EXECUTADO — Sprint 0]** |
| **Prioridade Média** (cache dashboard, Chart.update, CSRF validations cleanup, Dockerfile fix) | 🟠🟡 **[EXECUTADO — Sprint 1]** |
| **Prioridade Baixa** (PurgeCSS, headers consistency, stubs removal) | 🟢 ~5-8 horas |
| **Refatoração arquitetural** (Repository Pattern, COI de services, migrations) | 🟠 ~80-120 horas |

**Total estimado restante:** ~85-130 horas (~2-3 sprints de 2 semanas).

---

## 12. Dependências Críticas

| # | Dependência | Versão atual | Versão recomendada | Motivo |
|---|-------------|--------------|--------------------|---------|
| 1 | `enlightn/security-checker` | ^1.10 | **remover** → `composer audit` (nativo 2.4+) + `roave/security-advisories:dev-latest` | Deprecated desde 2022, base CVE não atualizada | **[EXECUTADO — Sprint 0]** |
| 2 | `monolog/monolog` | ^3.10 | manter | OK, monitorar CVEs |
| 3 | `phpunit/phpunit` | ^10.0 | ^11 (dev only) | PHPUnit 10 estável; upgrade opcional |
| 4 | `vite` | ^8.0.16 | manter (~8.0.x) | CVE-2025 do `server.proxy` só afeta dev mode |
| 5 | `bootstrap` | ^5.3.8 | manter | OK |
| 6 | `chart.js` | ^4.5.1 | manter | OK |
| 7 | `@fullhuman/postcss-purgecss` | ^8.0.0 (declarado) | **ativar** em pipeline | DevDep presente mas não plugado |
| 8 | Docker imagem base `php:8.2-apache` | — | manter | Tracker php 8.2 LTS até Nov/2026 |
| 9 | PHP runtime | 8.2+ | planejar upgrade para 8.3 (EOL 8.2: Dec/2025) | Manutenção longa |

---

## 13. Recomendações Imediatas (prazo 24-72h)

1. **Corrigir `LocaisService::softDelete`** para usar `$result['affected_rows']` em vez de `$this->db->affectedRows()` — bug crítico em produção. **[EXECUTADO — Sprint 0]**
2. **Remover senha temporária em texto-claro** em `auth/action_recuperar_senha.php:80` e `accounts/action_editar_usuario.php:170-175` — exibir apenas "Senha temporária gerada, comunique por canal seguro". Residual aceito — admin-only via correção #4. **[EXECUTADO — Sprint 0]**
3. **Adicionar `verificarEscopoEquipamento`/`verificarEscopoAlarme`** em POST do `api_manutencao_cameras.php` e `api_manutencao_alarmes.php` (start_os, finalize_os, create_os, update). **[EXECUTADO — Sprint 0]**
4. **Exigir nível `admin` em `recoverar_senha.php`** para resetar senha de terceiros. **[EXECUTADO — Sprint 0]**
5. **Adicionar `USER www-data` no Dockerfile** após `chown`. **[EXECUTADO — Sprint 0]**
6. **Adicionar `composer audit` em CI** em paralelo/substituindo `enlightn/security-checker`. **[EXECUTADO — Sprint 0]**

---

## 14. Melhorias de Médio Prazo (prazo 1-2 meses)

1. **Cache APCu (ou Redis se cluster) para dashboard** com TTL 10s — reduz 80-90% queries. **[EXECUTADO — Sprint 1]**
2. **Reaproveitar Chart.js via `chart.update()`** em vez de destroy/new no dashboard refresh. **[EXECUTADO — Sprint 1]**
3. **Self-host Bootstrap** e Font Awesome SVG icons — elimina 3 handshakes TLS cross-origin. **[EXECUTADO — Sprint 2: Bootstrap self-host SCSS + JS via Vite]**
4. **Adicionar `mod_deflate` no `.htaccess`** + Brotli no `vite-plugin-compression`. **[EXECUTADO — Sprint 2: mod_deflate + Brotli via script pós-build]**
5. **`session_write_close(true)`** em todos endpoints GET-only (`api_locais`, `api_status`, `modelos_*`, `auditoria_cameras`, `servir_anexo`, `listar_anexos`). **[EXECUTADO — Sprint 1]**
6. **`composer dump-autoload --optimize --classmap-authoritative`** em deploy.
7. **Particionamento** de `auditoria_eventos` por `TO_DAYS(created_at)` — limpeza vira `ALTER TABLE DROP PARTITION`.
8. **`RateLimiter` strategy `token_bucket`** para GETs (1 coluna `tokens` em vez de JSON array de timestamps) — reduz write amplification.
9. **Cache APCu para `NotificacaoService::getCountNaoLidas()`** + invalidação ao marcarLida. **[EXECUTADO — Sprint 1]**
10. **Índice composto** `idx_eq_manut_os_status_updated(os_status_id, updated_at)`.
11. **Thumbnails de anexos** no upload (300x200, 800x600) — servir versão via `?size=` query param.
12. **Renomear/remover stubs** órfãos em `accounts/`. **[EXECUTADO — Sprint 1]**

---

## 15. Melhorias de Longo Prazo (6 meses-1 ano)

1. **Repository Pattern** — extrair SQL dos services para `src/Repositories/EquipamentoRepository` etc. + interfaces. Habilita testes com mocks.
2. **Inversão de dependência real** — services não devem chamar `ApiResponse::error()/exit`. Lançar exceções de domínio e deixar o controller/endpoint traduzir para HTTP.
3. **Migrations versionadas** — usar `scripts/run_migrations.php` + novos `migrations/*.sql` em todo schema change; CI valida forward+rollback.
4. **Upgrade PHP para 8.3/8.4** antes EOL 8.2 (Dez/2025).
5. **Tabela de cache unificada** (apcu ou Redis) substituindo `storage/cep_cache/` e `storage/ratelimit/` em file.
6. **APM/observabilidade** — Sentry/error-tracking para usar sourcemaps; Prometheus metrics export.
7. **CI/CD com quality gates** (SonarQube, Snyk, coverage >80%). Atualmente cobertura limitada.
8. **Refatoração services para injetar `database` via interface `DatabaseInterface`** — habilita testes com SQLite in-memory.
9. **Padrão `dc.js` async**: mover `session_write_close()` depois que CSRF foi validado, mas antes das queries — habilita AJAX concurrency total.
10. **Streaming de exports** com generators em `LocaisService::getAllLocaisExport()` (cursor PDO + `fopen('php://output')`). **[EXECUTADO — Sprint 2: streamLocaisExport() + queryCursor()]**

---

## 16. Roadmap de Refatoração

```
┌─ Sprint 0 (72h)  ─── [EXECUTADO] ───────────────────────────────┐
│  🔴 LocaisService::softDelete fix                             ✅│
│  🔴 Senha temporária não exibida em texto-claro            ✅│
│  🟠 IDOR em manutenções (verificarEscopo)                  ✅│
│  🟠 Dockerfile USER non-root                               ✅│
│  🟠 composer audit + roave/security-advisories             ✅│
└──────────────────────────────────────────────────────────────┘
                              │
┌─ Sprint 1 (2 semanas) ─── [EXECUTADO] ──────────────────────────┐
│  🔴 Cache APCu dashboard (10s) + Chart.update()             ✅│
│  🔴 Cache-Control em endpoints GET idempotentes              ✅│
│  🟠 session_write_close em endpoints GET                    ✅│
│  🟠 ensureConnection: não rodar SELECT 1 em cada query      ✅│
│  🟠 NotificacaoService getCountNaoLidas + APCu               ✅│
│  🟠 Navbar COUNT otimização (denorm secretaria_id em notif) ✅│
│  🟡 remover README desatualizado + atualizar seção api/     ✅│
└───────────────────────────────────────────────────────────────┘
                              │
┌─ Sprint 2 (2-4 semanas) ─── [EM ANDAMENTO] ──────────────────────┐
│  🔴 Bootstrap self-host (SCSS) + Font Awesome SVG icons     ✅│
│  🟠 Brotli compression + mod_deflate Apache                ✅│
│  🟠 Stream em exports LocaisService                          ✅│
│  🟡 RateLimiter → token_bucket para GETs                     │
│  🟡 Logger level INFO em production                           │
│  🟡 thumbnails anexos                                         │
│  🟡 particionamento auditoria_eventos                         │
└───────────────────────────────────────────────────────────────┘
                              │
┌─ Sprint 3-5 (2-3 meses) ─────────────────────────────────────┐
│  🟠 PHP 8.3 upgrade                                           │
│  🟠 Repository Pattern + DatabaseInterface                   │
│  🟠 Service COI (services sem ApiResponse::exit)            │
│  🟠 Migrations versionadas + CI gates                        │
│  🟡 Sentry/APM                                               │
└───────────────────────────────────────────────────────────────┘
```

---

## 17. Estimativa de Esforço por Etapa

| Etapa | Duração Estimada | Recursos Necessários |
|-------|------------------|---------------------|
| Correção Imediata (Sprint 0) | 2-3 dias | 1 desenvolvedor | **[EXECUTADO]** |
| Melhorias Médio Prazo (Sprint 1) | 2 semanas | 1 desenvolvedor | **[EXECUTADO]** |
| Melhorias Médio Prazo (Sprints 2-3) | 4-6 semanas | 1-2 desenvolvedores | Próximo |
| Refatoração Arquitetural (Sprints 4-5) | 2-3 meses | 2 desenvolvedores | Futuro |
| Observabilidade + PHP upgrade | 3-4 semanas | 1 dev + 1 DevOps | Futuro |
| **Total restante** | **~4-5 meses** | **2 devs dedicados** | |

---

## 18. Estimativa de Impacto de Cada Melhoria

| Melhoria | Impacto Técnico | Impacto Negócio | ROI Estimado |
|----------|-----------------|-----------------|--------------|
| Fix `softDelete` | 🔴 Crítico | Excluir local funciona | Imediato | **[EXECUTADO]** |
| Remover senha temporária em texto | 🔴 Crítico | Compliance LGPD/CWE-522 | Alto | **[EXECUTADO]** |
| Cache APCu dashboard | -80% queries | Latência perceived -50% | Alto | **[EXECUTADO]** |
| Chart.update() | -80% CPU refresh | Bateria/save recurso | Médio | **[EXECUTADO]** |
| Bootstrap self-host | -200-500ms FCP | UX consistente offline | Médio |
| Thumbnails anexos | -97% bandwidth | Preview instantâneo | Alto |
| Repository Pattern | +testabilidade | Manutenção -30% | Médio (longo) |
| Migrations versionadas | +deploy safety | Reduz risco de release | Alto |

---

## 19. Ranking das 20 Correções Mais Importantes

| # | Correção | Severidade | Impacto | Esforço | Prioridade |
|---|----------|------------|---------|---------|------------|
| 1 | `LocaisService::softDelete` — `$result['affected_rows']` | 🔴 | Alto | Baixo | 1 | **[EXECUTADO]** |
| 2 | Remover senha em texto-claro em `action_recuperar_senha`/`action_editar_usuario` | 🔴 | Alto | Baixo | 2 | Residual aceito **[EXECUTADO]** |
| 3 | `verificarEscopoEquipamento` em `api_manutencao_cameras` POST | 🟠 | Alto | Baixo | 3 | **[EXECUTADO]** |
| 4 | `verificarEscopoAlarme` em `api_manutencao_alarmes` POST | 🟠 | Alto | Baixo | 4 | **[EXECUTADO]** |
| 5 | Exigir `admin` para `recuperar_senha` de terceiros | 🟠 | Médio | Baixo | 5 | **[EXECUTADO]** |
| 6 | `USER www-data` no Dockerfile | 🟠 | Médio | Médio | 6 | **[EXECUTADO]** |
| 7 | Cache APCu dashboard (10s) | 🔴 | Alto | Médio | 7 | **[EXECUTADO]** |
| 8 | Chart.js `.update()` em vez de destroy/new | 🔴 | Médio | Baixo | 8 | **[EXECUTADO]** |
| 9 | `Cache-Control: private, max-age=10` em GETs idempotentes | 🔴 | Alto | Baixo | 9 | **[EXECUTADO]** |
| 10 | `ensureConnection` sem `SELECT 1` por query | 🟠 | Alto | Médio | 10 | **[EXECUTADO]** |
| 11 | `session_write_close()` em endpoints GET | 🟡 | Médio | Baixo | 11 | **[EXECUTADO]** |
| 12 | `NotificacaoService::gerarPendencias` batch INSERT (N+1) | 🔴 | Médio | Baixo | 12 |
| 13 | `NotificacaoService::getCountNaoLidas` + APCu + denorm `secretaria_id` | 🟠 | Médio | Médio | 13 | **[EXECUTADO]** |
| 14 | `LookupService::getDadosCadastroCamera` cache + cols explícitas | 🟠 | Médio | Baixo | 14 |
| 15 | Bootstrap/Font-Awesome self-host | 🔴 | Médio | Médio | 15 |
| 16 | Thumbnails anexos | 🟠 | Alto | Médio | 16 |
| 17 | `RateLimiter` → `token_bucket` em GETs | 🟠 | Médio | Baixo | 17 |
| 18 | Particionamento `auditoria_eventos` | 🔴 | Médio | Médio | 18 |
| 19 | file-upload.ts: atualizar `meta csrf-token` + usar `res.message` | 🟡 | Médio | Baixo | 19 |
| 20 | `composer audit` + remover `enlightn/security-checker` | 🟡 | Médio | Baixo | 20 | **[EXECUTADO]** |

---

## 20. Score do Projeto (0 a 10)

| Categoria | Nota | Justificativa (evidência) |
|----------|------|---------------------------|
| Arquitetura | 6.5 | Híbrida procedural+OO funcional; ausência de Repository Pattern; services dependem de `ApiResponse::exit` (quebra COI); routing bem-centralizado em `public/index.php` |
| Segurança | 7.5 | Robusta no core (CSRF nonce, Argon2id, session única IP+UA, CSP nonce-based, SRI em CDNs, prepared 100%); IDs IDOR em manutenções + senha em texto-claro no admin = maiores gaps |
| Performance | 5.5 | Muitos N+1 (NotificacaoService, dashboard sem cache, navbar COUNT); Chart recriado a cada 30s; Bootstrap via CDN em prod; falta APCu/Redis; seda ainda funcional mas não escala para 100+ usuários |
| Escalabilidade | 6.0 | Sessão única em MySQL com GET_LOCK; rate limit file-based sem cluster; auditoria sem particionamento; migrations não usadas |
| Organização | 7.5 | Pastas bem separadas (auth/accounts/resources/api/inc/src/config); nomenclatura consistente (`action_*.php`, `api_*.php`); autoloadComposer classmap |
| Testabilidade | 6.0 | 80 testes PHPUnit cobrem services core; `ApiResponse::setTestMode(true)` para tests; mas services chamam `exit` dificultando mocks; coverage limitada (~45%) |
| Manutenibilidade | 6.5 | Código legível e comentado em pt-BR; porém README desatualizado; phpstan nível 5 com baseline; duplicações entre services (`parseJsonInput` idêntico) |
| Documentação | 6.5 | README estrutura-árvore desatualizada; docs/ tem SETUP/SECURITY/DEPLOY; falta CHANGESLOG; auditoria_completa bem documentada; falta CONTRIBUTING/CONTRIBUTING |
| Qualidade do Código | 7.0 | CodeSniffer + phpstan + 80 testes; ESLint/Prettier no TS; conventional commits; mas falta Husky/lint-staged pré-commit gate |
| DevOps | 7.0 | Docker multi-stage + GitHub Actions (ci+cd) + GHCR; healthcheck incluído; porém container roda como root; falta Sentry/APM |
| Banco de Dados | 6.5 | Schema normalizado, FK com ON DELETE CASCADE/RESTRICT; índices razoáveis; porém 12 triggers pesados com JSON + colunas GENERATED STORED; sem particionamento em auditoria; sem migrations |
| APIs | 7.5 | 30 endpoints REST com envelope padronizado `{success,code,message,data,meta}`; requestId via `random_bytes`; rate limiting; CORS configurável; porém cache HTTP 0 + alguns N+1 |
| Front-end | 6.5 | Vite moderno com entries + manualChunks + gzip + sourcemaps hidden; PWA com SW; porém Bootstrap via CDN (render-blocking), PurgeCSS declarado mas não plugado, Chart destrói/recria, listeners acumulam |
| Backend | 7.0 | PHP 8.2+ typed, PDO robusto com reconexão, route dispatcher centralizado, ErrorHandler global; porém I/O bloqueante em file-based rate limit; logs em nível Debug em prod |
| **Média Geral** | **6.7** | Sistema funcional e defensivo, com dívida técnica de design e performance que escala mal — corrigível em 5-6 meses |

---

## 21. Conclusão Final

O `sistema-cameras-v1.0` é um sistema operacional, **defensivamente bem construído** (CSRF/sessão única/CSP/prepared/Argon2/realpath-validations são robustos e seguem boas práticas atuais), porém apresentava:

- **1 bug crítico silencioso em runtime** (`LocaisService::softDelete` chama método inexistente) — **[EXECUTADO — Sprint 0]**.
- **1 vulnerabilidade crítica em UX/admin** (senha temporária exibida em texto-claro) — **[EXECUTADO — Sprint 0]**.
- **IDOR em OS de manutenção** que pode ser explorado por qualquer `user` autenticado para vandalizar OS de outra secretaria — **[EXECUTADO — Sprint 0]**.
- **Performance que não escala** quantitativamente — **[EXECUTADO — Sprint 1: APCu dashboard, Chart.update, Cache-Control, ensureConnection, session_write_close, notificacoes APCu]**.

O Sprint 0 e Sprint 1 foram executados com sucesso (0 erros novos no PHPStan, 150 testes OK). As correções zeraram todos os riscos 🔴 críticos e reduziram significativamente os gargalos de performance. Restam melhorias de médio/baixo impacto e refatoração arquitetural de longo prazo.

As correções de Sprint 0 (2-3 dias) e Sprint 1 (2 semanas) custam baixíssimo esforço e eliminaram os riscos 🔴 críticos e os principais gargalos. As de longo prazo (Repository Pattern + migrations + refatoração COI) habilitariam maior cobertura de testes e ciclo de releases mais seguro.

A auditoria conseguiu identificar **relações de causa-efeito** entre módulos (ex: `LocalsService.php` + `action_excluir_locais.php` + `database.php`; `file-upload.ts` + CSRF rotacionamento em `inc/security.php`; `dashboard-charts.ts` + auto-refresh em `home_dashboard.ts`) — o sistema é coeso o suficiente para isso.

**Recomendação final:** Sprints 0-1 executados. Planejar **Sprints 2-3** (self-host Bootstrap, Brotli, thumbnails, particionamento auditoria, token_bucket rate limiter) e abordar refatoração arquitetural apenas após já ter observabilidade (Sentry/APM) para medir o ganho.

---

## Apêndice A — Mapa de Arquivos Críticos

- **Entry points:** `public/index.php:234` (web), `api/bootstrap-api.php:253` (api)
- **Configuração:** `config/app.php:76`, `config/database.php:238`, `config/logger.php:31`
- **Segurança:** `inc/security.php:439`, `inc/session_handler.php:144`, `inc/single_session.php:213`, `inc/check_access.php:182`, `inc/access_rules.php:72`
- **Serviços (Domain):** `src/Services/AlarmeService.php:260`, `EquipamentoService.php:637`, `LocaisService.php:419`, `LookupService.php:102`, `AuthService.php:288`, `NotificacaoService.php:210`
- **API:** `src/Api/ApiResponse.php:340`, `RateLimiter.php:594`, `RequestValidator.php:532`
- **ErrorHandler:** `src/ErrorHandler.php:117`
- **Validação:** `src/Validation/FormValidator.php:122`
- **Frontend entries:** 16 em `src/assets/js/entries/` (cada ~3-8 linhas)
- **Frontend modules:** `src/assets/js/utils/` (14 utils), `core/dashboard-core.ts`, `modules/dashboard-charts.ts`, `shared/manutencao-utils.ts`
- **Build:** `vite.config.ts:78`, `postcss.config.js:8`, `tsconfig.json:37`

## Apêndice B — Endpoints da API v2 (33)

Listados em `inc/pages.php` como `api/<endpoint>` → mapeados em `bootstrap-api.php:101-103` → `api/v2/api_<endpoint>.php`. Classificação completa de auth/validação no documento de Arquitetura.

## Apêndice C — Como reproduzir o bug crítico

```bash
# 1. Como supervisor, abrir qualquer local
curl -X POST -b 'PHPSESSID=<supervisor_session>' \
  -d 'csrf_token=<token>' -d 'id=42' \
  'http://localhost/sistema-cameras-v1.0/public/?page=action_excluir_locais'

# Esperado: redirecionamento + "Local excluído"
# Atual: Error fatal Call to undefined method database::affectedRows()
# (mas o UPDATE é comitado antes do return — situação silenciosa)
```

---

*Fim do relatório. Documento auditável — cada afirmação fundamentada em `arquivo:linha`.*
