#!/usr/bin/env php
<?php

/**
 * PHAR smoke-test: require the built PHAR and verify autoloading works.
 *
 * Usage: php build/smoke-test-phar.php /path/to/lphenom-auth.phar
 */

declare(strict_types=1);

$pharFile = $argv[1] ?? dirname(__DIR__) . '/lphenom-auth.phar';

if (!file_exists($pharFile)) {
    fwrite(STDERR, 'PHAR not found: ' . $pharFile . PHP_EOL);
    exit(1);
}

require $pharFile;

// Test password hasher
$hasher = new \LPhenom\Auth\Hashing\BcryptPasswordHasher(4);
$hash = $hasher->hash('test123');
assert($hasher->verify('test123', $hash) === true, 'BcryptPasswordHasher verify failed');
echo 'smoke-test: BcryptPasswordHasher ok' . PHP_EOL;

// Test opaque token encoder
$encoder = new \LPhenom\Auth\Tokens\OpaqueTokenEncoder();
$issued = $encoder->issue('user-1', 3600);
assert($issued->plainTextToken !== '', 'OpaqueTokenEncoder issue failed');
$parsed = $encoder->parseBearer($issued->plainTextToken);
assert($parsed !== null, 'OpaqueTokenEncoder parseBearer failed');
echo 'smoke-test: OpaqueTokenEncoder ok' . PHP_EOL;

// Test InMemoryTokenRepository
$repo = new \LPhenom\Auth\Support\InMemoryTokenRepository();
$record = new \LPhenom\Auth\DTO\TokenRecord('tid1', 'uid1', 'hash1', '2026-01-01 00:00:00', '2027-01-01 00:00:00', null, '');
$repo->create($record);
$found = $repo->findByTokenId('tid1');
assert($found !== null, 'InMemoryTokenRepository find failed');
echo 'smoke-test: InMemoryTokenRepository ok' . PHP_EOL;

// Test MemoryThrottle
$throttle = new \LPhenom\Auth\Support\MemoryThrottle();
assert($throttle->tooManyAttempts('key1', 3) === false, 'MemoryThrottle failed');
echo 'smoke-test: MemoryThrottle ok' . PHP_EOL;

// Test AuthContextStorage
\LPhenom\Auth\Support\AuthContextStorage::reset();
assert(\LPhenom\Auth\Support\AuthContextStorage::get() === null, 'AuthContextStorage failed');
echo 'smoke-test: AuthContextStorage ok' . PHP_EOL;

echo '=== PHAR smoke-test: OK ===' . PHP_EOL;

