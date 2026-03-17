# Bearer-токены

## Почему opaque bearer токены, а не JWT

В LPhenom Auth используются **opaque bearer-токены**, а не JWT. Причины:

1. **Простой отзыв** — opaque-токен можно мгновенно инвалидировать, пометив запись в БД как `revoked`. JWT придётся ждать истечения или реализовывать blacklist.
2. **Совместимость с shared hosting** — не нужна криптографическая библиотека для подписей (RSA/EdDSA).
3. **KPHP-совместимость** — opaque-токен не требует `json_encode/json_decode` с JWT-claims, не зависит от `openssl_*` функций.
4. **Простота реализации** — меньше кода, меньше поверхность атаки.

## Формат токена

```
<tokenId>.<secret>
```

- **tokenId**: 32 hex-символа (16 случайных байт) — идентификатор для поиска в БД.
- **secret**: 64 hex-символа (32 случайных байт) — секрет для проверки.

Пример:

```
a1b2c3d4e5f6a1b2c3d4e5f6a1b2c3d4.0123456789abcdef0123456789abcdef0123456789abcdef0123456789abcdef
```

## Хранение токенов

В базе данных хранится **только hash секрета** (SHA-256), не сам токен:

```
token_id    = a1b2c3d4e5f6...
token_hash  = sha256(secret) = 9f86d08188...
user_id     = 42
expires_at  = 2026-03-18 13:00:00
revoked_at  = NULL
```

Это значит, что даже при утечке БД злоумышленник не сможет восстановить plaintext-токен.

## Жизненный цикл токена

### 1. Выдача (issue)

```php
$user = $authManager->attempt('user@example.com', 'password');
if ($user !== null) {
    $issued = $authManager->issueToken($user);
    // $issued->plainTextToken — отправляем клиенту ОДИН РАЗ
    // $issued->tokenId — ID для логирования
    // $issued->expiresAt — время истечения
}
```

### 2. Аутентификация (authenticate)

```php
// Клиент отправляет: Authorization: Bearer <tokenId>.<secret>
$user = $authManager->authenticateBearer($request->getHeader('Authorization'));
```

Процесс:
1. Извлечь токен из заголовка `Authorization: Bearer ...`
2. Разобрать на `tokenId` и `secret`
3. Найти запись по `tokenId` в БД
4. Вычислить `sha256(secret)` и сравнить с `token_hash` (через `hash_equals`)
5. Проверить `revoked_at IS NULL`
6. Проверить `expires_at > NOW()`
7. Загрузить пользователя через `UserProvider`

### 3. Отзыв (revoke)

```php
// По конкретному токену
$authManager->logoutToken($plainBearerToken);

// Все токены пользователя (через TokenRepository)
$tokenRepo->revokeAllForUser($userId);
```

## Схема таблицы `auth_tokens`

```sql
CREATE TABLE auth_tokens (
    id         INTEGER PRIMARY KEY AUTO_INCREMENT,
    token_id   VARCHAR(64) NOT NULL,
    user_id    VARCHAR(255) NOT NULL,
    token_hash VARCHAR(64) NOT NULL,
    created_at DATETIME NOT NULL,
    expires_at DATETIME NOT NULL,
    revoked_at DATETIME DEFAULT NULL,
    meta_json  TEXT DEFAULT NULL,
    UNIQUE KEY idx_token_id (token_id),
    KEY idx_user_id (user_id),
    KEY idx_expires_at (expires_at)
);
```

## TTL токена

По умолчанию — 86400 секунд (24 часа). Настраивается через параметр `tokenTtl` в `DefaultAuthManager`.

Рекомендации:
- **Web-приложения**: 86400 (24 часа) — 604800 (7 дней)
- **Мобильные приложения**: 2592000 (30 дней)
- **API-интеграции**: 31536000 (1 год)

## Метаданные токена

При выдаче токена можно передать JSON-метаданные:

```php
$metaJson = json_encode(['device' => 'mobile', 'ip' => '192.168.1.1']);
if ($metaJson === false) {
    $metaJson = '';
}
$issued = $authManager->issueToken($user, $metaJson);
```

Метаданные хранятся в поле `meta_json` и могут использоваться для аудита.

## KPHP-совместимость

Вся реализация bearer-токенов KPHP-совместима:

- `random_bytes()` — поддерживается KPHP
- `bin2hex()` — поддерживается KPHP
- `hash('sha256', ...)` — поддерживается KPHP
- `hash_equals()` — поддерживается KPHP
- Нет JWT, нет `openssl_*`, нет `json_decode` с `JSON_THROW_ON_ERROR`

