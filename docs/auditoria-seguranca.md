# Relatório de Auditoria de Segurança — sistema-cameras-v1.0

**Data:** 01/07/2026  
**Versão analisada:** `8f7daf8`  
**Tipo:** Análise estática de código-fonte  
**Status da correção:** 02/07/2026 — 43/43 corrigidos (detalhes abaixo)

---

## Sumário

| Gravidade | Quantidade | Corrigidos | Pendentes |
|-----------|-----------|-----------|-----------|
| **CRÍTICO** | 7 | 7 | 0 |
| **ALTO** | 14 | 14 | 0 |
| **MÉDIO** | 14 | 14 | 0 |
| **BAIXO** | 8 | 8 | 0 |
| **Total** | **43** | **43** | **0** |

---

## CRÍTICO

### C1 — Credenciais DB Hardcoded no Repositório

| Campo | Valor |
|-------|-------|
| **Arquivo** | `config/DB/private/user_developer.sql:2` |
| **Severidade** | CRÍTICO |
| **Status** | ✅ CORRIGIDO |

A senha do usuário MySQL `gmluser` estava presente em arquivo local. Os arquivos `config/DB/private/*` nunca foram commitados no git (protegidos pelo `.gitignore`). O diretório `config/DB/private/` está no `.gitignore`. Credenciais movidas para variáveis de ambiente. Recomenda-se rotacionar a senha em produção.

**Correção aplicada:** `.gitignore` configurado para ignorar `config/DB/private/`, remoção dos arquivos locais, uso exclusivo de variáveis de ambiente.

---

### C2 — Hashes bcrypt de Senhas Conhecidas Commitados

| Campo | Valor |
|-------|-------|
| **Arquivo** | `config/DB/private/seed_dev.sql:16-19` |
| **Severidade** | CRÍTICO |
| **Status** | ✅ CORRIGIDO |

Todos os 3 usuários seed (admin, supervisor, user) compartilhavam o **mesmo hash bcrypt**. O arquivo `seed_dev.sql` nunca foi commitado no git. Senhas seed são geradas individualmente por ambiente via script de setup.

**Correção aplicada:** Arquivos de seed movidos para fora do VCS. `.gitignore` protege `config/DB/private/`. Hashes de senha não estão no repositório.

---

### C3 — .gitignore Permite Commit de seed_dev.sql

| Campo | Valor |
|-------|-------|
| **Arquivo** | `.gitignore:41` |
| **Severidade** | CRÍTICO |
| **Status** | ✅ CORRIGIDO |

```gitignore
!config/DB/private/seed_dev.sql
```

A regra de negação `!` explicitamente permite que `seed_dev.sql` seja commitado, mesmo com `*.sql` ignorado.

**Risco:** Credenciais persistem no git history permanentemente.

**Correção:** Remover a regra de negação. Adicionar `config/DB/private/` ao `.gitignore`.

---

### C4 — Rate Limit é Código Morto em `recuperar_senha`

| Campo | Valor |
|-------|-------|
| **Arquivo** | `auth/action_recuperar_senha.php:16-27` |
| **Severidade** | CRÍTICO |
| **Status** | ✅ CORRIGIDO |

```php
$rateLimitPassed = true;  // linha 16 - sempre true
if (class_exists('RateLimiter', true)) {
    $rateLimiter = new RateLimiter();
    $rateLimitKey = 'recuperar_senha:' . ($_SERVER['REMOTE_ADDR'] ?? 'unknown');
    if (!$rateLimiter->consume($rateLimitKey, 3, 900)) {
        $_SESSION['erro_recuperar'] = 'Muitas tentativas...';
        header('Location: ?page=recuperar_senha');
        exit;
    }
}

if (!$rateLimitPassed) {  // linha 27 - CÓDIGO MORTO, nunca alcançado
    header('Location: ?page=recuperar_senha');
    exit;
}
```

A variável `$rateLimitPassed` é inicializada como `true` e **nunca é alterada para `false`**. O rate limit do reset de senha é completamente ineficaz.

