# AUDIT_MATRIX.md

Índice central de controles da auditoria do projeto "Controle Share Videos" (Sistema de Gerenciamento de Câmeras e Alarmes v1.0).

## Legenda

| Resultado | Significado |
| --------- | ----------- |
| OK | Controle confirmado, sem achados |
| FALHA | Achado confirmado com evidência |
| NA | Não aplicável |
| NAUD | Não auditado nesta fase |
| BLOQ | Bloqueado (evidência indisponível) |

| ID | Área | Controle | Resultado | Evidência | Finding |
| -- | ---- | -------- | --------- | --------- | ------- |
| AUD-001 | Baseline | Suite PHPUnit executa sem erro fatal | FALHA | `vendor/bin/phpunit --no-coverage` → Tests: 172, Assertions: 322, Errors: 3, Failures: 14, Skipped: 18 (2026-08-16) | [F-001] Erros em LocaisServiceTest; [F-002] Falhas em testes de API exigem servidor HTTP |
| AUD-001b | Regressão | F-001 corrigido — PHPUnit sem erros | OK | `vendor/bin/phpunit --no-coverage` → Tests: 172, Assertions: 325, Failures: 14, Skipped: 18 (2026-08-16, pós-correção) | [F-001] **RESOLVIDO** — `LocaisService::update()` com fallback legacy; testes com `getConnection()` mockado |
| AUD-002 | Baseline | Suite Vitest executa sem erro | OK | `npm run test` → 11 arquivos, 88 testes passando (2026-08-16) | — |
| AUD-003 | Baseline | PHPStan nível 5 sem erros | OK | `vendor/bin/phpstan analyse --no-progress` → "No errors" (2026-08-16, pós-fix) | [F-003] **RESOLVIDO** — `database/` adicionado aos paths do phpstan.neon |
| AUD-004 | Baseline | Lint sintático PHP sem erros | OK | `php -l` em 133 arquivos (api, config, src, inc, auth, accounts, database, resources, tests) → 0 erros (2026-08-16) | — |
| AUD-005 | Dependências | `composer validate --strict` sem erros | OK | `composer validate --strict` → "./composer.json is valid" (2026-08-16, pós-fix) | [F-004] **RESOLVIDO** — composer.lock sincronizado via `composer update` |
| AUD-006 | Dependências | `composer audit` sem vulnerabilidades | OK | `composer audit` → "No security vulnerability advisories found" (2026-08-16, pós-fix) | [F-005] **RESOLVIDO** — php_codesniffer 3.13.6 |
| AUD-007 | Ambiente | Config carregada sem erro | OK | `config/config.php:79-82` carrega via env `DB_*`; `getEnvPath()` retorna `/etc/sistema-cameras/.env` em Linux (`config/config.php:53-59`) | [F-006] Divergência: CI cria `.env` em `$HOME/env/sistema-cameras/.env` mas código espera `/etc/sistema-cameras/.env` — sem impacto real porque CI injeta env vars diretamente |
| AUD-008 | Regressão | "28 erros pré-existentes" da trait ParsesInput corrigidos | OK | Suite PHPUnit 2026-08-16 não apresenta erros de `ParsesInput`; `tests/Unit/EquipamentoServiceTest`/`AlarmeServiceTest` passam | — |
| AUD-009 | Cobertura | Inventário de endpoints API completo | OK | `api/v2/` → 34 arquivos (2026-08-16) | [F-007] Inventário DISCOVERY registrava 33 endpoints; total real é 34 (inclui `helpers/`) |
| AUD-010 | Inventário | LOC e estrutura inventariados | OK | api: 4.790 linhas/35 arq; src: 4.157/17; inc: 2.648/15; resources: 4.573/31; accounts: 1.699/14; config: 663/6; tests: 2.432/15; database: 385/3 (2026-08-16) | — |
| AUD-011 | Ambiente | PHPUnit sem servidor HTTP tem comportamento conhecido | OK | Falhas F-002 em `Tests\Api\*` retornam 404 (servidor ausente) em vez de 401 — padrão documentado no README ("requer servidor http rodando") | [F-002] |
| AUD-012 | Git | Estado do repositório consistente | OK | branch `main`, 120 commits, sem tags; HEAD `0838504`; working tree: `M docs/SETUP.md`, `M opencode.json`, `?? docs/DISCOVERY.md`, `?? docs/ESPECIFICACAO-FINAL.md` (2026-08-16) | — |
| AUD-013 | Segurança | Postura de segurança sem regressão | NAUD | Inspeção estática ok em `inc/security.php`; execução de exploração pendente para fases de segurança | — |
| AUD-014 | Ambiente | Banco de dados acessível para testes | BLOQ | Sem instância MySQL local em execução; `.env` de teste criado em `$HOME/env/sistema-cameras/.env` mas não exercitado | — |
| AUD-015 | Qualidade | Comandos de qualidade reproduzíveis | OK | Dependências instaladas (composer install + npm ci) geram baseline reproduzível | — |
| AUD-016 | Segurança | SQL Injection | OK | Prepared statements, `EMULATE_PREPARES=false` (`config/database.php`); whitelist de colunas nos services | — |
| AUD-017 | Segurança | Command Injection / Deserialização | OK | Zero `shell_exec`/`exec`/`system`/`unserialize`/`eval` em produção | — |
| AUD-018 | Segurança | XSS | OK | 154× `htmlspecialchars`; CSP estrito (HTML + API); sem `echo $_` direto | — |
| AUD-019 | Segurança | Path Traversal (uploads) | OK | Nome randomizado (`random_bytes`), `realpath` + prefixo em `api_servir_anexo.php:88-112` | — |
| AUD-020 | Segurança | Autenticação (senhas) | OK | Argon2id (`inc/security.php:397-420`), política forte, dummy hash anti-enumeração | — |
| AUD-021 | Segurança | Sessões | OK | HttpOnly + SameSite=Strict + secure condicional, sid 48, timeout inatividade+absoluto, sessão única | — |
| AUD-022 | Segurança | Brute force / Rate limit | OK | 5 tentativas/15min usuário+IP com lockout; RateLimiter dupla camada | — |
| AUD-023 | Segurança | Autorização (IDOR/BOLA) | OK (F-103 pendente) | `verificarEscopo*` por secretaria; role matrix em `bootstrap-api.php:25-57` | [F-103] supervisor isento de escopo em equip/anexo/alarme |
| AUD-024 | Segurança | CSRF | OK | Token em pool com consumo e rotação, TTL 180s, `hash_equals` | — |
| AUD-025 | Segurança | CORS | OK | Allowlist `APP_ALLOWED_ORIGINS` + validação same-origin; `*` gera warning | — |
| AUD-026 | Segurança | SSRF | OK | URLs fixas (nominatim/photon/viacep), SSL verify, timeout, rate limit | — |
| AUD-027 | Segurança | Upload | OK | MIME real via `finfo`, extensão derivada do MIME, 10MB, imagens reprocessadas via GD, magic header em Office/PDF | — |
| AUD-028 | Segurança | Secrets | OK | Nenhum secret hardcoded; `.env.example`/`.env.docker` são placeholders | — |
| AUD-029 | Segurança | Configuração/Headers | OK | CSP, nosniff, frame-ancestors, Referrer-Policy, Permissions-Policy, HSTS | — |
| AUD-030 | Segurança | Vazamento de erro ao cliente | FALHA | `api/v2/api_editar_camera.php:117` concatena `$e->getMessage()` na resposta | [F-101] CWE-209 — mensagem genérica + log |
| AUD-031 | Segurança | Logging sensível | OK | Tokens logados apenas por prefixo; auditoria sem dados sensíveis | — |
| AUD-032 | Performance | Paginação + COUNT separado | OK | Listagens paginadas com COUNT dedicado (`api_cameras.php:152-156` etc.) | — |
| AUD-033 | Performance | N+1 | OK | Sem N+1; batch EXISTS/INSERT no `NotificacaoService`; lookup cacheado | — |
| AUD-034 | Performance | Cache APCu | OK (F-201/F-202) | TTLs curtos; APCu/OPcache ativos no Docker | [F-201] dashboard global; [F-202] escrita não invalida |
| AUD-035 | Performance | Escopo de dados por secretaria | OK | `api_dashboard.php` filtra por secretaria (perfil user) via `$equipScope`/`$alarmScope` + EXISTS em locais | [F-201] escopo + cache redundante |
| AUD-036 | Banco | Integridade referencial / constraints | OK | FK completas com ON DELETE; CHECKs; soft delete indexado | — |
| AUD-037 | Banco | Migrações | OK (F-303) | Runner robusto + auto_migrate com GET_LOCK | [F-303] seed divergente |
| AUD-038 | Banco | Índices | OK (F-302) | Cobertura ampla; candidatos a redundância (EXPLAIN) | [F-302] duplicado em auditoria_eventos |
| AUD-039 | Banco | Auditoria / eventos agendados | FALHA | `event_scheduler` nunca habilitado; buffer de auditoria nunca é descarregado | [F-301] |
| AUD-040 | Banco | Particionamento | OK (F-304) | Auditoria particionada por mês, sem manutenção futura | [F-304] |
| AUD-041 | Qualidade | PSR-12 / estilo de código | FALHA | 226 erros + 187 warnings (212 autocorrigíveis via phpcbf) | [F-406] |
| AUD-042 | Qualidade | DRY / duplicação | FALHA | Endpoints de manutenção de câmeras e alarmes espelhados (~600 linhas cada) | [F-401] |
| AUD-043 | Qualidade | Código morto | FALHA | `getInputPayload()` não utilizado | [F-402] |
| AUD-044 | Qualidade | Complexidade ciclomática | FALHA | `extractCommonData` (65) e `validateInput` (63) acima de 20 | [F-404] |
| AUD-045 | Qualidade | Naming / coesão / acoplamento | OK | Nomes claros, coesão boa, acoplamento baixo | — |
| AUD-046 | Qualidade | Namespaces / PSR-4 | FALHA | 13 classes `src/` sem namespace (classmap) | [F-403] |
| AUD-047 | Qualidade | Documentação de código (README migrations) | FALHA | Tabela desatualizada vs commit de909e9 | [F-407] |
| AUD-048 | Dependências | Vulnerabilidades (composer/npm) | FALHA | 5 high em undici (via jsdom, testes) | [F-501] Corrigido |
| AUD-049 | Dependências | Versões desatualizadas | OK (F-502..506) | Sem urgência; majors de tooling dev postergadas | [F-502]..[F-506] |
| AUD-050 | Dependências | Abandonadas / sem uso | OK | Nenhuma; deps com justificativa | — |
| AUD-051 | Dependências | Licenciamento / conflitos | OK | composer validate --strict OK | — |
| AUD-052 | Docker | Multi-stage builds / imagem base | OK | Base dev+prod, cache de camadas | — |
| AUD-053 | Docker | Usuário / privileges | FALHA | Dockerfile.nginx roda como root | [F-603] |
| AUD-054 | Docker | Secrets / volumes / redes / portas | OK | Senhas por env; DB não exposto | — |
| AUD-055 | Docker | Repro..bilidade / tags / pacotes | FALHA | composer:latest; mongodb sem uso | [F-601]/[F-602] |
| AUD-056 | DevOps | CI/CD / healthcheck / logs | OK | CI+CD robustos; healthcheck OK | — |
| AUD-057 | DevOps | Paridade CI×prod | FALHA | CI PHP 8.3 vs prod 8.4 | [F-606] |
| AUD-058 | Docker | Imagem nginx sobe em produção | FALHA | `brotli_static` sem módulo | [F-607] |
| AUD-059 | Testes | Cobertura Vitest configurada? | FALHA | include aponta p/ `public/assets/js/` | [F-701] |
| AUD-060 | Testes | API tests determinísticos | FALHA | 404 tratado como falha (14 falsas) | [F-702] |
| AUD-061 | Testes | Cobertura PHP | FALHA | CI `--no-coverage`; sem xdebug/pcov | [F-703] |
| AUD-062 | Testes | Smoke/E2E portáveis no CI | FALHA | Smoke PowerShell; sem E2E/UI | [F-704]/[F-705] |
| AUD-063 | Testes | Integration fixtures | FALHA | DB real sem fixtures; parseJsonInput | [F-706]/[F-707] |
| AUD-064 | Documentação | API OpenAPI/Swagger | OK | `docs/openapi.yaml` criado (34 paths); rota `api/swagger-spec` mapeada | [F-801] |
| AUD-065 | Documentação | Variáveis de ambiente | FALHA | DB_PORT ignorado; MYSQL_ROOT_PASS errado | [F-802]/[F-806] |
| AUD-066 | Documentação | Versões e métricas | FALHA | PHP 8.2/8.3/8.4; testes desatualizados | [F-803]/[F-804] |
| AUD-067 | Documentação | Deploy descrito | FALHA | DEPLOY.md SSH ≠ cd.yml | [F-805] |
| AUD-068 | Documentação | Endpoints documentados | FALHA | 9 endpoints ausentes | [F-807] |

