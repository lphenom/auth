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

> Все файлы `lphenom/auth` имеют аннотацию `@lphenom-build shared,kphp`.
> Отдельных `shared`-only или `kphp`-only реализаций нет — один код для обоих режимов.

---

## Таблица реализаций

Все классы в `lphenom/auth` работают в обоих режимах:

| Интерфейс | Реализация | Почему совместима |
|---|---|---|
| `PasswordHasherInterface` | `CryptPasswordHasher` (**shared,kphp**) | `hash_hmac`/`random_bytes`/`bin2hex` — всё работает в KPHP |
| `CodeSenderInterface` (email) | `UniSenderEmailSender` (**shared,kphp**) | `file_get_contents` + HTTP context — KPHP-совместимо |
| `CodeSenderInterface` (SMS) | `MirSmsSender` (**shared,kphp**) | `file_get_contents` + HTTP context — KPHP-совместимо |

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

#### `CryptPasswordHasher` — `@lphenom-build shared,kphp` ✅ ЕДИНСТВЕННАЯ РЕАЛИЗАЦИЯ

```php
use LPhenom\Auth\Hashing\CryptPasswordHasher;

$hasher = new CryptPasswordHasher(iterations: 10000);
$hash   = $hasher->hash('my-password');    // $lphenom$sha256$10000$<salt>$<hash>
$ok     = $hasher->verify('my-password', $hash); // true
```

- Итеративный `hash_hmac('sha256', ...)` — упрощённый PBKDF2
- Формат хеша: `$lphenom$sha256$<iterations>$<salt32hex>$<hash64hex>`
- Работает в PHP 8.1+ **и** KPHP (`hash_hmac`, `hash_equals`, `random_bytes`, `bin2hex`)
- База данных shared ↔ kphp совместима **из коробки** — оба режима используют один формат

---

### `CodeSenderInterface` (Email)

#### `UniSenderEmailSender` — `@lphenom-build shared,kphp` ✅ ЕДИНСТВЕННАЯ РЕАЛИЗАЦИЯ

```php
use LPhenom\Auth\Support\EmailSender\UniSenderEmailSender;

$sender = new UniSenderEmailSender(
    'your-api-key',
    'noreply@example.com',
    'MyApp',
    'Ваш код подтверждения'
);
$sender->send('user@example.com', '123456');
```

- POST-запрос через `@file_get_contents()` + `stream_context_create()`
- Использует UniSender [`sendEmail`](https://www.unisender.com/ru/support/api/partners/sendemail/) API
- Работает в PHP 8.1+ **и** KPHP (`file_get_contents` с HTTP context поддерживается KPHP)
- Оператор `@` подавляет PHP `E_WARNING` при сетевой ошибке; в KPHP — no-op

---

## Как переключается реализация: конфиг

Поскольку все классы `lphenom/auth` работают в обоих режимах, конфигурация
**одинакова** для shared и kphp сборок:

```php
// config/auth.php
$passwordHasher = new \LPhenom\Auth\Hashing\CryptPasswordHasher(
    (int) ($_ENV['CRYPT_ITERATIONS'] ?? 10000)
);
$emailSender = new \LPhenom\Auth\Support\EmailSender\UniSenderEmailSender(
    $_ENV['UNISENDER_API_KEY'],
    $_ENV['UNISENDER_SENDER_EMAIL'],
    $_ENV['UNISENDER_SENDER_NAME'],
    $_ENV['UNISENDER_SUBJECT'] ?? 'Ваш код подтверждения'
);

// Одинаково для shared и kphp:
$authManager = new \LPhenom\Auth\Support\DefaultAuthManager(
    $userProvider,
    $passwordHasher,
    new \LPhenom\Auth\Tokens\OpaqueTokenEncoder(),
    $tokenRepo,
    $throttle,
    $auditListener,
    (int) ($_ENV['TOKEN_TTL'] ?? 86400),
    5,
    60
);
```

```dotenv
# Одни и те же переменные для shared и kphp
CRYPT_ITERATIONS=10000
EMAIL_API_URL=https://api.mailgun.net/v3/example.com/messages
EMAIL_API_KEY=key-xxxxxxxxxxxxxxx
EMAIL_FROM=noreply@example.com
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
# Одинаково для shared и kphp — никакого APP_BUILD не нужно
CRYPT_ITERATIONS=10000
TOKEN_TTL=86400

# UniSender API
UNISENDER_API_KEY=your-api-key
UNISENDER_SENDER_EMAIL=noreply@example.com
UNISENDER_SENDER_NAME=MyApp
UNISENDER_SUBJECT=Ваш код подтверждения
```

---

## Добавление собственной реализации

Чтобы добавить новую KPHP-совместимую реализацию интерфейса:

1. Создайте класс, реализующий нужный интерфейс
2. Пометьте `@lphenom-build shared,kphp`
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
 * @lphenom-build shared,kphp
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

