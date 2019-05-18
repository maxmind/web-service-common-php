<?php

namespace MaxMind\Test\WebService\Http;

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
 */
class CurlRequestTest extends TestCase
{
    private $options = [
        'caBundle' => null,
        'connectTimeout' => 0,
        'headers' => [],
        'proxy' => null,
        'timeout' => 0,
        'userAgent' => 'Test',
    ];

    /**
     * @expectedException \MaxMind\Exception\HttpException
     * @expectedExceptionMessage cURL error (6): Could not resolve host: invalid host
     */
    public function testGet()
    {
        $cr = new CurlRequest(
            'invalid host',
            $this->options
        );
        $cr->setCurlHandle(curl_init());

        $cr->get();
    }

    /**
     * @expectedException \MaxMind\Exception\HttpException
     * @expectedExceptionMessage cURL error (6): Could not resolve host: invalid host
     */
    public function testPost()
    {
        $cr = new CurlRequest(
            'invalid host',
            $this->options
        );
        $cr->setCurlHandle(curl_init());

        $cr->post('POST BODY');
    }
}
