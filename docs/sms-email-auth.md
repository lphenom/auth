# Авторизация по SMS (MirSMS) и Email (UniSender)

LPhenom Auth поддерживает аутентификацию по одноразовым кодам, отправленным через SMS или Email.

---

## Принцип работы

1. Пользователь вводит номер телефона или email.
2. Система генерирует случайный числовой код.
3. **Хеш кода** (SHA-256) сохраняется в кеше с TTL.
4. Код отправляется пользователю через SMS (MirSMS) или Email (UniSender).
5. Пользователь вводит код.
6. Система сравнивает хеш введённого кода с хешем в кеше.
7. При совпадении — аутентификация успешна. Код удаляется из кеша.

---

## Настройка через .env

### SMS (MirSMS)

```dotenv
# MirSMS API
MIRSMS_API_URL=https://api.mirsms.ru/message/send
MIRSMS_LOGIN=your_login
MIRSMS_PASSWORD=your_password
MIRSMS_SENDER=YourApp

# Настройки кода
AUTH_SMS_CODE_LENGTH=6
AUTH_SMS_CODE_TTL=300
```

### Email (UniSender)

```dotenv
# UniSender API
UNISENDER_API_KEY=your_api_key
UNISENDER_SENDER_EMAIL=noreply@yourapp.ru
UNISENDER_SENDER_NAME=YourApp
UNISENDER_SUBJECT=Код подтверждения

# Настройки кода
AUTH_EMAIL_CODE_LENGTH=6
AUTH_EMAIL_CODE_TTL=300
```

---

## SMS аутентификация (MirSMS)

### Настройка

```php
<?php

declare(strict_types=1);

use LPhenom\Auth\Support\SmsSender\MirSmsSender;
use LPhenom\Auth\Support\SmsSender\SmsCodeAuthenticator;
use LPhenom\Core\EnvLoader\EnvLoader;

$env = new EnvLoader();
$env->load(__DIR__ . '/.env');

// Создаём отправитель SMS
$smsSender = new MirSmsSender(
    $env->get('MIRSMS_API_URL', 'https://api.mirsms.ru/message/send'),
    $env->get('MIRSMS_LOGIN', ''),
    $env->get('MIRSMS_PASSWORD', ''),
    $env->get('MIRSMS_SENDER', 'LPhenom')
);

// Создаём аутентификатор кодов
$smsAuth = new SmsCodeAuthenticator(
    $smsSender,
    $cache,           // CacheInterface из lphenom/cache
    (int) $env->get('AUTH_SMS_CODE_LENGTH', '6'),
    (int) $env->get('AUTH_SMS_CODE_TTL', '300')
);
```

### Отправка кода

```php
$phone = '+79001234567';

$sent = $smsAuth->sendCode($phone);
if (!$sent) {
    // Ошибка отправки SMS
    echo 'Failed to send SMS';
}
```

### Верификация кода

```php
$phone = '+79001234567';
$code  = '123456'; // код, введённый пользователем

$valid = $smsAuth->verifyCode($phone, $code);
if ($valid) {
    // Код верный — аутентифицируем пользователя
    $user = $userProvider->findByLogin($phone);
    if ($user !== null) {
        $issued = $authManager->issueToken($user);
        echo 'Token: ' . $issued->plainTextToken;
    }
} else {
    echo 'Invalid or expired code';
}
```

---

## Email аутентификация (UniSender)

### Настройка

```php
<?php

declare(strict_types=1);

use LPhenom\Auth\Support\EmailSender\UniSenderEmailSender;
use LPhenom\Auth\Support\EmailSender\EmailCodeAuthenticator;
use LPhenom\Core\EnvLoader\EnvLoader;

$env = new EnvLoader();
$env->load(__DIR__ . '/.env');

// Создаём отправитель через UniSender API
$emailSender = new UniSenderEmailSender(
    $env->get('UNISENDER_API_KEY', ''),
    $env->get('UNISENDER_SENDER_EMAIL', 'noreply@yourapp.ru'),
    $env->get('UNISENDER_SENDER_NAME', 'YourApp'),
    $env->get('UNISENDER_SUBJECT', 'Код подтверждения')
    // 5-й аргумент — $apiUrl — опционален, по умолчанию RU-эндпоинт UniSender
);

// Создаём аутентификатор кодов
$emailAuth = new EmailCodeAuthenticator(
    $emailSender,
    $cache,           // CacheInterface из lphenom/cache
    (int) $env->get('AUTH_EMAIL_CODE_LENGTH', '6'),
    (int) $env->get('AUTH_EMAIL_CODE_TTL', '300')
);
```

### Отправка кода

```php
$email = 'user@example.com';

$sent = $emailAuth->sendCode($email);
if (!$sent) {
    echo 'Failed to send email';
}
```

### Верификация кода

