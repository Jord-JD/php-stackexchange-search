<?php

namespace JordJD\StackExchangeSearch\Tests;

use JordJD\BaseSearch\Interfaces\SearchResultInterface;
use JordJD\StackExchangeSearch\Enums\Sites;
use JordJD\StackExchangeSearch\StackExchangeSearcher;
use JordJD\StackExchangeSearch\StackExchangeSearchResult;
use PHPUnit\Framework\TestCase;

class SearchTest extends TestCase
{
    public function testSearchBuildsOptionsAndNormalizesMetadata()
    {
        $request = [];
        $client = function ($url, $headers, $timeout) use (&$request) {
            $request = compact('url', 'headers', 'timeout');

            return json_encode([
                'items' => [
                    [
                        'question_id' => 123,
                        'title' => 'PHP &amp; Databases',
                        'link' => 'https://stackoverflow.com/questions/123/example',
                        'owner' => ['display_name' => 'Jordan &amp; Co'],
                        'tags' => ['php', 'database'],
                        'answer_count' => 3,
                        'is_answered' => true,
                        'creation_date' => 1700000000,
                    ],
                    [
                        'question_id' => 456,
                        'title' => 'Second result',
                        'link' => 'https://stackoverflow.com/questions/456/example',
                        'tags' => [],
                        'answer_count' => 0,
                        'is_answered' => false,
                    ],
                ],
                'has_more' => true,
                'backoff' => 2,
                'quota_max' => 10000,
                'quota_remaining' => 9999,
            ]);
        };

        $searcher = new StackExchangeSearcher(Sites::STACK_OVERFLOW, 'api-key', $client, 8, 'php:test:v1');
        $results = $searcher->search(' PHP database ', [
            'fromdate' => 0,
            'nottagged' => 'wordpress',
            'order' => 'desc',
            'page' => 2,
            'pagesize' => 50,
            'sort' => 'votes',
            'tagged' => ['php', 'database'],
            'todate' => 1800000000,
            'min' => 1,
            'max' => 100,
        ]);

        $this->assertCount(2, $results);
        $this->assertInstanceOf(StackExchangeSearchResult::class, $results[0]);
        $this->assertInstanceOf(SearchResultInterface::class, $results[0]);
        $this->assertSame('PHP & Databases', $results[0]->getTitle());
        $this->assertSame('Jordan & Co', $results[0]->getOwner());
        $this->assertSame(['php', 'database'], $results[0]->getTags());
        $this->assertSame(123, $results[0]->getQuestionId());
        $this->assertSame(3, $results[0]->getAnswerCount());
        $this->assertTrue($results[0]->isAnswered());
        $this->assertSame(1700000000, $results[0]->getCreationDate());
        $this->assertSame(1.0, $results[0]->getScore());
        $this->assertSame(0.5, $results[1]->getScore());
        $this->assertEquals($results[0]->toArray(), json_decode(json_encode($results[0]), true));

        $this->assertTrue($searcher->hasMore());
        $this->assertSame(10000, $searcher->getQuotaMax());
        $this->assertSame(9999, $searcher->getQuotaRemaining());
        $this->assertSame(2, $searcher->getBackoffSeconds());

        $this->assertSame(8, $request['timeout']);
        $this->assertContains('User-Agent: php:test:v1', $request['headers']);
        $this->assertStringStartsWith('https://api.stackexchange.com/2.3/similar?', $request['url']);
        $this->assertStringContainsString('title=PHP%20database', $request['url']);
        $this->assertStringContainsString('site=stackoverflow', $request['url']);
        $this->assertStringContainsString('key=api-key', $request['url']);
        $this->assertStringContainsString('tagged=php%3Bdatabase', $request['url']);
        $this->assertStringContainsString('fromdate=0', $request['url']);
    }

    public function testApiErrorsAndInvalidJsonAreExplicit()
    {
        $apiError = new StackExchangeSearcher('stackoverflow', null, function () {
            return '{"error_id":400,"error_name":"bad_parameter","error_message":"site is invalid"}';
        });

        try {
            $apiError->search('test');
            $this->fail('API errors should throw.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('bad_parameter', $exception->getMessage());
        }

        $invalidJson = new StackExchangeSearcher('stackoverflow', null, function () {
            return 'not json';
        });

        try {
            $invalidJson->search('test');
            $this->fail('Invalid JSON should throw.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('invalid JSON', $exception->getMessage());
        }
    }

    public function testInvalidInputFailsBeforeHttp()
    {
        $cases = [
            [' ', [], 'cannot be empty'],
            ['php', ['unknown' => true], 'Unsupported'],
            ['php', ['page' => 0], 'page'],
            ['php', ['pagesize' => 101], 'cannot exceed'],
            ['php', ['sort' => 'random'], 'sort'],
            ['php', ['order' => 'sideways'], 'order'],
            ['php', ['min' => 1], 'not supported'],
            ['php', ['tagged' => ['php', 'bad;tag']], 'invalid tag'],
        ];

        foreach ($cases as $case) {
            $called = false;
            $searcher = new StackExchangeSearcher('stackoverflow', null, function () use (&$called) {
                $called = true;
                return '{}';
            });

            try {
                $searcher->search($case[0], $case[1]);
                $this->fail('Invalid input should throw.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString($case[2], $exception->getMessage());
            }

            $this->assertFalse($called);
        }
    }

    public function testConstructorValidatesSiteTimeoutAndUserAgent()
    {
        $cases = [
            ['', 5, 'agent', 'site'],
            ['bad/site', 5, 'agent', 'site'],
            ['stackoverflow', 0, 'agent', 'timeout'],
            ['stackoverflow', 5, "bad\r\nagent", 'user agent'],
        ];

        foreach ($cases as $case) {
            try {
                new StackExchangeSearcher($case[0], null, null, $case[1], $case[2]);
                $this->fail('Invalid constructor input should throw.');
            } catch (\InvalidArgumentException $exception) {
                $this->assertStringContainsString($case[3], strtolower($exception->getMessage()));
            }
        }
    }
}
