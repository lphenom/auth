# Build Targets — Архитектура сборок lphenom/auth

Этот документ описывает систему аннотаций `@lphenom-build`, принцип
**один интерфейс — разные реализации** и то, как строится KPHP-бинарник
против PHP shared-hosting режима.

---

## Зачем два режима?

LPhenom поддерживает два режима работы одного и того же приложения:

| Режим | Рантайм | Способ |
|---|---|---|
| `shared` | PHP 8.1+ | Apache/Nginx + PHP-FPM, shared hosting |
| `kphp` | Нативный бинарник | vkcom/kphp → C++ → статический бинарник |

Цель: **один и тот же бизнес-код работает в обоих режимах**.
Только низкоуровневые реализации (хешер паролей, SMTP-клиент) различаются.

---

## Аннотация `@lphenom-build`

Каждый PHP-файл в `src/` помечается аннотацией в DocBlock:

```php
/**
 * ...описание...
 *
 * @lphenom-build shared,kphp
 */
```

### Значения

| Значение | Включается в shared-сборку | Включается в kphp-сборку | Смысл |
|---|:---:|:---:|---|
| `shared,kphp` | ✅ | ✅ | Универсальный файл, работает в обоих режимах |
| `shared` | ✅ | ❌ | Только PHP runtime (fsockopen, password_hash, PDO) |
| `kphp` | ❌ | ✅ | KPHP-альтернатива с тем же интерфейсом |

### Правило

> Для каждого класса `@lphenom-build shared` должен существовать
> класс `@lphenom-build kphp`, реализующий **тот же интерфейс**.
> Переключение между ними — только через конфиг.

---

## Таблица альтернатив

| Интерфейс | Реализация shared | Реализация kphp | Почему разные |
|---|---|---|---|
| `PasswordHasherInterface` | `BcryptPasswordHasher` (опционально) | `CryptPasswordHasher` | `password_hash()` недоступна в KPHP |
| `CodeSenderInterface` (email) | `SmtpEmailSender` | `KphpHttpEmailSender` | `fsockopen()` / `stream_socket_enable_crypto()` недоступны в KPHP |

> **Важно:** `CryptPasswordHasher` помечен `@lphenom-build shared,kphp` и является
> **рекомендуемым хешером по умолчанию для обоих режимов**. Обе сборки используют
> одинаковый формат хешей — база данных совместима без каких-либо миграций.
> `BcryptPasswordHasher` — опция только для pure-PHP развёртываний без KPHP.

### Классы, совместимые с обоими режимами (`shared,kphp`)

Следующие классы работают в обоих режимах без изменений:

| Класс | Почему совместим |
|---|---|
| `OpaqueTokenEncoder` | `random_bytes()`, `bin2hex()`, `hash()` — все поддерживаются KPHP |
| `DefaultAuthManager` | Зависит только от контрактов |
| `AuthContextStorage` | Чистый PHP, static-переменная |
| `MemoryThrottle` | Чистый PHP, массив |
| `InMemoryTokenRepository` | Чистый PHP, массив |
| `CacheThrottle` | `lphenom/cache` — `CacheInterface` поддерживается KPHP |
| `LogAuditListener` | `lphenom/log` — `LoggerInterface` поддерживается KPHP |
| `DbTokenRepository` | `lphenom/db` — `ConnectionInterface` поддерживается KPHP через FFI-драйвер |
| `MirSmsSender` | `file_get_contents` + `stream_context_create` — KPHP-совместимы |
| `SmsCodeAuthenticator` | `CacheInterface` — KPHP-совместим |
| `EmailCodeAuthenticator` | `CacheInterface` — KPHP-совместим |
| `HttpAuthBridge` | `lphenom/http` — полностью KPHP-совместим |
| `BearerTokenGuard` | `lphenom/http` — полностью KPHP-совместим |
| `RequireAuthMiddleware` | `lphenom/http` — полностью KPHP-совместим |
| `RequireRoleMiddleware` | `lphenom/http` — полностью KPHP-совместим |
| `Migrations/*` | `ConnectionInterface` — в KPHP используется FFI-драйвер |

---

## Подробно о реализациях

### `PasswordHasherInterface`

#### `BcryptPasswordHasher` — `@lphenom-build shared`

```php
use LPhenom\Auth\Hashing\BcryptPasswordHasher;

$hasher = new BcryptPasswordHasher(cost: 10);
$hash   = $hasher->hash('my-password');    // $2y$10$...
$ok     = $hasher->verify('my-password', $hash); // true
```

