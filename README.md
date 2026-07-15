# Sistema de Gerenciamento de Câmeras e Alarmes v1.0

Sistema web para gerenciamento centralizado de câmeras de segurança e centrais de alarme em ambientes empresariais. Controle de equipamentos, manutenções, relatórios e geolocalização com autenticação hierárquica e API REST.

## Funcionalidades

### Gestão de Câmeras
- Cadastro, edição e exclusão de câmeras
- Associação a locais, modelos e regiões
- Status de operação (Ativo, Manutenção, Inativo)
- Histórico completo de manutenções com anexos
- Geolocalização e consulta automática de CEP
- Painel de controle (dashboard) com métricas

### Gestão de Alarmes
- Cadastro e configuração de centrais de alarme
- Associação a locais, regiões e contas
- Status de monitoramento (Ativo, Inativo, Desligado, Manutenção, Pendente)
- Integração com câmeras GM e DVRs
- Painel de controle (dashboard) com métricas

### Gestão de Locais
- Cadastro de locais com tipos (escola, CMEI, posto de saúde, próprio, operadora)
- Associação a regiões e secretarias
- Endereço completo (CEP, UF, cidade, bairro, logradouro, número)
- Geolocalização com coordenadas
- Horário de funcionamento
- Alarme integrado com conta

### Gestão de Usuários
- Três níveis de acesso hierárquico: `admin`, `supervisor`, `user`
- Cadastro, edição, ativação/bloqueio de usuários
- Redefinição de senha com token temporário
- Troca de senha com validação de força

### Manutenção
- Registro de manutenções de câmeras e alarmes
- Upload de fotos e documentos por equipamento
- Histórico completo com filtros por data, tipo e status

### Relatórios
- Relatórios de câmeras e alarmes com filtros avançados
- Auditoria de operações
- Dados geolocalizados

### API RESTful v2
- 30 endpoints padronizados com formato consistente
- Autenticação por sessão com controle de acesso por nível
- Rate limiting por IP e endpoint (sliding window)
- Validação robusta de entrada (RequestValidator)
- Paginação de resultados
- Suporte a CORS configurável

## Requisitos

- **PHP 8.2+** com extensões: `pdo_mysql`, `json`, `session`, `fileinfo`, `mbstring`, `zip`
- **MySQL 8.0+** ou **MariaDB 10.5+**
- **Composer** para dependências PHP
- **Node.js 20+** (para build do frontend com Vite)
- **Servidor web**: Apache (XAMPP/WAMP/LAMP) ou PHP built-in server

## Instalação

### 1. Clonar e instalar dependências

```bash
git clone https://github.com/GhostTerminals/sistema-cameras-v1.0.git
cd sistema-cameras-v1.0

# Instalar dependências PHP
composer install

# Instalar dependências Node.js (frontend)
npm install
```

### 2. Configurar ambiente

O sistema carrega configurações de uma pasta externa por segurança:

**Windows (XAMPP):**
```powershell
New-Item -ItemType Directory -Path "C:\env\sistema-cameras" -Force
Copy-Item ".env.example" "C:\env\sistema-cameras\.env"
# Editar C:\env\sistema-cameras\.env com credenciais do banco
```

**Linux:**
```bash
sudo mkdir -p /etc/sistema-cameras
sudo cp .env.example /etc/sistema-cameras/.env
sudo chown -R www-data:www-data /etc/sistema-cameras
sudo chmod 700 /etc/sistema-cameras
```

### 3. Importar banco de dados

```bash
mysql -u root -p < config/DB/cftv_gml.sql
```

### 4. Build do frontend (opcional para desenvolvimento)

```bash
npm run build     # Produção
npm run dev       # Desenvolvimento (Vite dev server em :3000)
```

### 5. Iniciar servidor

**Apache (XAMPP):** Acessar `http://localhost/sistema-cameras-v1.0/public/`

**PHP built-in server:**
```bash
php -S localhost:8080 -t public/
# Acessar: http://localhost:8080
```

## Configuração