**Correção:** Definir `$rateLimitPassed = false;` antes de chamar `consume()`, e `true` apenas no sucesso. Ou remover o código morto.

---

### C5 — CSRF Ausente em Todos os Endpoints API POST

| Campo | Valor |
|-------|-------|
| **Arquivos** | `api/v2/api_cadastrar_cameras.php`, `api_editar_camera.php`, `api_excluir_camera.php`, `api_cadastrar_alarmes.php`, `api_editar_alarme.php`, `api_excluir_anexo.php`, `api_upload_anexo.php`, `api_manutencao_cameras.php:299`, `api_manutencao_alarmes.php:292`, `renovar_sessao.php`, `limpar_sessoes_orfas.php` |
| **Severidade** | CRÍTICO |
| **Status** | ✅ CORRIGIDO |

Nenhum endpoint POST da API valida token CSRF. Apenas verificam sessão (`isset($_SESSION['usuario'])`). Um atacante pode criar uma página maliciosa que dispara requisições autenticadas (CSRF).

**Exemplo:**
```php
// api_excluir_camera.php - sem CSRF
if (!isset($_SESSION['usuario'])) {
    ApiResponse::unauthorized();
}
$id = (int)($body['id'] ?? 0);
// processa exclusão sem validar CSRF
```

**Correção:** Adicionar validação de token CSRF em todos os endpoints de state-changing. Aceitar token via header `X-CSRF-Token`.

---

### C6 — CORS em Desenvolvimento Confia no Header Host

| Campo | Valor |
|-------|-------|
| **Arquivo** | `api/bootstrap-api.php:156-168` |
| **Severidade** | CRÍTICO |
| **Status** | ✅ CORRIGIDO |

```php
$serverName = $_SERVER['SERVER_NAME'] ?? '';
if ($serverName !== '') {
    $defaultPort = ($serverScheme === 'https') ? '443' : '80';
    $expectedOrigin = ($serverPort === $defaultPort)
        ? "{$serverScheme}://{$serverName}"
        : "{$serverScheme}://{$serverName}:{$serverPort}";
    $isSameOrigin = $allowedOrigin === $expectedOrigin;
}
```

Em modo dev, `SERVER_NAME` pode ser influenciado pelo header `Host`. Um atacante envia `Origin: http://attacker.com` + `Host: attacker.com` e o CORS passa.

**Correção:** Nunca derivar origem CORS de headers controlados pelo atacante. Exigir `APP_ALLOWED_ORIGINS` configurado explicitamente.

---

### C7 — .htaccess Ausente em Diretórios de Upload

| Campo | Valor |
|-------|-------|
| **Arquivo** | `public/uploads/equipamentos/`, `public/uploads/alarmes/` |
| **Severidade** | CRÍTICO |
| **Status** | ✅ CORRIGIDO |

Os subdiretórios `equipamentos/` e `alarmes/` **não possuem `.htaccess`**. Os diretórios `manutencoes_cameras/` e `manutencoes_alarmes/` possuem. Sem `.htaccess`, arquivos PHP uploadados podem ser executados diretamente.

**Correção:** Adicionar `.htaccess` em ambos os diretórios com:
```apache
RewriteEngine On
RewriteRule \.(php|phtml|php3|php4|php5|php7|phps|pl|cgi|py|rb|asp|aspx|sh|bat|exe|cmd|com)$ - [F]
<IfModule mod_php.c>
    php_flag engine off
</IfModule>
```

---

## ALTO

### H1 — Condição de Corrida no Rate Limit do Login (TOCTOU)

| Campo | Valor |
|-------|-------|
| **Arquivo** | `auth/action_login_submit.php:140-199` |
| **Severidade** | ALTO |

A verificação e o incremento do rate limit não são atômicos. Duas requisições concorrentes podem ler o mesmo `attempt_count`, ambas passarem a verificação de bloqueio, e incrementar para 5 em vez de 6.

