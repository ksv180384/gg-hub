#!/bin/bash
# Первичная настройка SSL для gg-hub.ru и поддоменов (HTTP-01, webroot).
# Запуск из корня проекта: ./init-ssl-webroot.sh
#
# Если nginx не стартует с prod.conf (нет сертификата), в .env укажите:
#   NGINX_CONFIG=prod-bootstrap.conf
# затем запустите этот скрипт. После получения сертификата переключите на prod.conf.
#
# Переменные: CERT_EMAIL, CERT_DOMAINS (пробел-разделённый список).

set -e

CERT_EMAIL="${CERT_EMAIL:-ksv180384@yandex.ru}"
CERT_DOMAINS="${CERT_DOMAINS:-gg-hub.ru www.gg-hub.ru}"
DATA_PATH="./certbot"
CERT_NAME="gg-hub.ru"

# Какой конфиг nginx используется (из .env)
NGINX_CONFIG="default.conf"
[ -f .env ] && NGINX_CONFIG=$(grep '^NGINX_CONFIG=' .env | cut -d= -f2-) || true
[ -z "$NGINX_CONFIG" ] && NGINX_CONFIG="default.conf"
USE_BOOTSTRAP=false
[ "$NGINX_CONFIG" = "prod-bootstrap.conf" ] && USE_BOOTSTRAP=true

echo "### Проверка конфигов SSL для nginx..."
mkdir -p "$DATA_PATH/conf"
if [ ! -e "$DATA_PATH/conf/options-ssl-nginx.conf" ] || [ ! -e "$DATA_PATH/conf/ssl-dhparams.pem" ]; then
  echo "Загрузка options-ssl-nginx.conf и ssl-dhparams.pem..."
  curl -sSfL "https://raw.githubusercontent.com/certbot/certbot/master/certbot-nginx/certbot_nginx/_internal/tls_configs/options-ssl-nginx.conf" -o "$DATA_PATH/conf/options-ssl-nginx.conf"
  curl -sSfL "https://raw.githubusercontent.com/certbot/certbot/master/certbot/ssl-dhparams.pem" -o "$DATA_PATH/conf/ssl-dhparams.pem"
fi

CREATED_DUMMY=false
if [ "$USE_BOOTSTRAP" = false ] && [ ! -e "$DATA_PATH/conf/live/$CERT_NAME/fullchain.pem" ]; then
  echo "### Временный сертификат для старта nginx (конфиг: $NGINX_CONFIG)..."
  mkdir -p "$DATA_PATH/conf/live/$CERT_NAME" "$DATA_PATH/conf/archive/$CERT_NAME"
  docker compose run --rm --entrypoint "\
    sh -c 'openssl req -x509 -nodes -newkey rsa:4096 -days 1 \
      -keyout /etc/letsencrypt/live/$CERT_NAME/privkey.pem \
      -out /etc/letsencrypt/live/$CERT_NAME/fullchain.pem \
      -subj /CN=localhost'" certbot
  CREATED_DUMMY=true
fi

echo "### Запуск nginx..."
docker compose up -d gg-nginx

echo "### Ожидание nginx..."
sleep 5

if [ "$CREATED_DUMMY" = true ]; then
  echo "### Удаление временного сертификата..."
  docker compose run --rm --entrypoint "\
    rm -f /etc/letsencrypt/live/$CERT_NAME/privkey.pem /etc/letsencrypt/live/$CERT_NAME/fullchain.pem" certbot
fi

echo "### Запрос сертификата Let's Encrypt (webroot)..."
docker compose run --rm --entrypoint "" \
  -e "CERT_EMAIL=$CERT_EMAIL" \
  -e "CERT_DOMAINS=$CERT_DOMAINS" \
  -e "CERT_NAME=$CERT_NAME" \
  certbot sh /scripts/init-cert-webroot.sh

if [ "$USE_BOOTSTRAP" = true ]; then
  echo ""
  echo "### Сертификат получен. Включите HTTPS:"
  echo "  1. В .env укажите: NGINX_CONFIG=prod.conf"
  echo "  2. Выполните: docker compose up -d --force-recreate gg-nginx"
  echo ""
else
  echo "### Перезагрузка nginx..."
  docker compose exec gg-nginx nginx -s reload
fi

echo "### Готово. Сертификат для $CERT_DOMAINS установлен, автообновление каждые 12 ч."
