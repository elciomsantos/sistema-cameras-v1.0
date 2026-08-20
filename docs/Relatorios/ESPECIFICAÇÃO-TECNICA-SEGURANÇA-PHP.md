# ESPECIFICAÇÃO TÉCNICA DE SEGURANÇA
## Aplicação PHP + MySQL + Docker Compose + JavaScript/HTML

**Versão:** 1.0  
**Status:** Especificação técnica  
**Classificação:** Confidencial / Padrão de segurança  
**Aplicação:** Sistemas Web desenvolvidos em PHP, MySQL, JavaScript e HTML executados em Docker Compose.

---

# 1. OBJETIVO

Estabelecer os requisitos técnicos mínimos e obrigatórios de segurança para desenvolvimento, execução, publicação, manutenção e operação da aplicação.

A especificação tem como objetivos:

- proteger autenticação e sessões;
- impedir acesso não autorizado a recursos;
- reduzir risco de SQL Injection;
- reduzir risco de XSS, CSRF e ataques de sessão;
- proteger arquivos e uploads;
- proteger credenciais e segredos;
- restringir privilégios dos containers;
- proteger o banco MySQL;
- reduzir superfície de ataque do Docker;
- controlar recursos computacionais;
- permitir auditoria e investigação de incidentes;
- estabelecer critérios objetivos de aprovação para produção.

---

# 2. ESCOPO

Esta especificação abrange:

- PHP;
- HTML;
- JavaScript;
- MySQL;
- Docker;
- Docker Compose;
- Dockerfile;
- proxy/reverse proxy;
- autenticação;
- sessões;
- autorização;
- cookies;
- CSRF;
- XSS;
- CORS;
- uploads;
- downloads;
- compartilhamento de arquivos;
- banco de dados;
- logs;
- auditoria;
- secrets;
- imagens Docker;
- redes Docker;
- volumes;
- hardening dos containers;
- hardening do host Linux.

---

# 3. ARQUITETURA DE REFERÊNCIA

A arquitetura deverá seguir o princípio de separação de responsabilidades:

```text
                         INTERNET
                            |
                            | HTTPS
                            v
                  +-------------------+
                  | Reverse Proxy     |
                  | Nginx/Caddy/etc.  |
                  +---------+---------+
                            |
                            | HTTP interno
                            v
                  +-------------------+
                  | PHP Application   |
                  | Container         |
                  +---------+---------+
                            |
                  +---------+---------+
                  |                   |
                  v                   v
          +-------------+      +-------------+
          | MySQL       |      | Storage     |
          | Container   |      | / Volumes   |
          +-------------+      +-------------+
```

O banco de dados não deverá ser diretamente acessível pela Internet.

A comunicação externa deverá ocorrer somente pelo proxy/reverse proxy.

A comunicação entre containers deverá utilizar redes Docker internas e nomes DNS dos serviços.

---

# 4. PRINCÍPIOS DE SEGURANÇA

A aplicação deverá obedecer aos seguintes princípios:

1. Menor privilégio.
2. Defesa em profundidade.
3. Zero confiança em dados enviados pelo cliente.
4. Autenticação separada de autorização.
5. Autorização sempre executada no servidor.
6. Segredos fora do código-fonte.
7. Containers sem privilégios desnecessários.
8. Banco de dados isolado.
9. Logs sem credenciais ou tokens.
10. Falha segura.
11. Validação de entrada.
12. Escaping de saída.
13. Queries parametrizadas.
14. Auditoria de operações sensíveis.
15. Recursos protegidos independentemente de seus identificadores.

---

# 5. MODELO DE AMEAÇA

A aplicação deverá considerar, no mínimo:

- SQL Injection;
- XSS armazenado;
- XSS refletido;
- CSRF;
- Session Fixation;
- roubo de sessão;
- brute force;
- credential stuffing;
- enumeração de usuários;
- IDOR/BOLA;
- path traversal;
- acesso direto a arquivos;
- abuso de upload;
- upload de código executável;
- abuso de endpoints;
- exposição de credenciais;
- comprometimento de container;
- abuso de capabilities;
- container escape;
- acesso indevido ao Docker socket;
- exploração do MySQL;
- DoS por CPU;
- DoS por memória;
- DoS por armazenamento;
- vulnerabilidades em dependências;
- imagem Docker comprometida.

---

# 6. AUTENTICAÇÃO

## 6.1 Identidade

A identidade autenticada deverá ser determinada exclusivamente pelo servidor.

Fluxo obrigatório:

```text
Cookie
   ↓
Token
   ↓
Sessão
   ↓
user_id
   ↓
Permissões
   ↓
Recurso
```

