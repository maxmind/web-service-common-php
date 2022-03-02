<?php

declare(strict_types=1);

namespace MaxMind\Test\WebService;

putenv('XDEBUG_CONFIG=idekey=mock');

use Composer\CaBundle\CaBundle;
use MaxMind\WebService\Client;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Process\Process;

// This is the tmp file that the responses are stacks are stored.
\define('responseFileName', '/web-service-common-php-response.json');
\define('fullResponseFilePath', sys_get_temp_dir() . responseFileName);

/**
 * @coversNothing
 *
 * @internal
 */
class ClientTest extends TestCase
{
    /** @var Process */
    public static $process;

    /** @var int */
    public static $port;

    // Sets up the response that the test server is going to return.
    /**
     * addResponseInQueue.
     *
     * @param string $responseJSON the body that is going to be added into the queue
     * @param int    $n            the number of times that it is going to be the response
     */
    public static function addResponseInQueue(string $responseJSON, $n = 1): void
    {
        if (!$fh = fopen(fullResponseFilePath, 'wb')) {
            throw new \RuntimeException('Could not open tmp response json file.');
        }
        for ($n; $n > 0; $n--) {
            fwrite($fh, $responseJSON . \PHP_EOL);
        }
        fclose($fh);
    }

    // Makes sure the built-in server is up by querying
    // `/test` endpoint of the TestServer.
    /**
     * @return bool
     */
    public static function isWebsiteUp()
    {
        $requestUrl = 'localhost:' . (string) (self::$port) . '/test';

        $ch = curl_init();

        curl_setopt($ch, \CURLOPT_URL, $requestUrl);
        curl_setopt($ch, \CURLOPT_HEADER, false);
        curl_setopt($ch, \CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, \CURLOPT_TIMEOUT, 2);
        curl_exec($ch);

        $response_status = curl_getinfo($ch, \CURLINFO_RESPONSE_CODE);

        curl_close($ch);

        return $response_status === 200;
    }

    public static function setUpBeforeClass(): void
    {
        // Clean up the response json if there is one.
        if (file_exists(fullResponseFilePath)) {
            unlink(fullResponseFilePath);
        }

        // Router is the test server controller
        $routerPath = __DIR__ . '/TestServer.php';

        // Getting a port that is available for use.
        if (!$socket = socket_create_listen(0)) {
            throw new \RuntimeException('Could not create socket.');
        }
        socket_getsockname($socket, $addr, self::$port);
        socket_close($socket);

        // Starting up the build-in server with the port we got above.
        self::$process = new Process(['php', '-S', 'localhost:' . (string) (self::$port), $routerPath]);
        self::$process->setEnv(['RESPONSEJSON' => fullResponseFilePath]);
        self::$process->start();

        // Checking if the test server is up under 5 seconds.
        for ($half_seconds = 0; $half_seconds < 10; $half_seconds++) {
            if (self::isWebsiteUp()) {
                return;
            }
            usleep(500000); // wait half a second
        }

        throw new \RuntimeException('Test server could not be started.');
    }

    // Stop the test server after the tests are ran
    public static function tearDownAfterClass(): void
    {
        // If the test server is used in a test, then stop it.
        if (self::$process !== null) {
            self::$process->stop(0);
        }
    }