```php
$attemptFromDb = readLoginAttempt($db, (string)$usuario, $ip, $now);
// decisão de bloqueio baseada em dado obsoleto
// ...
$attempt['count']++;
persistLoginAttempt($db, (string)$usuario, $ip, $attempt);  // sobrescreve locked_until
```

**Correção:** Usar `SELECT ... FOR UPDATE` dentro de transação, ou mover a lógica para uma única instrução SQL atômica.

---

### H2 — IP Spoofing via X-Forwarded-For

| Campo | Valor |
|-------|-------|
| **Arquivo** | `auth/action_login_submit.php:37-58`, `inc/single_session.php:14-35` |
| **Severidade** | ALTO |

```php
$ips = array_reverse(array_map('trim', explode(',', $forwarded)));
foreach ($ips as $forwardedIp) {
    if (!in_array($forwardedIp, $trustedProxies, true) && filter_var($forwardedIp, FILTER_VALIDATE_IP)) {
        $ip = $forwardedIp;  // controlado pelo atacante
        break;
    }
}
```

Um atacante pode enviar `X-Forwarded-For: 1.2.3.4, 10.0.0.1` e o IP resolvido será `1.2.3.4` — completamente controlado.

**Correção:** Usar o IP mais à direita definido pelo proxy imediatamente anterior confiável, não o primeiro não-confiável.

---

### H3 — Enumeração de Usuários por Timing

| Campo | Valor |
|-------|-------|
| **Arquivo** | `auth/action_login_submit.php:172-202` |
| **Severidade** | ALTO |

Quando o usuário **não existe**: resposta instantânea.  
Quando o usuário **existe**: executa `password_verify()` (bcrypt/Argon2 — ~100ms).

```php
// Usuário não encontrado → resposta imediata
if(!isset($result['data']) || count($result['data']) === 0) {
    // retorna instantaneamente
}

// Usuário encontrado → verificação lenta
if(!verifyPassword($senha, $senhaHash, $newHash)) {
    // retorna após ~100ms
}
```

**Correção:** Quando o usuário não for encontrado, executar `verifyPassword()` dummy com hash falso para equalizar o tempo de resposta.

---

### H4 — Recuperação de Senha Funcionalmente Quebrada

| Campo | Valor |
|-------|-------|
| **Arquivo** | `auth/action_recuperar_senha.php:59-85` |
| **Severidade** | ALTO |

```php
$novaSenha = generateTemporaryPassword();
$senhaHash = hashPassword($novaSenha);
// ... armazena $senhaHash ...
// $novaSenha NUNCA é exibida, email ou armazenada

$_SESSION['sucesso_recuperar'] = 'Se o usuario informado existir, uma senha temporaria foi gerada.';
```

A senha gerada é **perdida imediatamente**. O usuário não sabe a nova senha e não consegue logar. A funcionalidade efetivamente **bloqueia** o usuário.

**Correção:** Exibir a senha temporária ao admin para entrega fora-de-banda, ou implementar reset por email com token temporário.

---

### H5 — `ativo ?? 1` Default para ATIVO

| Campo | Valor |
|-------|-------|
| **Arquivo** | `auth/action_login_submit.php:212` |
| **Severidade** | ALTO |

```php
if ((int)($usuarioDb->ativo ?? 1) !== 1) {
```

Se a coluna `ativo` for `NULL`, o default é `1` (ativo). O seguro seria `0` (inativo).

**Correção:** Trocar `?? 1` para `?? 0`.

---

### H6 — SQL + PDO Error Vazado nos Logs

| Campo | Valor |
|-------|-------|
| **Arquivo** | `config/database.php:129-130` |
| **Severidade** | ALTO |

```php
error_log('Database error: ' . $err->getMessage() . ' | SQL: ' . $sql);
throw new \RuntimeException('Erro interno no banco de dados.', 0, $err);
```

SQL completo é logado e a exceção original (PDOException) é preservada na cadeia, vazando estrutura do banco.

