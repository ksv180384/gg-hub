# 10. Тестирование

## Фреймворк

**Pest 3** поверх PHPUnit 11.

Конфигурация: `backend/tests/Pest.php`, `phpunit.xml`.

## Структура

```
backend/tests/
├── Pest.php           # Глобальные настройки, хелперы
├── TestCase.php
├── Feature/           # HTTP, интеграция, сценарии API
└── Unit/              # Изолированная логика (Actions, расчёты)
```

## Запуск

```bash
cd backend

# Все тесты
php artisan test --compact

# Файл
php artisan test --compact tests/Feature/GuildBankAndDkpTest.php

# Фильтр по имени
php artisan test --compact --filter=AdjustGuildUserDkp

# В Docker
docker compose exec gg-php_8.4 php artisan test --compact
```

## Создание тестов

```bash
php artisan make:test --pest MyFeatureTest
php artisan make:test --pest MyUnitTest --unit
```

Предпочтительно **Feature**-тесты для API; **Unit** — для чистых функций (например расчёт DKP).

## Примеры покрытых областей

| Тест / группа | Область |
|---------------|---------|
| `GuildBankAndDkpTest` | Банк, выдача, DKP, минус с подтверждением |
| `GuildDkpLedgerTest` | Журнал, фильтры, синхронизация с событиями |
| `EventHistoryTitlesTest` | Виды событий |
| `RegistrationUserNameTest`, `UpdateProfileUserNameTest` | Имя пользователя |
| `Domains/GuildDkp/*Test` | Unit: расчёт очков, фильтры журнала |

## Соглашения

- Используй **фабрики** моделей вместо ручного `Model::create` где возможно
- Для API: `actingAs($user)` + JSON-запросы
- Проверяй статусы и структуру `JsonResource`, не сырые модели в ответе
- Сообщения валидации — на русском (проверять ключи `errors`)

## Форматирование перед коммитом

```bash
vendor/bin/pint --dirty --format agent
```

## CI

Автоматический прогон в репозитории **не настроен**. Рекомендуется локально:

```bash
php artisan test --compact
vendor/bin/pint --dirty
```

## Связанные документы

- [04-backend.md](04-backend.md)
- [guild-bank-and-dkp.md](guild-bank-and-dkp.md#тесты)