    public function testCurlPost(): void
    {
        $accountId = 10;
        $licenseKey = '0123456789';
        $host = 'localhost:' . self::$port;
        $response = $this->runRequestTestServer(
            'TestService',
            '/mirror',
            ['test'],
            $accountId,
            $licenseKey,
            [
                'host' => $host,
                'useHttps' => false,
            ],
            'post'
        );

        $curlVersion = curl_version();
        $userAgent = 'MaxMind-WS-API/' . Client::VERSION . ' PHP/' . \PHP_VERSION
            . ' curl/' . $curlVersion['version'];

        $this->assertSame($response['_SERVER']['HTTP_HOST'], $host, 'received expected host');
        $this->assertSame($response['_SERVER']['HTTP_USER_AGENT'], $userAgent, 'received expected user agent');
        $this->assertSame($response['_SERVER']['HTTP_ACCEPT'], 'application/json', 'received expected accept header');
        $this->assertSame($response['_SERVER']['CONTENT_TYPE'], 'application/json', 'received expected content type');
        $this->assertSame($response['_SERVER']['HTTP_AUTHORIZATION'], 'Basic ' . base64_encode($accountId . ':' . $licenseKey), 'received expected authorization header');
        $this->assertSame($response['_SERVER']['CONTENT_LENGTH'], '8', 'received expected content length');
        $this->assertSame($response['_SERVER']['REQUEST_METHOD'], 'POST', 'received expected http method');
        $this->assertSame($response['_SERVER']['REQUEST_URI'], '/mirror', 'received expected path');

        $this->assertSame($response['INPUT'], '["test"]', 'received expected body');
    }

    public function testCurlGet(): void
    {
        $accountId = 10;
        $licenseKey = '0123456789';
        $host = 'localhost:' . self::$port;
        $response = $this->runRequestTestServer(
            'TestService',
            '/mirror',
            [],
            $accountId,
            $licenseKey,
            [
                'host' => $host,
                'useHttps' => false,
            ],
            'get'
        );

        $curlVersion = curl_version();
        $userAgent = 'MaxMind-WS-API/' . Client::VERSION . ' PHP/' . \PHP_VERSION
            . ' curl/' . $curlVersion['version'];

        $this->assertSame($response['_SERVER']['HTTP_HOST'], $host, 'received expected host');
        $this->assertSame($response['_SERVER']['HTTP_USER_AGENT'], $userAgent, 'received expected user agent');
        $this->assertSame($response['_SERVER']['HTTP_ACCEPT'], 'application/json', 'received expected accept header');
        $this->assertSame($response['_SERVER']['HTTP_AUTHORIZATION'], 'Basic ' . base64_encode($accountId . ':' . $licenseKey), 'received expected authorization header');
        $this->assertSame($response['_SERVER']['REQUEST_METHOD'], 'GET', 'received expected http method');
        $this->assertSame($response['_SERVER']['REQUEST_URI'], '/mirror', 'received expected path');
    }

    // Sending a post request
    public function test200Post(): void
    {
        $this->assertSame(
            ['a' => 'b'],
            $this->withResponseTestServer(
                200,
                'application/json',
                '{"a":"b"}',
                'post'
            ),
            'received expected decoded response'
        );
    }

    // Sending a post request
    public function test200Get(): void
    {
        // Sending a get request
        $this->assertSame(
            ['a' => 'b'],
            $this->withResponseTestServer(
                200,
                'application/json',
                '{"a":"b"}',
                'get'
            ),
            'received expected decoded response'
        );
    }

    public function test204Post(): void
    {
        // Sending a post request
        $this->assertNull(
            $this->withResponseTestServer(
                204,
                'application/json',
                '',
                'post'
            ),
            'received expected empty response'
        );
    }

    public function test204Get(): void
    {
        // Sending a get request
        $this->assertNull(
            $this->withResponseTestServer(
                204,
                'application/json',
                '',
                'get'
            ),
            'received expected empty response'
        );
    }