O cliente não poderá determinar:

- `user_id`;
- role;
- permissões;
- propriedade do recurso;
- identidade de outro usuário.

Alterar `user_id` enviado pelo navegador nunca poderá resultar em acesso a recursos de outro usuário.

---

# 7. SESSÕES

A aplicação deverá utilizar sessão server-side.

Para aplicações Web tradicionais em PHP, JWT não deverá ser utilizado apenas como substituição de sessão quando não existir necessidade arquitetural específica.

O token deverá possuir:

- 256 bits de entropia;
- geração por CSPRNG;
- formato opaco;
- ausência de informações de negócio.

Implementação PHP:

```php
$token = bin2hex(random_bytes(32));
$tokenHash = hash('sha256', $token);
```

O token real não deverá ser armazenado no banco.

Somente o hash SHA-256 deverá ser persistido.

---

# 8. BANCO DE SESSÕES

Estrutura mínima:

```sql
CREATE TABLE sessions (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT UNSIGNED NOT NULL,
    token_hash CHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    last_activity_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME NULL,
    ip_address VARCHAR(45) NULL,
    user_agent VARCHAR(512) NULL,

    PRIMARY KEY (id),
    UNIQUE KEY uq_sessions_token_hash (token_hash),
    KEY idx_sessions_user_id (user_id),
    KEY idx_sessions_expires_at (expires_at),

    CONSTRAINT fk_sessions_user
        FOREIGN KEY (user_id)
        REFERENCES users(id)
        ON DELETE CASCADE
);
```

A especificação existente já define `token_hash` como índice `UNIQUE` e os índices para usuário e expiração.

---

# 9. COOKIE DE SESSÃO

Produção deverá utilizar:

```http
Set-Cookie: __Host-SID=<TOKEN>; Path=/; Secure; HttpOnly; SameSite=Strict
```

Requisitos:

- `Secure`;
- `HttpOnly`;
- `SameSite=Strict`, quando compatível;
- `Path=/`;
- prefixo `__Host-`;
- sem `Domain`.

Credenciais de sessão não poderão ser armazenadas em:

- `localStorage`;
- `sessionStorage`;
- IndexedDB;
- URL;
- query string;
- fragment.

Esses controles estão definidos na especificação de sessões existente.

---

# 10. LOGIN

Fluxo obrigatório:

```text
POST /login
      ↓
Validar entrada
      ↓
Localizar usuário
      ↓
Verificar senha
      ↓
Aplicar rate limit
      ↓
Aplicar MFA quando obrigatório
      ↓
Gerar token
      ↓
Calcular SHA-256
      ↓
Criar sessão
      ↓
Enviar cookie protegido
```

Após autenticação deverá ocorrer regeneração da sessão.

PHP nativo:

```php
session_regenerate_id(true);
```

Quando utilizado mecanismo próprio de sessão, o identificador anterior deverá ser invalidado.

---

# 11. SENHAS

Senhas nunca poderão ser armazenadas em texto puro.

Utilizar:

```php
password_hash(
    $password,
    PASSWORD_ARGON2ID
);
```

Validação:

```php
password_verify($password, $passwordHash);
```

Não utilizar:

- MD5;
- SHA-1;
- SHA-256 como mecanismo de armazenamento de senha;
- criptografia reversível para senha;
- hashes sem salt.

---

# 12. MFA

MFA deverá ser obrigatório para:

- contas administrativas;
- operações administrativas críticas;
- alteração de privilégios;
- alteração de credenciais;
- operações sensíveis.

Métodos preferenciais:

- WebAuthn/passkeys;
- TOTP.

Recovery codes deverão ser:

- armazenados de forma segura;
- de uso único;
- auditáveis.

---

# 13. AUTORIZAÇÃO

Autenticação responde:

> Quem é o usuário?

Autorização responde:

> Esse usuário pode executar esta operação sobre este recurso?

Toda operação deverá realizar autorização server-side.

Exemplo incorreto:

```php
if ($request['user_id'] == $sessionUserId) {
    // liberar
}
```

O identificador enviado pelo cliente não poderá ser considerado prova de propriedade.

Exemplo conceitual correto:

```text
Sessão válida
      ↓
Obter user_id server-side
      ↓
Localizar recurso
      ↓
Verificar proprietário/permissão
      ↓
Executar operação
```

A especificação anterior explicitamente ressalta que proteger `user_id` não elimina falhas de autorização sobre objetos.

---

# 14. IDOR / BOLA

Todo endpoint que recebe identificadores deverá validar autorização.

Exemplo:

```http
GET /video/123
```

