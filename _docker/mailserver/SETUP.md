# Настройка почтового сервера (docker-mailserver + Roundcube)

## 1. DNS-записи (Cloudflare)

Все записи для `mail778.gg-hub.ru` **должны** иметь выключенный proxy (серая облачка, DNS only).

### Обязательные записи

| Тип | Имя | Значение | Proxy | Примечание |
|-----|-----|----------|-------|------------|
| A | `mail778.gg-hub.ru` | `<IP сервера>` | OFF | Адрес почтового сервера |
| MX | `gg-hub.ru` | `mail778.gg-hub.ru` | — | Приоритет: 10 |
| TXT | `gg-hub.ru` | `v=spf1 a mx ip4:<IP> ~all` | — | SPF — разрешённые отправители |
| TXT | `_dmarc.gg-hub.ru` | `v=DMARC1; p=quarantine; rua=mailto:postmaster@gg-hub.ru` | — | DMARC — политика |

### DKIM (после первого запуска)

DKIM-ключ генерируется автоматически при первом запуске контейнера.
Получить DNS-запись:

```bash
docker exec gg-mailserver setup config dkim
docker exec gg-mailserver cat /tmp/docker-mailserver/opendkim/keys/gg-hub.ru/mail.txt
```

Скопировать содержимое в TXT-запись:
| Тип | Имя | Значение |
|-----|-----|----------|
| TXT | `mail._domainkey.gg-hub.ru` | `v=DKIM1; h=sha256; k=rsa; p=<ключ из команды выше>` |

### PTR (обратная DNS-запись)

Настраивается у хостинг-провайдера (не в Cloudflare):
- IP сервера → `mail778.gg-hub.ru`

Без PTR письма будут попадать в спам.

---

## 2. SSL-сертификат

Если сертификат ещё не покрывает `mail778.gg-hub.ru`, перевыпустите:

```bash
# Вариант A: Webroot (HTTP-01) — добавить mail778.gg-hub.ru
docker compose run --rm certbot sh /scripts/init-cert-webroot.sh

# Вариант B: Wildcard через Cloudflare DNS
docker compose run --rm certbot certbot certonly \
  --dns-cloudflare \
  --dns-cloudflare-credentials /opt/cloudflare.ini \
  --cert-name gg-hub.ru \
  -d "gg-hub.ru" -d "*.gg-hub.ru" \
  --agree-tos --email ksv180384@yandex.ru
```

---

## 3. Запуск

```bash
# Поднять все сервисы (без phpMyAdmin)
docker compose up -d

# Если нужен phpMyAdmin (только dev):
docker compose --profile dev up -d phpmyadmin
```

---

## 4. Создание почтового ящика

```bash
# Создать ящик (пароль будет запрошен интерактивно)
docker exec -ti gg-mailserver setup email add user@gg-hub.ru

# Или неинтерактивно:
docker exec gg-mailserver setup email add user@gg-hub.ru 'password123'

# Создать ящик noreply для Laravel
docker exec -ti gg-mailserver setup email add noreply@gg-hub.ru

# Список ящиков
docker exec gg-mailserver setup email list
```

---

## 5. Настройка Laravel (.env на production)

```
MAIL_MAILER=smtp
MAIL_HOST=mailserver
MAIL_PORT=587
MAIL_USERNAME=noreply@gg-hub.ru
MAIL_PASSWORD=<пароль из шага 4>
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS=noreply@gg-hub.ru
MAIL_FROM_NAME="${APP_NAME}"
```

---

## 6. Веб-почта (Roundcube)

Доступ: `https://mail778.gg-hub.ru`

Логин: полный email (например `user@gg-hub.ru`)
Пароль: пароль ящика из шага 4

---

## 7. Проверка работоспособности

```bash
# Статус контейнера
docker exec gg-mailserver setup debug show-mail-logs

# Тест SMTP
docker exec gg-mailserver swaks --to test@example.com --from noreply@gg-hub.ru --server localhost

# Проверка DNS (внешне)
# https://mxtoolbox.com — ввести gg-hub.ru
# https://www.mail-tester.com — отправить тестовое письмо и получить оценку
```

---

## 8. Swap (рекомендуется для 2GB RAM)

```bash
sudo fallocate -l 2G /swapfile
sudo chmod 600 /swapfile
sudo mkswap /swapfile
sudo swapon /swapfile
echo '/swapfile none swap sw 0 0' | sudo tee -a /etc/fstab
```

---

## 9. Проверка порта 25

Некоторые хостеры блокируют исходящий порт 25. Проверить:

```bash
telnet smtp.gmail.com 25
# или
nc -zv smtp.gmail.com 25
```

Если порт заблокирован — обратитесь к хостеру для разблокировки или используйте внешний SMTP-relay (Brevo, Mailgun, Amazon SES).
