<?php

declare(strict_types=1);

namespace MaxMind\WebService\Http;

use MaxMind\Exception\HttpException;

/**
 * This class is for internal use only. Semantic versioning does not not apply.
 *
 * @internal
 */
class CurlRequest implements Request
{
    /**
     * @var \CurlHandle
     */
    private $ch;

    /**
     * @var string
     */
    private $url;

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
    private $options;

    /**
     * @param array{
     *     caBundle?: string,
     *     connectTimeout: float|int,
     *     curlHandle: \CurlHandle,
     *     headers: array<int, string>,
     *     proxy: string|null,
     *     timeout: float|int,
     *     userAgent: string
     * } $options
     */
    public function __construct(string $url, array $options)
    {
        $this->url = $url;
        $this->options = $options;
        $this->ch = $options['curlHandle'];
    }

    /**
     * @throws HttpException
     *
     * @return array{0:int, 1:string|null, 2:string|null}
     */
    public function post(string $body): array
    {
        $curl = $this->createCurl();

        curl_setopt($curl, \CURLOPT_POST, true);
        curl_setopt($curl, \CURLOPT_POSTFIELDS, $body);

        return $this->execute($curl);
    }

    /**
     * @return array{0:int, 1:string|null, 2:string|null}
     */
    public function get(): array
    {
        $curl = $this->createCurl();

        curl_setopt($curl, \CURLOPT_HTTPGET, true);

        return $this->execute($curl);
    }

    /**
     * @return \CurlHandle
     */
    private function createCurl()
    {
        curl_reset($this->ch);

        $opts = [];
        $opts[\CURLOPT_URL] = $this->url;

        if (!empty($this->options['caBundle'])) {
            $opts[\CURLOPT_CAINFO] = $this->options['caBundle'];
        }

        $opts[\CURLOPT_ENCODING] = '';
        $opts[\CURLOPT_SSL_VERIFYHOST] = 2;
        $opts[\CURLOPT_FOLLOWLOCATION] = false;
        $opts[\CURLOPT_SSL_VERIFYPEER] = true;
        $opts[\CURLOPT_RETURNTRANSFER] = true;

        $opts[\CURLOPT_HTTPHEADER] = $this->options['headers'];
        $opts[\CURLOPT_USERAGENT] = $this->options['userAgent'];
        $opts[\CURLOPT_PROXY] = $this->options['proxy'];

        $connectTimeout = $this->options['connectTimeout'];
        $opts[\CURLOPT_CONNECTTIMEOUT_MS] = (int) ceil($connectTimeout * 1000);

        $timeout = $this->options['timeout'];
        $opts[\CURLOPT_TIMEOUT_MS] = (int) ceil($timeout * 1000);

        curl_setopt_array($this->ch, $opts);

        return $this->ch;
    }

    /**
     * @param \CurlHandle $curl
     *
     * @throws HttpException
     *
     * @return array{0:int, 1:string|null, 2:string|null}
     */
    private function execute($curl): array
    {
        $body = curl_exec($curl);
        if ($errno = curl_errno($curl)) {
            $errorMessage = curl_error($curl);

            throw new HttpException(
                "cURL error ({$errno}): {$errorMessage}",
                0,
                $this->url
            );
        }

        $statusCode = curl_getinfo($curl, \CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($curl, \CURLINFO_CONTENT_TYPE);

        return [
            $statusCode,
            // The PHP docs say "Content-Type: of the requested document. NULL
            // indicates server did not send valid Content-Type: header" for
            // CURLINFO_CONTENT_TYPE. However, it will return FALSE if no header
            // is set. To keep our types simple, we return null in this case.
            $contentType === false ? null : $contentType,
            $body,
        ];
    }
}
