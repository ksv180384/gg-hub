#!/bin/sh
# Первичное получение SSL для gg-hub.ru и поддоменов (HTTP-01, webroot).
# Запуск: docker compose run --rm certbot sh /etc/letsencrypt/init-cert-webroot.sh
# Или задайте переменные: CERT_EMAIL, CERT_DOMAINS (пробел-разделённый список)

set -e

CERT_EMAIL="${CERT_EMAIL:-ksv180384@yandex.ru}"
# Домен и поддомены через пробел (без wildcard — для wildcard нужен DNS-01)
CERT_DOMAINS="${CERT_DOMAINS:-gg-hub.ru www.gg-hub.ru}"
CERT_NAME="${CERT_NAME:-gg-hub.ru}"

# Собираем флаги -d для каждого домена
D_ARGS=""
for d in $CERT_DOMAINS; do
  D_ARGS="$D_ARGS -d $d"
done

certbot certonly --webroot \
  -w /var/www/certbot \
  --email "$CERT_EMAIL" \
  --cert-name "$CERT_NAME" \
  --agree-tos \
  --no-eff-email \
  --key-type rsa \
  $D_ARGS

echo "Сертификат получен. Перезагрузите nginx: docker exec gg-nginx-container nginx -s reload"
