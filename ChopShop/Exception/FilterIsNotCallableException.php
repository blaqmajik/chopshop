<?php

namespace ChopShop\Exception;

/**
 * Class NotCallableException
 * @package ChopShop\Exception
 *
 * FilterIsNotCallableException is thrown when a filter function supplied during initialization is not callable.
 */
class FilterIsNotCallableException extends \InvalidArgumentException implements ChopShopException
{
}
