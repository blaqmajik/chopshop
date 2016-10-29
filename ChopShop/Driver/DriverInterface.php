<?php

namespace ChopShop\Driver;

interface DriverInterface
{
    /**
     * @param string $url
     * @return string|null
     */
    public function get($url);
    
    /**
     * @param int $delay
     */
    public function setDelay($delay);
}
