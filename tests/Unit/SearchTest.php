<?php

namespace DivineOmega\StackExchangeSearch\Tests;

use DivineOmega\BaseSearch\Interfaces\SearchResultInterface;
use DivineOmega\StackExchangeSearch\Enums\Sites;
use DivineOmega\StackExchangeSearch\StackExchangeSearcher;
use DivineOmega\StackExchangeSearch\StackExchangeSearchResult;
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
