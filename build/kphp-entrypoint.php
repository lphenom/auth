<?php

/**
 * KPHP entrypoint for lphenom/auth.
 *
 * KPHP does NOT support Composer PSR-4 autoloading.
 * All source files must be explicitly require_once'd in dependency order.
 *
 * Build targets are indicated by the @lphenom-build annotation in each file:
 *
 *   @lphenom-build shared,kphp  - included in both PHP shared-hosting and KPHP binary builds
 *   @lphenom-build shared       - PHP runtime only (password_hash, fsockopen, etc.)
 *                                 NOT included in this entrypoint
 *   @lphenom-build kphp         - KPHP-only implementation (alternative to shared class)
 *
 * Only @lphenom-build shared,kphp and @lphenom-build kphp files are compiled here.
 * @lphenom-build shared files are EXCLUDED — provide your own or use KPHP alternatives.
 *
 * For the full build-target architecture, see: docs/build-targets.md
 *
 * Compile with:
 *   kphp -d /build/kphp-out -M cli /build/build/kphp-entrypoint.php
 */

declare(strict_types=1);

// =============================================================================
// DEPENDENCIES — lphenom/core
// =============================================================================
require_once __DIR__ . '/../vendor/lphenom/core/src/Exception/LPhenomException.php';

// =============================================================================
// DEPENDENCIES — lphenom/db contracts + Param (needed by DbTokenRepository, Migrations)
// =============================================================================
require_once __DIR__ . '/../vendor/lphenom/db/src/Contract/ResultInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Contract/TransactionCallbackInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Contract/ConnectionInterface.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Param/Param.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Param/ParamBinder.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Exception/ConnectionException.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Exception/NotImplementedException.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Exception/QueryException.php';
require_once __DIR__ . '/../vendor/lphenom/db/src/Migration/MigrationInterface.php';

// =============================================================================
// DEPENDENCIES — lphenom/cache (needed by CacheThrottle, SmsCodeAuthenticator, etc.)
// =============================================================================
require_once __DIR__ . '/../vendor/lphenom/cache/src/Exception/CacheException.php';
require_once __DIR__ . '/../vendor/lphenom/cache/src/KeyNormalizer.php';
require_once __DIR__ . '/../vendor/lphenom/cache/src/CacheInterface.php';
require_once __DIR__ . '/../vendor/lphenom/cache/src/Driver/InMemoryCache.php';

// =============================================================================
// DEPENDENCIES — lphenom/log (needed by LogAuditListener)
// =============================================================================
require_once __DIR__ . '/../vendor/lphenom/log/src/Exception/LogException.php';
require_once __DIR__ . '/../vendor/lphenom/log/src/Exception/InvalidLogLevelException.php';
require_once __DIR__ . '/../vendor/lphenom/log/src/Contract/LogLevel.php';
require_once __DIR__ . '/../vendor/lphenom/log/src/Contract/LogRecord.php';
require_once __DIR__ . '/../vendor/lphenom/log/src/Contract/FormatterInterface.php';
require_once __DIR__ . '/../vendor/lphenom/log/src/Contract/HandlerInterface.php';
require_once __DIR__ . '/../vendor/lphenom/log/src/Contract/LoggerInterface.php';
require_once __DIR__ . '/../vendor/lphenom/log/src/Logger/AbstractLogger.php';
require_once __DIR__ . '/../vendor/lphenom/log/src/Logger/NullLogger.php';

