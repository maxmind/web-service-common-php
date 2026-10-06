<?php

declare(strict_types=1);

namespace MaxMind\Test\WebService\Http;

use MaxMind\Exception\HttpException;
use MaxMind\WebService\Http\CurlRequest;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\ExecutableFinder;
use Symfony\Component\Process\Process;

// These tests are totally insufficient, but they do test that most of our
// curl calls are at least syntactically valid and available in each PHP
// version. Doing more sophisticated testing would require setting up a
// server, which is very painful to do in PHP 5.3. For 5.4+, there are
// various solutions. When we increase our required PHP version, we should
// look into those.
/**
 * @coversNothing
 *
 * @internal
 */
class CurlRequestTest extends TestCase
{
    /**
     * @var array{
     *     caBundle?: string,
     *     connectTimeout: float|int,
     *     curlHandle: \CurlHandle,
     *     headers: array<int, string>,
     *     proxy: string|null,
     *     timeout: float|int,
     *     userAgent: string
     * }
     */
    private array $options;

    protected function setUp(): void
    {
        $curlHandle = curl_init();
        if ($curlHandle === false) {
            throw new \RuntimeException('curl_init() returned false');
        }
        $this->options = [
            'connectTimeout' => 0,
            'curlHandle' => $curlHandle,
            'headers' => [],
            'proxy' => null,
            'timeout' => 0,
            'userAgent' => 'Test',
        ];
    }

    public function testGet(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageMatches('/^cURL error.*invalid.host/');

        $cr = new CurlRequest(
            'invalid.host',
            $this->options
        );

        $cr->get();
    }

    public function testCaDirectory(): void
    {
        $curlVersion = curl_version();
        if ($curlVersion === false || !str_starts_with($curlVersion['ssl_version'], 'OpenSSL')) {
            $this->markTestSkipped('This test requires cURL with OpenSSL.');
        }
        $openssl = (new ExecutableFinder())->find('openssl');
        if ($openssl === null) {
            $this->markTestSkipped('This test requires the openssl command.');
        }
        $directory = tempnam(sys_get_temp_dir(), 'curl-ca-');
        unlink($directory);
        mkdir($directory);
        $server = null;

        try {
            $generate = new Process([
                $openssl, 'req', '-x509', '-newkey', 'rsa:2048', '-nodes',
                '-subj', '/CN=localhost', '-days', '1',
                '-addext', 'subjectAltName=DNS:localhost',
                '-keyout', $directory . '/server.key', '-out', $directory . '/cert.pem',
            ]);
            $generate->mustRun();
            $hash = new Process([$openssl, 'x509', '-hash', '-noout', '-in', $directory . '/cert.pem']);
            $hash->mustRun();
            $this->assertTrue(copy($directory . '/cert.pem', $directory . '/' . trim($hash->getOutput()) . '.0'));
            $socket = socket_create_listen(0);
            $this->assertNotFalse($socket);
            socket_getsockname($socket, $address, $port);
            socket_close($socket);
            $server = new Process([
                $openssl, 's_server', '-accept', (string) $port, '-www',
                '-cert', $directory . '/cert.pem', '-key', $directory . '/server.key',
            ]);
            $server->start();
            $this->assertTrue($server->waitUntil(
                static function (string $type, string $output): bool {
                    return str_contains($output, 'ACCEPT');
                }
            ));
            $options = $this->options;
            $options['caBundle'] = $directory;
            $options['timeout'] = 5;
            $request = new CurlRequest('https://localhost:' . $port, $options);
            [$status] = $request->get();
            $this->assertSame(200, $status);
        } finally {
            if ($server !== null) {
                $server->stop();
            }
            $paths = glob($directory . '/*');
            if ($paths === false) {
                throw new \RuntimeException('Could not list temporary certificate files.');
            }
            foreach ($paths as $path) {
                unlink($path);
            }
            rmdir($directory);
        }
    }

    public function testPost(): void
    {
        $this->expectException(HttpException::class);
        $this->expectExceptionMessageMatches('/^cURL error.*invalid.host/');

        $cr = new CurlRequest(
            'invalid.host',
            $this->options
        );

        $cr->post('POST BODY');
    }
}