### Variáveis de Ambiente

```env
# Ambiente: development, testing, production
CAMERAS_ENV=development

# Banco de Dados
DB_HOST=localhost
DB_NAME=cftv_gml
DB_USER=seu_usuario
DB_PASS=sua_senha

# Proxy Confiavel (X-Forwarded-For trust)
PROXY_TRUSTED_IPS=

# Origens CORS (separadas por vírgula)
APP_ALLOWED_ORIGINS=

# Aplicação
APP_TIMEZONE=America/Sao_Paulo

# Sessão (segundos)
CAMERAS_SESSION_TIMEOUT=3600
CAMERAS_SESSION_ABSOLUTE_TIMEOUT=28800

# CSP (0=strict, 1=permite inline styles p/ Bootstrap)
CAMERAS_CSP_ALLOW_INLINE_STYLES=0

# Vite Dev Server: URL base da API para o proxy
VITE_API_URL=http://localhost:80
```

## Estrutura do Projeto

```
sistema-cameras-v1.0/
├── accounts/                    # Gestão de usuários
│   ├── action_*.php            # Handlers de formulário (POST)
│   ├── cadastroUsuario.php     # Cadastro de usuário
│   ├── editarUsuario.php       # Edição de usuário
│   ├── listarUsuario.php       # Listagem de usuários
│   ├── administracao.php       # Painel administrativo
│   ├── alterar_senha.php       # Alteração de senha
│   └── trocar_senha.php        # Troca de senha (token)
├── api/                         # API RESTful v2
│   ├── bootstrap-api.php       # Bootstrap + roteamento da API
│   └── v2/                     # Endpoints (30 arquivos)
│       ├── api_alarmes.php
│       ├── api_cameras.php
│       ├── api_cadastrar_*.php
│       ├── api_health.php
│       ├── api_ping.php
│       └── ...
├── auth/                        # Autenticação
│   ├── login.php               # Página de login
│   ├── action_login_submit.php # Processar login
│   ├── action_logout.php       # Logout
│   └── recuperar_senha.php     # Recuperação de senha
├── config/                      # Configuração do sistema
│   ├── app.php                 # Config de sessão, CSP, HTTPS, env
│   ├── database.php            # Conexão com banco
│   └── DB/                     # Scripts SQL
├── inc/                         # Includes compartilhados
│   ├── security.php            # Hash (Argon2id/bcrypt), CSRF, CSP, audit
│   ├── session_handler.php     # Gerenciamento de sessão
│   ├── single_session.php      # Sessão única com binding IP+UA
│   ├── header.php              # Layout principal + navbar
│   ├── footer.php              # Scripts globais
│   ├── navbar.php              # Menu de navegação
│   ├── pages.php               # Whitelist de páginas
│   ├── access_rules.php        # Regras de acesso por página
│   ├── check_access.php        # Verificação de permissão
│   └── assets.php              # Asset pipeline
├── resources/                   # Páginas de recursos
│   ├── cadastro_cameras.php    # Cadastro de câmeras
│   ├── cadastro_alarmes.php    # Cadastro de alarmes
│   ├── cadastro_locais.php     # Cadastro de locais
│   ├── listar_*.php            # Listagens
│   ├── editar_*.php            # Edições
│   ├── manutencao_*.php        # Manutenções
│   ├── relatorios_*.php        # Relatórios
│   ├── controle_*.php          # Dashboards/painéis
│   ├── home.php                # Dashboard inicial
│   └── action_*.php            # Handlers de formulário
├── src/                         # Classes PHP
│   ├── Api/
│   │   ├── ApiResponse.php     # Respostas padronizadas
│   │   ├── RateLimiter.php     # Rate limiting
│   │   └── RequestValidator.php
│   ├── Services/
│   │   ├── AlarmeService.php   # Lógica de alarmes
│   │   ├── EquipamentoService.php
│   │   ├── LocaisService.php   # CRUD de locais
│   │   └── LookupService.php   # CEP/geocode
│   ├── Validation/
│   │   └── FormValidator.php
│   ├── ErrorHandler.php        # Tratamento global de erros
│   └── assets/                 # Frontend source (TypeScript/CSS)
│       ├── css/
│       └── js/
├── public/                      # Web root (DocumentRoot)
│   ├── index.php               # Entry point único + roteador
│   ├── .htaccess               # Regras Apache
│   ├── sw.js                   # Service Worker (PWA)
│   ├── assets/                 # Frontend compilado (Vite)
│   ├── images/                 # Favicon, ícones PWA
│   └── uploads/                # Anexos de manutenção
├── dist/                        # Build do Vite (saída)
├── docs/                        # Documentação
│   ├── SETUP.md                # Setup detalhado
│   └── SECURITY_ENV.md         # Segurança de ambiente
├── storage/                     # Dados runtime
│   ├── cep_cache/              # Cache de consultas CEP
│   └── ratelimit/              # Dados de rate limiting
├── tests/                       # Testes PHPUnit (80 testes)
│   ├── Unit/                   # Testes unitários
│   ├── Integration/            # Testes de integração
│   ├── Api/                    # Testes de API (requer servidor http)
│   └── bootstrap.php
├── scripts/                     # Scripts auxiliares
│   ├── backup.ps1 / backup.sh  # Backup do banco
│   ├── port-forward.ps1        # Port forwarding
│   ├── copy-assets.js          # Cópia de assets pós-build
│   ├── optimize-database.php   # Otimização do banco
│   └── run_migrations.php      # Runner de migrações
├── composer.json
├── package.json
├── vite.config.ts
├── tsconfig.json
├── postcss.config.js
└── phpunit.xml
```

