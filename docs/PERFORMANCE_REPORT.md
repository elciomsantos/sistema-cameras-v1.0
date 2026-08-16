# PERFORMANCE_REPORT.md

Auditoria de Performance — Projeto "Controle Share Videos" (Sistema de Gerenciamento de Câmeras e Alarmes v1.0).
Data: 2026-08-16. Método: revisão estática (algoritmos, queries, cache, paginação, concorrência, I/O) sem ambiente de runtime (MySQL/APCu indisponíveis localmente — medições com métricas antes/depois classificadas como **Bloqueado**).

## Metodologia

- Revisão das queries de maior custo: `api/v2/api_dashboard.php`, `api/v2/api_cameras.php`, `api/v2/api_alarmes.php`, `api/v2/api_relatorios_cameras.php`, `api/v2/api_auditoria_cameras.php`, `api/v2/api_manutencao_cameras.php`, `src/Services/NotificacaoService.php`, `src/Services/LookupService.php`.
- Verificação de: paginação, COUNT separado do SELECT, índices (schema `config/DB/cftv_gml.sql`), cache APCu (chave, TTL, invalidação), N+1, loops, serialização JSON, monitoramento de query lenta.
- Sem banco em execução: medições empíricas (EXPLAIN, tempo de query) **não realizadas**.

## Resumo Executivo

Performance geral **boa por construção**: todos os endpoints de listagem são paginados com COUNT separado, os índices do schema cobrem os padrões de acesso, e há cache APCu em camadas com TTLs curtos. Não há N+1 real nos services (lotes/joins). Foram encontrados **2 achados** (1 médio — escopo+cache do dashboard; 1 baixo — falta de invalidação de cache em escrita).

## Achados

### F-201 — Dashboard calcula estatísticas globais, mas cacheia por secretaria (query sem filtro + cache redundante)

| Campo | Valor |
| ----- | ----- |
| Local | `api/v2/api_dashboard.php:10-44` |
| Evidência | `$secretariaId = (int)($currentUser->secretaria_id ?? 0); $dashboardCacheKey = "dashboard:{$secretariaId}";` — porém **nenhuma** das 6 queries tem `WHERE ... secretaria_id = :secretaria_id` |
| Causa raiz | As queries agregam `equipamentos`/`central_alarmes` sem filtro por secretaria; a secretaria só entra na chave do cache. Resultado: usuário `user` de uma secretaria recebe estatísticas de **todas** as secretarias, e todos os usuários compartilham o mesmo conjunto de dados em chaves de cache distintas. |
| Risco | Médio — vazamento de escopo de dados (reforça F-103) e cache inútil (múltiplas chaves com o mesmo payload global). |
| Classificação | **Confirmado** |
| Severidade | Média |
| Recomendação | Aplicar escopo por secretaria nas queries quando o usuário não for admin (`resolveUserAccessLevel === 'user'`), de forma consistente com `api_cameras.php:43-46` e `api_alarmes.php:44-47`; e alinhar a chave de cache ao escopo real. |

### F-202 — Endpoints de escrita de câmeras/alarmes não invalidam o cache APCu de listagem/relatórios

| Campo | Valor |
| ----- | ----- |
| Local | `api/v2/api_cadastrar_cameras.php`, `api/v2/api_editar_camera.php`, `api/v2/api_excluir_camera.php`, `api/v2/api_cadastrar_alarmes.php`, `api/v2/api_editar_alarme.php`, `api/v2/api_excluir_alarme.php` |
| Evidência | Nenhum desses arquivos chama `apcu_delete`. O cache `cameras:*`/`alarmes:*` tem TTL 30s e `rel_cameras:*` 60s (`api_cameras.php:237`, `api_alarmes.php:219`, `api_relatorios_cameras.php:178`). |
| Causa raiz | O cache de leitura não é invalidado após escritas; a consistência depende apenas do TTL curto. Inconsistente com `api_manutencao_cameras.php:426,470,597` que invalida `manut_cameras_list` explicitamente. |
| Risco | Baixo — usuário pode ver dados desatualizados por até 30-60s após cadastrar/editar/excluir câmera ou alarme. |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Invalidar as chaves afetadas em escrita (padrão já usado em manutenção) ou reduzir TTL para escritas frequentes. |

## Verificações detalhadas

### Queries / N+1
- Listagens (`api_cameras.php`, `api_alarmes.php`, relatórios, auditoria): **paginadas** com `COUNT(*)` separado do `SELECT` (bom — o COUNT usa `countBaseFrom` com menos joins: `api_cameras.php:152-156`).
- `NotificacaoService::gerarPendencias` usa **batch EXISTS** e **INSERT em lotes de 50** (sem N+1).
- `LookupService::getTableData` cacheia por tabela (300s).
- Subquery agregada `em` (manutenção em aberto) em `api_cameras.php:143-148` — agrupada por equipamento; coberta por `idx_eq_manut_os_status_equip`.
- **N+1: não identificado** em services/views.

### Algoritmos / loops
- Nenhuma recursão em produção.
- Loops são iterações de resultados de query (bounded por paginação/LIMIT).
- `buildResumoMudancas` faz `json_decode` por linha na auditoria (página de 100 max) — custo linear aceitável.

### Cache
- `lookup_*`: TTL 300s (dados de referência, baixa volatilidade) — **OK**.
- `cameras:*` / `alarmes:*`: TTL 30s — **OK**, mas sem invalidação em escrita (F-202).
- `rel_cameras:*`: TTL 60s — **OK**, sem invalidação (F-202).
- `dashboard:*`: TTL 60s — **F-201** (cache por secretaria com dados globais).
- `notif_count:*`: TTL 5s com invalidação em `marcarLida`/`marcarTodasLidas` — **OK**.
- APCu habilitado no Docker (`docker/Dockerfile.nginx:36,68`) e OPcache (32M shm, `revalidate_freq=2`).
- APCu **não instalado** no ambiente local — todos os `function_exists('apcu_*')` fazem fallback para DB; testes locais não exercitam cache.

### Concorrência
- RateLimiter com janela deslizante e storage em DB/arquivo (não há corrida por sessão).
- `sequencia_codigo_publico` para geração de código público — sem hot path em leitura.
- Sessão única: `user_sessions` com PK por usuário — escrita pontual por login.

### I/O / Network
- Geocode/CEP: `api_geocode.php`/`api_cep_lookup.php` têm cache (`geocode_cache`, storage/cep_cache) + timeouts.
- Upload: 10MB com leitura para buffer — aceitável.

### Monitoramento
- Slow-query log apenas em `api_alarmes.php:203-206` (>1s). Ausente nos demais endpoints — **recomendação**: padronizar (informativo).

## Matriz de Performance

| ID | Área | Severidade | Status | Observação |
| -- | ---- | ---------- | ------ | ---------- |
| F-201 | Dashboard: query global + cache por secretaria | Média | Confirmado | Escopo por secretaria + alinhar chave de cache |
| F-202 | Escrita não invalida cache de listagem | Baixa | Confirmado | Padronizar invalidação (como em manutenção) |
| — | Medições empíricas (EXPLAIN, tempo) | — | Bloqueado | Sem MySQL/APCu local; requer runtime com dados de produção |

## Conclusão

O sistema não apresenta gargalos estruturais evidentes: paginação universal, COUNT separado, índices cobrindo os padrões de acesso e cache em camadas. Os dois achados confirmados têm correção de baixo esforço. Recomenda-se que a correção de F-201 seja coordenada com a decisão de escopo do supervisor (F-103), pois ambos dependem da mesma regra de negócio.