Não deverá ser suficiente possuir o ID `123`.

A aplicação deverá verificar:

```text
sessão
+
usuário
+
recurso
+
permissão
```

Somente então o recurso poderá ser retornado.

---

# 15. SQL INJECTION

Todas as queries que utilizarem dados externos deverão utilizar prepared statements.

PHP/PDO:

```php
$stmt = $pdo->prepare(
    'SELECT id, name
       FROM users
      WHERE email = :email
      LIMIT 1'
);

$stmt->execute([
    ':email' => $email
]);
```

É proibido:

```php
$sql = "SELECT * FROM users WHERE email = '$email'";
```

Também é proibido concatenar diretamente:

- `$_GET`;
- `$_POST`;
- `$_COOKIE`;
- headers;
- JSON recebido;
- parâmetros de rota.

---

# 16. UPDATE E DELETE

Nenhum `UPDATE` ou `DELETE` poderá ser executado sem condição explicitamente validada.

Obrigatório:

```sql
UPDATE sessions
SET revoked_at = NOW()
WHERE id = ?;
```

Proibido:

```sql
UPDATE sessions
SET revoked_at = NOW();
```

Para operações críticas, a aplicação deverá validar o número de linhas afetadas.

---

# 17. CSRF

Endpoints autenticados por cookie que alterem estado deverão possuir proteção CSRF.

Obrigatório para:

- POST;
- PUT;
- PATCH;
- DELETE.

SameSite não deverá ser considerado substituto único para CSRF.

A aplicação deverá utilizar:

- synchronizer token;
- double submit cookie;
- mecanismo equivalente do framework.

---

# 18. XSS

Toda entrada deverá ser considerada não confiável.

Requisitos:

- validação de entrada;
- escaping contextual;
- evitar HTML gerado diretamente com dados não confiáveis;
- evitar `innerHTML` quando desnecessário;
- evitar `eval()`;
- evitar execução dinâmica de JavaScript.

Exemplo preferencial:

```javascript
element.textContent = value;
```

Evitar:

```javascript
element.innerHTML = value;
```

quando `value` vier de fonte não confiável.

---

# 19. CONTENT SECURITY POLICY

A aplicação deverá possuir CSP adequada.

Base conceitual:

```http
Content-Security-Policy:
default-src 'self';
object-src 'none';
base-uri 'self';
frame-ancestors 'none';
```

A política final deverá ser adaptada aos recursos reais da aplicação.

`unsafe-inline` e `unsafe-eval` não deverão ser utilizados sem justificativa técnica documentada.

---

# 20. SECURITY HEADERS

Quando aplicável:

```http
Strict-Transport-Security
Content-Security-Policy
X-Content-Type-Options: nosniff
Referrer-Policy
Permissions-Policy
```

Quando a aplicação não precisar ser incorporada:

```text
frame-ancestors 'none'
```

A especificação original já estabelece esses headers como requisitos de segurança da aplicação.

---

# 21. HTTPS

Produção deverá utilizar exclusivamente HTTPS.

HTTP deverá:

- redirecionar para HTTPS; ou
- ser bloqueado.

Tokens, cookies de sessão e credenciais nunca poderão ser enviados por HTTP.

HSTS deverá ser utilizado quando a operação permanente em HTTPS estiver garantida.

---

# 22. CORS

Não utilizar:

```http
Access-Control-Allow-Origin: *
```

em endpoints autenticados.

Origens deverão ser explicitamente configuradas.

Exemplo:

```text
https://app.exemplo.com
```

Somente origens confiáveis poderão receber credenciais.

---

# 23. CACHE

Respostas contendo:

- tokens;
- sessão;
- credenciais;
- informações sensíveis;

deverão utilizar:

```http
Cache-Control: no-store
```

Não permitir cache implícito de informações sensíveis.

---

# 24. RATE LIMITING

Deverá existir rate limiting para:

- login;
- recuperação de senha;
- MFA;
- endpoints administrativos;
- criação de compartilhamentos;
- downloads;
- operações de alto custo;
- APIs públicas.

Login deverá possuir controle por:

- IP;
- identificador de conta;
- janela temporal.

Em arquitetura distribuída, o rate limiting não deverá depender somente da memória local do processo PHP.

---

# 25. UPLOADS

Caso o sistema permita upload, deverão ser aplicados:

- limite de tamanho;
- validação MIME;
- validação de extensão;
- geração de nome interno;
- armazenamento fora do diretório público;
- bloqueio de execução;
- autorização de acesso;
- controle de espaço;
- registro de auditoria.

O nome original enviado pelo usuário não deverá controlar o caminho físico do arquivo.

