# Интеграция с HTTP

## Middleware

Пакет предоставляет два middleware для интеграции с `lphenom/http`:

### RequireAuthMiddleware

Проверяет наличие валидного bearer-токена. Возвращает 401 если токен отсутствует или невалиден.

```php
use LPhenom\Auth\Guards\BearerTokenGuard;
use LPhenom\Auth\Middleware\RequireAuthMiddleware;

$guard = new BearerTokenGuard($authManager);
$authMiddleware = new RequireAuthMiddleware($guard);
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
$guard = new BearerTokenGuard($authManager);

// Публичный маршрут — без аутентификации
$router->post('/api/login', $loginHandler);

// Защищённый маршрут — требуется аутентификация
$router->get('/api/profile', $profileHandler);

// Маршрут с проверкой роли
$router->get('/api/admin/users', $adminUsersHandler);

// Создаём middleware stack для защищённых маршрутов
$authStack = new MiddlewareStack();
$authStack->push(new RequireAuthMiddleware($guard));

// Для admin-маршрутов
$adminStack = new MiddlewareStack();
$adminStack->push(new RequireAuthMiddleware($guard));
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

use LPhenom\Auth\Hashing\BcryptPasswordHasher;
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
    new BcryptPasswordHasher(10),
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

