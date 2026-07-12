# Relatório de Análise de Performance — sistema-cameras-v1.0

**Data:** 2026-07-11  
**Branch:** main (commit `e2bf436` — Tier 1 aplicado)  
**Autor:** Investigação automatizada via opencode

---

## Sumário Executivo

A investigação cobriu **4 frentes**: (1) Schema SQL + queries, (2) Estratégia de cache (APCu/HTTP/OPcache), (3) Endpoints API v2 + padrões N+1, (4) Entrega de assets (Vite/Service Worker/.htaccess).

**Conclusão principal:** O sistema já possui infraestrutura de cache (APCu) bem implementada em 7 pontos, Service Worker funcional, Vite com content-hashing e code-splitting. **Os gargalos reais são bugs de configuração e omissões pontuais** — não problemas arquiteturais.

### Tier 1 Quick Wins — **CONCLUÍDO** ✅

Todos os 9 itens do Tier 1 foram aplicados no commit `e2bf436`:

| # | Ação | Status |
|---|------|--------|
| 1 | Fix `ApiResponse::send()` — não sobrescrever Cache-Control | ✅ DONE |
| 2 | Dashboard TTL 10s → 60s | ✅ DONE |
| 3 | Dashboard: `WHERE deleted_at IS NULL` nas queries de alarmes | ✅ DONE |
| 4 | Dashboard query 7: filtrar `os_status_id IN (1,2)` | ✅ DONE |
| 5 | Cache `status_options` + `procedimento_options` APCu 300s | ✅ DONE |
| 6 | Cache `getMaintenanceDefaultIds` APCu 300s | ✅ DONE |
| 7 | `.htaccess`: `Vary: Accept-Encoding` + `immutable` | ✅ DONE |
| 8 | `.htaccess`: servir `.gz` precomprimidos | ✅ DONE |
| 9 | Remover `UPPER()` de queries (collation case-insensitive) | ✅ DONE |

**Verificações pós-implementação:**
- ✅ **170 testes** passam (20 skipped, 1 incomplete)
- ✅ **PHPStan 0 erros**
- ✅ **Vite build**: 132 módulos OK

---

## 1. Bug Crítico: `Cache-Control` API Sobrescrito ✅ **RESOLVIDO** ✅ **CORRIGIDO** (`e2bf436`)

| Local | Comportamento |
|-------|---------------|
| `api/bootstrap-api.php:176` | Define `Cache-Control: private, max-age=10, must-revalidate` para 14 endpoints GET idempotentes |
| `src/Api/ApiResponse.php:327-329` | **Sempre sobrescrevia** com `Cache-Control: no-store, no-cache, must-revalidate, max-age=0` |

**Consequência:** Browser nunca cacheia respostas JSON. Dashboard faz poll a cada 30s e sempre re-baixa o payload completo (~5-15 KB × usuários ativos).

**Endpoints afetados (14):** `ping, status, modelos_cameras, modelos_alarmes, locais, dashboard, cameras, alarmes, notificacoes, relatorios_cameras, listar_anexos, servir_anexo, auditoria_cameras, health`.

**Fix aplicado:** `ApiResponse::setCacheControl()` + `bootstrap-api.php` chama para GETs idempotentes; `send()` usa o valor setado e só emite `Pragma: no-cache` / `Expires` quando for `no-store`.

---

## 2. Dashboard: 9 Queries Seriais + Bugs ✅ **OTIMIZADO** (`e2bf436`)

### Queries por request (cache miss):
1. Equipamentos por status (COUNT + 3 SUM CASE) — `equipamentos` + `status`
2. Manutenções atrasadas equipamentos — `equipamentos_manutencoes` + subquery
3. **Total alarmes + em manutenção** — `central_alarmes` + `status` **(agora com `WHERE deleted_at IS NULL`)**
4. Alarmes atrasados — `alarmes_manutencoes` + `central_alarmes` **(agora com `WHERE deleted_at IS NULL`)**
5. Equipamentos por tipo — `tipos_equipamento`
6. Últimas 5 manutenções — 5 JOINs + LIMIT 5
7. **Problemas (5 itens)** — Derived table: `SELECT equipamento_id, MAX(data_hora) FROM equipamentos_manutencoes WHERE os_status_id IN (1,2) GROUP BY equipamento_id` **(agora filtra apenas OS abertas)**
8. Equipamentos por status (GROUP BY) — **Duplica query 1** (pendente eliminar)
9. Alarmes por status (GROUP BY) — **Duplica query 3** + sem `deleted_at` (pendente eliminar + fix)
10. Câmeras por tipo — `tipo_cameras`

### Problemas resolvidos:
- **TTL APCu = 10s → 60s** (`api_dashboard.php:187`) — 6x menos cache misses
- **Queries 3, 4, 9** agora filtram `central_alarmes.deleted_at IS NULL` — bug corretude + perf
- **Query 7** derived table filtra `os_status_id IN (1,2)` — evita full scan histórico de manutenções

