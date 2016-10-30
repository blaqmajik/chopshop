<?php

namespace ChopShop\Tests;

use ChopShop\Driver\DriverInterface;
use ChopShop\Driver\Guzzle;
use ChopShop\Scraper;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;

/**
 * Class ScraperTest
 * @package ChopShop\Tests
 */
class ScraperTest extends \PHPUnit_Framework_TestCase
{
    const BASE_URL = 'http://example.com';

    /**
     * @param DriverInterface|null $driver
     * @return Scraper
     */
    protected function getScraper(DriverInterface $driver = null)
    {
        $options = [];

        $filters =
            [
                'asDateTime' => function($value) {
                    return is_string($value) && strtotime($value) !== false ? new \DateTime($value) : $value;
                },
                'asFloat' => function($value) {
                    return (float) $value;
                },
                'trim' => function($value) {
                    return is_string($value) ? trim($value) : $value;
                },
                'withoutLeading' => function($value, $startString) {
                    return is_string($value) && strpos($value, $startString) === 0
                        ? substr($value, strlen($startString))
                        : $value;
                }
            ];

        $options['filters'] = $filters;

        if ($driver !== null) {
            $options['driver'] = $driver;
        }

        return new Scraper($options);
    }

    /**
     * @param Response[] $responses
     * @return Guzzle
     */
    protected function getDriver(array $responses = [])
    {
        $mockHandler = new MockHandler($responses);
        $handlerStack = HandlerStack::create($mockHandler);

        $driver = new Guzzle(['handler' => $handlerStack]);
        $driver->setDelay(1);

        return $driver;
    }

    public function testScraper()
    {
        $driver = $this->getDriver(
            [
                new Response(200, [], file_get_contents(__DIR__ . '/fixtures/test.html'))
            ]
        );

        $scraper = $this->getScraper($driver);

        $selectors =
            [
                'reviews' =>
                    [
                        'div#reviews div.review' =>
                            [
                                'linkToArtwork' => 'div.artwork img@src',
                                'artworkTitle' => 'div.artwork img@alt',
                                'artists' => ['div.album-info ul.artist-list li | trim'],
                                'title' => 'div.album-info h2 a | trim',
                                'linkToReview' => 'div.album-info h2 a@href',
                                'abstract' => ['div.abstract p | trim'],
                                'publishDate' => 'div.meta span.publish-date | trim | asDateTime',
                                'score' => 'div.meta span.score | trim | asFloat',
                                'author' =>
                                    "div.meta span.author | trim | withoutLeading: 'By:' | trim | strtolower | ucwords",
                                'specialHtml' => 'div.meta span.special@html',
                                'editions' =>
                                    [
                                        'div.editions div.edition ul' =>
                                            [
                                                'region' => 'li.region | trim',
                                                'releaseDate' => 'li.releaseDate | trim | asDateTime'
                                            ]
                                    ],
                                'notFound' => 'div#doesNotExist',
                                'notFoundMultiple' => ['div.doesNotExist']
                            ]
                    ]
            ]
        ;

        $result = $scraper->scrape(self::BASE_URL . '/reviews', $selectors);

        $this->assertEquals(
            [
                'reviews' =>
                    [
                        [
                            'linkToArtwork' => self::BASE_URL . '/artworks/album-1.jpg',
                            'artworkTitle' => 'Artwork for Album 1',
                            'artists' =>
                                [
                                    'Artist 1.1',
                                    'Artist 1.2'
                                ],
                            'title' => 'Album 1',
                            'linkToReview' => self::BASE_URL . '/reviews/album-1',
                            'abstract' =>
                                [
                                    'Text',
                                    'Text',
                                    'Text'
                                ],
                            'publishDate' => \DateTime::createFromFormat('Y-m-d H:i:s', '2016-01-01 00:00:00'),
                            'score' => (float) 10.0,
                            'author' => 'Author Name',
                            'specialHtml' => '<strong>Best New Music</strong>',
                            'editions' =>
                                [
                                    [
                                        'region' => 'US',
                                        'releaseDate' => \DateTime::createFromFormat('Y-m-d H:i:s', '2016-01-01 00:00:00'),
                                    ],
                                    [
                                        'region' => 'UK',
                                        'releaseDate' => \DateTime::createFromFormat('Y-m-d H:i:s', '2016-01-08 00:00:00'),
                                    ]
                                ],
                            'notFound' => null,
                            'notFoundMultiple' => []
                        ],
                        [
                            'linkToArtwork' => self::BASE_URL . '/artworks/album-2.jpg',
                            'artworkTitle' => 'Artwork for Album 2',
                            'artists' =>
                                [
                                    'Artist 2.1'
                                ],
                            'title' => 'Album 2',
                            'linkToReview' => self::BASE_URL . '/reviews/album-2',
                            'abstract' =>
                                [
                                    'Text',
                                    'Text',
                                    'Text'
                                ],
                            'publishDate' => \DateTime::createFromFormat('Y-m-d H:i:s', '2016-01-02 00:00:00'),
                            'score' => (float) 0.1,
                            'author' => 'Editor Name',
                            'specialHtml' => null,
                            'editions' => [],
                            'notFound' => null,
                            'notFoundMultiple' => []
                        ]
                    ]
            ],
            $result
        );
    }

