#!/bin/bash
# Wildcard SSL для gg-hub.ru и *.gg-hub.ru с автообновлением через Cloudflare DNS.
# Требуется: домен gg-hub.ru в Cloudflare и API-токен.
#
# 1. Создайте certbot/cloudflare.ini из certbot/cloudflare.ini.example,
#    подставьте dns_cloudflare_api_token из Cloudflare.
# 2. Запуск: ./init-ssl-wildcard-cloudflare.sh
# 3. После этого certbot renew в контейнере будет продлевать сертификат автоматически.

set -e

CERT_EMAIL="${CERT_EMAIL:-ksv180384@yandex.ru}"
CERT_NAME="gg-hub.ru"
CREDENTIALS_FILE="certbot/cloudflare.ini"

if [ ! -f "$CREDENTIALS_FILE" ]; then
  echo "Файл $CREDENTIALS_FILE не найден."
  echo "Скопируйте certbot/cloudflare.ini.example в certbot/cloudflare.ini и укажите API-токен Cloudflare."
  exit 1
fi

echo "### Wildcard-сертификат через Cloudflare DNS (gg-hub.ru, *.gg-hub.ru)"
echo "### Первый выпуск или замена существующего сертификата на автообновляемый."
echo ""

docker compose run --rm --entrypoint "" certbot certbot certonly \
  --dns-cloudflare \
  --dns-cloudflare-credentials /opt/cloudflare.ini \
  --email "$CERT_EMAIL" \
  --agree-tos \
  --no-eff-email \
  --force-renewal \
  -d "gg-hub.ru" \
  -d "*.gg-hub.ru" \
  --cert-name "$CERT_NAME"

echo ""
echo "### Сертификат получен. Дальнейшее продление — автоматически (certbot renew в контейнере)."
echo "### Перезагрузите nginx: docker compose exec gg-nginx nginx -s reload"
echo ""
