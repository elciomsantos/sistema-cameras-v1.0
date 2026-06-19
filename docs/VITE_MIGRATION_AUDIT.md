# Auditoria Pós-Migração Vite — Sistema de Câmeras v1.0

**Data:** 19/06/2026  
**Escopo:** Análise completa do projeto após migração do build system para Vite 8. Inclui verificação de dependências, imports, integração PHP-JS, Service Worker, segurança e robustez.  
**Metodologia:** Revisão manual de todos os arquivos TS (31), CSS (11), PHP (50+) e configurações do projeto.  
**Convenção:** Itens marcados com `[VITE]` são novos (pós-migração). `[PRÉ-VITE]` são preexistentes. `[AMBOS]` são agravados pela migração.

**Status das correções:**
- ✅ **FIXED** (Fase 1): Itens #1 e #2 resolvidos em 19/06/2026
- 🔄 Em progresso
- ⏳ Pendente

---

## Resumo Executivo

| Severidade | Quantidade | Novos Vite | Pré-existentes | Corrigidos |
|---|---|---|---|---|
| CRÍTICO | 7 | 2 | 5 | ✅ 2/2 |
| ALTO | 11 | 4 | 7 | ✅ 4/11 |
| MÉDIO | 16 | 6 | 10 | ⏳ 0/16 |
| BAIXO | 10 | 4 | 6 | ⏳ 0/10 |
| MÉDIO | 16 | 6 | 10 | ⏳ 0/6 |
| BAIXO | 10 | 4 | 6 | ⏳ 0/4 |
| **Total** | **44** | **16** | **28** | **6/44** |

Os 2 problemas CRÍTICOS novos (`[VITE]` - Fase 1) foram corrigidos: assets Vite agora são copiados pós-build para `public/assets/` e o SW lê `sw-assets.json` dinamicamente. Na Fase 2 (Segurança), 4 itens ALTOS foram corrigidos: `htmlspecialchars` adicionado em 4 arquivos, controle de acesso padronizado em 10+ arquivos via `requererAcesso()`, e `access_rules.php` atualizado com páginas de locais. As correções restantes serão aplicadas incrementalmente.

---

## 1. CRÍTICO — Quebra funcionalidade em produção

### #1 — Assets do Vite dist/ inacessíveis pelo Apache `[VITE]` ✅ FIXED

**Arquivo:** `vite.config.ts:8`, `inc/assets.php:14`, `inc/header.php:50-51`

**Status 19/06/2026:** ✅ CORRIGIDO. Solução implementada:
- Criado `scripts/copy-assets.js` — copia `dist/assets/` (JS + CSS) para `public/assets/` pós-build
- `package.json` atualizado: `"build": "vite build && node scripts/copy-assets.js"`
- Arquivos `.gz` e subpastas legadas ignorados — apenas arquivos relevantes copiados
- `public/assets/js/main-DAd1vEXY.js` etc. agora existem e são servidos pelo Apache

### #2 — Service Worker pré-cachia caminhos legados, ignora build Vite `[VITE]` ✅ FIXED

**Arquivo:** `public/sw.js:10-18`

**Problema:** O SW pré-cachia caminhos que não existem quando Vite está ativo:

| SW caches (legado) | O que Vite gera | Status |
|---|---|---|
| `./assets/css/main.css` | `assets/style-aR3CP0Pa.css` | **MISMATCH** |
| `./assets/css/theme-enhancements.css` | Não existe no build Vite | **AUSENTE** |
| `./assets/js/main.js` | `assets/js/main-DAd1vEXY.js` | **MISMATCH** |
| `./assets/js/utils/ui/theme-manager.js` | Inlined no bundle Vite | **AUSENTE** |

Nenhum dos 5 entry points JS Vite nem o CSS hasheado são pré-cachados. Offline/PWA **não funciona** em produção Vite. O cache `cameras-app-v4` é estático — não sincroniza com novos hashes de build.

**Status 19/06/2026:** ✅ CORRIGIDO. Solução implementada:
- `scripts/copy-assets.js` agora gera `public/sw-assets.json` com URLs extraídas do `dist/.vite/manifest.json`
- `public/sw.js` atualizado para v5: lê `./sw-assets.json` no evento `install` e usa essas URLs para pré-cache
- Fallback para assets básicos caso `sw-assets.json` não esteja disponível
- Versionamento de cache incrementado (`cameras-app-v4` → `cameras-app-v5`) para invalidar cache antigo

---

### #3 — Senha real no .env.example (commitado no git) `[PRÉ-VITE]`

**Arquivo:** `.env.example:26`

