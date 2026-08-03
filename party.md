# Добавить конст пати

> Надо составить план добавления конст пати.
> В меню (в на аватаре пользователя), добавить пункт Мои КП (при наведении мыши Мои конст пати).
> Пользователь создает конст пати делая лидером своего персонажа
> У конст пати должно быть название
> В конст пати можно пригласить персонажа (надо проработать систему приглашения персонажей с оповещениями, приглашать персонажа можно только если персонаж находится на том же сервере что и лидер, при переходе персонажа на другой сервер, он исключается из конст пати)
> У конст пати должен быть свой чат
> Должно быть свое хранилище с историей выдачи предметов
> участникам группы можно выдавать права на управление хранилищем.

<details><summary>6 previous messages</summary>

> Понял задачу: нужен не код сходу, а продуманный план фичи “конст пати” с UX, данными, правилами и интеграциями. Я быстро посмотрю структуру проекта, чтобы план лег на реальные модули, а не висел в воздухе.
>
> Вижу Laravel backend, Vue frontend, отдельный socket server, уже есть уведомления, гильдейский банк/DKP и realtime-события. Сейчас соберу план вокруг этих существующих паттернов: так будет меньше новой инфраструктуры и меньше риска разъезда с текущей архитектурой.
>
> Документация местами отдается в кривой кодировке через PowerShell, но структура считывается: банк гильдии уже почти готовый донор архитектуры для хранилища КП, а уведомления и Socket.IO дадут приглашения/чат без изобретения отдельного канала доставки. Проверю конкретные модели/роуты персонажей и банка, чтобы в плане указать точные точки расширения.
>
> Нашел важную деталь: персонаж уже жестко привязан к `server_id`, а гильдейский банк сделан как отдельный домен с actions/models/resources. Для КП логично сделать отдельный домен, но часть механик хранилища повторить по образцу `GuildBank`, не тащить внутрь гильдий.
>
> На фронте пункт меню добавляется прямо в `Header.vue`, рядом с “Мои персонажи”; роуты уже имеют protected-разделы типа `/my-posts`, так что `/my-constant-parties` хорошо ложится туда. Осталось сформулировать план по слоям и отдельно зафиксировать спорные решения: членство по персонажу, права по участнику, исключение при смене сервера.
>
> Оповещения в API уже универсальные: `notifications` умеют список/прочтение/удаление и live push на пользователя. Для приглашений в КП достаточно добавить типизированные уведомления с action-ссылками, а сами accept/decline держать в таблице приглашений, чтобы не превращать уведомление в источник истины.
</details>

Ниже план добавления “конст пати” с опорой на текущую архитектуру проекта.

**Цель**
Добавить пользовательские конст пати как отдельную сущность, не привязанную к гильдии, но привязанную к игре/локализации/серверу через персонажа-лидера. Управление доступно из меню пользователя в аватаре: пункт `Мои КП`, tooltip/title при наведении: `Мои конст пати`.

**1. Навигация и UX**
1. В [Header.vue](./frontend/src/widgets/header/Header.vue) добавить пункт в dropdown пользователя:
   `Мои КП` -> `/my-constant-parties`.
2. Добавить роуты во [frontend router](./frontend/src/router/index.ts):
    - `/my-constant-parties` список КП пользователя;
    - `/my-constant-parties/create` создание;
    - `/constant-parties/:id` страница КП;
    - `/constant-parties/:id/storage` хранилище;
    - `/constant-parties/:id/chat` можно как вкладку на общей странице, не обязательно отдельный URL.
3. На странице “Мои КП”:
    - список КП, где персонажи пользователя состоят участниками;
    - кнопка создания;
    - бейдж роли: лидер, участник, может управлять хранилищем;
    - входящие приглашения можно показывать отдельным блоком, плюс они будут в уведомлениях.

**2. Модель данных**
Добавить домен backend, например `backend/Domains/ConstantParty`.

Основные таблицы:

```text
constant_parties
- id
- leader_character_id
- game_id
- localization_id
- server_id
- name
- created_by_user_id
- created_at
- updated_at
- deleted_at nullable
```

```text
constant_party_members
- id
- constant_party_id
- character_id
- role enum: leader/member
- can_manage_storage boolean
- joined_at
- created_at
- updated_at
```

```text
constant_party_invitations
- id
- constant_party_id
- invited_character_id
- invited_by_character_id
- status enum: pending/accepted/declined/revoked/expired
- message nullable
- responded_at nullable
- expires_at nullable
- created_at
- updated_at
```

