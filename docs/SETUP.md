# Setup - Sistema de Câmeras

## Arquitetura de Configuração

```
DESENVOLVIMENTO (Windows + XAMPP)
└── C:\env\sistema-cameras\
    └── .env

PRODUÇÃO (Linux)
└── /etc/sistema-cameras/
    └── .env
```

## Passo a Passo

### 1. Criar pasta de configuração

**Windows (desenvolvimento):**
```powershell
New-Item -ItemType Directory -Path "C:\env\sistema-cameras" -Force
```

**Linux (produção):**
```bash
sudo mkdir -p /etc/sistema-cameras
sudo chown www-data:www-data /etc/sistema-cameras
sudo chmod 700 /etc/sistema-cameras
```

### 2. Copiar arquivo de exemplo

Copie `.env.example` da raiz do projeto para o local correto:

**Windows:**
```powershell
Copy-Item ".env.example" "C:\env\sistema-cameras\.env"
```

**Linux:**
```bash
sudo cp .env.example /etc/sistema-cameras/.env
sudo chown www-data:www-data /etc/sistema-cameras/.env
```

### 3. Editar com dados reais

Abra o arquivo e preencha com suas credenciais:

```env
CAMERAS_ENV=development  # ou production
DB_HOST=localhost
DB_NAME=cftv_gml
DB_USER=seu_usuario
DB_PASS=sua_senha_forte
APP_TIMEZONE=America/Sao_Paulo
CAMERAS_SESSION_TIMEOUT=3600
CAMERAS_SESSION_ABSOLUTE_TIMEOUT=28800
CAMERAS_CSP_ALLOW_INLINE_STYLES=0
```

### 4. Windows: Configurar XAMPP

Edite `C:\xampp\php\php.ini`:
```ini
always_populate_raw_post_data = -1
error_reporting = E_ALL
display_errors = On
```

### 5. Verificar

Acesse a aplicação. Se tudo estiver correto, o sistema carregará as configurações da pasta externa.

## Solução de Problemas

### "Arquivo de configuração não encontrado"

1. Verifique se a pasta existe:
   - Windows: `C:\env\sistema-cameras\.env`
   - Linux: `/etc/sistema-cameras/.env`

2. Verifique se o arquivo é legível:
   - Windows: Sem restrições de NTFS
   - Linux: `chmod 644 /etc/sistema-cameras/.env`

### "Configurações ausentes: DB_PASS"

O arquivo existe mas não contém `DB_PASS`. Adicione a linha ao arquivo.

## Segurança

- **Nunca** commite arquivos `.env`
- **Nunca** coloque credenciais no código
- Use senhas fortes (mínimo 32 caracteres)
- Em produção, defina `CAMERAS_ENV=production`
- Mantenha a pasta de config com permissões restritivas