## Roteamento

O sistema usa roteamento unificado em `public/index.php`. Toda requisição passa pelo entry point que gerencia:

| Tipo | Descrição |
|------|-----------|
| `action` | Processa POST, retorna JSON/redirect, sem layout |
| `mixed` | Intercepta POST com `submit`, senão renderiza com layout |
| `logout` | Limpa sessão e redireciona |
| Página normal | Renderiza com header/footer via `resolvePageScriptPath()` |
| API | Prefixo `api/` → bootstrap da API → JSON |
| 404 | Página não encontrada na whitelist |

## API Endpoints

Todos os endpoints usam `index.php?page=api/` como base. Formato de resposta padronizado:

```json
{"success": true, "code": "SUCCESS", "message": "...", "data": {}, "meta": {"timestamp": "...", "version": "v2", "request_id": "req_..."}}
```

### Autenticação
- `login` — Página de login
- `login_submit` — POST para autenticar
- `logout` — Logout

### Câmeras
- `api/api_cameras` — GET Listar (paginado)
- `api/api_cadastrar_cameras` — POST Cadastrar
- `api/api_editar_camera` — GET/POST Editar
- `api/api_excluir_camera` — POST Excluir
- `api/api_modelos_cameras` — GET Listar modelos
- `api/api_manutencao_cameras` — GET Manutenções
- `api/api_upload_anexo` — POST Upload anexo
- `api/api_listar_anexos` — GET Listar anexos
- `api/api_servir_anexo` — GET Baixar anexo
- `api/api_excluir_anexo` — POST Excluir anexo

### Alarmes
- `api/api_alarmes` — GET Listar (paginado)
- `api/api_cadastrar_alarmes` — POST Cadastrar
- `api/api_editar_alarme` — GET/POST Editar
- `api/api_manutencao_alarmes` — GET Manutenções
- `api/api_modelos_alarmes` — GET Listar modelos

### Dashboard e Relatórios
- `api/api_dashboard` — GET Métricas do dashboard
- `api/api_relatorios_cameras` — GET Relatórios
- `api/api_status` — GET Status agregados

### Utilitários
- `api/api_ping` — GET Ping (público)
- `api/api_health` — GET Health check (admin)
- `api/api_cep_lookup` — GET Consulta CEP
- `api/api_geocode` — GET Geocodificação
- `api/api_locais` — GET Listar locais
- `api/api_get_modelos` — GET Modelos

