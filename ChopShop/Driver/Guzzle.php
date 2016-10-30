<?php

namespace ChopShop\Driver;

use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;

class Guzzle implements DriverInterface
{
    /**
     * @var Client
     */
    protected $client;

    /**
     * @var int
     */
    protected $delay = 0;

    /**
     * @var int
     */
    protected $numberOfRequests = 0;

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
        if ($this->numberOfRequests > 0 && $this->delay > 0) {
            usleep($this->delay * 1000);
        }

        try {
            $response = $this->client->get($url);
        } catch (RequestException $e) {
            $response = $e->getResponse();

            if ($response === null) {
                throw $e;
            }
        }

        $this->numberOfRequests++;

        return $response->getBody()->getContents();
    }

    /**
     * @param int $delay
     */
    public function setDelay($delay)
    {
        $this->delay = $delay;
    }
}
