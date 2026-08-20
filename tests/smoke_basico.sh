#!/bin/bash
set -e

BASE_URL="${1:-http://localhost/sistema-cameras-v1.0/public}"

echo "=== Smoke Básico do Sistema ==="

# 1. Página inicial
home_url="${BASE_URL}/"
code=$(curl -s -o /dev/null -w "%{http_code}" -m 10 "$home_url")
if [ "$code" = "200" ]; then
    echo "OK: Página inicial carrega (200)"
else
    echo "FAIL: Página inicial (esperado=200, atual=$code)"
    exit 1
fi

# 2. API health sem auth
health_url="${BASE_URL}/index.php?page=api/api_health"
code=$(curl -s -o /dev/null -w "%{http_code}" -m 10 "$health_url")
if [ "$code" = "401" ]; then
    echo "OK: API health sem autenticação (401)"
else
    echo "FAIL: API health (esperado=401, atual=$code)"
    exit 1
fi

# 3. Página login
login_url="${BASE_URL}/index.php?page=login"
code=$(curl -s -o /dev/null -w "%{http_code}" -m 10 "$login_url")
if [ "$code" = "200" ]; then
    echo "OK: Página login carrega (200)"
else
    echo "FAIL: Página login (esperado=200, atual=$code)"
    exit 1
fi

# 4. API cameras sem auth
cams_url="${BASE_URL}/index.php?page=api/api_cameras"
code=$(curl -s -o /dev/null -w "%{http_code}" -m 10 "$cams_url")
if [ "$code" = "401" ]; then
    echo "OK: API cameras sem autenticação (401)"
else
    echo "FAIL: API cameras (esperado=401, atual=$code)"
    exit 1
fi

echo "=== Smoke Básico concluido ==="
