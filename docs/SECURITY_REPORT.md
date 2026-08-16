# SECURITY_REPORT.md

Auditoria de Segurança — Projeto "Controle Share Videos" (Sistema de Gerenciamento de Câmeras e Alarmes v1.0).
Data: 2026-08-16. Método: revisão estática de código com mapeamento OWASP Top 10 / ASVS / API Security Top 10 / CWE.

## Metodologia

- Revisão manual de `inc/security.php`, `inc/single_session.php`, `inc/check_access.php`, `inc/session_handler.php`, `api/bootstrap-api.php`, `src/Services/AuthService.php`, `src/Api/RateLimiter.php`, `src/Logging/LoggerFactory.php`, `src/ErrorHandler.php`, `api/v2/api_upload_anexo.php`, `api/v2/api_servir_anexo.php`, `api/v2/api_geocode.php`, `api/v2/api_cep_lookup.php`, endpoints de escrita (câmeras/alarmes), `config/app.php`, `public/.htaccess`, `docker/conf.d/default.conf`, `.env.example`, `.env.docker`.
- Buscas automatizadas por padrões perigosos: `shell_exec/system/exec/passthru/proc_open/popen`, `unserialize`, `eval`, `curl_exec`/`file_get_contents` para URLs externas (SSRF), secrets hardcoded, logs de dados sensíveis, vazamento de mensagens de erro ao cliente.
- Verificação de histórico Git para arquivos `.env`/secrets versionados.
- Ambiente: sem instância MySQL em execução — verificações dependentes de runtime de banco classificadas como **Bloqueado** (não exercitadas).

## Resumo Executivo

Postura de segurança **forte e bem estruturada**, com controles em profundidade. A infraestrutura de autenticação (Argon2id, sessão única, timeout, rate limit por usuário+IP com dummy hash), CSRF com consumo de token, CORS com allowlist e upload validado por conteúdo real estão acima do padrão médio de sistemas PHP legados.

Foram identificados **2 achados confirmados de baixa/média severidade** e **2 pontos de atenção a validar** (dependem de decisão de negócio sobre o papel "supervisor").

## Achados

### F-101 — Vazamento de mensagem de exceção ao cliente (CWE-209)

| Campo | Valor |
| ----- | ----- |
| Local | `api/v2/api_editar_camera.php:117` |
| Evidência | `ApiResponse::internalError('Erro ao editar camera: ' . $msg)` onde `$msg = $e->getMessage()` |
| Causa raiz | No `catch (Throwable $e)` genérico, a mensagem da exceção (que pode conter SQL, caminhos, stack) é concatenada na resposta ao cliente. |
| Risco | Baixo — informação interna pode vazar em erro de runtime; facilita reconhecimento de atacante. |
| Classificação | **Confirmado** |
| Severidade | Baixa |
| Recomendação | Usar mensagem genérica `'Erro ao editar camera.'` e registrar `$e->getMessage()` apenas no log (como feito nos demais endpoints, ex. `api_cadastrar_cameras.php:91-92`). |

### F-102 — `session_regenerate_id(true)` e cookie PHPSESSID após login (CWE-384 / session fixation mitigada parcialmente)

| Campo | Valor |
| ----- | ----- |
| Local | `src/Services/AuthService.php:266` |
| Evidência | `session_regenerate_id(true)` no `createSession()` |
| Análise | `session_regenerate_id(true)` regenera o ID e apaga o arquivo antigo. No PHP 8.4 com session handler padrão, a mitigação de fixation é aplicada. A função `configureSessionSecurity()` exige `session_status() === PHP_SESSION_NONE` — se algum caminho chamar `session_start()` antes (ex. `inc/security.php:6`), os parâmetros de cookie de segurança (HttpOnly/SameSite/secure) podem não ser aplicados. |
| Classificação | **Não confirmado como vulnerabilidade** — verificação de caminho de boot duplicado necessária. |

### F-103 — Inconsistência de escopo: supervisor é isento de verificação de secretaria em equipamentos/anexos/alarmes