```php
$email = 'user@example.com';
$code  = '123456';

$valid = $emailAuth->verifyCode($email, $code);
if ($valid) {
    $user = $userProvider->findByLogin($email);
    if ($user !== null) {
        $issued = $authManager->issueToken($user);
        echo 'Token: ' . $issued->plainTextToken;
    }
} else {
    echo 'Invalid or expired code';
}
```

---

## MirSMS API

### Регистрация

1. Зарегистрируйтесь на [mirsms.ru](https://mirsms.ru)
2. Получите логин и пароль API
3. Зарегистрируйте имя отправителя (sender name)

### API endpoint

По умолчанию: `https://api.mirsms.ru/message/send`

### Параметры запроса

| Параметр   | Описание                              |
|------------|---------------------------------------|
| `login`    | Логин аккаунта MirSMS                 |
| `password` | Пароль аккаунта MirSMS                |
| `sender`   | Зарегистрированное имя отправителя    |
| `phone`    | Номер телефона получателя             |
| `text`     | Текст сообщения                       |

### Пример HTTP запроса

```
POST https://api.mirsms.ru/message/send
Content-Type: application/x-www-form-urlencoded

login=mylogin&password=mypass&sender=MyApp&phone=79001234567&text=123456
```

---

## UniSender API

`UniSenderEmailSender` использует метод [`sendEmail`](https://www.unisender.com/ru/support/api/partners/sendemail/)
UniSender API для отправки одного транзакционного письма.

### Регистрация

1. Зарегистрируйтесь на [unisender.com](https://www.unisender.com/)
2. Получите API-ключ: **Аккаунт → API → Ключ**
3. Укажите верифицированный email отправителя

### API endpoint

По умолчанию: `https://api.unisender.com/ru/api/sendEmail`

Для других локалей передайте 5-й аргумент конструктора:

```php
$emailSender = new UniSenderEmailSender(
    $apiKey,
    $senderEmail,
    $senderName,
    $subject,
    'https://api.unisender.com/en/api/sendEmail' // EN-эндпоинт
);
```

### Параметры запроса

| Параметр       | Описание                              |
|----------------|---------------------------------------|
| `api_key`      | Ключ API из настроек аккаунта         |
| `email`        | Email получателя                      |
| `sender_name`  | Отображаемое имя отправителя          |
| `sender_email` | Email отправителя (верифицированный)  |
| `subject`      | Тема письма                           |
| `body`         | HTML-тело письма                      |
| `format`       | Формат ответа (`json`)                |

### Формат ответа

Успех:
```json
{"result": {"email_id": "abc123"}}
```

Ошибка:
```json
{"error": "invalid_api_key", "code": 3}
```

`UniSenderEmailSender` считает ответ успешным, если в теле отсутствует ключ `"error"`.

---

## Безопасность

- Коды хранятся в кеше как **SHA-256 хеш** — при утечке кеша коды не раскрываются.
- Коды автоматически истекают по TTL.
- После успешной верификации код удаляется.
- Рекомендуется ограничивать частоту отправки кодов через throttle.
- Длина кода по умолчанию — 6 цифр (10^6 = 1 000 000 комбинаций).

### Ограничение частоты отправки

```php
$throttleKey = 'auth:code:' . $phone;

if ($throttle->tooManyAttempts($throttleKey, 3)) {
    echo 'Too many attempts. Try later.';
} else {
    $smsAuth->sendCode($phone);
    $throttle->hit($throttleKey, 60);
}
```

---

## Собственная реализация отправителя

Если вам нужен другой SMS-провайдер или email-транспорт, реализуйте `CodeSenderInterface`:

```php
<?php

declare(strict_types=1);

use LPhenom\Auth\Contracts\CodeSenderInterface;

final class TwilioSmsSender implements CodeSenderInterface
{
    public function send(string $recipient, string $code): bool
    {
        // Ваша реализация отправки через Twilio API
        // ...
        return true;
    }
}
```

Затем передайте в `SmsCodeAuthenticator` или `EmailCodeAuthenticator`:

```php
$smsAuth = new SmsCodeAuthenticator(
    new TwilioSmsSender(),
    $cache,
    6,
    300
);
```

---

## KPHP-совместимость

| Компонент               | Статус                                              |
|-------------------------|-----------------------------------------------------|
| `MirSmsSender`          | ✅ `@file_get_contents` + stream context            |
| `UniSenderEmailSender`  | ✅ `@file_get_contents` + stream context            |
| `SmsCodeAuthenticator`  | ✅ `random_bytes`, `hash('sha256')`                 |
| `EmailCodeAuthenticator`| ✅ аналогично SMS                                   |
| `CodeSenderInterface`   | ✅ простой интерфейс без callable                   |

> **Примечание про `@`:** `file_get_contents` на мёртвый сокет эмитит PHP `E_WARNING`
> **до** того, как выполнение дойдёт до `try/catch`. Оператор `@` подавляет это
> предупреждение в PHP; в KPHP он является no-op и не влияет на поведение.