### Sessão
- `api/session_check` — GET Verificar sessão
- `api/renovar_sessao` — POST Renovar sessão

## Níveis de Acesso

| Nível | Descrição |
|-------|-----------|
| `admin` | Acesso completo, gerencia usuários, health check |
| `supervisor` | Gerencia câmeras, alarmes, locais, manutenções |
| `user` | Visualização e operações básicas |

A hierarquia é cumulativa: `admin > supervisor > user`. Usuários com nível superior automaticamente têm acesso aos níveis inferiores.

## Segurança

### Medidas Implementadas

| Medida | Implementação |
|--------|---------------|
| **Senhas** | Argon2id com auto-rehash de bcrypt legado, validação de comprimento mínimo (8 caracteres) |
| **CSRF** | Token rotativo por sessão (sliding window de 3 tokens), sync automático via fetch interceptor |
| **Sessão** | Sessão única por usuário com binding de IP + User-Agent, `HttpOnly`, `SameSite=Lax`, `Secure` (HTTPS) |
| **Rate Limiting** | Login: 15 tentativas por 15 min por IP; API: configuração por endpoint |
| **SQL Injection** | 100% prepared statements com `EMULATE_PREPARES=false` |
| **XSS** | `htmlspecialchars()` + CSP nonce-based em produção |
| **Upload** | Validação MIME via `finfo`, whitelist de tipos, nomes hasheados |
| **Path Traversal** | Verificação com `realpath()` |
| **CSP** | Nonce-based, política restritiva por diretiva |
| **Headers** | `X-Content-Type-Options: nosniff`, `X-Frame-Options: SAMEORIGIN`, `Referrer-Policy`, `Permissions-Policy`, HSTS |
| **Config** | Variáveis de ambiente em pasta externa (fora do DocumentRoot) |

### Boas Práticas em Produção

1. Definir `CAMERAS_ENV=production`
2. Habilitar `CAMERAS_FORCE_HTTPS_SESSION=1`
3. Configurar `CAMERAS_CSP_ALLOW_INLINE_STYLES=0`
4. Usar HTTPS com HSTS
5. Configurar `APP_ALLOWED_ORIGINS` para CORS
6. Manter `.env` fora do repositório
7. Backups diários do banco (`scripts/backup.sh` ou `scripts/backup.ps1`)

## Testes

```bash
# Rodar todos os testes
vendor/bin/phpunit

# Apenas unit tests
vendor/bin/phpunit tests/Unit/

# Apenas API tests (requer servidor http rodando)
vendor/bin/phpunit tests/Api/

# Análise estática (PHPStan nível 5)
vendor/bin/phpstan analyse
```

**170+ testes, 0 erros PHPStan, 20 skipped, 1 incomplete.**

> Nota: 28 erros pré-existentes da `Trait ParsesInput not found` em `EquipamentoServiceTest`/`AlarmeServiceTest` são de autoload legado — não relacionados às mudanças de performance.

| Suite | Testes | Cobertura |
|-------|--------|-----------|
| Unit | SecurityTest, LocaisServiceTest, AlarmeServiceTest, FileUploadValidationTest, LookupServiceTest | Classes core, validação, segurança, lookups |
| Integration | EquipamentoServiceTest | CRUD com banco |
| Api | AlarmeApiTest, CameraApiTest, HealthTest | Endpoints (requer servidor) |

## Migrations

O sistema possui um runner de migrations em `database/migrate.php` (formato `YYYY_MM_DD_HHMMSS_description.php`).

```bash
# Status: lista migrations aplicadas e pendentes
php database/migrate.php --status

# Aplicar migrations pendentes
php database/migrate.php

# Rollback das últimas N migrations
php database/migrate.php --down 1
```

### Migrations consolidadas no `cftv_gml.sql`

As migrations abaixo já estão incorporadas ao schema principal
(`config/DB/cftv_gml.sql`) e registradas na tabela `migrations` como
aplicadas — ou seja, uma base nova importada via `cftv_gml.sql` **não
precisa rodar `migrate.php`**. Os arquivos `.php` foram removidos do
repositório; o histórico permanece no git para referência.

