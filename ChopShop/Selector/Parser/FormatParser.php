<?php

namespace ChopShop\Selector\Parser;

/**
 * Class FormatParser
 * @package ChopShop\Selector\Parser
 *
 * Mimics the behavior of npm package 'format-parser'
 */
class FormatParser
{
    // shamelessly borrowed from format-parser
    const REGEX_FORMAT_SPLIT = '/\s*\|\s*/';
    const REGEX_FORMAT_ARGUMENTS = '/"([^"]*)"|\'([^\']*)\'|([^\s\t,]+)/';

    /**
     * @param $string
     * @return array
     */
    public static function parse($string)
    {
        $parsedFormats = [];

        foreach (preg_split(self::REGEX_FORMAT_SPLIT, $string) as $filter) {
            $parts = explode(':', $filter);
            $name = array_shift($parts);
            $arguments = self::parseArguments(implode(':', $parts));

            $parsedFormats[] = ['name' => $name, 'args' => $arguments];
        }

        return $parsedFormats;
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
                if (count($matches) > 0 && $matches[0] !== null && $matches[0] !== '') {
                    $arguments = $matches;

                    break;
                }
            }
        }

        return $arguments;
    }
}