| Campo | Valor |
| ----- | ----- |
| Local | `inc/check_access.php:77,133,149` |
| Evidência | `if (in_array(resolveUserAccessLevel($usuario), ['admin', 'supervisor'], true)) return true;` nas funções `verificarEscopoEquipamento`, `verificarEscopoAnexo`, `verificarEscopoAlarme` |
| Análise | Supervisor tem acesso amplo a equipamentos/anexos/alarmes de **todas** as secretarias via essas funções, porém `verificarEscopoLocal` (`inc/check_access.php:41`) valida supervisor por secretaria, e `api_upload_anexo.php:58` também valida secretaria para não-admin. Há inconsistência: supervisor vê/edita câmeras de todas as secretarias mas não pode anexar arquivo a registro de outra secretaria. |
| Risco | Baixo — pode ser intencional (supervisor gerencia múltiplas secretarias). Depende de regra de negócio. |
| Classificação | **Confirmado (inconsistência)** — recomenda-se definir explicitamente se supervisor deve ou não ser escopado por secretaria. |

### F-104 — Recuperação de senha retorna senha temporária em sessão (não por e-mail)

| Campo | Valor |
| ----- | ----- |
| Local | `auth/action_recuperar_senha.php:83` |
| Evidência | `$_SESSION['sucesso_recuperar'] = "Senha temporária gerada para o usuário '{$userData->nome}': {$novaSenha}"` |
| Análise | Fluxo intencional do sistema (sem SMTP), restrito a admin com rate limit (3/15min). A senha temporária é exibida ao admin logado, não ao usuário-alvo. Risco limitado, mas a senha em texto plano fica na sessão/página. |
| Classificação | **Não confirmado como vulnerabilidade** — comportamento definido; monitorar. |

## OWASP Top 10 — Resultado por categoria

| OWASP | Categoria | Resultado | Evidência |
| ----- | --------- | --------- | --------- |
| A01 | Broken Access Control | **Atender (com F-103 a decidir)** | `userHasAccess`, `requiredAccessForApiEndpoint`, `verificarEscopo*`; endpoints de escrita exigem `supervisor`; role-based matrix em `api/bootstrap-api.php:25-57` |
| A02 | Cryptographic Failures | **Atender** | Argon2id (`inc/security.php:397-420`), SHA-256 para tokens de sessão, TLS via HSTS (`bootstrap-api.php:184-186`), `session.cookie_secure` condicionado a HTTPS |
| A03 | Injection | **Atender** | Prepared statements com `EMULATE_PREPARES=false`; sem `eval`/`unserialize`/shell; entrada validada (CEP, IP, coord) |
| A04 | Insecure Design | **Atender (com atenção)** | Rate limit em profundidade (AuthService + RateLimiter), CSRF com consumo |
| A05 | Security Misconfiguration | **Atender** | `display_errors=0`, headers de segurança, `.htaccess` bloqueia arquivos sensíveis, CORS allowlist |
| A06 | Vulnerable Components | **Atender (pós-Fase 1)** | `composer audit` limpo após F-005; PHP 8.4 |
| A07 | Auth Failures | **Atender** | Password policy, dummy hash anti-enumeração, lockout 5 tentativas/15min |
| A08 | Software Integrity | **Atender** | `composer.lock` versionado, CD por tag |
| A09 | Logging & Monitoring | **Atender** | Monolog + auditoria de eventos; sem dados sensíveis em log |
| A10 | SSRF | **Atender** | URLs externas fixas (nominatim, photon, viacep), SSL verify, timeouts, rate limit |

## OWASP API Security Top 10

| Item | Resultado | Evidência |
| ---- | --------- | --------- |
| API1 Broken Object Level Authorization | Atender (F-103 supervisor) | `verificarEscopo*` por secretaria para usuário comum |
| API2 Broken Authentication | Atender | Sessão única, timeout absoluto + inatividade |
| API3 Broken Object Property Level Authorization | **Não auditado em runtime** (BD bloqueado) | validação de campos por whitelist observada |
| API4 Unrestricted Resource Consumption | Atender | Rate limiter com limites por endpoint (`bootstrap-api.php:135-148`), payload max 10MB |
| API5 Broken Function Level Authorization | Atender | `requiredAccessForApiEndpoint` (admin/supervisor/user) |
| API6 Unrestricted Access to Sensitive Business Flows | Atender | Auditoria + role matrix |
| API7 SSRF | Atender | URLs fixas (ver A10) |
| API8 Security Misconfiguration | Atender | Headers, CORS, `X-Request-ID` |
| API9 Improper Inventory Management | Atender | `allowedEndpoints` whitelist em `bootstrap-api.php:107-116` |
| API10 Unsafe Consumption of APIs | Atender | SSL verify + timeouts |