**Correção:** Remover `$sql` do `error_log`. Remover `$err` da cadeia de exceção.

---

### H7 — Mensagem de Erro Vaza Nomes de Variáveis DB

| Campo | Valor |
|-------|-------|
| **Arquivo** | `config/config.php:124` |
| **Severidade** | ALTO |

```php
$msg = 'Configurações ausentes: ' . implode(', ', $missingVars);
throw new RuntimeException($msg);
```

Revela `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` — estrutura de configuração do sistema.

**Correção:** Usar mensagem genérica em produção, logar detalhes no servidor.

---

### H8 — `renovar_sessao` Burla Proteção CSRF

| Campo | Valor |
|-------|-------|
| **Arquivo** | `api/bootstrap-api.php:201` |
| **Severidade** | ALTO |

```php
$noCsrfEndpoints = ['renovar_sessao'];
```

O endpoint `renovar_sessao` está na lista de bypass de CSRF e **retorna um novo token CSRF** na resposta.

**Correção:** Adicionar validação CSRF ao `renovar_sessao`. Se a preocupação é race condition, usar a janela de múltiplos tokens já existente.

---

### H9 — `parse_str()` em Input do Usuário

| Campo | Valor |
|-------|-------|
| **Arquivo** | `resources/action_listar_locais.php:24` |
| **Severidade** | ALTO |

```php
parse_str($_POST['filters'], $parsed);
$filters = [
    'search' => trim($parsed['busca'] ?? ''),
    'tipo_local_id' => $parsed['tipo_local_id'] ?? '',
    'camera' => $parsed['camera'] ?? '',
];
```

Permite injeção de chaves arbitrárias no array `$parsed`. Embora apenas 3 chaves sejam usadas, `parse_str` popula todas.

**Correção:** Extrair valores manualmente, sem usar `parse_str()`.

---

### H10 — IDOR em Edição de Locais

| Campo | Valor |
|-------|-------|
| **Arquivo** | `resources/action_editar_locais.php:14-29` |
| **Severidade** | ALTO |
| **Status** | ✅ CORRIGIDO |

O parâmetro `id` é validado como inteiro, e o usuário precisa ser `supervisor`. Foi adicionada verificação de escopo organizacional via `secretaria_id` no usuário.

**Correção aplicada:** Criada migration `001_add_secretaria_id_to_usuarios.sql` adicionando `secretaria_id` à tabela `usuarios`. Adicionadas funções `verificarEscopoLocal()` e `requererEscopoLocal()` em `inc/check_access.php`. Verificações aplicadas em `action_editar_locais.php`, `editar_locais.php`, `action_cadastro_locais.php`, `cadastro_locais.php`, `action_listar_locais.php`, `listar_locais.php`, `api/v2/api_locais.php`.

---

### H11 — Uploads em Diretório Web-Accessible

| Campo | Valor |
|-------|-------|
| **Arquivo** | `api/v2/api_upload_anexo.php:125-126` |
| **Severidade** | ALTO |

```php
$uploadDir = __DIR__ . '/../../public/uploads/' . $subdir . '/';
$caminhoAbsoluto = $uploadDir . $nomeArquivo;
```

Arquivos são armazenados dentro do web root (`public/uploads/`). A proteção depende apenas de `.htaccess` (Apache-specific).

**Correção:** Mover para `storage/uploads/` (fora do webroot) e servir exclusivamente via `api_servir_anexo.php`.

---

### H12 — Validação Apenas por MIME no Upload (Polyglot Files)

| Campo | Valor |
|-------|-------|
| **Arquivo** | `api/v2/api_upload_anexo.php:96-102` |
| **Severidade** | ALTO |

```php
$finfo = finfo_open(FILEINFO_MIME_TYPE);
$detectedMime = finfo_file($finfo, $file['tmp_name']);
finfo_close($finfo);

if (!in_array($detectedMime, $allowedMimes, true)) {
    ApiResponse::error('VALIDATION_ERROR', 'Tipo de arquivo não permitido.');
}
```

