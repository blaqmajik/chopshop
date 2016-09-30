<?php

namespace ChopShop\Selector;

use ChopShop\Exception\SelectorParserException;

/**
 * Class SelectorParser
 * @package ChopShop\Selector
 *
 * Mimics the behavior of npm packages lapwinglabs/x-ray-parse and component/format-parser
 */
class SelectorParser
{
    // blatantly stolen from x-ray-parse
    const REGEX_SELECTOR = '/^([^@]*)(?:@\s*([\w-_:]+))?$/';
    const REGEX_FILTERS = '/\s*\|(?!\=)\s*/';

    // shamelessly borrowed from format-parser
    const REGEX_FORMAT_SPLIT = '/\s*\|\s*/';
    const REGEX_FORMAT_ARGUMENTS = '/"([^"]*)"|\'([^\']*)\'|([^\s\t,]+)/';

    const ATTRIBUTE_NAME_INNER_HTML = 'html';

    /**
     * @param string|array $definition
     * @return Selector
     * @throws SelectorParserException
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
            throw new SelectorParserException('Malformed selector');
        }

        $selector->setSelector(trim($matches[1]));

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
            $parsedFilters = self::parseFormat(implode('|', $filters));

            $selector->setFilters($parsedFilters);
        }

        return $selector;
    }

    /**
     * @param $string
     * @return array
     */
    protected static function parseFormat($string)
    {
        $filters = [];

        foreach (preg_split(self::REGEX_FORMAT_SPLIT, $string) as $filter) {
            $parts = explode(':', $filter);
            $name = array_shift($parts);
            $arguments = self::parseArguments(implode(':', $parts));

            $filters[] = new FilterFunctionCall($name, $arguments);
        }

        return $filters;
    }

    /**
     * @param $string
     * @return array
     */
    protected static function parseArguments($string)
    {
        $arguments = [];
        $allMatches = [];

        preg_match_all(self::REGEX_FORMAT_ARGUMENTS, $string, $allMatches);

        // pretty sure there's a much more elegant way to do this, just not today!
        if (!empty($allMatches)) {
            array_shift($allMatches);

            foreach ($allMatches as $matches) {
                if (count($matches) > 0 && !is_null($matches[0]) && $matches[0] !== '') {
                    $arguments[] = $matches[0];
                    break;
                }
            }
        }

        return $arguments;
    }
}
