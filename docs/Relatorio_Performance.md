# Relatório de Análise de Performance — sistema-cameras-v1.0

**Data:** 2026-07-11
**Escopo:** Banco de dados, caching, API endpoints, entrega de assets estáticos

---

## Sumário Executivo

Foram identificados **28 gargalos de performance** em 4 áreas (banco, cache, API, assets). O projeto já possui boas práticas (APCu em 7 locais, Service Worker, code splitting, prepared statements), mas há **bugs de caching ativos**, **cache ausente em endpoints críticos**, **índices SQL faltantes** e **assets não otimizados**.

---

## 1. Bug Crítico: `Cache-Control` API Sobrescrito

**Arquivo:** `src/Api/ApiResponse.php:327-329`

`bootstrap-api.php:176` define `Cache-Control: private, max-age=10` para 14 endpoints GET idempotentes (dashboard, cameras, alarmes, notificacoes, etc.). Porém `ApiResponse::send()` sempresobrescreve com `no-store, no-cache, must-revalidate, max-age=0`.

**Consequência:** O browser NUNCA cacheia respostas JSON da API. O dashboard faz poll a cada 30s e sempre re-baixa o JSON completo, mesmo com APCu ativo no servidor. ~50% de banda desperdiçada em polling.

**Fix:** `ApiResponse::send()` deve respeitar o `Cache-Control` já setado por `bootstrap-api.php` para GETs idempotentes.

---

## 2. Dashboard (`api/v2/api_dashboard.php`): 4 Problemas

### 2.1 TTL de Cache APCu: 10s (insuficiente)
**Arquivo:** `api/v2/api_dashboard.php:187`
**Status:** APCu já existe (`dashboard:{secretariaId}`, linha 14), mas TTL = 10 segundos. Em refresh storms (múltiplos usuários abrindo dashboard), a cada 10s são 9 queries sequenciais disparadas.

**Fix:** Aumentar TTL para 30-60s. Dados do dashboard aceitam até 1 minuto de stale.

### 2.2 Queries 3, 4, 9: Sem Filtro `deleted_at IS NULL`
**Arquivo:** `api/v2/api_dashboard.php:64-69, 76-82, 151-159`

As queries de agregação de alarmes (`central_alarmes`) contam registros soft-deletados. As queries equivalentes de equipamentos (`equipamentos`) usam `WHERE e.deleted_at IS NULL`. Inconsistência = bug de contagem + scan extra de registros deletados.

**Fix:** Adicionar `WHERE ca.deleted_at IS NULL` nas queries 3, 4, 9.

### 2.3 Query 7: Full Scan de `equipamentos_manutencoes`
**Arquivo:** `api/v2/api_dashboard.php:126-130`

A derived table faz `SELECT equipamento_id, MAX(data_hora) FROM equipamentos_manutencoes GROUP BY equipamento_id` — sem filtro de `os_status_id`. Para instalações com histórico grande, isso é um full table scan sobre TODAS as manutenções já registradas.

**Fix:** Adicionar `WHERE os_status_id IN (1,2)` dentro da derived table.

### 2.4 Queries Duplicadas (8 e 9)
**Arquivo:** `api/v2/api_dashboard.php:139-148, 151-159`

Query 8 (`GROUP BY status` de equipamentos) duplica os resultados da query 1 (`SUM CASE WHEN s.nome...`). Query 9 (`GROUP BY status` de alarmes) duplica query 3. São 2 round-trips desnecessários.

**Fix:** Eliminar queries 8 e 9; derivar dados do chart a partir das queries 1 e 3.

---

## 3. APCu Cache Ausente em Endpoints Críticos

| Endpoint | Queries / Peso | APCu? |
|----------|---------------|-------|
| `api_cameras.php` | 12 LEFT JOINs + COUNT + subquery | ❌ |
| `api_alarmes.php` | 3 LEFT JOINs + COUNT + FULLTEXT | ❌ |
| `api_relatorios_cameras.php` | 14 LEFT JOINs + COUNT | ❌ |
| `api_manutencao_alarmes.php` | 9 queries (GET) | ❌ |
| `api_modelos_cameras.php` / `api_modelos_alarmes.php` | lookup tables estáveis | ❌ |
| `api_status.php` (status lookup) | tabela de 1 coluna | ❌ |
| `getMaintenanceDefaultIds` (`api_manutencao_utils.php:28-37`) | 2 queries a cada GET | ❌ |

**Nota:** `api_manutencao_cameras.php` JÁ tem APCu 300s para a lista de equipamentos. O endpoint gêmeo (`api_manutencao_alarmes.php`) NÃO tem — inconsistência.

---

## 4. Índices SQL Faltantes (Full Table Scans)

### central_alarmes
| Coluna | Usada em | Índice? |
|--------|----------|---------|
| `ip` | `api_alarmes.php:88-100` (busca LIKE) | ❌ |
| `ip_dvr` | `api_alarmes.php:88` | ❌ |
| `mac` | `api_alarmes.php:101` | ❌ |
| `numero_sei` | `api_alarmes.php:108` | ❌ |
| `local`, `endereco` | fallback busca <3 chars | ❌ (só FULLTEXT, não usado nesse path) |
| ORDER BY `(data_atualizacao DESC, id DESC)` | paginação | Sem composto → filesort |

