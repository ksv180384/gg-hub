# План добавления конст пати

## Цель

Добавить пользовательские конст пати как отдельную сущность, не привязанную к гильдии, но привязанную к игре, локализации и серверу через персонажа-лидера.

В меню пользователя на аватаре добавить пункт `Мои КП`. При наведении показывать подсказку `Мои конст пати`.

## 1. Навигация и UX

1. В `frontend/src/widgets/header/Header.vue` добавить пункт в dropdown пользователя:
   - текст: `Мои КП`;
   - ссылка: `/my-constant-parties`;
   - `title`: `Мои конст пати`.
2. В `frontend/src/router/index.ts` добавить роуты:
   - `/my-constant-parties` - список КП пользователя;
   - `/my-constant-parties/create` - создание КП;
   - `/constant-parties/:id` - страница КП;
   - `/constant-parties/:id/storage` - хранилище КП;
   - чат можно сделать вкладкой на общей странице КП или отдельным роутом `/constant-parties/:id/chat`.
3. На странице `Мои КП` показать:
   - список КП, где персонажи пользователя состоят участниками;
   - кнопку создания КП;
   - роль пользователя в каждой КП: лидер, участник, управление хранилищем;
   - входящие приглашения отдельным блоком.

## 2. Модель данных

Добавить backend-домен `backend/Domains/ConstantParty`.

### `constant_parties`

```text
id
leader_character_id
game_id
localization_id
server_id
name
created_by_user_id
created_at
updated_at
deleted_at nullable
```

### `constant_party_members`

```text
id
constant_party_id
character_id
role enum: leader/member
can_manage_storage boolean
joined_at
created_at
updated_at
```

### `constant_party_invitations`

```text
id
constant_party_id
invited_character_id
invited_by_character_id
status enum: pending/accepted/declined/revoked/expired
message nullable
responded_at nullable
expires_at nullable
created_at
updated_at
```

### `constant_party_chat_messages`

```text
id
constant_party_id
character_id
body
created_at
updated_at
deleted_at nullable
```

### `constant_party_storage_item_tiers`

```text
id
constant_party_id
name
color
sort_order
created_at
updated_at
```

### `constant_party_storage_items`

```text
id
constant_party_id
tier_id nullable
name
description nullable
quantity nullable
created_by_character_id
updated_by_character_id nullable
created_at
updated_at
```

### `constant_party_storage_item_grants`

```text
id
constant_party_id
item_id
received_by_character_id
granted_by_character_id
reason nullable
granted_at
created_at
updated_at
```

Членство лучше вести по `character_id`, а не по `user_id`, потому что правила серверов и лидерства завязаны на персонажа.

## 3. Создание КП

1. Авторизованный пользователь выбирает одного из своих персонажей.
2. Выбранный персонаж становится лидером.
3. Название КП обязательно.
4. `game_id`, `localization_id`, `server_id` копируются с персонажа-лидера.
5. Лидер автоматически добавляется в `constant_party_members`:
   - `role = leader`;
   - `can_manage_storage = true`.
6. Нужно заранее зафиксировать правило членства:
   - рекомендовано для MVP: один персонаж может состоять только в одной активной КП.

## 4. Приглашения

Лидер или участник с правом управления составом может пригласить персонажа.

Условия приглашения:

1. Персонаж существует.
2. `server_id` персонажа совпадает с `constant_parties.server_id`.
3. Персонаж еще не состоит в этой КП.
4. Нет активного `pending`-приглашения в эту КП.
5. Если вводится ограничение "один персонаж - одна КП", персонаж не должен состоять в другой активной КП.

При приглашении:

1. Создать запись `constant_party_invitations` со статусом `pending`.
2. Создать in-app notification владельцу приглашенного персонажа через существующую систему `notifications`.
3. Уведомление ведет на `/my-constant-parties` или на отдельную страницу принятия приглашения.

Нужные actions:

```text
InviteCharacterToConstantPartyAction
AcceptConstantPartyInvitationAction
DeclineConstantPartyInvitationAction
RevokeConstantPartyInvitationAction
ExpireConstantPartyInvitationsAction
```

## 5. Исключение при переходе персонажа на другой сервер

