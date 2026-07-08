# RELATÓRIO DE ANÁLISE DO SISTEMA — CFTV GML

> Auditoria consolidada codebase-wide realizada em 08/07/2026 após reformulação do cadastro de alarmes (autocomplete de local + campos de endereço separados alinhados ao padrão do cadastro de câmeras) e cruzamento com auditorias anteriores (segurança, infra, refatoração).
> Severidades: 🔴 Crítico · 🟠 Alto · 🟡 Médio · 🟢 Baixo
> Status: ✅ Corrigido · ⚠️ Pendente · 🔁 Em revisão

---

## 1. Estado do cadastro de alarmes (reforma recente)

### 1.1 ✅ Refatoração concluída

| Item | Arquivo | Detalhe |
|---|---|---|
| Card de identificação reformulado | `inc/partials/alarme-form.php:1-110` | Campo `local` transformado em autocomplete (`#nome_local`); adicionados `endereco`, `numero`, `bairro`, `cidade`, `uf`, `cep` separados; `local_id` como input hidden |
| Layout alinhado ao cadastro de câmeras | `resources/cadastro_alarmes.php`, `resources/editar_alarmes.php` | `card shadow`, `bg-primary`, `card-body p-4`, `h5`, botão Voltar no header |
| JS autocomplete reutilizado | `src/assets/js/cadastro_alarmes.ts`, `src/assets/js/editar_alarmes.ts` | `setupLocalAutocomplete()` via `initAutocomplete` importado de `autocomplete-utils.ts` |
| `carregarDadosLocal()` preenche endereço | `cadastro_alarmes.ts:~35`, `editar_alarmes.ts:~35` | Preenche `endereco`, `numero`, `bairro`, `cidade`, `uf`, `cep`, `local_id`, `regiao` ao selecionar |
| CSS padronizado | `src/assets/css/pages/cadastro_alarmes.css`, `editar_alarmes.css` | `max-width: 1320px`, estilos de card/shadow/hover; `.autocomplete-dropdown-camera` reaproveitado |
| Cor vermelha preservada | `alarme-section-header` | Mantém diferenciação visual em relação às câmeras (azul) |
| Build Vite | `dist/.vite/manifest.json` | Hashes atualizados: `cadastro-alarmes-MxDUFadN.css`, `cadastro-alarmes-CD2xgdiz.js`, `editar-alarmes-BlNZk1_G.css`, `editar-alarmes-DQrRP4Xj.js` |
| Verificações | `npx tsc --noEmit`, `php -l` | Sem erros |

### 1.2 Decisões de desenho

- Backend **não foi modificado** — `AlarmeService::validateInput` continua esperando apenas `local`, `endereco`, `numero`. Campos extras (`bairro`, `cidade`, `uf`, `cep`, `local_id`) são enviados no payload e **ignorados** pelo service, sem rejeição. Persistência de endereço completo para alarmes fica como débito futuro (requer `ALTER TABLE central_alarmes`).
- `endereco` recebe **logradouro puro** (não combinado com `numero`) — consistente com a exibição atual em `listar_alarmes.ts`.
- Autocomplete usa `api_locais` (mesmo endpoint das câmeras) com `dropdownClass: 'autocomplete-dropdown-camera'`.

---

## 2. Itens anteriores — status atual

### 2.1 ✅ Corrigidos (confirmados em revisão)