Proibido:

```php
$path = "/uploads/" . $_POST['filename'];
```

O caminho deverá ser controlado pelo servidor.

---

# 26. PATH TRAVERSAL

Entradas não poderão controlar diretamente caminhos de arquivos.

Bloquear tentativas envolvendo:

```text
../
..\ 
/etc/
/root/
C:\
```

A aplicação deverá trabalhar preferencialmente com identificadores internos:

```text
video_id
file_id
share_id
```

e resolver o caminho físico exclusivamente no servidor.

---

# 27. DOWNLOAD E STREAMING

Download e streaming deverão possuir a mesma autorização.

Não deverá existir:

```text
arquivo público → acesso direto
```

quando o arquivo exigir autorização.

Fluxo:

```text
Request
   ↓
Sessão
   ↓
Autorização
   ↓
Recurso
   ↓
Arquivo
```

HTTP Range/206 deverá obedecer à mesma autorização.

A existência do identificador do vídeo não deverá conceder acesso ao arquivo.

---

# 28. TOKENS DE COMPARTILHAMENTO

Tokens de compartilhamento deverão ser independentes dos tokens de sessão.

Nunca reutilizar token de sessão como token de compartilhamento.

Características:

- 256 bits;
- CSPRNG;
- token opaco;
- armazenamento somente do hash;
- expiração;
- revogação;
- limite de visualizações;
- limite de downloads;
- rate limiting.

Contadores deverão ser atualizados atomicamente.

---

# 29. JAVASCRIPT

O JavaScript do frontend deverá ser tratado como código executado em ambiente não confiável do ponto de vista de dados.

Não armazenar no JavaScript:

- senha;
- token de sessão;
- secret;
- chave privada;
- credencial MySQL;
- JWT administrativo;
- API key privada.

O JavaScript não deverá decidir autorização.

Exemplo proibido:

```javascript
if (user.role === 'admin') {
    showDeleteButton();
}
```

A ocultação do botão pode ser utilizada para UX, porém o backend deverá sempre validar a autorização.

---

# 30. SEGREDOS

Nunca colocar secrets no:

- PHP;
- JavaScript;
- HTML;
- Dockerfile;
- Git;
- imagem Docker;
- logs.

Proibido:

```dockerfile
ENV DB_PASSWORD=minhaSenha
```

Utilizar:

- Docker Secrets;
- secret manager;
- arquivo protegido fora do Git;
- mecanismo seguro equivalente.

`.env` contendo credenciais não deverá ser versionado.

---

# 31. MYSQL

O MySQL deverá:

- permanecer em rede interna;
- não publicar `3306` para Internet;
- possuir usuário específico da aplicação;
- utilizar menor privilégio;
- possuir senha forte;
- restringir origem;
- possuir backup;
- possuir política de retenção;
- utilizar TLS quando aplicável.

A aplicação não deverá utilizar usuário administrativo do MySQL para operação normal.

Preferencial:

```text
PHP
 ↓
app_user
 ↓
database
```

e não:

```text
PHP
 ↓
root
 ↓
database
```

---

# 32. PRINCÍPIO DE MENOR PRIVILÉGIO NO MYSQL

O usuário da aplicação deverá possuir somente permissões necessárias.

Evitar conceder:

```sql
GRANT ALL PRIVILEGES
```

quando a aplicação não necessitar de todas as permissões.

Operações administrativas deverão utilizar credenciais separadas.

---

# 33. DOCKER — REGRAS OBRIGATÓRIAS

Produção não deverá utilizar:

```yaml
privileged: true
```

Não utilizar:

```yaml
network_mode: host
```

Não utilizar:

```yaml
pid: host
```

Não utilizar:

```yaml
ipc: host
```

Não montar:

```text
/var/run/docker.sock
```

Não montar desnecessariamente:

```text
/
/etc
/root
/proc
/sys
```

Essas configurações são tratadas como regras eliminatórias no baseline de hardening fornecido.

---

# 34. CONTAINER NON-ROOT

O processo PHP deverá executar como usuário não-root.

Exemplo:

```dockerfile
USER app
```

ou:

```yaml
user: "1000:1000"
```

O UID/GID deverá ser compatível com a imagem e os volumes utilizados.

---

# 35. CAPABILITIES

A política padrão deverá ser:

```yaml
cap_drop:
  - ALL
```

Capabilities deverão ser adicionadas somente quando comprovadamente necessárias.

Evitar:

```text
SYS_ADMIN
SYS_PTRACE
SYS_MODULE
NET_ADMIN
DAC_OVERRIDE
DAC_READ_SEARCH
SYS_RAWIO
```

