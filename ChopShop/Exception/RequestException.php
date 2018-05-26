<?php

namespace ChopShop\Exception;

/**
 * Class RequestException
 * @package ChopShop\Exception
 *
 * RequestException is thrown when the Client does not return a successful response.
 */
class RequestException extends \RuntimeException implements ChopShopException
{
}
