# LPhenom Auth

**lphenom/auth** — пакет аутентификации для фреймворка LPhenom.

Поддерживает bearer-токены, хеширование паролей, guards, middleware, rate limiting, аудит-логирование, а также авторизацию по SMS (MirSMS) и Email (UniSender) через одноразовые коды.

Совместим с **PHP >= 8.1** и **KPHP** (компилируется в статический бинарник).

---

## Возможности

- 🔐 **Bearer Token аутентификация** — opaque-токены (не JWT), хранятся как SHA-256 хеш
- 🔑 **Хеширование паролей** — PBKDF2-HMAC-SHA256 (`CryptPasswordHasher`), KPHP-совместимо
- 🛡 **Guards и Middleware** — `RequireAuthMiddleware`, `RequireRoleMiddleware`
- 📱 **SMS авторизация** — интеграция с MirSMS API
- 📧 **Email авторизация** — отправка кодов через UniSender API
- 🚦 **Rate limiting** — ограничение попыток логина
- 📝 **Аудит-логирование** — через lphenom/log
- 🗄 **DB адаптер** — `DbTokenRepository` через lphenom/db
- ⚙️ **KPHP-совместимость** — никакого reflection, eval, dynamic loading

---

## Установка

```bash
composer require lphenom/auth
```

С VCS-репозиториями:

```json
{
    "repositories": [
        {"type": "vcs", "url": "https://github.com/lphenom/auth"},
        {"type": "vcs", "url": "https://github.com/lphenom/core"},
        {"type": "vcs", "url": "https://github.com/lphenom/http"},
        {"type": "vcs", "url": "https://github.com/lphenom/cache"},
        {"type": "vcs", "url": "https://github.com/lphenom/log"},
        {"type": "vcs", "url": "https://github.com/lphenom/db"},
        {"type": "vcs", "url": "https://github.com/lphenom/migrate"}
    ]
}
```

---

## Быстрый старт

### 1. Реализуйте интерфейс пользователя

```php
use LPhenom\Auth\Contracts\AuthenticatedUserInterface;

final class User implements AuthenticatedUserInterface
{
    // ... поля: id, email, passwordHash, roles, active

    public function getAuthIdentifier(): string { return $this->id; }
    public function getAuthRoles(): array { return $this->roles; }
    public function getAuthPasswordHash(): ?string { return $this->passwordHash; }
    public function isActive(): bool { return $this->active; }
}
```

### 2. Реализуйте UserProvider

```php
use LPhenom\Auth\Contracts\UserProviderInterface;

final class DbUserProvider implements UserProviderInterface
{
    public function findById(string $id): ?AuthenticatedUserInterface { /* SQL запрос */ }
    public function findByLogin(string $login): ?AuthenticatedUserInterface { /* SQL запрос */ }
}
```

### 3. Настройте AuthManager

```php
use LPhenom\Auth\Hashing\CryptPasswordHasher;
use LPhenom\Auth\Support\DefaultAuthManager;
use LPhenom\Auth\Support\DbTokenRepository;
use LPhenom\Auth\Tokens\OpaqueTokenEncoder;

$authManager = new DefaultAuthManager(
    $userProvider,
    new CryptPasswordHasher(10000),
    new OpaqueTokenEncoder(),
    new DbTokenRepository($db),
    $throttle,   // null если не нужен
    $audit,      // null если не нужен
    86400,       // TTL токена (сек)
    5,           // Макс. попыток логина
    60           // Время блокировки (сек)
);
```

### 4. Аутентификация

```php
// Логин
$user = $authManager->attempt('user@example.com', 'password');
if ($user !== null) {
    $issued = $authManager->issueToken($user);
    echo 'Token: ' . $issued->plainTextToken;
}

// Проверка bearer токена
$user = $authManager->authenticateBearer($request->getHeader('Authorization'));

// Logout
$authManager->logoutToken($plainToken);
```

---

## Структура пакета

```
src/
  Contracts/          — интерфейсы (AuthenticatedUserInterface, UserProviderInterface, ...)
  DTO/                — TokenRecord, IssuedToken, ParsedToken, AuthContext
  Exceptions/         — AuthException, InvalidCredentialsException, ...
  Guards/             — BearerTokenGuard
  Hashing/            — CryptPasswordHasher
  Middleware/          — RequireAuthMiddleware, RequireRoleMiddleware
  Migrations/         — CreateAuthTokensTable, CreateAuthCodesTable
  Tokens/             — OpaqueTokenEncoder
  Support/            — DefaultAuthManager, DbTokenRepository, ...
    SmsSender/        — MirSmsSender, SmsCodeAuthenticator
    EmailSender/      — UniSenderEmailSender, EmailCodeAuthenticator
tests/
docs/
build/
```

---

## SMS / Email авторизация

Пакет поддерживает авторизацию по одноразовым кодам:

- **SMS** через MirSMS API
- **Email** через UniSender API

Подробнее: [docs/sms-email-auth.md](docs/sms-email-auth.md)

---

## Документация

- [Быстрый старт](docs/quickstart.md)
- [Bearer-токены](docs/bearer-tokens.md)
- [HTTP интеграция](docs/http-integration.md)
- [SMS / Email коды](docs/sms-email-auth.md)
- [Безопасность](docs/security.md)

---

## Разработка

```bash
# Клонировать
git clone git@github.com:lphenom/auth.git && cd auth

# Установить зависимости (через Docker)
make install

# Тесты
make test

# Линтер
make lint

# PHPStan
make stan

# KPHP-проверка
make kphp-check
```

---

## Зависимости

| Пакет            | Назначение                     |
|------------------|-------------------------------|
| `lphenom/core`   | Базовые утилиты, Config, Env  |
| `lphenom/http`   | Request, Response, Middleware  |
| `lphenom/cache`  | Кеширование (throttle, коды)  |
| `lphenom/log`    | Аудит-логирование             |
| `lphenom/db`     | DbTokenRepository             |
| `lphenom/migrate`| Миграции таблиц               |

---

## Лицензия

MIT — см. [LICENSE](LICENSE)
