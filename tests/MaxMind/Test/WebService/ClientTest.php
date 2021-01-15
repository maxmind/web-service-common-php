<?php

declare(strict_types=1);

namespace MaxMind\Test\WebService;

use Composer\CaBundle\CaBundle;
use MaxMind\WebService\Client;
use PHPUnit\Framework\TestCase;

/**
 * @coversNothing
 */
class ClientTest extends TestCase
{
    public function test200(): void
    {
        $this->assertSame(
            ['a' => 'b'],
            $this->withResponse(
                200,
                'application/json',
                '{"a":"b"}'
            ),
            'received expected decoded response'
        );
    }

    public function test204(): void
    {
        $this->assertNull(
            $this->withResponse(
                204,
                'application/json',
                ''
            ),
            'received expected empty response'
        );
    }

    public function testOptions(): void
    {
        $this->runRequest(
            'TestService',
            '/path',
            [],
            200,
            'application/json',
            '{}',
            3213,
            'abcdefghij',
            [
                'caBundle' => '/path/to/ca.pem',
                'connectTimeout' => 15,
                'proxy' => 'http://bob:pass@127.0.0.1:10',
                'timeout' => 100,
                'userAgent' => 'TestClient/1',
            ]
        );
    }

    public function test200WithInvalidJson(): void
    {
        $this->expectException(\MaxMind\Exception\WebServiceException::class);
        $this->expectExceptionMessage('Received a 200 response for TestService but could not decode the response as JSON: Syntax error. Body: {');

        $this->withResponse(200, 'application/json', '{');
    }

    public function test204WithResponseBody(): void
    {
        $this->expectException(\MaxMind\Exception\WebServiceException::class);
        $this->expectExceptionMessage('Received a 204 response for TestService along with an unexpected HTTP body: non-empty response body');

        $this->withResponse(204, 'application/json', 'non-empty response body');
    }

    public function testInsufficientFunds(): void
    {
        $this->expectException(\MaxMind\Exception\InsufficientFundsException::class);
        $this->expectExceptionMessage('out of credit');

        $this->withResponse(
            402,
            'application/json',
            '{"code":"INSUFFICIENT_FUNDS","error":"out of credit"}'
        );
    }

    /**
     * @dataProvider invalidAuthCodes
     */
    public function testInvalidAuth(string $code): void
    {
        $this->expectException(\MaxMind\Exception\AuthenticationException::class);
        $this->expectExceptionMessage('Invalid auth');

        $this->withResponse(
            401,
            'application/json',
            '{"code":"' . $code . '","error":"Invalid auth"}'
        );
    }

    public function invalidAuthCodes(): array
    {
        return [
            ['ACCOUNT_ID_REQUIRED'],
            ['ACCOUNT_ID_UNKNOWN'],
            ['AUTHORIZATION_INVALID'],
            ['LICENSE_KEY_REQUIRED'],
            ['USER_ID_REQUIRED'],
            ['USER_ID_UNKNOWN'],
        ];
    }

    public function testPermissionRequired(): void
    {
        $this->expectException(\MaxMind\Exception\PermissionRequiredException::class);
        $this->expectExceptionMessage('Permission required');

        $this->withResponse(
            403,
            'application/json',
            '{"code":"PERMISSION_REQUIRED","error":"Permission required"}'
        );
    }

    public function testInvalidRequest(): void
    {
        $this->expectException(\MaxMind\Exception\InvalidRequestException::class);
        $this->expectExceptionMessage('IP invalid');

        $this->withResponse(
            400,
            'application/json',
            '{"code":"IP_ADDRESS_INVALID","error":"IP invalid"}'
        );
    }

    public function test400WithInvalidJson(): void
    {
        $this->expectException(\MaxMind\Exception\WebServiceException::class);
        $this->expectExceptionMessage('Received a 400 error for TestService but could not decode the response as JSON: Syntax error. Body: {"blah"}');

        $this->withResponse(400, 'application/json', '{"blah"}');
    }

    public function test400WithNoBody(): void
    {
        $this->expectException(\MaxMind\Exception\HttpException::class);
        $this->expectExceptionMessage('Received a 400 error for TestService with no body');

        $this->withResponse(400, 'application/json', '');
    }

    public function test400WithUnexpectedContentType(): void
    {
        $this->expectException(\MaxMind\Exception\HttpException::class);
        $this->expectExceptionMessage('Received a 400 error for TestService with the following body: text');

        $this->withResponse(400, 'text/plain', 'text');
    }

    public function test400WithUnexpectedJson(): void
    {
        $this->expectException(\MaxMind\Exception\HttpException::class);
        $this->expectExceptionMessage('Error response contains JSON but it does not specify code or error keys: {"not":"expected"}');

        $this->withResponse(400, 'application/json', '{"not":"expected"}');
    }

    public function test300(): void
    {
        $this->expectException(\MaxMind\Exception\HttpException::class);
        $this->expectExceptionMessage('Received an unexpected HTTP status (300) for TestService');

        $this->withResponse(300, 'application/json', '');
    }

    public function test500(): void
    {
        $this->expectException(\MaxMind\Exception\HttpException::class);
        $this->expectExceptionMessage('Received a server error (500) for TestService');

        $this->withResponse(500, 'application/json', '');
    }

    // convenience method when you don't care about the request
    private function withResponse(int $statusCode, string $contentType, string $body): ?array
    {
        return $this->runRequest(
            'TestService',
            '/path',
            [],
            $statusCode,
            $contentType,
            $body
        );
    }

    private function runRequest(
        string $service,
        string $path,
        array $requestContent,
        int $statusCode,
        string $contentType,
        string $responseBody,
        int $accountId = 10,
        string $licenseKey = '0123456789',
        array $options = []
    ): ?array {
        $host = isset($options['host']) ? $options['host'] : 'api.maxmind.com';

        $url = 'https://' . $host . $path;

        $stub = $this->createMock(
            \MaxMind\WebService\Http\Request::class
        );

        $stub->expects($this->once())
            ->method('post')
            ->with($this->equalTo(json_encode($requestContent)))
            ->willReturn([$statusCode, $contentType, $responseBody]);

        $factory = $this->getMockBuilder(
            'MaxMind\\WebService\\Http\\RequestFactory'
        )->getMock();

        $headers = [
            'Content-Type: application/json',
            'Authorization: Basic '
            . base64_encode($accountId . ':' . $licenseKey),
            'Accept: application/json',
        ];

        $curlVersion = curl_version();
        $userAgent = 'MaxMind-WS-API/' . Client::VERSION . ' PHP/' . PHP_VERSION
            . ' curl/' . $curlVersion['version'];
        if (isset($options['userAgent'])) {
            $userAgent = $options['userAgent'] . ' ' . $userAgent;
        }

        $caBundle = isset($options['caBundle']) ? $options['caBundle']
            : CaBundle::getSystemCaRootBundlePath();

        $factory->expects($this->once())
            ->method('request')
            ->with(
                $this->equalTo($url),
                $this->equalTo(
                    [
                        'headers' => $headers,
                        'userAgent' => $userAgent,
                        'connectTimeout' => isset($options['connectTimeout'])
                            ? $options['connectTimeout'] : null,
                        'timeout' => isset($options['timeout'])
                            ? $options['timeout'] : null,
                        'caBundle' => $caBundle,
                        'proxy' => isset($options['proxy'])
                            ? $options['proxy'] : null,
                    ]
                )
            )->willReturn($stub);

        $options['httpRequestFactory'] = $factory;
        $client = new Client(
            $accountId,
            $licenseKey,
            $options
        );

        return $client->post($service, $path, $requestContent);
    }
}
