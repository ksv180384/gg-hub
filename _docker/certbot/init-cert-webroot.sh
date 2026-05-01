#!/bin/sh
# Первичное получение / расширение SSL для gg-hub.ru и поддоменов (HTTP-01, webroot).
# Запуск: docker compose run --rm certbot sh /scripts/init-cert-webroot.sh
# Или задайте переменные: CERT_EMAIL, CERT_DOMAINS (пробел-разделённый список), CERT_NAME

set -e

CERT_EMAIL="${CERT_EMAIL:-ksv180384@yandex.ru}"
# Домен и поддомены через пробел (без wildcard — для wildcard нужен DNS-01)
CERT_DOMAINS="${CERT_DOMAINS:-gg-hub.ru www.gg-hub.ru mail778.gg-hub.ru admin.gg-hub.ru aion2.gg-hub.ru tl.gg-hub.ru}"
CERT_NAME="${CERT_NAME:-gg-hub.ru}"

D_ARGS=""
for d in $CERT_DOMAINS; do
  D_ARGS="$D_ARGS -d $d"
done

# --non-interactive: не спрашивать выбор аккаунта (если их несколько — удалите лишние через `certbot unregister`)
# --expand: позволяет добавлять новые домены к существующему cert-name без force-renewal
certbot certonly --webroot \
  -w /var/www/certbot \
  --email "$CERT_EMAIL" \
  --cert-name "$CERT_NAME" \
  --agree-tos \
  --no-eff-email \
  --non-interactive \
  --expand \
  --key-type rsa \
  $D_ARGS

echo "Сертификат получен/обновлён. Перезагрузите nginx: docker exec gg-nginx-container nginx -s reload"