---

## Findings resumidos (Fase 1)

| ID | Tipo | Severidade | Status |
| -- | ---- | ---------- | ------ |
| F-001 | Erro de teste (3) em `Tests\Unit\LocaisServiceTest` — `LocaisService::create` lança `RuntimeException` com mock; `update` chama `bindValue()` em `false` | Alta (teste quebrado) | **RESOLVIDO** (2026-08-16) |
| F-002 | 14 falhas em `Tests\Api\*` — testes exigem servidor HTTP rodando; 404 no lugar de 401 | Esperado/Ambiental | Documentado |
| F-003 | PHPStan: 2 funções não encontradas em `inc/auto_migrate.php` | Média | **RESOLVIDO** (2026-08-16) — `database/` nos paths |
| F-004 | composer.lock fora de sincronia com composer.json | Baixa | **RESOLVIDO** (2026-08-16) |
| F-005 | CVE-2026-67434 — php_codesniffer 3.13.5 (dev dep) | Alta (dev only) | **RESOLVIDO** (2026-08-16) — 3.13.6, requisito `^3.13.6` |
| F-006 | Divergência caminho `.env` CI vs `getEnvPath()` | Baixa | Documentado |
| F-007 | Contagem de endpoints API: 34 (não 33) | Informativa | Documentado |
| F-101 | CWE-209 — vazamento de mensagem de exceção em `api_editar_camera.php:117` | Baixa | Corrigido |
| F-102 | CWE-384 — session fixation mitigada; validar caminho de boot único de `session_start` | Informativo | Não confirmado |
| F-103 | CWE-863 — supervisor isento de escopo de secretaria em equipamentos/anexos/alarmes | Baixa | Aberto (decisão de negócio) |
| F-104 | Recuperação de senha expõe senha temporária em sessão (fluxo intencional) | Informativo | Não confirmado |
| F-201 | CWE-200 — dashboard agrega dados globais sem filtro de secretaria, mas cacheia por secretaria | Média | Corrigido |
| F-202 | Escrita de câmeras/alarmes não invalida cache APCu de listagem/relatórios | Baixa | Corrigido |
| F-301 | event_scheduler OFF → flush de auditoria e limpeza inoperantes | Média | Corrigido |
| F-302 | Índice duplicado em `auditoria_eventos` (entidade, created_at) | Baixa | Corrigido |
| F-303 | Seed da tabela `migrations` divergente (100000 inexistente; 110000 não registrada) | Baixa | Corrigido |
| F-304 | Partições de `auditoria_eventos` sem manutenção futura | Baixa | Corrigido |
| F-401 | Duplicação entre `api_manutencao_cameras` e `api_manutencao_alarmes` | Média | Aberto (R-001) |
| F-402 | Código morto: `getInputPayload()` | Baixa | Corrigido |
| F-403 | Classes `src/` sem namespace (PSR-4) | Baixa | Aberto (R-004) |
| F-404 | Complexidade ciclomática alta (`extractCommonData` 65, `validateInput` 63) | Média | Aberto (R-003) |
| F-405 | `loadManutencaoPayload()` com 10 parâmetros | Baixa | Aberto (R-002) |
| F-406 | PSR-12: 226 erros + 187 warnings | Baixa | Corrigido |
| F-407 | README.md: tabela de migrations desatualizada | Baixa | Corrigido |
| F-501 | 5 vulns high em undici (via jsdom, testes) | Alta | Corrigido |
| F-502 | phpstan 1.x → 2.x (major) | Baixa | Aberto (postergado) |
| F-503 | phpunit 10 → 13 (major) | Baixa | Aberto (postergado) |
| F-504 | php_codesniffer 3 → 4 (major) | Baixa | Aberto (postergado) |
| F-505 | sentry 4.29 → 4.30 (patch) | Baixa | Aberto (próx. manutenção) |
| F-506 | npm: minors/majors disponíveis | Baixa | Aberto (npm update) |
| F-601 | `composer:latest` tag flutuante | Baixa | Corrigido |
| F-602 | Extensão mongodb sem uso (2 Dockerfiles) | Baixa | Corrigido |
| F-603 | Dockerfile.nginx roda como root (prod) | Média | Corrigido |
| F-604 | container_name fixo dev×prod | Baixa | Corrigido |
| F-605 | Healthcheck HTTP sem auth (smoke) | Baixa | Não confirmado |
| F-606 | CI PHP 8.3 vs produção 8.4 | Baixa | Corrigido |
| F-607 | nginx não subia (`brotli_static` sem módulo) | Alta | Corrigido |
| F-701 | Coverage Vitest aponta p/ diretório inexistente | Média | Corrigido |
| F-702 | API tests: 404 tratado como falha (14 falsas) | Média | Corrigido |
| F-703 | Sem cobertura PHP configurada | Baixa | Corrigido |
| F-704 | Smoke tests PowerShell (não rodam no CI) | Baixa | Aberto |
| F-705 | Sem testes E2E/UI/PWA | Média | Aberto |
| F-706 | Integration sem fixtures de teste | Baixa | Aberto |
| F-707 | `parseJsonInput` sem teste | Baixa | Corrigido |
| F-801 | openapi.yaml ausente → Swagger 404 | Média | Corrigido |
| F-802 | DB_PORT ignorado pelo DSN | Baixa | Corrigido |
| F-803 | Versão PHP divergente (8.2/8.3/8.4) | Baixa | Corrigido |
| F-804 | Números de testes desatualizados | Baixa | Corrigido |
| F-805 | DEPLOY descreve SSH inexistente | Média | Corrigido |
| F-806 | `MYSQL_ROOT_PASS` vs `DB_ROOT_PASSWORD` | Baixa | Corrigido |
| F-807 | 9 endpoints não documentados | Baixa | Corrigido |

---

## Métricas de Baseline (2026-08-16)

| Métrica | Valor |
| ------- | ----- |
| Total arquivos PHP | 147 (excl. vendor) |
| Total arquivos TS/JS/Vue | 142 (excl. node_modules/dist) |
| Testes PHPUnit | 172 (3 erros, 14 falhas, 18 skipped) |
| Testes Vitest | 88 (0 falhas) |
| Erros PHPStan (nível 5) | 2 |
| Vulnerabilidades composer | 1 (HIGH, dev only) |
| Endpoints API v2 | 34 |
| Commits | 120 |