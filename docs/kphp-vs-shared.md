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
4. [Таблица альтернативных реализаций](#4-таблица-альтернативных-реализаций)
5. [Детали каждой пары](#5-детали-каждой-пары)
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

Решение: для каждой такой операции есть **два класса**:

| Аннотация | Режим | Описание |
|---|---|---|
| `@lphenom-build shared` | PHP runtime | Использует все возможности PHP 8.1+ |
| `@lphenom-build kphp` | KPHP binary | Использует только KPHP-совместимые функции |
| `@lphenom-build shared,kphp` | **Оба** | Один класс, работающий в обоих режимах |

Оба класса одной пары реализуют **один и тот же интерфейс** — приложение
не знает, какой класс используется.

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

### Единственное исключение: `BcryptPasswordHasher`

`BcryptPasswordHasher` (`@lphenom-build shared`) использует `password_hash()`,
которая **недоступна в KPHP**. Этот хешер — опциональная альтернатива
для развёртываний, которые:
- Работают **только** на PHP shared-хостинге
- **Никогда** не планируют переходить на KPHP

Если использовали `BcryptPasswordHasher` и решили перейти на KPHP —
см. раздел [5.1 PasswordHasher: миграция bcrypt → lphenom](#51-passwordhasher).

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

## 4. Таблица альтернативных реализаций

| Интерфейс | Реализация по умолчанию | Shared-альтернатива | Причина |
|---|---|---|---|
| `PasswordHasherInterface` | `CryptPasswordHasher` (**shared,kphp**) | `BcryptPasswordHasher` (shared only) | bcrypt (`password_hash`) недоступен в KPHP |
| `CodeSenderInterface` (email) | `KphpHttpEmailSender` (**kphp**) / `SmtpEmailSender` (**shared**) | — | `fsockopen` / TLS-сокет недоступны в KPHP |

> **Ключевое:** `CryptPasswordHasher` — **единственный рекомендуемый хешер паролей
> для обоих режимов**. `BcryptPasswordHasher` — опция только если вы точно знаете,
> что никогда не будете использовать KPHP.

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

## 5. Детали каждой пары

### 5.1 PasswordHasher

#### `CryptPasswordHasher` — `@lphenom-build shared,kphp` ✅ РЕКОМЕНДУЕТСЯ

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

**Результат:** база данных shared и kphp сборок **полностью совместима**.

---

#### `BcryptPasswordHasher` — `@lphenom-build shared` (опциональная альтернатива)

```php
use LPhenom\Auth\Hashing\BcryptPasswordHasher;

$hasher = new BcryptPasswordHasher(cost: 10);
$hash   = $hasher->hash('секретный-пароль');    // "$2y$10$..."
$ok     = $hasher->verify('секретный-пароль', $hash); // true
```

**Используйте только если:**
- Приложение работает исключительно в shared PHP режиме
- Переход на KPHP **не планируется**
- Требования безопасности настаивают на bcrypt (более устойчив к GPU-атакам, чем PBKDF2-HMAC-SHA256 с низким числом итераций)

**Не работает в KPHP:** `password_hash()` / `password_verify()` отсутствуют в KPHP runtime.

---

#### Миграция bcrypt → lphenom (если `BcryptPasswordHasher` уже использовался)

Если вы **уже запустили** приложение с `BcryptPasswordHasher` и хотите перейти
на KPHP, используйте `CompatPasswordHasher` (`@lphenom-build shared`) как
промежуточный шаг:

```
Шаг 1. Переключите хешер на CompatPasswordHasher (в shared режиме).
Шаг 2. Пользователи логинятся: bcrypt-хеш верифицируется ✅,
        needsRehash() = true → DefaultAuthManager автоматически
        перехеширует в lphenom-формат и сохраняет через
        PasswordHashUpdaterInterface::updateAuthPasswordHash().
Шаг 3. Через некоторое время все хеши в БД — lphenom-формат.
Шаг 4. Переходите на KPHP бинарь. Всё работает. ✅
```

```php
use LPhenom\Auth\Hashing\CompatPasswordHasher;

// verify() — bcrypt ИЛИ lphenom, hash() — всегда lphenom:
$hasher = new CompatPasswordHasher(iterations: 10000);
```

Для автоматического перехеша `UserProvider` должен реализовывать
`PasswordHashUpdaterInterface`:

```php
use LPhenom\Auth\Contracts\PasswordHashUpdaterInterface;

class MyUserProvider implements UserProviderInterface, PasswordHashUpdaterInterface
{
    public function updateAuthPasswordHash(string $userId, string $newHash): void
    {
        $this->db->execute('UPDATE users SET password_hash = :h WHERE id = :id',
            ['h' => $newHash, 'id' => $userId]);
    }
}
```

---

#### Сводная таблица совместимости хешей

| Хешер | @lphenom-build | verify bcrypt | verify lphenom | hash() формат | DB совместима shared↔kphp |
|---|---|:---:|:---:|---|:---:|
| `CryptPasswordHasher` | **shared,kphp** | ❌ | ✅ | lphenom | **✅ да** |
| `BcryptPasswordHasher` | shared | ✅ | ❌ | bcrypt | ❌ нет с KPHP |
| `CompatPasswordHasher` | shared | ✅ | ✅ | lphenom | ✅ (переходный) |

---

### 5.2 EmailSender

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
$ok = $sender->send('recipient@example.com', '123456');
```

Прямое TCP-соединение через `fsockopen()` / `stream_socket_enable_crypto()`.
**Не работает в KPHP** — `fsockopen()` недоступен.

> **Примечание:** коды подтверждения хранятся в кеше (не в БД), поэтому
> смена способа отправки email при переходе shared→kphp не влияет на
> существующие данные в базе.

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

POST-запрос через `file_get_contents()` + `stream_context_create()` — KPHP-совместимо.

**Совместимые HTTP Email API:** Mailgun, SendGrid, Mailersend, любой кастомный HTTPS POST.

---

## 6. Классы shared,kphp — универсальные

Эти классы **не требуют альтернатив** — они работают в обоих режимах:

### `CryptPasswordHasher` — хеширование паролей (рекомендуемый по умолчанию)

Описан выше в §5.1. Использует `hash_hmac`, `random_bytes`, `bin2hex` — всё
доступно как в PHP, так и в KPHP. **Обеспечивает совместимость БД из коробки.**

### `MirSmsSender` — отправка SMS

```php
$sender = new \LPhenom\Auth\Support\SmsSender\MirSmsSender(
    apiUrl: 'https://api.mirsms.ru/message/send',
    login: 'mylogin',
    password: 'mypassword',
    sender: 'MyApp'
);
$ok = $sender->send('+79001234567', '654321');
```

### `CacheThrottle` — троттлинг через кеш

```php
$throttle = new CacheThrottle($cache);
$throttle->hit('login:user@example.com', decaySeconds: 60);
$tooMany = $throttle->tooManyAttempts('login:user@example.com', maxAttempts: 5);
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

Переключение происходит в **точке сборки приложения** в `lphenom/lphenom`.
Рекомендуемая конфигурация с максимальной совместимостью:

```php
$mode = $_ENV['APP_BUILD'] ?? 'shared'; // 'shared', 'kphp', или 'compat'

if ($mode === 'kphp') {
    // KPHP build — CryptPasswordHasher (совместим с shared DB ✅)
    $passwordHasher = new \LPhenom\Auth\Hashing\CryptPasswordHasher(
        iterations: (int)($_ENV['CRYPT_ITERATIONS'] ?? 10000)
    );
    $emailSender = new \LPhenom\Auth\Support\EmailSender\KphpHttpEmailSender(
        apiUrl: $_ENV['EMAIL_API_URL'], apiKey: $_ENV['EMAIL_API_KEY'],
        fromEmail: $_ENV['EMAIL_FROM'], subject: $_ENV['EMAIL_SUBJECT'] ?? 'Ваш код'
    );
} elseif ($mode === 'compat') {
    // Переходный режим — CompatPasswordHasher мигрирует bcrypt → lphenom при логине
    $passwordHasher = new \LPhenom\Auth\Hashing\CompatPasswordHasher(
        iterations: (int)($_ENV['CRYPT_ITERATIONS'] ?? 10000)
    );
    $emailSender = new \LPhenom\Auth\Support\EmailSender\SmtpEmailSender(
        host: $_ENV['SMTP_HOST'], port: (int)$_ENV['SMTP_PORT'],
        username: $_ENV['SMTP_USER'], password: $_ENV['SMTP_PASS'],
        fromEmail: $_ENV['EMAIL_FROM'], fromName: $_ENV['EMAIL_FROM_NAME'],
        encryption: $_ENV['SMTP_ENCRYPTION'] ?? 'tls'
    );
} else {
    // Shared build — CryptPasswordHasher (совместим с kphp DB ✅)
    $passwordHasher = new \LPhenom\Auth\Hashing\CryptPasswordHasher(
        iterations: (int)($_ENV['CRYPT_ITERATIONS'] ?? 10000)
    );
    $emailSender = new \LPhenom\Auth\Support\EmailSender\SmtpEmailSender(
        host: $_ENV['SMTP_HOST'], port: (int)$_ENV['SMTP_PORT'],
        username: $_ENV['SMTP_USER'], password: $_ENV['SMTP_PASS'],
        fromEmail: $_ENV['EMAIL_FROM'], fromName: $_ENV['EMAIL_FROM_NAME'],
        encryption: $_ENV['SMTP_ENCRYPTION'] ?? 'tls'
    );
}

// Остальной код одинаков для всех режимов:
$authManager = new \LPhenom\Auth\Support\DefaultAuthManager(
    userProvider: $userProvider,
    hasher: $passwordHasher,
    tokenEncoder: new \LPhenom\Auth\Tokens\OpaqueTokenEncoder(),
    tokenRepo: $tokenRepo,
    throttle: $throttle,
    audit: $auditListener,
    tokenTtl: 3600,
    maxAttempts: 5,
    throttleDecay: 60
);
```

### Принцип совместимости

```
                 ┌──────────────────────────────────────────┐
                 │           Contracts (интерфейсы)          │
                 │  PasswordHasherInterface                   │
                 │  CodeSenderInterface                       │
                 │  TokenRepositoryInterface  ...             │
                 └──────────┬─────────────────┬──────────────┘
                            │                 │
          ┌─────────────────▼──┐    ┌─────────▼──────────────────┐
          │  shared реализации │    │  kphp реализации            │
          │                    │    │                             │
          │ BcryptPasswordHasher│   │ KphpHttpEmailSender         │
          │  (опционально)     │    │                             │
          │ SmtpEmailSender    │    └─────────────────────────────┘
          └────────────────────┘
                            │
          ┌─────────────────▼──────────────────────────────────┐
          │          shared,kphp (одинаково в обоих)            │
          │                                                     │
          │  CryptPasswordHasher ← РЕКОМЕНДУЕТСЯ ПО УМОЛЧАНИЮ  │
          │  OpaqueTokenEncoder  ← токены совместимы            │
          │  MirSmsSender, CacheThrottle, DbTokenRepository...  │
          └─────────────────────────────────────────────────────┘
                            ▲
          ┌─────────────────┴──────────────────────────────────┐
          │          APP_BUILD=shared | kphp                   │
          │          Билдер в lphenom/lphenom                  │
          └────────────────────────────────────────────────────┘
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

Если по какой-то причине использовался `BcryptPasswordHasher` —
миграция через `CompatPasswordHasher` описана в [§5.1](#51-passwordhasher).

---

### Нюанс 3: Email через HTTP API vs SMTP

При переходе shared→kphp нужно настроить HTTP Email API (Mailgun, SendGrid, etc.),
так как `SmtpEmailSender` (fsockopen) недоступен в KPHP. Это единственное
**ручное действие** при переходе между режимами (пароли и токены мигрируют
автоматически). Коды подтверждения в кеше — эфемерны, не требуют миграции.

---

### Нюанс 4: Тайм-аут HTTP-запросов в KPHP

`MirSmsSender` и `KphpHttpEmailSender` используют `file_get_contents` с тайм-аутом
10 секунд. В KPHP `file_get_contents` блокирует воркер. Используйте быстрые API (< 2 сек).

---

### Нюанс 5: lphenom/db — FFI-драйвер в KPHP

`DbTokenRepository` в KPHP использует `FfiMySqlConnection`:

```php
$conn = new \LPhenom\Db\Driver\FfiMySqlConnection('127.0.0.1', 'user', 'pass', 'db', 3306);
$tokenRepo = new \LPhenom\Auth\Support\DbTokenRepository($conn);
```

---

### Нюанс 6: `CryptPasswordHasher` vs `BcryptPasswordHasher` — безопасность

| Аспект | `CryptPasswordHasher` | `BcryptPasswordHasher` |
|---|---|---|
| Алгоритм | PBKDF2-HMAC-SHA256 | bcrypt |
| Устойчивость к GPU | ⚠️ Средняя | ✅ Высокая |
| Рекомендуемые параметры (2026) | `iterations=200000` | `cost=12` |
| Режимы | **shared + kphp** ✅ | shared only |
| Совместимость БД | **✅ да** | ❌ нет с KPHP |

> Если безопасность паролей критична, увеличьте `iterations` до `200000`+.
> Bcrypt устойчивее к GPU-атакам, но несовместим с KPHP.

---

## Ссылки

- [KPHP documentation](https://vkcom.github.io/kphp/)
- [KPHP vs PHP — отличия](https://vkcom.github.io/kphp/kphp-language/kphp-vs-php/whats-the-difference.html)
- [lphenom/auth: build-targets](./build-targets.md)
- [lphenom/auth: quickstart](./quickstart.md)
- [lphenom/auth: bearer tokens](./bearer-tokens.md)
- [lphenom/auth: security](./security.md)

