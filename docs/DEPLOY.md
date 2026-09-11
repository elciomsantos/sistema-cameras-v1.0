# Deploy — CD via GitHub Actions

## Fluxo

1. Push de tag `v*` → job CD builda a imagem Docker (`docker/Dockerfile.nginx`) e publica em `ghcr.io/<repo>/sistema-cameras-v1.0` (tags semver + sha).
2. Push na `main` → CI executa lint, composer/npm audit, build Docker (Nginx) e Trivy (sem publicar).

> **Nota:** o CD **não** executa deploy automático via SSH. O pull e restart no servidor é feito manualmente (ver "Atualização manual" abaixo). O job `deploy` do workflow apenas publica a imagem no GHCR.

## TLS por padrão (intranet)

O container `app` termina TLS no próprio Nginx:

| Host | Container | Finalidade                     |
|------|-----------|--------------------------------|
| `${APP_PORT:-80}`   | `8080` | Redirect 301 → HTTPS      |
| `${APP_HTTPS_PORT:-443}` | `8443` | HTTPS (TLS) com a aplicação |

- **Sem certificado montado** → o entrypoint gera certificado **self-signed** automaticamente (o browser mostra aviso; aceitável em intranet corporativa; o acesso funciona via `https://<servidor>/`).
- **Com CA interna** → copie `server.crt`/`server.key` da sua CA para o volume `nginx_certs` e reinicie:

```bash
docker cp server.crt sistema-cameras-app-1:/etc/nginx/certs/server.crt
docker cp server.key sistema-cameras-app-1:/etc/nginx/certs/server.key
docker compose -f docker-compose.prod.yml restart app
```

- Os clientes devem confiar na CA interna (ou aceitar o self-signed).
- Cookies de sessão são `Secure` (o Nginx detecta `$https` automaticamente).

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

Crie `/opt/sistema-cameras/.env` (senhas com valores fortes e únicos):

```bash
DB_ROOT_PASSWORD=senha_root_forte
DB_PASS=senha_usuario_forte
DB_NAME=cftv_gml
DB_USER=cftv_user

# Acesso HTTPS/HTTP no host (defaults: 443 / 80)
APP_PORT=80
APP_HTTPS_PORT=443

# Sentry (opcional; em branco = desativado)
SENTRY_DSN=

# IPs de proxy confiáveis (vazio = nenhum)
PROXY_TRUSTED_IPS=
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

# Subir (imagem do GHCR + schema init no MySQL)
docker compose -f docker-compose.prod.yml up -d
```

Acesse `https://<servidor>/` (aceite o certificado na primeira vez).

## Testar manualmente

```bash
docker compose -f docker-compose.prod.yml ps   # db + app healthy
curl -k https://localhost/                     # 200 (front controller)
curl -I http://localhost/                      # 301 -> https
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
# No REPOSITORY ghcr.io, defina APP_TAG (default: latest)
APP_TAG=v1.0.1 docker compose -f docker-compose.prod.yml up -d
```

> **Nota:** o compose usa `image: ghcr.io/elciomsantos/sistema-cameras-v1.0:${APP_TAG:-latest}`.
> Defina `GITHUB_REPOSITORY` para apontar para outro repositório GHCR.