| Timestamp | Descrição |
|-----------|-----------|
| `2026_07_10_100000` | Coluna `thumbnails` JSON em `equipamentos_anexos` |
| `2026_07_10_110000` | Particionamento mensal de `auditoria_eventos` (RANGE por `created_at`) |
| `2026_07_10_120000` | Buffer de auditoria + procedure + EVENT 10s + reescrita de 15 triggers |
| `2026_07_11_100000` | Índice composto `(os_status_id, updated_at)` em `equipamentos_manutencoes` |
| `2026_07_12_100000` | Índices de performance (Tier 3 #18) |
| `2026_07_13_100000` | (obsoleto) UNIQUE `nome + secretaria_id` em `locais` — substituído pela `07_14` |
| `2026_07_14_100000` | Fix: UNIQUE sobre coluna virtual `nome_ativo` em `locais` (corrige bug NULL+NULL) |

Para adições futuras, crie novos arquivos `.php` em `database/migrations/`
seguindo o formato `YYYY_MM_DD_HHMMSS_description.php` (com `'up'` e
`'down'` idempotentes quando possível) e rode `php database/migrate.php`.

> **Nota sobre DDL MySQL**: O MySQL faz commit implícito em operações DDL (`ALTER`, `CREATE TABLE`, `RENAME`, `DROP`, `TRIGGERS`, `EVENTS`). O runner detecta esse caso e registra a migration post-hoc via INSERT autônomo para evitar reprocesamento. Mensagem `OK (DDL auto-committed; migration registered post-hoc)` é esperada em migrations DDL-heavy.

### Aplicar em produção

```bash
# 1. Aplicar todas as migrations pendentes
php database/migrate.php

# 2. Verificar status final
php database/migrate.php --status
# Deve mostrar: Total: N | Applied: N | Pending: 0
```

## Performance

O sistema passou por uma análise e otimização de performance em 3 tiers (commits `e2bf436`, `d7cfb20`, `d049535`, `e2de9d6`). Diagnóstico completo em `docs/concluidos/Relatorio_Performance.md` (relatório concluído, mantido para referência histórica).

### Resumo das otimizações aplicadas

| Tier | Item | Impacto |
|------|------|---------|
| **1** | Fix `Cache-Control` API (14 endpoints GET) | **Alto** — ativa cache browser 60s |
| **1** | Dashboard APCu TTL 10s→60s | **Alto** — 6x menos cache misses |
| **1** | Dashboard: `WHERE deleted_at IS NULL` em alarmes | Bug fix + perf |
| **1** | Dashboard query 7: filtro `os_status_id IN (1,2)` | **Alto** — evita full scan |
| **1** | Cache APCu 300s em lookups (status, procedimento, getDefaultIds) | Médio |
| **1** | `.htaccess`: `Vary: Accept-Encoding` + `immutable` + servir `.gz` precomprimidos | Médio |
| **1** | Remover `UPPER()` de queries (collation é `utf8mb4_unicode_ci`) | Médio — usa índices UNIQUE |
| **2** | Eliminar queries 8+9 do dashboard (duplicam 1+3) | Médio |
| **2** | COUNT trimado em `api_relatorios_cameras.php` (sem 14 JOINs) | **Alto** |
| **2** | APCu 30s em `api_cameras.php` + `api_alarmes.php` (hash de filtros) | **Alto** |
| **2** | APCu em `api_manutencao_alarmes.php` (espelhar gêmeo) | Médio |
| **2** | Chart.js isolado em chunk `vendor-chart` (`manualChunks`) | Médio |
| **2** | ETag + 304 Not Modified em `api_servir_anexo.php` | Médio |
| **2** | APCu 60s em `api_relatorios_cameras.php` (hash de filtros) | Médio |
| **3** | Índices DB: `central_alarmes(ip, mac, numero_sei)`, `equipamentos_anexos(created_at, created_by)` | **Alto** |
| **3** | Endpoints `api_status`/`api_modelos_*` usando `LookupService` com APCu 300s | Baixo |
| **3** | `<link rel="modulepreload">` para entry chunks (fetch+parse em paralelo) | Médio |
| **3** | **Brotli precompressão** no Vite (level 11) + `.htaccess` serve `.br` com prioridade sobre `.gz` | Médio — ~17% extra menor que gzip |
| **3** | **Self-host Font Awesome** via `@fortawesome/fontawesome-free` npm + SCSS import + webfonts no build | Médio — elimina CDN render-blocking |
| **3** | Script `scripts/check-opcache.php` — smoke test OPcache para CI/deploy | **Alto** se OPcache OFF |

### Estrutura de cache

- **APCu** (opcache de usuário): 9 pontos de cache — dashboard, lookups, endpoints críticos
- **HTTP `Cache-Control`**: 14 endpoints GET idempotentes com cache browser
- **ETag/304**: `api_servir_anexo.php` (anexos imutáveis)
- **Service Worker**: 3 caches (app/api/assets), cache-first para assets com hash, network-only para API
- **Brotli + gzip precomprimidos**: `.htaccess` serve `.br` com prioridade, fallback `.gz`

### Deploy de performance em produção

```bash
# 1. Aplicar migrations de índices (Tier 3 #18)
php database/migrate.php

# 2. Verificar OPcache ativo (exit 1 se OFF — bloquear deploy)
php scripts/check-opcache.php

# 3. Build frontend com Brotli + Font Awesome self-host
npm install && npm run build

# 4. Verificar suporte a Brotli no Apache 2.4+
#    (mod_brotli ativo OU RewriteCond servindo .br — já configurado no .htaccess)

# 5. Validar cache hits do Font Awesome self-hosted no DevTools
#    (não deve haver mais requests para cdnjs.cloudflare.com/font-awesome)

# 6. Monitorar slow_query_log MySQL por 1 semana pós-deploy
```

## Tecnologias

- **Backend**: PHP 8.2+, PDO, Monolog
- **Frontend**: TypeScript, Vite 8, Bootstrap 5.3, Chart.js, PostCSS, esbuild
- **Banco**: MySQL 8.0+ / MariaDB 10.5+
- **Ícones**: Font Awesome 6 (self-host via npm `@fortawesome/fontawesome-free`)
- **Mapa**: Leaflet
- **Testes**: PHPUnit 10, Vitest
- **Análise**: PHPStan (nível 5), PHP_CodeSniffer, roave/security-advisories
- **Build**: Vite, compression plugin, PurgeCSS

---

**Versão**: 1.0.0
**Última atualização**: 2026-07-12 (Performance Tier 1+2+3 aplicados)

Passos para nova implantação:
1. `mysql -u root -p < config/DB/cftv_gml.sql` — importa schema completo (com todos os objetos: índices, triggers, procedures, eventos, partições e tabela `migrations` já populada)
2. Copiar `.env.example` → configurar credenciais em `C:\env\sistema-cameras\.env` (Windows) ou `/etc/sistema-cameras/.env` (Linux)
3. `composer install` — dependências PHP
4. `npm install && npm run build` — build frontend (Vite + Brotli + Font Awesome self-host + webfonts)
5. `php database/migrate.php --status` — confirma que não há migrations pendentes (`Total: 0 | Applied: 14 | Pending: 0`)
6. `php scripts/check-opcache.php` — validar OPcache ativo (recomendado)
7. Configurar Apache/Nginx apontando para `public/`

Atualizações em instâncias existentes:
1. `git pull` — obter código novo
2. `composer install && npm install && npm run build` — atualizar dependências e rebuild frontend
3. `php database/migrate.php` — aplicar migrations pendentes (índices, auditoria buffer, etc.) — em instalações novas via `cftv_gml.sql`, todas já aplicadas
4. `php scripts/check-opcache.php` — validar OPcache (em especial se mudou PHP.ini)