## Verificações detalhadas

### Entrada
- SQLi: **Atender** — prepared statements (`config/database.php`, `EMULATE_PREPARES=false`); whitelist de colunas nos services.
- Command injection: **Atender** — nenhum `shell_exec`/`exec`/`system` em código de produção.
- XSS: **Atender** — 154 usos de `htmlspecialchars`; CSP estrito no HTML (`public/index.php:60`) e API (`bootstrap-api.php:182`); sem `echo $_` direto.
- Path traversal: **Atender** — `api_servir_anexo.php:88-112` usa `realpath()` + checagem de prefixo.
- Deserialização insegura: **Atender** — nenhum `unserialize` no código.

### Autenticação
- Senhas: Argon2id (fallback bcrypt cost 12); política de senha forte (`security.php:268-291`).
- Sessões: cookie HttpOnly + SameSite=Strict + secure condicional (`config/app.php:53-71`), sid 48 chars.
- Expiração: inatividade + absoluta (`session_handler.php:53-86`).
- Brute force: 5 tentativas/15min por usuário+IP com lockout; 15 por IP (`AuthService`).
- Rate limiting: em camada dupla (AuthService DB + RateLimiter FS/DB).

### Autorização
- IDOR/BOLA: `verificarEscopoLocal/Equipamento/Anexo/Alarme` por secretaria; upload valida secretaria alvo (`api_upload_anexo.php:58`).
- Privilege escalation: níveis admin/supervisor/user com função dedicada.

### API
- Inventário: 34 arquivos; whitelist de 32 endpoints expostos.
- CORS: allowlist via `APP_ALLOWED_ORIGINS` + validação de origem same-origin (`bootstrap-api.php:188-246`); `*` gera warning.
- SSRF: não aplicável (hosts fixos).
- Rate limit por endpoint: `['/^login:/'=>[5,60], '/^POST:/'=>[30,60], ...]`.

### Upload (`api_upload_anexo.php`)
- MIME real via `finfo` + mapa de extensão derivada do MIME (nunca do nome).
- Tamanho: 10MB.
- Nome de arquivo: random (`bin2hex(random_bytes(16))`), nunca o original.
- Imagens reprocessadas via GD (elimina polyglot).
- Office/PDF validados por magic header.
- Path traversal: impossível (nome gerado).

### Secrets / Dados
- Nenhum secret hardcoded no código.
- `.env.example`/`.env.docker` commitados **sem valores reais** (placeholders).
- Logs não contêm senhas/tokens completos (`security.php:92-96` loga prefixo do token).

### Configuração
- Headers: CSP, nosniff, X-Frame-Options, Referrer-Policy, Permissions-Policy, HSTS (condicional).
- Cookies: configurados acima.
- Debug: `display_errors=0` em bootstrap.
- Erros: mensagens genéricas (exceto F-101).

## Matriz de Vulnerabilidades

| ID | CWE | Severidade | Status | Ação |
| -- | --- | ---------- | ------ | ---- |
| F-101 | CWE-209 | Baixa | Confirmado | Corrigir mensagem genérica em `api_editar_camera.php:117` |
| F-102 | CWE-384 | Informativo | Não confirmado | Validar caminho de boot único de `session_start` |
| F-103 | CWE-863 | Baixa | Confirmado (inconsistência) | Decisão de negócio sobre escopo do supervisor |
| F-104 | — | Informativo | Não confirmado | Monitorar fluxo de senha temporária |

## Limitações

- Banco de dados não acessível no momento da auditoria: verificações de runtime (BOLA em execução, IDOR ativo, SQLi dinâmico) classificadas como **Bloqueado** — dependem de ambiente com MySQL.
- Sem execução de pentest ativo (fuzzing/exploit) — apenas revisão estática.
- Serviços externos (ViaCEP, geocode, Sentry) não exercitados.
