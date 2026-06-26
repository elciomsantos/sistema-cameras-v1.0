# ANALISE COMPLETA — sistema-cameras-v1.0

**Data:** 26/06/2026  
**Escopo:** Todos os arquivos PHP do projeto (config, auth, accounts, inc, api, resources, src, public)

---

## Resumo por Severidade

| Severidade | Quantidade | Impacto Principal |
|------------|-----------|-------------------|
| **CRITICO** | 9 | Senha nao alterada, sessoes bypassadas, acesso sem autenticacao, rate limiter ineficaz, LFI |
| **ALTO** | 22 | IDOR, CSRF reuso, XSS, race conditions, DoS por memoria, auditoria quebrada |
| **MEDIO** | 24 | Validacao ausente, performance, inconsistencias logicas, cache inseguro |
| **LOW/CONFIG** | 5 | Code smells, TLS desabilitado, contradicoes de headers |

---

## CRITICO — Pode travar o sistema ou permitir ataques graves

| # | Arquivo | Linha | Problema | Correcao |
|---|---------|-------|----------|----------|
| 1 | `auth/recuperar_senha.php` | 36-37 | `header()+exit` ANTES do UPDATE — senha nunca e alterada. Usuario ve "senha temporaria gerada" mas nada muda no BD. | Mover `header/exit` para APOS a query UPDATE (linha 49) |
| 2 | `inc/session_handler.php` | 153-156 | `enforceUniqueSession()` — se BD falha, `$valid = true` (fallback permite tudo). Sessoes invalidas sao aceitas. | Fail-closed: `$valid = false` em erro de BD |
| 3 | `inc/check_access.php` / `index.php` | 145 | Paginas NAO listadas em `access_rules.php` ficam SEM protecao. `trocar_senha`, `controle_*` e novas paginas sao acessiveis sem login. | Default-deny: `requererAcesso($pageAccess[$page] ?? 'user')` |
| 4 | `auth/login_submit.php` | 8 | `session_regenerate_id(true)` executado ANTES da validacao CSRF. Atacante pode forcar regeneracao em massa (DoS). | Mover para APOS autenticacao bem-sucedida |
| 5 | `auth/login_submit.php` | 125-129 | Erro de BD redireciona para `?page=404`. `$result['data']` pode ser null — `count(null)` gera warning e fluxo continua para verificacao de senha com dado indefinido. | Verificar `isset($result['data'])` antes de `count()`. Retornar erro generico |
| 6 | `src/Services/EquipamentoService.php` | 39-45 | `max(1, (int)($data['status_id'] ?? 0))` — IDs ausentes/zero viram `1` silenciosamente, bypassando `validateRequiredIds()`. Equipamento recebe status_id=1 (Ativo) sem querer. | Usar `null`/`0` como default; validar ANTES de aplicar defaults |
| 7 | `src/Api/RateLimiter.php` | 118-138 | Race condition TOCTOU: `read()` com LOCK_SH, depois `write()` com LOCK_EX — locks separados. Rate limiter bypassavel sob concorrencia. | Prender LOCK_EX durante todo read-modify-write |
| 8 | `resources/listar_locais.php` | 29 | `parse_str($_POST['filters'], $exportFilters)` — variavel injection via input do usuario. | Sanitizar/whitelisteer chaves permitidas de `$exportFilters` |
| 9 | `api/bootstrap-api.php` | 83-87 | Inclusao dinamica de arquivos sem whitelist — qualquer `api_*.php` e incluido. | Whitelist explicito de endpoints permitidos |

---

## ALTO — Risco de seguranca significativo

