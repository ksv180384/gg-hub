# Образ для автоматического обновления сертификатов с перезагрузкой nginx
FROM certbot/certbot:latest

RUN apk add --no-cache docker-cli \
    && pip install --no-cache-dir certbot-dns-cloudflare

COPY _docker/certbot/init-cert-webroot.sh /scripts/init-cert-webroot.sh
RUN chmod +x /scripts/init-cert-webroot.sh

# После успешного обновления сертификата перезагружаем nginx
ENV DEPLOY_HOOK="docker exec gg-nginx-container nginx -s reload"
