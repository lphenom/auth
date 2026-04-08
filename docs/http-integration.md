# Интеграция с HTTP

## Middleware

Пакет предоставляет два middleware для интеграции с `lphenom/http`:

### RequireAuthMiddleware

Проверяет наличие валидного bearer-токена. Возвращает 401 если токен отсутствует или невалиден.

Второй аргумент конструктора — необязательный список **публичных путей**, которые не требуют аутентификации. Поддерживаются два режима совпадения:

| Запись                   | Тип                        | Пример совпадения                                    |
|--------------------------|----------------------------|------------------------------------------------------|
| `/api/v1/auth/login`     | Точное совпадение          | `/api/v1/auth/login` — да; `/api/v1/auth/reg` — нет |
| `/api/v1/auth/*`         | Префиксный wildcard (`*`)  | `/api/v1/auth/login`, `/api/v1/auth/register` — да  |

```php
use LPhenom\Auth\Guards\BearerTokenGuard;
use LPhenom\Auth\Middleware\RequireAuthMiddleware;

$guard = new BearerTokenGuard($authManager);

// Без исключений — все маршруты защищены
$authMiddleware = new RequireAuthMiddleware($guard);

// С публичными маршрутами — /api/v1/auth/* пропускается без токена
$authMiddleware = new RequireAuthMiddleware($guard, [
    '/api/v1/auth/login',
    '/api/v1/auth/register',
    // или через wildcard:
    // '/api/v1/auth/*',
]);
```

### RequireRoleMiddleware

Проверяет наличие определённых ролей у аутентифицированного пользователя. Возвращает 403 если роль отсутствует.

**Важно:** этот middleware должен стоять ПОСЛЕ `RequireAuthMiddleware` в pipeline.

```php
use LPhenom\Auth\Middleware\RequireRoleMiddleware;

$adminMiddleware = new RequireRoleMiddleware(['admin']);
```

## Подключение к Router

```php
use LPhenom\Http\Router;
use LPhenom\Http\MiddlewareStack;
use LPhenom\Auth\Guards\BearerTokenGuard;
use LPhenom\Auth\Middleware\RequireAuthMiddleware;
use LPhenom\Auth\Middleware\RequireRoleMiddleware;

$router = new Router();
$guard  = new BearerTokenGuard($authManager);

// Публичные маршруты — без аутентификации
$router->post('/api/v1/auth/login',    $loginHandler);
$router->post('/api/v1/auth/register', $registerHandler);

// Защищённые маршруты
$router->get('/api/profile',         $profileHandler);
$router->get('/api/admin/users',     $adminUsersHandler);

// Глобальный middleware stack с исключениями для публичных путей
$authStack = new MiddlewareStack();
$authStack->push(new RequireAuthMiddleware($guard, [
    '/api/v1/auth/login',
    '/api/v1/auth/register',
    // wildcard-вариант: '/api/v1/auth/*'
]));

// Для admin-маршрутов дополнительно проверяем роль
$adminStack = new MiddlewareStack();
$adminStack->push(new RequireAuthMiddleware($guard, ['/api/v1/auth/*']));
$adminStack->push(new RequireRoleMiddleware(['admin']));
```

## BearerTokenGuard

Guard извлекает токен из HTTP заголовка `Authorization`:

```php
use LPhenom\Auth\Guards\BearerTokenGuard;

$guard = new BearerTokenGuard($authManager);

// Аутентификация запроса
$user = $guard->authenticate($request);
if ($user !== null) {
    echo 'Authenticated as: ' . $user->getAuthIdentifier();
}
```

## HttpAuthBridge

Удобный мост между HTTP запросом и системой аутентификации:

```php
use LPhenom\Auth\Support\HttpAuthBridge;
use LPhenom\Auth\Guards\BearerTokenGuard;

$bridge = new HttpAuthBridge(new BearerTokenGuard($authManager));

// Аутентифицировать запрос
$authenticated = $bridge->authenticateRequest($request);

if ($authenticated) {
    $ctx = $bridge->getContext();
    // $ctx->user — текущий пользователь
    // $ctx->tokenId — ID токена
}
```

## Получение текущего пользователя в контроллере

После прохождения `RequireAuthMiddleware` контекст аутентификации хранится в `AuthContextStorage`:

```php
use LPhenom\Auth\Support\AuthContextStorage;
use LPhenom\Http\HandlerInterface;
use LPhenom\Http\Request;
use LPhenom\Http\Response;

final class ProfileHandler implements HandlerInterface
{
    public function handle(Request $request): Response
    {
        $ctx = AuthContextStorage::get();
        if ($ctx === null) {
            return Response::json(['error' => 'Unauthorized'], 401);
        }

        $user = $ctx->user;

        /** @var array<string, string> $data */
        $data = [
            'id'    => $user->getAuthIdentifier(),
        ];

        return Response::json($data);
    }
}
```

## Пример полного API

```php
<?php

declare(strict_types=1);

use LPhenom\Auth\Hashing\CryptPasswordHasher;
use LPhenom\Auth\Support\DefaultAuthManager;
use LPhenom\Auth\Support\InMemoryTokenRepository;
use LPhenom\Auth\Tokens\OpaqueTokenEncoder;
use LPhenom\Auth\Guards\BearerTokenGuard;
use LPhenom\Auth\Middleware\RequireAuthMiddleware;
use LPhenom\Auth\Support\AuthContextStorage;
use LPhenom\Http\Request;
use LPhenom\Http\Response;
use LPhenom\Http\Router;
use LPhenom\Http\MiddlewareStack;

// --- Настройка аутентификации ---
$authManager = new DefaultAuthManager(
    $userProvider,
    new CryptPasswordHasher(10000),
    new OpaqueTokenEncoder(),
    new InMemoryTokenRepository(),
    null,    // throttle
    null,    // audit
    86400,   // TTL
    5,       // max attempts
    60       // decay
);

$guard = new BearerTokenGuard($authManager);

// --- Маршруты ---
$router = new Router();

// POST /api/login — вход
// POST /api/logout — выход (требуется токен)
// GET  /api/me — текущий пользователь (требуется токен)

// Обработка:
$request = Request::fromGlobals();

// Для защищённых маршрутов — сначала аутентификация
AuthContextStorage::reset();
$guard->authenticate($request);
```

## Сброс контекста

В compiled mode (KPHP) сбрасывайте контекст в начале каждого запроса:

```php
AuthContextStorage::reset();
```

На shared hosting (Apache/Nginx + PHP-FPM) контекст сбрасывается автоматически при завершении процесса.

