# CONSOLIDATION_REPORT.md

Consolidação de todos os achados da auditoria — Projeto "Controle Share Videos" v1.0.
Data: 2026-08-16. Fase 13 do protocolo (`docs/ESPECIFICACAO-FINAL.md:946`).

## 1. Visão geral

| Métrica | Valor |
| ------- | ----- |
| Total de achados (F-001..F-807) | 72 |
| Corrigidos | 53 |
| Abertos | 15 |
| Não confirmados | 3 |
| Documentados/informativos | 3 |
| Controles AUD auditados | 68 |

## 2. Matriz consolidada de priorização

A prioridade é calculada como `Impacto × Probabilidade` (escala 1–5), conforme protocolo.
Esforço em escala 1–5 (1 = trivial, 5 = grande). Apenas achados **abertos/não confirmados** com ação potencial são listados.

| ID | Categoria | Severidade | Impacto | Probabilidade | Esforço | Prioridade | Confiança | Status |
| -- | --------- | ---------- | ------- | ------------- | ------- | ---------- | --------- | ------ |
| F-201 | Segurança | Média | 4 | 3 | 1 | 12 | Confirmado | Aberto |
| F-801 | Documentação | Média | 3 | 4 | 2 | 12 | Confirmado | Aberto |
| F-103 | Segurança | Baixa | 3 | 3 | 1 | 9 | Confirmado | Aberto (decisão de negócio) |
| F-705 | Testes | Média | 3 | 3 | 4 | 9 | Confirmado | Aberto |
| F-404 | Qualidade | Média | 2 | 4 | 3 | 8 | Confirmado | Aberto |
| F-401 | Qualidade | Média | 2 | 4 | 3 | 8 | Confirmado | Aberto |
| F-706 | Testes | Baixa | 2 | 3 | 2 | 6 | Confirmado | Aberto |
| F-403 | Qualidade | Baixa | 1 | 4 | 4 | 4 | Confirmado | Aberto |
| F-704 | Testes | Baixa | 1 | 3 | 1 | 3 | Confirmado | Aberto |
| F-405 | Qualidade | Baixa | 1 | 3 | 2 | 3 | Confirmado | Aberto |
| F-502 | Dependências | Baixa | 1 | 2 | 3 | 2 | Confirmado | Aberto (postergado) |
| F-503 | Dependências | Baixa | 1 | 2 | 3 | 2 | Confirmado | Aberto (postergado) |
| F-504 | Dependências | Baixa | 1 | 2 | 3 | 2 | Confirmado | Aberto (postergado) |
| F-505 | Dependências | Baixa | 1 | 1 | 1 | 1 | Confirmado | Aberto (próx. manutenção) |
| F-506 | Dependências | Baixa | 1 | 1 | 2 | 1 | Confirmado | Aberto (npm update) |
| F-605 | DevOps | Baixa | 1 | 2 | 1 | 2 | Não confirmado | Não confirmado |
| F-102 | Segurança | — | — | — | — | — | Não confirmado | Não confirmado |
| F-104 | Segurança | — | — | — | — | — | Não confirmado | Não confirmado |

## 3. Achados abertos por categoria

### Segurança (F-201, F-103) — prioridade 12 e 9
- **F-201** (Média): dashboard agrega dados globais sem filtro de secretaria, mas cacheia por secretaria. Correção de baixo esforço (1): passar o escopo de secretaria para as queries do dashboard e invalidar cache por secretaria.
- **F-103** (Baixa, decisão de negócio): supervisor isento de escopo de secretaria em equipamentos/anexos/alarmes. Requer decisão do proprietário sobre se supervisor deve ser restrito a uma secretaria.

### Documentação (F-801) — prioridade 12
- **F-801** (Média): `docs/openapi.yaml` ausente → Swagger UI retorna 404. Correção: gerar a spec OpenAPI cobrindo os endpoints reais.

### Testes (F-705, F-706, F-704) — prioridades 9, 6, 3
- **F-705** (Média): sem testes E2E/UI/PWA. Maior esforço (4): adicionar Playwright/Puppeteer.
- **F-706** (Baixa): integration sem fixtures. Criar schema + dados de teste determinísticos.
- **F-704** (Baixa): smoke PowerShell não roda no CI. Converter para `.sh` ou remover.

### Qualidade (F-404, F-401, F-403, F-405) — prioridades 8, 8, 4, 3
- **F-404** (Média): complexidade ciclomática alta (`extractCommonData` 65, `validateInput` 63).
- **F-401** (Média): duplicação `api_manutencao_cameras`/`api_manutencao_alarmes` (~600 linhas cada).
- **F-403** (Baixa): 13 classes `src/` sem namespace.
- **F-405** (Baixa): `loadManutencaoPayload()` com 10 parâmetros.