**Problema:** A senha `ykB]Af4|2kHPU#;1g+?J3z)P]=p|AX=R` parece ser uma senha real, não placeholder. Está visível no repositório.

**Solução:** Substituir por `SUA_SENHA_AQUI` (já feito parcialmente no `.env.example` atual, mas verificar se credentials vazaram em git history).

---

### #4 — EquipamentoService omite colunas da migration 003 `[PRÉ-VITE]`

**Arquivo:** `src/Services/EquipamentoService.php:140,166`

**Problema:** `createLocation()` não inclui `regiao_id` nem `horario_funcionamento` no INSERT. `updateLocationFields()` não inclui `regiao_id` nem `horario_funcionamento` no UPDATE. Câmeras criadas/editadas via API perdem dados de região e horário do local.

**Solução:** Adicionar as colunas faltantes nas queries INSERT/UPDATE de `EquipamentoService`.

---

### #5 — Dados duplicados em auditoria (triggers + aplicação) `[PRÉ-VITE]`

**Arquivo:** Triggers AFTER INSERT/UPDATE/BEFORE DELETE na tabela `equipamentos` + `api_cadastrar_cameras.php:48`, `api_editar_camera.php:70`, `api_excluir_camera.php:51`

**Problema:** Cada operação de equipamento gera 2 registros de auditoria (trigger MySQL + `auditEvent()` manual). Desperdício de storage e confusão na leitura do log.

**Solução:** Remover triggers de auditoria em `equipamentos` (a aplicação já audita) ou remover as chamadas manuais de `auditEvent()` para equipamentos.

---

### #6 — Swagger com CORS aberto e sem autenticação `[PRÉ-VITE]`

**Arquivo:** `api/v2/swagger.php:17`

**Problema:** `header('Access-Control-Allow-Origin: *')` bypassa todas as restrições CORS e não requer autenticação, expondo a spec completa da API.

**Solução:** Remover CORS wildcard, requerer autenticação, ou desabilitar em produção.

---

### #7 — XSS em ui-utils.js innerHTML `[PRÉ-VITE]`

**Arquivo:** `public/assets/js/utils/ui-utils.js:93`

**Problema:** `bodyEl.innerHTML = htmlContent` recebe HTML cru como parâmetro. Se qualquer chamador passa conteúdo do usuário, é XSS direto.

**Solução:** Sanitizar `htmlContent` antes de atribuir ao innerHTML, ou usar `textContent` / DOM API segura.

---

## 2. ALTO — Segurança e bugs

### #8 — `htmlspecialchars` faltando em `BASE_URL` em 4 arquivos (XSS) `[VITE]`

**Arquivos:**
- `resources/cadastro_cameras.php:641-644` (4 tags `<script src>`)
- `resources/editar_cameras.php:497-500` (4 tags `<script src>`)
- `resources/listar_cameras.php:179` (1 tag)
- `resources/listar_alarmes.php:119` (1 tag)

**Problema:** `<?= BASE_URL ?>` sem `htmlspecialchars()` em tags `<script src=...>` nos branches de fallback legado. Se `BASE_URL` for influenciado por input ou misconfigurado, é vetor de script injection. Outras páginas usam `htmlspecialchars()` consistentemente.

**Solução:** Trocar `<?= BASE_URL ?>` por `<?= htmlspecialchars(BASE_URL, ENT_QUOTES, 'UTF-8') ?>` nas 10 ocorrências.

---

### #9 — 7 páginas sem integração Vite (carregam apenas JS legado) `[VITE]`

**Arquivos:**
- `resources/listar_cameras.php`
- `resources/listar_alarmes.php`
- `resources/relatorios_cameras.php`
- `resources/relatorios_alarmes.php`
- `resources/auditoria_cameras.php`
- `resources/cadastro_alarmes.php`
- `resources/editar_alarmes.php`

**Problema:** Essas páginas NÃO usam `vite_asset()` / `vite_entry_imports()`. Em produção com Vite, carregam sempre os scripts legados de `public/assets/js/` — sem minificação, sem tree-shaking, sem hashes para cache busting, sem code splitting.

**Solução:** Adicionar entry points no `vite.config.ts` para os módulos faltantes e usar o padrão `vite_asset()` + fallback condicional nas páginas PHP — mesmo padrão já adotado em `cadastro_cameras.php`, `editar_cameras.php`, etc.

---

### #10 — `controle_alarmes.php` sem controle de acesso `[VITE]`

**Arquivo:** `resources/controle_alarmes.php`

**Problema:** Qualquer usuário autenticado (incluindo nível "user") pode ver o painel de alarmes. Sem `requererAcesso()` ou verificação de sessão. Outras páginas de controle usam verificação.