```text
constant_party_chat_messages
- id
- constant_party_id
- character_id
- body
- created_at
- updated_at
- deleted_at nullable
```

```text
constant_party_storage_item_tiers
- id
- constant_party_id
- name
- color
- sort_order
- created_at
- updated_at
```

```text
constant_party_storage_items
- id
- constant_party_id
- tier_id nullable
- name
- description nullable
- quantity nullable
- created_by_character_id
- updated_by_character_id nullable
- created_at
- updated_at
```

```text
constant_party_storage_item_grants
- id
- constant_party_id
- item_id
- received_by_character_id
- granted_by_character_id
- reason nullable
- granted_at
- created_at
- updated_at
```

Важно: членство лучше вести именно по `character_id`, а не по `user_id`, потому что правила серверов и лидерства завязаны на персонажа.

**3. Создание КП**
Правила:
1. Авторизованный пользователь выбирает одного из своих персонажей.
2. Этот персонаж становится лидером.
3. Название КП обязательно.
4. `game_id`, `localization_id`, `server_id` копируются с персонажа-лидера.
5. Лидер автоматически добавляется в `constant_party_members` с:
    - `role = leader`;
    - `can_manage_storage = true`.
6. Один персонаж может состоять только в одной активной КП в рамках одного сервера, если не нужен сценарий “персонаж в нескольких КП”. Это лучше явно зафиксировать до реализации.

**4. Приглашения**
API-сценарий:
1. Лидер или участник с правом управления составом ищет персонажа.
2. Приглашать можно только если:
    - персонаж существует;
    - `server_id` совпадает с `constant_parties.server_id`;
    - персонаж не состоит в этой КП;
    - нет активного pending-приглашения;
    - персонаж не лидер другой КП, если вводим ограничение “один персонаж - одна КП”.
3. Создается `constant_party_invitations.status = pending`.
4. Владельцу приглашенного персонажа создается in-app notification через существующую систему `notifications`.
5. Уведомление ведет на страницу принятия приглашения или на `/my-constant-parties`.

Нужные действия:
- `InviteCharacterToConstantPartyAction`
- `AcceptConstantPartyInvitationAction`
- `DeclineConstantPartyInvitationAction`
- `RevokeConstantPartyInvitationAction`
- `ExpireConstantPartyInvitationsAction`, опционально через scheduled command.

**5. Исключение при переходе персонажа на другой сервер**
В [UpdateCharacterAction](./backend/Domains/Character/Actions/UpdateCharacterAction.php) или рядом с ним добавить доменное действие:

```text
RemoveCharacterFromConstantPartiesOnServerChangeAction
```

Логика:
1. До обновления сохранить старый `server_id`.
2. После обновления, если `server_id` изменился:
    - удалить персонажа из всех КП старого сервера;
    - если персонаж был лидером, выбрать правило:
        - MVP: распустить КП;
        - лучше: запретить лидеру менять сервер, пока он лидер КП, или требовать передать лидерство;
        - альтернатива: автоматически передать лидерство старшему участнику.
3. Создать уведомления остальным участникам: персонаж исключен из КП из-за смены сервера.

Я бы выбрал для MVP: **лидеру запрещаем смену сервера, пока он лидер КП**. Для обычного участника - автоматическое исключение.

**6. Чат КП**
Минимальный вариант:
1. REST:
    - `GET /api/v1/constant-parties/{party}/chat/messages`
    - `POST /api/v1/constant-parties/{party}/chat/messages`
    - `DELETE /api/v1/constant-parties/{party}/chat/messages/{message}`
2. Доступ только участникам КП.
3. Сообщения soft-delete.
4. Realtime через `socket_server`:
    - room `constant-party:{id}:chat`;
    - endpoint `/constant-parties/chat/broadcast-created`;
    - событие `constant-party-chat-message-created`.

Для MVP можно начать с REST + периодического обновления/ручной загрузки, но раз Socket.IO уже есть, лучше сразу добавить realtime.

**7. Хранилище КП**
Повторить паттерн гильдейского банка из `GuildBank`, но без DKP на первом этапе.

API:
```text
GET    /api/v1/constant-parties/{party}/storage/context
GET    /api/v1/constant-parties/{party}/storage/tiers
POST   /api/v1/constant-parties/{party}/storage/tiers
PATCH  /api/v1/constant-parties/{party}/storage/tiers/{tier}
DELETE /api/v1/constant-parties/{party}/storage/tiers/{tier}

GET    /api/v1/constant-parties/{party}/storage/items
POST   /api/v1/constant-parties/{party}/storage/items
PATCH  /api/v1/constant-parties/{party}/storage/items/{item}
DELETE /api/v1/constant-parties/{party}/storage/items/{item}

GET    /api/v1/constant-parties/{party}/storage/items/{item}/grants
POST   /api/v1/constant-parties/{party}/storage/grants
DELETE /api/v1/constant-parties/{party}/storage/grants/{grant}
```

