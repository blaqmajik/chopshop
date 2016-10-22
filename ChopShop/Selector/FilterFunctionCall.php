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
    protected $name;

    /**
     * @var array
     */
    protected $arguments = [];

    /**
     * Filter constructor.
     * @param string $name
     * @param array $arguments
     */
    public function __construct($name, $arguments = [])
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