**Solução:** Adicionar `requererAcesso('user')` no mínimo, ou `requererAcesso('supervisor')` se o painel deve ser restrito.

---

### #11 — Controle de acesso inconsistente em 10+ arquivos `[VITE]`

**Arquivos:**
- `resources/cadastro_locais.php:8`
- `resources/editar_locais.php:8-9`
- `resources/controle_cameras.php:9`
- `resources/listar_locais.php:8`
- `resources/listar_proprios.php:8`
- `resources/listar_operadoras.php:8`
- `resources/listar_escolas.php:8`
- `resources/listar_cmeis.php:8`
- `resources/listar_cameras.php:5,12-13`
- `resources/listar_alarmes.php:5,10-11`
- `resources/home.php:209`

**Problema:** Usam `$_SESSION['usuario']->nivel_acesso !== 'admin'` manualmente em vez de `requererAcesso()`. Se as regras mudam em `inc/access_rules.php`, esses arquivos ficam dessincronizados e podem permitir acesso não autorizado. Arquivos modernos (`cadastro_alarmes.php`, `editar_alarmes.php`, `manutencao_*.php`, `auditoria_cameras.php`) já usam `requererAcesso()`.

**Solução:** Padronizar todas as verificações com `requererAcesso()` e adicionar entradas faltantes em `inc/access_rules.php`.

---

### #12 — `api_cameras.php` não filtra soft-delete `[PRÉ-VITE]`

**Arquivo:** `api/v2/api_cameras.php:48`

**Problema:** `whereParts` não inclui `e.deleted_at IS NULL`, fazendo câmeras excluídas aparecerem na listagem. `api_dashboard.php` filtra corretamente.

**Solução:** Adicionar filtro `e.deleted_at IS NULL` no WHERE default.

---

### #13 — `api_relatorios_cameras.php` sem filtro de soft-delete `[PRÉ-VITE]`

**Arquivo:** `api/v2/api_relatorios_cameras.php:87`

**Problema:** Relatórios incluem equipamentos excluídos logicamente.

**Solução:** Adicionar `e.deleted_at IS NULL` no WHERE.

---

### #14 — `cadastro_cameras.js` crash ao não encontrar form `[PRÉ-VITE]`

**Arquivo:** `public/assets/js/cadastro_cameras.js:11`

**Problema:** `this.tipoSelect = this.form.querySelector(...)` executa ANTES do null check em `init():18`. Se `formCadastroCamera` não existe no DOM → TypeError.

**Solução:** Mover as queries DOM para dentro de `init()`, após o null check do form.

---

### #15 — `cadastro_cameras.js` — submitToAPI não checa `response.ok` `[PRÉ-VITE]`

**Arquivo:** `public/assets/js/cadastro_cameras.js:681`

**Problema:** `await response.json()` sem verificar `response.ok` — resposta não-JSON causa `SyntaxError` não tratado.

**Solução:** Adicionar `if (!response.ok) throw new Error(...)` antes de `.json()`.

---

### #16 — Race condition ao carregar modelos por marca `[PRÉ-VITE]`

**Arquivo:** `public/assets/js/cadastro_cameras.js:232`

**Problema:** Trocas rápidas de marca disparam fetches concorrentes sem cancelamento. Respostas fora de ordem podem popular dropdown com modelos da marca errada.

**Solução:** Usar `AbortController` para cancelar fetch anterior ao trocar marca.

---

### #17 — Access rules faltando para páginas de locais `[PRÉ-VITE]`

**Arquivo:** `inc/access_rules.php`

**Problema:** `listar_locais`, `cadastro_locais`, `editar_locais`, `listar_escolas`, `listar_cmeis`, `listar_operadoras`, `listar_proprios` não têm restrição no mapa de access_rules. Qualquer user logado acessa.

**Solução:** Adicionar entradas no `$pageAccess` com nível mínimo `supervisor`.

---

## 3. MÉDIO — Robusteza e eficiência

### #18 — `AbortSignal.any` pode não existir no navegador `[VITE]`

**Arquivo:** `src/assets/js/utils/fetchWithTimeout.ts:22`

**Problema:** `AbortSignal.any` só existe em browsers modernos (Chrome 93+, Firefox 100+). O target ES2020 não garante suporte. Tem fallback parcial (`controller.signal`) mas o `init.signal` do chamador é descartado em browsers sem suporte.

**Solução:** Implementar fallback manual que combina sinais quando `AbortSignal.any` não existe.

---

### #19 — jQuery não está no `package.json` `[VITE]`

**Arquivos:** `src/assets/js/relatorios.ts`, `src/assets/js/relatorios_alarmes.ts` usam `$(document).ready(...)`

