#!/bin/sh
# ============================================
# Entrypoint do container app (Nginx + PHP-FPM).
# Roda como www-data (uid 33) — nao escala privilegio.
#
# 1. Garante certificados TLS em /etc/nginx/certs/:
#    - usa o montado/CA interna se existir (server.crt/server.key);
#    - caso contrario gera um self-signed (fallback para intranet).
# 2. Renderiza o bloco server em /run/nginx_conf/server.conf:
#    - HTTPS na 8443 (ssl) incluindo docker/locations.conf;
#    - HTTP na 8080 com redirect 301 -> HTTPS.
# 3. Sobe Nginx + PHP-FPM.
# ============================================
set -e

CONF_DIR="${CONF_DIR:-/run/nginx_conf}"
CERT_DIR="/etc/nginx/certs"
CERT_FILE="$CERT_DIR/server.crt"
KEY_FILE="$CERT_DIR/server.key"
LOCATIONS_CONF="/etc/nginx/locations.conf"

mkdir -p "$CONF_DIR" "$CERT_DIR"

if [ ! -s "$CERT_FILE" ] || [ ! -s "$KEY_FILE" ]; then
    echo "[entrypoint] Nenhum certificado em $CERT_DIR — gerando self-signed (substitua por CA interna se necessario)."
    openssl req -x509 -nodes -newkey rsa:2048 -days 825 \
        -keyout "$KEY_FILE" -out "$CERT_FILE" \
        -subj "/C=BR/O=Sistema Cameras e Alarmes/CN=sistema-cameras" \
        >/dev/null 2>&1
    chmod 600 "$KEY_FILE"
    chmod 644 "$CERT_FILE"
fi

# O nginx monta $https automaticamente ("on" em conexoes TLS);
# portanto cookie Secure + HSTS da aplicacao funcionam no HTTPS.
cat > "$CONF_DIR/server.conf" <<EOF
server {
    listen 8080 default_server;
    server_name _;
    return 301 https://\$host\$request_uri;
}

server {
    listen 8443 ssl default_server;
    server_name _;

    ssl_certificate     $CERT_FILE;
    ssl_certificate_key $KEY_FILE;
    ssl_protocols       TLSv1.2 TLSv1.3;
    ssl_session_cache   shared:SSL:10m;
    ssl_session_timeout 10m;
    ssl_prefer_server_ciphers off;

    include $LOCATIONS_CONF;
}
EOF

echo "[entrypoint] Config de servidor gerada em $CONF_DIR/server.conf"

nginx -g 'daemon off;' &
exec php-fpm --nodaemonize