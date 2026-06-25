# Análise Completa do Projeto — Falhas e Melhorias

**Projeto:** Sistema de Gerenciamento de Câmeras e Alarmes v1.0  
**Data:** 2026-06-24  
**Escopo:** Análise de segurança, banco de dados, API, frontend e testes

---

## 🔴 CRÍTICOS

### C1. Colunas faltantes na tabela `locais` — `regiao_id` e `horario_funcionamento`

`EquipamentoService.php:177,205` referencia `regiao_id` e `horario_funcionamento` como colunas da tabela `locais`, mas **o schema SQL (`cftv_gml.sql`) não as define**. Qualquer INSERT/UPDATE via `LocaisService` ou `EquipamentoService` quebra com "Unknown column".

**Arquivos:** `src/Services/EquipamentoService.php:177,205`, `config/DB/cftv_gml.sql`

---

### C2. APIs mutantes sem proteção CSRF

Sete endpoints POST/PUT/DELETE **não chamam `requireApiCsrf()`**:

- `api/v2/api_cadastrar_cameras.php`
- `api/v2/api_editar_camera.php`
- `api/v2/api_excluir_camera.php`
- `api/v2/api_upload_anexo.php`
- `api/v2/api_cadastrar_alarmes.php`
- `api/v2/api_editar_alarme.php`
- `api/v2/api_manutencao_alarmes.php`

---

### C3. `api_excluir_camera.php` — resposta dupla

Se o UPDATE bem-sucedido executa `ApiResponse::success()` (que faz `exit`), mas por alguma falha de fluxo o código **continua** e chama `ApiResponse::error()` depois. Causa "Headers already sent".

**Arquivo:** `api/v2/api_excluir_camera.php:41-48`

---

### C4. Imports ausentes nos entry points TypeScript

`entries/manutencao-cameras.ts` e `entries/manutencao-alarmes.ts` **não importam**:

- `ui-utils.ts` → `showToast()`, `handleApiError()`, `showConfirm()` undefined
- `fetchWithTimeout.ts` → timeout + CSRF refresh perdidos

**Arquivos:** `src/assets/js/entries/manutencao-cameras.ts`, `src/assets/js/entries/manutencao-alarmes.ts`

---

## 🟠 ALTOS

### A1. XSS em `showConfirm()` — sanitização regex insuficiente

A função usa `innerHTML` com regex para remover `<script>` e `on*` handlers. Bypass possível com `<img src=x onerror=alert(1)>`, `<svg/onload>`, `<details open ontoggle>`, etc.

**Arquivo:** `src/assets/js/utils/ui-utils.ts:89`  
**Callers afetados:** `file-upload.ts:242` (nome do anexo vem do DOM)

---

### A2. `api_cameras.php` e `api_alarmes.php` sem `userHasAccess()`

Qualquer usuário autenticado (inclusive nível "user") pode listar **todas** as câmeras e alarmes. O acesso deveria exigir no mínimo `supervisor`.

**Arquivos:** `api/v2/api_cameras.php:13-15`, `api/v2/api_alarmes.php:13-15`

---

### A3. `api_manutencao_cameras.php` não filtra soft-delete

As queries em equipamentos **não incluem `e.deleted_at IS NULL`**, podendo exibir ordens de serviço de equipamentos já deletados.

**Arquivo:** `api/v2/api_manutencao_cameras.php` (linhas 41-48, 124-131, 154-176, 199-232)

---

### A4. `saveTipoSpecificInsert()` sempre insere em `equipamentos_camera`

Indepentente de `tipo_id` (câmera=1, LPR=2, DVR=3, Totem=4), o método insere um registro em `equipamentos_camera` com vários campos NULL.

**Arquivo:** `src/Services/EquipamentoService.php:394-401`

---

### A5. Nenhum trigger de auditoria para `central_alarmes`

A tabela `central_alarmes` não tem triggers de auditoria no banco. Operações em alarmes não ficam registradas em `auditoria_eventos` pelo lado do DB (apenas via PHP, que é inconsistente).

---

### A6. `central_alarmes` sem soft delete

A tabela não possui coluna `deleted_at`. Não é possível restaurar alarmes deletados.

---