**Problema:** jQuery é carregado via CDN condicionalmente pelo PHP. Se falhar ou ordem de carregamento mudar, o JS quebra silenciosamente. Além disso, `relatorios_alarmes.php` **não** carrega jQuery do CDN (diferença de `relatorios_cameras.php` que carrega).

**Solução:** Adicionar jQuery no `package.json` OU refatorar para vanilla JS. Padronizar carregamento de jQuery nas duas páginas de relatórios.

---

### #20 — `escapeHtml` duplicado em 7+ arquivos TS `[VITE]`

**Arquivos:** `ui-utils.ts`, `manutencao-utils.ts`, `relatorios.ts`, `relatorios_alarmes.ts`, `listar_cameras.ts`, `listar_alarmes.ts`, `auditoria-cameras.ts`, `editar_alarmes.ts`

**Problema:** A mesma função `escapeHtml` é re-declarada localmente em cada arquivo. Divergências sutis entre implementações são possíveis.

**Solução:** Consolidar em um único módulo compartilhado (ex: `src/assets/js/utils/html.ts`) e importar de lá.

---

### #21 — Proxy Vite hardcoded para `localhost:80` `[VITE]`

**Arquivo:** `vite.config.ts:37`

**Problema:** O proxy de desenvolvimento `/api` aponta fixo para `http://localhost:80`. Diferentes ambientes podem usar portas diferentes.

**Solução:** Usar variável de ambiente: `target: process.env.VITE_API_URL || 'http://localhost:80'`.

---

### #22 — `tsconfig.json` declara alias `@/*` mas `vite.config.ts` não configura `[VITE]`

**Arquivo:** `tsconfig.json:19` (`paths: { "@/*": ["src/*"] }`), `vite.config.ts` (sem `resolve.alias`)

**Problema:** Import com `@/` compila no TypeScript mas falha em runtime no Vite. Atualmente sem impacto (ninguém usa), mas risco latente se alguém adotar o alias.

**Solução:** Adicionar `resolve: { alias: { '@': path.resolve(__dirname, 'src') } }` no `vite.config.ts`, ou remover do `tsconfig.json`.

---

### #23 — Chart.js carregado via CDN e pelo Vite (duplo) `[VITE]`

**Arquivo:** `resources/home.php:244` — CDN carregado incondicionalmente

**Problema:** Chart.js é importado via `import Chart from 'chart.js'` no TS (empacotado pelo Vite) E carregado via CDN `<script>` na página `home.php`. Carga duplicada aumenta peso da página.

**Solução:** Mover o CDN `<script>` para dentro do else (fallback legado) ou remover se Vite já empacota chart.js.

---

### #24 — Nomes de tabela dinâmicos em queries SQL `[VITE]`

**Arquivos:** `resources/cadastro_cameras.php:31`, `resources/editar_cameras.php:34`

**Problema:** `"SELECT * FROM $tabela ORDER BY $order"` interpola variável. Os valores vêm de array hardcoded, não exploitável hoje, mas padrão frágil.

**Solução:** Usar `switch`/`match` com whitelist de nomes permitidos em vez de interpolação direta.

---

### #25 — LIKE hardcoded para filtrar tipos de local `[VITE]`

**Arquivos:** `resources/listar_escolas.php:19`, `resources/listar_cmeis.php:19`

**Problema:** `WHERE l.nome LIKE '%ESCOLA%'` / `LIKE '%CMEI%'` são frágeis — locais com essas palavras no nome são incluídos indevidamente. `listar_proprios.php` faz corretamente via `tipo_local_id`.

**Solução:** Usar `tipo_local_id` em vez de LIKE no nome.

---

### #26 — `showToast` duplicado `[PRÉ-VITE]`

**Arquivos:** `public/assets/js/main.js:1-36`, `public/assets/js/utils/ui-utils.js:18-43`

**Problema:** Duas implementações de `showToast`. O que carrega por último sobrescreve a outra.

**Solução:** Consolidar em `ui-utils.js` apenas, remover de `main.js`.

---

### #27 — `uppercase.js` acumula listeners duplicados `[PRÉ-VITE]`

**Arquivo:** `public/assets/js/utils/uppercase.js:14`

**Problema:** Cada chamada de `aplicarUppercaseUniversal()` adiciona NOVOS listeners `input` sem verificar se já existem. Após toggle de modelo 2x, cada campo tem 3 listeners.

**Solução:** Adicionar `dataset` flag no campo para evitar listeners duplicados (`if (el.dataset.uppercaseBound) return;`).

---

### #28 — `listar_cameras.js` — DOMContentLoaded pode nunca disparar `[PRÉ-VITE]`

**Arquivo:** `public/assets/js/listar_cameras.js:399`

