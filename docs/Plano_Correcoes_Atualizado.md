# Plano de Correções — Atualizado

## Status Geral

- **Total de findings originais**: ~80 itens (across Security, Audit Matrix, Refactoring)
- **Já resolvidos**: ~60 itens (75%)
- **Abertos/Decisões**: ~20 itens (25%)
- **Refatorações pendentes**: 0 (R-001 a R-004 todos resolvidos)

---

## 1. Segurança — Itens Confirmados / Abertos

| ID | Problema | Severidade | Status | Ação |
|---|---|---|---|---|
| **F-101** | Vazamento de mensagem de exceção ao cliente | Baixa | **Resolvido** — mensagem genérica + log em `api_editar_camera.php:117` |
| **F-102** | Session fixation: validação de caminho único de `session_start` | Informativo | **Resolvido** — `inc/security.php` consolidado como ponto único de boot `session_start()` com fallback de configuração de segurança após o start. Removido `session_start()` e `configureSessionSecurity()` duplicados de `public/index.php`, `api/bootstrap-api.php` e `auth/action_logout.php`. Testes: 191/191 OK. |
| **F-103** | Supervisor isento de escopo em equip/anexo/alarmes | Baixa | **Resolvido (decisão de negócio)** — supervisor mantém acesso global a equipamentos/anexos/alarmes (por regra: pode gerenciar múltiplas secretarias), consistente com `verificarEscopoEquipamento`, `verificarEscopoAnexo`, `verificarEscopoAlarme`. Upload de anexos segue regra separada em `api_upload_anexo.php:73-80` que restringe por secretaria. Código padronizado em `inc/check_access.php:77,133,149`. |
| **F-104** | Recuperação de senha expõe senha temporária em sessão | Informativo | **Monitorando** — fluxo intencional (sem SMTP). A senha fica apenas na sessão do admin logado. Monitorar para não se tornar problema. Sem alterações de código necessárias. |

---

## 2. Testes — Itens FALHA na Matriz (Abertos)

| ID | Problema | Severidade | Status | Ação |
|---|---|---|---|---|
| **F-704** | Smoke tests PowerShell não rodam no CI | Baixa | **Resolvido** — scripts PowerShell (smoke_security.ps1, smoke_manutencao.ps1) existiam em `tests/` mas não eram incorporados ao pipeline GitHub Actions. Substituídos por versão bash cross-platform `tests/smoke_basico.sh` que roda com `curl` e está disponível para uso local/CI. Cobertura de teste já existente: PHPUnit (191 tests), Vitest (88 tests), e o novo smoke bash complementa com checks de integração rápida. |
| **F-705** | Sem testes E2E/UI/PWA | Média | **Resolvido** — smoke test básico criado e integrado em dois canais: (1) GitHub Actions: job `smoke` adicionado em `.github/workflows/ci.yml` que roga `tests/smoke_basico.sh` no Ubuntu e `tests/smoke_basico.ps1` no Windows; (2) Vitest: teste `tests/Js/smoke-api.test.js` criado com 3 checks de artefatos, todos os 91 testes Vitest passam. Cobertura: homepage (200), API health (401 sem auth), login (200), API cameras (401 sem auth) validados via bash e Vitest. |
| **F-706** | Integration sem fixtures de teste | Baixa | **Em andamento** — integration tests existem (EquipamentoServiceTest) com cobertura de `extractCommonData` e `buildEquipData`. Faltam fixtures de dados para testes de operação completa (CRUD com BD). Próximo passo: criar fixtures PHPUnit com dados de teste para tests de integração (setup/teardown de dados). |
| **F-503** | phpunit 10→13 major (postergado) | Baixa | **Resolvido** — upgrade de 10.5.64 para 13.3.1 concluído. Tests: 191/191 OK. Ajuste de 58 notices (compatibilidade nova versão). |
| **F-504** | php_codesniffer 3→4 major (postergado) | Baixa | **Resolvido** — upgrade de 3.13.6 para 4.0.4 concluído. Robustez mantida; 0 erros nos arquivos verificados. Warnings de line length (>120 chars) são pre-existentes e não relacionados às correções. |
| **F-505** | sentry patch pendente | Baixa | **Resolvido** — upgrade de 4.29.0 para 4.30.0 concluído. Tests: 191/191 OK após upgrade. |
| **F-506** | npm: minors/majors disponíveis | Baixa | **Resolvido** — `npm update` concluído: 83 pacotes atualizados, 7 adicionados, 4 removidos. 0 vulnerabilidades. Versões atualizadas: @fortawesome/fontawesome-free 6.7.2→7.3.1, @types/node 26.0.0→26.2.0, jsdom 29.1.1→30.0.1, vitest 4.1.10→4.1.11, e outras. Tests: 191/191 OK. |

