<?php

declare(strict_types=1);

namespace MaxMind\Test\WebService\Http;

use MaxMind\Exception\HttpException;
use MaxMind\WebService\Http\CurlRequest;
use PHPUnit\Framework\TestCase;

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