Права:
- смотреть хранилище могут все участники КП;
- добавлять/редактировать предметы, выдавать и отменять выдачу могут:
    - лидер;
    - участники с `can_manage_storage = true`.

История выдачи хранится в `constant_party_storage_item_grants`.

**8. Backend-слой**
Добавить:
- models: `ConstantParty`, `ConstantPartyMember`, `ConstantPartyInvitation`, `ConstantPartyChatMessage`, storage models;
- controllers:
    - `ConstantPartyController`
    - `ConstantPartyInvitationController`
    - `ConstantPartyChatController`
    - `ConstantPartyStorageController`
- requests с русскими validation messages;
- resources для списков и карточек;
- middleware или policy:
    - `constant.party.member`;
    - `constant.party.leader`;
    - `constant.party.storage-manager`.

Роуты добавить в `backend/routes/api.php` внутри `auth`.

**9. Frontend-слой**
По FSD-подходу:
```text
frontend/src/features/constant-party/
frontend/src/widgets/constant-party/
frontend/src/pages/my-constant-parties/
frontend/src/pages/constant-parties/[id]/
frontend/src/shared/api/constantPartiesApi.ts
```

Экран КП:
- шапка: название, сервер, лидер;
- вкладки: состав, чат, хранилище, приглашения/настройки;
- состав: участники, права на хранилище, исключение, передача лидерства;
- чат: список сообщений + поле ввода;
- хранилище: предметы, история выдачи, модалки добавления/выдачи.

**10. Тесты**
Backend feature tests:
1. Создание КП делает выбранного персонажа лидером.
2. Нельзя создать КП без названия.
3. Нельзя пригласить персонажа с другого сервера.
4. Приглашение создает notification.
5. Accept добавляет участника.
6. Decline/revoke не добавляет.
7. При смене сервера обычный участник исключается.
8. Лидеру запрещена смена сервера без передачи лидерства.
9. Только лидер/storage-manager может управлять хранилищем.
10. История выдачи предметов сохраняется корректно.

**Рекомендуемый порядок реализации**
1. Миграции, модели, базовые policies/middleware.
2. CRUD создания и просмотра КП.
3. Пункт меню `Мои КП` и список КП на фронте.
4. Система приглашений + уведомления.
5. Состав КП и права на хранилище.
6. Хранилище КП по образцу `GuildBank`.
7. Чат КП + Socket.IO.
8. Обработка смены сервера персонажа.
9. Полировка UI и тесты.

> Сохрани этот план в md файл

<details><summary>2 previous messages</summary>

> Сохраню план в `docs` как отдельный markdown-файл, чтобы его можно было дальше править и использовать как техзадание. Назову файл нейтрально: `constant-party-plan.md`.
>
> <details><summary>Created a file</summary>
>
> - Wrote `./docs/constant-party-plan.md` (+333 -0)
>
> </details>
</details>

Готово, сохранил план в [docs/constant-party-plan.md](./docs/constant-party-plan.md).

Файл оформлен как полноценное ТЗ: UX, модель данных, приглашения, чат, хранилище, backend/frontend-слои, тесты и порядок реализации.

> может с таблице constant_parties достаточно только constant_parties, без localization_id?

Да, скорее всего в `constant_parties` достаточно хранить `server_id`, а `localization_id` не нужен.

Причина: персонаж уже имеет `server_id`, а сервер сам должен однозначно относиться к локализации. Если у `servers` есть `localization_id`, то `constant_parties.localization_id` будет дублировать данные и может разъехаться: КП хранит одну локализацию, сервер уже переехал/изменился или данные обновили не везде.

Я бы оставил так:

```text
constant_parties
- id
- leader_character_id
- game_id
- server_id
- name
- created_by_user_id
- created_at
- updated_at
- deleted_at nullable
```

Можно даже обсудить, нужен ли `game_id`: если `server_id -> localization_id -> game_id` гарантированно выводится, то `game_id` тоже денормализация. Но `game_id` часто удобно держать для быстрых фильтров “мои КП по игре” и для UI, так что его можно оставить как snapshot/индексируемое поле. А вот `localization_id` пользы дает меньше.

Рекомендация: убрать `localization_id` из плана, а при создании КП валидировать через персонажа-лидера и сохранять `server_id` + опционально `game_id`.

