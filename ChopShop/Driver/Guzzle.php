<?php

namespace ChopShop\Driver;

use GuzzleHttp\Client;

class Guzzle implements DriverInterface
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * Guzzle constructor.
     * @param array $options
     */
    public function __construct(array $options = [])
    {
        $this->client = new Client($options);
    }

    /**
     * @param $url
     * @return string
     * @throws \RuntimeException
     */
    public function get($url)
    {
        $response = $this->client->get($url);

        return $response->getBody()->getContents();
    }
}
