# Setup — Sistema de Câmeras

Guia único de instalação (Linux produção e Windows desenvolvimento). Concatena
a configuração do `.env` externo com o boot de dependências (Composer + npm) e
build frontend (Vite).

---

## 1. Arquitetura de Configuração

```
DESENVOLVIMENTO (Windows + XAMPP)
└── C:\env\sistema-cameras\
    └── .env

PRODUÇÃO (Linux)
└── /etc/sistema-cameras/
    └── .env
```

> O arquivo `.env` **não fica dentro do projeto** — isto evita versionar
> credenciais. A aplicação procura os dois caminhos acima em ordem.

---

## 2. Pré-requisitos

| Ferramenta | Versão mínima | Notas |
|-------------|---------------|-------|
| PHP         | 8.3           | Extensões: pdo_mysql, apcu, openssl, mbstring, fileinfo |
| Composer    | 2.4+          | Para `composer install` e `composer audit` |
| Node.js     | 20 LTS+       | Para `npm install && npm run build` |
| MySQL/MariaDB | 8.0+ / 10.5+ | Schema em `config/DB/cftv_gml.sql` |
| Apache      | 2.4+          | mod_rewrite, mod_deflate, mod_brotli (opcional) |

---

## 3. Passo a passo (Linux produção)

```bash
# 1) Clone o repositório
git clone <repo-url> sistema-cameras
cd sistema-cameras

# 2) Crie a pasta externa de configuração
sudo mkdir -p /etc/sistema-cameras
sudo chown www-data:www-data /etc/sistema-cameras
sudo chmod 700 /etc/sistema-cameras

# 3) Copie e edite o .env
sudo cp .env.example /etc/sistema-cameras/.env
sudo chown www-data:www-data /etc/sistema-cameras/.env
sudo chmod 600 /etc/sistema-cameras/.env
sudo nano /etc/sistema-cameras/.env   # preencha credenciais

# 4) Instale dependências PHP
composer install --no-interaction --prefer-dist

# 5) Instale dependências Node e gere o build do frontend
npm install
npm run build

# 6) Importe o schema do banco
mysql -u root -p < config/DB/cftv_gml.sql
#    Em base nova, a tabela `migrations` já é criada e populada —
#    confirme com: php database/migrate.php --status
#    (esperado: Total: 0 | Applied: 14 | Pending: 0)

# 7) Ajuste permissões de storage/cache
if [ -d "storage" ]; then
    sudo chown -R www-data:www-data storage
    sudo chmod -R 775 storage
fi

# 8) Reinicie o Apache
sudo systemctl restart apache2
```

Acesse: `http://sistema-cameras.local`

---

## 4. Passo a passo (Windows desenvolvimento — XAMPP)

```powershell
# 1) Crie a pasta externa de configuração
New-Item -ItemType Directory -Path "C:\env\sistema-cameras" -Force

# 2) Copie o .env.example
Copy-Item ".env.example" "C:\env\sistema-cameras\.env"

# 3) Edite com as credenciais locais
notepad "C:\env\sistema-cameras\.env"

# 4) Instale dependências
composer install
npm install
npm run build

# 5) Importe o schema
#    No MySQL Workbench ou via linha de comando:
#    mysql -u root -p cftv_gml < config/DB/cftv_gml.sql
```

Edite `C:\xampp\php\php.ini` para desenvolvimento:
```ini
always_populate_raw_post_data = -1
error_reporting = E_ALL
display_errors = On
```

---

## 5. Variáveis do `.env`

```env
CAMERAS_ENV=development                  # development | production
DB_HOST=localhost
DB_NAME=cftv_gml
DB_USER=seu_usuario
DB_PASS=sua_senha_forte
APP_TIMEZONE=America/Sao_Paulo
CAMERAS_SESSION_TIMEOUT=3600             # segundos de inatividade
CAMERAS_SESSION_ABSOLUTE_TIMEOUT=28800   # 8h — expiração absoluta
CAMERAS_CSP_ALLOW_INLINE_STYLES=0        # mantenha 0 (CSP estrita)
```

---

## 6. Solução de Problemas

### "Arquivo de configuração não encontrado"
- Windows: confirme `C:\env\sistema-cameras\.env`
- Linux: `ls -l /etc/sistema-cameras/.env` (permissão `600`)

### "Configurações ausentes: DB_PASS"
O `.env` existe mas não contém a variável `DB_PASS`. Adicione a linha.

### "composer: command not found"
```bash
sudo apt install composer -y
```

### "npm: command not found"
Instale Node.js 20 LTS+ (via nvm ou apt).

### Build do Vite falha
- Apague `node_modules` e `dist`: `rm -rf node_modules dist`
- Reinstale: `npm install && npm run build`

### `php database/migrate.php --status` mostra itens pendentes
Em base nova via `cftv_gml.sql`, deve mostrar `Total: 0 | Applied: 14 | Pending: 0`.
Se mostrar pendentes, verifique se importou o `cftv_gml.sql` completo (trecho
de `CREATE TABLE migrations` + `INSERT INTO migrations`).

---

## 7. Segurança

- **Nunca** commite arquivos `.env`
- **Nunca** coloque credenciais no código
- Use senhas fortes (mínimo 32 caracteres)
- Em produção, defina `CAMERAS_ENV=production`
- Permissões restritivas: `chmod 600 /etc/sistema-cameras/.env`
- Em produção, ative OPcache — valide com `php scripts/check-opcache.php`
  (exit 1 se OPcache estiver OFF)

---

## 8. Próximos passos

- `php scripts/check-opcache.php` — smoke test OPcache
- `composer audit` — checar vulnerabilidades em dependências PHP
- Configurar VirtualHost do Apache apontando para `public/`
- Ver `docs/DEPLOY.md` para implantação completa (Docker, CI/CD)
- Ver `docs/SECURITY_ENV.md` para detalhes de segurança do `.env`





#=====================================================
suricato@mamute:~/projects/sistema-cameras-v1.0$ 
Instalar nates de inicia o docker

sudo snap install docker        
sudo apt  install docker.io      
sudo apt  install podman-docker 

O Podman não está rodando. Execute:
systemctl --user start podman.socket

Executar

docker compose -f docker-compose.nginx.yml up --build