### Dependências (F-502..F-506) — prioridades 2, 2, 2, 1, 1
- Majors de tooling dev postergadas (phpstan 2, phpunit 13, phpcs 4); sentry patch; npm minors/majors.

## 4. Recomendação de plano de correção (ordem de prioridade)

| Fase | IDs | Esforço total |
| ---- | --- | ------------- |
| **1. Segurança** | F-201, F-103 (decisão de negócio) | 2 |
| **2. Documentação** | F-801 (openapi.yaml) | 2 |
| **3. Testes de baixo custo** | F-704 (smoke.sh), F-706 (fixtures) | 3 |
| **4. Qualidade** | F-405, F-404, F-401 | 8 |
| **5. Testes E2E** | F-705 | 4 |
| **6. Dependências** | F-502..F-506 | 11 |
| **7. Qualidade (namespaces)** | F-403 | 4 |

## 5. Matriz de cobertura (por área auditada)

| Controle | Status | Evidência |
| -------- | ------ | --------- |
| SQL Injection | Conforme | AUD-016 |
| XSS | Conforme | AUD-018 |
| Path Traversal (upload) | Conforme | AUD-019 |
| Autenticação (senhas) | Conforme | AUD-020 |
| Sessões | Conforme | AUD-021 |
| Brute force / Rate limit | Conforme | AUD-022 |
| Autorização (IDOR/BOLA) | Não conforme | F-103 (supervisor isento) |
| CSRF | Conforme | AUD-024 |
| CORS | Conforme | AUD-025 |
| SSRF | Conforme | AUD-026 |
| Upload | Conforme | AUD-027 |
| Secrets | Conforme | AUD-028 |
| Headers de segurança | Conforme | AUD-029 |
| Vazamento de erro | Não conforme | F-101 (Corrigido) |
| Logging sensível | Conforme | AUD-031 |
| Performance (N+1, paginação) | Conforme | AUD-032/033 |
| Cache (APCu) | Não conforme | F-201/F-202 (F-202 Corrigido) |
| Banco (FK, índices) | Conforme | AUD-036/038 |
| Migrações | Conforme | F-303 (Corrigido) |
| Auditoria / eventos | Conforme | F-301 (Corrigido) |
| Particionamento | Conforme | F-304 (Corrigido) |
| PSR-12 / estilo | Conforme | F-406 (Corrigido) |
| DRY / duplicação | Não conforme | F-401 |
| Código morto | Conforme | F-402 (Corrigido) |
| Complexidade | Não conforme | F-404 |
| Namespaces / PSR-4 | Não conforme | F-403 |
| Dependências (vulns) | Conforme | F-501 (Corrigido) |
| Dependências (versões) | Não conforme | F-502..F-506 (postergado) |
| Docker (multi-stage, USER) | Conforme | F-601..604 (Corrigido), F-607 (Corrigido) |
| Docker (nginx sobe) | Conforme | F-607 (Corrigido) |
| CI/CD | Conforme | AUD-056 |
| Paridade CI×prod | Conforme | F-606 (Corrigido) |
| Cobertura de testes (JS) | Conforme | F-701 (Corrigido) |
| API tests determinísticos | Conforme | F-702 (Corrigido) |
| Cobertura PHP | Conforme | F-703 (Corrigido) |
| E2E/UI | Não conforme | F-705 |
| Integration fixtures | Não conforme | F-706 |
| Doc API (OpenAPI) | Não conforme | F-801 |
| Doc env vars | Conforme | F-802/F-806 (Corrigido) |
| Doc versões/métricas | Conforme | F-803/F-804 (Corrigido) |
| Doc deploy | Conforme | F-805 (Corrigido) |
| Doc endpoints | Conforme | F-807 (Corrigido) |
| Exploração ativa de segurança | Bloqueado | Sem instância MySQL/docker de teste |
| Cobertura PHP real (%) | Bloqueado | CI pcov habilitado, local sem xdebug |

## 6. Conclusão

A auditoria percorreu **13 fases** (segurança, performance, banco, qualidade, dependências, docker/devops, testes, documentação) e gerou **72 achados**, dos quais **53 corrigidos**. Restam **15 abertos**, concentrados em: segurança de escopo (F-201/F-103), spec OpenAPI (F-801), qualidade de código (F-401/F-403/F-404/F-405), testes E2E/fixtures/smoke (F-704/F-705/F-706) e majors de dependências dev (F-502..F-506). Nenhum achado de severidade **crítica** permanece aberto.

A próxima etapa (Fase 14 — Plano de Refatoração) deve tratar os itens na ordem da seção 4, começando por F-201 e F-801 (maior prioridade, menor esforço).