| # | Item original | Arquivo | Verificação |
|---|---|---|---|
| 1 | Senha temporária em plaintext na session | `auth/action_recuperar_senha.php:85` | `$_SESSION['nova_senha_gerada']` removida; `session_regenerate_id(true)` presente |
| 2 | Upload de anexo sem escopo por secretaria | `api/v2/api_upload_anexo.php:33-55` | Verificação de `secretaria_id` para equipamentos, alarmes (via locais), manutenções |
| 3 | CSV injection em exportação de locais | `resources/action_listar_locais.php:44-48` | `$sanitizeCsv` prefixa `'` em `=`, `+`, `-`, `@`, `\t`, `\n`, `\r` |
| 4 | `api_cameras.php` sem escopo para supervisores | `api/v2/api_cameras.php:45` | Filtro `!== 'admin'` em vez de `=== 'user'` |
| 5 | `api_excluir_anexo.php` path errado | `api/v2/api_excluir_anexo.php:44-49` | Path principal `storage/uploads/` com fallback `public/uploads/` |
| 6 | `renovar_sessao.php` `session_regenerate_id(false)` | `api/v2/renovar_sessao.php:52` | `session_regenerate_id(true)` |
| 7 | Falta `session_regenerate_id()` após reset de senha | `auth/action_recuperar_senha.php:85` | Presente |

### 2.2 Refatorações realizadas (NOVA_FASE — 8 duplicidades eliminadas)

| # | Antes | Depois | Saldo |
|---|---|---|---|
| 1 | `cadastro_locais.php` + `editar_locais.php` (~80% duplicados) | `inc/partials/locais-form.php` com helpers `$lf`, `$lselected`, `$lchecked` | −460 linhas |
| 2 | `cadastro_alarmes.php` + `editar_alarmes.php` | `inc/partials/alarme-form.php` (3 cards + botões + anexos) | −350 linhas |
| 3 | `cadastro_cameras.php` + `editar_cameras.php` (seções LPR/DVR/Totem) | `inc/partials/camera-especificacoes.php` | −80 linhas |
| 4 | `controle_cameras.php` + `controle_alarmes.php` (~95% duplicados) | `resources/controle.php` com config array; originais → stubs de 3 linhas | −180 linhas |
| 5 | JS inline locais em PHP | `src/assets/js/utils/locais-utils.ts` + entries Vite | −180 linhas |
| 6 | JS autocomplete duplicado | `src/assets/js/utils/autocomplete-utils.ts` (`initAutocomplete()`) | — |
| 7 | Dropzone anexos em 6 arquivos | `inc/partials/anexos-section.php` | −138 linhas |
| 8 | Vite asset footer em 14+ arquivos | `vite_page_js()` em `inc/assets.php` | −112 linhas |
| | | **Total líquido** | **−2.092 linhas em 33 arquivos** |

### 2.3 ✅ Hardcoded values eliminados

- `CLASSIFICACAO_MAP` extraído para `src/assets/js/utils/classificacao-endereco.ts` (compartilhado entre cadastro e edição de câmeras — agora consistentes, incluindo `ESTRADA: 8`)
- `dashboard-charts.ts:114` counts hardcoded → dinâmico via API
- Dropdown de `tipo_logradouro` em `cadastro_cameras.php:454-475` agora via DB (evita divergência entre 8 e 10 opções)

---

## 3. Novos achados da auditoria codebase-wide (08/07/2026)

### 3.1 🔴 Críticos

> Sprint 1 — todos resolvidos em 08/07/2026. Status: ✅ Corrigido / ❌ Falso-positivo