    public function testOptions(): void
    {
        // Sending a post request
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

    public function test200PostWithInvalidJson(): void
    {
        $this->expectException(\MaxMind\Exception\WebServiceException::class);
        $this->expectExceptionMessage('Received a 200 response for TestService but could not decode the response as JSON: Syntax error. Body: {');

        $this->withResponseTestServer(200, 'application/json', '{', 'post');
    }

    public function test200GetWithInvalidJson(): void
    {
        $this->expectException(\MaxMind\Exception\WebServiceException::class);
        $this->expectExceptionMessage('Received a 200 response for TestService but could not decode the response as JSON: Syntax error. Body: {');

        $this->withResponseTestServer(200, 'application/json', '{', 'get');
    }

    // We can't test this with the test server because
    // the built-in php server doesn't let us send a body when the http status is 204.
    public function test204WithResponseBody(): void
    {
        $this->expectException(\MaxMind\Exception\WebServiceException::class);
        $this->expectExceptionMessage('Received a 204 response for TestService along with an unexpected HTTP body: non-empty response body');

        $this->withResponse(204, 'application/json', 'non-empty response body');
    }

    public function testGetInsufficientFunds(): void
    {
        $this->expectException(\MaxMind\Exception\InsufficientFundsException::class);
        $this->expectExceptionMessage('out of credit');

        $this->withResponseTestServer(
            402,
            'application/json',
            '{"code":"INSUFFICIENT_FUNDS","error":"out of credit"}',
            'post'
        );
    }

    public function testPostInsufficientFunds(): void
    {
        $this->expectException(\MaxMind\Exception\InsufficientFundsException::class);
        $this->expectExceptionMessage('out of credit');

        $this->withResponseTestServer(
            402,
            'application/json',
            '{"code":"INSUFFICIENT_FUNDS","error":"out of credit"}',
            'get'
        );
    }

    /**
     * @dataProvider invalidAuthCodes
     */
    public function testPostInvalidAuth(string $code): void
    {
        $this->expectException(\MaxMind\Exception\AuthenticationException::class);
        $this->expectExceptionMessage('Invalid auth');

        $this->withResponseTestServer(
            401,
            'application/json',
            '{"code":"' . $code . '","error":"Invalid auth"}',
            'post'
        );
    }

    /**
     * @dataProvider invalidAuthCodes
     */
    public function testGetInvalidAuth(string $code): void
    {
        $this->expectException(\MaxMind\Exception\AuthenticationException::class);
        $this->expectExceptionMessage('Invalid auth');

        $this->withResponseTestServer(
            401,
            'application/json',
            '{"code":"' . $code . '","error":"Invalid auth"}',
            'get'
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

    public function testGetPermissionRequired(): void
    {
        $this->expectException(\MaxMind\Exception\PermissionRequiredException::class);
        $this->expectExceptionMessage('Permission required');

        $this->withResponseTestServer(
            403,
            'application/json',
            '{"code":"PERMISSION_REQUIRED","error":"Permission required"}',
            'get'
        );
    }

    public function testPostPermissionRequired(): void
    {
        $this->expectException(\MaxMind\Exception\PermissionRequiredException::class);
        $this->expectExceptionMessage('Permission required');

        $this->withResponseTestServer(
            403,
            'application/json',
            '{"code":"PERMISSION_REQUIRED","error":"Permission required"}',
            'post'
        );
    }

    public function testPostInvalidRequest(): void
    {
        $this->expectException(\MaxMind\Exception\InvalidRequestException::class);
        $this->expectExceptionMessage('IP invalid');

        $this->withResponseTestServer(
            400,
            'application/json',
            '{"code":"IP_ADDRESS_INVALID","error":"IP invalid"}',
            'get'
        );
    }

    public function testGetInvalidRequest(): void
    {
        $this->expectException(\MaxMind\Exception\InvalidRequestException::class);
        $this->expectExceptionMessage('IP invalid');

        $this->withResponseTestServer(
            400,
            'application/json',
            '{"code":"IP_ADDRESS_INVALID","error":"IP invalid"}',
            'get'
        );
    }

    public function testPost400WithInvalidJson(): void
    {
        $this->expectException(\MaxMind\Exception\WebServiceException::class);
        $this->expectExceptionMessage('Received a 400 error for TestService but could not decode the response as JSON: Syntax error. Body: {"blah"}');

        $this->withResponseTestServer(400, 'application/json', '{"blah"}', 'post');
    }

    public function testGet400WithInvalidJson(): void
    {
        $this->expectException(\MaxMind\Exception\WebServiceException::class);
        $this->expectExceptionMessage('Received a 400 error for TestService but could not decode the response as JSON: Syntax error. Body: {"blah"}');

        $this->withResponseTestServer(400, 'application/json', '{"blah"}', 'get');
    }

    public function testPost400WithNoBody(): void
    {
        $this->expectException(\MaxMind\Exception\HttpException::class);
        $this->expectExceptionMessage('Received a 400 error for TestService with no body');

        $this->withResponseTestServer(400, 'application/json', '', 'post');
    }

    public function testGet400WithNoBody(): void
    {
        $this->expectException(\MaxMind\Exception\HttpException::class);
        $this->expectExceptionMessage('Received a 400 error for TestService with no body');

        $this->withResponseTestServer(400, 'application/json', '', 'get');
    }

    public function testPost400WithUnexpectedContentType(): void
    {
        $this->expectException(\MaxMind\Exception\HttpException::class);
        $this->expectExceptionMessage('Received a 400 error for TestService with the following body: text');

        $this->withResponseTestServer(400, 'text/plain', 'text', 'post');
    }

    public function testGet400WithUnexpectedContentType(): void
    {
        $this->expectException(\MaxMind\Exception\HttpException::class);
        $this->expectExceptionMessage('Received a 400 error for TestService with the following body: text');

        $this->withResponseTestServer(400, 'text/plain', 'text', 'get');
    }

    public function testPost400WithUnexpectedJson(): void
    {
        $this->expectException(\MaxMind\Exception\HttpException::class);
        $this->expectExceptionMessage('Error response contains JSON but it does not specify code or error keys: {"not":"expected"}');

        $this->withResponseTestServer(400, 'application/json', '{"not":"expected"}', 'post');
    }

    public function testGet400WithUnexpectedJson(): void
    {
        $this->expectException(\MaxMind\Exception\HttpException::class);
        $this->expectExceptionMessage('Error response contains JSON but it does not specify code or error keys: {"not":"expected"}');

        $this->withResponseTestServer(400, 'application/json', '{"not":"expected"}', 'get');
    }

    public function testPost300(): void
    {
        $this->expectException(\MaxMind\Exception\HttpException::class);
        $this->expectExceptionMessage('Received an unexpected HTTP status (300) for TestService');

        $this->withResponseTestServer(300, 'application/json', '', 'post');
    }

    public function testGet300(): void
    {
        $this->expectException(\MaxMind\Exception\HttpException::class);
        $this->expectExceptionMessage('Received an unexpected HTTP status (300) for TestService');

        $this->withResponseTestServer(300, 'application/json', '', 'get');
    }

    public function testPost500(): void
    {
        $this->expectException(\MaxMind\Exception\HttpException::class);
        $this->expectExceptionMessage('Received a server error (500) for TestService');

        $this->withResponseTestServer(500, 'application/json', '', 'post');
    }

    public function testGet500(): void
    {
        $this->expectException(\MaxMind\Exception\HttpException::class);
        $this->expectExceptionMessage('Received a server error (500) for TestService');

        $this->withResponseTestServer(500, 'application/json', '', 'get');
    }

    // Convenience method when you don't care about the request
    // It runs the request through the test server.
    // This version is used for when we want to test with an actual server.
    private function withResponseTestServer(int $statusCode, string $contentType, string $body, string $httpMethod): ?array
    {
        // Set up the test server
        $response = [
            'status' => $statusCode,
            'body' => $body,
            'contentType' => $contentType,
        ];
        self::addResponseInQueue(json_encode($response));

        return $this->runRequestTestServer(
            'TestService',
            '/path',
            [],
            10,
            '0123456789',
            [
                'host' => 'localhost:' . self::$port,
                'useHttps' => false,
            ],
            $httpMethod
        );
    }

    // runs the request through the test server
    private function runRequestTestServer(
        string $service,
        string $path,
        array $requestContent,
        int $accountId,
        string $licenseKey,
        array $options,
        string $httpMethod
    ): ?array {
        $client = new Client(
            $accountId,
            $licenseKey,
            $options
        );

        if ($httpMethod === 'get') {
            return $client->get($service, $path);
        }

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
