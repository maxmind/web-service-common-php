<?php

declare(strict_types=1);

namespace MaxMind\WebService\Http;

/**
 * Class RequestFactory.
 *
 * @internal
 */
class RequestFactory
{
    /**
     * Keep the cURL resource here, so that if there are multiple API requests
     * done the connection is kept alive, SSL resumption can be used
     * etcetera.
     */
    private ?\CurlHandle $ch = null;

    /**
     * @throws \RuntimeException if the cURL handle cannot be initialized
     */
    private function getCurlHandle(): \CurlHandle
    {
        if ($this->ch === null) {
            $ch = curl_init();
            if ($ch === false) {
                throw new \RuntimeException('Unable to initialize cURL handle');
            }
            $this->ch = $ch;
        }

        return $this->ch;
    }

    /**
     * @param array<string, mixed> $options
     *
     * @throws \RuntimeException if the cURL handle cannot be initialized
     */
    public function request(string $url, array $options): Request
    {
        $options['curlHandle'] = $this->getCurlHandle();

        // @phpstan-ignore argument.type (options array is built dynamically by Client)
        return new CurlRequest($url, $options);
    }
}