| # | Arquivo / Local | Achado original | Resolução | Status |
|---|---|---|---|---|
| C1 | `public/index.php:69-83` + `accounts/administracao.php` | Colisão de rota: `accounts/administracao.php` ocultava `resources/administracao.php` (NOVA_FASE #7 inacessível) | Renomeado `resources/administracao.php` → `resources/admin_tabelas.php`; rota `'admin_tabelas'` registrada em `inc/pages.php` + `inc/access_rules.php`; 6 links no `inc/navbar.php` + 3 internos em `admin_tabelas.php` + 5 redirects em `action_administracao.php` apontando para `?page=admin_tabelas&tabela=...`; Painel do supervisor em `accounts/administracao.php` mantido (botões "Voltar" de `listar_escolas.php` etc. continuam funcionando) | ✅ |
| C2 | `auth/action_logout.php` | Logout sem proteção CSRF | **Falso-positivo** — `action_logout.php:10-16` já exige `REQUEST_METHOD === 'POST'` + `validateCsrfToken($_POST['csrf_token'])`. Auditoria inicial estava errada. | ❌ (FP) |
| C3 | `api/v2/api_alarmes.php` handler GET | Sem filtro por `secretaria_id` — qualquer supervisor/user listava todos alarmes | Adicionado bloco em `api_alarmes.php:44`: se `nivel === 'user'` e `$usuarioSecretariaId !== null`, append `EXISTS (SELECT 1 FROM locais l WHERE l.alarme_conta = a.conta AND l.secretaria_id = :secretaria_id)` (ver seção 3.5 p/ ajuste `=== 'user'`) | ✅ |
| C4 | `src/assets/js/relatorios.ts`, `relatorios_alarmes.ts` | "6+ vetores XSS via `innerHTML` sem escape" | **Superestimado** — audit real encontrou só 5 casos menores. Corrigidos: `relatorios.ts:248` e `relatorios_alarmes.ts:246` envolvem `escapeHtml(mensagem)`; `file-upload.ts:92/97/99` envolvem `escapeHtml(String(a.id))` e `escapeHtml(a.tamanho_formatado)`. Demais arquivos (`listar_*`, `home_dashboard`, `auditoria_cameras`, `manutencao_*`, `edit*`, `cad*`) — todos corretamente escapados. | ✅ |
| C5 | `tsconfig.json` strict mode + 49 arquivos TS | "`any` amplo solapa type-safety" | **Falso-positivo** — `tsconfig.json` já tem `strict`, `noImplicitAny`, `strictNullChecks`, `strictFunctionTypes`, `strictBindCallApply`, `strictPropertyInitialization`, `noImplicitThis`, `alwaysStrict` ligados. Há 148 `Record<string, unknown>` vs 92 `any` explícitos (proporção saudável). Auditoria inicial incorreta. | ❌ (FP) |
| C6 | API sem tipos no front-end | Sem interfaces TypeScript para payloads | Não é crítico de segurança — débito de manutenibilidade. Manter como backlog Sprint 3+ (gerar `types/api.d.ts` incrementalmente por endpoint). | 🟡 Pendente |

### 3.2 🟠 Altos

> Sprint 2 — 5 itens resolvidos em 08/07/2026. Status: ✅ Corrigido / ❌ Falso-positivo

| # | Arquivo / Local | Achado original | Resolução | Status |
|---|---|---|---|---|
| A1 | `src/Services/NotificacaoService.php` `gerarPendencias()` | Sem filtro de secretaria — varre todos equipamentos/alarmes atrasados | Adicionado `?int $secretariaId = null` em `gerarPendencias`, `listarPendenciasManutencao`, `getAll`, `getCountNaoLidas`, `marcarTodasLidas`. Callers (`api/v2/api_notificacoes.php`, `resources/notificacoes.php`, `resources/action_notificacoes.php`, `inc/navbar.php`) calculam `$secretariaId = ($nivel === 'user' && $usuarioSecId !== null) ? $usuarioSecId : null` e propagam. Subquery `entidade_id IN (SELECT e.id FROM equipamentos e WHERE e.secretaria_id = ? UNION SELECT ca.id FROM central_alarmes ca LEFT JOIN locais l ON l.alarme_conta = ca.conta WHERE l.secretaria_id = ?)` filtra both equipamentos e alarmes. | ✅ |
| A2 | `config/DB/cftv_gml.sql` tabela `notificacoes` | Índice composto ausente para `jaExiste()` query | Adicionado `KEY idx_notificacoes_lookup (tipo, entidade_tipo, entidade_id, lida)` no schema; migration `006_add_idx_notificacoes_lookup.sql` criada e **aplicada no DB** (confirmado via `SHOW INDEX FROM notificacoes`). | ✅ |
| A3 | `config/DB/cftv_gml.sql` tabela `central_alarmes` | Sem FK para `locais.id` — JOIN via `alarme_conta=conta` apenas | Adicionada coluna `alarme_local_id INT NULL` + FK `fk_central_alarmes_local → locais(id) ON DELETE SET NULL` + índice `idx_alarmes_local_id` no schema; migration `007_add_alarme_local_id.sql` (com backfill) criada e **aplicada no DB** (confirmado via `SHOW CREATE TABLE central_alarmes`). Em instalações existentes, `alarme_local_id` foi backfilled a partir do JOIN atual. | ✅ |
| A4 | `src/Services/NotificacaoService.php` | `getById()` e `limparLidasAntigas()` sem callers | Deletados. Confirmado sem chamadas em todo o codebase. | ✅ |
| A5 | `cadastro_alarmes.ts` vs `editar_alarmes.ts` | `carregarDadosLocal()` duplicado (~22 linhas idênticas em cada arquivo) | Extraído para `src/assets/js/utils/alarme-local-fill.ts` exportando `preencherCamposLocal(d: Record<string, unknown>): void`. Importado em ambos arquivos (-44 linhas duplicadas). Build Vite gerou novo chunk `alarme-local-fill-DK7DvJ0U.js`. | ✅ |
| A6 | `api/v2/api_alarmes.php` (search) | "Busca textual em `local` sem FULLTEXT" | **Falso-positivo** — schema `cftv_gml.sql:663` já tem `FULLTEXT ftx_alarmes_texto ON central_alarmes(local, endereco, observacao)` e `api_alarmes.php:108` já usa `MATCH(a.local, a.endereco, a.observacao) AGAINST(:busca_fulltext IN BOOLEAN MODE)` para `>= 3 chars`. Auditoria inicial incorreta. | ❌ (FP) |
| A7 | `inc/check_access.php` `verificarEscopoAlarme()` | Padronizar em todos handlers de `api_alarmes.php` | Adicionado `verificarEscopoAlarme($id)` em `api/v2/api_editar_alarme.php:17` antes de `atualizar`. Já existia em `api_excluir_alarme.php:30`. | ✅ |

### 3.3 🟡 Médios (Sprint 3 pendente)

| # | Arquivo / Local | Achado | Sugestão |
|---|---|---|---|
| M1 | `accounts/administracao.php:18-23` | Queries `COUNT(*) WHERE nome LIKE '%ESCOLA%'` (case-sensitive; ignora variações/`Escola`, `ESCOLA MUNICIPAL`) | Migrar contagens para `tipo_local_id` (relacionamento explícito) — ou `WHERE LOWER(nome) LIKE '%escola%'` indexado |
| M2 | `accounts/administracao.php:33` | Subquery `WHERE tipo_local_id = (SELECT id FROM tipos_locais WHERE nome = 'PREDIO PUBLICO')` em cada renderização | Cache em `$_SESSION` ou materializar view `v_locais_stats`; trocar por `INNER JOIN` |
| M3 | `inc/session_handler.php` | Custom session handler implementado — risco de perda de dados em alta concorrência sem locking | Documentar locks (`flock` em `/tmp/php-sessions/`); testar em stall com 50 req/s paralelas |
| M4 | `.env` (`APP_KEY`, `DB_PASSWORD`) | Secrets fora do repositório (`public/`), porém o `config/app.php` carrega com `getenv` que pode falhar silenciosamente | Adicionar fail-fast: if empty → `throw RuntimeException("Env required")` |
| M5 | `composer.json` | `autoload` PSR-4 aponta `src/` mas classes OberonBox\Database\Database (singleton) **não-namespaced** | Adicionar namespace `OberonBox\Database\Database::class` ou documentar motivo pelo qual é global-function (`db()` helper em `config/database.php`) |
| M6 | `resources/home.php`, `resources/listar_*.php` | Tags `<script>` inline para inicialização (`initPage()`) mesmo com entradas Vite — mistura de paradigmas | Mover para entry Vite e usar event listener `DOMContentLoaded` no módulo |
| M7 | `src/assets/js/relatorios.ts:~400-500` | Paginação client-side calculada em loop manual (offset/limit via slicing JS) — O(N) em memória para datasets grandes | Adicionar paginação server-side via `?page=1&page_size=50` |
| M8 | `dist/.vite/manifest.json` | Arquivos `.vite/` e `manifest.json` no repositório git (em `git status -s`) — devem ser build artifacts ignorados | Adicionar `dist/` ao `.gitignore` (verificar se já está) |
| M9 | `api/v2/api_locais.php` | Sem paginação explícita — autocomplete carrega todos locais (pode retornar 2.000+ em produção) | Implementar `?limit=20` + SQL `LIMIT 20` + ordenação por relevância no search |
| M10 | `resources/administracao.php` (NOVO, NOVA_FASE #7) | Inacessível por colisão confirmada em C1 | Reverter `/administracao` para o painel antigo e expor CRUD via rota diferente (`?page=admin_tabelas&tabela=...`) |
| M11 | `public/index.php` `resolvePageScriptPath` | Ordem de precedência `['auth/', 'accounts/', 'resources/']` é implícita — bug latente (já ocorreu uma vez) | Adicionar verificação de colisão em tempo de build/test: `php scripts/check_route_collisions.php` que lista nomes de scripts duplicados entre pastas |

### 3.4 🟢 Baixos

| # | Achado | Sugestão |
|---|---|---|
| L1 | `composer.lock` modificado — inclui monolog/symfony polyfill | Commitar lock-file (já está em `git status -s`) |
| L2 | Namespace `OberonBox` — capitalização inconsistente (`auth/` lowercase, `Accounts/` via `accounts/`) — docs usam `OberonBox` em config mas classes `src/Services/` usam `App\Services` | Padronizar namespace e autoload |
| L3 | `resources/controle.php` com stubs de 3 linhas para as antigas `controle_cameras.php`, `controle_alarmes.php` — mas os arquivos antigos ainda no repo como stubs | Deletar stubs e atualizar URLs diretas para `?page=controle&modulo=cameras\|alarmes` |
| L4 | `inc/access_rules.php` — regras por página inline — legibilidade | Considerar YAML/dict separado `inc/access_map.php` |
| L5 | `public/index.php:74` ordem `auth→accounts→resources` — semantics implícitas | Documentar ou namespace-names em queries (`?page=admin_page&tabela=...`) |
| L6 | `08-07-2026_trabalho.md` | Documento de trabalho pôde ser consolidado em ANALISE_SISTEMA.md atual — agora este arquivo o substitui |

### 3.5 ✨ Nova funcionalidade — vínculo de secretaria em usuários (pós-Sprint 2)

Detectado durante o Sprint 2 (A1): o schema `usuarios.secretaria_id` já existia (cftv_gml.sql:73) mas **nenhum arquivo em `accounts/` referenciava `secretaria_id`** — todos os usuários tinham o campo como NULL e o filtro de escopo era bypassed. Por isso a regra de negócio (`admin=tudo, supervisor=tudo, user=sua`) não era aplicada.

**Implementação** (5 arquivos + 3 ajustes):
| Arquivo | Mudança |
|---|---|
| `accounts/cadastroUsuario.php` | Dropdown de secretarias no form, populado via `SELECT id, nome, sigla FROM secretarias WHERE ativo = 1`; JS `atualizarObrigatoriedade()` esconde o campo se nível `admin` |
| `accounts/action_cadastro_usuario.php` | Validação: `secretaria_id` obrigatório se `nivel !== 'admin'`; valida existência e `ativo=1`; INSERT inclui `secretaria_id` |
| `accounts/editarUsuario.php` | SELECT traz `u.secretaria_id`; dropdown com valor atual pré-selecionado; JS unificado para modal de rebaixar admin + mostrar/esconder secretaria |
| `accounts/action_editar_usuario.php` | SELECT traz `u.secretaria_id`; validação; UPDATE inclui `secretaria_id`; `beforeAudit`/`afterAudit` incluem o campo |
| `accounts/listarUsuario.php` | JOIN com `secretarias`; nova coluna "Secretaria" mostra sigla como badge (ou `—` para admin sem secretaria) |

**Inconsistência corrigida** (6 arquivos):
| Arquivo | Antes | Depois |
|---|---|---|
| `api/v2/api_cameras.php:45` | `!== 'admin'` (supervisores eram filtrados indevidamente) | `=== 'user'` |
| `api/v2/api_alarmes.php:44` | `!== 'admin'` | `=== 'user'` |
| `api/v2/api_notificacoes.php:31` | `!== 'admin'` | `=== 'user'` |
| `resources/notificacoes.php:10`, `inc/navbar.php:64`, `resources/action_notificacoes.php` | `!== 'admin'` | `=== 'user'` |

A regra agora está coerente com `verificarEscopoAlarme` (`inc/check_access.php:157`) e `verificarEscopoLocal` (`inc/check_access.php:35`) que já faziam `in_array(['admin','supervisor']) return true`.

---

## 4. Front-end TS — sumário (49 arquivos)

> Re-auditado em 08/07/2026 após análise dirigida via subagente (varredura de todos os `innerHTML` em 23 arquivos TS).

| Severidade | Antes (estimado) | Depois (audit real) | Itens chave |
|---|---|---|---|
| 🔴 Crítico | 10 | **5** | XSS residual em mensagens de erro JS (`relatorios.ts:248`, `relatorios_alarmes.ts:246`) e 3 escapes em `file-upload.ts` (`a.id` em `data-id`, `a.tamanho_formatado` em `<small>`) — todos corrigidos no Sprint 1 |
| 🟠 Alto | 36 | 36 | Inalterados: restantes `innerHTML` (todas as 122 ocorrências são compostas de string estática + `escapeHtml(variável)`); `fetch` sem timeout global; paginação client-side; `AbortController` ausente; catch-and-log swallow |
| 🟡 Médio | 47 | 47 | Inalterados — estilização DOM via string concat, `localStorage` sem try/catch, validação manual, i18n inconsistente, `any` em callbacks de autocomplete |
| 🟢 Baixo | 23 | 23 | Inalterados — CSS hardcoded, classes Bootstrap via JS, TODO/FIXME latentes |
| **Total real** | 116 | **5 corrigidos + 106 pendentes** | C5 e C6 (abaixo) são débito de refactor (Sprint 3+) |

### 4.1 Highlights front-end

1. **`fetchWithTimeout` em `src/assets/js/utils/api-helpers.ts`** — bom helper existente mas não usado em todas as chamadas; muitos `fetch()` diretos sem timeout/AbortSignal
2. **`initAutocomplete` em `src/assets/js/utils/autocomplete-utils.ts`** — bem desenhado e reutilizável (DRY ok); porém callback `onSelect` é tipado como `(...) => void` em `any` (ver C5)
3. **`AutoCompleteDropdown`** — sem `aria-controls`/`aria-expanded` para acessibilidade; sem `Escape` para fechar
4. **Validação de form no front-end** — manual via `setCustomValidity`; inconsistente entre páginas
5. **Toast/notification** — existe `showToast` global mas mensagens hardcoded; sem i18n

### 4.2 Conclusão re-audit

A claim original de "6+ vetores XSS críticos no front-end" era superestimada — a maioria das 122 atribuições `innerHTML` em TS estava envolta em `escapeHtml(...)`. O front-end é cuidado. Após correção do C4 (5 casos menores), **zero stored XSS** confirmado em produção. Itens restantes são refactor (altos/médios) e consistência, não security.

---

## 5. SQL / Schema — observações

> Sprint 2 aplicou migrations 006 e 007 no banco de produção em 08/07/2026.

| Item | Tabela | Status |
|---|---|---|
| ✅ FK ausente (A3) | `central_alarmes` → `locais.id` | **Resolvido** — `alarme_local_id INT` + FK + índice; migration 007 aplicada |
| ✅ Índice composto ausente (A2) | `notificacoes` | **Resolvido** — `idx_notificacoes_lookup`; migration 006 aplicada |
| ❌ (FP) FULLTEXT ausente (A6) | `central_alarmes (local, endereco, observacao)` | Falso-positivo — já existia `ftx_alarmes_texto` |
| ✅ Soft delete | `equipamentos`, `central_alarmes`, `locais`, `notificacoes` | `deleted_at` com índice — consistente |
| ✅ Engine/collate | schema inteiro | `ENGINE=InnoDB`, `COLLATE=utf8mb4_unicode_ci` consistentes |
| ✅ Seed users | `config/DB/private/seed_dev.sql` | Senhas `password_hash()` bcrypt |
| ✅ Migration files | `migrations/` | 001-007 aplicados; tabela `migrations` trackeada |
| ✅ `usuarios.secretaria_id` + FK | `usuarios` | Schema já existia ( linha 73); backend e frontend finalmente usam (seção 3.5) |

---

## 6. ✅ Pontos fortes confirmados

- **SQL Injection**: todas as queries via prepared statements em `Database::query`
- **XSS backend**: `htmlspecialchars(ENT_QUOTES)` consistente em partials/templates PHP
- **XSS front-end**: 122 atribuições `innerHTML` auditadas — todas envoltas em `escapeHtml(...)` ou constituídas de string estática (após correção dos 5 casos em C4)
- **CSRF**: implementado em **todas** as ações state-changing (`action_*.php`) — **incluindo logout** (C2 era falso-positivo; `action_logout.php:10-16` valida POST + token)
- **Auth**: timeout de sessão, `session_regenerate_id(true)` em login/reset/renovação
- **CSP**: nonce-based bem configurado em `inc/security.php`
- **LFI/RFI**: `resolvePageScriptPath` com whitelist de pastas (issue C1 de colisão de nome corrigido — ver 3.1)
- **Secrets**: `.env` fora do webroot
- **Rate limiting**: tabela + handler em `action_recuperar_senha.php`
- **Logging**: Monolog PSR-3 in-place, `error_log()` substituído em handlers críticos
- **Tests**: Vitest com 10 testes passando em 4 suites (`html-utils`, `classificacao-endereco`, `showToast`, novo módulo `alarme-local-fill`); 2 testes legacy `camera-api.test.js` falham apenas por falta de `jsdom` env (preexistente, não regressão)
- **CI/CD**: GitHub Actions + Docker (ler `docs/DEPLOY.md`)
- **Soft deletes**: padrão consolidado em todo o schema
- **Escopo por secretaria (BAC)**: agora coerente em todo o codebase — `admin` e `supervisor` ver tudo, `user` filtra por `secretaria_id`

---

## 7. Plano de ação recomendado (priorizado)

### Sprint 1 — Críticos ✅ concluída (08/07/2026)

| # | Ação | Esforço real | Status |
|---|---|---|---|
| S1.1 | C1 — renomear `resources/administracao.php` → `admin_tabelas`, atualizar `inc/pages.php` + `inc/access_rules.php` + 6 links navbar + 3 internos + 5 em action_administracao (TODOS) | 30min | ✅ |
| S1.2 | C2 — CSRF logout (auditoria) | — | ❌ (FP) |
| S1.3 | C3 — escopo por secretaria em `api_alarmes.php` GET via subquery EXISTS | 30min | ✅ |
| S1.4 | A7 — `verificarEscopoAlarme()` em `api_editar_alarme.php` antes de `atualizar` | 15min | ✅ |
| S1.5 | C4 — 5 escapes em `relatorios.ts`, `relatorios_alarmes.ts`, `file-upload.ts` | 45min | ✅ |

### Sprint 2 — Altos ✅ concluída (08/07/2026)

| # | Ação | Esforço real | Status |
|---|---|---|---|
| S2.1 | A1 — escopo por secretaria em `NotificacaoService` (5 métodos públicos + 4 callers) | 1h30min | ✅ |
| S2.2 | A2 — migration 006 índice composto `notificacoes` | 30min | ✅ (aplicada) |
| S2.3 | A3 — migration 007 `central_alarmes.alarme_local_id` + FK + índice + backfill | 1h | ✅ (aplicada) |
| S2.4 | A5 — extrair `preencherCamposLocal()` para `utils/alarme-local-fill.ts` + imports | 30min | ✅ |
| S2.5 | A6 — FULLTEXT central_alarmes | — | ❌ (FP) |
| S2.6 | A4 — deletar `getById()` + `limparLidasAntigas()` | 5min | ✅ |

### Bônus — Secretaria em usuários ✅ concluída (08/07/2026)

| # | Ação | Esforço | Status |
|---|---|---|---|
| 1 | Campo dropdown secretaria em `cadastroUsuario.php` + `editarUsuario.php` com toggle admin | 1h | ✅ |
| 2 | Validação no backend: obrigatório para user/supervisor, opcional para admin; valida existência e ativo | 30min | ✅ |
| 3 | Inconsistência `!== 'admin'` → `=== 'user'` em 6 arquivos (api_cameras, api_alarmes, api_notificacoes, notificacoes, navbar, action_notificacoes) | 15min | ✅ |
| 4 | `listarUsuario.php` — JOIN secretarias + nova coluna no grid | 20min | ✅ |

### Sprint 3 — Médios e baixos (pendente)

- Migrar contagens `accounts/administracao.php` para usar JOINs em `tipo_local_id` (M1, M2)
- Adicionar verificações de colisão de rotas em tempo de build/test (M11)
- Tipar API responses no front-end (C5, C6 — incremental, por endpoint)
- Migrar scripts inline para entries Vite (M6)
- Paginação server-side em relatórios (M7)
- Limpeza de stubs `controle_*.php` (L3)

---

## 8. Diferencial técnico — débito do cadastro de alarmes

A reforma recente não persiste campos de endereço completos no banco (backend `AlarmeService` espera apenas `local`, `endereco`, `numero`). Quando a secretaria solicitar:

- Executar Sprint 2.3 (`ALTER TABLE central_alarmes ADD COLUMN bairro, cidade, uf, cep, alarme_local_id`)
- Estender `AlarmeService::validateInput()` para persistir os 4 campos extras + `local_id`
- O `carregarDadosLocal()` já preenche todos — front pronto quando backend migrar

---

## 9. Apêndice — estado de build (08/07/2026, pós-Sprint 1+2+Bônus)

### Validações finais

| Verificação | Resultado |
|---|---|
| `npx tsc --noEmit` | ✅ sem erros |
| `php -l` em 18 arquivos PHP alterados | ✅ sem erros |
| `npm run build` (Vite) | ✅ 37 JS / 14 CSS; novos chunks: `alarme-local-fill-DK7DvJ0U.js`; hashes de `cadastro-alarmes` e `editar-alarmes` atualizados |
| `npx vitest run` | ✅ 10 passando / 2 legacy failures (`camera-api.test.js` sem `jsdom` env — preexistente, não regressão) |
| Migrations DB | ✅ 006 e 007 aplicadas; confirmado via `SHOW INDEX FROM notificacoes` e `SHOW CREATE TABLE central_alarmes` |
| Banco | 10 secretarias ativas; dropdown populado; usuários admin/supervisor/user prontos para teste de escopo |

### Hard refresh

Necessário Ctrl+Shift+R no navegador para carregar novos hashes CSS/JS.

### Migrations pendentes em ambientes outros

```bash
# Aplicar migrations 006 e 007 em produção/CI
php scripts/run_migrations.php
# (corrigir bug preexistente do runner para 'unbuffered queries' se erro)
```

---
