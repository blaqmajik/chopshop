<?php

namespace ChopShop\Tests;

use ChopShop\Selector\FilterFunctionCall;
use ChopShop\Selector\Parser\SelectorParser;

/**
 * Class SelectorParserTest
 * @package ChopShop\Tests
 *
 * Based on the tests in npm package 'x-ray-parse'
 */
class SelectorParserTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var array
     */
    protected $selectors = [];

    public function setUp()
    {
        $this->selectors = require __DIR__ . '/Fixtures/selectors.php';
    }

    public function testParseElementSelectors()
    {
        foreach ($this->selectors as $selectorString) {
            $selector = SelectorParser::parse($selectorString);

            $this->assertEquals($selectorString, $selector->getSelector());
            $this->assertTrue($selector->targetIsText());
            $this->assertNull($selector->getAttribute());
            $this->assertFalse($selector->isMultiple());
            $this->assertFalse($selector->hasChildren());
        }
    }

    public function testParseMultipleElementSelectors()
    {
        foreach ($this->selectors as $selectorString) {
            $selector = SelectorParser::parse([$selectorString]);

            $this->assertEquals($selectorString, $selector->getSelector());
            $this->assertTrue($selector->targetIsText());
            $this->assertNull($selector->getAttribute());
            $this->assertTrue($selector->isMultiple());
            $this->assertFalse($selector->hasChildren());
        }
    }

    public function testParseMultipleElementSelectorWithChildren()
    {
        $selector =
            [
                'div.something' =>
                    [
                        'first' => 'h2',
                        'second' => 'h3'
                    ]
            ];

        $parsedSelector = SelectorParser::parse($selector);

        $this->assertTrue($parsedSelector->isMultiple());
        $this->assertTrue($parsedSelector->hasChildren());
    }

    public function testParseElementAndAttributeSelectors()
    {
        foreach ($this->selectors as $selectorString) {
            $selector = SelectorParser::parse($selectorString . '@ href');

            $this->assertEquals($selectorString, $selector->getSelector());
            $this->assertTrue($selector->targetIsAttribute());
            $this->assertEquals('href', $selector->getAttribute());
            $this->assertFalse($selector->isMultiple());
            $this->assertFalse($selector->hasChildren());
        }
    }

    public function testParseElementAndAttributeSelectorsWithSpacesAndHyphens()
    {
        foreach ($this->selectors as $selectorString) {
            $selector = SelectorParser::parse($selectorString . ' @ data-item');

            $this->assertEquals($selectorString, $selector->getSelector());
            $this->assertTrue($selector->targetIsAttribute());
            $this->assertEquals('data-item', $selector->getAttribute());
            $this->assertFalse($selector->isMultiple());
            $this->assertFalse($selector->hasChildren());
        }
    }

    public function testParseSingleAttribute()
    {
        foreach (['@ href', '@href'] as $selectorString) {
            $selector = SelectorParser::parse($selectorString);
            $this->assertNull($selector->getSelector());
            $this->assertEquals('href', $selector->getAttribute());
        }
    }

    public function testParseTargetIsInnerHtml()
    {
        $selector = SelectorParser::parse('div@html');
        $this->assertTrue($selector->targetIsInnerHtml());
    }

    public function testParseFilters()
    {
        $selector = SelectorParser::parse('a[href][class] @ attr | filter1 | filter2');
        $this->assertEquals('a[href][class]', $selector->getSelector());
        $this->assertEquals('attr', $selector->getAttribute());

        $this->assertEquals(
            [
                new FilterFunctionCall('filter1'),
                new FilterFunctionCall('filter2')
            ],
            $selector->getFilters()
        );
    }

    public function testParseFiltersWithArguments()
    {
        $selector = SelectorParser::parse('a[href][class] @ attr | filter1: "%Y %M %d" | filter2: test 13');
        $this->assertEquals('a[href][class]', $selector->getSelector());
        $this->assertEquals('attr', $selector->getAttribute());

        $this->assertEquals(
            [
                new FilterFunctionCall('filter1', ['%Y %M %d']),
                new FilterFunctionCall('filter2', ['test', 13])
            ],
            $selector->getFilters()
        );
    }

    public function testParseEverythingWithNoSpaces()
    {
        $selector = SelectorParser::parse('a@href|href|uppercase');
        $this->assertEquals('a', $selector->getSelector());
        $this->assertEquals('href', $selector->getAttribute());

        $this->assertEquals(
            [
                new FilterFunctionCall('href'),
                new FilterFunctionCall('uppercase')
            ],
            $selector->getFilters()
        );
    }

    /**
     * @expectedException \ChopShop\Exception\MalformedSelectorException
     */
    public function testMalformedSelectorShouldRaiseException()
    {
        SelectorParser::parse('@');
    }
}