### A7. XSS no `ErrorHandler.ts` — mensagem em `innerHTML`

Mensagens de erro (que podem vir do servidor) são inseridas diretamente via `innerHTML` sem escape.

**Arquivo:** `src/assets/js/utils/ui/ErrorHandler.ts:175-188`

---

### A8. XSS no `LoadingManager.ts` — parâmetro `message` em `innerHTML`

O parâmetro `message` de `showElement()` é injetado em `innerHTML` sem sanitização.

**Arquivo:** `src/assets/js/utils/ui/LoadingManager.ts:167-175`

---

### A9. Cobertura de testes insuficiente

Sem testes para:
- `AlarmeService`, `LocaisService`, `LookupService`
- Alarmes API (`api_alarmes.php`, `api_cadastrar_alarmes.php`, `api_editar_alarme.php`, `api_manutencao_alarmes.php`)
- Ações POST de manutenção (create_os, start_os, finalize_os)
- Upload de anexos (`api_upload_anexo.php`)
- Dashboard (`api_dashboard.php`) e relatórios
- Testes de integração que criam/alteram registros reais no DB

---

### A10. `session.cookie_secure` dependente de proxy

Atrás de reverse proxy sem `PROXY_TRUSTED_IPS` configurado, `isHttpsRequest()` retorna `false` e o cookie de sessão é enviado **sem a flag Secure**.

**Arquivo:** `config/app.php:64-65`

---

### A11. Enumeração de usuários via recuperação de senha

`recuperar_senha.php` retorna mensagens diferentes para usuário existente vs. inexistente, permitindo enumeração.

**Arquivo:** `auth/recuperar_senha.php:47,54`

---

## 🟡 MÉDIOS

### M1. CSRF token na URL

`listar_locais.php:125` passa `csrf_token` como query parameter GET, expondo-o em logs, histórico e referrer.

**Arquivo:** `resources/listar_locais.php:125`

---

### M2. SQL injection potencial via ORDER BY / LIMIT interpolados

- `api/v2/api_manutencao_cameras.php:195-197,230`
- `api/v2/api_manutencao_alarmes.php:192-194,225`
- `src/Services/LocaisService.php`
- `api/v2/api_alarmes.php:157`

`ORDER BY {$sortColumn}` e `LIMIT {$perPage} OFFSET {$offset}` são interpolados diretamente na string SQL. Embora mitigado por cast `(int)` e whitelist de colunas, o padrão é perigoso.

---

### M3. `api_dashboard.php` sem `userHasAccess()`

**Arquivo:** `api/v2/api_dashboard.php:13-15`

---

### M4. `session_check.php` não valida token contra DB

Apenas verifica timeout, não se o `session_token` corresponde ao armazenado.

**Arquivo:** `api/v2/session_check.php:20`

---

### M5. Search de alarmes — CAST sem índice

`CAST(a.conta AS CHAR) LIKE :busca7` em tabela grande é extremamente lento sem índice na expressão.

**Arquivo:** `api/v2/api_alarmes.php:74-104`

---

### M6. Parâmetros `page` vs `page_num` duplicados

`api_cameras.php:41` aceita ambos, causando confusão na API.

---

### M7. Memory leak — `ResizeObserver` nunca desconectado

`setupTopScrollbar()` em `relatorios.ts` cria um novo `ResizeObserver` a cada chamada sem desconectar o anterior.

**Arquivo:** `src/assets/js/relatorios.ts:440-442`

---

### M8. `showToast()` chamado como bare function

Em `listar_cameras.ts`, `showToast(...)` é chamado sem `window.` prefixo, mas a função só é registrada em `window.showToast`. Causa `ReferenceError` em runtime.

**Arquivo:** `src/assets/js/listar_cameras.ts:86,293,333,364,372`

---

### M9. Polling frágil para carregar modelos

`editar_cameras.ts` usa `setTimeout` polling de 120ms (máx 8 tentativas) em vez de await na Promise de carregamento.

**Arquivo:** `src/assets/js/editar_cameras.ts:533-544`

---

### M10. Modulo bias em `generateTemporaryPassword()`

`$chars[ord($bytes[$i]) % 59]` — caracteres 0-19 têm probabilidade 4/256 vs 20-58 têm 3/256. Viés de ~0,4%.