### equipamentos_manutencoes
| Coluna | Usada em | Índice? |
|--------|----------|---------|
| `local_servico` | `api_manutencao_cameras.php:89-93` (LIKE) | ❌ |
| `endereco_servico` | mesmo path | ❌ |
| `descricao`, `tecnico`, `pecas_previstas` | 11 condições OR LIKE | ❌ (só FULLTEXT composto) |
| `COALESCE(data_execucao, data_hora, created_at)` no ORDER BY | histórico | ❌ Sempre filesort |

### alarmes_manutencoes
| Coluna | Usada em | Índice? |
|--------|----------|---------|
| `(os_status_id, updated_at)` | ORDER BY em lista "executando" | ❌ (existe para cameras, não para alarmes) |

### equipamentos_anexos
| Coluna | Usada em | Índice? |
|--------|----------|---------|
| `created_at` | ORDER BY DESC em `api_listar_anexos.php:70` | ❌ |
| `created_by` | JOIN com usuarios | ❌ (FK definida mas sem índice próprio) |

### auditoria_eventos
| Coluna | Usada em | Índice? |
|--------|----------|---------|
| `(created_at DESC, id DESC)` | ORDER BY paginação de auditoria | ❌ |

---

## 5. `UPPER()` Derrota Índices UNIQUE (~20 locais)

**Arquivos:** `src/Services/LocaisService.php`, `src/Services/EquipamentoService.php`, `src/Services/AlarmeService.php`, `api/v2/api_manutencao_utils.php`

Padrão: `WHERE UPPER(nome) = UPPER(?)`. O collation do banco é `utf8mb4_unicode_ci` — JÁ case-insensitive. `UPPER()` é redundante E força table scan em colunas com índice UNIQUE.

**Fix:** Remover `UPPER()` de todas as queries; usar diretamente `WHERE nome = ?`.

---

## 6. COUNT com JOINs Desnecessários em Relatórios

**Arquivo:** `api/v2/api_relatorios_cameras.php:80-81`

O COUNT usa 14 LEFT JOINs idênticos ao SELECT de dados. `api_cameras.php` já resolveu isso com `$countBaseFrom` (JOINs trimados), mas o relatório não foi atualizado — copiou o pattern pesado sem trimming.

**Fix:** Aplicar o padrão `$countBaseFrom` de `api_cameras.php:135-139` no relatório.

---

## 7. Padrões N+1 Escondidos

### 7.1 `verbificarEscopoAnexo` — 3 queries por anexo
**Arquivo:** `inc/check_access.php:96-142`

Cada download/foto (`api_servir_anexo.php`) e exclusão de anexo dispara:
1. SELECT do anexo
2. `getAnexoLocalId()` → 1 query com COALESCE de 4 subqueries correlacionadas
3. `verbificarEscopoLocal()` → 1 SELECT em `locais`

Para uma galeria com 10 thumbnails = **30 queries**.

### 7.2 `api_notificacoes`: `gerarPendencias` a cada GET
**Arquivo:** `api/v2/api_notificacoes.php → NotificacaoService::gerarPendencias()`

A cada GET, executa 2 queries pesadas (equipamentos overdue + alarmes overdue) + batch existence check — mesmo sem nenhuma notificação nova.

### 7.3 `verbificarEscopoEquipamento` redundante
**Arquivos:** `api/v2/api_excluir_camera.php:20+25`, `api/v2/api_upload_anexo.php:33+verificar`, `api/v2/api_manutencao_cameras.php:363+370`

Vários endpoints SELECTam `secretaria_id` e depois chamam `verbificarEscopoEquipamento` que re-SELECTa o mesmo dado.

---

## 8. Sem Suporte a ETag / 304 Not Modified

**Escopo:** Toda a codebase

Zero suporte a `ETag`, `Last-Modified`, `If-None-Match`, `304 Not Modified`. Mesmo `api_servir_anexo.php`, que serve arquivos imutáveis por ID e já envia `Cache-Control: private, max-age=3600`, não envia ETag. Cada re-view de anexo re-baixa o BLOB completo.

---

## 9. Assets Estáticos: Gargalos Identificados

| Item | Status | Impacto |
|------|--------|---------|
| `.gz` precomprimidos gerados pelo Vite | ✅ Gerados, ❌ não servidos | `mod_deflate` recompime on-the-fly cada request |
| Brotli (`vite-plugin-compression`) | ❌ Ausente | 15-20% menor que gzip |
| `Vary: Accept-Encoding` no `.htaccess` | ❌ Ausente | Bug para proxies/CDN |
| `Cache-Control: immutable` para assets com hash | ❌ Ausente | Eliminaria revalidação condicional em Firefox/Chrome |
| Chart.js isolado em chunk `vendor-chart` | ❌ (inline no `home-dashboard` 213KB) | Mudança em código do dashboard invalida o bundle inteiro de Chart.js |
| `<link rel="modulepreload">` para entry chunks | ❌ Ausente | Download serial em vez de paralelo |
| Font Awesome + Bootstrap via CDN (render-blocking) | ⚠️ CDN externo | Bloqueia render; dependência externa |

