#!/bin/bash
# Проверка, какие домены в текущем сертификате gg-hub.ru
CERT="${1:-./certbot/conf/live/gg-hub.ru/fullchain.pem}"
if [ ! -f "$CERT" ]; then
  echo "Файл не найден: $CERT"
  exit 1
fi
echo "Домены в сертификате:"
openssl x509 -in "$CERT" -noout -text | grep -A1 "Subject Alternative Name" | tail -1 | tr ',' '\n' | sed 's/.*DNS:/  - /'