**Problema:** IIFE executa e registra `DOMContentLoaded`. Se o script carrega com `defer` ou no final do body, o evento já disparou e a inicialização falha silenciosamente.

**Solução:** Verificar `document.readyState !== 'loading'` antes de registrar o listener.

---

### #29 — `putenv()` sem validação `[PRÉ-VITE]`

**Arquivo:** `config/config.php:3-47`

**Problema:** Valores do `.env` são carregados diretamente via `putenv()` sem sanitização. Valores maliciosos poderiam afetar lógica downstream.

**Solução:** Validar formato/tipo dos valores críticos antes de definir constantes.

---

### #30 — API relatórios sem cast de tipos `[PRÉ-VITE]`

**Arquivo:** `api/v2/api_relatorios_cameras.php:51-56`

**Problema:** `$_GET['status']`, `$_GET['local']`, `$_GET['regiao']` não são convertidos para `(int)` antes de ir para query parametrizada.

**Solução:** Adicionar `(int)` cast nos parâmetros GET antes de passar para queries.

---

### #31 — Migrations não-idempotentes `[PRÉ-VITE]`

**Arquivos:** `config/DB/migrations/001_fulltext_indexes.sql`, `003_locais_regiao_horario.sql`

**Problema:** `ALTER TABLE ADD INDEX` e `ADD COLUMN` falham na segunda execução (nomes duplicados).

**Solução:** Usar `IF NOT EXISTS` (MariaDB) ou wrapping condicional verificando `information_schema`.

---

### #32 — `central_alarmes` com status/região em texto livre `[PRÉ-VITE]`

**Problema:** `status VARCHAR(20)` e `regiao VARCHAR(50)` sem FK para as tabelas `status` e `regioes`. Inconsistente com `equipamentos` que usa `status_id`/`regiao_id`.

**Solução:** Normalizar para FK IDs (mudança de schema + refactor queries).

---

### #33 — `setTimeout(500)` fixo em preencherFormulario `[PRÉ-VITE]`

**Arquivo:** `public/assets/js/cadastro_cameras.js:793`

**Problema:** Espera 500ms fixo para setar valor do select de modelos. Deveria aguardar a Promise de `carregarModelosExistentes`.

**Solução:** Usar `await` na Promise de carregamento de modelos em vez de timeout fixo.

---

## 4. BAIXO — Limpeza e consistência

### #34 — SW cache version `v4` hardcoded `[VITE]`

**Arquivo:** `public/sw.js:6-8`

**Problema:** Cache version estático não sincroniza com build Vite. Novos hashes de asset não invalidam cache antigo.

**Solução:** Incrementar versão do cache a cada build ou gerar dinamicamente com hash do manifest.

---

### #35 — `listar_cameras.php` — queries DB inline no template HTML `[VITE]`

**Arquivo:** `resources/listar_cameras.php:48-57`

**Problema:** Queries SQL executadas diretamente dentro do HTML template para popular `<select>` de filtros. Mistura de concerns e dificulta auditoria.

**Solução:** Mover queries para camada de serviço ou pré-carregar dados antes de renderizar.

---

### #36 — Parâmetros posicionais (`?`) em `listar_proprios.php` `[VITE]`

**Arquivo:** `resources/listar_proprios.php:22-24`

**Problema:** Usa `?` placeholders enquanto todo o resto do projeto usa named params (`:param`). Funcional mas inconsistente.

**Solução:** Converter para named params `:param`.

---

### #37 — `PER_PAGE` e `currentPage` sem declaração explícita em TS `[VITE]`

**Arquivos:** `src/assets/js/listar_cameras.ts:33`, `src/assets/js/listar_alarmes.ts:33`

**Problema:** Variáveis globais injetadas pelo PHP template não são declaradas com fallback no TS. Se o PHP falhar em injetá-las, o JS crasha com `ReferenceError`.

**Solução:** Declarar com fallback: `const PER_PAGE = (window as any).PER_PAGE || 10;`

---

### #38 — Null guards faltando em casts `as HTMLInputElement` `[VITE]`

**Arquivos:** `src/assets/js/cadastro_cameras.ts`, `src/assets/js/editar_cameras.ts`

**Problema:** `document.getElementById(...) as HTMLInputElement` sem verificar null. Se o elemento não existe no DOM, TypeError.

**Solução:** Adicionar `if (!el) return;` antes do cast ou usar early return no init.

---

### #39 — `home.php` expõe link admin via `$_SESSION` direto `[VITE]`

**Arquivo:** `resources/home.php:209`

**Problema:** `$_SESSION['usuario']->nivel_accesso ?? '' === 'admin'` em vez de `userHasAccess('admin')`. Vaza existência do painel admin no HTML source.

