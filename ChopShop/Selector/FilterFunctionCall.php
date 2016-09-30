<?php

namespace ChopShop\Selector;

/**
 * Class Filter
 * @package ChopShop\Selector
 */
class FilterFunctionCall
{
    /**
     * @var string
     */
    protected $name = null;

    /**
     * @var array
     */
    protected $arguments = [];

    /**
     * Filter constructor.
     * @param null $name
     * @param array $arguments
     */
    public function __construct($name = null, $arguments = [])
    {
        $this->setName($name);
        $this->setArguments($arguments);
    }

    /**
     * @return string
     */
    public function getName()
    {
        return $this->name;
    }

    /**
     * @param string $name
     */
    public function setName($name)
    {
        $this->name = $name;
    }

    /**
     * @return array
     */
    public function getArguments()
    {
        return $this->arguments;
    }

    /**
     * @param array $arguments
     */
    public function setArguments($arguments)
    {
        $this->arguments = $arguments;
    }
}
