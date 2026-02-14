<?php

namespace DivineOmega\StackExchangeSearch;

use DivineOmega\BaseSearch\Interfaces\SearcherInterface;

class StackExchangeSearcher implements SearcherInterface
{
    const URL = 'https://api.stackexchange.com/2.2/similar?order=desc&sort=relevance&title=[QUERY]&site=[SITE]';
    private const USER_AGENT = 'jord-jd-stackexchange-search/1.0 (+https://github.com/Jord-JD/php-stackexchange-search)';

    private $site;

    public function __construct(string $site)
    {
        $this->site = $site;
    }

    public function search(string $query): array
    {
        $url = $this->buildUrl($query);

        $rawResponse = $this->fetch($url);
        $decodedGzip = @gzdecode($rawResponse);
        $response = ($decodedGzip === false) ? $rawResponse : $decodedGzip;
        $decodedResponse = json_decode($response, true);
        if (!is_array($decodedResponse) || !isset($decodedResponse['items']) || !is_array($decodedResponse['items'])) {
            return [];
        }

        $results = [];

        $count = count($decodedResponse['items']);
        if ($count === 0) {
            return [];
        }

        foreach ($decodedResponse['items'] as $index => $item) {
            $score = ($count - $index) / $count;
            $results[] = new StackExchangeSearchResult($item, $score);
        }

        return $results;
    }

    private function fetch(string $url): string
    {
        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => 5,
                'header' => "User-Agent: " . self::USER_AGENT . "\r\nAccept-Encoding: gzip\r\n",
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        if ($response === false) {
            throw new \RuntimeException('Unable to fetch StackExchange search results.');
        }

        return $response;
    }

    private function buildUrl(string $query): string
    {
        return str_replace(
            ['[QUERY]', '[SITE]'],
            [urlencode($query), urlencode($this->site)],
            self::URL
        );
    }
}
