<?php

namespace ChopShop\Selector\Parser;

use ChopShop\Exception\MalformedSelectorException;
use ChopShop\Selector\FilterFunctionCall;
use ChopShop\Selector\Selector;

/**
 * Class SelectorParser
 * @package ChopShop\Selector\Parser
 *
 * Mimics the behavior of npm package 'x-ray-parse'
 */
class SelectorParser
{
    // blatantly stolen from x-ray-parse
    const REGEX_SELECTOR = '/^([^@]*)(?:@\s*([\w-_:]+))?$/';
    const REGEX_FILTERS = '/\s*\|(?!\=)\s*/';

    const ATTRIBUTE_NAME_INNER_HTML = 'html';

    /**
     * @param string|array $definition
     * @return Selector
     * @throws MalformedSelectorException
     */
    public static function parse($definition)
    {
        $selector = new Selector();

        if (is_array($definition)) {
            $selector->setMultiple(true);

            if (key($definition) !== null && is_string(key($definition))) {
                $childDefinitions = reset($definition);
                $definition = key($definition);

                foreach ($childDefinitions as $key => $childDefinition) {
                    $selector->addChild($key, self::parse($childDefinition));
                }
            } else {
                $definition = reset($definition);
            }
        }

        $filters = preg_split(self::REGEX_FILTERS, $definition);
        $definitionWithoutFilters = array_shift($filters);
        $matches = [];

        preg_match(self::REGEX_SELECTOR, $definitionWithoutFilters, $matches);

        if (!isset($matches[1])) {
            throw new MalformedSelectorException();
        }

        $selectorString = trim($matches[1]);

        if ($selectorString !== '') {
            $selector->setSelector($selectorString);
        }

        if (isset($matches[2])) {
            $attribute = $matches[2];

            if (strtolower($attribute) === self::ATTRIBUTE_NAME_INNER_HTML) {
                $selector->setTarget(Selector::TARGET_INNER_HTML);
            } else {
                $selector->setTarget(Selector::TARGET_ATTRIBUTE);
                $selector->setAttribute($attribute);
            }
        } else {
            $selector->setTarget(Selector::TARGET_TEXT);
        }

        if (count($filters) > 0) {
            $parsedFormats = FormatParser::parse(implode('|', $filters));

            $filterFunctionCalls = [];

            foreach ($parsedFormats as $parsedFormat) {
                $filterFunctionCall = new FilterFunctionCall($parsedFormat['name'], $parsedFormat['args']);
                $filterFunctionCalls[] = $filterFunctionCall;
            }

            $selector->setFilters($filterFunctionCalls);
        }

        return $selector;
    }
}