// =============================================================================
// DEPENDENCIES — lphenom/http (needed by Guards, Middleware, HttpAuthBridge)
// =============================================================================
require_once __DIR__ . '/../vendor/lphenom/http/src/HandlerInterface.php';
require_once __DIR__ . '/../vendor/lphenom/http/src/RouterGroupCallback.php';
require_once __DIR__ . '/../vendor/lphenom/http/src/Middleware/RateLimiterInterface.php';
require_once __DIR__ . '/../vendor/lphenom/http/src/Exception/RouteNotFoundException.php';
require_once __DIR__ . '/../vendor/lphenom/http/src/Request.php';
require_once __DIR__ . '/../vendor/lphenom/http/src/Response.php';
require_once __DIR__ . '/../vendor/lphenom/http/src/RouteMatch.php';
require_once __DIR__ . '/../vendor/lphenom/http/src/MiddlewareInterface.php';
require_once __DIR__ . '/../vendor/lphenom/http/src/Next.php';

// =============================================================================
// lphenom/auth — Exceptions (@lphenom-build shared,kphp)
// =============================================================================
require_once __DIR__ . '/../src/Exceptions/AuthException.php';
require_once __DIR__ . '/../src/Exceptions/InvalidCredentialsException.php';
require_once __DIR__ . '/../src/Exceptions/InvalidTokenException.php';
require_once __DIR__ . '/../src/Exceptions/ExpiredTokenException.php';
require_once __DIR__ . '/../src/Exceptions/RevokedTokenException.php';
require_once __DIR__ . '/../src/Exceptions/UnauthorizedException.php';
require_once __DIR__ . '/../src/Exceptions/ForbiddenException.php';

// =============================================================================
// lphenom/auth — Contracts (@lphenom-build shared,kphp)
// =============================================================================
require_once __DIR__ . '/../src/Contracts/AuthenticatedUserInterface.php';
require_once __DIR__ . '/../src/Contracts/UserProviderInterface.php';
require_once __DIR__ . '/../src/Contracts/PasswordHasherInterface.php';
require_once __DIR__ . '/../src/Contracts/TokenRepositoryInterface.php';
require_once __DIR__ . '/../src/Contracts/TokenEncoderInterface.php';
require_once __DIR__ . '/../src/Contracts/AuthManagerInterface.php';
require_once __DIR__ . '/../src/Contracts/LoginThrottleInterface.php';
require_once __DIR__ . '/../src/Contracts/AuditListenerInterface.php';
require_once __DIR__ . '/../src/Contracts/CodeSenderInterface.php';

// =============================================================================
// lphenom/auth — DTOs (@lphenom-build shared,kphp)
// =============================================================================
require_once __DIR__ . '/../src/DTO/TokenRecord.php';
require_once __DIR__ . '/../src/DTO/IssuedToken.php';
require_once __DIR__ . '/../src/DTO/ParsedToken.php';
require_once __DIR__ . '/../src/DTO/AuthContext.php';

// =============================================================================
// lphenom/auth — Tokens (@lphenom-build shared,kphp)
// =============================================================================
require_once __DIR__ . '/../src/Tokens/OpaqueTokenEncoder.php';

// =============================================================================
// lphenom/auth — Hashing
//   BcryptPasswordHasher  @lphenom-build shared        ← EXCLUDED (password_hash not in KPHP)
//   CryptPasswordHasher   @lphenom-build kphp          ← INCLUDED (HMAC-SHA256 based)
// =============================================================================
require_once __DIR__ . '/../src/Hashing/CryptPasswordHasher.php';

// =============================================================================
// lphenom/auth — Support: shared,kphp implementations
// =============================================================================
require_once __DIR__ . '/../src/Support/AuthContextStorage.php';
require_once __DIR__ . '/../src/Support/MemoryThrottle.php';
require_once __DIR__ . '/../src/Support/InMemoryTokenRepository.php';
require_once __DIR__ . '/../src/Support/DefaultAuthManager.php';
require_once __DIR__ . '/../src/Support/CacheThrottle.php';
require_once __DIR__ . '/../src/Support/LogAuditListener.php';
require_once __DIR__ . '/../src/Support/DbTokenRepository.php';
require_once __DIR__ . '/../src/Support/HttpAuthBridge.php';

