<?php

namespace ChopShop\Tests;

use ChopShop\Selector\Parser\FormatParser;
use PHPUnit\Framework\TestCase;

/**
 * Class FormatParserTest
 * @package ChopShop\Tests
 *
 * Based on the tests in npm package component/format-parser
 */
class FormatParserTest extends TestCase
{
    public function testParseSingleFormatWithoutArguments()
    {
        $format = 'foo';
        $result = FormatParser::parse($format);

        $this->assertEquals(
            [
                ['name' => 'foo', 'args' => []]
            ],
            $result
        );
    }

    public function testParseMultipleFormatsWithoutArguments()
    {
        $format = 'foo | bar | baz';
        $result = FormatParser::parse($format);

        $this->assertEquals(
            [
                ['name' => 'foo', 'args' => []],
                ['name' => 'bar', 'args' => []],
                ['name' => 'baz', 'args' => []]
            ],
            $result
        );
    }

    public function testParseArguments()
    {
        $format = 'foo:bar';
        $result = FormatParser::parse($format);

        $this->assertEquals(
            [
                ['name' => 'foo', 'args' => ['bar']],
            ],
            $result
        );
    }

    public function testParseQuotedArguments()
    {
        $format = 'created_at | date:"%B %d, %Y at %I:%M%P"';
        $result = FormatParser::parse($format);

        $this->assertEquals(
            [
                ['name' => 'created_at', 'args' => []],
                ['name' => 'date', 'args' => ['%B %d, %Y at %I:%M%P']]
            ],
            $result
        );
    }

    public function testParseArgumentsWithSingleQuotes()
    {
        $format = "created_at | date:'%B %d, %Y at %I:%M%P'";
        $result = FormatParser::parse($format);

        $this->assertEquals(
            [
                ['name' => 'created_at', 'args' => []],
                ['name' => 'date', 'args' => ['%B %d, %Y at %I:%M%P']]
            ],
            $result
        );
    }

    public function testParseMultipleArguments()
    {
        $format = "foo:bar,baz,raz";
        $result = FormatParser::parse($format);

        $this->assertEquals(
            [
                ['name' => 'foo', 'args' => ['bar', 'baz', 'raz']]
            ],
            $result
        );
    }

    public function testParseMultipleArgumentsWithWhitespace()
    {
        $format = "foo:bar, baz, raz";
        $result = FormatParser::parse($format);

        $this->assertEquals(
            [
                ['name' => 'foo', 'args' => ['bar', 'baz', 'raz']]
            ],
            $result
        );
    }
}
