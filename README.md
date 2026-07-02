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
│   ├── RateLimiter.php         # Rate limiting sliding window
│   ├── ApiResponse.php         # Respostas padronizadas
│   ├── RequestValidator.php    # Validação de entrada
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
```

**80 testes, 0 falhas, 0 warnings.**

| Suite | Testes | Cobertura |
|-------|--------|-----------|
| Unit | SecurityTest, LocaisServiceTest, AlarmeServiceTest, FileUploadValidationTest | Classes core, validação, segurança |
| Integration | EquipamentoServiceTest | CRUD com banco |
| Api | AlarmeApiTest, CameraApiTest, HealthTest | Endpoints (requer servidor) |

## Tecnologias

- **Backend**: PHP 8.2+, PDO, Monolog
- **Frontend**: TypeScript, Vite 8, Bootstrap 5.3, Chart.js, PostCSS, esbuild
- **Banco**: MySQL 8.0+ / MariaDB 10.5+
- **Ícones**: Font Awesome 6
- **Mapa**: Leaflet
- **Testes**: PHPUnit 10, Vitest
- **Análise**: PHPStan (nível max), PHP_CodeSniffer, Security Checker
- **Build**: Vite, compression plugin, PurgeCSS

---

**Versão**: 1.0.0
**Última atualização**: 2026-06-30

Passos para nova implantação:
1. mysql -u root < config/DB/cftv_gml.sql
2. Copiar .env.example → configurar credenciais em C:\env\sistema-cameras\.env (Windows) ou /etc/sistema-cameras/.env (Linux)
3. composer install
4. npm install && npm run build
5. Configurar Apache/Nginx apontando para public/