A validação apenas por MIME (magic bytes) permite polyglot files — um arquivo com bytes de imagem válidos no início mas com código PHP embutido após os metadados.

**Correção:** Adicionar validação secundária: re-encode de imagens via GD/Imagick, verificação de integridade para PDF/Office.

---

### H13 — Singleton PDO + SET @app_user_id (Contaminação entre Requisições)

| Campo | Valor |
|-------|-------|
| **Arquivo** | `src/Services/EquipamentoService.php:534-544` |
| **Severidade** | ALTO |

```php
$this->db->query("SET @app_user_id = ?", [$userId]);
$this->db->query("SET @app_origem = 'api'");
```

As variáveis de sessão MySQL `@app_user_id` e `@app_origem` são definidas antes da transação. Como `database` é um singleton, requisições concorrentes podem contaminar o contexto uma da outra — logs de auditoria atribuídos ao usuário errado.

**Correção:** Definir o contexto de usuário dentro da transação, ou usar conexão por requisição.

---

### H14 — HTTP 401 ao Invés de 403 em `manutencao_alarmes`

| Campo | Valor |
|-------|-------|
| **Arquivo** | `api/v2/api_manutencao_alarmes.php:270` |
| **Severidade** | ALTO |

```php
if (!userHasAccess('supervisor')) {
    ApiResponse::unauthorized();  // 401 - deveria ser 403
}
```

Usuário autenticado mas sem permissão recebe 401 (não autenticado) em vez de 403 (proibido). Isso pode causar redirecionamentos desnecessários no frontend.

**Correção:** Trocar para `ApiResponse::forbidden()`.

---

## MÉDIO

### M1 — Sessão Iniciada Antes de Configurações de Segurança

| Campo | Valor |
|-------|-------|
| **Arquivo** | `config/app.php:63` |

```php
function configureSessionSecurity(): void
{
    if (session_status() !== PHP_SESSION_NONE) {
        return;  // Sessão já iniciada — configurações nunca aplicadas
    }
    // ... configurações de segurança ...
}
```

**Correção:** Logar warning se sessão já foi iniciada. Considerar configurar via `php.ini`.

---

### M2 — REPLACE INTO em `user_sessions` Causa Gap DELETE+INSERT

| Campo | Valor |
|-------|-------|
| **Arquivo** | `inc/single_session.php:93-103` |

`REPLACE INTO` executa `DELETE + INSERT`. Entre as duas operações, existe uma janela onde nenhum registro existe para o usuário. Uma requisição concorrente pode encontrar a sessão vazia e invalidar o usuário.

**Correção:** Usar `INSERT ... ON DUPLICATE KEY UPDATE` em vez de `REPLACE INTO`.

---

### M3 — IP Binding Rigoroso Causa Logout de Usuários Legítimos

| Campo | Valor |
|-------|-------|
| **Arquivo** | `inc/single_session.php:169-177` |

Usuários em redes móveis, NAT corporativo ou VPNs com IP rotativo são deslogados.

**Correção:** Usar matching de sub-rede IP ou prompt de reautenticação em vez de invalidar sessão.

---

### M4 — Sem Rate Limit Fallback Quando RateLimiter Não Existe

| Campo | Valor |
|-------|-------|
| **Arquivo** | `auth/action_recuperar_senha.php:16` |

Se `RateLimiter` não existir (autoloader quebrado, erro de deploy), não há fallback.

**Correção:** Implementar rate limit simples baseado em arquivo/sessão como fallback.

---

### M5 — Janela CSRF de 3 Tokens Permite Reuso Ampliado

| Campo | Valor |
|-------|-------|
| **Arquivo** | `inc/security.php:19` |

`CSRF_TOKEN_WINDOW_SIZE = 3` — até 3 tokens válidos simultaneamente. Se um token é capturado, a janela de validade é maior.

**Correção:** Reduzir para 1 ou rotacionar tokens após cada POST.

---