**Solução:** Usar `userHasAccess('admin')` consistentemente.

---

### #40 — Toast tipo `'error'` não existe `[PRÉ-VITE]`

**Arquivo:** `public/assets/js/cadastro_cameras.js:668`

**Problema:** `showToast('...', 'error')` — os mapas só suportam `success`, `danger`, `warning`, `info`. Cai para `info`, mostrando ícone azul para erro crítico.

**Solução:** Trocar `error` por `danger` em todas as chamadas.

---

### #41 — `fetchWithTimeout.js` fallback descarta sinal do chamador `[PRÉ-VITE]`

**Arquivo:** `public/assets/js/utils/fetchWithTimeout.js:22`

**Problema:** Em browsers sem `AbortSignal.any`, `controller.signal` ignora o `init.signal` do chamador original.

**Solução:** Implementar composição manual de sinais no fallback.

---

### #42 — Console logs com emojis em produção `[PRÉ-VITE]`

**Arquivos:** Vários arquivos TS e JS legado

**Problema:** `console.log('✅ ...')`, `console.error('❌ ...')` etc. Embora `header.php` suprima logs em produção, os emojis ainda estão no bundle.

**Solução:** Remover emojis dos console logs ou usar `legalComments: 'none'` (já configurado).

---

### #43 — Scripts legados faltando `uppercase.js` nos fallbacks `[VITE]`

**Arquivos:** `resources/manutencao_cameras.php:399-401`, `resources/manutencao_alarmes.php:404-408`

**Problema:** Fallbacks legados dessas páginas não incluem `uppercase.js`, enquanto `cadastro_cameras.php` e `editar_cameras.php` incluem. Se formulários dessas páginas precisam de uppercase, é bug funcional.

**Solução:** Adicionar `uppercase.js` nos fallbacks consistente com as outras páginas.

---

### #44 — `controle_cameras.php` manual check sem `exit` consistente `[VITE]`

**Arquivo:** `resources/controle_cameras.php:4-12`

**Problema:** Verificação manual de acesso redireciona mas o conteúdo pode renderizar parcialmente em edge cases com output buffering.

**Solução:** Usar `requererAcesso()` que trata redirecionamento e `exit` corretamente.

---

## 5. Melhorias Sugeridas

### Segurança

| # | Melhoria | Prioridade |
|---|---|---|
| S1 | Headers de segurança na API bootstrap (X-Frame-Options, HSTS, CSP) | Média |
| S2 | Password policy → exigir caracteres especiais e maiúsculas (atual aceita `password1`) | Média |
| S3 | IP spoofing em `single_session.php` → usar primeiro IP não-confiável da cadeia X-Forwarded-For | Baixa |
| S4 | `ativarUsuario.php` → prevenir auto-ativação | Média |
| S5 | RateLimiter file-based → não usar `sys_get_temp_dir()` em shared hosting | Baixa |

### Código

| # | Melhoria | Prioridade |
|---|---|---|
| C1 | Consolidar `showToast` → manter apenas em `ui-utils.js`/`ui-utils.ts`, remover duplicata | Média |
| C2 | Adicionar `AbortController` nas chamadas fetch de modelos e listagem | Média |
| C3 | Verificar `response.ok` em todos os fetch antes de `.json()` | Alta |
| C4 | Adicionar guard no constructor de `cadastro_cameras`/`editar_cameras` para form null | Média |
| C5 | Refatorar jQuery `$(document).ready()` para vanilla `DOMContentLoaded` | Baixa |
| C6 | Criar helper `handleApiError(response)` reutilizável | Baixa |

### Banco de Dados

| # | Melhoria | Prioridade |
|---|---|---|
| D1 | Adicionar FK em `equipamentos_anexos.manutencao_camera_id` e `manutencao_alarme_id` | Média |
| D2 | Adicionar UNIQUE constraint em `locais(nome, secretaria_id)` para prevenir duplicatas | Média |
| D3 | Tornar migrations idempotentes | Baixa |
| D4 | Remover triggers de auditoria em `equipamentos` ou chamadas manuais de `auditEvent()` | Média |
| D5 | Normalizar `central_alarmes` → converter `status` e `regiao` para FK IDs | Baixa |

### Arquitetura

| # | Melhoria | Prioridade |
|---|---|---|
| A1 | Região duplicada em `locais` e `equipamentos` → definir qual é autoritativo, documentar | Baixa |
| A2 | Adicionar `access_rules` para todas as páginas de locais (supervisor mínimo) | Alta |
| A3 | Mover queries DB de templates PHP para camada de serviço | Baixa |
| A4 | Adicionar script `postbuild` para copiar `dist/assets/` → `public/assets/` | Alta |
| A5 | Configurar SW para ler manifest.json pós-build | Média |