---

## 10. Service Worker + OPcache

| Item | Status |
|------|--------|
| Service Worker (`public/sw.js`) | ✅ Bem implementado: cache-first para assets com hash, network-only para API |
| OPcache | ❌ Não instrumentado — sem `opcache_get_status()`, sem verificação |

---

## Plano de Ação (Ranqueado por Impacto × Facilidade)

### Tier 1 — Quick Wins (trivial, alto impacto)

| # | Otimização | Arquivo | Esforço |
|---|-----------|---------|---------|
| 1 | Fix `ApiResponse::send()` — não sobrescrever Cache-Control | `src/Api/ApiResponse.php:327` | 3 linhas |
| 2 | Dashboard APCu TTL 10s → 60s | `api/v2/api_dashboard.php:187` | 1 número |
| 3 | Dashboard: `WHERE deleted_at IS NULL` nas queries 3,4,9 | `api/v2/api_dashboard.php:64,76,151` | 3 linhas |
| 4 | Dashboard query 7: filtrar `os_status_id IN (1,2)` | `api/v2/api_dashboard.php:127-130` | 1 linha |
| 5 | Cache `status_options` + `procedimento_options` em APCu | `api_manutencao_cameras.php:73,76` + alarmes | ~10 linhas |
| 6 | Cache `getMaintenanceDefaultIds` em APCu 300s | `api/v2/api_manutencao_utils.php:28-37` | ~10 linhas |
| 7 | `.htaccess`: `Vary: Accept-Encoding` + `immutable` | `public/.htaccess` | 4 linhas |
| 8 | `.htaccess`: RewriteCond para servir `.gz` | `public/.htaccess` | 6 linhas |
| 9 | Remover `UPPER()` de queries | ~20 locais | replaceAll |
| 10 | Add ETag em `api_servir_anexo.php` | `api/v2/api_servir_anexo.php` | ~15 linhas |

### Tier 2 — Esforço médio, alto impacto

| # | Otimização | Arquivo | Esforço |
|---|-----------|---------|---------|
| 11 | Dashboard: eliminar queries 8 e 9 (duplicam 1 e 3) | `api/v2/api_dashboard.php` | ~30 linhas |
| 12 | Trimmar JOINs do COUNT em `api_relatorios_cameras.php` | `api/v2/api_relatorios_cameras.php` | ~15 linhas |
| 13 | APCu em `api_cameras.php` (30s por hash de filtros) | `api/v2/api_cameras.php` | ~30 linhas |
| 14 | APCu em `api_alarmes.php` (30s por hash de filtros) | `api/v2/api_alarmes.php` | ~30 linhas |
| 15 | APCu em `api_manutencao_alarmes.php` (espelhar gêmeo) | `api/v2/api_manutencao_alarmes.php` | ~20 linhas |
| 16 | APCu em `api_relatorios_cameras.php` (60s) | `api/v2/api_relatorios_cameras.php` | ~30 linhas |
| 17 | Chart.js → `vendor-chart` via `manualChunks` | `vite.config.ts` | 2 linhas |
| 18 | `<link rel="modulepreload">` para entry chunks | `inc/header.php` + `inc/assets.php` | ~15 linhas |

### Tier 3 — Maior esforço

| # | Otimização | Arquivo | Esforço |
|---|-----------|---------|---------|
| 19 | Índices SQL faltantes (8 índices) | migration SQL | ~30 linhas |
| 20 | `api_status`/`api_modelos` usar LookupService | endpoints | ~10 linhas cada |
| 21 | Brotli precompressão Vite | `vite.config.ts` | 3 linhas |
| 22 | Self-host Font Awesome + Bootstrap | `inc/header.php` + Vite | ~30 linhas |
| 23 | Pre-compute dashboard stats (tabela/vista materializada) | migration + trigger | ~60 linhas |
| 24 | Redis session handler | `config/app.php` | ~10 linhas (se Redis disponível) |
| 25 | Cache invalidation hooks para `LookupService` | admin CRUD endpoints | ~15 linhas |

---

## Métricas do Sistema Atual (referência)

- **27 tabelas** no banco (`cftv_gml.sql`)
- **~220 queries SQL** mapeadas nos Services e API endpoints
- **APCu:** 7 locais com cache (dashboard 10s, CEP 24h, manut_cameras 300s, lookup 300s, notificacoes 5s, geocode throttle, session)
- **Endpoints GET sem cache APCu:** 11 de 18
- **Endpoints GET sem cache browser:** 100% (bug `ApiResponse::send`)
- **Assets:** 52 JS + 13 CSS entries, gzip precompressed (não servido), content-hashed
- **Service Worker:** cache-first para assets com hash, network-only para API
- **Testes:** 170 pass (20 skipped, 1 incomplete — pre-existing)
- **PHPStan:** 0 errors (baseline deletada)