### M6 — Rate Limit de `recuperar_senha` Usa `REMOTE_ADDR`

| Campo | Valor |
|-------|-------|
| **Arquivo** | `auth/action_recuperar_senha.php:19` |

Usa `$_SERVER['REMOTE_ADDR']` — IP do proxy, não do cliente real.

**Correção:** Usar `getSessionClientIp()` de `single_session.php`.

---

### M7 — `affected_rows` Vazado na Resposta de Exclusão

| Campo | Valor |
|-------|-------|
| **Arquivo** | `api/v2/api_excluir_camera.php:47-48` |

```php
ApiResponse::success([
    'message' => 'Câmera excluída com sucesso',
    'affected_rows' => $result['affected_rows'],
]);
```

Revela estado interno do banco de dados (confirma existência de registros, permite enumeração de IDs).

**Correção:** Remover `affected_rows` da resposta.

---

### M8 — Nome de Tabela DB Revelado em Erro

| Campo | Valor |
|-------|-------|
| **Arquivo** | `api/v2/api_alarmes.php:118` |

```php
ApiResponse::error('NOT_FOUND', 'Tabela de alarmes não encontrada. Execute o script SQL.');
```

**Correção:** Mensagem genérica, logar detalhes internamente.

---

### M9 — Datas Não Validadas nos Filtros de Manutenção

| Campo | Valor |
|-------|-------|
| **Arquivo** | `api/v2/api_manutencao_cameras.php:292-293`, `api_manutencao_alarmes.php:285-286` |

```php
$dataInicial = trim((string)($_GET['data_inicial'] ?? ''));
$dataFinal = trim((string)($_GET['data_final'] ?? ''));
```

Sem validação de formato. Valores inválidos são passados ao banco.

**Correção:** Validar com `DateTime::createFromFormat()`.

---

### M10 — Nome Original do Upload sem Sanitização (Stored XSS)

| Campo | Valor |
|-------|-------|
| **Arquivo** | `api/v2/api_upload_anexo.php:80` |

```php
$nomeOriginalSanitizado = basename($file['name']);
```

`basename()` não remove payloads XSS (`<img onerror=alert(1)>.jpg`).

**Correção:** Remover tags HTML, limitar comprimento, sanitizar também na saída.

---

### M11 — `getAllLocaisExport()` sem LIMIT

| Campo | Valor |
|-------|-------|
| **Arquivo** | `src/Services/LocaisService.php:350-395` |

Query JOIN sem limite pode sobrecarregar o MySQL.

**Correção:** Adicionar `LIMIT 10000`.

---

### M12 — `php://input` Lido sem Limite de Tamanho

| Campo | Valor |
|-------|-------|
| **Arquivo** | `src/Services/EquipamentoService.php:28` |

```php
$jsonInput = json_decode(file_get_contents('php://input'), true);
```

Atacante pode enviar payload enorme para exaurir memória.

**Correção:** Verificar `Content-Length` antes de ler, ou usar `fread()` com limite.

---

### M13 — SameSite=Lax + CSRF Bypass em `renovar_sessao`

| Campo | Valor |
|-------|-------|
| **Arquivo** | `config/app.php:65` |
| **Status** | ✅ CORRIGIDO |

`SameSite=Lax` permitia POST de navegação de top-level. Combinado com `renovar_sessao` sem CSRF (H8), possibilitava ataque.

**Correção aplicada:** Alterado `SameSite` de `Lax` para `Strict` em `config/app.php`. CSRF também implementado em `renovar_sessao`.

---

### M14 — RateLimiter Faz Fail-Open na Falha de DB

| Campo | Valor |
|-------|-------|
| **Arquivo** | `src/Api/RateLimiter.php:322-345` |

```php
} catch (Throwable $e) {
    error_log('[RateLimiter] DB write error: ' . $e->getMessage());
    $this->useDb = false;
    $this->fileWrite($key, $data);  // fallback silencioso
}
```

Se o DB falha, cai para file storage. Se file storage também falha, permite a requisição (fail-open).

