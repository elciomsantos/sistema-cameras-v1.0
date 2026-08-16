# DATABASE_REPORT.md

Auditoria de Banco de Dados — Projeto "Controle Share Videos" (Sistema de Gerenciamento de Câmeras e Alarmes v1.0).
Data: 2026-08-16. Método: revisão estática do schema (`config/DB/cftv_gml.sql`), gerenciador de migrações (`database/runner.php`, `inc/auto_migrate.php`), queries e configuração de infraestrutura (Docker/MySQL 8.0).

## Metodologia

- Análise das 41 tabelas, 20 triggers, 6 views e stored procedures/events no `config/DB/cftv_gml.sql`.
- Verificação de: modelagem/normalização, PK/FK/constraints, índices (redundantes/duplicados/ausentes), particionamento, integridade referencial, migrações, concorrência.
- Sem instância MySQL em execução: verificações de runtime (EXPLAIN, `SHOW INDEX`, testes de constraint) classificadas como **Bloqueado**.

## Resumo Executivo

**Status: F-301 a F-304 corrigidos em 2026-08-16.**

Modelagem **sólida e bem normalizada**, com integridade referencial completa (FKs com `ON DELETE` explícito), soft delete consistente, CHECKs de domínio, índice UNIQUE com coluna virtual para contornar NULL em MariaDB/MySQL, triggers de auditoria e de anti-duplicidade, e tabela de auditoria particionada por mês com buffer + eventos de flush.

Foram identificados **4 achados confirmados** (1 de severidade média, 3 baixas), todos de baixo esforço de correção.

## Achados

### F-301 — `event_scheduler` nunca habilitado: flush de auditoria e limpeza ficam inoperantes

| Campo | Valor |
| ----- | ----- |
| Local | `config/DB/cftv_gml.sql:857-860` (CREATE EVENT `evt_flush_auditoria_buffer`), `:1493-1507` (`ev_limpeza_auditoria_mensal`, `ev_limpeza_login_attempts`) |
| Evidência | Os 3 eventos agendados dependem de `SET GLOBAL event_scheduler = ON;` (comentado em `cftv_gml.sql:1509-1510`). O `docker-compose.yml` e o `docker-compose.prod.yml` usam `mysql:8.0` **sem** `command: --event-scheduler=ON`. Nenhum script de deploy habilita o scheduler. |
| Causa raiz | A auditoria é gravada em `auditoria_buffer` (INSERT leve) e só é movida para `auditoria_eventos` pelo evento `evt_flush_auditoria_buffer` (a cada 10s). Com o scheduler OFF, o buffer **nunca é descarregado** — os endpoints `api_auditoria_*.php` consultam `auditoria_eventos` e ficam vazios. A limpeza de auditoria antiga e de `login_attempts` também nunca roda. |
| Risco | Médio — trilha de auditoria não funciona (a menos que o scheduler seja ligado manualmente), retenção de dados não aplicada. |
| Classificação | **Confirmado** |
| Severidade | Média |
| Recomendação | Habilitar no MySQL: `command: --event-scheduler=ON` no serviço `db` do docker-compose (dev e prod), ou `SET GLOBAL event_scheduler = ON;` no bootstrap do container. Alternativa: mover o flush para um job PHP (cron/systemd) chamando `CALL sp_flush_auditoria_buffer()`. |

### F-302 — Índice duplicado em `auditoria_eventos`

| Campo | Valor |
| ----- | ----- |
| Local | `config/DB/cftv_gml.sql:784` e `:813` |
| Evidência | `INDEX idx_auditoria_entidade (entidade, created_at)` (inline na CREATE TABLE) e `CREATE INDEX idx_audit_entidade_data ON auditoria_eventos(entidade, created_at)` (externo) — definição idêntica. |
| Causa raiz | O índice composto foi adicionado inline e depois recriado em instrução externa de otimização. |
| Risco | Baixo — duplicação de índice custa espaço e sobrecarga de escrita na tabela mais volátil. |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Remover um dos dois (manter `idx_audit_entidade_data`). |

### F-303 — Divergência na tabela `migrations`: registrada `2026_07_13_100000` que não existe mais; `2026_07_13_110000` presente mas não registrada

| Campo | Valor |
| ----- | ----- |
| Local | `config/DB/cftv_gml.sql:1711-1712` |
| Evidência | O `INSERT INTO migrations` registra `2026_07_13_100000`, mas o arquivo `database/migrations/2026_07_13_100000_add_locais_unique.php` foi **removido** (commit `de909e9`). A única migration PHP restante é `2026_07_13_110000_add_locais_dup_trigger.php`, que **não** está registrada no INSERT do schema. |
| Causa raiz | Reorganização (commit `de909e9`) removeu as migrations obsoletas, mas o seed da tabela `migrations` no `cftv_gml.sql` não foi atualizado para refletir o timestamp real da única migration restante. |
| Risco | Baixo — em instalação nova via `cftv_gml.sql`, o `auto_migrate` verá `2026_07_13_110000` como pendente e tentará aplicá-la. Como a migration é idempotente (DROP IF EXISTS + CREATE), não quebra, mas roda desnecessariamente e deixa o histórico incoerente. |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Atualizar o seed para registrar `2026_07_13_110000` (removendo a entrada `2026_07_13_100000`). |

### F-304 — Partições de `auditoria_eventos` cobrem apenas até 2026-07 (data de hoje: 2026-08)