| # | Arquivo | Linha | Problema | Correcao |
|---|---------|-------|----------|----------|
| 10 | `auth/login_submit.php` | 113-115 | Falha no BD na tabela `login_attempts` bypassa completamente o rate limit. | Em caso de erro de BD, BLOQUEAR login (fail-closed) |
| 11 | `inc/security.php` | 33-47 | CSRF token NAO e consumido apos validacao — mesmo token pode ser reusado em ate 3 requests (janela=3). | `unset($_SESSION['csrf_tokens'][$key])` apos validacao bem-sucedida |
| 12 | `inc/security.php` | 115-117 | `userHasAccess()` Muta `$_SESSION['usuario']->nivel_acesso` — admin pode ser silenciosamente rebaixado a `user` | Nao mutar session no access check. Separar resolucao de mutacao |
| 13 | `inc/single_session.php` | 136-192 | `isUniqueSessionValid()` nao compara IP/User-Agent com valores armazenados — sessao sequestrada e indetectavel | Comparar IP e UA armazenados vs atuais; invalidar se divergirem |
| 14 | `inc/single_session.php` | 82-100 | `ON DUPLICATE KEY UPDATE` sobrescreve sessao ativa sem invalidar a antiga | Invalidar sessao antiga explicitamente antes de inserir nova |
| 15 | `inc/access_rules.php` | 14 | `recuperar_senha` exige `admin` — usuario comum sem self-service de recuperacao de senha | Tornar publica ou nivel `user` |
| 16 | `accounts/trocar_senha.php` | 7-13 | Sem `requererAcesso()`, sem `isUniqueSessionValid()` — sessao sequestrada acessa livremente | Adicionar ambos |
| 17 | `accounts/deletarUsuario.php` | 35 | `DELETE FROM usuarios` — hard delete, sem FK check, sem soft-delete | Usar soft-delete (`deleted_at`) ou verificar FKs antes |
| 18 | `api/v2/api_excluir_anexo.php` | 22-66 | IDOR — qualquer supervisor deleta anexo de qualquer departamento | Verificar escopo organizacional antes de deletar |
| 19 | `api/v2/api_excluir_camera.php` | 25-41 | IDOR — qualquer supervisor deleta/edita equipamento de qualquer escopo | Verificar secretaria/regiao do usuario |
| 20 | `api/v2/api_manutencao_alarmes.php` | 267-270 | Le `$_SESSION->nivel_acesso` diretamente em vez de `userHasAccess('supervisor')` — bypassa logica centralizada | Usar `userHasAccess('supervisor')` como todos os outros |
| 21 | `api/bootstrap-api.php` | 136-178 | CORS: `Access-Control-Allow-Origin` e `Allow-Credentials` NAO sao enviados mesmo para origens permitidas. CORS efetivamente same-origin only. | Adicionar headers CORS na resposta de sucesso |
| 22 | `src/Services/LocaisService.php` | 12,331 | `perPage` sem limite maximo; `searchAll()` sem LIMIT — DoS por exaustao de memoria | `min($perPage, 100)` e `LIMIT 5000` |
| 23 | `src/Services/EquipamentoService.php` | 140-161,224-268 | Race condition em `resolveLocation()` e `resolveModelo()` — criacao de duplicatas sob concorrencia | UNIQUE constraint + `ON DUPLICATE KEY UPDATE` |
| 24 | `src/Services/EquipamentoService.php` | 524-533 | `SET @app_user_id` e por conexao, nao por request. Se excecao ocorre antes de `reset()`, auditoria atribui ao proximo usuario. | Usar `try/finally` em todos os call sites |
| 25 | `src/Api/ApiResponse.php` | 59 | `uniqid(mt_rand())` — request ID previsivel. Facilita IDOR em sistemas que confiam no ID. | Usar `bin2hex(random_bytes(8))` |
| 26 | `src/Api/RateLimiter.php` | 133 | Array `timestamps` cresce sem limites sob trafego alto — 60K+ entradas por chave, causando latencia | Limitar tamanho do array a `$limit * 2` |
| 27 | `resources/` varios | — | `$page` (paginacao) sobrescreve `$page` (router) em `listar_locais`, `listar_escolas`, `listar_cmeis`, `listar_proprios` | Renomear para `$pageNum` |
| 28 | `resources/` varios | — | CSRF token ausente em forms JS-only (`cadastro_cameras`, `editar_cameras`, `manutencao_*`) | Adicionar `<input type="hidden" name="csrf_token">` e enviar header |
| 29 | `resources/` varios | — | `<?= $item->id ?>` sem `htmlspecialchars()` em atributos HTML — stored XSS se BD comprometido | `htmlspecialchars($item->id, ENT_QUOTES, 'UTF-8')` |
| 30 | `resources/editar_cameras.php` | 164+ | `$formData` referenciado mas NUNCA definido — campos LPR/DVR/Totem ficam vazios | Inicializar com dados do equipamento carregado |
| 31 | `api/v2/renovar_sessao.php` | 52 | `session_regenerate_id(true)` nao-atomico — concorrencia pode ver sessao invalida entre delete e insert | Usar `session_regenerate_id(false)` + delete explicito |

---

## MEDIO — Bugs de logica ou falhas que degradam o sistema

