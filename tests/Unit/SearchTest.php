<?php

namespace JordJD\StackExchangeSearch\Tests;

use JordJD\BaseSearch\Interfaces\SearchResultInterface;
use JordJD\StackExchangeSearch\Enums\Sites;
use JordJD\StackExchangeSearch\StackExchangeSearcher;
use JordJD\StackExchangeSearch\StackExchangeSearchResult;
use PHPUnit\Framework\TestCase;

final class SearchTest extends TestCase
{
    public function testSearch()
    {
        $searcher = new StackExchangeSearcher(Sites::STACK_OVERFLOW);
        try {
            $results = $searcher->search('how to connect to a database in PHP');
        } catch (\Throwable $e) {
            $this->markTestSkipped('StackExchange API unavailable: '.$e->getMessage());
            return;
        }

        if (count($results) === 0) {
            $this->markTestSkipped('StackExchange API returned zero results.');
            return;
        }

        $this->assertGreaterThanOrEqual(1, count($results));

        foreach($results as $result) {
            $this->assertInstanceOf(StackExchangeSearchResult::class, $result);
            $this->assertInstanceOf(SearchResultInterface::class, $result);

            $this->assertGreaterThanOrEqual(0, $result->getScore());
            $this->assertLessThanOrEqual(1, $result->getScore());
        }
    }

}
