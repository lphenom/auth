# KPHP vs Shared — Нюансы и альтернативы

Этот документ детально описывает различия между двумя режимами сборки
`lphenom/auth`: **shared** (PHP runtime) и **kphp** (нативный бинарник),
все пары альтернативных реализаций и нюансы, которые нужно учитывать при
написании KPHP-совместимого кода.

---

## Содержание

1. [Почему два режима](#1-почему-два-режима)
2. [Что недоступно в KPHP](#2-что-недоступно-в-kphp)
3. [Таблица альтернативных реализаций](#3-таблица-альтернативных-реализаций)
4. [Детали каждой пары](#4-детали-каждой-пары)
   - [PasswordHasher](#41-passwordhasher)
   - [EmailSender](#42-emailsender)
5. [Классы shared,kphp — универсальные](#5-классы-sharedkphp--универсальные)
6. [Как переключается режим](#6-как-переключается-режим)
7. [Правила написания KPHP-совместимого кода](#7-правила-написания-kphp-совместимого-кода)
8. [Нюансы и ловушки](#8-нюансы-и-ловушки)

---

## 1. Почему два режима

LPhenom поддерживает компиляцию через [vkcom/kphp](https://github.com/VKCOM/kphp) —
транспилятор PHP → C++, дающий нативный бинарник без PHP-рантайма. Это даёт
прирост производительности и меньший footprint для production-деплоя.

Проблема: KPHP поддерживает **не все функции PHP** и ряд низкоуровневых
операций (SMTP-сокеты, `password_hash`, `PDO`) в KPHP отсутствует.

Решение: для каждой такой операции есть **два класса**:

| Аннотация | Режим | Описание |
|---|---|---|
| `@lphenom-build shared` | PHP runtime | Использует все возможности PHP 8.1+ |
| `@lphenom-build kphp` | KPHP binary | Использует только KPHP-совместимые функции |
| `@lphenom-build shared,kphp` | Оба | Один класс, работающий в обоих режимах |

Оба класса одной пары реализуют **один и тот же интерфейс** — приложение
не знает, какой класс используется.

---

## 2. Что недоступно в KPHP

### Функции хеширования паролей

| Функция PHP | Статус в KPHP | Альтернатива |
|---|---|---|
| `password_hash()` | ❌ Отсутствует | `hash_hmac('sha256', ...)` в цикле |
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

## 3. Таблица альтернативных реализаций

| Интерфейс | Реализация `shared` | Реализация `kphp` | Причина разделения |
|---|---|---|---|
| `PasswordHasherInterface` | `BcryptPasswordHasher` | `CryptPasswordHasher` | `password_hash()` недоступна в KPHP |
| `CodeSenderInterface` (email) | `SmtpEmailSender` | `KphpHttpEmailSender` | `fsockopen()` / TLS-сокет недоступны в KPHP |

### Классы, работающие в обоих режимах (shared,kphp)

| Класс | Причина совместимости |
|---|---|
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

## 4. Детали каждой пары

### 4.1 PasswordHasher

#### `BcryptPasswordHasher` — `@lphenom-build shared`

```php
use LPhenom\Auth\Hashing\BcryptPasswordHasher;

$hasher = new BcryptPasswordHasher(cost: 10);
$hash   = $hasher->hash('секретный-пароль');    // "$2y$10$..."
$ok     = $hasher->verify('секретный-пароль', $hash); // true
$rehash = $hasher->needsRehash($hash);           // false
```

**Внутренняя работа:**
- `hash()` → `password_hash($plain, PASSWORD_BCRYPT, ['cost' => $cost])`
- `verify()` → `password_verify($plain, $hash)`
- `needsRehash()` → `password_needs_rehash($hash, PASSWORD_BCRYPT, ['cost' => $cost])`

**Формат хеша:** `$2y$10$<22-char-salt><31-char-hash>` (стандартный bcrypt)

**Не работает в KPHP:** `password_hash` / `password_verify` отсутствуют в KPHP runtime.

---

#### `CryptPasswordHasher` — `@lphenom-build kphp`

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

Пример: `$lphenom$sha256$10000$a3f1...8b$c9d2...1e`

**Работает в KPHP:** `hash_hmac`, `hash_equals`, `random_bytes`, `bin2hex` — все поддерживаются.

---

#### ⚠️ Несовместимость хешей — КРИТИЧЕСКИЙ НЮАНС

> **Хеши `BcryptPasswordHasher` и `CryptPasswordHasher` НЕ взаимозаменяемы!**

Bcrypt хеш: `$2y$10$...`
Lphenom хеш: `$lphenom$sha256$...`

При переходе `shared` → `kphp` (или обратно) **все пользователи должны сбросить пароль**.

**Стратегия миграции:**
1. Смена режима → принудительный logout всех активных сессий
2. Отзыв всех выданных токенов (`tokenRepo->revokeAllForUser(...)`)
3. Email всем пользователям: "Обновите пароль по ссылке"
4. При первом входе с новым паролем — хеш пересоздаётся в нужном формате

**Пример проверки совместимости при чтении хеша:**
```php
// Определить тип хеша по префиксу:
if (str_starts_with($hash, '$2y$') || str_starts_with($hash, '$2b$')) {
    // Это bcrypt (shared-режим)
} elseif (str_starts_with($hash, '$lphenom$sha256$')) {
    // Это CryptPasswordHasher (kphp-режим)
}
```

---

### 4.2 EmailSender

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
    encryption: 'tls'     // 'tls', 'ssl' или ''
);
$ok = $sender->send('recipient@example.com', '123456');
```

**Как работает:**
1. `fsockopen($host, $port)` — открывает TCP-сокет
2. `EHLO localhost` → `STARTTLS` → `stream_socket_enable_crypto(TLS)` → `AUTH LOGIN`
3. `MAIL FROM` → `RCPT TO` → `DATA` → письмо → `QUIT`

**Поддерживает:** TLS (STARTTLS на 587), SSL (465), plain (25), SMTP AUTH

**Не работает в KPHP:** `fsockopen()` и `stream_socket_enable_crypto()` не реализованы.

---

#### `KphpHttpEmailSender` — `@lphenom-build kphp`

```php
use LPhenom\Auth\Support\EmailSender\KphpHttpEmailSender;

$sender = new KphpHttpEmailSender(
    apiUrl: 'https://api.mailgun.net/v3/example.com/messages',
    apiKey: 'key-xxxxxxxxxxxxxxx',
    fromEmail: 'noreply@example.com',
    subject: 'Ваш код подтверждения'
);
$ok = $sender->send('recipient@example.com', '123456');
```

**Как работает:**
```
POST $apiUrl
Authorization: Bearer $apiKey
Content-Type: application/x-www-form-urlencoded

to=recipient@example.com&from=noreply@example.com&subject=...&text=Ваш код: 123456
```

**Совместимые HTTP Email API:**

| Провайдер | API URL | Аутентификация |
|---|---|---|
| Mailgun | `https://api.mailgun.net/v3/{domain}/messages` | `Bearer key-xxx` |
| SendGrid | `https://api.sendgrid.com/v3/mail/send` | `Bearer SG.xxx` |
| Mailersend | `https://api.mailersend.com/v1/email` | `Bearer xxx` |
| Кастомный | Любой HTTPS POST эндпоинт | По договорённости |

**Работает в KPHP:** `file_get_contents()` с HTTP stream context + `stream_context_create()` — полностью поддерживаются.

---

#### ⚠️ Нюансы EmailSender при смене режима

1. **Нужен HTTP API для email в KPHP** — если раньше использовался корпоративный SMTP,
   нужно завести аккаунт у Mailgun/SendGrid или поднять собственный HTTP-proxy
2. **Формат POST-запроса** — `KphpHttpEmailSender` отправляет `application/x-www-form-urlencoded`,
   у некоторых API нужен `application/json`. В этом случае — создайте свою реализацию `CodeSenderInterface`.
3. **Тайм-аут** зафиксирован в `10` секунд через `stream_context_create` — для медленных API увеличьте его.

---

## 5. Классы shared,kphp — универсальные

Эти классы **не требуют альтернатив** — они работают в обоих режимах:

### `MirSmsSender` — отправка SMS

```php
// Одинаковый код в shared И в kphp:
$sender = new \LPhenom\Auth\Support\SmsSender\MirSmsSender(
    apiUrl: 'https://api.mirsms.ru/message/send',
    login: 'mylogin',
    password: 'mypassword',
    sender: 'MyApp'
);
$ok = $sender->send('+79001234567', '654321');
```

Использует `file_get_contents()` с POST-контекстом — работает в KPHP.

---

### `CacheThrottle` — троттлинг через кеш

```php
use LPhenom\Cache\Driver\InMemoryCache;  // или RedisCache в продакшне
use LPhenom\Auth\Support\CacheThrottle;

$throttle = new CacheThrottle($cache);

$throttle->hit('login:user@example.com', decaySeconds: 60);
$tooMany = $throttle->tooManyAttempts('login:user@example.com', maxAttempts: 5);
$throttle->reset('login:user@example.com');
```

`lphenom/cache` (`CacheInterface`) поддерживается в KPHP через FFI Redis-драйвер.

---

### `DbTokenRepository` — хранение токенов в БД

```php
// shared (PHP): PDO-драйвер
$conn = new \LPhenom\Db\Driver\PdoMySqlConnection($pdo);

// kphp: FFI MySQL-драйвер
$conn = new \LPhenom\Db\Driver\FfiMySqlConnection($host, $user, $pass, $db);

// В обоих случаях — одинаково:
$tokenRepo = new \LPhenom\Auth\Support\DbTokenRepository($conn);
```

Класс `DbTokenRepository` использует только `ConnectionInterface` + `Param` — совместим с KPHP.

---

### `AuthContextStorage` — контекст запроса

```php
// Хранит AuthContext в static-переменной — работает в KPHP
\LPhenom\Auth\Support\AuthContextStorage::set($ctx);
$ctx = \LPhenom\Auth\Support\AuthContextStorage::get();
\LPhenom\Auth\Support\AuthContextStorage::reset(); // ОБЯЗАТЕЛЬНО вызывать в начале каждого запроса в KPHP!
```

> **Важно для KPHP:** в скомпилированном бинарнике один процесс обрабатывает несколько запросов
> без перезапуска. Статические переменные **не сбрасываются** между запросами автоматически.
> Всегда вызывайте `AuthContextStorage::reset()` в начале обработки каждого запроса.

---

## 6. Как переключается режим

Переключение между `shared` и `kphp` реализациями происходит в **точке сборки приложения**.
Сам билдер находится в `lphenom/lphenom` (основной скелетон проекта).

Концептуально выбор реализации происходит так:

```php
// В bootstrap/container-сборщике приложения:
$mode = $_ENV['APP_BUILD'] ?? 'shared'; // 'shared' или 'kphp'

if ($mode === 'kphp') {
    // KPHP-реализации:
    $passwordHasher = new \LPhenom\Auth\Hashing\CryptPasswordHasher(
        iterations: (int)($_ENV['CRYPT_ITERATIONS'] ?? 10000)
    );
    $emailSender = new \LPhenom\Auth\Support\EmailSender\KphpHttpEmailSender(
        apiUrl:    $_ENV['EMAIL_API_URL'],
        apiKey:    $_ENV['EMAIL_API_KEY'],
        fromEmail: $_ENV['EMAIL_FROM'],
        subject:   $_ENV['EMAIL_SUBJECT'] ?? 'Ваш код подтверждения'
    );
} else {
    // Shared-реализации:
    $passwordHasher = new \LPhenom\Auth\Hashing\BcryptPasswordHasher(
        cost: (int)($_ENV['BCRYPT_COST'] ?? 10)
    );
    $emailSender = new \LPhenom\Auth\Support\EmailSender\SmtpEmailSender(
        host:       $_ENV['SMTP_HOST'],
        port:       (int)$_ENV['SMTP_PORT'],
        username:   $_ENV['SMTP_USER'],
        password:   $_ENV['SMTP_PASS'],
        fromEmail:  $_ENV['EMAIL_FROM'],
        fromName:   $_ENV['EMAIL_FROM_NAME'],
        encryption: $_ENV['SMTP_ENCRYPTION'] ?? 'tls'
    );
}

// Остальной код не меняется — интерфейс один:
$authManager = new \LPhenom\Auth\Support\DefaultAuthManager(
    userProvider:  $userProvider,
    hasher:        $passwordHasher,   // PasswordHasherInterface
    tokenEncoder:  new \LPhenom\Auth\Tokens\OpaqueTokenEncoder(),
    tokenRepo:     $tokenRepo,
    throttle:      $throttle,
    audit:         $auditListener,
    tokenTtl:      3600,
    maxAttempts:   5,
    throttleDecay: 60
);
```

### Принцип

```
                 ┌──────────────────────────────────────────┐
                 │           Contracts (интерфейсы)          │
                 │  PasswordHasherInterface                   │
                 │  CodeSenderInterface                       │
                 │  TokenRepositoryInterface                  │
                 │  ...                                       │
                 └──────────────┬──────────────┬─────────────┘
                                │              │
            ┌───────────────────▼──┐    ┌──────▼──────────────────┐
            │   shared реализации  │    │   kphp реализации        │
            │                      │    │                           │
            │ BcryptPasswordHasher │    │ CryptPasswordHasher       │
            │ SmtpEmailSender      │    │ KphpHttpEmailSender       │
            └──────────────────────┘    └───────────────────────────┘
                       ▲                             ▲
            ┌──────────┴──────────────────────────────┴──────────┐
            │              APP_BUILD=shared|kphp                  │
            │          Билдер в lphenom/lphenom                   │
            └──────────────────────────────────────────────────────┘
```

---

## 7. Правила написания KPHP-совместимого кода

Правила, которым следуют все файлы с `@lphenom-build kphp` или `@lphenom-build shared,kphp`:

### ✅ Разрешено

```php
// Явные типы в DocBlock
/** @var array<string, int> $map */
$map = [];

// Nullable типы
public function findUser(?string $id): ?UserInterface { ... }

// Интерфейсы и полиморфизм
public function __construct(PasswordHasherInterface $hasher) { ... }

// Статические методы и свойства
private static ?AuthContext $context = null;

// Стандартные функции: random_bytes, bin2hex, hash, hash_hmac, hash_equals
// json_encode/decode, http_build_query, file_get_contents, stream_context_create
// base64_encode/decode, strlen, substr, explode, implode, in_array, count

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
$class = 'SomeClass';
$obj = new $class(); // KPHP не поддерживает

// ❌ Closure как тип параметра
public function withHook(callable $hook): void { ... }
// Замена: interface HookInterface { public function run(): void; }

// ❌ Reflection
$ref = new \ReflectionClass($obj); // Отсутствует в KPHP

// ❌ call_user_func / call_user_func_array
call_user_func([$this, 'method']); // Не поддерживается

// ❌ password_hash / password_verify
$hash = password_hash($plain, PASSWORD_BCRYPT); // Нет в KPHP

// ❌ fsockopen
$sock = fsockopen('smtp.example.com', 587); // Нет в KPHP

// ❌ PDO / mysqli
$pdo = new PDO('mysql:...'); // Используйте ConnectionInterface + FFI-драйвер
```

### Аннотации для KPHP

```php
/**
 * Массив с явными типами — обязательно
 * @var array<string, string> $params
 */
$params = [];

/**
 * Nullable возврат — явно
 * @return ?TokenRecord
 */
public function findByTokenId(string $id): ?TokenRecord { ... }
```

---

## 8. Нюансы и ловушки

### Нюанс 1: Статические переменные в KPHP не сбрасываются между запросами

В PHP-FPM каждый запрос — отдельный процесс, статические переменные сбрасываются
автоматически. В KPHP-бинарнике один воркер обслуживает несколько запросов подряд.

**Затронутые классы:**
- `AuthContextStorage` — нужен явный вызов `::reset()` в начале каждого запроса

```php
// В точке входа KPHP-бинарника (перед обработкой запроса):
\LPhenom\Auth\Support\AuthContextStorage::reset();
```

---

### Нюанс 2: Несовместимость хешей паролей

Уже описана в [§4.1](#41-passwordhasher). Не забудьте: при смене режима
пользователи должны сбросить пароли.

---

### Нюанс 3: Email через HTTP API vs SMTP — не одно и то же

При использовании `KphpHttpEmailSender` письмо отправляется через сторонний HTTP API
(Mailgun, SendGrid, etc.). Это означает:
- Зависимость от третьего сервиса (нужен аккаунт и API-ключ)
- Письма могут попасть в спам иначе, чем с собственного SMTP
- Нужна настройка DNS: SPF, DKIM, DMARC через панель провайдера

---

### Нюанс 4: Тайм-аут HTTP-запросов в KPHP

`MirSmsSender` и `KphpHttpEmailSender` используют `file_get_contents` с тайм-аутом 10 секунд.
В KPHP нет встроенного async-IO для `file_get_contents`, поэтому долгий запрос
**блокирует весь воркер**. Рекомендации:
- Используйте быстрые API (< 1-2 сек)
- Ставьте SMS/email задачи в очередь через `lphenom/queue` (если доступно)

---

### Нюанс 5: lphenom/db — FFI-драйвер требует компиляции

`DbTokenRepository` в KPHP использует `FfiMySqlConnection`, который:
- Требует наличия `libmysqlclient` или `libmariadb` на сервере
- Компилируется вместе с KPHP-бинарником через FFI
- Конфигурируется иначе, чем PDO (строка подключения, не DSN)

```php
// Пример конфигурации в KPHP:
$conn = new \LPhenom\Db\Driver\FfiMySqlConnection(
    host: '127.0.0.1',
    user: 'dbuser',
    password: 'dbpassword',
    database: 'myapp',
    port: 3306
);
```

---

### Нюанс 6: Функция hash_hmac vs password_hash — сравнение безопасности

| Аспект | `BcryptPasswordHasher` | `CryptPasswordHasher` |
|---|---|---|
| Алгоритм | bcrypt | PBKDF2-HMAC-SHA256 |
| Устойчивость к GPU-перебору | ✅ Высокая (bcrypt специально медленный) | ⚠️ Средняя (HMAC-SHA256 быстрее на GPU) |
| Параметр стоимости | `cost` (2–31) | `iterations` (100–1 000 000) |
| Рекомендуемый cost/iterations | `cost=12` (2026) | `iterations=200000` (2026) |
| Длина соли | 16 байт | 16 байт |
| Длина хеша в БД | ~60 символов | ~104 символа |

> **Совет по безопасности:** если возможно, используйте `shared`-режим с `BcryptPasswordHasher`
> для сервисов с хранением паролей в БД. Bcrypt более устойчив к GPU-атакам.
> Для KPHP увеличьте `iterations` до `200000`+.

---

## Ссылки

- [KPHP documentation](https://vkcom.github.io/kphp/)
- [KPHP vs PHP — отличия](https://vkcom.github.io/kphp/kphp-language/kphp-vs-php/whats-the-difference.html)
- [lphenom/auth: build-targets](./build-targets.md)
- [lphenom/auth: quickstart](./quickstart.md)
- [lphenom/auth: bearer tokens](./bearer-tokens.md)
- [lphenom/auth: security](./security.md)

