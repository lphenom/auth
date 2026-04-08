<?php

declare(strict_types=1);

namespace LPhenom\Auth\Tests\Support\EmailSender;

use LPhenom\Auth\Contracts\CodeSenderInterface;
use LPhenom\Auth\Support\EmailSender\UniSenderEmailSender;
use PHPUnit\Framework\TestCase;

final class UniSenderEmailSenderTest extends TestCase
{
    // -------------------------------------------------------------------------
    // Constructor
    // -------------------------------------------------------------------------

    public function testImplementsCodeSenderInterface(): void
    {
        $sender = new UniSenderEmailSender(
            'api-key-123',
            'noreply@example.com',
            'MyApp'
        );

        self::assertInstanceOf(CodeSenderInterface::class, $sender);
    }

    public function testConstructorWithAllArgs(): void
    {
        $sender = new UniSenderEmailSender(
            'key',
            'from@example.com',
            'App',
            'Your code',
            'https://api.unisender.com/ru/api/sendEmail'
        );

        self::assertInstanceOf(UniSenderEmailSender::class, $sender);
    }

    public function testConstructorWithDefaultSubjectAndUrl(): void
    {
        $sender = new UniSenderEmailSender('key', 'from@example.com', 'App');
        self::assertInstanceOf(UniSenderEmailSender::class, $sender);
    }

    // -------------------------------------------------------------------------
    // send() — network failure → false
    // -------------------------------------------------------------------------

    public function testSendReturnsFalseOnNetworkError(): void
    {
        $sender = new UniSenderEmailSender(
            'key',
            'from@example.com',
            'App',
            'Код',
            'http://127.0.0.1:19999/unreachable'
        );

        $result = $sender->send('user@example.com', '123456');

        self::assertFalse($result, 'send() must return false when the HTTP call fails');
    }

    // -------------------------------------------------------------------------
    // send() — response parsing via local HTTP server
    // -------------------------------------------------------------------------

    public function testSendReturnsFalseOnUniSenderErrorResponse(): void
    {
        $port = $this->startLocalServer('{"error":"invalid_api_key","code":3}');
        if ($port === 0) {
            $this->markTestSkipped('Could not start local server');
        }

        $sender = new UniSenderEmailSender(
            'bad-key',
            'from@example.com',
            'App',
            'Код',
            'http://127.0.0.1:' . $port
        );

        $result = $sender->send('user@example.com', '123456');
        $this->stopLocalServer();

        self::assertFalse($result, 'send() must return false when UniSender responds with "error"');
    }

    public function testSendReturnsTrueOnUniSenderSuccessResponse(): void
    {
        $port = $this->startLocalServer('{"result":{"email_id":"abc123"}}');
        if ($port === 0) {
            $this->markTestSkipped('Could not start local server');
        }

        $sender = new UniSenderEmailSender(
            'good-key',
            'from@example.com',
            'App',
            'Код',
            'http://127.0.0.1:' . $port
        );

        $result = $sender->send('user@example.com', '123456');
        $this->stopLocalServer();

        self::assertTrue($result, 'send() must return true on UniSender success response');
    }

    // -------------------------------------------------------------------------
    // Local HTTP server helpers
    // -------------------------------------------------------------------------

    /** @var resource|null */
    private $serverProcess = null;

    /** @var int */
    private int $serverPort = 0;

    private function startLocalServer(string $responseBody): int
    {
        // Find a free port using stream_socket_server (no ext-sockets required)
        $server = @stream_socket_server('tcp://127.0.0.1:0', $errno, $errstr);
        if ($server === false) {
            return 0;
        }
        $name = stream_socket_get_name($server, false);
        fclose($server);
        if ($name === false) {
            return 0;
        }
        $port = (int) substr($name, strrpos($name, ':') + 1);

        $this->serverPort = (int) $port;

        // Write a tiny PHP responder script to a temp file
        $escapedBody = addslashes($responseBody);
        $script = sys_get_temp_dir() . '/lphenom_test_server_' . $this->serverPort . '.php';
        file_put_contents(
            $script,
            '<?php header("Content-Type: application/json"); echo "' . $escapedBody . '";'
        );

        $cmd = 'php -S 127.0.0.1:' . $this->serverPort . ' ' . escapeshellarg($script);
        $descriptors = [['pipe', 'r'], ['pipe', 'w'], ['pipe', 'w']];

        /** @var resource $proc */
        $proc = proc_open($cmd, $descriptors, $pipes);
        $this->serverProcess = $proc;

        // Give the server a moment to start
        usleep(80000);

        return $this->serverPort;
    }

    private function stopLocalServer(): void
    {
        if ($this->serverProcess !== null) {
            proc_terminate($this->serverProcess);
            proc_close($this->serverProcess);
            $this->serverProcess = null;
        }

        $script = sys_get_temp_dir() . '/lphenom_test_server_' . $this->serverPort . '.php';
        if (file_exists($script)) {
            unlink($script);
        }
    }

    protected function tearDown(): void
    {
        $this->stopLocalServer();
    }
}