O documento de hardening estabelece `cap_drop: ALL` como baseline.

---

# 36. NO NEW PRIVILEGES

Containers deverão utilizar:

```yaml
security_opt:
  - no-new-privileges:true
```

Objetivo:

impedir obtenção de privilégios adicionais por mecanismos como:

- setuid;
- setgid;
- file capabilities.

---

# 37. FILESYSTEM DO CONTAINER

Quando compatível:

```yaml
read_only: true
```

Diretórios temporários poderão utilizar:

```yaml
tmpfs:
  - /tmp
```

Dados persistentes deverão ficar somente nos volumes necessários.

---

# 38. REDES DOCKER

Preferir redes bridge definidas pelo usuário.

Exemplo:

```yaml
networks:
  frontend:
  backend:
    internal: true
```

Arquitetura recomendada:

```text
proxy
  |
frontend
  |
php
  |
backend
  |
mysql
```

O MySQL deverá estar somente na rede necessária.

Não publicar:

```text
3306:3306
```

salvo necessidade operacional formalmente justificada.

---

# 39. PORTAS

Somente portas realmente necessárias deverão ser publicadas no host.

Exemplo:

```yaml
ports:
  - "80:80"
  - "443:443"
```

A porta do PHP-FPM, caso utilizado, normalmente não deverá ser publicada externamente.

O MySQL também não deverá possuir publicação externa.

---

# 40. LIMITES DE RECURSOS

Os containers deverão possuir limites adequados de:

- CPU;
- memória;
- PIDs;
- armazenamento quando aplicável.

Exemplo:

```yaml
mem_limit: 1g
cpus: "1.0"
pids_limit: 256
```

Os valores acima são exemplos e não deverão ser aplicados automaticamente.

O dimensionamento deverá ser baseado no consumo real da aplicação.

---

# 41. LOGS DO DOCKER

Deverá existir rotação de logs.

Exemplo:

```yaml
logging:
  driver: json-file
  options:
    max-size: "10m"
    max-file: "5"
```

Nunca registrar:

- senha;
- token;
- cookie;
- refresh token;
- MFA;
- secrets.

O baseline Docker também estabelece rotação e retenção como requisitos.

---

# 42. DOCKERFILE

O Dockerfile deverá:

- utilizar imagem base suportada;
- fixar versão;
- evitar `latest`;
- utilizar multi-stage quando aplicável;
- executar como non-root;
- instalar somente dependências necessárias;
- não conter secrets;
- utilizar `.dockerignore`;
- reduzir superfície da imagem.

Exemplo:

```dockerfile
FROM php:8.4-fpm-alpine

RUN addgroup -S app && \
    adduser -S app -G app

WORKDIR /var/www/html

COPY --chown=app:app . .

USER app
```

A versão real deverá ser definida de acordo com a versão PHP efetivamente suportada pelo projeto.

---

# 43. DOCKERIGNORE

Deverá existir `.dockerignore`.

No mínimo avaliar exclusão de:

```text
.git
.git/*
.env
.env.*
*.pem
*.key
*.p12
*.pfx
node_modules
vendor
logs
tmp
```

A lista deverá ser ajustada conforme o processo de build.

Segredos nunca poderão ser enviados ao build context.

---

# 44. IMAGENS

Produção não deverá utilizar:

```yaml
image: app:latest
```

Preferir:

```yaml
image: app:1.0.0
```

Para maior reprodutibilidade:

```text
image@sha256:<digest>
```

Imagens deverão ser provenientes de fontes confiáveis.

---

# 45. SCAN DE VULNERABILIDADES

O CI/CD deverá executar scanner de imagem.

Ferramentas possíveis:

- Trivy;
- Grype;
- Docker Scout.

Vulnerabilidade crítica explorável deverá bloquear promoção para produção, salvo exceção formal.

O scanner não substitui:

- atualização de dependências;
- análise de configuração;
- testes de segurança.

---

# 46. DEPENDÊNCIAS PHP

Dependências Composer deverão ser auditadas.

Exemplo:

```bash
composer audit
```

Não utilizar dependências abandonadas ou vulneráveis sem justificativa documentada.

O arquivo:

```text
composer.lock
```

deverá ser versionado para garantir reprodutibilidade.

---

# 47. DEPENDÊNCIAS JAVASCRIPT

Dependências deverão ser controladas por lockfile.

Utilizar o mecanismo correspondente ao projeto:

```text
package-lock.json
```

ou equivalente.

Executar auditoria:

```bash
npm audit
```

quando aplicável ao projeto.