**Arquivo:** `inc/security.php:260-271`

---

### M11. Campo de busca limpo pós-request em relatórios

`relatorios.ts:108` limpa o campo `#pesquisa` após cada fetch — o usuário perde referência visual do que buscou.

---

### M12. `data-anexos-init` nunca removido

Após finalizar cadastro, `data-anexos-init` permanece, impedindo reinicialização da seção de anexos em novo salvamento.

**Arquivo:** `src/assets/js/cadastro_cameras.ts:736-744`

---

### M13. Sem validação de `conta` duplicada

`AlarmeService` não checa duplicidade de `conta` antes do INSERT. O erro se torna um 500 genérico.

**Arquivo:** `src/Services/AlarmeService.php:129-163`

---

### M14. `status` hardcoded em `AlarmeService`

`STATUS_PERMITIDOS` = `['Ativo', 'Inativo', 'Desligado', 'Manutenção', 'Pendente']` duplica a tabela `status` do banco. Pode dessincronizar.

**Arquivo:** `src/Services/AlarmeService.php:9,58-61`

---

## 🔵 BAIXOS / INFO

### B1. `console.log('Vite build system initialized')` em produção

**Arquivo:** `src/assets/js/main.ts:30`

---

### B2. Sem sourcemap na build

`sourcemap: false` em `vite.config.ts:46` — debugging em produção mais difícil.

---

### B3. Código utilitário duplicado em 14 bundles

Sem `manualChunks` no Vite, `ui-utils.ts`, `html.ts`, `fetchWithTimeout.ts` etc. são duplicados em cada entry point.

---

### B4. FK ausente: `locais.regiao_id` → `regioes(id)`

---

### B5. Type mismatch: `equipamentos.id BIGINT UNSIGNED` vs `locais.id INT`

---

### B6. CSP `unsafe-inline` para styles em development

`config/app.php:19-25`

---

### B7. Path do `.env` exibido em erro no dev

`config/config.php:80-88`

---

### B8. Sem `exit` após sucesso em actions de manutenção

`api_manutencao_cameras.php:365,401,496`

---

### B9. Tags `data-empty-msg` sem sanitização

`src/assets/js/utils/file-upload.ts:63`

---

### B10. Nenhum FK: `equipamentos_manutencoes.tecnico` vs `tecnico_id`

Ambos existem sem restrição de consistência.

---

### B11. `auditEvent()` — digitação `$mapaOperacao` em vez de `$mapaOperacao`

`inc/security.php:209`

---

### B12. Duas fontes de auditoria (PHP + triggers) inconsistentes

---

### B13. `api_excluir_camera.php` aceita JSON body E form POST

Inconsistente com o resto da API que só aceita JSON.

---

### B14. Rotação de CSRF token causa race condition em chamadas concorrentes

`inc/security.php:71-73`

---

## 📊 Resumo

| Severidade | Quantidade |
|-----------|-----------|
| 🔴 Crítico | 4 |
| 🟠 Alto | 11 |
| 🟡 Médio | 14 |
| 🔵 Baixo/Info | 14+ |

---

## 🥇 Top 10 — Prioridade de Correção

1. **Adicionar colunas faltantes** `regiao_id` e `horario_funcionamento` na tabela `locais` (ou remover referências) — **C1**
2. **Adicionar `requireApiCsrf()`** nas 7 APIs mutantes — **C2**
3. **Corrigir resposta dupla** em `api_excluir_camera.php` — **C3**
4. **Adicionar imports** de `ui-utils.ts` e `fetchWithTimeout.ts` nos entry points de manutenção — **C4**
5. **Adicionar `userHasAccess()`** em `api_cameras.php` e `api_alarmes.php` — **A2**
6. **Sanitizar XSS** em `ui-utils.ts` (DOMPurify ou safe HTML) — **A1**
7. **Filtrar `deleted_at IS NULL`** nas queries de manutenção — **A3**
8. **Desconectar `ResizeObserver`** em `relatorios.ts` — **M7**
9. **Corrigir `showToast`** — usar `window.showToast()` — **M8**
10. **Escrever testes** para services e APIs de alarmes — **A9**