    public function testScraperWithPagination()
    {
        $driver = $this->getDriver(
            [
                new Response(200, [], file_get_contents(__DIR__ . '/fixtures/test.html')),
                new Response(200, [], file_get_contents(__DIR__ . '/fixtures/test2.html')),
            ]
        );

        $scraper = $this->getScraper($driver);

        $selectors =
            [
                'reviews' =>
                    [
                        'div#reviews div.review' =>
                            [
                                'title' => 'div.album-info h2 a | trim'
                            ]
                    ]
            ]
        ;

        $result = $scraper->scrape(self::BASE_URL . '/reviews', $selectors, 'div#pagination a.nextPage@href');

        $this->assertEquals(
            [
                'reviews' =>
                    [
                        ['title' => 'Album 1'],
                        ['title' => 'Album 2'],
                        ['title' => 'Album 3']
                    ]
            ],
            $result
        );
    }

    public function testScraperWithPaginationAndLimit()
    {
        $driver = $this->getDriver(
            [
                new Response(200, [], file_get_contents(__DIR__ . '/fixtures/test.html'))
            ]);

        $scraper = $this->getScraper($driver);

        $selectors = [ 'headline' => 'h1' ];

        $result = $scraper->scrape(self::BASE_URL . '/reviews', $selectors, 'div#pagination a.nextPage@href', 1);

        $this->assertEquals(
            [
                'headline' => 'ChopShop Test'
            ],
            $result
        );
    }

    /**
     * @expectedException \ChopShop\Exception\InvalidDriverException
     */
    public function testScraperWithInvalidDriver()
    {
        new Scraper(['driver' => new \DateTime()]);
    }

    public function testScraperWithoutDriver()
    {
        $scraper = $this->getScraper();

        $result = $scraper->scrape(file_get_contents(__DIR__ . '/fixtures/test.html'),
            [
                'title' => 'h1'
            ]);

        $this->assertEquals($result['title'], 'ChopShop Test');
    }

    /**
     * @expectedException \ChopShop\Exception\MoreThanOneMatchFoundException
     */
    public function testMoreThanOneMatchForSingleSelectorShouldRaiseException()
    {
        $driver = $this->getDriver(
            [
                new Response(200, [], file_get_contents(__DIR__ . '/fixtures/test.html'))
            ]
        );

        $scraper = $this->getScraper($driver);

        $selectors =
            [
                'artist' => 'div.review:first ul.artist-list li.artist'
            ];

        $scraper->scrape(self::BASE_URL . '/reviews', $selectors);
    }

    /**
     * @expectedException \ChopShop\Exception\UndefinedFilterException
     */
    public function testNonExistingFilterShouldRaiseException()
    {
        $scraper = $this->getScraper();

        $scraper->scrape('<p>Test</p>',
            [
                'text' => 'p | trim | nonExistingFilter'
            ]
        );
    }

    /**
     * @expectedException \ChopShop\Exception\FilterIsNotCallableException
     */
    public function testNonCallableFilterDefinitionShouldRaiseException()
    {
        new Scraper(
            [
                'filters' =>
                    [
                        'filter' => 'not callable, just a string'
                    ]
            ]
        );
    }
}
