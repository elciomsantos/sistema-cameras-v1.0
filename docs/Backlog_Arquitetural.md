# Backlog Arquitetural

**Data:** 11 de Julho de 2026 | **Origem:** Auditoria Técnica (Relatorio_Auditoria_Final.md)

---

Itens de refatoração de longo prazo (6 meses a 1 ano). Requerem observabilidade (Sentry/APM), cobertura de testes >80% e 2 devs dedicados.

---

## 1. Services COI — Services sem `ApiResponse::exit`

**Problema:** Services chamam `ApiResponse::error()/exit` diretamente, quebrando inversão de dependência. Dificulta testes isolados (precisa de `ApiResponse::setTestMode(true)`).

**Solução:** Services devem lançar exceções de domínio (`ServiceException`). O controller/endpoint traduz para HTTP.

**Arquivos afetados:**
- `src/Services/AlarmeService.php`
- `src/Services/EquipamentoService.php`
- `src/Services/LocaisService.php`
- `src/Services/NotificacaoService.php`
- `api/v2/*.php` (todos os endpoints que chamam services)

**Esforço estimado:** ~40-60h

---

## 2. Repository Pattern

**Problema:** SQL inline mistura presentation (`api/v2/*.php`) e domain (`src/Services/*.php`). `database::insert` (com whitelist de tabelas) é sub-utilizado.

**Solução:** Extrair SQL para `src/Repositories/EquipamentoRepository`, `LocaisRepository`, etc. + interfaces. Habilita testes com mocks e SQLite in-memory.

**Arquivos afetados:**
- `src/Services/EquipamentoService.php` → `src/Repositories/EquipamentoRepository.php`
- `src/Services/LocaisService.php` → `src/Repositories/LocaisRepository.php`
- `src/Services/AlarmeService.php` → `src/Repositories/AlarmeRepository.php`
- `api/v2/*.php` (endpoints que fazem SQL cru)

**Esforço estimado:** ~40-60h

---

## 3. Migrations Versionadas

**Problema:** `migrations/` está vazio. Schema evolui por `config/DB/cftv_gml.sql` + `scripts/optimize-database.php` + scripts standalone. Qualquer alteração em produção depende de executar scripts manualmente.

**Solução:** Usar `scripts/run_migrations.php` + novos `migrations/*.php` em todo schema change; CI valida forward+rollback.

**Esforço estimado:** ~20-30h

---

## Pré-requisitos

Antes de iniciar qualquer item:

1. Sentry/APM funcional para medir ganho
2. Cobertura de testes >80% (atual ~45%)
3. CI com quality gates (SonarQube/Snyk)
4. 2 devs dedicados por 2-3 meses