- Использует нативный `password_hash(PASSWORD_BCRYPT)` / `password_verify()`
- Формат хеша: `$2y$<cost>$<salt><hash>` (стандартный bcrypt)
- **Недоступен в KPHP** (функция `password_hash` отсутствует в KPHP runtime)

#### `CryptPasswordHasher` — `@lphenom-build kphp`

```php
use LPhenom\Auth\Hashing\CryptPasswordHasher;

$hasher = new CryptPasswordHasher(iterations: 10000);
$hash   = $hasher->hash('my-password');    // $lphenom$sha256$10000$<salt>$<hash>
$ok     = $hasher->verify('my-password', $hash); // true
```

- Использует итеративный `hash_hmac('sha256', ...)` — упрощённый PBKDF2
- Формат хеша: `$lphenom$sha256$<iterations>$<salt32hex>$<hash64hex>`
- **Поддерживается в KPHP** (`hash_hmac`, `hash_equals`, `random_bytes`, `bin2hex`)

#### ⚠️ Совместимость хешей

> **Хеши `BcryptPasswordHasher` и `CryptPasswordHasher` НЕ взаимозаменяемы.**
>
> Если вы переводите приложение с `shared` на `kphp` (или обратно),
> пользователям придётся сбросить пароли.
>
> **Рекомендация для миграции:**
> 1. После смены режима — принудительный logout всех пользователей
> 2. Отправка email со ссылкой для сброса пароля
> 3. При первом входе — пользователь вводит новый пароль, хеш пересоздаётся в новом формате

---

### `CodeSenderInterface` (Email)

#### `SmtpEmailSender` — `@lphenom-build shared`

```php
use LPhenom\Auth\Support\EmailSender\SmtpEmailSender;

$sender = new SmtpEmailSender(
    host: 'smtp.example.com',
    port: 587,
    username: 'user@example.com',
    password: 'secret',
    fromEmail: 'noreply@example.com',
    fromName: 'MyApp',
    encryption: 'tls'
);
$sender->send('user@example.com', '123456');
```

- Прямое TCP-соединение через `fsockopen()` / `stream_socket_enable_crypto()`
- Поддерживает TLS/SSL, SMTP AUTH
- **Недоступен в KPHP** — `fsockopen()` и `stream_socket_enable_crypto()` не реализованы

#### `KphpHttpEmailSender` — `@lphenom-build kphp`

```php
use LPhenom\Auth\Support\EmailSender\KphpHttpEmailSender;

$sender = new KphpHttpEmailSender(
    apiUrl: 'https://api.mailgun.net/v3/example.com/messages',
    apiKey: 'key-xxxxxxxxxxxxxxx',
    fromEmail: 'noreply@example.com',
    subject: 'Ваш код подтверждения'
);
$sender->send('user@example.com', '123456');
```

- POST-запрос через `file_get_contents()` + `stream_context_create()`
- Работает с любым HTTP API: Mailgun, SendGrid, кастомный эндпоинт
- **KPHP-совместим** — `file_get_contents` с HTTP context поддерживается KPHP

---

## Как переключается реализация: конфиг

В приложении не должно быть хардкода конкретного класса.
Выбор реализации через конфиг:

```php
// config/auth.php (или через .env)
$buildMode = $_ENV['APP_BUILD'] ?? 'shared'; // 'shared' или 'kphp'

if ($buildMode === 'kphp') {
    $passwordHasher = new \LPhenom\Auth\Hashing\CryptPasswordHasher(10000);
    $emailSender    = new \LPhenom\Auth\Support\EmailSender\KphpHttpEmailSender(
        $_ENV['EMAIL_API_URL'],
        $_ENV['EMAIL_API_KEY'],
        $_ENV['EMAIL_FROM']
    );
} else {
    $passwordHasher = new \LPhenom\Auth\Hashing\BcryptPasswordHasher(10);
    $emailSender    = new \LPhenom\Auth\Support\EmailSender\SmtpEmailSender(
        $_ENV['SMTP_HOST'],
        (int) $_ENV['SMTP_PORT'],
        $_ENV['SMTP_USER'],
        $_ENV['SMTP_PASS'],
        $_ENV['EMAIL_FROM'],
        $_ENV['EMAIL_FROM_NAME'],
        $_ENV['SMTP_ENCRYPTION']
    );
}

// Оба реализуют одинаковые интерфейсы — остальной код не меняется
$authManager = new \LPhenom\Auth\Support\DefaultAuthManager(
    userProvider: $userProvider,
    passwordHasher: $passwordHasher,  // ← один интерфейс, разный класс
    tokenEncoder: $tokenEncoder,
    tokenRepository: $tokenRepository
);
```

