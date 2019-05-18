<?php

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
     *
     * @var resource
     */
    private $ch;

    public function __construct()
    {
        $this->ch = curl_init();
    }

    public function __destruct()
    {
        curl_close($this->ch);
    }

    /**
     * @param $url
     * @param $options
     *
     * @return Request
     */
    public function request($url, $options)
    {
        $request = new CurlRequest($url, $options);
        $request->setCurlHandle($this->ch);

        return $request;
    }
}
