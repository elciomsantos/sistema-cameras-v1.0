#!/bin/bash

set -e

PROJECT_DIR="$(pwd)"

echo "🚀 Iniciando setup do sistema de câmeras..."

cd "$PROJECT_DIR"

# 1. Composer (PHP)
echo "📦 Instalando dependências PHP (Composer)..."
if ! command -v composer &> /dev/null; then
    echo "❌ Composer não encontrado. Instale com: sudo apt install composer -y"
    exit 1
fi

composer install --no-interaction --prefer-dist

# 2. Node (frontend)
echo "📦 Instalando dependências Node..."
if ! command -v npm &> /dev/null; then
    echo "❌ npm não encontrado. Instale Node.js primeiro."
    exit 1
fi

npm install

echo "⚡ Gerando build do frontend (Vite)..."
npm run build

# 3. Permissões (storage e cache)
echo "🔐 Ajustando permissões..."

if [ -d "storage" ]; then
    sudo chown -R www-data:www-data storage || true
    sudo chmod -R 775 storage || true
fi

# opcional dev local (descomente se quiser)
# sudo chown -R $USER:$USER storage

# 4. Limpeza básica
echo "🧹 Limpando cache opcional..."
rm -rf storage/logs/*.log 2>/dev/null || true

# 5. Restart Apache
echo "🔄 Reiniciando Apache..."
sudo systemctl restart apache2

echo "✅ Setup concluído com sucesso!"
echo "🌐 Acesse: http://sistema-cameras.local"


chmod +x setup.sh

./setup.sh
