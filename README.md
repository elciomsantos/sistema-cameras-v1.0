# Sistema de Gerenciamento de Câmeras v1.0

Sistema web de gerenciamento centralizado de câmeras e alarmes para ambientes empresariais (intranet).

## Funcionalidades

### Gestão de Câmeras
- Cadastro, edição e exclusão de câmeras
- Associação a locais e modelos
- Status de operação (Ativo, Manutenção, Inativo)
- Histórico de manutenções
- Geolocalização e consulta CEP
- Anexos e documentação por equipamento

### Gestão de Alarmes
- Cadastro e configuração de centrais de alarme
- Associação a locais e regiões
- Status de monitoramento
- Consulta de status (Ativo, Inativo, Desligado, Manutenção, Pendente)
- Integração com câmeras GM
- Endereço e contato do local

### Gestão de Locais
- Cadastro de locais com tipos (escola, posto de saúde, etc.)
- Associação a regiões e secretarias
- Informações de endereço completas (CEP, UF, cidade, bairro, logradouro)
- Coordenadas de geolocalização
- Alarme integrado com conta de alarme
- Horário de funcionamento

### Gestão de Usuários
- Três níveis de acesso: `admin`, `supervisor`, `user`
- Redefinição de senha comtoken temporário
- Ativação/bloqueio de usuários
- Auditoria de alterações

### Manutenção
- Registro de manutenções de câmeras e alarmes
- Anexos de fotos e documentos
- Histórico completo por equipamento
- Filtros por data, tipo e status

### Relatórios
- Relatórios de câmeras com filtros
- Auditoria de operações
- Dados geolocalizados

### API RESTful v2
- Endpoints padronizados com formato consistente
- Autenticação por sessão
- Rate limiting por IP e endpoint
- Validação robusta de entrada
- Paginação de resultados
- Auditoria de auditorias

## Requisitos

- **PHP 8.2+** com extensões: `pdo_mysql`, `json`, `session`, `fileinfo`
- **MySQL 8.0+** ou **MariaDB 10.5+**
- **Composer** para dependências PHP

## Instalação

```bash
# Clonar repositório
git clone https://github.com/GhostTerminals/sistema-cameras-v1.0.git
cd sistema-cameras-v1.0

# Instalar dependências
composer install

# Configurar ambiente
cp .env.example .env
# Editar .env com credenciais do banco

# Importar banco de dados
mysql -uroot -p < config/DB/cftv_gml.sql

# Iniciar servidor de desenvolvimento
php -S localhost:8080 -t public/

# Acessar: http://localhost:8080
```

## Configuração

### Variáveis de Ambiente

```env
# Banco de Dados
DB_HOST=localhost
DB_NAME=cftv_gml
DB_USER=seu_usuario
DB_PASS=sua_senha

# Ambiente
CAMERAS_ENV=development

# Sessão (opcionais)
CAMERAS_SESSION_TIMEOUT=3600
CAMERAS_SESSION_ABSOLUTE_TIMEOUT=28800

# Segurança - Senha
ARGON2_MEMORY_COST=65536
ARGON2_TIME_COST=4
ARGON2_THREADS=3

# Segurança - CSP
CAMERAS_CSP_ALLOW_INLINE_STYLES=1
```

## Estrutura do Projeto

