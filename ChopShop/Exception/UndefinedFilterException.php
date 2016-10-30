<?php

namespace ChopShop\Exception;

/**
 * Class UndefinedFilterException
 * @package ChopShop\Exception
 *
 * UndefinedFilterException is thrown when a filter that should be applied to the result of a selection is not defined
 * or not callable.
 */
class UndefinedFilterException extends \BadFunctionCallException implements ChopShopException
{
}
