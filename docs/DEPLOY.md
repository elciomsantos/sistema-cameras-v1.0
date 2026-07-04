# Deploy — CD via GitHub Actions

## Fluxo

1. Push na `main` → CI executa lint, testes, PHPStan, security check, frontend build
2. Job `docker` builda a imagem e publica em `ghcr.io/ghostterminals/sistema-cameras-v1.0`
3. Job `deploy` faz SSH no VPS, faz pull da nova imagem e reinicia os containers

## Pré-requisitos no servidor

```bash
# Diretório do projeto
sudo mkdir -p /opt/sistema-cameras
sudo chown $USER:$USER /opt/sistema-cameras

# Estrutura
/opt/sistema-cameras/
├── docker-compose.prod.yml    # copiado do repositório
├── .env                       # criado manualmente (não versionado)
├── config/
│   └── DB/
│       └── cftv_gml.sql       # schema inicial (cópia do repo)
└── storage/                   # criado automaticamente
```

## Arquivo `.env`

Crie `/opt/sistema-cameras/.env`:

```bash
DB_ROOT_PASSWORD=senha_root_forte
DB_PASS=senha_usuario_forte
DB_NAME=cftv_gml
DB_USER=cftv_user
```

## Instalação inicial (primeira vez)

```bash
cd /opt/sistema-cameras

# Instalar Docker se não tiver
curl -fsSL https://get.docker.com | sh
sudo usermod -aG docker $USER

# Baixar docker-compose.prod.yml do repositório
curl -O https://raw.githubusercontent.com/GhostTerminals/sistema-cameras-v1.0/main/docker-compose.prod.yml

# Schema inicial
mkdir -p config/DB
curl -o config/DB/cftv_gml.sql https://raw.githubusercontent.com/GhostTerminals/sistema-cameras-v1.0/main/config/DB/cftv_gml.sql

# Subir
docker compose -f docker-compose.prod.yml up -d
```

## Secrets do GitHub Actions

Configurar em **Settings → Secrets and variables → Actions**:

| Secret | Descrição |
|--------|-----------|
| `SSH_HOST` | IP ou domínio do VPS |
| `SSH_USER` | Usuário SSH |
| `SSH_PRIVATE_KEY` | Chave privada SSH (sem passphrase) |
| `SSH_PORT` | Opcional (default 22) |

### Gerar chave SSH para deploy

```bash
ssh-keygen -t ed25519 -f ~/.ssh/deploy_key -N ""
cat ~/.ssh/deploy_key.pub >> ~/.ssh/authorized_keys
```

Cole o conteúdo de `~/.ssh/deploy_key` no secret `SSH_PRIVATE_KEY`.

## Testar manualmente

```bash
# Build local
docker build -t cameras-app .

# Executar com docker compose local
docker compose up -d
```

## Atualização automática

Após configurar os secrets, todo push na `main`:

1. CI valida o código
2. Se passa: build → push para ghcr.io → SSH no servidor → `docker compose pull && up -d`
3. Zero downtime (contêiner reinicia em segundos)