// =============================================================================
// lphenom/auth — SMS / Email senders
//   SmtpEmailSender       @lphenom-build shared        ← EXCLUDED (fsockopen not in KPHP)
//   KphpHttpEmailSender   @lphenom-build kphp          ← INCLUDED (file_get_contents)
//   MirSmsSender          @lphenom-build shared,kphp   ← INCLUDED (file_get_contents)
//   SmsCodeAuthenticator  @lphenom-build shared,kphp   ← INCLUDED
//   EmailCodeAuthenticator@lphenom-build shared,kphp   ← INCLUDED
// =============================================================================
require_once __DIR__ . '/../src/Support/SmsSender/MirSmsSender.php';
require_once __DIR__ . '/../src/Support/SmsSender/SmsCodeAuthenticator.php';
require_once __DIR__ . '/../src/Support/EmailSender/EmailCodeAuthenticator.php';
require_once __DIR__ . '/../src/Support/EmailSender/KphpHttpEmailSender.php';

// =============================================================================
// lphenom/auth — Guards & Middleware (@lphenom-build shared,kphp)
// =============================================================================
require_once __DIR__ . '/../src/Guards/BearerTokenGuard.php';
require_once __DIR__ . '/../src/Middleware/RequireAuthMiddleware.php';
require_once __DIR__ . '/../src/Middleware/RequireRoleMiddleware.php';

// =============================================================================
// lphenom/auth — Migrations (@lphenom-build shared,kphp)
//   Uses ConnectionInterface — in KPHP: FfiMySqlConnection; in PHP: PdoMySqlConnection
// =============================================================================
require_once __DIR__ . '/../src/Migrations/CreateAuthTokensTable.php';
require_once __DIR__ . '/../src/Migrations/CreateAuthCodesTable.php';

// =============================================================================
// Smoke-check: instantiate key classes to verify KPHP compilation
// =============================================================================

// Core token operations
$encoder = new \LPhenom\Auth\Tokens\OpaqueTokenEncoder();
$issued = $encoder->issue('user-1', 3600);
$parsed = $encoder->parseBearer($issued->plainTextToken);

// KPHP hasher (CryptPasswordHasher — @lphenom-build kphp)
$hasher = new \LPhenom\Auth\Hashing\CryptPasswordHasher(100);
$hash = $hasher->hash('smoke-test');
$ok = $hasher->verify('smoke-test', $hash);
$needsRehash = $hasher->needsRehash($hash);

// In-memory implementations (shared,kphp)
$tokenRepo = new \LPhenom\Auth\Support\InMemoryTokenRepository();
$throttle  = new \LPhenom\Auth\Support\MemoryThrottle();

// Cache-based throttle (shared,kphp via InMemoryCache)
$cache = new \LPhenom\Cache\Driver\InMemoryCache();
$cacheThrottle = new \LPhenom\Auth\Support\CacheThrottle($cache);

// Log-based audit listener (shared,kphp via NullLogger)
$logger = new \LPhenom\Log\Logger\NullLogger('auth');
$auditListener = new \LPhenom\Auth\Support\LogAuditListener($logger);

// KPHP HTTP email sender (@lphenom-build kphp)
$emailSender = new \LPhenom\Auth\Support\EmailSender\KphpHttpEmailSender(
    'https://api.example.com/send',
    'api-key',
    'noreply@example.com',
    'Verification Code'
);

// DTOs
$record = new \LPhenom\Auth\DTO\TokenRecord('tid', 'uid', 'hash', '2026-01-01 00:00:00', '2027-01-01 00:00:00', null, '');
$issuedToken = new \LPhenom\Auth\DTO\IssuedToken('a.b', 'a', '2027-01-01 00:00:00');
$parsedToken = new \LPhenom\Auth\DTO\ParsedToken('a', 'b');

\LPhenom\Auth\Support\AuthContextStorage::reset();

echo 'lphenom/auth KPHP entrypoint OK' . PHP_EOL;