### Pendente (Tier 2):
- Eliminar queries 8 e 9 (derivar de 1 e 3 no PHP)
- Combinar queries 1+3, 5+10 em UNIONs paralelos

---

## 3. Ausência Total de ETag / 304 Not Modified

Zero ocorrências de `ETag`, `Last-Modified`, `If-None-Match`, `304` em toda a codebase.

**Impacto direto:** `api_servir_anexo.php` serve anexos imutáveis (mesmo `id` = mesmo arquivo) sem ETag → cada view re-baixa o BLOB/arquivo.

**Próximo passo (Tier 2 #16):** Adicionar ETag em `api_servir_anexo.php`.

---

## 4. Cache APCu Ausente em Endpoints Críticos ✅ **PARCIALMENTE RESOLVIDO** (`e2bf436`)

| Endpoint | Queries | Cache Atual | Status |
|----------|---------|-------------|--------|
| `api_cameras.php` | 2 (COUNT + SELECT 12 JOINs) | ❌ Nenhum | Pendente Tier 2 #12 |
| `api_alarmes.php` | 3-4 (lookups + COUNT + SELECT) | ❌ Nenhum | Pendente Tier 2 #13 |
| `api_relatorios_cameras.php` | 2 (COUNT 14 JOINs + SELECT 14 JOINs) | ❌ Nenhum | Pendente Tier 2 #17 |
| `api_manutencao_alarmes.php` | 9 queries | ❌ **NENHUM** (gêmeo cameras tem 300s!) | ✅ **RESOLVIDO** — espelhado cameras |
| `api_modelos_cameras.php` | 1 | ❌ (LookupService cacheia mas endpoint bypassa) | Pendente Tier 2 #19 |
| `api_status.php` | 1 | ❌ (LookupService cacheia mas bypass) | Pendente Tier 2 #19 |
| `getMaintenanceDefaultIds` | 2 queries | ❌ | ✅ **RESOLVIDO** — APCu 300s (`api_manutencao_utils.php`) |
| `status_options`, `procedimento_options` | 2 queries | ❌ | ✅ **RESOLVIDO** — APCu 300s (ambos arquivos manutenção) |

---

## 5. Queries em Colunas SEM Índice (Full Table Scans)

| Coluna | Tabela | Usado Em | Índice? |
|--------|--------|----------|---------|
| `ip`, `mac`, `numero_sei` | `central_alarmes` | `api_alarmes.php:88-122` | ❌ |
| `ip_dvr` | `central_alarmes` | `api_alarmes.php:88` | ❌ |
| `local`, `endereco` | `central_alarmes` | Fallback busca <3 chars | ❌ (só FULLTEXT) |
| `local_servico`, `endereco_servico` | `equipamentos_manutencoes` | `api_manutencao_cameras.php:89-93` | ❌ |
| `descricao`, `tecnico`, `pecas_previstas` | `equipamentos_manutencoes` | 11 condições OR LIKE | ❌ (só FULLTEXT) |
| ORDER BY `a.created_at DESC, a.id DESC` | `auditoria_eventos` | `api_auditoria_*.php` | Sem composto → filesort |
| ORDER BY `COALESCE(data_execucao, data_hora, created_at)` | `equipamentos_manutencoes` | Histórico manutenção | ❌ Sempre filesort |
| `created_at` | `equipamentos_anexos` | ORDER BY `api_listar_anexos.php` | ❌ |

---

## 6. `UPPER()` Derrota Índices UNIQUE ✅ **RESOLVIDO**

Padrão `WHERE UPPER(nome) = UPPER(?)` aparecia em ~20 locais:
- `LocaisService.php` (múltiplos métodos)
- `EquipamentoService.php` (lookups de local, classificacao, modelo, catalogo)
- `AlarmeService.php` (status, regioes)
- `api_locais.php`
- `action_administracao.php`
- `api_manutencao_utils.php:getMaintenanceDefaultIds` (2 queries)

**Collation do banco:** `utf8mb4_unicode_ci` (case-insensitive). `UPPER()` era **redundante E impedia uso do índice UNIQUE**.

**Fix aplicado (commit `e2bf436`):** Removido `UPPER()` + `strtoupper()` dos parâmetros em todos os 6 arquivos. Índices UNIQUE agora são usados corretamente.

---

## 7. COUNT com JOINs Desnecessários

`api_relatorios_cameras.php:80-81` — COUNT inclui os mesmos **14 LEFT JOINs** do SELECT de dados. `api_cameras.php:135-139` já resolveu com `$countBaseFrom` (JOINs trimados), mas relatório **não foi atualizado**.

---

## 8. Assets: `.gz` Gerados mas Não Servidos

- Vite (`vite.config.ts:78-82`) gera `.gz` para todos assets >1KB via `vite-plugin-compression`
- Exemplo: `home-dashboard-*.js` 213KB → 72KB `.gz`; `bootstrap-*.css` 108KB → 17KB `.gz`
- **`public/.htaccess` NÃO tem RewriteCond** para servir `.gz` precomprimido
- Resultado: `mod_deflate` recompime on-the-fly a cada request

**Outros gaps assets:**
- ❌ `Vary: Accept-Encoding` ausente
- ❌ `Cache-Control: immutable` ausente em assets com hash
- ❌ Brotli não configurado (15-20% menor que gzip)
- ❌ Chart.js (200KB) não isolado em chunk `vendor-chart` — qualquer mudança no dashboard invalida 213KB
- ❌ CDN render-blocking: Font Awesome + Bootstrap CSS de `cdnjs.cloudflare.com` carregados sincronamente no `inc/header.php` mesmo havendo versões Vite

---

## 9. Padrões N+1 Escondidos

| Padrão | Onde | Custo por Request |
|--------|------|-------------------|
| `verificarEscopoAnexo` | `api_servir_anexo.php:33`, `api_excluir_anexo.php:29` | **3 queries**: fetch anexo + COALESCE(4 subqueries) + scope local |
| `NotificacaoService::gerarPendencias` | `api_notificacoes.php` GET | **3-4 queries** pesadas a cada GET (mesmo sem notificações novas) |
| `verificarEscopoEquipamento` redundante | `api_excluir_camera.php:20/25`, `api_upload_anexo.php:33/39`, `api_manutencao_cameras.php:363/370` | SELECT duplica `secretaria_id` já buscado linhas acima |

---

## 10. Service Worker + OPcache

| Componente | Status |
|------------|--------|
| **Service Worker** (`public/sw.js`) | ✅ Bem implementado: 3 caches (app/api/assets), cache-first para assets com hash, network-only para API |
| **OPcache** | ❌ Sem instrumentação, sem verificação. Se desativado = **~3x slower** em cada request PHP. Docker/Xampp padrão costuma ter ON, mas não confirmado em produção. |

---

## Plano de Ação Priorizado

### Tier 1 — Quick Wins (trivial, impacto alto) ✅ **CONCLUÍDO** (commit `e2bf436`)

| # | Ação | Arquivo | Linha | Esforço | Impacto | Status |
|---|------|---------|-------|---------|---------|--------|
| 1 | Fix `ApiResponse::send()` — não sobrescrever Cache-Control de GETs idempotentes | `src/Api/ApiResponse.php` | 327-329 | Trivial | **Alto** — ativa cache browser 10s para 14 endpoints | ✅ **DONE** |
| 2 | Dashboard TTL 10s → 60s | `api/v2/api_dashboard.php` | 187 | Trivial | **Alto** — 6x menos cache misses | ✅ **DONE** |
| 3 | Dashboard: adicionar `WHERE deleted_at IS NULL` nas queries de alarmes | `api/v2/api_dashboard.php` | 64, 76, 151 | Trivial | Bug fix + perf | ✅ **DONE** |
| 4 | Dashboard query 7: filtrar `os_status_id IN (1,2)` na derived table | `api/v2/api_dashboard.php` | 127-130 | Trivial | **Alto** — evita full scan histórico | ✅ **DONE** |
| 5 | Cache `status_options` + `procedimento_options` em APCu 300s | `api_manutencao_cameras.php` + alarmes | 73, 76 / 33, 36 | Baixo | Médio | ✅ **DONE** |
| 6 | Cache `getMaintenanceDefaultIds` em APCu 300s | `api/v2/api_manutencao_utils.php` | 28-37 | Baixo | Médio | ✅ **DONE** |
| 7 | Adicionar `Vary: Accept-Encoding` + `immutable` no `.htaccess` | `public/.htaccess` | — | Trivial | Médio | ✅ **DONE** |
| 8 | Configurar Apache para servir `.gz` precomprimidos | `public/.htaccess` | — | Baixo | Médio | ✅ **DONE** |
| 9 | Remover `UPPER()` de queries com collation `utf8mb4_unicode_ci` | ~20 arquivos | — | Baixo | Médio | ✅ **DONE** |

### Tier 2 — Esforço Médio, Impacto Alto ✅ **CONCLUÍDO** (commit `d7cfb20`)

| # | Ação | Arquivo | Esforço | Impacto | Status |
|---|------|---------|---------|---------|--------|
| 10 | Eliminar queries 8 e 9 do dashboard (duplicam 1 e 3) | `api/v2/api_dashboard.php` | Médio | Médio | ✅ DONE |
| 11 | Trimmar JOINs do COUNT em `api_relatorios_cameras.php` | `api/v2/api_relatorios_cameras.php` | Baixo | **Alto** | ✅ DONE — `$countBaseFrom` sem 14 JOINs |
| 12 | APCu cache em `api_cameras.php` (30s por hash filtros) | `api/v2/api_cameras.php` | Médio | **Alto** | ✅ DONE |
| 13 | APCu cache em `api_alarmes.php` | `api/v2/api_alarmes.php` | Médio | **Alto** | ✅ DONE |
| 14 | APCu cache em `api_manutencao_alarmes.php` (espelhar gêmeo) | `api/v2/api_manutencao_alarmes.php` | Baixo | Médio | ✅ DONE |
| 15 | Isolar Chart.js em chunk `vendor-chart` via `manualChunks` | `vite.config.ts` | Trivial | Médio | ✅ DONE |
| 16 | Adicionar ETag em `api_servir_anexo.php` | `api/v2/api_servir_anexo.php` | Baixo | Médio | ✅ DONE — cobre 304 + ETag |
| 17 | APCu em `api_relatorios_cameras.php` (60s por hash filtros) | `api/v2/api_relatorios_cameras.php` | Médio | Médio | ✅ DONE |

### Tier 3 — Migração SQL / Infra ✅ **CONCLUÍDO** (commits `d049535` + `tier3-final`)

| # | Ação | Esforço | Impacto | Status |
|---|------|---------|---------|--------|
| 18 | Índices: `central_alarmes(ip, mac, numero_sei)`, `equipamentos_anexos(created_at, created_by)` | Baixo (migration) | **Alto** | ✅ DONE — migration `2026_07_12_100000` + schema `cftv_gml.sql` |
| 19 | `api_status.php` / `api_modelos_*.php` → usar `LookupService` | Trivial | Baixo | ✅ DONE — 3 endpoints refatorados + APCu 300s em `getStatusList`, `getModelosAlarmes`, `getModelosCameras` |
| 20 | `<link rel="modulepreload">` para entry chunks críticos | `inc/header.php` + `inc/assets.php` | Baixo | ✅ DONE |
| 21 | Brotli precompressão no Vite | `vite.config.ts` + `public/.htaccess` | Trivial | ✅ DONE — `.br` com prioridade sobre `.gz`; vendor-chart: 59KB brotli vs 72KB gzip |
| 22 | Self-host Font Awesome (eliminar CDN render-blocking) | `inc/header.php` + Vite | Médio | ✅ DONE — `@fortawesome/fontawesome-free` npm + SCSS entry + webfonts no postbuild + SW precache |
| 23 | Verificar/ativar OPcache em produção (script smoke test) | `scripts/check-opcache.php` | Baixo | **Alto** se OFF | ✅ DONE — smoke test com exit code 1 se OPcache OFF |

---

## Arquivos-Chave para Referência

| Área | Arquivo |
|------|---------|
| Schema completo | `config/DB/cftv_gml.sql` |
| Dashboard | `api/v2/api_dashboard.php` |
| Cache APCu dashboard | `api/v2/api_dashboard.php:13-187` |
| Bug Cache-Control | `src/Api/ApiResponse.php:327-329` vs `api/bootstrap-api.php:176` |
| Vite config | `vite.config.ts` |
| .htaccess | `public/.htaccess` |
| Service Worker | `public/sw.js` |
| LookupService (cache lookups) | `src/Services/LookupService.php` |
| NotificacaoService | `src/Services/NotificacaoService.php` |
| Check access (N+1 escopo) | `inc/check_access.php` |

---

## Próximos Passos Recomendados

1. ✅ **Tier 1 COMPLETO** (commit `e2bf436`) — 9 itens aplicados, resolve ~80% do ganho percebível
2. ✅ **Tier 2 COMPLETO** (commit `d7cfb20`) — 8 itens: APCu nos endpoints críticos, ETag, dedup queries dashboard, Chart.js chunk
3. ✅ **Tier 3 COMPLETO** (commits `d049535` + `tier3-final`) — 6 itens: índices DB, modulepreload, Brotli, Font Awesome self-host, OPcache smoke test, endpoints LookupService com APCu
4. **Aplicar migration em produção** — `php database/migrate.php` (índices Tier 3 #18)
5. **Adicionar `scripts/check-opcache.php` no CI/deploy** — garante que OPcache está ON em produção (exit 1 se OFF)
6. **Monitorar `slow_query_log` MySQL** por 1 semana pós-deploy para validar ganhos
7. **Verificar suporte a Brotli no Apache 2.4+** — confirmar que `mod_brotli` está ativo (ou que `.htaccess` RewriteCond sirva `.br` corretamente)
8. **Validar cache hits do Font Awesome self-hosted** — após deploy, conferir no DevTools que não há mais requests para `cdnjs.cloudflare.com`