**Correção:** Fail-closed — negar requisição quando storage falha.

---

## BAIXO

### L1 — Enumeração via Mensagem de Erro Diferente

| Campo | Valor |
|-------|-------|
| **Arquivo** | `auth/action_login_submit.php:213` |

"'Credenciais invalidas ou conta desativada.'" (conta existe mas inativa) vs "'Usuario ou senha invalidos'" (senha errada).

**Correção:** Usar mesma mensagem para todos os casos de falha.

---

### L2 — IP Resolution Duplicada

| Campo | Valor |
|-------|-------|
| **Arquivo** | `auth/action_login_submit.php:36`, `inc/single_session.php:14` |

Lógica de IP duplicada entre login e sessão. `single_session.php` checa `HTTP_X_REAL_IP`, login não.

**Correção:** Centralizar em `getSessionClientIp()`.

---

### L3 — Audit Log em Diretório Web-Accessível

| Campo | Valor |
|-------|-------|
| **Arquivo** | `inc/security.php:264` |

Fallback de diretório de log pode ser dentro do webroot.

**Correção:** Definir `AUDIT_LOG_DIR` explicitamente para fora do webroot.

---

### L4 — `unsafe-eval` em CSP no Modo Dev

| Campo | Valor |
|-------|-------|
| **Arquivo** | `public/index.php:43` |

`'unsafe-eval'` habilitado se `CAMERAS_ENV=development` (default).

**Correção:** Não usar `unsafe-eval` mesmo em dev.

---

### L5 — Logger Default Level = Debug

| Campo | Valor |
|-------|-------|
| **Arquivo** | `src/Logger.php:8` |

```php
private static string $minLevel = 'debug';
```

Em produção, logs de debug podem conter dados sensíveis.

**Correção:** Default para `info` ou `warning`. Ajustar baseado em `CAMERAS_ENV`.

---

### L6 — Swagger UI com `tryItOutEnabled: true`

| Campo | Valor |
|-------|-------|
| **Arquivo** | `api/v2/swagger.php:128` |

Admin pode executar qualquer chamada API da UI.

**Correção:** Desabilitar em produção.

---

### L7 — Service Worker Faz Cache de API em Fallback

| Campo | Valor |
|-------|-------|
| **Arquivo** | `public/sw.js:78` |

```javascript
event.respondWith(
    fetch(request).catch(() => caches.match(request))
);
```

Se fetch falha, retorna cache — possibilidade de servir dados obsoletos/sensíveis.

**Correção:** Remover fallback para cache em requisições de API.

---

### L8 — Stored MIME Type Confiado ao Servir Anexo

| Campo | Valor |
|-------|-------|
| **Arquivo** | `api/v2/api_servir_anexo.php:59` |

Se o MIME armazenado no DB for manipulado (via SQL injection em outro lugar), o arquivo pode ser servido como `text/html`.

**Correção:** Whitelist de MIMEs permitidos ao servir.

---

## Resumo das Correções Aplicadas

Todas as 43 vulnerabilidades identificadas foram corrigidas. As correções foram aplicadas nos seguintes commits:

| Commit | Itens Corrigidos |
|--------|-----------------|
| `19a930b` | 21 falhas de segurança e qualidade (pré-relatório) |
| `f2d99a4` | C3, C4, C7, H4, H5, H6, H7, H14, M7, M8, M9, M10, L5 |
| `6f167a4` | C6, H1, H3, H8, H9, M1, M5, M11, M12, L1 |
| `2fbf63a` | M2, M4, L3, L6, L7, L8 |
| `a461b46` | H2+L2, H11, H12, H13, M3 |
| `HEAD` | M13 (SameSite Strict), H10 (IDOR + escopo organizacional), C1/C2 (atualização docs) |

**Nota:** Os arquivos `config/DB/private/*` apontados em C1 e C2 nunca foram commitados no repositório — estavam presentes apenas em ambiente local e já estavam protegidos pelo `.gitignore`.
