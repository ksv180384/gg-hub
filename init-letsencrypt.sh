#!/bin/bash

domains=(gg-hub.ru *.gg-hub.ru)
email="ksv180384@yandex.ru" # Замените на ваш email
staging=1 # Установите 1 для тестирования

data_path="./certbot"
rsa_key_size=4096

if [ -d "$data_path" ]; then
  read -p "Существующие данные сертификата найдены. Продолжить и перезаписать существующие сертификаты? (y/N) " decision
  if [ "$decision" != "Y" ] && [ "$decision" != "y" ]; then
    exit
  fi
fi

if [ ! -e "$data_path/conf/options-ssl-nginx.conf" ] || [ ! -e "$data_path/conf/ssl-dhparams.pem" ]; then
  echo "### Загрузка рекомендованной конфигурации SSL ..."
  mkdir -p "$data_path/conf"
  curl -s https://raw.githubusercontent.com/certbot/certbot/master/certbot-nginx/certbot_nginx/_internal/tls_configs/options-ssl-nginx.conf > "$data_path/conf/options-ssl-nginx.conf"
  curl -s https://raw.githubusercontent.com/certbot/certbot/master/certbot/certbot/ssl-dhparams.pem > "$data_path/conf/ssl-dhparams.pem"
fi

echo "### Создание временного сертификата для первого запуска..."
for domain in "${domains[@]}"; do
  path="/etc/letsencrypt/live/$domain"
  mkdir -p "$data_path/conf/live/$domain"
  docker-compose run --rm --entrypoint "\
    openssl req -x509 -nodes -newkey rsa:$rsa_key_size -days 1\
      -keyout '$path/privkey.pem' \
      -out '$path/fullchain.pem' \
      -subj '/CN=localhost'" certbot
done

echo "### Запуск nginx..."
docker-compose up --force-recreate -d gg-nginx

echo "### Удаление временного сертификата..."
for domain in "${domains[@]}"; do
  docker-compose run --rm --entrypoint "\
    rm -Rf /etc/letsencrypt/live/$domain && \
    rm -Rf /etc/letsencrypt/archive/$domain && \
    rm -Rf /etc/letsencrypt/renewal/$domain.conf" certbot
done

echo "### Запрос Let's Encrypt сертификата..."
docker-compose run --rm --entrypoint "\
  certbot certonly --manual --preferred-challenges=dns \
    --email $email \
    --server https://acme-v02.api.letsencrypt.org/directory \
    --agree-tos \
    --manual-public-ip-logging-ok \
    --keep-until-expiring \
    --manual-auth-hook /auth-hook.sh \
    --manual-cleanup-hook /cleanup-hook.sh \
    ${staging:+--staging} \
    ${domains[@]/#/-d }" certbot

echo "### Перезапуск nginx..."
docker-compose exec gg-nginx nginx -s reload