> Оставим как есть. Приступай к реализации плана

<details><summary>45 previous messages</summary>

> Окей, беру в работу. Начну с backend-скелета: миграции, модели, actions/controllers/routes и базовые проверки доступа, потом подключу фронтовый вход “Мои КП” и минимальные экраны, чтобы фича уже была проходимой end-to-end.
>
> Сейчас читаю ближайшие аналоги: `GuildBank`, `GuildApplication` и обновление персонажа. Это даст нам правильный стиль Laravel-кода и формат ресурсов, чтобы новая фича выглядела родной для проекта.
>
> Фича большая, поэтому я сделаю рабочий MVP с правильными расширяемыми точками: создание/список/просмотр КП, участники, приглашения с уведомлениями, базовое хранилище и чат по REST. Realtime для чата оставлю как следующий шаг, иначе сильно раздуем первый проход.
>
> Перед правками фиксирую одно практичное решение: в первом проходе не буду делать отдельные middleware-классы для КП, а проверки доступа вынесу в actions/controller helpers. Так меньше связки с bootstrap middleware и быстрее получить надежный первый слой, который потом легко вынести в policy.
>
> Теперь внесу основную пачку backend-файлов. Она большая, но механика стандартная: таблицы, Eloquent-связи, ресурсы, запросы и контроллеры с транзакциями для приглашений и выдачи предметов.
>
> <details><summary>Created 8 files</summary>
>
> - Wrote `./backend/Domains/ConstantParty/Models/ConstantParty.php` (+62 -0)
> - Wrote `./backend/Domains/ConstantParty/Models/ConstantPartyChatMessage.php` (+29 -0)
> - Wrote `./backend/Domains/ConstantParty/Models/ConstantPartyInvitation.php` (+49 -0)
> - Wrote `./backend/Domains/ConstantParty/Models/ConstantPartyMember.php` (+39 -0)
> - Wrote `./backend/Domains/ConstantParty/Models/ConstantPartyStorageItem.php` (+54 -0)
> - Wrote `./backend/Domains/ConstantParty/Models/ConstantPartyStorageItemGrant.php` (+44 -0)
> - Wrote `./backend/Domains/ConstantParty/Models/ConstantPartyStorageItemTier.php` (+27 -0)
> - Wrote `./backend/database/migrations/2026_06_14_120000_create_constant_party_tables.php` (+102 -0)
>
> </details>
>
> Миграции и модели легли. Дальше добавляю ресурсы и requests: это определит контракт API для фронта и сразу даст нормальную валидацию на создание КП, приглашения, чат и хранилище.
>
> <details><summary>Created 14 files</summary>
>
> - Wrote `./backend/app/Http/Resources/ConstantParty/ConstantPartyChatMessageResource.php` (+26 -0)
> - Wrote `./backend/app/Http/Resources/ConstantParty/ConstantPartyInvitationResource.php` (+32 -0)
> - Wrote `./backend/app/Http/Resources/ConstantParty/ConstantPartyMemberResource.php` (+28 -0)
> - Wrote `./backend/app/Http/Resources/ConstantParty/ConstantPartyResource.php` (+35 -0)
> - Wrote `./backend/app/Http/Resources/ConstantParty/ConstantPartyStorageGrantResource.php` (+31 -0)
> - Wrote `./backend/app/Http/Resources/ConstantParty/ConstantPartyStorageItemResource.php` (+33 -0)
> - Wrote `./backend/app/Http/Resources/ConstantParty/ConstantPartyStorageItemTierResource.php` (+26 -0)
> - Wrote `./backend/app/Http/Requests/ConstantParty/InviteConstantPartyCharacterRequest.php` (+23 -0)
> - Wrote `./backend/app/Http/Requests/ConstantParty/StoreConstantPartyChatMessageRequest.php` (+22 -0)
> - Wrote `./backend/app/Http/Requests/ConstantParty/StoreConstantPartyRequest.php` (+22 -0)
> - Wrote `./backend/app/Http/Requests/ConstantParty/StoreConstantPartyStorageGrantRequest.php` (+42 -0)
> - Wrote `./backend/app/Http/Requests/ConstantParty/StoreConstantPartyStorageItemRequest.php` (+34 -0)
> - Wrote `./backend/app/Http/Requests/ConstantParty/StoreConstantPartyStorageTierRequest.php` (+23 -0)
> - Wrote `./backend/app/Http/Requests/ConstantParty/UpdateConstantPartyMemberRequest.php` (+21 -0)
>
> </details>
>
> Добавляю контроллеры. Здесь будут главные бизнес-ограничения: “приглашать только на том же сервере”, “участник только в одной КП”, “хранилищем управляет лидер или выданное право”.
>
> <details><summary>Created 3 files</summary>
>
> - Wrote `./backend/app/Http/Controllers/Api/ConstantPartyChatController.php` (+91 -0)
> - Wrote `./backend/app/Http/Controllers/Api/ConstantPartyController.php` (+370 -0)
> - Wrote `./backend/app/Http/Controllers/Api/ConstantPartyStorageController.php` (+285 -0)
>
> </details>
>
> Backend-контроллеры готовы. Сейчас подключаю маршруты и добавляю доменные связи к персонажу плюс правило: лидер КП не может сменить сервер, а обычный участник при смене сервера исключается.
>
> Маршрутный файл чуть отличается по порядку импортов, поэтому я точечно посмотрю верхнюю часть и вставлю routes рядом с уведомлениями без угадывания контекста.
>
> Вставлю импорты и блок маршрутов одним аккуратным патчем около существующего блока notifications.
>
> <details><summary>Edited a file</summary>
>
> - Wrote `./backend/routes/api.php` (+29 -0)
>
> </details>
>
> Маршруты подключены. Теперь встраиваю серверное правило в обновление персонажа: до сохранения сравниваем сервер, лидера блокируем, обычного участника исключаем после успешного обновления.
>
> <details><summary>Edited 2 files</summary>
>
> - Wrote `./backend/Domains/Character/Actions/UpdateCharacterAction.php` (+51 -0)
> - Wrote `./backend/Domains/Character/Models/Character.php` (+6 -0)
>
> </details>
>
> Backend MVP на месте, перехожу к фронту: добавлю API-клиент, роуты, пункт в меню и два основных экрана - список/создание и карточку КП с вкладками состава, приглашений, чата и хранилища.
>
> <details><summary>Created a file</summary>
>
> - Wrote `./frontend/src/shared/api/constantPartiesApi.ts` (+217 -0)
>
> </details>
>
> API-клиент добавлен. Теперь добавляю страницы: список с приглашениями, форма создания и рабочая карточка КП с простыми вкладками. UI будет без лишней декоративности, но с полным набором действий для MVP.
>
> <details><summary>Created 2 files</summary>
>
> - Wrote `./frontend/src/pages/my-constant-parties/create.vue` (+94 -0)
> - Wrote `./frontend/src/pages/my-constant-parties/index.vue` (+147 -0)
>
> </details>
>
> Список и создание готовы. Карточка КП будет чуть плотнее: состав, приглашение по ID персонажа, чат и минимальное хранилище с выдачей предмета участнику.
>
> <details><summary>Created a file</summary>
>
> - Wrote `./frontend/src/pages/constant-parties/[id]/index.vue` (+294 -0)
>
> </details>
>
> Страницы добавлены. Подключаю их в router и меню аватара, после чего начну гонять статические проверки, потому что тут легко поймать мелкие несовпадения типов и route-model binding.
>
> Нашел уже нормализованные русские строки в router, патч не совпал из-за отличия кодировки в прочитанном ранее контексте. Вставляю по фактическому фрагменту.
>
> Разделю на два патча: router уже с понятным контекстом, header прочитаю вокруг пункта меню и вставлю отдельно.
>
> <details><summary>Edited a file</summary>
>
> - Wrote `./frontend/src/router/index.ts` (+18 -0)
>
> </details>
>
> Router подключен. Остался пункт в dropdown: там текст вокруг старых пунктов отдается в другой кодировке, поэтому вставлю по строке `to="/my-posts"` как надежному якорю.
>
> Отлично, header уже в нормальной кодировке для этих строк. Добавляю пункт “Мои КП” между персонажами и постами.
>
> <details><summary>Edited a file</summary>
>
> - Wrote `./frontend/src/widgets/header/Header.vue` (+5 -0)
>
> </details>
>
> Базовая фронтовая связка готова. Запускаю быстрые проверки: PHP lint по новым/измененным backend-файлам и TypeScript build для фронта, чтобы поймать несостыковки до финала.
>
> Небольшой сюрприз: `git diff` из PowerShell не видит репозиторий по UNC-пути, так что для проверки изменений буду опираться на файловые проверки и сборку. Сейчас запускаю PHP lint через PowerShell-цикл по измененным backend-файлам.
>
> Локальный `php` из Windows-среды не стартует в этом UNC workspace. Попробую проверку через docker-сервис проекта, если контейнеры подняты.
>
> Docker PHP доступен, отлично. Запускаю lint по новым backend-файлам внутри контейнера.
>
> PHP-синтаксис чистый. Теперь проверю frontend TypeScript/сборку; ожидаю пару типовых правок, потому что новые страницы используют API-клиент и router-params.
>
> Frontend build прошел; остались только уже существующие Rollup warnings по chunk/circular re-export. Проверю, что Laravel поднимает маршруты и не падает на автозагрузке новых классов.
>
> Маршруты зарегистрировались. Еще проверю миграцию в `--pretend`, чтобы поймать SQL/схемные ошибки без применения к базе.
>
> Миграция в pretend режиме корректная. Хочу еще посмотреть итоговый diff через WSL-git, потому что PowerShell по UNC не распознал `.git`; это поможет убедиться, что сборка не накидала лишних артефактов.
>
> `wsl` из sandbox тоже уперся в запуск. Повторю с разрешением только на чтение git diff, без изменений файлов.
>
> Git status показывает наши новые файлы как untracked, плюс уже существующую `.ai/` я не трогаю. Прогоню еще PHP lint по всем новым `ConstantParty`-директориям, чтобы не оставить синтаксическую мелочь в моделях/resources.
>
> Все новые PHP-файлы проходят lint. Полный тестовый прогон может быть заметно дольше, но после такого backend-изменения стоит хотя бы попробовать `php artisan test`, чтобы поймать регрессии на существующих сценариях.
>
> Полный backend suite прошел 71 тест и упал на 2 старых сценариях, не связанных с КП: один тест постов не передает теперь обязательные `title/character_id`, второй ходит на старый `/api/games/catalog` вместо `/api/v1/games/catalog`. Добавлю пару целевых feature-тестов для КП, чтобы новая логика тоже была прикрыта.
>
> Добавлю короткий `ConstantPartyTest`: создание лидером, запрет приглашения с другого сервера, принятие приглашения и выдача предмета из хранилища. Это покроет самые хрупкие правила MVP.
>
> <details><summary>Created a file</summary>
>
> - Wrote `./backend/tests/Feature/ConstantPartyTest.php` (+188 -0)
>
> </details>
>
> Целевые тесты добавлены. Запускаю только `ConstantPartyTest`, чтобы проверить именно новую функциональность независимо от старых падающих тестов.
>
> Новые тесты КП проходят. Остался один важный момент: полный suite падает на старых тестах, но целевые проверки новой фичи, build фронта, route-list и миграция в pretend режиме уже зеленые. Соберу итог и отмечу известные ограничения MVP.
</details>

