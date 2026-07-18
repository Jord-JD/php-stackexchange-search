# PHP Stack Exchange Search

Search for similar questions on any Stack Exchange network site through the
current Stack Exchange API v2.3.

## Installation

```bash
composer require jord-jd/php-stackexchange-search
```

## Usage

```php
use JordJD\StackExchangeSearch\Enums\Sites;
use JordJD\StackExchangeSearch\StackExchangeSearcher;

$searcher = new StackExchangeSearcher(Sites::STACK_OVERFLOW, $optionalApiKey);

$results = $searcher->search('connect to a database in PHP', [
    'tagged' => ['php', 'database'],
    'nottagged' => ['wordpress'],
    'sort' => 'votes',
    'order' => 'desc',
    'pagesize' => 25,
    'page' => 1,
]);

foreach ($results as $result) {
    echo $result->getTitle();
    echo $result->getDescription();
    echo $result->getUrl();
    echo $result->getQuestionId();
    echo implode(', ', $result->getTags());
    echo $result->getOwner();
    echo $result->getAnswerCount();
    echo $result->isAnswered() ? 'answered' : 'unanswered';
    echo json_encode($result);
}
```

Any valid Stack Exchange API `site` parameter can be used; the `Sites` class
contains convenient constants for common sites. An API key is optional but can
increase the request quota for registered applications.

Supported search options mirror the official
[`/similar` endpoint](https://api.stackexchange.com/docs/similar): `page`,
`pagesize` (up to 100), `fromdate`, `todate`, `order`, `min`, `max`, `sort`,
`tagged`, and `nottagged`. Invalid inputs fail before an HTTP request is made.

After each request, inspect `hasMore()`, `getQuotaMax()`,
`getQuotaRemaining()`, and `getBackoffSeconds()`. If the API supplies a
backoff, wait that many seconds before another request.

## Custom HTTP transport

The optional third constructor argument is a callable receiving the URL,
headers, and timeout. It must return the response body as a string:

```php
$searcher = new StackExchangeSearcher(
    Sites::STACK_OVERFLOW,
    $apiKey,
    function (string $url, array $headers, int $timeout): string {
        return $yourHttpClient->get($url, $headers, $timeout);
    },
    10
);
```

The package supports PHP 7.1 through current PHP 8.x releases.
