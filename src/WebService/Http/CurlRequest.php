<?php

namespace MaxMind\WebService\Http;

/**
 * This class is for internal use only. Semantic versioning does not not apply.
 * @package MaxMind\WebService\Http
 * @internal
 */
class CurlRequest implements Request
{
    private $url;
    private $options;

    /**
     * @param $url
     * @param $options
     */
    public function __construct($url, $options)
    {
        $this->url = $url;
        $this->options = $options;
    }

    /**
     * @param $body
     * @return array
     */
    public function post($body)
    {
        $curl = $this->createCurl();

        curl_setopt($curl, CURLOPT_POST, true);
        curl_setopt($curl, CURLOPT_POSTFIELDS, $body);

        return $this->execute(curl);
    }

    public function get()
    {
        $curl = $this->createCurl();

        curl_setopt($curl, CURLOPT_HTTPGET, true);

        return $this->execute($curl);
    }

    /**
     * @return resource
     */
    private function createCurl()
    {
        $curl = curl_init($this->url);

        $opts[CURLOPT_CAINFO] = $this->options['caBundle'];
        $opts[CURLOPT_SSL_VERIFYHOST] = 2;
        $opts[CURLOPT_FOLLOWLOCATION] = false;
        $opts[CURLOPT_SSL_VERIFYPEER] = true;
        $opts[CURLOPT_RETURNTRANSFER] = true;


        $opts[CURLOPT_HTTPHEADER] = $this->options['headers'];
        $opts[CURLOPT_USERAGENT] = $this->options['userAgent'];

        $opts[CURLOPT_CONNECTTIMEOUT] = $this->options['connectTimeout'];
        $opts[CURLOPT_TIMEOUT] = $this->options['timeout'];

        curl_setopt_array($curl, $opts);
        return $curl;
    }

    private function execute($curl)
    {
        $body = curl_exec($curl);
        $statusCode = curl_getinfo($curl, CURLINFO_HTTP_CODE);
        $contentType = curl_getinfo($curl, CURLINFO_CONTENT_TYPE);
        curl_close($curl);

        return array($statusCode, $contentType, $body);
    }
}
