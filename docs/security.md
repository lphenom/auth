# Безопасность

## Хранение токенов

- В базе данных хранится **только SHA-256 хеш секрета токена**, не сам токен.
- Даже при утечке БД злоумышленник не сможет восстановить plaintext-токен.
- Используется `hash_equals()` для сравнения хешей (защита от timing-атак).

## Хранение паролей

- Пароли хешируются через `password_hash()` с алгоритмом `PASSWORD_BCRYPT`.
- По умолчанию cost = 10 (настраивается через конструктор `BcryptPasswordHasher`).
- Поддержка `needsRehash()` — автоматическое обнаружение устаревших хешей.

## Генерация токенов

- **tokenId**: 16 случайных байт → 32 hex-символа (`random_bytes(16)`)
- **secret**: 32 случайных байт → 64 hex-символа (`random_bytes(32)`)
- `random_bytes()` использует криптографически стойкий генератор ОС.

## Защита от brute-force

### Rate limiting на логин

Используйте `LoginThrottleInterface` для ограничения попыток:

```php
use LPhenom\Auth\Support\CacheThrottle;

$throttle = new CacheThrottle($cache);

$authManager = new DefaultAuthManager(
    $userProvider,
    $hasher,
    $encoder,
    $tokenRepo,
    $throttle,      // Включаем throttle
    $audit,
    86400,
    5,              // Макс. 5 попыток
    60              // Блокировка на 60 секунд
);
```

Ключ throttle формируется как `auth:login:<login>`. При 5 неудачных попытках последующие запросы блокируются на 60 секунд.

### Ограничение по IP

Для ограничения по IP рекомендуется использовать `RateLimitMiddleware` из `lphenom/http` перед auth-маршрутами.

## Аудит-логирование

Никогда не логируются:
- Plaintext пароли
- Plaintext токены

Логируются:
- Успешный вход (login, user_id)
- Неудачная попытка входа (login)
- Выдача токена (user_id, token_id)
- Отзыв токена (token_id)
- Невалидная попытка bearer-аутентификации (причина)

```php
use LPhenom\Auth\Support\LogAuditListener;

$audit = new LogAuditListener($logger);
```

## Одноразовые коды (SMS / Email)

- Коды хранятся в кеше как **SHA-256 хеш**, не в открытом виде.
- Коды имеют ограниченный TTL (по умолчанию 300 секунд).
- После успешной верификации код удаляется из кеша.
- Используйте `LoginThrottleInterface` для ограничения частоты отправки кодов.

## Рекомендации

1. **Используйте HTTPS** — bearer-токены передаются в заголовках, без TLS они видны в сети.
2. **Устанавливайте разумный TTL** — не делайте токены бессрочными.
3. **Отзывайте токены при смене пароля** — `$tokenRepo->revokeAllForUser($userId)`.
4. **Логируйте auth-события** — используйте `LogAuditListener`.
5. **Ограничивайте попытки** — всегда включайте throttle в production.
6. **Не храните токены на клиенте в localStorage** — используйте httpOnly cookies или secure storage.

## KPHP-совместимость

Все криптографические операции поддерживаются KPHP:
- `random_bytes()` ✅
- `password_hash()` / `password_verify()` ✅
- `hash('sha256', ...)` ✅
- `hash_equals()` ✅
- `bin2hex()` ✅