Добавить доменное действие:

```text
RemoveCharacterFromConstantPartiesOnServerChangeAction
```

Логика:

1. До обновления персонажа сохранить старый `server_id`.
2. После обновления проверить, изменился ли `server_id`.
3. Если сервер изменился:
   - обычного участника автоматически исключить из КП;
   - создать уведомления участнику и лидеру КП;
   - если персонаж лидер, применить отдельное правило.

Рекомендация для MVP: лидеру запрещать смену сервера, пока он лидер КП. Для смены сервера лидер должен сначала передать лидерство или распустить КП.

## 6. Чат КП

REST API:

```text
GET    /api/v1/constant-parties/{party}/chat/messages
POST   /api/v1/constant-parties/{party}/chat/messages
DELETE /api/v1/constant-parties/{party}/chat/messages/{message}
```

Правила:

1. Доступ только участникам КП.
2. Сообщения хранить в `constant_party_chat_messages`.
3. Удаление делать через soft delete.
4. Для realtime использовать `socket_server`:
   - room: `constant-party:{id}:chat`;
   - broadcast endpoint: `/constant-parties/chat/broadcast-created`;
   - client event: `constant-party-chat-message-created`.

## 7. Хранилище КП

Хранилище сделать по образцу гильдейского банка, но без DKP на первом этапе.

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

1. Смотреть хранилище могут все участники КП.
2. Добавлять, редактировать предметы, выдавать предметы и отменять выдачу могут:
   - лидер;
   - участники с `can_manage_storage = true`.
3. История выдачи предметов хранится в `constant_party_storage_item_grants`.

## 8. Backend-слой

Добавить models:

```text
ConstantParty
ConstantPartyMember
ConstantPartyInvitation
ConstantPartyChatMessage
ConstantPartyStorageItemTier
ConstantPartyStorageItem
ConstantPartyStorageItemGrant
```

Добавить controllers:

```text
ConstantPartyController
ConstantPartyInvitationController
ConstantPartyChatController
ConstantPartyStorageController
```

Добавить requests и resources для списков, карточек, приглашений, сообщений чата и хранилища.

Добавить middleware или policy:

```text
constant.party.member
constant.party.leader
constant.party.storage-manager
```

Роуты добавить в `backend/routes/api.php` внутри группы `auth`.

## 9. Frontend-слой

Предлагаемая структура:

```text
frontend/src/features/constant-party/
frontend/src/widgets/constant-party/
frontend/src/pages/my-constant-parties/
frontend/src/pages/constant-parties/[id]/
frontend/src/shared/api/constantPartiesApi.ts
```

Страница КП:

1. Шапка: название, сервер, лидер.
2. Вкладки:
   - состав;
   - чат;
   - хранилище;
   - приглашения и настройки.
3. Состав:
   - список участников;
   - права на хранилище;
   - исключение участника;
   - передача лидерства.
4. Чат:
   - список сообщений;
   - поле ввода;
   - realtime-обновления через Socket.IO.
5. Хранилище:
   - список предметов;
   - история выдачи;
   - модалки добавления, редактирования, выдачи и отмены выдачи.

## 10. Тесты

Backend feature tests:

1. Создание КП делает выбранного персонажа лидером.
2. Нельзя создать КП без названия.
3. Нельзя пригласить персонажа с другого сервера.
4. Приглашение создает notification.
5. Accept добавляет участника.
6. Decline и revoke не добавляют участника.
7. При смене сервера обычный участник исключается из КП.
8. Лидеру запрещена смена сервера без передачи лидерства или роспуска КП.
9. Только лидер или storage-manager может управлять хранилищем.
10. История выдачи предметов сохраняется корректно.

## 11. Рекомендуемый порядок реализации

1. Миграции, модели, базовые policies/middleware.
2. CRUD создания и просмотра КП.
3. Пункт меню `Мои КП` и список КП на фронте.
4. Система приглашений и уведомления.
5. Состав КП и права на хранилище.
6. Хранилище КП по образцу `GuildBank`.
7. Чат КП и Socket.IO.
8. Обработка смены сервера персонажа.
9. Полировка UI и тесты.
