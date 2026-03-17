<?php

/**
 * KPHP entrypoint for lphenom/auth.
 *
 * KPHP does NOT support Composer PSR-4 autoloading.
 * All source files must be explicitly require_once'd in dependency order.
 *
 * Note: BcryptPasswordHasher, DbTokenRepository, CacheThrottle, LogAuditListener,
 * SmsSender/*, EmailSender/* are PHP-runtime-only classes (they use functions not
 * available in KPHP like password_hash, fsockopen, PDO). They are NOT included here.
 * In KPHP mode, provide your own implementations of the interfaces.
 *
 * Compile with:
 *   kphp -d /build/kphp-out -M cli /build/build/kphp-entrypoint.php
 */

declare(strict_types=1);

// ---- Dependencies: lphenom/core stubs ----
// LPhenomException is the base exception class from lphenom/core.
// For KPHP standalone compilation, we include a minimal stub.
require_once __DIR__ . '/stubs/LPhenomException.php';

// ---- Exceptions (base first) ----
require_once __DIR__ . '/../src/Exceptions/AuthException.php';
require_once __DIR__ . '/../src/Exceptions/InvalidCredentialsException.php';
require_once __DIR__ . '/../src/Exceptions/InvalidTokenException.php';
require_once __DIR__ . '/../src/Exceptions/ExpiredTokenException.php';
require_once __DIR__ . '/../src/Exceptions/RevokedTokenException.php';
require_once __DIR__ . '/../src/Exceptions/UnauthorizedException.php';
require_once __DIR__ . '/../src/Exceptions/ForbiddenException.php';

// ---- Contracts (interfaces, no dependencies) ----
require_once __DIR__ . '/../src/Contracts/AuthenticatedUserInterface.php';
require_once __DIR__ . '/../src/Contracts/UserProviderInterface.php';
require_once __DIR__ . '/../src/Contracts/PasswordHasherInterface.php';
require_once __DIR__ . '/../src/Contracts/TokenRepositoryInterface.php';
require_once __DIR__ . '/../src/Contracts/TokenEncoderInterface.php';
require_once __DIR__ . '/../src/Contracts/AuthManagerInterface.php';
require_once __DIR__ . '/../src/Contracts/LoginThrottleInterface.php';
require_once __DIR__ . '/../src/Contracts/AuditListenerInterface.php';
require_once __DIR__ . '/../src/Contracts/CodeSenderInterface.php';

// ---- DTOs ----
require_once __DIR__ . '/../src/DTO/TokenRecord.php';
require_once __DIR__ . '/../src/DTO/IssuedToken.php';
require_once __DIR__ . '/../src/DTO/ParsedToken.php';
require_once __DIR__ . '/../src/DTO/AuthContext.php';

// ---- KPHP-safe Implementations ----
require_once __DIR__ . '/../src/Tokens/OpaqueTokenEncoder.php';
require_once __DIR__ . '/../src/Support/AuthContextStorage.php';
require_once __DIR__ . '/../src/Support/MemoryThrottle.php';
require_once __DIR__ . '/../src/Support/InMemoryTokenRepository.php';
require_once __DIR__ . '/../src/Support/DefaultAuthManager.php';

// ---- Smoke-check: instantiate key classes ----
$encoder = new \LPhenom\Auth\Tokens\OpaqueTokenEncoder();
$issued = $encoder->issue('user-1', 3600);
$parsed = $encoder->parseBearer($issued->plainTextToken);

$tokenRepo = new \LPhenom\Auth\Support\InMemoryTokenRepository();
$throttle = new \LPhenom\Auth\Support\MemoryThrottle();

// Verify DTO creation
$record = new \LPhenom\Auth\DTO\TokenRecord('tid', 'uid', 'hash', '2026-01-01 00:00:00', '2027-01-01 00:00:00', null, '');
$issuedToken = new \LPhenom\Auth\DTO\IssuedToken('a.b', 'a', '2027-01-01 00:00:00');
$parsedToken = new \LPhenom\Auth\DTO\ParsedToken('a', 'b');

\LPhenom\Auth\Support\AuthContextStorage::reset();

echo 'lphenom/auth KPHP entrypoint OK' . PHP_EOL;