Готово: реализовал MVP конст пати end-to-end.

Что добавлено:
- Backend-домен `ConstantParty`: модели, миграция, API, ресурсы и requests.
- Создание КП с персонажем-лидером.
- Список “Мои КП” и входящие приглашения.
- Приглашения персонажей с проверкой того же сервера и in-app notification.
- Принятие/отклонение приглашений.
- Состав КП, права `can_manage_storage`, исключение участников.
- Чат КП через REST.
- Хранилище КП: предметы, остатки, выдача, история выдачи на backend.
- Правило смены сервера: лидер КП не может сменить сервер, обычный участник исключается из КП.
- Пункт `Мои КП` в меню аватара с title `Мои конст пати`.
- План сохранен в [docs/constant-party-plan.md](/home/ksv180384/projects/gg-hub/docs/constant-party-plan.md).

Ключевые файлы:
- [миграция](/home/ksv180384/projects/gg-hub/backend/database/migrations/2026_06_14_120000_create_constant_party_tables.php)
- [ConstantPartyController.php](/home/ksv180384/projects/gg-hub/backend/app/Http/Controllers/Api/ConstantPartyController.php)
- [ConstantPartyStorageController.php](/home/ksv180384/projects/gg-hub/backend/app/Http/Controllers/Api/ConstantPartyStorageController.php)
- [constantPartiesApi.ts](/home/ksv180384/projects/gg-hub/frontend/src/shared/api/constantPartiesApi.ts)
- [Мои КП](/home/ksv180384/projects/gg-hub/frontend/src/pages/my-constant-parties/index.vue)
- [Карточка КП](/home/ksv180384/projects/gg-hub/frontend/src/pages/constant-parties/[id]/index.vue)

