<?php

namespace ChopShop;

use ChopShop\Driver\DriverInterface;
use ChopShop\Driver\Guzzle;
use ChopShop\Exception\FilterIsNotCallableException;
use ChopShop\Exception\InvalidDriverException;
use ChopShop\Exception\MalformedSelectorException;
use ChopShop\Exception\MoreThanOneMatchFoundException;
use ChopShop\Exception\UndefinedFilterException;
use ChopShop\Selector\FilterFunctionCall;
use ChopShop\Selector\Selector;
use ChopShop\Selector\Parser\SelectorParser;
use GuzzleHttp\Psr7\Uri;
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
     * @var DriverInterface|null
     */
    protected $driver;

    /**
     * @var string|null
     */
    protected $url;

    /**
     * Scraper constructor.
     * @param array $options
     * @throws FilterIsNotCallableException|\InvalidArgumentException
     */
    public function __construct(array $options = [])
    {
        $this->resolveOptions($options);
    }

    /**
     * @param array $options
     * @throws FilterIsNotCallableException|\InvalidArgumentException
     */
    protected function resolveOptions(array $options = [])
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

        if (array_key_exists('driver', $options)) {
            $driver = $options['driver'];

            if ($driver instanceof DriverInterface) {
                $this->driver = $driver;
            } elseif ($driver !== null) {
                throw new InvalidDriverException('The given driver does not implement the required interface.');
            }
        }
    }

    /**
     * @param $source
     * @param string[] $selectorDefinitions
     * @param string|null $paginate
     * @param int|null $limit
     * @return array
     * @throws MalformedSelectorException|MoreThanOneMatchFoundException|UndefinedFilterException
     */
    public function scrape($source, array $selectorDefinitions = [], $paginate = null, $limit = null)
    {
        if (filter_var($source, FILTER_VALIDATE_URL)) {
            $this->parse($this->request($source));
        } else {
            $this->parse($source);
        }

        $result = [];

        foreach ($selectorDefinitions as $key => $definition) {
            $selector = SelectorParser::parse($definition);
            $result[$key] = $this->select($selector);
        }

        if ($paginate !== null) {
            $numberOfPages = 1;

            while ($limit === null || $numberOfPages < $limit) {
                $linkToNextPage = $this->select(SelectorParser::parse($paginate));

                if ($linkToNextPage === null) {
                    break;
                }

                $nextPageResult = $this->scrape($linkToNextPage, $selectorDefinitions);

                $result = array_merge_recursive($result, $nextPageResult);
                $numberOfPages++;
            }
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
     * @throws MoreThanOneMatchFoundException|UndefinedFilterException
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
            $value = $node->attr($selector->getAttribute());

            if ($this->url !== null && in_array($selector->getAttribute(), ['href', 'src'])) {
                return (string) Uri::resolve(new Uri($this->url), new Uri($value));
            } else {
                return $value;
            }
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
    protected function applyFilters($subject, array $filters = [])
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

    /**
     * @param $url
     * @return string
     */
    protected function request($url)
    {
        if ($this->driver === null) {
            $this->driver = new Guzzle();
        }

        $this->url = $url;

        return $this->driver->get($url);
    }
}