| Campo | Valor |
| ----- | ----- |
| Local | `config/DB/cftv_gml.sql:790-810` |
| Evidência | `PARTITION BY RANGE (TO_DAYS(created_at))` com partições explícitas até `p2026_07` e `p_future VALUES LESS THAN MAXVALUE`. Não há processo automatizado de adição mensal de partições. |
| Causa raiz | As partições foram criadas no momento do schema; sem manutenção, todos os registros de 2026-08 em diante caem em `p_future` (MAXVALUE), perdendo o pruning por mês. |
| Risco | Baixo — funcional, mas o benefício do particionamento se perde com o tempo e a tabela cresce em partição única. |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Adicionar job/migration mensal (`ALTER TABLE auditoria_eventos ADD PARTITION` ou `REORGANIZE PARTITION p_future`) alinhado à manutenção de retenção (2 anos). |

## Verificações detalhadas

### Modelagem / Normalização
- **Atender** — 41 tabelas com domínios específicos; FK explícitas em todas as relações; `ON DELETE` definido (RESTRICT/SET NULL/CASCADE) conforme semântica.
- Soft delete consistente (`deleted_at` + índices compostos `(deleted_at, id)` etc.) em `equipamentos`, `locais`, `central_alarmes`.
- Denormalização pontual intencional: `central_alarmes` mantém `status`/`regiao` VARCHAR além de `status_id`/`regiao_id` (espelho) e `alarmes_manutencoes` `status_anterior`/`status_novo` — **aceitável**, documentado.

### Chaves / Constraints
- PKs em todas as tabelas. `auditoria_eventos` usa `PRIMARY KEY (id, created_at)` (necessário para particionamento).
- CHECKs de domínio: porta 1-65535, lat/long, leitura_noturna 0/1, canais/armazenamento/altura >= 0.
- `uq_locais_nome_ativo` (coluna virtual) resolve corretamente o caso NULL em UNIQUE — **boa prática**.

### Índices
- Cobertura ampla nos padrões de acesso (ver Fase 7): `equipamentos`, `locais`, `manutencoes`, `central_alarmes`, `notificacoes` bem indexados, inclusive compostos por secretaria + deleted_at.
- **F-302**: índice duplicado em `auditoria_eventos`.
- Candidatos a redundância (prefixo idêntico) que exigem EXPLAIN: `idx_equip_ip` vs `idx_equip_ip_porta`/`idx_equip_ip_deleted`; `idx_equip_deleted` vs `idx_equip_deleted_id/status/tipo/secretaria/tipo_camera`; `idx_equip_composicao` vs `idx_equip_busca`; `idx_eq_manut_os_status` vs `idx_eq_manut_os_status_data_hora/updated/created/execucao`; `idx_alarme_manut_os_status` vs `idx_alarme_manut_os_status_data_hora`. **Classificação: Necessita evidência adicional** (sem DB para EXPLAIN).

### Queries / Paginação / N+1
- Paginação em todos os endpoints de listagem (Fase 7, AUD-032).
- COUNT separado do SELECT com base reduzida (menos JOINs) — `api_cameras.php:152-156`.
- Subqueries agregadas cobrem índices compostos (ex. `idx_eq_manut_os_status_equip`).
- **N+1: não identificado** (batch no `NotificacaoService`).

### Transações / Locking / Concorrência
- `auto_migrate` usa `GET_LOCK('cftv_migrate', 0)` advisory não-bloqueante (migration runner) — **boa prática**.
- Triggers de anti-duplicidade com `<=>` NULL-safe + `SIGNAL` cobrem race de duplo submit.
- `login_attempts` com PK `(username, ip_address)` e índice `locked_until` — bom para força bruta.
- Sem locks pessimistas desnecessários; `equipamentos` com soft delete evita bloqueios longos.

### Integridade Referencial / Triggers
- 20 triggers: auditoria (equipamentos/usuarios/secretarias/locais), anti-duplicidade (locais), geração de `codigo_publico`.
- Buffer de auditoria (`auditoria_buffer`) + `sp_flush_auditoria_buffer` + evento de 10s — **inoperante sem event_scheduler (F-301)**.
- `proximo_codigo_equipamento()` usa sequência com autopoda (>1000) — concorrente e sem lock de tabela.

### Migrações
- Runner robusto (DDL implicit-commit tratado; `down` disponível; lock advisory; fallback sem DB).
- `auto_migrate` no bootstrap web/API com gate `CAMERAS_AUTO_MIGRATE=0`.
- **F-303**: divergência de timestamp na tabela seed.

### Full Table Scans
- Não identificados em produção estática; dependem de EXPLAIN (Bloqueado).

## Matriz de Banco de Dados

| ID | Área | Severidade | Status | Observação |
| -- | ---- | ---------- | ------ | ---------- |
| F-301 | event_scheduler OFF → auditoria/flush/limpeza inoperantes | Média | Corrigido | `command: --event-scheduler=ON` nos docker-compose |
| F-302 | Índice duplicado em `auditoria_eventos` | Baixa | Corrigido | `idx_auditoria_entidade` inline removido |
| F-303 | Seed `migrations` divergente (100000 vs 110000) | Baixa | Corrigido | Seed registra apenas `2026_07_13_110000` |
| F-304 | Partições de auditoria até 2026-07, sem manutenção | Baixa | Corrigido | `sp_manter_particoes_auditoria(3)` no evento mensal |
| — | Índices candidatos a redundância (EXPLAIN) | — | Necessita evidência adicional | Sem MySQL local |
| — | Medições de runtime (EXPLAIN, SHOW INDEX) | — | Bloqueado | Sem MySQL local |

## Conclusão

O banco é bem modelado, com integridade referencial completa e indexação pensada para os padrões de acesso reais. Os 4 achados (F-301 a F-304) foram corrigidos conforme as recomendações.