---

## Как работает билдер

> **Примечание:** Выбор реализации (shared или kphp) осуществляется билдером
> из основного скелетона **lphenom/lphenom**. Данный пакет (`lphenom/auth`)
> предоставляет только реализации обоих режимов — конкретный класс выбирает
> приложение на этапе сборки.
>
> Подробно о нюансах каждой пары реализаций: [docs/kphp-vs-shared.md](./kphp-vs-shared.md)

### Shared build (PHP runtime)

Используется Composer PSR-4 autoload — никаких специальных шагов.

```bash
composer install
php public/index.php
```

В autoload включаются ВСЕ классы из `src/` (и `shared`, и `kphp`).
Правильный класс выбирается через конфиг (см. выше) или через билдер `lphenom/lphenom`.

### KPHP build

1. Только файлы с `@lphenom-build kphp` и `@lphenom-build shared,kphp` входят в бинарник
2. Файлы с `@lphenom-build shared` **не компилируются**
3. Entrypoint: `build/kphp-entrypoint.php` — явный список `require_once`

```bash
# Проверка совместимости (KPHP + PHAR)
docker build -f Dockerfile.check -t lphenom-auth-check .

# Или напрямую через kphp CLI:
kphp -d /build/kphp-out -M cli build/kphp-entrypoint.php
```

---

## lphenom/db в KPHP: PDO vs FFI

`DbTokenRepository` использует `ConnectionInterface` из `lphenom/db`.
Это позволяет одному классу работать с разными драйверами:

| Режим | Драйвер | Аннотация |
|---|---|---|
| `shared` (PHP) | `PdoMySqlConnection` | `@lphenom-build shared` |
| `kphp` (compiled) | `FfiMySqlConnection` | `@lphenom-build shared,kphp` |

```php
// PHP (shared) режим:
$conn = new \LPhenom\Db\Driver\PdoMySqlConnection($pdo);

// KPHP режим:
$conn = new \LPhenom\Db\Driver\FfiMySqlConnection($host, $user, $pass, $db);

// Оба реализуют ConnectionInterface — DbTokenRepository не меняется:
$tokenRepo = new \LPhenom\Auth\Support\DbTokenRepository($conn);
```

---

## Переменные окружения для сборки

```dotenv
# Режим сборки: 'shared' или 'kphp'
APP_BUILD=shared

# Shared mode: SMTP
SMTP_HOST=smtp.example.com
SMTP_PORT=587
SMTP_USER=user@example.com
SMTP_PASS=secret
SMTP_ENCRYPTION=tls
EMAIL_FROM=noreply@example.com
EMAIL_FROM_NAME=MyApp

# KPHP mode: HTTP Email API
EMAIL_API_URL=https://api.mailgun.net/v3/example.com/messages
EMAIL_API_KEY=key-xxxxxxxxxxxxxxx
```

---

## Добавление собственной реализации

Чтобы добавить новую KPHP-совместимую реализацию интерфейса:

1. Создайте класс, реализующий нужный интерфейс
2. Пометьте `@lphenom-build kphp` или `@lphenom-build shared,kphp`
3. Убедитесь, что класс не использует запрещённые в KPHP конструкции
4. Добавьте `require_once` в `build/kphp-entrypoint.php`
5. Зарегистрируйте в конфиге приложения

Пример кастомного хешера паролей для KPHP:

```php
<?php
declare(strict_types=1);

namespace App\Auth\Hashing;

use LPhenom\Auth\Contracts\PasswordHasherInterface;

/**
 * @lphenom-build kphp
 */
final class Argon2KphpHasher implements PasswordHasherInterface
{
    // ваша реализация через hash_hmac или другие KPHP-совместимые функции
}
```

---

## Ссылки

- [KPHP vs Shared — нюансы и альтернативы](./kphp-vs-shared.md)
- [Документация KPHP vs PHP](https://vkcom.github.io/kphp/kphp-language/kphp-vs-php/whats-the-difference.html)
- [KPHP builtin functions](https://vkcom.github.io/kphp/kphp-language/static-type-system/kphp-type-system.html)
- [lphenom/auth: quickstart](./quickstart.md)
- [lphenom/auth: bearer tokens](./bearer-tokens.md)
- [lphenom/auth: security](./security.md)

