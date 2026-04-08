# KPHP vs Shared — Нюансы и альтернативы

Этот документ детально описывает различия между двумя режимами сборки
`lphenom/auth`: **shared** (PHP runtime) и **kphp** (нативный бинарник),
все пары альтернативных реализаций и нюансы, которые нужно учитывать при
написании KPHP-совместимого кода.

---

## Содержание

1. [Почему два режима](#1-почему-два-режима)
2. [Совместимость данных из коробки](#2-совместимость-данных-из-коробки)
3. [Что недоступно в KPHP](#3-что-недоступно-в-kphp)
4. [Таблица реализаций](#4-таблица-реализаций)
5. [Детали реализаций](#5-детали-реализаций)
   - [PasswordHasher](#51-passwordhasher)
   - [EmailSender](#52-emailsender)
6. [Классы shared,kphp — универсальные](#6-классы-sharedkphp--универсальные)
7. [Как переключается режим](#7-как-переключается-режим)
8. [Правила написания KPHP-совместимого кода](#8-правила-написания-kphp-совместимого-кода)
9. [Нюансы и ловушки](#9-нюансы-и-ловушки)

---

## 1. Почему два режима

LPhenom поддерживает компиляцию через [vkcom/kphp](https://github.com/VKCOM/kphp) —
транспилятор PHP → C++, дающий нативный бинарник без PHP-рантайма. Это даёт
прирост производительности и меньший footprint для production-деплоя.

Проблема: KPHP поддерживает **не все функции PHP** и ряд низкоуровневых
операций (SMTP-сокеты, `password_hash`, `PDO`) в KPHP отсутствует.

Решение: все классы `lphenom/auth` написаны с учётом KPHP-ограничений и
помечены `@lphenom-build shared,kphp` — они работают в обоих режимах без
каких-либо изменений. Там, где PHP-функция недоступна в KPHP, используется
KPHP-совместимая альтернатива:

| Аннотация | Режим | Описание |
|---|---|---|
| `@lphenom-build shared,kphp` | **Оба** | Единственный тип аннотаций в `lphenom/auth` |

---

## 2. Совместимость данных из коробки

> **TL;DR:** Перенос базы данных с shared PHAR на KPHP бинарь работает
> **автоматически, без каких-либо действий**.

Конечный пользователь (не разработчик) просто:
1. Устанавливает shared PHAR на shared-хостинге
2. Принимает решение перейти на VDS с KPHP бинарём
3. Переносит базу данных
4. Запускает KPHP бинарь — все пароли, токены и данные работают ✅

Это возможно потому, что **оба режима по умолчанию используют одни и те же
алгоритмы хеширования**:

| Данные | Алгоритм | shared | kphp | Совместимость |
|---|---|:---:|:---:|:---:|
| Пароли | PBKDF2-HMAC-SHA256 (`CryptPasswordHasher`) | ✅ | ✅ | ✅ **из коробки** |
| Токены (секрет) | SHA-256 (`OpaqueTokenEncoder`) | ✅ | ✅ | ✅ **из коробки** |
| SMS-коды | SHA-256 (`SmsCodeAuthenticator`) | ✅ | ✅ | ✅ **из коробки** |
| Email-коды | SHA-256 (`EmailCodeAuthenticator`) | ✅ | ✅ | ✅ **из коробки** |

`CryptPasswordHasher` помечен `@lphenom-build shared,kphp` — он использует
`hash_hmac('sha256', ...)`, `random_bytes()`, `bin2hex()`, которые доступны
как в PHP, так и в KPHP.

---

## 3. Что недоступно в KPHP

### Функции хеширования паролей

| Функция PHP | Статус в KPHP | Альтернатива |
|---|---|---|
| `password_hash()` | ❌ Отсутствует | `hash_hmac('sha256', ...)` в цикле (`CryptPasswordHasher`) |
| `password_verify()` | ❌ Отсутствует | `hash_equals()` + ручная деривация |
| `password_needs_rehash()` | ❌ Отсутствует | Ручная проверка префикса строки |

### Сетевые функции

| Функция PHP | Статус в KPHP | Альтернатива |
|---|---|---|
| `fsockopen()` | ❌ Отсутствует | `file_get_contents()` + HTTP context |
| `stream_socket_enable_crypto()` | ❌ Отсутствует | Использовать HTTPS API |
| `socket_create()` | ❌ Отсутствует | `file_get_contents()` + HTTP context |
| `file_get_contents()` с HTTP | ✅ Работает | — |
| `stream_context_create()` | ✅ Работает | — |

### База данных

| Класс / функция PHP | Статус в KPHP | Альтернатива |
|---|---|---|
| `PDO` / `PDOStatement` | ❌ Отсутствует | `FfiMySqlConnection` из `lphenom/db` |
| `mysqli_*` | ❌ Отсутствует | `FfiMySqlConnection` из `lphenom/db` |
| `ConnectionInterface` | ✅ Работает | Интерфейс — реализация через FFI |

### Динамические конструкции языка

| Конструкция | Статус в KPHP | Альтернатива |
|---|---|---|
| `new $className()` (динамический `new`) | ❌ Запрещён | Явный `new ClassName()` |
| `call_user_func()` | ❌ Запрещён | Интерфейс с методом |
| `Closure` как тип параметра | ❌ Запрещён | Интерфейс / callable-интерфейс |
| Reflection API | ❌ Отсутствует | Нет альтернативы, избегать |
| `match` expression (старые KPHP) | ⚠️ Только в новых версиях | `if/elseif/else` |
| `str_starts_with()` (PHP 8.0) | ⚠️ Зависит от версии KPHP | `substr(...)` + `===` |
| Именованные аргументы (PHP 8.0) | ⚠️ Зависит от версии KPHP | Позиционные аргументы |
| Union types `int\|string` | ⚠️ Ограниченно | Отдельные параметры / `mixed` |

### Что в KPHP работает нормально

- `random_bytes()`, `bin2hex()`, `hex2bin()` ✅
- `hash()`, `hash_hmac()`, `hash_equals()` ✅
- `file_get_contents()` с HTTP stream context ✅
- `stream_context_create()` ✅
- `base64_encode()` / `base64_decode()` ✅
- `json_encode()` / `json_decode()` ✅
- `http_build_query()` ✅
- Интерфейсы и их полиморфизм ✅
- Статические методы и свойства ✅
- Nullable типы (`?string`) ✅
- `array<K, V>` с аннотациями ✅

---

## 4. Таблица реализаций

Все классы `lphenom/auth` работают в обоих режимах:

| Интерфейс | Реализация | Аннотация | Почему KPHP-совместима |
|---|---|---|---|
| `PasswordHasherInterface` | `CryptPasswordHasher` | **shared,kphp** | `hash_hmac`/`random_bytes`/`bin2hex` — есть в KPHP |
| `CodeSenderInterface` (email) | `UniSenderEmailSender` | **shared,kphp** | `file_get_contents` + HTTP context — есть в KPHP |
| `CodeSenderInterface` (SMS) | `MirSmsSender` | **shared,kphp** | `file_get_contents` + HTTP context — есть в KPHP |

> **Ключевое:** нет разделения на shared-only и kphp-only классы.
> Один код — оба режима.

### Классы, работающие в обоих режимах (shared,kphp)

| Класс | Причина совместимости |
|---|---|
| `CryptPasswordHasher` | `hash_hmac`, `random_bytes`, `bin2hex` — всё работает в KPHP |
| `OpaqueTokenEncoder` | Только `random_bytes`, `bin2hex`, `hash` |
| `DefaultAuthManager` | Зависит только от контрактов |
| `AuthContextStorage` | static-переменная, чистый PHP |
| `MemoryThrottle` | Массив в памяти, чистый PHP |
| `InMemoryTokenRepository` | Массив в памяти, чистый PHP |
| `CacheThrottle` | Зависит от `CacheInterface` (KPHP-совместим) |
| `LogAuditListener` | Зависит от `LoggerInterface` (KPHP-совместим) |
| `DbTokenRepository` | Зависит от `ConnectionInterface` (реализация через FFI) |
| `MirSmsSender` | Использует `file_get_contents` + `stream_context_create` |
| `SmsCodeAuthenticator` | Зависит от `CacheInterface` + `CodeSenderInterface` |
| `EmailCodeAuthenticator` | Зависит от `CacheInterface` + `CodeSenderInterface` |
| `HttpAuthBridge` | Зависит только от KPHP-совместимых компонентов |
| `BearerTokenGuard` | Зависит только от `AuthManagerInterface` |
| `RequireAuthMiddleware` | Зависит от `BearerTokenGuard` и `MiddlewareInterface` |
| `RequireRoleMiddleware` | Использует `in_array`, `AuthContextStorage` |
| `CreateAuthTokensTable` | Зависит от `ConnectionInterface` |
| `CreateAuthCodesTable` | Зависит от `ConnectionInterface` |

---

## 5. Детали реализаций

### 5.1 PasswordHasher

#### `CryptPasswordHasher` — `@lphenom-build shared,kphp` ✅ ЕДИНСТВЕННАЯ РЕАЛИЗАЦИЯ

```php
use LPhenom\Auth\Hashing\CryptPasswordHasher;

$hasher = new CryptPasswordHasher(iterations: 10000);
$hash   = $hasher->hash('секретный-пароль');    // "$lphenom$sha256$10000$<salt>$<hash>"
$ok     = $hasher->verify('секретный-пароль', $hash); // true
$rehash = $hasher->needsRehash($hash);           // false
```

**Внутренняя работа (PBKDF2-подобная схема):**
```
salt = bin2hex(random_bytes(16))   // 32 hex символа
h    = hash_hmac('sha256', salt, password)
for i in 1..iterations:
    h = hash_hmac('sha256', h, password)
result = h  // 64 hex символа (SHA-256)
```

**Формат хеша:** `$lphenom$sha256$<iterations>$<salt32hex>$<hash64hex>`

**Работает в:** PHP 8.1+ (`hash_hmac`, `hash_equals`, `random_bytes`, `bin2hex`) ✅
**Работает в:** KPHP (`hash_hmac`, `hash_equals`, `random_bytes`, `bin2hex`) ✅

**Результат:** база данных shared и kphp сборок **полностью совместима из коробки**.

---

### 5.2 EmailSender

#### `UniSenderEmailSender` — `@lphenom-build shared,kphp` ✅ ЕДИНСТВЕННАЯ РЕАЛИЗАЦИЯ

```php
use LPhenom\Auth\Support\EmailSender\UniSenderEmailSender;

$sender = new UniSenderEmailSender(
    'your-api-key',                  // UniSender API key
    'noreply@example.com',           // верифицированный sender email
    'MyApp',                         // sender name
    'Ваш код подтверждения'          // тема (опционально)
    // 5-й аргумент — $apiUrl — опционален, по умолчанию RU-эндпоинт
);
$ok = $sender->send('recipient@example.com', '123456');
```

POST-запрос через `@file_get_contents()` + `stream_context_create()` — KPHP-совместимо.
Оператор `@` подавляет PHP `E_WARNING` при сетевой ошибке; в KPHP — no-op.

Использует метод [`sendEmail`](https://www.unisender.com/ru/support/api/partners/sendemail/) UniSender API.

> **Примечание:** коды подтверждения хранятся в кеше (не в БД), поэтому
> смена конфигурации email при переходе shared→kphp не влияет на
> существующие данные в базе.

---

## 6. Классы shared,kphp — универсальные

Все классы `lphenom/auth` работают в обоих режимах без изменений.

### `CryptPasswordHasher` — хеширование паролей (рекомендуемый по умолчанию)

Описан выше в §5.1. Использует `hash_hmac`, `random_bytes`, `bin2hex` — всё
доступно как в PHP, так и в KPHP. **Обеспечивает совместимость БД из коробки.**

### `MirSmsSender` — отправка SMS

```php
$sender = new \LPhenom\Auth\Support\SmsSender\MirSmsSender(
    'https://api.mirsms.ru/message/send',
    'mylogin',
    'mypassword',
    'MyApp'
);
$ok = $sender->send('+79001234567', '654321');
```

### `UniSenderEmailSender` — отправка email через UniSender API

```php
$sender = new \LPhenom\Auth\Support\EmailSender\UniSenderEmailSender(
    'your-api-key',
    'noreply@example.com',
    'MyApp',
    'Ваш код подтверждения'
);
$ok = $sender->send('user@example.com', '654321');
```

### `CacheThrottle` — троттлинг через кеш

```php
$throttle = new CacheThrottle($cache);
$throttle->hit('login:user@example.com', 60);
$tooMany = $throttle->tooManyAttempts('login:user@example.com', 5);
$throttle->reset('login:user@example.com');
```

### `DbTokenRepository` — хранение токенов в БД

```php
// shared: PDO-драйвер
$conn = new \LPhenom\Db\Driver\PdoMySqlConnection($pdo);
// kphp: FFI MySQL-драйвер
$conn = new \LPhenom\Db\Driver\FfiMySqlConnection($host, $user, $pass, $db);

$tokenRepo = new \LPhenom\Auth\Support\DbTokenRepository($conn);
```

### `AuthContextStorage` — контекст запроса

```php
\LPhenom\Auth\Support\AuthContextStorage::set($ctx);
$ctx = \LPhenom\Auth\Support\AuthContextStorage::get();
\LPhenom\Auth\Support\AuthContextStorage::reset(); // ОБЯЗАТЕЛЬНО в начале каждого запроса в KPHP!
```

> **Важно для KPHP:** в скомпилированном бинарнике один процесс обрабатывает несколько
> запросов без перезапуска. Статические переменные **не сбрасываются** автоматически.
> Вызывайте `AuthContextStorage::reset()` в начале каждого запроса.

---

## 7. Как переключается режим

Поскольку все классы `lphenom/auth` работают в обоих режимах, конфигурация
**одинакова** для shared PHAR и KPHP бинарника — никакого `APP_BUILD` не нужно:

```php
// Одинаково для shared и kphp:
$passwordHasher = new \LPhenom\Auth\Hashing\CryptPasswordHasher(
    (int) ($_ENV['CRYPT_ITERATIONS'] ?? 10000)
);
$emailSender = new \LPhenom\Auth\Support\EmailSender\UniSenderEmailSender(
    $_ENV['UNISENDER_API_KEY'],
    $_ENV['UNISENDER_SENDER_EMAIL'],
    $_ENV['UNISENDER_SENDER_NAME'],
    $_ENV['UNISENDER_SUBJECT'] ?? 'Ваш код'
);

$authManager = new \LPhenom\Auth\Support\DefaultAuthManager(
    $userProvider,
    $passwordHasher,
    new \LPhenom\Auth\Tokens\OpaqueTokenEncoder(),
    $tokenRepo,
    $throttle,
    $auditListener,
    3600,
    5,
    60
);
```

### Принцип совместимости

```
                 ┌──────────────────────────────────────────┐
                 │           Contracts (интерфейсы)          │
                 │  PasswordHasherInterface                   │
                 │  CodeSenderInterface                       │
                 │  TokenRepositoryInterface  ...             │
                 └──────────────────┬───────────────────────┘
                                    │
          ┌─────────────────────────▼───────────────────────────────┐
          │          shared,kphp — одинаково в обоих режимах        │
          │                                                          │
          │  CryptPasswordHasher  ← хеш паролей (HMAC-SHA256)       │
          │  UniSenderEmailSender ← email коды (file_get_contents)   │
          │  OpaqueTokenEncoder   ← токены (random_bytes + hash)     │
          │  MirSmsSender, CacheThrottle, DbTokenRepository...       │
          └─────────────────────────────────────────────────────────┘
                                    ▲
          ┌─────────────────────────┴───────────────────────────────┐
          │       APP_BUILD не нужен — код одинаков                  │
          │       Билдер в lphenom/lphenom                           │
          └─────────────────────────────────────────────────────────┘
```

---

## 8. Правила написания KPHP-совместимого кода

### ✅ Разрешено

```php
/** @var array<string, int> $map */
$map = [];

public function findUser(?string $id): ?UserInterface { ... }
public function __construct(PasswordHasherInterface $hasher) { ... }

private static ?AuthContext $context = null;

// while / for вместо match (для старых версий KPHP)
$i = 0;
while ($i < $iterations) {
    $h = hash_hmac('sha256', $h, $password);
    $i++;
}
```

### ❌ Запрещено / нежелательно

```php
// ❌ Динамический new
$obj = new $class();

// ❌ Closure как тип / callable
public function withHook(callable $hook): void { ... }

// ❌ Reflection
$ref = new \ReflectionClass($obj);

// ❌ password_hash / password_verify — используйте CryptPasswordHasher
$hash = password_hash($plain, PASSWORD_BCRYPT);

// ❌ fsockopen — используйте file_get_contents + HTTP context
$sock = fsockopen('smtp.example.com', 587);

// ❌ PDO / mysqli — используйте ConnectionInterface + FFI-драйвер
$pdo = new PDO('mysql:...');
```

---

## 9. Нюансы и ловушки

### Нюанс 1: Статические переменные в KPHP не сбрасываются между запросами

В PHP-FPM каждый запрос — отдельный процесс, статические переменные сбрасываются
автоматически. В KPHP-бинарнике один воркер обслуживает несколько запросов подряд.

```php
// В точке входа KPHP-бинарника (перед обработкой запроса):
\LPhenom\Auth\Support\AuthContextStorage::reset();
```

---

### Нюанс 2: Совместимость данных shared ↔ kphp

При использовании `CryptPasswordHasher` по умолчанию — совместимость
**гарантирована из коробки**. Подробности в [§2](#2-совместимость-данных-из-коробки).

---

### Нюанс 3: Email через UniSender API

`UniSenderEmailSender` работает через `@file_get_contents()` — KPHP-совместимо.
Это единственная реализация для обоих режимов. Никакой смены конфига при
переходе shared↔kphp не требуется.

---

### Нюанс 4: Тайм-аут HTTP-запросов в KPHP

`MirSmsSender` и `UniSenderEmailSender` используют `@file_get_contents` с тайм-аутом
10 секунд. В KPHP `file_get_contents` блокирует воркер. Используйте быстрые API (< 2 сек).

---

### Нюанс 5: lphenom/db — FFI-драйвер в KPHP

`DbTokenRepository` в KPHP использует `FfiMySqlConnection`:

```php
$conn = new \LPhenom\Db\Driver\FfiMySqlConnection('127.0.0.1', 'user', 'pass', 'db', 3306);
$tokenRepo = new \LPhenom\Auth\Support\DbTokenRepository($conn);
```

---

### Нюанс 6: `CryptPasswordHasher` — безопасность

| Аспект | `CryptPasswordHasher` |
|---|---|
| Алгоритм | PBKDF2-HMAC-SHA256 |
| Устойчивость к GPU | ⚠️ Средняя (увеличьте iterations для компенсации) |
| Рекомендуемые параметры (2026) | `iterations=200000` |
| Режимы | **shared + kphp** ✅ |
| Совместимость БД | **✅ да** |

> Увеличьте `iterations` до `200000`+ для повышения безопасности в production.

---

## Ссылки

- [KPHP documentation](https://vkcom.github.io/kphp/)
- [KPHP vs PHP — отличия](https://vkcom.github.io/kphp/kphp-language/kphp-vs-php/whats-the-difference.html)
- [lphenom/auth: build-targets](./build-targets.md)
- [lphenom/auth: quickstart](./quickstart.md)
- [lphenom/auth: bearer tokens](./bearer-tokens.md)
- [lphenom/auth: security](./security.md)

