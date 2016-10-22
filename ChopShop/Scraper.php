<?php

namespace ChopShop;

use ChopShop\Exception\FilterIsNotCallableException;
use ChopShop\Exception\MoreThanOneMatchFoundException;
use ChopShop\Exception\UndefinedFilterException;
use ChopShop\Selector\FilterFunctionCall;
use ChopShop\Selector\Selector;
use ChopShop\Selector\Parser\SelectorParser;
use QueryPath\DOMQuery;

/**
 * Class Scraper
 * @package ChopShop
 */
class Scraper
{
    /**
     * @var DOMQuery[]
     */
    protected $dom = [];

    /**
     * @var array
     */
    protected $filters = [];

    /**
     * Scraper constructor.
     * @param array $options
     * @throws FilterIsNotCallableException
     */
    public function __construct($options = [])
    {
        if (array_key_exists('filters', $options)) {
            foreach ($options['filters'] as $name => $function) {
                if (is_callable($function)) {
                    $this->filters[$name] = $function;
                } else {
                    throw new FilterIsNotCallableException(sprintf('Filter "%s" is not callable.', $name));
                }
            }
        }
    }

    /**
     * @param $html
     * @param string[] $selectorDefinitions
     * @return array
     */
    public function scrape($html, $selectorDefinitions = [])
    {
        $this->parse($html);

        $result = [];

        foreach ($selectorDefinitions as $key => $definition) {
            $selector = SelectorParser::parse($definition);

            $result[$key] = $this->select($selector);
        }

        return $result;
    }

    /**
     * @param string $html
     */
    protected function parse($html)
    {
        $this->dom[] = \QueryPath::withHTML5($html);
    }

    /**
     * @param Selector $selector
     * @return array|null|string
     * @throws MoreThanOneMatchFoundException
     */
    protected function select(Selector $selector)
    {
        /** @var DOMQuery $latestDom */
        $latestDom = end($this->dom);

        $foundNodes = $latestDom->find($selector->getSelector());

        if ($foundNodes->length === 0) {
            if (!$selector->isMultiple()) {
                return null;
            } else {
                return [];
            }
        }

        if (!$selector->isMultiple()) {
            if ($foundNodes->length > 1) {
                throw new MoreThanOneMatchFoundException(
                    sprintf('One match expected but %d matches found for selector "%s".', $foundNodes->length,
                        $selector->getSelector())
                );
            }

            $node = $foundNodes;
            $result = $this->selectTarget($node, $selector);
        } else {
            $result = [];

            if ($selector->hasChildren()) {
                $subResult = [];

                foreach ($foundNodes as $node) {
                    $this->dom[] = $node;

                    foreach ($selector->getChildren() as $key => $childSelector) {
                        $subResult[$key] = $this->select($childSelector);
                    }

                    $result[] = $subResult;

                    array_pop($this->dom);
                }
            } else {
                /** @var DOMQuery $node */
                foreach ($foundNodes as $node) {
                    $result[] = $this->selectTarget($node, $selector);
                }
            }
        }

        return $this->applyFilters($result, $selector->getFilters());
    }

    /**
     * @param DOMQuery $node
     * @param Selector $selector
     * @return mixed|null|string
     */
    protected function selectTarget(DOMQuery $node, Selector $selector)
    {
        if ($selector->targetIsText()) {
            return $node->text();
        } elseif ($selector->targetIsAttribute()) {
            return $node->attr($selector->getAttribute());
        } elseif ($selector->targetIsInnerHtml()) {
            return trim($node->innerHTML5());
        }
    }

    /**
     * @param $subject
     * @param FilterFunctionCall[] $filters
     * @return string|array
     * @throws UndefinedFilterException
     */
    protected function applyFilters($subject, $filters = [])
    {
        foreach ($filters as $filter) {
            if (array_key_exists($filter->getName(), $this->filters)) {
                $callable = $this->filters[$filter->getName()];
            } else {
                $callable = $filter->getName();
            }

            if (!is_callable($callable)) {
                throw new UndefinedFilterException(sprintf('Filter "%s" is not defined.', $filter->getName()));
            }

            if (is_array($subject)) {
                foreach ($subject as $key => $value) {
                    $subject[$key] = call_user_func_array($callable, array_merge([$value], $filter->getArguments()));
                }
            } else {
                $subject = call_user_func_array($callable, array_merge([$subject], $filter->getArguments()));
            }
        }

        return $subject;
    }
}
