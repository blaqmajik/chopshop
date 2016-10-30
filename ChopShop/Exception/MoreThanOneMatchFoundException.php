<?php

namespace ChopShop\Exception;

/**
 * Class MoreThanOneMatchFoundException
 * @package ChopShop\Exception
 *
 * MoreThanOneMatchFoundException is thrown when a Selector expects a single match but more than one match is found.
 */
class MoreThanOneMatchFoundException extends \UnexpectedValueException implements ChopShopException
{
}
