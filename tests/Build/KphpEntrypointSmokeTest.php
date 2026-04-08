<?php

declare(strict_types=1);

namespace LPhenom\Auth\Tests\Build;

use PHPUnit\Framework\TestCase;

/**
 * KPHP build smoke tests.
 *
 * Verifies that build/kphp-entrypoint.php:
 *   1. Loads without PHP errors (all @lphenom-build kphp and shared,kphp classes present)
 *   2. Instantiates every feature class correctly
 *   3. Produces the expected "lphenom/auth KPHP entrypoint OK" confirmation line
 *
 * These tests run the entrypoint via PHP CLI (not KPHP compiler) which is
 * sufficient to catch missing require_once, wrong constructors, and class errors.
 * Actual KPHP compilation is verified by Dockerfile.check.
 */
final class KphpEntrypointSmokeTest extends TestCase
{
    private string $entrypoint;

    protected function setUp(): void
    {
        // __DIR__ = /app/tests/Build  → dirname x2 = /app
        $this->entrypoint = dirname(__DIR__, 2) . '/build/kphp-entrypoint.php';
        self::assertFileExists($this->entrypoint, 'build/kphp-entrypoint.php must exist');
    }

    // -------------------------------------------------------------------------
    // Core: entrypoint runs without errors
    // -------------------------------------------------------------------------

    public function testEntrypointExitsCleanly(): void
    {
        $output   = [];
        $exitCode = 0;

        exec('php ' . escapeshellarg($this->entrypoint) . ' 2>&1', $output, $exitCode);

        $combined = implode("\n", $output);

        self::assertSame(
            0,
            $exitCode,
            "kphp-entrypoint.php exited with code {$exitCode}.\nOutput:\n{$combined}"
        );
    }

    public function testEntrypointOutputsOkLine(): void
    {
        $output   = [];
        $exitCode = 0;

        exec('php ' . escapeshellarg($this->entrypoint) . ' 2>&1', $output, $exitCode);

        $combined = implode("\n", $output);

        self::assertStringContainsString(
            'lphenom/auth KPHP entrypoint OK',
            $combined,
            "Expected 'lphenom/auth KPHP entrypoint OK' in output.\nGot:\n{$combined}"
        );
    }

    // -------------------------------------------------------------------------
    // Verify all @lphenom-build kphp and shared,kphp files are in the entrypoint
    // -------------------------------------------------------------------------

    /**
     * @return array<string, array{string}>
     */
    public static function kphpRequiredFilesProvider(): array
    {
        return [
            // Contracts
            'AuthenticatedUserInterface'   => ['src/Contracts/AuthenticatedUserInterface.php'],
            'UserProviderInterface'        => ['src/Contracts/UserProviderInterface.php'],
            'PasswordHasherInterface'      => ['src/Contracts/PasswordHasherInterface.php'],
            'PasswordHashUpdaterInterface' => ['src/Contracts/PasswordHashUpdaterInterface.php'],
            'TokenRepositoryInterface'     => ['src/Contracts/TokenRepositoryInterface.php'],
            'TokenEncoderInterface'        => ['src/Contracts/TokenEncoderInterface.php'],
            'AuthManagerInterface'         => ['src/Contracts/AuthManagerInterface.php'],
            'LoginThrottleInterface'       => ['src/Contracts/LoginThrottleInterface.php'],
            'AuditListenerInterface'       => ['src/Contracts/AuditListenerInterface.php'],
            'CodeSenderInterface'          => ['src/Contracts/CodeSenderInterface.php'],
            // DTOs
            'TokenRecord'                  => ['src/DTO/TokenRecord.php'],
            'IssuedToken'                  => ['src/DTO/IssuedToken.php'],
            'ParsedToken'                  => ['src/DTO/ParsedToken.php'],
            'AuthContext'                  => ['src/DTO/AuthContext.php'],
            // Tokens
            'OpaqueTokenEncoder'           => ['src/Tokens/OpaqueTokenEncoder.php'],
            // Hashing — kphp implementation
            'CryptPasswordHasher'          => ['src/Hashing/CryptPasswordHasher.php'],
            // Support
            'AuthContextStorage'           => ['src/Support/AuthContextStorage.php'],
            'MemoryThrottle'               => ['src/Support/MemoryThrottle.php'],
            'InMemoryTokenRepository'      => ['src/Support/InMemoryTokenRepository.php'],
            'DefaultAuthManager'           => ['src/Support/DefaultAuthManager.php'],
            'CacheThrottle'                => ['src/Support/CacheThrottle.php'],
            'LogAuditListener'             => ['src/Support/LogAuditListener.php'],
            'DbTokenRepository'            => ['src/Support/DbTokenRepository.php'],
            'HttpAuthBridge'               => ['src/Support/HttpAuthBridge.php'],
            // SMS sender
            'MirSmsSender'                 => ['src/Support/SmsSender/MirSmsSender.php'],
            'SmsCodeAuthenticator'         => ['src/Support/SmsSender/SmsCodeAuthenticator.php'],
            // Email sender — UniSender integration (shared,kphp)
            'UniSenderEmailSender'         => ['src/Support/EmailSender/UniSenderEmailSender.php'],
            'EmailCodeAuthenticator'       => ['src/Support/EmailSender/EmailCodeAuthenticator.php'],
            // Guards & Middleware
            'BearerTokenGuard'             => ['src/Guards/BearerTokenGuard.php'],
            'RequireAuthMiddleware'        => ['src/Middleware/RequireAuthMiddleware.php'],
            'RequireRoleMiddleware'        => ['src/Middleware/RequireRoleMiddleware.php'],
            // Migrations
            'CreateAuthTokensTable'        => ['src/Migrations/CreateAuthTokensTable.php'],
            'CreateAuthCodesTable'         => ['src/Migrations/CreateAuthCodesTable.php'],
        ];
    }

    /**
     * @dataProvider kphpRequiredFilesProvider
     */
    public function testKphpEntrypointIncludesRequiredFile(string $relativePath): void
    {
        $content = file_get_contents($this->entrypoint);
        self::assertIsString($content);
        self::assertStringContainsString(
            $relativePath,
            $content,
            "kphp-entrypoint.php must include '{$relativePath}' (@lphenom-build kphp or shared,kphp)"
        );
    }

}
