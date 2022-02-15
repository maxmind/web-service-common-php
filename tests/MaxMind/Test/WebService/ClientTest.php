<?php

declare(strict_types=1);

namespace MaxMind\Test\WebService;

use Composer\CaBundle\CaBundle;
use MaxMind\WebService\Client;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

/**
 * @coversNothing
 *
 * @internal
 */
class ClientTest extends TestCase
{
    /** @var Process */
    private $process;

    // Starting up a test server before the class
    protected function setUp(): void
    {
        // Router is the test server controller
        $routerPath = __DIR__ . '/TestServer/router.php';
        $this->process = new Process(['php', '-S', 'localhost:8084', $routerPath]);
        $this->process->setInput('foobar');
    }

    // Sets up the response that the test server is going to return.
    public function setupResponse(string $responseJSON): void
    {
        $this->process->setInput($responseJSON);
    }

    // Starts up the process of the test server
    public function startTestServer(): void
    {
        $this->process->start();

        // Wait for server to get going
        usleep(500000);
    }

    // // Stop the test server after the tests are ran
    protected function tearDown(): void
    {
        // If the test server is used in a test, then stop it.
        $this->process->stop(0, \SIGKILL);

        // Wait for server to get going
        usleep(100000);
    }

    public function test200(): void
    {
        $this->assertSame(
            ['a' => 'b'],
            $this->withResponseTestServer(
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
            $this->withResponseTestServer(
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

        $this->withResponseTestServer(200, 'application/json', '{');
    }

    public function test204WithResponseBody(): void
    {
        $this->expectException(\MaxMind\Exception\WebServiceException::class);
        $this->expectExceptionMessage('Received a 204 response for TestService along with an unexpected HTTP body: non-empty response body');

        // Fatih: I was not able to use the built-in server to send a body with 204. For some reason,
        // the echo in the router.php doesn't send the body when the status is 204 (No-content).
        $this->withResponse(204, 'application/json', 'non-empty response body');
    }

    public function testInsufficientFunds(): void
    {
        $this->expectException(\MaxMind\Exception\InsufficientFundsException::class);
        $this->expectExceptionMessage('out of credit');

        $this->withResponseTestServer(
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

        $this->withResponseTestServer(
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

        $this->withResponseTestServer(
            403,
            'application/json',
            '{"code":"PERMISSION_REQUIRED","error":"Permission required"}'
        );
    }

    public function testInvalidRequest(): void
    {
        $this->expectException(\MaxMind\Exception\InvalidRequestException::class);
        $this->expectExceptionMessage('IP invalid');

        $this->withResponseTestServer(
            400,
            'application/json',
            '{"code":"IP_ADDRESS_INVALID","error":"IP invalid"}'
        );
    }

    public function test400WithInvalidJson(): void
    {
        $this->expectException(\MaxMind\Exception\WebServiceException::class);
        $this->expectExceptionMessage('Received a 400 error for TestService but could not decode the response as JSON: Syntax error. Body: {"blah"}');

        $this->withResponseTestServer(400, 'application/json', '{"blah"}');
    }

    public function test400WithNoBody(): void
    {
        $this->expectException(\MaxMind\Exception\HttpException::class);
        $this->expectExceptionMessage('Received a 400 error for TestService with no body');

        $this->withResponseTestServer(400, 'application/json', '');
    }

    public function test400WithUnexpectedContentType(): void
    {
        $this->expectException(\MaxMind\Exception\HttpException::class);
        $this->expectExceptionMessage('Received a 400 error for TestService with the following body: text');

        $this->withResponseTestServer(400, 'text/plain', 'text');
    }

    public function test400WithUnexpectedJson(): void
    {
        $this->expectException(\MaxMind\Exception\HttpException::class);
        $this->expectExceptionMessage('Error response contains JSON but it does not specify code or error keys: {"not":"expected"}');

        $this->withResponseTestServer(400, 'application/json', '{"not":"expected"}');
    }

    public function test300(): void
    {
        $this->expectException(\MaxMind\Exception\HttpException::class);
        $this->expectExceptionMessage('Received an unexpected HTTP status (300) for TestService');

        $this->withResponseTestServer(300, 'application/json', '');
    }

    public function test500(): void
    {
        $this->expectException(\MaxMind\Exception\HttpException::class);
        $this->expectExceptionMessage('Received a server error (500) for TestService');

        $this->withResponseTestServer(500, 'application/json', '');
    }

    // Convenience method when you don't care about the request
    // It runs the request through the test server.
    // This version is used for when we want to test with an actual server.
    private function withResponseTestServer(int $statusCode, string $contentType, string $body): ?array
    {
        // Set up the test server
        $response = [
            'status' => $statusCode,
            'body' => $body,
            'contentType' => $contentType,
        ];
        $this->setupResponse(json_encode($response));
        $this->startTestServer();

        return $this->runRequestTestServer(
            'TestService',
            '/path',
            [],
            10,
            '0123456789',
            [
                'host' => 'localhost:8084',
                'protocol' => 'http://',
            ]
        );
    }

    // runs the request through the test server
    private function runRequestTestServer(
        string $service,
        string $path,
        array $requestContent,
        int $accountId = 10,
        string $licenseKey = '0123456789',
        array $options = []
    ): ?array {
        $client = new Client(
            $accountId,
            $licenseKey,
            $options
        );

        return $client->post($service, $path, $requestContent);
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

    // The other version of withResponse exists because some responses are not supported
    // by the built-in php server, such as sending a body while having a status 204(No-content).
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
        $userAgent = 'MaxMind-WS-API/' . Client::VERSION . ' PHP/' . \PHP_VERSION
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