---

## 6. Plano de Implementação por Fases

### Fase 1 — Produção Vite funcional (CRÍTICO) ✅ CONCLUÍDA (19/06/2026)

| Item | Ação | Arquivos alterados | Status |
|---|---|---|---|
| #1 | Servir dist/ assets pelo Apache | `scripts/copy-assets.js` (novo), `package.json` | ✅ |
| #2 | Atualizar SW para cachear paths Vite | `public/sw.js`, `public/sw-assets.json` (gerado) | ✅ |

### Fase 2 — Segurança (ALTO) ✅ CONCLUÍDA (19/06/2026)

| Item | Ação | Arquivos alterados | Status |
|---|---|---|---|
| #3 | Remover senha real do .env.example | `.env.example` (já estava corrigido) | ✅ |
| #5 | Eliminar auditoria duplicada | Triggers + PHP API (adiado: requer mudança DB) | ⏳ |
| #6 | Restringir Swagger CORS | `api/v2/swagger.php` (já estava corrigido) | ✅ |
| #8 | Adicionar htmlspecialchars em BASE_URL | `cadastro_cameras.php`, `editar_cameras.php`, `listar_cameras.php`, `listar_alarmes.php` | ✅ |
| #10 | Adicionar acesso em controle_alarmes | `inc/access_rules.php` (já tinha entrada `supervisor`) | ✅ |
| #11 | Padronizar controle de acesso | `cadastro_locais.php`, `editar_locais.php`, `listar_locais.php`, `listar_proprios.php`, `listar_operadoras.php`, `listar_escolas.php`, `listar_cmeis.php`, `controle_cameras.php`, `listar_cameras.php`, `listar_alarmes.php`, `home.php` | ✅ |
| #17 | Adicionar access_rules para locais | `inc/access_rules.php` | ✅ |

### Fase 3 — Integração Vite completa (ALTO)

| Item | Ação | Arquivos a alterar |
|---|---|---|
| #9 | Adicionar entry points + vite_asset() nas 7 páginas | `vite.config.ts`, 7 resources/*.php |
| #23 | Mover Chart.js CDN para fallback condicional | `resources/home.php` |
| #43 | Adicionar uppercase.js nos fallbacks faltantes | 2 resources/*.php |

### Fase 4 — Robusteza JS (MÉDIO)

| Item | Ação | Arquivos a alterar |
|---|---|---|
| #14 | Guard em cadastro_cameras form null | `cadastro_cameras.ts` |
| #15 | Checar response.ok antes de .json() | Arquivos TS com fetch |
| #18 | AbortSignal.any fallback | `fetchWithTimeout.ts` |
| #37 | Declarar PER_PAGE com fallback | `listar_cameras.ts`, `listar_alarmes.ts` |
| #38 | Null guards em HTMLElement casts | `cadastro_cameras.ts`, `editar_cameras.ts` |

### Fase 5 — Consistência (MÉDIO/BAIXO)

| Item | Ação | Arquivos a alterar |
|---|---|---|
| #4 | Adicionar regiao_id/horario no EquipamentoService | `EquipamentoService.php` |
| #12-13 | Filtro soft-delete nas APIs | `api_cameras.php`, `api_relatorios_cameras.php` |
| #20 | Consolidar escapeHtml | Novo `utils/html.ts` + 7 arquivos |
| #21 | Proxy Vite via env var | `vite.config.ts` |
| #22 | Sync tsconfig/vite alias | `vite.config.ts` ou `tsconfig.json` |
| #24-25 | Refatorar queries dinâmicas | 3 resources/*.php |
| #26 | Remover showToast duplicado | `main.ts` |
| #27 | uppercase.js com flag anti-duplicata | `uppercase.ts` |
| #36 | Named params em listar_proprios | `listar_proprios.php` |
| #39 | userHasAccess() em home.php | `home.php` |
| #40 | Toast 'error' → 'danger' | Arquivos TS |

### Fase 6 — Limpeza (BAIXO)

| Item | Ação | Arquivos a alterar |
|---|---|---|
| #7 | Sanitizar innerHTML em ui-utils | `ui-utils.ts` |
| #19 | Refatorar jQuery para vanilla | `relatorios.ts`, `relatorios_alarmes.ts` |
| #28-33 | Fixes menores | Diversos |
| #34 | Atualizar SW cache version | `sw.js` |
| #42 | Remover emojis de console logs | Diversos |

---

## 7. Mapeamento de Integração Vite por Página

