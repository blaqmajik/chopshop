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
use Masterminds\HTML5;
use Symfony\Component\CssSelector\CssSelectorConverter;

/**
 * Class Scraper
 * @package ChopShop
 */
class Scraper
{
    /**
     * @var \DOMDocument[]
     */
    protected $dom = [];

    /**
     * @var array
     */
    protected $filters = [];

    /**
     * @var HTML5
     */
    protected $htmlParser;

    /**
     * @var CssSelectorConverter
     */
    protected $cssSelectorConverter;

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
        $this->htmlParser = new HTML5(['disable_html_ns' => true]);
        $this->cssSelectorConverter = new CssSelectorConverter();

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
        $this->dom[] = $this->htmlParser->loadHTML($html);
    }

    /**
     * @param Selector $selector
     * @return array|null|string
     * @throws MoreThanOneMatchFoundException|UndefinedFilterException
     */
    protected function select(Selector $selector)
    {
        $xPath = new \DOMXPath(end($this->dom));
        $selectorAsXPath = $this->cssSelectorConverter->toXPath($selector->getSelector());

        $foundNodes = $xPath->query($selectorAsXPath);

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

            /** @var \DOMElement $node */
            $node = $foundNodes->item(0);
            $result = $this->selectTarget($node, $selector);
        } else {
            $result = [];

            if ($selector->hasChildren()) {
                $subResult = [];

                foreach ($foundNodes as $node) {
                    $nodeDocument = new \DOMDocument();
                    $nodeDocument->appendChild($nodeDocument->importNode($node, true));
                    $this->dom[] = $nodeDocument;

                    foreach ($selector->getChildren() as $key => $childSelector) {
                        $subResult[$key] = $this->select($childSelector);
                    }

                    $result[] = $subResult;
                    array_pop($this->dom);
                }
            } else {
                foreach ($foundNodes as $node) {
                    $result[] = $this->selectTarget($node, $selector);
                }
            }
        }

        return $this->applyFilters($result, $selector->getFilters());
    }

    /**
     * @param \DOMElement $node
     * @param Selector $selector
     * @return mixed|null|string
     */
    protected function selectTarget(\DOMElement $node, Selector $selector)
    {
        if ($selector->targetIsText()) {
            return $node->textContent;
        } elseif ($selector->targetIsAttribute()) {
            $value = $node->getAttribute($selector->getAttribute());

            if ($this->url !== null && in_array($selector->getAttribute(), ['href', 'src'], true)) {
                $uri = new Uri($value);

                if (Uri::isAbsolute($uri)) {
                    return (string) $uri;
                }

                // prepend missing leading slash to relative paths, if neccessary
                if (strpos($value, '/') !== 0) {
                    $uri = new Uri('/' . $value);
                }

                return (string) Uri::resolve(new Uri($this->url), $uri);
            } else {
                return $value;
            }
        } elseif ($selector->targetIsInnerHtml()) {
            $html = '';

            foreach ($node->childNodes as $childNode) {
                $html .= $node->ownerDocument->saveHTML($childNode);
            }

            return trim($html);
        } elseif ($selector->targetIsOuterHtml()) {
            $html = $node->ownerDocument->saveHTML($node);

            return trim($html);
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