```
sistema-cameras-v1.0/
├── accounts/                    # Páginas de gestão de usuários
│   ├── action_*.php            # Action handlers (POST)
│   ├── cadastroUsuario.php
│   ├── editarUsuario.php
│   ├── listarUsuario.php
│   ├── alterar_senha.php
│   └── trocar_senha.php
├── api/                         # API RESTful
│   ├── bootstrap-api.php        # Bootstrap da API
│   ├── v2/                      # Endpoints v2
│   │   ├── api_alarmes.php
│   │   ├── api_auditoria_cameras.php
│   │   ├── api_cadastrar_cameras.php
│   │   ├── api_cameras.php
│   │   ├── api_cep_lookup.php
│   │   ├── api_dashboard.php
│   │   ├── api_excluir_camera.php
│   │   ├── api_excluir_anexo.php
│   │   ├── api_geocode.php
│   │   ├── api_health.php
│   │   ├── api_listar_anexos.php
│   │   ├── api_manutencao_alarmes.php
│   │   ├── api_manutencao_cameras.php
│   │   ├── api_modelos_cameras.php
│   │   ├── api_ping.php
│   │   ├── api_relatorios_cameras.php
│   │   ├── api_servir_anexo.php
│   │   └── api_upload_anexo.php
│   ├── RateLimiter.php          # Rate limiting por IP
│   ├── ApiResponse.php          # Respostas padronizadas
│   └── RequestValidator.php     # Validação de entrada
├── auth/                        # Autenticação
│   ├── action_login_submit.php
│   ├── action_logout.php
│   ├── action_recuperar_senha.php
│   ├── login.php
│   └── recuperar_senha.php
├── config/                      # Configuração
│   ├── app.php
│   └── database.php
├── inc/                         # Includes comuns
│   ├── access_rules.php         # Regras de acesso por página
│   ├── check_access.php         # Verificação de permissão
│   ├── footer.php               # Scripts globais (fetch CSRF sync)
│   ├── header.php               # Layout + navbar
│   ├── navbar.php               # Menu de navegação
│   ├── security.php             # Hash, CSRF, policies, audit
│   ├── session_handler.php
│   └── single_session.php       # Controle de sessão única
├── public/                      # Web root
│   └── index.php               # Entry point + roteamento
├── resources/                   # Páginas de recursos
│   ├── action_*.php            # Action handlers (POST)
│   ├── cadastro_alarmes.php
│   ├── cadastro_cameras.php
│   ├── cadastro_locais.php
│   ├── editar_alarmes.php
│   ├── editar_cameras.php
│   ├── editar_locais.php
│   ├── listar_locais.php
│   ├── manutencao_alarmes.php
│   └── manutencao_cameras.php
├── src/                         # Classes PHP
│   ├── Api/
│   │   ├── ApiResponse.php
│   │   └── RateLimiter.php
│   ├── ErrorHandler.php
│   ├── Services/
│   │   ├── AlarmeService.php
│   │   ├── EquipamentoService.php
│   │   ├── LocaisService.php
│   │   └── LookupService.php
│   └── Validation/
│       └── FormValidator.php
├── tests/                       # Testes PHPUnit
│   ├── bootstrap.php
│   ├── phpunit.xml
│   ├── Unit/
│   │   ├── AlarmeServiceTest.php
│   │   ├── FileUploadValidationTest.php
│   │   ├── LocaisServiceTest.php
│   │   └── SecurityTest.php
│   ├── Integration/
│   │   └── EquipamentoServiceTest.php
│   └── Api/
│       ├── AlarmeApiTest.php
│       ├── CameraApiTest.php
│       └── HealthTest.php
├── composer.json
└── README.md
```

## Níveis de Acesso

| Nível | Descrição |
|-------|-----------|
| `admin` | Acesso completo ao sistema, gerencia usuários |
| `supervisor` | Gerencia câmeras, alarmes, locais; acesso ao painel administrativo |
| `user` | Visualização e operações básicas |

## Roteamento

O sistema usa roteamento unificado em `public/index.php`. Rotas definidas no array `$routes`:

| Tipo | Descrição |
|------|-----------|
| `action` | Processa POST, retorna JSON, sem layout |
| `mixed` | Intercepta POST com `submit`, senão renderiza com layout |
| `logout` | Limpa sessão e redireciona |

## API Endpoints

### Autenticação
- `GET /index.php?page=login` - Login
- `POST /index.php?page=login_submit` - Processar login
- `POST /index.php?page=logout` - Logout