Dependências críticas deverão ser avaliadas antes da promoção para produção.

---

# 48. GESTÃO DE ERROS PHP

Produção não deverá exibir:

- stack trace;
- caminho físico;
- SQL;
- credenciais;
- variáveis internas;
- detalhes do banco.

Não utilizar:

```ini
display_errors=On
```

em produção.

Os erros deverão ser registrados em logs controlados.

---

# 49. LOGS DA APLICAÇÃO

Os logs deverão conter informações suficientes para investigação sem registrar segredos.

Eventos recomendados:

```text
LOGIN_SUCCESS
LOGIN_FAILURE
LOGOUT
SESSION_CREATED
SESSION_REVOKED
PASSWORD_CHANGED
PASSWORD_RESET_REQUESTED
PASSWORD_RESET_COMPLETED
MFA_ENABLED
MFA_DISABLED
MFA_FAILED
PERMISSION_CHANGED
ROLE_CHANGED
RESOURCE_CREATED
RESOURCE_UPDATED
RESOURCE_DELETED
SHARE_CREATED
SHARE_REVOKED
SHARE_ACCESS
SHARE_DOWNLOAD
RATE_LIMIT_TRIGGERED
AUTHORIZATION_DENIED
SECURITY_EVENT
```

Nunca registrar:

```text
password
session token
cookie
refresh token
MFA code
secret
API key
```

---

# 50. REQUEST ID

Cada requisição deverá possuir identificador de correlação.

Fluxo:

```text
Browser
   ↓
Proxy
   ↓
PHP
   ↓
Logs
```

O mesmo `request_id` deverá ser utilizado para correlacionar os eventos.

O `request_id` não deverá funcionar como credencial.

---

# 51. PROXY TRUST

A aplicação somente poderá confiar em:

```text
X-Forwarded-For
X-Real-IP
Forwarded
```

quando a requisição vier de um proxy explicitamente confiável.

Headers enviados diretamente por clientes externos não deverão ser utilizados como fonte confiável de IP.

---

# 52. BACKUP

Backups deverão possuir:

- controle de acesso;
- criptografia quando necessário;
- retenção definida;
- armazenamento independente;
- teste periódico de restauração.

Backup não deverá permanecer exclusivamente no mesmo host do banco.

---

# 53. HOST LINUX

O host Docker deverá:

- possuir sistema atualizado;
- possuir kernel suportado;
- possuir Docker Engine atualizado;
- possuir containerd atualizado;
- possuir runc atualizado;
- possuir firewall;
- possuir SSH protegido;
- possuir LSM ativo;
- desabilitar serviços desnecessários.

O baseline fornecido estabelece janela operacional de até 7 dias para atualizações de rotina, salvo impossibilidade documentada.

---

# 54. SSH

Recomendado:

```text
PermitRootLogin no
PasswordAuthentication no
PubkeyAuthentication yes
```

Antes de desativar autenticação por senha, deverá ser validada uma chave administrativa funcional.

O acesso SSH deverá ser limitado a redes administrativas sempre que possível.

---

# 55. DOCKER SOCKET

É proibido montar:

```text
/var/run/docker.sock
```

no container da aplicação.

O Docker socket concede capacidade potencialmente equivalente a administração do Docker e pode permitir escalada para o host.

---

# 56. SECcomp / APPARMOR / SELINUX

Seccomp deverá permanecer habilitado.

Não utilizar:

```text
seccomp=unconfined
```

AppArmor/SELinux deverá permanecer ativo quando disponível.

Não desabilitar LSM para contornar problemas de aplicação.

Ajustar a política quando necessário.

---

# 57. DOCKER COMPOSE — BASE DE PRODUÇÃO

Modelo conceitual:

```yaml
services:

  app:
    build:
      context: .
      dockerfile: Dockerfile

    restart: unless-stopped

    user: "1000:1000"

    read_only: true

    security_opt:
      - no-new-privileges:true

    cap_drop:
      - ALL

    pids_limit: 512

    tmpfs:
      - /tmp

    networks:
      - backend

    depends_on:
      - mysql

  mysql:
    image: mysql:8.4

    restart: unless-stopped

    networks:
      - backend

    volumes:
      - mysql_data:/var/lib/mysql

    # Não publicar 3306 externamente.

networks:

  backend:
    internal: true

volumes:

  mysql_data:
```

Esse bloco é um baseline arquitetural. Os valores finais deverão ser ajustados ao funcionamento real da aplicação.

---

# 58. SEPARAÇÃO DE AMBIENTES

Deverão existir ambientes separados para:

```text
development
staging
production
```

Credenciais não deverão ser compartilhadas entre ambientes.