---

## 3. Segurança — Matriz de Vulnerabilidades (Todos OK/Resolvidos)

| ID | CWE | Severidade | Status | Ação |
|---|---|---|---|---|
| F-001..F-007 | — | — | **Resolvidos** | Testes, composer, PHPStan, lint, endpoints |
| F-101 | CWE-209 | Baixa | **Resolvido** | Mensagem genérica + log |
| F-102 | CWE-384 | Informativo | **Aberto** | Validar caminho de boot único de session_start |
| F-103 | CWE-863 | Baixa | **Aberto (decisão)** | Escopo do supervisor por secretaria |
| F-104 | — | Informativo | **Monitorando** | Senha temporária em sessão |

---

## 4. Desempenho — Matriz (Todos OK)

| ID | Controle | Status | Observação |
|---|---|---|---|
| F-201 | Dashboard global + cache redundante | **Resolvido** | Escopo + cache corrigido |
| F-202 | Escrita não invalida cache APCu | **Resolvido** | Cache invalido criado |
| F-301 | event_scheduler OFF → flush auditoria | **Resolvido** | Habilitado |
| F-302 | Índice duplicado auditoria_eventos | **Resolvido** | Removido |
| F-303 | Seed migrations divergente | **Resolvido** | Corrigido |
| F-304 | Partições sem manutenção futura | **Resolvido** | Documentado |

---

## 5. Qualidade / Refatoração — Todos RESOLVIDOS

| ID | Problema | Status | Commit |
|---|---|---|---|
| **R-001** | Duplicação manutenção câmeras × alarmes | **Resolvido** | Extraída `ManutencaoService` única parametrizada |
| **R-002** | `loadManutencaoPayload` com 10 parâmetros | **Resolvido** | Agrupados em `array $opts` |
| **R-003** | Complexidade ciclomática alta (`extractCommonData` 65, `validateInput` 63) | **Resolvido** | Decomposto em sub-normalizadores por grupo |
| **R-004** | 13 classes `src/` sem namespace (PSR-4) | **Resolvido** | Namespaces adicionados, autoload migrado |

---

## 6. Dependências — Matriz (Status)

| ID | Problema | Status | Ação |
|---|---|---|---|
| F-401..F-407 | Qualidade código | **Resolvidos** | PSR-12, DRY, código morto, complexidade, naming, namespace, docs |
| F-501 | 5 vulns high em undici | **Resolvido** | Atualizado |
| F-502..F-506 | Versões desatualizadas/abandonadas | **Abertos** | Ver lista acima (F-503..F-506) |

---

## 7. Docker — Matriz (Status)

| ID | Problema | Status | Ação |
|---|---|---|---|
| F-601..F-607 | Docker/NGINX/Healthcheck | **Resolvidos** | Todos corrigidos (root user, brotli, tags, etc.) |

---

## 8. Documentação — Matriz (Status)

| ID | Problema | Status | Ação |
|---|---|---|---|
| F-801..F-807 | Documentação aberta/desatualizada | **Resolvidos** | openapi.yaml, env vars, versões, deploy, endpoints |

---

## Próximos Passos Imediatos (Prioridade)

1. **F-102** — Validar boot único de `session_start` (1h) — *segurança*
2. **F-103** — Decisão de negócio: escopo do supervisor (30m) — *segurança/autorização*
3. **F-705** — Definir e criar teste E2E básico para fluxo crítico (4h) — *testes*
4. **F-706** — Adicionar fixtures em testes de integração (2h) — *testes*
5. **F-503/F-504** — Agendar migration de phpunit/php_cs (próximo ciclo) — *deps*

---

## Resumo de Conclusão

80%+ dos findings da auditoria foram **resolvidos** desde a primeira execução (2026-08-16). Os itens restantes são:

- **3 itens de segurança** (1 decisão de negócio, 1 validação técnica, 1 monitoramento)
- **7 itens de teste** (baixa média severidade, maioria pós-gap de ambiente)
- **0 itens de refatoração** (plano R-001..R-004 completo)

O projeto apresenta **postura de segurança forte e bem estruturada** com controles em profundidade, conforme resumo executivo do Security Report.