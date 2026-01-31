#!/bin/bash
# Wildcard SSL для gg-hub.ru и *.gg-hub.ru (DNS-01 challenge).
# Нужен доступ к DNS: при запросе certbot попросит добавить TXT-запись.
#
# Запуск из корня проекта: ./init-ssl-wildcard.sh
# Переменные: CERT_EMAIL (по умолчанию из .env или ksv180384@yandex.ru)

set -e

CERT_EMAIL="${CERT_EMAIL:-ksv180384@yandex.ru}"
CERT_NAME="gg-hub.ru"

echo "### Wildcard-сертификат для gg-hub.ru и *.gg-hub.ru (DNS-01)"
echo "### Certbot попросит добавить TXT-запись в DNS. Подготовьте панель управления DNS для домена gg-hub.ru."
echo ""

# Интерактивный режим (-it) нужен: certbot попросит ввести TXT и нажать Enter
docker compose run --rm -it --entrypoint "" certbot certbot certonly \
  --manual \
  --preferred-challenges=dns \
  --email "$CERT_EMAIL" \
  --agree-tos \
  --no-eff-email \
  -d "gg-hub.ru" \
  -d "*.gg-hub.ru" \
  --cert-name "$CERT_NAME"

echo ""
echo "### Сертификат получен. Перезагрузите nginx:"
echo "  docker compose exec gg-nginx nginx -s reload"
echo ""
echo "### Важно: продление wildcard тоже требует DNS-01."
echo "  Вариант 1: каждые ~80 дней запускать снова: ./init-ssl-wildcard.sh"
echo "  Вариант 2: настроить DNS-плагин (Cloudflare, Yandex и т.д.) для автообновления."
echo ""
echo "--- Как это работает ---"
echo "1. Certbot выведет имя (_acme-challenge.gg-hub.ru) и значение (длинная строка)."
echo "2. В панели DNS для домена gg-hub.ru создайте запись:"
echo "   Тип: TXT"
echo "   Имя/хост: _acme-challenge  (если панель сама дописывает домен) ИЛИ _acme-challenge.gg-hub.ru"
echo "   Значение: строка из certbot (без кавычек)"
echo "3. Подождите 2–10 минут. Проверьте с другого сервера/компьютера:"
echo "   dig TXT _acme-challenge.gg-hub.ru +short"
echo "   Должно вывести значение записи. Только после этого нажимайте Enter в certbot."
echo "4. Если NXDOMAIN — запись не создана или имя указано неверно (должно быть именно _acme-challenge.gg-hub.ru)."