### Câmeras
- `GET /index.php?page=api/v2/api_cameras` - Listar
- `POST /index.php?page=api/v2/api_cadastrar_cameras` - Criar
- `GET /index.php?page=api/v2/api_editar_camera` - Editar
- `POST /index.php?page=api/v2/api_excluir_camera` - Excluir
- `GET /index.php?page=api/v2/api_manutencao_cameras` - Manutenções

### Alarmes
- `GET /index.php?page=api/v2/api_alarmes` - Listar
- `GET /index.php?page=api/v2/api_manutencao_alarmes` - Manutenções

### Utilitários
- `GET /index.php?page=api/v2/api_cep_lookup` - Consulta CEP
- `GET /index.php?page=api/v2/api_geocode` - Geocodificação
- `GET /index.php?page=api/v2/api_health` - Health check
- `GET /index.php?page=api/v2/api_ping` - Ping

### Upload
- `POST /index.php?page=api/v2/api_upload_anexo` - Upload de arquivo
- `GET /index.php?page=api/v2/api_servir_anexo` - Baixar anexo
- `POST /index.php?page=api/v2/api_excluir_anexo` - Excluir anexo

## Testes

```bash
# Rodar todos os testes
vendor/bin/phpunit

# Apenas unit tests
vendor/bin/phpunit tests/Unit/

# Com cobertura
vendor/bin/phpunit --coverage-text
```

**Testes disponíveis:**
- `SecurityTest` - Hash, CSRF, policy de senhas, access levels
- `LocaisServiceTest` - CRUD de locais
- `AlarmeServiceTest` - Validação e parâmetros
- `FileUploadValidationTest` - Validação de upload
- `EquipamentoServiceTest` - CRUD de equipamentos

**API tests** precisam de servidor web rodando (`php -S localhost:8080`).

## Segurança

### Medidas Implementadas

| Medida | Implementação |
|--------|---------------|
| **Senhas** | Argon2id com auto-rehash de bcrypt legado |
| **CSRF** | Token por sessão, sync automático via fetch |
| **Session** | `HttpOnly`, `SameSite=Lax`, `Secure` (HTTPS) |
| **Rate Limiting** | 3-5 tentativas por 15 min por IP |
| **SQL Injection** | 100% prepared statements |
| **XSS** | `htmlspecialchars` + CSP nonce |
| **Upload** | MIME via `finfo`, whitelist, nomes hash |
| **Path Traversal** | Verificação com `realpath` |
| **CSP** | Nonce-based em produção |
| **Headers** | X-Content-Type-Options, X-Frame-Options, HSTS |

### Boas Práticas em Produção

1. Definir `CAMERAS_ENV=production`
2. Configurar `CAMERAS_CSP_ALLOW_INLINE_STYLES=0`
3. Usar HTTPS com HSTS
4. Configurar `APP_ALLOWED_ORIGINS` para CORS
5. Definir `CAMERAS_FORCE_HTTPS_SESSION=1`
6. Não expor `.env` no repositório
7. Backups diários do banco

## Resolução de Problemas

### Sessão expira rapidamente
Verificar `CAMERAS_SESSION_TIMEOUT` e `CAMERAS_SESSION_ABSOLUTE_TIMEOUT` no `.env`.

### Erro de CSRF
Limpar cookies do navegador e fazer login novamente. Verificar que o `meta csrf-token` está sendo atualizado pelo fetch interceptor.

### Login não funciona
Verificar `error_log` do PHP, checar credenciais do banco em `config/database.php`.

### API retorna 401
Verificar que há sessão ativa (`$_SESSION['usuario']`) e que `renovar_sessao` não está consumindo tokens CSRF dos formulários.

## Tecnologias

- **Backend**: PHP 8.2+
- **Banco**: MySQL 8.0+ / MariaDB 10.5+
- **Frontend**: HTML, CSS, JavaScript (vanilla + jQuery + DataTables)
- **Ícones**: Font Awesome 6
- **Mapa**: Leaflet
- **Testes**: PHPUnit 10
- **Dependências**: Composer (Monolog)

---

**Versão**: 1.0.0  
**Última atualização**: 2026-06-29