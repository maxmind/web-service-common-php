<?php

namespace MaxMind\Test\WebService;

use Composer\CaBundle\CaBundle;
use MaxMind\WebService\Client;

/**
 * @coversNothing
 */
class ClientTest extends \PHPUnit_Framework_TestCase
{
    public function test200()
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

    public function testOptions()
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

    /**
     * @expectedException \MaxMind\Exception\WebServiceException
     * @expectedExceptionMessage Received a 200 response for TestService but could not decode the response as JSON: Syntax error. Body: {
     */
    public function test200WithInvalidJson()
    {
        $this->withResponse(200, 'application/json', '{');
    }

    /**
     * @expectedException \MaxMind\Exception\InsufficientFundsException
     * @expectedExceptionMessage out of credit
     */
    public function testInsufficientFunds()
    {
        $this->withResponse(
            402,
            'application/json',
            '{"code":"INSUFFICIENT_FUNDS","error":"out of credit"}'
        );
    }

    /**
     * @expectedException \MaxMind\Exception\AuthenticationException
     * @expectedExceptionMessage Invalid auth
     * @dataProvider invalidAuthCodes
     *
     * @param mixed $code
     */
    public function testInvalidAuth($code)
    {
        $this->withResponse(
            401,
            'application/json',
            '{"code":"' . $code . '","error":"Invalid auth"}'
        );
    }

    public function invalidAuthCodes()
    {
        return [
            ['AUTHORIZATION_INVALID'],
            ['LICENSE_KEY_REQUIRED'],
            ['USER_ID_REQUIRED'],
            ['USER_ID_UNKNOWN'],
        ];
    }

    /**
     * @expectedException \MaxMind\Exception\PermissionRequiredException
     * @expectedExceptionMessage Permission required
     */
    public function testPermissionRequired()
    {
        $this->withResponse(
            403,
            'application/json',
            '{"code":"PERMISSION_REQUIRED","error":"Permission required"}'
        );
    }

    /**
     * @expectedException \MaxMind\Exception\InvalidRequestException
     * @expectedExceptionMessage IP invalid
     */
    public function testInvalidRequest()
    {
        $this->withResponse(
            400,
            'application/json',
            '{"code":"IP_ADDRESS_INVALID","error":"IP invalid"}'
        );
    }

    /**
     * @expectedException \MaxMind\Exception\WebServiceException
     * @expectedExceptionMessage Received a 400 error for TestService but could not decode the response as JSON: Syntax error. Body: {"blah"}
     */
    public function test400WithInvalidJson()
    {
        $this->withResponse(400, 'application/json', '{"blah"}');
    }

    /**
     * @expectedException \MaxMind\Exception\HttpException
     * @expectedExceptionMessage Received a 400 error for TestService with no body
     */
    public function test400WithNoBody()
    {
        $this->withResponse(400, 'application/json', '');
    }

    /**
     * @expectedException \MaxMind\Exception\HttpException
     * @expectedExceptionMessage Received a 400 error for TestService with the following body: text
     */
    public function test400WithUnexpectedContentType()
    {
        $this->withResponse(400, 'text/plain', 'text');
    }

    /**
     * @expectedException \MaxMind\Exception\HttpException
     * @expectedExceptionMessage Error response contains JSON but it does not specify code or error keys: {"not":"expected"}
     */
    public function test400WithUnexpectedJson()
    {
        $this->withResponse(400, 'application/json', '{"not":"expected"}');
    }

    /**
     * @expectedException \MaxMind\Exception\HttpException
     * @expectedExceptionMessage Received an unexpected HTTP status (300) for TestService
     */
    public function test300()
    {
        $this->withResponse(300, 'application/json', '');
    }

    /**
     * @expectedException \MaxMind\Exception\HttpException
     * @expectedExceptionMessage Received a server error (500) for TestService
     */
    public function test500()
    {
        $this->withResponse(500, 'application/json', '');
    }

    // convenience method when you don't care about the request
    private function withResponse($statusCode, $contentType, $body)
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
        $service,
        $path,
        $requestContent,
        $statusCode,
        $contentType,
        $responseBody,
        $userId = 10,
        $licenseKey = '0123456789',
        $options = []
    ) {
        $stub = $this->getMockForAbstractClass(
            'MaxMind\\WebService\\Http\\Request'
        );

        $stub->expects($this->once())
            ->method('post')
            ->with($this->equalTo(json_encode($requestContent)))
            ->willReturn([$statusCode, $contentType, $responseBody]);

        $factory = $this->getMockBuilder(
            'MaxMind\\WebService\\Http\\RequestFactory'
        )->getMock();

        $host = isset($options['host']) ? $options['host'] : 'api.maxmind.com';

        $url = 'https://' . $host . $path;

        $headers = [
            'Content-Type: application/json',
            'Authorization: Basic '
            . base64_encode($userId . ':' . $licenseKey),
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
            $userId,
            $licenseKey,
            $options
        );

        return $client->post($service, $path, $requestContent);
    }
}
