# Авторизация по SMS (MirSMS) и Email (SMTP)

LPhenom Auth поддерживает аутентификацию по одноразовым кодам, отправленным через SMS или Email.

---

## Принцип работы

1. Пользователь вводит номер телефона или email.
2. Система генерирует случайный числовой код.
3. **Хеш кода** (SHA-256) сохраняется в кеше с TTL.
4. Код отправляется пользователю через SMS (MirSMS) или Email (SMTP).
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

### Email (SMTP)

```dotenv
# SMTP настройки
SMTP_HOST=smtp.yandex.ru
SMTP_PORT=465
SMTP_USERNAME=noreply@yourapp.ru
SMTP_PASSWORD=your_smtp_password
SMTP_FROM_EMAIL=noreply@yourapp.ru
SMTP_FROM_NAME=YourApp
SMTP_ENCRYPTION=ssl

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

## Email аутентификация (SMTP)

### Настройка

```php
<?php

declare(strict_types=1);

use LPhenom\Auth\Support\EmailSender\SmtpEmailSender;
use LPhenom\Auth\Support\EmailSender\EmailCodeAuthenticator;
use LPhenom\Core\EnvLoader\EnvLoader;

$env = new EnvLoader();
$env->load(__DIR__ . '/.env');

// Создаём SMTP отправитель
$emailSender = new SmtpEmailSender(
    $env->get('SMTP_HOST', 'smtp.yandex.ru'),
    (int) $env->get('SMTP_PORT', '465'),
    $env->get('SMTP_USERNAME', ''),
    $env->get('SMTP_PASSWORD', ''),
    $env->get('SMTP_FROM_EMAIL', 'noreply@yourapp.ru'),
    $env->get('SMTP_FROM_NAME', 'YourApp'),
    $env->get('SMTP_ENCRYPTION', 'ssl')
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

| Параметр   | Описание                        |
|------------|--------------------------------|
| `login`    | Логин аккаунта MirSMS          |
| `password` | Пароль аккаунта MirSMS         |
| `sender`   | Зарегистрированное имя отправителя |
| `phone`    | Номер телефона получателя       |
| `text`     | Текст сообщения                |

### Пример HTTP запроса

```
POST https://api.mirsms.ru/message/send
Content-Type: application/x-www-form-urlencoded

login=mylogin&password=mypass&sender=MyApp&phone=79001234567&text=Your+code:+123456
```

---

## SMTP настройки для популярных провайдеров

### Yandex

```dotenv
SMTP_HOST=smtp.yandex.ru
SMTP_PORT=465
SMTP_ENCRYPTION=ssl
```

### Gmail

```dotenv
SMTP_HOST=smtp.gmail.com
SMTP_PORT=587
SMTP_ENCRYPTION=tls
```

### Mail.ru

```dotenv
SMTP_HOST=smtp.mail.ru
SMTP_PORT=465
SMTP_ENCRYPTION=ssl
```

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

| Компонент              | Статус |
|------------------------|--------|
| `MirSmsSender`         | ✅ использует `file_get_contents` + stream context |
| `SmtpEmailSender`      | ✅ использует `fsockopen` + ручной SMTP-протокол |
| `SmsCodeAuthenticator` | ✅ `random_bytes`, `hash('sha256')` |
| `EmailCodeAuthenticator`| ✅ аналогично SMS |
| `CodeSenderInterface`  | ✅ простой интерфейс без callable |