| # | Arquivo | Problema | Correcao |
|---|---------|----------|----------|
| 32 | `auth/login_submit.php` | Brute-force counter: read-modify-write nao-atomico. Duas requests concorrentes perdem incrementos | Usar `UPDATE SET attempt_count = attempt_count + 1` |
| 33 | `auth/login_submit.php` | `X-Forwarded-For`: confia no ultimo IP, nao percorre trusted proxies | Walk right-to-left pulando trusted proxies |
| 34 | `accounts/editarUsuario.php` | Admin pode desativar a si mesmo via checkbox "ativo" | Adicionar self-deactivation check |
| 35 | `accounts/editarUsuario.php` | `$beforeAudit` capturado mas NUNCA usado — edicao nao e auditada | Chamar `auditEvent()` com before/after |
| 36 | `inc/security.php` | `writeAuditLog()` cria diretorio com `0755` (world-readable) | Usar `0700` |
| 37 | `inc/session_handler.php` | `time()` chamado duas vezes — `$now` vs `time()` no `ultimo_acesso` | Usar `$now` consistentemente |
| 38 | `src/Services/LocaisService.php` | Subqueries correlatas por linha em `total_cameras`, `total_outros` — O(n) queries | Reescrever com `LEFT JOIN + GROUP BY` |
| 39 | `src/Services/LocaisService.php` | `create()/update()` sem validacao de formato (`email`, `cep`, `lat/lng`, `telefone`) | Adicionar `filter_var` para email, range para coords, regex para CEP |
| 40 | `api/v2/api_cep_lookup.php` | Cache com MD5 em `/tmp` — sem integridade, colisao, dir compartilhado | SHA256 + diretorio dedicado com perms restritas |
| 41 | `api/v2/` varios | LIKE wildcard injection — `%` e `_` no input do usuario nao sao escapados | `str_replace(['%','_'], ['\%','\_'], $input)` |
| 42 | `api/v2/` manutencao | `data_hora` nao validado como data — strings malformadas causam 500 ou `0000-00-00` | `DateTime::createFromFormat('Y-m-d H:i:s', $val)` |
| 43 | `api/v2/` varios | Campos texto sem limite de comprimento — strings de MBs causam erros de BD | Adicionar `max:255` ou limites por coluna |
| 44 | `api/v2/limpar_sessoes_orfas.php` | DELETE sem LIMIT — milhoes de registros travam a tabela | `LIMIT 1000` em loop |
| 45 | `api/v2/api_listar_anexos.php` | `formatFileSize()` definida dentro de try block — pode causar fatal se incluido duas vezes | Mover para fora do try ou usar `function_exists()` |
| 46 | `src/Services/AlarmeService.php` | Case mismatch entre whitelist e lookup de status — `MANUTENCAO` vs `Manutencao` insere `status_id=null` | Normalizar ambos para UPPER ou comparar case-insensitive |
| 47 | `src/Api/RequestValidator.php` | `validateIn` usa `===` mas params sao always-strings — `in:1,2,3` falha para int `1` | Cast params ou usar loose comparison com doc claro |
| 48 | `resources/cadastro_locais.php` | Sem validacao server-side de email, latitude/longitude, comprimento de campos | Adicionar `filter_var`, range checks, `mb_strlen` |
| 49 | `resources/listar_locais.php` | `<script>` sem CSP nonce — bloqueado pelo CSP em producao, export CSV nao funciona | Adicionar `nonce="<?= $CSP_NONCE ?>"` |
| 50 | `resources/relatorios_*.php` | Botao "Sair" redireciona para `home`, nao `logout` | Trocar para `?page=logout` |
| 51 | `public/index.php` | `X-Frame-Options: DENY` contradiz CSP `frame-ancestors 'self'` | Alinhar: ambos `SAMEORIGIN` ou ambos `none` |
| 52 | `accounts/` varios | `ob_end_clean()` sem `ob_start()` correspondente em todos os caminhos | Padronizar ou remover ob_* manual |
| 53 | `src/Services/EquipamentoService.php` | `rollback()` engole erros silenciosamente | Logar rollback failure em nivel `error` |
| 54 | `resources/editar_locais.php` | Mutar `$local` (objeto do BD) com input submetido sem validacao | Usar array `$formData` separado |
| 55 | `src/ErrorHandler.php` | Fatal handler: se `headers_sent()`, ecoa JSON apos HTML ja enviado — mixed content response | Se headers sent, nao emitir content |

---

## CONFIGURACAO / BAIXA PRIORIDADE

| # | Arquivo | Problema | Correcao |
|---|---------|----------|----------|
| 56 | `composer.json` | `"disable-tls": true` — comunicacao Composer sem TLS | Remover ou trocar para `false` |
| 57 | `config/config.php` | `$envDbPass === false` trata senha vazia como ausente — senha real vazia recusada | Separar checagem `=== false` de `=== ''` |
| 58 | `database.php` | `insert()` whitelists tabelas mas `query()` aceita SQL arbitrario — inconsistencia de API | Considerar mover logica de negocio para Services |
| 59 | `accounts/editarUsuario.php` | Temp password exibido sem `Cache-Control: no-store` — botao voltar do browser expoe senha | Adicionar `header('Cache-Control: no-store')` |
| 60 | `api/v2/swagger.php` | Swagger UI carrega JS/CSS externo de CDN sem SRI — supply chain risk | Vendorizar assets ou adicionar SRI hashes |

---

## TOP 5 CORRECOES PRIORITARIAS PARA EVITAR TRAVAMENTO EM PRODUCAO

1. **#1** — `recuperar_senha.php`: senha NUNCA e alterada (dead code critico)
2. **#5** — `login_submit.php`: `count(null)` + acesso a propriedade indefinida apos erro de BD
3. **#6** — `EquipamentoService.php`: IDs faltantes viram `1`, corrompendo dados silenciosamente
4. **#3** — `index.php`: paginas sem regra de acesso sao acessiveis sem login
5. **#2** — `session_handler.php`: falha de BD libera todas as sessoes