O banco de desenvolvimento não deverá utilizar credenciais de produção.

---

# 59. DESENVOLVIMENTO

Durante desenvolvimento:

- erros podem ser habilitados localmente;
- ferramentas de debug podem existir;
- volumes de código podem ser utilizados;
- portas podem ser publicadas para desenvolvimento.

Essas permissões não deverão ser automaticamente transferidas para produção.

---

# 60. PRODUÇÃO

Produção deverá:

- utilizar HTTPS;
- utilizar containers non-root;
- bloquear privilégios desnecessários;
- possuir limites de recursos;
- possuir logs;
- possuir backup;
- possuir monitoramento;
- utilizar imagens fixadas;
- executar scanner;
- não possuir secrets no Git;
- não publicar MySQL;
- não publicar portas internas;
- possuir autenticação forte;
- possuir autorização server-side.

---

# 61. CRITÉRIOS GO / NO-GO

A implantação deverá ser bloqueada quando existir:

- `privileged: true`;
- Docker socket montado;
- filesystem do host montado sem necessidade;
- `network_mode: host`;
- `pid: host`;
- `ipc: host`;
- `seccomp=unconfined`;
- LSM desabilitado sem justificativa;
- secret dentro da imagem;
- credencial no Git;
- MySQL exposto publicamente;
- container executando como root sem justificativa;
- ausência de limites em serviços sujeitos a DoS;
- SQL Injection não corrigido;
- autorização baseada somente em ID enviado pelo cliente;
- sessão sem proteção;
- CSRF em operações autenticadas sem proteção;
- arquivo privado acessível diretamente;
- path traversal funcional;
- vulnerabilidade crítica explorável sem tratamento.

---

# 62. TESTES OBRIGATÓRIOS

Antes da produção deverão ser testados:

## Autenticação

- login válido;
- login inválido;
- brute force;
- sessão expirada;
- sessão revogada;
- logout;
- session fixation;
- troca de senha;
- revogação global.

## Autorização

- acesso ao próprio recurso;
- acesso ao recurso de outro usuário;
- alteração de `user_id`;
- alteração de role;
- alteração de identificadores;
- acesso administrativo sem permissão.

## Web

- SQL Injection;
- XSS;
- CSRF;
- CORS;
- path traversal;
- HTTP method abuse;
- headers;
- cache.

## Arquivos

- upload inválido;
- upload executável;
- arquivo acima do limite;
- path traversal;
- acesso direto;
- download sem autorização;
- streaming sem autorização;
- Range request sem autorização.

## Docker

- usuário do processo;
- capabilities;
- `privileged`;
- mounts;
- namespaces;
- seccomp;
- AppArmor/SELinux;
- limites;
- portas publicadas.

---

# 63. TESTES DE SQL INJECTION

Devem ser testados, no mínimo:

```text
' OR 1=1 --
' OR '1'='1
admin'--
```

Também deverão ser testados parâmetros:

- GET;
- POST;
- JSON;
- cookies;
- headers;
- IDs de rota.

Resultado esperado:

```text
Nenhuma entrada poderá alterar a estrutura da query.
```

---

# 64. TESTES DE AUTORIZAÇÃO

Para qualquer endpoint:

```text
GET /resource/{id}
```

deverão ser realizados testes:

```text
Usuário A → recurso A → permitido
Usuário A → recurso B → negado
Usuário B → recurso A → negado
Administrador → recurso permitido conforme política
```

O teste deverá validar tanto leitura quanto alteração e exclusão.

---

# 65. TESTES DE CONTAINER

Verificar:

```bash
docker inspect <container>
```

Confirmar:

```text
Privileged=false
```

Verificar usuário:

```bash
docker exec <container> id
```

Verificar portas:

```bash
docker ps
ss -lntup
```

Verificar mounts:

```bash
docker inspect <container>
```

Verificar capabilities e security options.

---

# 66. TESTE DE ISOLAMENTO

Deverá ser validado que a aplicação não possui acesso a:

```text
/var/run/docker.sock
/
/etc do host
/root do host
/proc do host
/sys do host
```

quando não existir requisito explícito.

Também deverá ser validado que o container não consegue acessar serviços internos que não sejam necessários à sua função.

---

# 67. AUDITORIA

Auditorias periódicas deverão verificar:

- código;
- dependências;
- Dockerfile;
- Compose;
- imagens;
- banco;
- secrets;
- permissões;
- volumes;
- redes;
- firewall;
- logs;
- backups.

Ferramentas possíveis:

```text
Trivy
Docker Scout
Docker Bench Security
Lynis
Hadolint
composer audit
npm audit
```