| Página PHP | Usa `vite_asset()`? | Entry Vite | Carrega CDN? | Notas |
|---|---|---|---|---|
| `home.php` | SIM (`home-dashboard`) | `entries/home-dashboard.ts` | Chart.js CDN sempre | CDN duplica se Vite empacota |
| `cadastro_cameras.php` | SIM (`cadastro-cameras`) | `entries/cadastro-cameras.ts` | Não | BASE_URL sem escape no fallback |
| `editar_cameras.php` | SIM (`editar-cameras`) | `entries/editar-cameras.ts` | Não | BASE_URL sem escape no fallback |
| `manutencao_cameras.php` | SIM (`manutencao-cameras`) | `entries/manutencao-cameras.ts` | Não | Fallback sem uppercase.js |
| `manutencao_alarmes.php` | SIM (`manutencao-alarmes`) | `entries/manutencao-alarmes.ts` | Não | Usa CSP nonce inline |
| `listar_cameras.php` | NAO | — | Não | BASE_URL sem escape |
| `listar_alarmes.php` | NAO | — | Não | BASE_URL sem escape |
| `relatorios_cameras.php` | NAO | — | jQuery CDN | Sem integração Vite |
| `relatorios_alarmes.php` | NAO | — | Não | Sem jQuery (assimétrico) |
| `auditoria_cameras.php` | NAO | — | Não | Sem integração Vite |
| `cadastro_alarmes.php` | NAO | — | Não | Assimétrico vs câmeras |
| `editar_alarmes.php` | NAO | — | Não | Assimétrico vs câmeras |

---

## 8. Dependências `window` globais (JS ↔ PHP)

### Globais definidas pelo PHP (header.php)

| Global | Valor | Usada por |
|---|---|---|
| `window.BASE_URL` | Path público do Apache | Quase todos os módulos TS |
| `window.APP_API_BASE` | `BASE_URL + 'index.php?page=api/'` | Chamadas de API |
| `window.FETCH_TIMEOUT` | `30000` | `fetchWithTimeout.ts` |
| `window.CSRF_TOKEN` | Função que lê `<meta>` | Chamadas POST |
| `window.bootstrap` | Bootstrap 5 CDN | Modais, toasts, dropdowns |

### Globais definidas pelo TS

| Global | Definida em | Consumida por |
|---|---|---|
| `window.fetchWithTimeout` | `fetchWithTimeout.ts` | Todos os módulos com fetch |
| `window.showToast` | `ui-utils.ts` | Operações CRUD |
| `window.showConfirm` | `ui-utils.ts` | Confirmações de exclusão |
| `window.escapeHtml` | `manutencao-utils.ts` | Renderização de dados |
| `window.aplicarUppercaseUniversal` | `uppercase.ts` | Transformação de inputs |
| `window.uploadFile` | `file-upload.ts` | Upload de anexos |
| `window.DashboardCore` | `dashboard-core.ts` | Inicialização do dashboard |
| `window.ChartManager` | `dashboard-charts.ts` | Gerenciamento de gráficos |
| `window.ErrorHandler` | `ErrorHandler.ts` | Tratamento centralizado |
| `window.LoadingManager` | `LoadingManager.ts` | Indicadores de loading |
| `window.showSuccessModal` | `showSuccessModal.ts` | Modal de sucesso pós-criação |

### Globais injetadas por templates PHP (sem window prefix)

| Global | Template | Módulos consumidores |
|---|---|---|
| `PER_PAGE` | `listar_cameras.php`, `listar_alarmes.php` | Reflexo do PHP sem declaração TS |
| `currentPage` | `listar_cameras.php`, `listar_alarmes.php` | Reflexo do PHP sem declaração TS |

**Risco:** Se qualquer global falhar ao ser injetada, o JS crasha com `ReferenceError`. Não há declarações de fallback no TypeScript.

---

## 9. Entry Points Vite vs. Manifest (consistência)

| Entry no `vite.config.ts` | Nome no manifest | `vite_asset()` encontra? |
|---|---|---|
| `main` (via index.html) | `main` | SIM (via `name === 'main'`) |
| `manutencao-cameras` | `manutencao-cameras` | SIM |
| `manutencao-alarmes` | `manutencao-alarmes` | SIM |
| `home-dashboard` | `home-dashboard` | SIM |
| `cadastro-cameras` | `cadastro-cameras` | SIM |
| `editar-cameras` | `editar-cameras` | SIM |
| CSS: `vite_css()` | `style.css` | SIM |

**Nota:** A lookup de `main` é um edge case — a key no manifest é `index.html` (não termina em `.ts`), mas funciona via `$entry['name'] === 'main'`. Se a Vite mudar o formato do manifest, pode quebrar.