Проверки:
- `php -l` по новым backend-файлам: OK
- `php artisan route:list --path=constant-parties`: OK
- `php artisan migrate --path=... --pretend`: OK
- `npm run build`: OK
- `php artisan test tests/Feature/ConstantPartyTest.php`: 4 passed

Полный `php artisan test` сейчас падает на 2 уже существующих, не связанных с КП тестах:
- `BannedUserRestrictionsTest`: тест не передает обязательные `title` и `character_id`.
- `GamesCatalogTest`: тест ходит на `/api/games/catalog`, а приложение использует `/api/v1/games/catalog`.

Ограничение первого прохода: чат пока REST-only, без Socket.IO realtime. Backend и UI готовы так, чтобы realtime можно было добавить следующим слоем без переделки модели.

> при выполнении миграции такая ошибка
>  SQLSTATE[42000]: Syntax error or access violation: 1059 Identifier name 'constant_party_storage_item_grants_received_by_character_id_foreign' is too long (Connection: mariadb, Host: gg-mariadb, Port: 3306, Database: gg, SQL: alter table `constant_party_storage_item_grants` add constraint `constant_party_storage_item_grants_received_by_character_id_foreign` foreign key (`received_by_character_id`) references `characters` (`id`) on delete cascade)
>
>   at vendor/laravel/framework/src/Illuminate/Database/Connection.php:838
>     834▕             $exceptionType = $this->isUniqueConstraintError($e)
>     835▕                 ? UniqueConstraintViolationException::class
>     836▕                 : QueryException::class;
>     837▕
>   ➜ 838▕             throw new $exceptionType(
>     839▕                 $this->getNameWithReadWriteType(),
>     840▕                 $query,
>     841▕                 $this->prepareBindings($bindings),
>     842▕                 $e,
>
>       +9 vendor frames
>
>   10  database/migrations/2026_06_14_120000_create_constant_party_tables.php:80
>       Illuminate\Support\Facades\Facade::__callStatic("create")
>       +26 vendor frames
>
>   37  artisan:16
>       Illuminate\Foundation\Application::handleCommand(Object(Symfony\Component\Console\Input\ArgvInput))
>
> #

<details><summary>3 previous messages</summary>

> Да, это лимит MariaDB/MySQL на длину имени constraint. Исправлю миграцию: длинные auto-generated FK names заменю на короткие явные имена, особенно в таблицах storage grants/items.
>
> <details><summary>Edited a file</summary>
>
> - Wrote `./backend/database/migrations/2026_06_14_120000_create_constant_party_tables.php` (+26 -8)
>
> </details>
>
> Патч внесен. Проверю `migrate --pretend`, чтобы убедиться, что SQL теперь генерирует короткие constraint names.
</details>

Да, это из-за лимита MariaDB на имя foreign key. Я поправил миграцию: для длинных FK в `constant_party_storage_items` и `constant_party_storage_item_grants` теперь заданы короткие имена вроде `cp_grants_received_char_fk`.

Проверил:

```bash
php artisan migrate --path=database/migrations/2026_06_14_120000_create_constant_party_tables.php --pretend
```

Теперь SQL генерируется корректно.

Важно: так как миграция упала посередине, часть таблиц уже могла создаться, а запись в `migrations` не добавилась. Перед повторным запуском нужно удалить частично созданные таблицы КП в обратном порядке:

```sql
DROP TABLE IF EXISTS constant_party_storage_item_grants;
DROP TABLE IF EXISTS constant_party_storage_items;
DROP TABLE IF EXISTS constant_party_storage_item_tiers;
DROP TABLE IF EXISTS constant_party_chat_messages;
DROP TABLE IF EXISTS constant_party_invitations;
DROP TABLE IF EXISTS constant_party_members;
DROP TABLE IF EXISTS constant_parties;
```

После этого можно снова запускать:

```bash
php artisan migrate
```

> приглашение должно быть не по id персонажа, а в поисковой строке вводится ник персонажа и персонажи ищутся на том же сервере что и лидер. И найденому персонажу можно отправить приглашение в КП, которое он может отклонить или принять. При принятии или отклонении приглашения, пользователю который отправил приглашение, должно прийти оповещение с согласием или отказом

<details><summary>18 previous messages</summary>

> Да, согласен: ID в интерфейсе это техническая кость наружу, лучше поисковая строка по нику. Сделаю endpoint кандидатов по КП: ищет персонажей по имени только на сервере КП и исключает уже состоящих/уже приглашенных, а на accept/decline добавлю уведомление отправителю.
>
> Начинаю с backend: добавляю `invite-candidates` и уведомления отправителю при принятии/отклонении. Поиск будет case-insensitive через `LIKE`, ограничен сервером КП и отфильтрует текущих участников плюс pending-приглашения.
>
> <details><summary>Edited a file</summary>
>
> - Wrote `./backend/app/Http/Controllers/Api/ConstantPartyController.php` (+43 -1)
>
> </details>
>
> Заметил мелочь в патче: дублировался фильтр `whereDoesntHave`. Уберу дубликат и подключу маршрут поиска кандидатов.
>
> <details><summary>Edited 2 files</summary>
>
> - Wrote `./backend/app/Http/Controllers/Api/ConstantPartyController.php` (+0 -1)
> - Wrote `./backend/routes/api.php` (+1 -0)
>
> </details>
>
> Теперь фронт: заменяю поле “ID персонажа” на поисковую строку, список результатов и кнопку приглашения у найденного персонажа.
>
> Backend-часть почти готова. Сейчас добавлю метод в frontend API и перепишу форму приглашения: сначала поиск по нику, затем отправка приглашения выбранному найденному персонажу.
>
> Файл API в нормальной кодировке, вставляю метод по фактическому тексту.
>
> <details><summary>Edited a file</summary>
>
> - Wrote `./frontend/src/shared/api/constantPartiesApi.ts` (+9 -0)
>
> </details>
>
> Обновляю компонент карточки КП: добавлю состояние поиска, функцию `searchInviteCandidates`, и приглашение будет по выбранной строке результата.
>
> <details><summary>Edited a file</summary>
>
> - Wrote `./frontend/src/pages/constant-parties/[id]/index.vue` (+26 -6)
>
> </details>
>
> Скрипт компонента готов, теперь заменяю саму форму: ввод ника, кнопка “Найти”, результаты с кнопкой “Пригласить”.
>
> <details><summary>Edited a file</summary>
>
> - Wrote `./frontend/src/pages/constant-parties/[id]/index.vue` (+43 -4)
>
> </details>
>
> Логика UI готова. Добавлю/обновлю тесты: поиск кандидатов должен возвращать только персонажа с того же сервера, а отправитель приглашения должен получать уведомление при принятии и отказе.
>
> <details><summary>Edited a file</summary>
>
> - Wrote `./backend/tests/Feature/ConstantPartyTest.php` (+53 -0)
>
> </details>
>
> Тесты обновлены. Запускаю PHP lint и целевые тесты КП, затем фронтовую сборку.
>
> PHP-синтаксис чистый. Запускаю целевые feature-тесты.
>
> Целевые тесты прошли. Проверяю frontend build, чтобы убедиться, что новый поиск в Vue и API типы не сломали сборку.
</details>

Готово, переделал приглашения в КП под поиск по нику.

Что изменилось:
- Добавлен API поиска кандидатов:
  `GET /api/v1/constant-parties/{id}/invitations/candidates?query=ник`
- Поиск возвращает только персонажей на том же сервере, что и КП.
- Из результатов исключаются персонажи, которые уже состоят в КП или уже имеют pending-приглашение.
- В UI вместо поля `ID персонажа` теперь строка поиска по нику и список найденных персонажей с кнопкой `Пригласить`.
- При принятии приглашения отправителю приходит уведомление о согласии.
- При отклонении приглашения отправителю приходит уведомление об отказе.

Проверки:
- `php -l` по измененным backend-файлам: OK
- `php artisan test tests/Feature/ConstantPartyTest.php`: 6 passed
- `npm run build`: OK, остались только прежние Vite/Rollup warnings по чанкам.

> Поиск персонажа должен осуществляться при вводе текста, а не при нажатии на кнопку Найти

<details><summary>3 previous messages</summary>

> Сделаю поиск живым: при вводе ника будет запускаться debounce-запрос, кнопка “Найти” уберу из UX. Заодно оставлю защиту от коротких запросов, чтобы не спамить API на 1 символ.
>
> <details><summary>Edited a file</summary>
>
> - Wrote `./frontend/src/pages/constant-parties/[id]/index.vue` (+33 -23)
>
> </details>
>
> Поиск теперь привязан к `v-model` и срабатывает через 300 мс после остановки ввода. Проверю сборку фронта, чтобы убедиться, что Vue-шаблон и типы в порядке.
</details>

Готово. Поиск персонажа для приглашения теперь выполняется автоматически при вводе текста, с debounce `300ms`; кнопку `Найти` убрал.

Поведение:
- меньше 2 символов - запрос не отправляется;
- при вводе ника список найденных персонажей обновляется сам;
- старые ответы не перетирают новые, если пользователь быстро печатает;
- приглашение отправляется кнопкой `Пригласить` у найденного персонажа.

Проверка: `npm run build` прошел успешно, остались только прежние Vite/Rollup warnings по чанкам.
