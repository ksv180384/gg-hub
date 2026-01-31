#!/bin/bash
# Первичная настройка SSL для gg-hub.ru и поддоменов (HTTP-01, webroot).
# Запуск из корня проекта: ./init-ssl-webroot.sh
# Переменные: CERT_EMAIL, CERT_DOMAINS (пробел-разделённый список, по умолчанию gg-hub.ru www.gg-hub.ru)

set -e

CERT_EMAIL="${CERT_EMAIL:-ksv180384@yandex.ru}"
CERT_DOMAINS="${CERT_DOMAINS:-gg-hub.ru www.gg-hub.ru}"
DATA_PATH="./certbot"
RSA_KEY_SIZE=4096
CERT_NAME="gg-hub.ru"

echo "### Проверка конфигов SSL для nginx..."
mkdir -p "$DATA_PATH/conf"
if [ ! -e "$DATA_PATH/conf/options-ssl-nginx.conf" ] || [ ! -e "$DATA_PATH/conf/ssl-dhparams.pem" ]; then
  echo "Загрузка options-ssl-nginx.conf и ssl-dhparams.pem..."
  curl -sSfL "https://raw.githubusercontent.com/certbot/certbot/master/certbot-nginx/certbot_nginx/_internal/tls_configs/options-ssl-nginx.conf" -o "$DATA_PATH/conf/options-ssl-nginx.conf"
  curl -sSfL "https://raw.githubusercontent.com/certbot/certbot/master/certbot/ssl-dhparams.pem" -o "$DATA_PATH/conf/ssl-dhparams.pem"
fi

echo "### Временный сертификат для старта nginx..."
mkdir -p "$DATA_PATH/conf/live/$CERT_NAME" "$DATA_PATH/conf/archive/$CERT_NAME"
docker compose run --rm --entrypoint "\
  sh -c 'openssl req -x509 -nodes -newkey rsa:4096 -days 1 \
    -keyout /etc/letsencrypt/live/$CERT_NAME/privkey.pem \
    -out /etc/letsencrypt/live/$CERT_NAME/fullchain.pem \
    -subj /CN=localhost'" certbot

echo "### Запуск nginx..."
docker compose up -d gg-nginx

echo "### Ожидание nginx..."
sleep 5

echo "### Удаление временного сертификата..."
docker compose run --rm --entrypoint "\
  rm -f /etc/letsencrypt/live/$CERT_NAME/privkey.pem /etc/letsencrypt/live/$CERT_NAME/fullchain.pem" certbot

echo "### Запрос сертификата Let's Encrypt (webroot)..."
docker compose run --rm --entrypoint "" \
  -e "CERT_EMAIL=$CERT_EMAIL" \
  -e "CERT_DOMAINS=$CERT_DOMAINS" \
  -e "CERT_NAME=$CERT_NAME" \
  certbot sh /scripts/init-cert-webroot.sh

echo "### Перезагрузка nginx..."
docker compose exec gg-nginx nginx -s reload

echo "### Готово. Сертификат для $CERT_DOMAINS установлен, автообновление каждые 12 ч."
