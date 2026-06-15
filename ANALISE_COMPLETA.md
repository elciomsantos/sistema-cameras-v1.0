# Análise Completa — Sistema Câmeras v1.0

**Data:** 14/06/2026
**Escopo:** Todos os arquivos PHP (~80), JS (26), CSS (12), Service Worker, configurações e infraestrutura

---

## Visão Geral do Projeto

Sistema de gerenciamento de câmeras de segurança e alarmes (CFTV/GML), construído em **PHP 8.2+ custom** (sem framework), **MySQL 8.0**, **JavaScript vanilla (ES6+)**, com front controller pattern via `public/index.php` e roteamento por `?page=`. Três níveis de acesso (user, supervisor, admin). CSRF, CSP, sessões seguras, rate limiting e audit logging implementados.

**Stack:** PHP 8.2+ / MySQL 8.0 / Bootstrap (CDN) / Monolog / PHPUnit 10 / Vitest / PHPStan L5 / Docker + Nginx / GitHub Actions CI

---

## Índice

1. [Falhas Críticas](#1-falhas-críticas)
2. [Falhas de Alta Severidade](#2-falhas-de-alta-severidade)
3. [Falhas de Severidade Média](#3-falhas-de-severidade-média)
4. [Falhas de Baixa Severidade / Info](#4-falhas-de-baixa-severidade--info)
5. [Problemas Específicos por Camada](#5-problemas-específicos-por-camada)
6. [Hall de Pontos Positivos](#6-hall-de-pontos-positivos)
7. [Plano de Melhorias](#7-plano-de-melhorias)

---

## 1. Falhas Críticas

Corrigir imediatamente — causam erros de runtime, corrupção de dados ou vulnerabilidades exploráveis.

| # | Arquivo | Linha(s) | Problema | Impacto |
|---|---------|----------|----------|---------|
| C1 | `public/sw.js` | 99 | `BASE_URL` indefinido no Service Worker (não existe `window`) — gera `ReferenceError` no evento `sync` | SW quebra em background sync |
| C2 | `public/sw.js` | 15 | Cache inclui `AlarmeSearch.js` que **não existe** no projeto — `cache.addAll()` falha e `.catch(()=>{})` esconde o erro | Cache incompleto, app quebra offline |
| C3 | `public/assets/js/utils/file-upload.js` | 35, 78, 84, 219 | **XSS**: URLs de anexo inseridas em atributos HTML sem `escapeHtml()` — aspa dupla no URL quebra o atributo e permite injeção de HTML/JS | Execução arbitrária de JS no navegador do usuário |
| C4 | `public/assets/js/relatorios_alarmes.js` | 323 | `}` extra fecha `$(document).ready()` prematuramente — todo código após linha 323 executa **fora** do ready, DOM pode não existir | Página de relatórios de alarmes com listeners mortos e erros de null reference |
| C5 | `src/Services/EquipamentoService.php` | 38-46 | `extractCommonData` usa `max(1, (int)$val)` — IDs ausentes são silenciosamente convertidos para `1`, **corrompendo relacionamentos** | Registros criados com FK errada (status, região, procedimento sempre ID 1) |
| C6 | `src/Services/EquipamentoService.php` | 370 | Quando `$tipoCameraId===3` E `$tipoId===3`, dados LPR são inseridos para equipamento DVR — **erro lógico** | DVR cadastrado com dados de LPR; dados de DVR perdidos |
| C7 | `config/database.php` | 98-105 | `query()` retorna array de erro em vez de lançar exceção — chamadores que não verificam `$result['status']` causam **falhas silenciosas** | Operações DB falham sem aviso; dados inconsistentes |
| C8 | `config/database.php` | 30 | `MYSQL_ATTR_SSL_VERIFY_SERVER_CERT=true` **desabilita** verificação de certificado SSL | Conexão DB vulnerável a MITM em produção |
| C9 | `api/bootstrap-api.php` | 114-116 | Rate limiter falha **aberto** (permite tudo) quando armazenamento em arquivo falha — sem fallback restritivo | Atacante pode forçar falha de storage e bypassar rate limiting |

**Detalhes C3 — XSS em file-upload.js:**

```javascript
// Linha 35 — URL quebra atributo src se contém aspa:
'<img src="' + anexo.url + '" ... onerror="this.onerror=null;this.parentElement.innerHTML=\'...\'"'

// Linha 78 — URL quebra atributo href:
'<a href="' + a.url + '" target="_blank" ...'

// Linha 84 — URL quebra atributo data-url:
'data-url="' + a.url + '"'

// Linha 219 — URL quebra atributo src no modal de confirmação:
'<img src="' + url + '" ... onerror="this.style.display=\'none\'">'
```

Um anexo com URL como `" onclick="alert(1)` executaria JavaScript arbitrário.

**Detalhes C5 — Corrupção silenciosa de IDs:**

```php
'status_id' => max(1, (int)($data['status_id'] ?? 0)),       // 0 → 1
'procedimento_id' => max(1, (int)($data['procedimento_id'] ?? 0)), // 0 → 1
'regiao_id' => max(1, (int)($data['regiao_id'] ?? 0)),       // 0 → 1
```

Se `validateRequiredIds` for chamado **depois** de `extractCommonData`, todos os IDs já são >=1 e a validação passa sem detectar os campos ausentes.

**Detalhes C4 — Chave extra em relatorios_alarmes.js:**

```javascript
// Linha 320-323
function carregarFiltrosSalvos() { } } }
//                                   ^--- esta } fecha $(document).ready()
// Todo código de linha 325+ (setupColumnSelector, setupTopScrollbar, inicializar)
// executa fora do ready, podando null references
```

---

## 2. Falhas de Alta Severidade

| # | Arquivo | Linha(s) | Problema | Impacto |
|---|---------|----------|----------|---------|
| A1 | `inc/single_session.php` | 15 | `X-Forwarded-For` usa `end()` (último IP) em vez do rightmost untrusted — IP spoofing | Atacante pode forjar IP de sessão |
| A2 | `src/Api/RateLimiter.php` | 289, 313 | `@fopen` suprime erros — rate limiting silenciosamente desabilitado | Rate limiting ineficaz se disco cheio ou sem permissão |
| A3 | `src/Api/RateLimiter.php` | 387 | `catch(Exception){}` vazio — cleanup de arquivos falha sem feedback | Arquivos de rate limit órfãos acumulam |
| A4 | `inc/session_handler.php` | 153-156 | Falha de DB em `enforceUniqueSession` → `$valid=false` → derruba TODOS os usuários | DoS acidental: queda do DB = logout em massa |
| A5 | `api/v2/api_upload_anexo.php` | 22-31 | Múltiplos FK IDs aceitos simultaneamente + sem verificação de existência do FK | Anexo ligado a 4 entidades; FK órfã |
| A6 | `api/v2/api_excluir_anexo.php` | 65-68 | Resultado do DELETE não verificado — DB row pode permanecer após arquivo excluído | Órfão de registro DB apontando para arquivo inexistente |
| A7 | `api/v2/api_excluir_camera.php` | 46-58 | `SET @app_user_id` + UPDATE + audit **sem transação** | Audit trail perdido se qualquer etapa falhar |
| A8 | `api/v2/api_cadastrar_alarmes.php` | 86-94 | INSERT + audit sem transação | Registro commitado sem audit se audit falhar |
| A9 | `api/v2/api_editar_alarme.php` | 105-112 | UPDATE + audit sem transação | Mesmo que A8 |
| A10 | `api/v2/swagger.php` | 15-41 | OpenAPI spec exposto sem auth + `Access-Control-Allow-Origin: *` | Vazamento completo da superfície API |
| A11 | `api/v2/api_cep_lookup.php` | 25-33 | **Sem `CURLOPT_SSL_VERIFYPEER`** no cURL | Resposta MITM pode injetar dados maliciosos |
| A12 | `api/v2/limpar_sessoes_orfas.php` | 22-25 | Nominalmente limpa sessões órfãs, mas só limpa a sessão **do próprio usuário** | Admin espera limpar órfãs e é desconectado |
| A13 | `resources/listar_locais.php` | 64 | Exportação CSV via GET sem CSRF | Exfiltração de dados via link/image tag |
| A14 | `public/assets/js/modules/dashboard-theme.js` vs `utils/ui/theme-manager.js` | 1/5 | Dois `ThemeManager` **diferentes** sobrescrevem `window.ThemeManager` | Dark mode quebra na home (versão simples não adiciona classe no body) |
| A15 | `public/assets/js/main.js` vs `utils/ui-utils.js` | 1/109 | Duas implementações de `showToast` sobrescrevem `window.showToast` | Comportamento inconsistente dependendo da ordem de carregamento |
| A16 | `public/assets/js/pages/home-dashboard.js` | 49-50 | Dashboard falha silenciosamente — `console.error` é no-op em produção | Usuário vê contadores zerados sem nenhuma indicação de erro |
| A17 | `api/v2/api_upload_anexo.php` | 22-31 | Nenhuma verificação de existência dos FK IDs informados | Arquivo associado a equipamento/alarme inexistente |
| A18 | `api/v2/api_upload_anexo.php` | 60-68 | Exclusão arquivo + exclusão DB sem transação — arquivo deletado mas DB row pode permanecer | Órfão de registro; ausência de arquivo não logada como warning |

---

## 3. Falhas de Severidade Média

| # | Arquivo | Linha(s) | Problema |
|---|---------|----------|----------|
| M1 | `resources/cadastro_cameras.php` | 31 | Nome de tabela interpolado em SQL (`$tabela`, `$order`) — frágil se mapa for alterado |
| M2 | `config/config.php` | 9-12 | Carregamento do `.env` falha silenciosamente se `file()` retorna `false` |
| M3 | `config/config.php` | 31-37 | Variáveis de ambiente com valor vazio (`VAR=`) são ignoradas — impede override intencional |
| M4 | `config/database.php` | 113-129 | Flag `inTransaction` desincroniza se `beginTransaction()` lançar exceção |
| M5 | `config/database.php` | 84 | Detecção de SELECT por prefixo — QUERY iniciando com `WITH` ou `/* comment */` é classificado incorretamente |
| M6 | `src/Api/ApiResponse.php` | 122 | Divisão por zero se `$perPage === 0` em `paginated()` |
| M7 | `src/Api/RequestValidator.php` | 179 | `validateBoolean` aceita `"false"` como válido, mas `(bool)"false" === true` — erro lógico downstream |
| M8 | `src/Api/RequestValidator.php` | 292 | `validateMin` confunde string numérica `"100"` com número — `min:5` passa (100>=5) em vez de falhar (len=3<5) |
| M9 | `inc/security.php` | 227 | `@mkdir` suprime erro — diretório de audit log não criado silenciosamente |
| M10 | `inc/security.php` | 252-255 | `generateTemporaryPassword` tem viés de módulo (256 ÷ 60 = 4.26) e não garante conformidade com política de senhas |
| M11 | `inc/security.php` | 19-26 | CSRF token long-lived — uma vez roubado (via XSS), válido por toda a sessão |
| M12 | `public/assets/js/manutencao_alarmes_v2.js` | 212, 224 | Chamadas diretas a `fetchDados()` sem proteção try/catch (debounce só protege alguns caminhos) |
| M13 | `public/assets/js/listar_cameras.js` | 72-78, 438 | Race condition em chamadas recursivas + `setInterval` com async sem catch |
| M14 | `public/assets/js/manutencao_cameras.js` | 567 | `fetchDados()` sem guarda de reentrada — mudanças rápidas de filtro causam sobreposição |
| M15 | 9 arquivos JS | DOMContentLoaded | Se scripts carregam após `DOMContentLoaded`, listeners nunca disparam — falha silenciosa |
| M16 | `api/v2/api_upload_anexo.php` | 90 | Fallback `.bin` para MIME desconhecido — servidor pode executar `.bin` |
| M17 | `api/v2/api_health.php` | 9-10 | 401 Unauthorized para usuário autenticado sem permissão (deveria ser 403) |
| M18 | `api/v2/api_cadastrar_alarmes.php` | 57-58 | Sem validação de IP (`FILTER_VALIDATE_IP`), MAC, datas |
| M19 | `api/v2/api_dashboard.php` | 31-165 | 8 queries DB — falhas parciais escondidas substituindo por arrays vazios, sem logging |
| M20 | `api/v2/renovar_sessao.php` | 49-55 | Após `session_regenerate_id`, token CSRF antigo é retornado |
| M21 | `api/v2/api_geocode.php` | 17 | Sem limite de tamanho na query — abuso de API externa (Nominatim) |
| M22 | `api/v2/api_cadastrar_alarmes.php` | 51 | Campo `status` aceita string livre — sem whitelist de valores válidos |
| M23 | `api/v2/api_manutencao_cameras.php` | 315 | `data_hora` aceita string livre — datas no futuro ou inválidas são inseridas |
| M24 | `public/index.php` | 38 | CSP `'unsafe-eval'` em dev — se `CAMERAS_ENV` falta, default é `development` com eval habilitado |
| M25 | `public/assets/js/auditoria-cameras.js` | 62 | `codigo_publico`, `numero_serie`, `ip` interpolados em innerHTML sem escape |
| M26 | `resources/cadastro_cameras.php` | 632 | `BASE_URL` usada em `src` sem `htmlspecialchars()` |
| M27 | `public/assets/js/cadastro_cameras.js` | 667 | `showToast('...', 'error')` — tipo `'error'` não existe no bgMap (deveria ser `'danger'`) |
| M28 | `public/assets/js/cadastro_cameras.js` | 667 | Botão submit não é restaurado do estado loading quando CSRF token ausente |
| M29 | `config/database.php` | 63-65 | `PDO::PARAM_BOOL` inconsistente entre drivers — pode inserir valor errado |
| M30 | `src/Services/EquipamentoService.php` | 485-489 | `SET @app_user_id` persiste na conexão singleton — pode vazar para próxima request no mesmo processo |
| M31 | `src/Services/EquipamentoService.php` | 103-125 | `resolveLocation` tem race condition em INSERT — sem UNIQUE constraint, locais duplicados |
| M32 | `api/v2/api_excluir_anexo.php` | 81-89 | Audit logging falha silenciosamente — delete retorna sucesso sem audit |
| M33 | `api/v2/api_editar_camera.php` | 80-84 | Rollback falha silenciosamente: `catch(Exception $ignored){}` |
| M34 | `api/v2/api_manutencao_cameras.php` | 497 | Rollback falha silenciosamente: `catch(Throwable $ignored){}` |
| M35 | `api/v2/api_manutencao_alarmes.php` | 473 | Rollback falha silenciosamente: `catch(Throwable $ignored){}` |
| M36 | `api/v2/api_excluir_anexo.php` | 50-68 | Se arquivo já foi deletado (`realpath===false`), ausência não é logada como warning |
| M37 | `api/v2/api_alarmes.php` | 155 | LIMIT/OFFSET interpolados em SQL em vez de bind parameters ( inconsistente com `api_cameras.php`) |
| M38 | `api/v2/api_health.php` | 24-34 | Endpoint expõe nome do ambiente, nomes de tabelas internas e status HTTPS |
| M39 | `api/v2/api_alarmes.php` | 112-117 | Mensagem "Execute o script SQL" vaza detalhes internos em produção |
| M40 | `resources/cadastro_locais.php` | 360-394 | CEP lookup `.catch()` não reabilita o botão/esconde spinner (diferente de `editar_locais.php` que usa `.finally()`) |
| M41 | Vários resources | múltiplas | Auth checks manuais inline em vez de usar `requererAcesso()` — lógica pode divergir |
| M42 | `inc/security.php` | 5-7 | `session_start()` chamado em 3 arquivos diferentes — frágil se ordem de include mudar |
| M43 | `src/Api/RateLimiter.php` | 284-327 | TOCTOU entre `fileRead` e `fileWrite` — dois requests podem ler, ambos achar under-limit, e ambos escrever |
| M44 | `api/v2/api_cadastrar_cameras.php` | 52 | ID interno vazado na mensagem de sucesso: `"ID: $equipId"` |
| M45 | `src/ErrorHandler.php` | 55-100 | Shutdown function não limpa output buffer — resposta pode ser mistura HTML+JSON em fatal error |
| M46 | `scripts/optimize-database.php` | 108,162,171,199 | Nomes de tabela/índice interpolados em SQL — CLI only, mas padrão perigoso |

---

## 4. Falhas de Baixa Severidade / Info

| # | Arquivo | Problema |
|---|---------|----------|
| B1 | `resources/cadastro_locais.php:129` | `global $formData` dentro de `fd()` — quebra de encapsulamento |
| B2 | `inc/security.php:240` | `file_put_contents` retorno não verificado no audit fallback |
| B3 | `src/Api/RateLimiter.php:412` | `@rmdir` suprime erro em cleanup |
| B4 | `config/app.php:60,72` | `SameSite=Strict` bloqueia navegação cross-site — links de email/bookmark não enviam cookie |
| B5 | `config/app.php:38-44` | Detecção HTTPS confia em `X-Forwarded-Proto` — requer configuração correta de proxy |
| B6 | `api/v2/api_relatorios_cameras.php:51,56,61` | Parâmetros de filtro não cast para `(int)` — MySQL type coercion pode causar resultados inesperados |
| B7 | `src/Api/ApiResponse.php:58` | Request ID tem baixa entropia — `uniqid()+mt_rand()` não é criptograficamente seguro |
| B8 | `src/Api/ApiResponse.php:295` | Timezone do servidor exposta em toda resposta API |
| B9 | `src/Api/ApiResponse.php:302-313` | `exit` em todos os métodos `send()` — impossibilita testes unitários do ApiResponse |
| B10 | `inc/header.php:37-41,51-52` | Console override roda antes do SW registration — mensagens de SW nunca são visíveis |
| B11 | `public/assets/js/pages/home-dashboard.js:263-265` | Dead code: classe `page-home` adicionada e verificada na sequência |
| B12 | `api/v2/api_manutencao_alarmes.php:416-464` | `finalize_os` não atualiza status do alarme na tabela `central_alarmes` (gap funcional vs cameras) |
| B13 | `api/v2/api_cadastrar_alarmes.php:92-94` | Audit data inclui prefixo `:` nos nomes de campo (ex: `:regiao` ao invés de `regiao`) |
| B14 | `api/v2/api_listar_anexos.php:79-87` | Função `formatFileSize` declarada dentro de `try` — se incluída por outro arquivo, fatal "Cannot redeclare" |
| B15 | `api/v2/api_geocode.php:104-115` | Sem cache — cada request faz chamada ao Nominatim; risco de ban de IP |
| B16 | `resources/cadastro_locais.php:153` | Form tem `novalidate` — validação depende 100% de JS que pode falhar |
| B17 | 37 console statements | `console.log/error/warn` em 14 arquivos JS — dead code em produção (override no-op) mas com overhead de chamada de função |
| B18 | `public/assets/js/relatorios.js:270` | `onclick="location.reload()"` inline via `.html()` — viola CSP strict |

---

## 5. Problemas Específicos por Camada

### 5.1 Backend — PHP (Config, DB, Segurança)

**Configuração e Ambiente**
- `.env` parsing sem suporte a multi-line, sem logging de falhas, sem suporte a valores vazios intencionais
- Carregamento duplicado de `config.php` (via `database.php` e via `index.php`)
- `ENVIRONMENT` definido em dois lugares com lógica diferente (`app.php` vs `config.php`)
- `$_ENV` populado mas `$_SERVER` não — inconsistência potencial

**Banco de Dados**
- Singleton PDO sem lógica de re-conexão — se MySQL reinicia, conexão morta persiste
- `query()` retorna array de erro em vez de lançar exceção — inconsistente e propenso a falhas silenciosas
- Flag `inTransaction` pode desincronizar se PDO lançar exceção
- Detecção de SELECT frágil (`stripos($sql, 'SELECT') === 0`)
- `SSL_VERIFY_SERVER_CERT=true` desabilita verificação (nome enganoso)
- `PARAM_BOOL` inconsistente entre drivers MySQL

**Sessão e Autenticação**
- Falha de DB em `enforceUniqueSession` força logout de todos os usuários (DoS)
- `X-Forwarded-For` parsing usa `end()` — permite IP spoofing
- `session_start()` espalhado em 3 arquivos — frágil
- CSRF token long-lived (rotação só em mutation requests) — múltiplas abas quebram
- `clearLocalSessionState()` não regenera session ID (mitigado por `use_strict_mode`)
- `hashSessionToken` usa SHA-256 sem HMAC/salt — não-keyed

**Rate Limiting**
- Baseado em arquivo no `sys_get_temp_dir()` — qualquer usuário local pode manipular
- Falha aberto (permite tudo) quando storage falha
- TOCTOU entre read e write
- `@fopen` e `catch{}` suprimem todos os erros
- Não funciona em ambiente load-balanced

**EquipamentoService**
- IDs ausentes silenciosamente coercidos para 1 (corrompe dados)
- Lógica LPR vs DVR incorreta quando `$tipoCameraId===3`
- `SET @app_user_id` vaza entre requests no mesmo processo
- Sem UNIQUE constraint em `locais(nome, secretaria_id)` — race condition
- Sem transações em operações CRUD isoladas

**RequestValidator**
- Regras desconhecidas falham com mensagem genérica inútil
- `validateBoolean` aceita `"false"` mas downstream `(bool)"false" === true`
- `validateMin/Max` confunde strings numéricas com números
- Regra `not_in` com strict comparison pode falhar com type coercion

### 5.2 Backend — API Endpoints (api/v2/)

**Valdação de Entrada Ausente**
- `api_cadastrar_alarmes.php`: sem validação de IP, MAC, datas, status whitelist
- `api_editar_alarme.php`: mesmo problema
- `api_relatorios_cameras.php`: filtros sem cast para `(int)`
- `api_manutencao_cameras.php`: `data_hora` aceita string livre
- `api_geocode.php`: sem limite de comprimento na query
- `api_auditoria_cameras.php`: sem RequestValidator, validação manual inconsistente

**Transações Ausentes em Operações Multi-Step**
- `api_cadastrar_alarmes.php`: INSERT + audit sem transação
- `api_editar_alarme.php`: UPDATE + audit sem transação
- `api_excluir_camera.php`: SET vars + UPDATE + audit sem transação
- `api_excluir_anexo.php`: arquivo + DB delete sem transação
- `api_upload_anexo.php`: move file + INSERT DB sem transação

**Resultados de Query Não Verificados**
- `api_excluir_anexo.php`: DELETE não verificado
- `api_excluir_camera.php`: `SET @app_user_id` com NULL não verificado
- `api_dashboard.php`: falhas parciais escondidas com arrays vazios
- `api_manutencao_cameras.php`: queries de busca substituem erros por `[]`

**Erros de HTTP Status**
- `api_health.php`: 401 para usuário autenticado sem permissão (deveria ser 403)
- `limpar_sessoes_orfas.php`: mesmo problema
- `api_manutencao_alarmes.php`: mesmo problema

**Upload de Arquivos**
- Múltiplos FK IDs aceitos simultaneamente
- FK IDs sem verificação de existência
- Fallback `.bin` para MIME desconhecido
- Tipo de arquivo não validado (MIME apenas, extensão não verificada)

**Outros**
- `swagger.php`: spec aberto sem auth + CORS wildcard
- `api_cep_lookup.php`: sem SSL verification no cURL
- `limpar_sessoes_orfas.php`: não limpa sessões órfãs, só a própria
- `renovar_sessao.php`: CSRF token antigo retornado após regeneração
- `api_geocode.php`: sem cache, risco de ban do Nominatim
- `formatFileSize` em `api_listar_anexos.php`: função declarada dentro de try

### 5.3 Frontend — JavaScript

**XSS**
- `file-upload.js`: URLs não escapadas em atributos HTML (4 locais)
- `auditoria-cameras.js`: dados da API interpolados em innerHTML sem escape
- `ui-utils.js`: `showConfirm()` aceita HTML cru como `htmlContent`
- `relatorios.js`/`relatorios_alarmes.js`: `onclick` inline via `.html()` — viola CSP

**Falhas Silenciosas de Erro**
- Dashboard: `console.error` = no-op em produção; zeros sem indicação de falha
- Service Worker: `.catch(()=>{})` em `cache.addAll` — cache incompleto silencioso
- Session check no footer: `.catch(()=>{})` — erro de rede ignorado
- CEP lookup em `cadastro_locais.php`: `.catch()` não reabilita botão

**Race Conditions**
- `listar_cameras.js`: chamadas recursivas + setInterval async sem catch
- `listar_alarmes.js`: mesmo padrão
- `manutencao_cameras.js`: fetchDados sem reentrancy guard
- `manutencao_alarmes_v2.js`: caminhos diretos sem debounce
- `relatorios.js`: buscarDados sem proteção (TODO documentado mas não implementado)
- `home-dashboard.js`: interval + manual refresh não coordenados

**DOMContentLoaded Timing**
- 9 arquivos usam `addEventListener('DOMContentLoaded')` — se script carrega AFTER ready, nunca dispara
- Sem fallback `document.readyState` check

**Duplicação e Poluição Global**
- `getApiBase()` duplicado em 7+ arquivos
- `window.showToast` sobrescrito (2 implementações diferentes)
- `window.ThemeManager` sobrescrito (2 classes diferentes)
- 14 símbolos globais sem namespacing
- `relatorios.js` e `relatorios_alarmes.js`: usam jQuery `.html()` enquanto resto usa fetch nativo

**Validação de Formulário Quebrada**
- `cadastro_cameras.js`: validação IP no blur desconectada do submit
- `cadastro_cameras.js`: sem validação de MAC no submit
- `editar_cameras.js`: sem validação IP/MAC
- `cadastro_alarmes.js` / `editar_alarmes.js`: `checkValidity()` apenas
- `manutencao_cameras.js` / `manutencao_alarmes_v2.js`: validação mínima (length e emptiness)
- `file-upload.js`: sem validação de tipo de arquivo (qualquer extensão aceita)

**Botão Travado**
- `cadastro_cameras.js:667`: submit fica disabled+spinner quando CSRF ausente, sem restauração

**Editar Cameras — Dual Binding**
- `editar_cameras.js:25-32`: dois event listeners para `tipoSelect` e `tipoCameraSelect` ambos disparam `handleTipoChange()` — valor stale do outro campo influencia lógica
- `editar_cameras.js:379-382,384-666`: dois DOMContentLoaded listeners inicializando o mesmo form — IIFE com array `cameras` separado do estado da classe

### 5.4 Infraestrutura

**Docker/CI**
- Sem healthcheck no container da aplicação
- Sem restart policy específica (usa default)

**Nginx**
- TLS com cert auto-assinado para Dev apenas
- Sem rate limiting no nível do proxy

**Service Worker**
- `BASE_URL` undefined no SW scope
- Asset inexistente na lista de cache
- Console override impede debug do SW

---

## 6. Hall de Pontos Positivos

O projeto demonstra diversas boas práticas de segurança:

- **Queries parametrizadas** com `ERRMODE_EXCEPTION` e `EMULATE_PREPARES = false`
- **Bcrypt** com cost 12 via `password_hash()`
- **CSRF tokens** em todos os POST forms com `hash_equals()` (timing-safe)
- **Path traversal protection** em `api_servir_anexo.php` com `realpath()` + `str_starts_with()`
- **Content Security Policy** com nonces
- **Session security**: HttpOnly, SameSite=Strict, IP/UA binding, single-session
- **Audit logging** com fallback para arquivo
- **Access control centralizado** via `access_rules.php` + `requererAcesso()`
- **Output encoding** consistente com `htmlspecialchars($var, ENT_QUOTES, 'UTF-8')`
- **Sem credenciais hardcoded** — tudo via `.env`
- **PHPStan L5** + **PHPUnit** + **Vitest** configurados
- **CI/CD completo** no GitHub Actions

---

## 7. Plano de Melhorias

### Fase 1 — Correções Críticas (1-3 dias)

Elimina erros de runtime, XSS e corrupção de dados.

| # | Ação | Arquivos | Detalhe |
|---|------|----------|---------|
| 1.1 | Corrigir Service Worker | `public/sw.js` | Remover `AlarmeSearch.js` inexistente; passar `BASE_URL` via `postMessage` ou `importScripts`; adicionar `.catch()` com logging real |
| 1.2 | Corrigir XSS em uploads | `public/assets/js/utils/file-upload.js` | Escapar URLs com `escapeHtml()` antes de inserir em atributos; preferir `createElement` |
| 1.3 | Corrigir chave extra | `public/assets/js/relatorios_alarmes.js` | Remover `}` extra na linha 323 que fecha `$(document).ready()` prematuramente |
| 1.4 | Refatorar `database.php::query()` | `config/database.php` | Lançar exceção em vez de retornar array de erro; adicionar reconnection logic; corrigir `SSL_VERIFY_SERVER_CERT`; corrigir detecção SELECT para suportar `WITH` |
| 1.5 | Corrigir `EquipamentoService` | `src/Services/EquipamentoService.php` | Chamar `validateRequiredIds` ANTES de `extractCommonData`; corrigir branch LPR vs DVR; adicionar transações em insert/update |
| 1.6 | Verificar resultado DELETE | `api/v2/api_excluir_anexo.php` | Verificar `$result['status']` após DELETE |
| 1.7 | Rate limiter fail-closed | `src/Api/RateLimiter.php`, `api/bootstrap-api.php` | Quando storage falha, retornar `check()=false` (bloquear requisições) |

### Fase 2 — Correções de Segurança (3-5 dias)

Fecha vetores de ataque e fortalece proteções.

| # | Ação | Arquivos | Detalhe |
|---|------|----------|---------|
| 2.1 | CSRF na exportação CSV | `resources/listar_locais.php` | Mudar para POST ou adicionar token na URL |
| 2.2 | Validação server-side beef-up | Todos os endpoints API | Adicionar `RequestValidator` com regras completas (IP, MAC, datas, whitelist status) |
| 2.3 | SSL no cURL | `api/v2/api_cep_lookup.php` | Adicionar `CURLOPT_SSL_VERIFYPEER=>true, CURLOPT_SSL_VERIFYHOST=>2` |
| 2.4 | X-Forwarded-For parsing | `inc/single_session.php` | Implementar rightmost untrusted IP (walk backwards, skip trusted proxies) |
| 2.5 | Swagger/Health auth | `api/v2/swagger.php`, `api/v2/api_health.php` | Adicionar autenticação; remover `CORS: *`; reduzir info leaked; corrigir 401→403 |
| 2.6 | Session fallback gracioso | `inc/session_handler.php` | Quando DB falha em `enforceUniqueSession`, aceitar sessão com warning em vez de derrubar |
| 2.7 | CSP em produção obrigatório | `public/index.php` | Garantir que `CAMERAS_ENV=production` seja obrigatório em deploy; remover `unsafe-eval` default |
| 2.8 | Normalizar erros HTTP | Todos os endpoints | Usar 403 (não 401) quando usuário está autenticado mas sem permissão |
| 2.9 | `limpar_sessoes_orfas` | `api/v2/limpar_sessoes_orfas.php` | Implementar busca real por sessões órfãs no DB |
| 2.10 | Upload anexo FK check | `api/v2/api_upload_anexo.php` | Garantir exatamente 1 FK por anexo; verificar existência do FK no DB |

### Fase 3 — Robustez Frontend (3-5 dias)

Elimina falhas silenciosas no UI e inconsistências.

| # | Ação | Arquivos | Detalhe |
|---|------|----------|---------|
| 3.1 | Unificar `showToast` | `main.js`, `ui-utils.js` | Uma única implementação em `ui-utils.js`; remover de `main.js` |
| 3.2 | Unificar `ThemeManager` | `dashboard-theme.js`, `theme-manager.js` | Dashboard carregar extendendo o base; não sobrescrever `window.ThemeManager` |
| 3.3 | Eliminar `getApiBase()` duplicado | 7+ arquivos JS | Usar `window.APP_API_BASE` injetado pelo PHP em um único ponto |
| 3.4 | Error handling no dashboard | `home-dashboard.js` | Mostrar ícone de erro + botão retry quando API falha; não mostrar zeros |
| 3.5 | DOMContentLoaded robusto | 9 arquivos JS | Usar `if (document.readyState !== 'loading') init(); else document.addEventListener('DOMContentLoaded', init)` |
| 3.6 | Reentrancy guards | `manutencao_cameras.js`, `relatorios.js`, `relatorios_alarmes.js` | Adicionar `AbortController` ou flag de loading para cancelar fetch anterior |
| 3.7 | Botão submit restaurável | `cadastro_cameras.js` | Restaurar botão no catch de CSRF error; corrigir tipo `'error'`→`'danger'` |
| 3.8 | Limpar console statements | 14 arquivos JS | Remover 37 `console.log/error/warn` ou envolver em `if (window.DEBUG)` |
| 3.9 | CEP lookup .finally() | `resources/cadastro_locais.php` | Adicionar `.finally()` para reabilitar botão e esconder spinner |
| 3.10 | Editar cameras dual-binding | `editar_cameras.js` | Unificar em única lógica de tipo; remover IIFE e segundo DOMContentLoaded |
| 3.11 | Escape de dados API | `auditoria-cameras.js` | Escapar `codigo_publico`, `numero_serie`, `ip` antes de innerHTML |

### Fase 4 — Transações e Integridade de Dados (2-3 dias)

Garante consistência ACID nas operações críticas.

| # | Ação | Arquivos | Detalhe |
|---|------|----------|---------|
| 4.1 | Transações em alarmes | `api_cadastrar_alarmes.php`, `api_editar_alarme.php` | Envolver INSERT/UPDATE + audit em transação |
| 4.2 | Transação em exclusão câmera | `api_excluir_camera.php` | Envolver SET vars + UPDATE + audit em transação |
| 4.3 | Transação em exclusão anexo | `api/v2/api_excluir_anexo.php` | Envolver file delete + DB delete em transação (ou inverter ordem: DB primeiro, file depois) |
| 4.4 | Transação em upload anexo | `api/v2/api_upload_anexo.php` | Envolver INSERT DB + move_uploaded_file (mover para temp, INSERT, renomear) |
| 4.5 | Unique constraint locais | Migration SQL | Adicionar `UNIQUE(nome, secretaria_id)` em `locais`; usar `ON DUPLICATE KEY` no `resolveLocation` |
| 4.6 | Reset @app_user_id | `EquipamentoService.php` | Adicionar `SET @app_user_id = NULL` ao final de cada request |
| 4.7 | Log rollback failures | 3 arquivos API | Substituir `catch(Exception $ignored){}` por `catch(Exception $e) { error_log('Rollback falhou: ' . $e->getMessage()); }` |
| 4.8 | Audit log replay | `inc/security.php` | Mecanismo para re-inserir file-based audit entries no DB quando conexão restaura |

### Fase 5 — Melhorias Arquiteturais (5-7 dias)

Escalabilidade, manutenibilidade e observabilidade.

| # | Ação | Detalhe |
|---|------|---------|
| 5.1 | Migration para ES Modules | Converter JS global-scoped para `import`/`export`; eliminar 14 símbolos globais |
| 5.2 | Camada Service para Alarmes | Espelhar `EquipamentoService` para alarmes, centralizando validação e transações |
| 5.3 | Cache de Geocoding | Implementar cache MySQL com TTL (30 dias) para evitar abuso do Nominatim |
| 5.4 | Rate limiting por endpoint | Limites diferenciados: upload (10/min), geocode (5/min), login (5/min), API geral (60/min) |
| 5.5 | CSRF token per-tab | Usar `localStorage` + header customizado em vez de session token único; resolver problema de múltiplas abas |
| 5.6 | ApiResponse testável | Remover `exit` dos métodos `send()`; usar output buffer pattern ou retorno para permitir testes |
| 5.7 | Reconnection PDO | Adicionar ping/reconnect logic no singleton database antes de cada query |
| 5.8 | Testes automatizados | Expandir PHPUnit (5→30+ testes) e Vitest (2→15+ testes) cobrindo endpoints críticos identificados |
| 5.9 | Health check automático | Verificação periódica de DB, storage, SSL com alerta em degradação |
| 5.10 | Observabilidade | Adicionar structured logging (JSON) com correlation IDs; remover error_log() dispersed |
| 5.11 | File-based rate limit migration | Migrar de armazenamento em arquivo para DB (com fallback) para suportar load balancing |

---

## Cronograma Resumido

```
Fase 1 (Crítico)      ████████████████████  1-3 dias    → Eliminar crashes, XSS, corrupção de dados
Fase 2 (Segurança)     ████████████████       3-5 dias    → Fechar vetores de ataque
Fase 3 (Frontend)      ████████████████       3-5 dias    → Eliminar falhas silenciosas no UI
Fase 4 (Dados)         ██████████             2-3 dias    → Garantir consistência ACID
Fase 5 (Arquitetural)  ████████████████████   5-7 dias    → Escalabilidade e manutenibilidade
                                              ─────────
                                              Total: 14-23 dias úteis
```

---

## Estatísticas da Análise

| Categoria | Quantidade |
|-----------|-----------|
| Falhas Críticas | 9 |
| Falhas Altas | 18 |
| Falhas Médias | 46 |
| Falhas Baixas/Info | 18 |
| **Total de findings** | **91** |
| Arquivos analisados | ~110 |
| Console statements em produção | 37 |
| Símbolos globais JS | 14 |
| Endpoints sem validação completa | 8 |
| Operações sem transação | 5 |
| Rollback failures silenciosas | 3 |
