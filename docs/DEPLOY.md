# Deploy — CD via GitHub Actions

## Fluxo

1. Push de tag `v*` → job CD builda a imagem Docker e publica em `ghcr.io/<repo>/sistema-cameras-v1.0` (tags semver + sha).
2. Push na `main` → CI executa lint, testes, PHPStan, security check, frontend build e valida o build Docker (sem publicar).

> **Nota:** o CD **não** executa deploy automático via SSH. O pull e restart no servidor é feito manualmente (ver "Atualização manual" abaixo). O job `deploy` do workflow apenas publica a imagem no GHCR.

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
curl -O https://raw.githubusercontent.com/elciomsantos/sistema-cameras-v1.0/main/docker-compose.prod.yml

# Schema inicial
mkdir -p config/DB
curl -o config/DB/cftv_gml.sql https://raw.githubusercontent.com/elciomsantos/sistema-cameras-v1.0/main/config/DB/cftv_gml.sql

# Subir
docker compose -f docker-compose.prod.yml up -d
```

## Testar manualmente

```bash
# Build local
docker build -t cameras-app .

# Executar com docker compose local
docker compose up -d
```

## Atualização manual (servidor)

Após publicar uma nova imagem no GHCR (via tag `v*`), atualize o servidor:

```bash
cd /opt/sistema-cameras
docker compose -f docker-compose.prod.yml pull
docker compose -f docker-compose.prod.yml up -d
```

Ou, apontando para uma tag específica:

```bash
# No docker-compose.prod.yml, defina APP_TAG (default: latest)
APP_TAG=v1.0.1 docker compose -f docker-compose.prod.yml up -d
```