Ferramentas não substituem análise técnica.

---

# 68. GESTÃO DE EXCEÇÕES

Toda exceção deverá possuir:

- componente;
- configuração excepcional;
- justificativa;
- risco;
- impacto;
- controles compensatórios;
- responsável;
- prazo;
- plano de remoção.

Exceções não deverão ser permanentes por padrão.

---

# 69. CHECKLIST DE IMPLEMENTAÇÃO

## Aplicação PHP

- [ ] PDO/prepared statements
- [ ] Sem SQL concatenado
- [ ] Argon2id
- [ ] Sessão server-side
- [ ] Token CSPRNG
- [ ] Token com 256 bits
- [ ] Token armazenado somente como hash
- [ ] Cookie HttpOnly
- [ ] Cookie Secure
- [ ] SameSite
- [ ] `__Host-`
- [ ] Session regeneration
- [ ] Timeout de sessão
- [ ] Expiração absoluta
- [ ] Revogação
- [ ] CSRF
- [ ] Rate limiting
- [ ] Autorização por recurso
- [ ] Auditoria

## MySQL

- [ ] Banco não publicado
- [ ] Usuário exclusivo da aplicação
- [ ] Menor privilégio
- [ ] Queries parametrizadas
- [ ] Índices adequados
- [ ] Backup
- [ ] Teste de restauração
- [ ] TLS quando aplicável

## JavaScript/HTML

- [ ] Sem secrets
- [ ] Sem credenciais em localStorage
- [ ] Output escaping
- [ ] CSP
- [ ] Sem `eval`
- [ ] CORS restritivo
- [ ] CSRF integrado aos requests
- [ ] Autorização não depende do frontend

## Docker

- [ ] Non-root
- [ ] `cap_drop: ALL`
- [ ] `no-new-privileges`
- [ ] `read_only` quando possível
- [ ] `tmpfs`
- [ ] Limites CPU
- [ ] Limites memória
- [ ] `pids_limit`
- [ ] Sem privileged
- [ ] Sem Docker socket
- [ ] Sem host network
- [ ] Sem host PID
- [ ] Sem host IPC
- [ ] Seccomp ativo
- [ ] AppArmor/SELinux ativo
- [ ] Logs rotacionados
- [ ] Imagem versionada
- [ ] Scanner executado

## Produção

- [ ] HTTPS
- [ ] HSTS quando aplicável
- [ ] Firewall
- [ ] SSH por chave
- [ ] Root SSH desabilitado
- [ ] MySQL isolado
- [ ] Secrets fora do Git
- [ ] Backup independente
- [ ] Monitoramento
- [ ] Auditoria
- [ ] Plano de resposta a incidentes

---

# 70. CRITÉRIO FINAL DE CONFORMIDADE

A aplicação somente deverá ser considerada pronta para produção quando:

```text
Código
  +
Banco
  +
Autenticação
  +
Autorização
  +
Frontend
  +
Docker
  +
Host
  +
Secrets
  +
Logs
  +
Backup
  +
Testes
```

estiverem validados.

A existência de um Dockerfile seguro não comprova segurança da aplicação.

A existência de autenticação não comprova autorização correta.

A existência de um `user_id` na sessão não elimina IDOR/BOLA.

A existência de HTTPS não elimina XSS, CSRF ou SQL Injection.

A existência de scanner de vulnerabilidade não substitui análise de configuração.

A configuração efetivamente executada deverá ser considerada a fonte de verdade.

---

# 71. PRINCÍPIO FINAL

O modelo de segurança deverá seguir:

```text
Usuário
   ↓
HTTPS
   ↓
Proxy
   ↓
Sessão segura
   ↓
Autenticação
   ↓
Autorização
   ↓
Validação
   ↓
Aplicação PHP
   ↓
Query parametrizada
   ↓
MySQL
```

Paralelamente:

```text
Host Linux
    ↓
Docker Engine
    ↓
Namespaces
    ↓
Seccomp
    ↓
AppArmor/SELinux
    ↓
Container non-root
    ↓
Capabilities mínimas
    ↓
Recursos limitados
    ↓
Aplicação
```

Nenhuma camada deverá ser considerada suficiente isoladamente.

O objetivo é reduzir simultaneamente:

```text
probabilidade de comprometimento
+
privilégio disponível
+
superfície de ataque
+
movimentação lateral
+
impacto do comprometimento
+
tempo de detecção
```

A segurança deverá ser validada pela configuração efetivamente executada, pelos testes realizados e pelas evidências de conformidade, e não apenas pela intenção declarada no código ou na documentação.