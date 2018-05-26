<?php

namespace ChopShop\Selector;

/**
 * Class Selector
 * @package ChopShop\Selector
 */
class Selector
{
    const TARGET_TEXT = 'text';
    const TARGET_ATTRIBUTE = 'attribute';
    const TARGET_INNER_HTML = 'innerHtml';
    const TARGET_OUTER_HTML = 'outerHtml';

    /**
     * @var string
     */
    protected $selector = null;

    /**
     * @var string
     */
    protected $target = null;

    /**
     * @var string
     */
    protected $attribute = null;

    /**
     * @var array
     */
    protected $filters = [];

    /**
     * @var bool
     */
    protected $multiple = false;

    /**
     * @var Selector[]
     */
    protected $children = [];

    /**
     * Selector constructor.
     */
    public function __construct()
    {
    }

    /**
     * @return mixed
     */
    public function getSelector()
    {
        return $this->selector;
    }

    /**
     * @param string $selector
     */
    public function setSelector($selector)
    {
        $this->selector = $selector;
    }

    /**
     * @return string
     */
    public function getTarget()
    {
        return $this->target;
    }

    /**
     * @param string $target
     */
    public function setTarget($target)
    {
        $this->target = $target;
    }

    /**
     * @return bool
     */
    public function targetIsText()
    {
        return $this->target === self::TARGET_TEXT;
    }

    /**
     * @return bool
     */
    public function targetIsAttribute()
    {
        return $this->target === self::TARGET_ATTRIBUTE;
    }

    /**
     * @return bool
     */
    public function targetIsInnerHtml()
    {
        return $this->target === self::TARGET_INNER_HTML;
    }

    /**
     * @return bool
     */
    public function targetIsOuterHtml()
    {
        return $this->target === self::TARGET_OUTER_HTML;
    }
    
    /**
     * @return string
     */
    public function getAttribute()
    {
        return $this->attribute;
    }

    /**
     * @param string $attribute
     */
    public function setAttribute($attribute)
    {
        $this->attribute = $attribute;
    }

    /**
     * @return array
     */
    public function getFilters()
    {
        return $this->filters;
    }

    /**
     * @param array $filters
     */
    public function setFilters($filters)
    {
        $this->filters = $filters;
    }

    /**
     * @return boolean
     */
    public function isMultiple()
    {
        return $this->multiple;
    }

    /**
     * @param boolean $multiple
     */
    public function setMultiple($multiple)
    {
        $this->multiple = $multiple;
    }

    /**
     * @return Selector[]
     */
    public function getChildren()
    {
        return $this->children;
    }

    /**
     * @param Selector[] $children
     */
    public function setChildren($children)
    {
        $this->children = $children;
    }

    /**
     * @return bool
     */
    public function hasChildren()
    {
        return count($this->children) > 0;
    }

    /**
     * @param string $key
     * @param Selector $child
     */
    public function addChild($key, Selector $child)
    {
        $this->children[$key] = $child;
    }
}
