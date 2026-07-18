<?php

namespace JordJD\StackExchangeSearch;

use JordJD\BaseSearch\Interfaces\SearcherInterface;

class StackExchangeSearcher implements SearcherInterface
{
    const URL = 'https://api.stackexchange.com/2.3/similar';

    private const USER_AGENT = 'jord-jd-stackexchange-search/1.1 (+https://github.com/Jord-JD/php-stackexchange-search)';

    /** @var string */
    private $site;

    /** @var string|null */
    private $apiKey;

    /** @var callable|null */
    private $httpClient;

    /** @var int */
    private $timeoutSeconds;

    /** @var string */
    private $userAgent;

    /** @var bool */
    private $hasMore = false;

    /** @var int|null */
    private $quotaMax;

    /** @var int|null */
    private $quotaRemaining;

    /** @var int|null */
    private $backoffSeconds;

    public function __construct(
        string $site,
        ?string $apiKey = null,
        ?callable $httpClient = null,
        int $timeoutSeconds = 5,
        string $userAgent = self::USER_AGENT
    ) {
        $site = trim($site);
        if ($site === '' || !preg_match('/^[A-Za-z0-9.-]+$/D', $site)) {
            throw new \InvalidArgumentException('The Stack Exchange site parameter is invalid.');
        }

        if ($timeoutSeconds < 1) {
            throw new \InvalidArgumentException('The HTTP timeout must be at least one second.');
        }

        if (trim($userAgent) === '' || preg_match('/[\r\n]/', $userAgent)) {
            throw new \InvalidArgumentException('The Stack Exchange user agent is invalid.');
        }

        $this->site = $site;
        $this->apiKey = $apiKey !== null && trim($apiKey) !== '' ? trim($apiKey) : null;
        $this->httpClient = $httpClient;
        $this->timeoutSeconds = $timeoutSeconds;
        $this->userAgent = trim($userAgent);
    }

    /**
     * @param array<string, mixed> $options
     *
     * @return StackExchangeSearchResult[]
     */
    public function search(string $query, array $options = []): array
    {
        $query = trim($query);
        if ($query === '') {
            throw new \InvalidArgumentException('The Stack Exchange search query cannot be empty.');
        }

        $url = $this->buildUrl($query, $options);
        $response = $this->fetch($url);
        $decodedResponse = json_decode($response, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new \RuntimeException('Stack Exchange returned invalid JSON: '.json_last_error_msg());
        }

        if (!is_array($decodedResponse)) {
            throw new \RuntimeException('Stack Exchange returned an unexpected search response.');
        }

        if (isset($decodedResponse['error_id'])) {
            $name = isset($decodedResponse['error_name']) ? (string) $decodedResponse['error_name'] : 'unknown_error';
            $message = isset($decodedResponse['error_message']) ? (string) $decodedResponse['error_message'] : 'Unknown API error';
            throw new \RuntimeException('Stack Exchange API error '.$name.': '.$message);
        }

        if (!isset($decodedResponse['items']) || !is_array($decodedResponse['items'])) {
            throw new \RuntimeException('Stack Exchange returned an unexpected search response.');
        }

        $this->hasMore = !empty($decodedResponse['has_more']);
        $this->quotaMax = isset($decodedResponse['quota_max']) ? (int) $decodedResponse['quota_max'] : null;
        $this->quotaRemaining = isset($decodedResponse['quota_remaining']) ? (int) $decodedResponse['quota_remaining'] : null;
        $this->backoffSeconds = isset($decodedResponse['backoff']) ? (int) $decodedResponse['backoff'] : null;

        $results = [];
        $count = count($decodedResponse['items']);

        if ($count === 0) {
            return [];
        }

        foreach ($decodedResponse['items'] as $index => $item) {
            if (!is_array($item)) {
                continue;
            }

            try {
                $results[] = new StackExchangeSearchResult($item, ($count - $index) / $count);
            } catch (\UnexpectedValueException $exception) {
                continue;
            }
        }

        return $results;
    }

    public function hasMore(): bool
    {
        return $this->hasMore;
    }

    public function getQuotaMax(): ?int
    {
        return $this->quotaMax;
    }

    public function getQuotaRemaining(): ?int
    {
        return $this->quotaRemaining;
    }

    public function getBackoffSeconds(): ?int
    {
        return $this->backoffSeconds;
    }

    private function fetch(string $url): string
    {
        $headers = [
            'Accept: application/json',
            'User-Agent: '.$this->userAgent,
        ];

        if ($this->httpClient !== null) {
            $response = call_user_func($this->httpClient, $url, $headers, $this->timeoutSeconds);

            if (!is_string($response)) {
                throw new \RuntimeException('The Stack Exchange HTTP client must return a response body string.');
            }

            return $response;
        }

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'timeout' => $this->timeoutSeconds,
                'ignore_errors' => true,
                'header' => implode("\r\n", $headers)."\r\n",
            ],
        ]);

        $response = @file_get_contents($url, false, $context);
        $responseHeaders = isset($http_response_header) ? $http_response_header : [];
        $status = null;

        foreach ($responseHeaders as $header) {
            if (preg_match('/^HTTP\/\S+\s+(\d{3})\b/i', $header, $matches)) {
                $status = (int) $matches[1];
            }
        }

        if ($response === false || $status === null) {
            throw new \RuntimeException('Unable to fetch Stack Exchange search results.');
        }

        if ($status < 200 || $status >= 300) {
            throw new \RuntimeException('Stack Exchange search failed with HTTP status '.$status.'.');
        }

        return $response;
    }

    /**
     * @param array<string, mixed> $options
     */
    private function buildUrl(string $query, array $options): string
    {
        $allowed = ['fromdate', 'max', 'min', 'nottagged', 'order', 'page', 'pagesize', 'sort', 'tagged', 'todate'];

        foreach (array_keys($options) as $option) {
            if (!in_array($option, $allowed, true)) {
                throw new \InvalidArgumentException('Unsupported Stack Exchange search option: '.$option.'.');
            }
        }

        $sort = isset($options['sort']) ? (string) $options['sort'] : 'relevance';
        if (!in_array($sort, ['activity', 'creation', 'relevance', 'votes'], true)) {
            throw new \InvalidArgumentException('Unsupported Stack Exchange search sort: '.$sort.'.');
        }

        $order = isset($options['order']) ? (string) $options['order'] : 'desc';
        if (!in_array($order, ['asc', 'desc'], true)) {
            throw new \InvalidArgumentException('The Stack Exchange search order must be asc or desc.');
        }

        $params = [
            'order' => $order,
            'sort' => $sort,
            'title' => $query,
            'site' => $this->site,
        ];

        if ($this->apiKey !== null) {
            $params['key'] = $this->apiKey;
        }

        if (isset($options['page'])) {
            $params['page'] = $this->positiveInteger($options['page'], 'page');
        }

        if (isset($options['pagesize'])) {
            $pageSize = $this->positiveInteger($options['pagesize'], 'pagesize');
            if ($pageSize > 100) {
                throw new \InvalidArgumentException('The Stack Exchange pagesize cannot exceed 100.');
            }
            $params['pagesize'] = $pageSize;
        }

        foreach (['fromdate', 'todate'] as $dateOption) {
            if (isset($options[$dateOption])) {
                $params[$dateOption] = $this->positiveInteger($options[$dateOption], $dateOption, true);
            }
        }

        foreach (['min', 'max'] as $bound) {
            if (isset($options[$bound])) {
                if ($sort === 'relevance') {
                    throw new \InvalidArgumentException('Stack Exchange min and max are not supported for relevance sorting.');
                }
                if (!is_scalar($options[$bound]) || trim((string) $options[$bound]) === '') {
                    throw new \InvalidArgumentException('The Stack Exchange '.$bound.' option is invalid.');
                }
                $params[$bound] = (string) $options[$bound];
            }
        }

        foreach (['tagged', 'nottagged'] as $tagOption) {
            if (isset($options[$tagOption])) {
                $params[$tagOption] = $this->normalizeTags($options[$tagOption], $tagOption);
            }
        }

        return self::URL.'?'.http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    /**
     * @param mixed $value
     */
    private function positiveInteger($value, string $name, bool $allowZero = false): int
    {
        $integer = filter_var($value, FILTER_VALIDATE_INT);
        $minimum = $allowZero ? 0 : 1;

        if ($integer === false || $integer < $minimum) {
            throw new \InvalidArgumentException('The Stack Exchange '.$name.' option must be an integer of at least '.$minimum.'.');
        }

        return $integer;
    }

    /**
     * @param mixed $tags
     */
    private function normalizeTags($tags, string $name): string
    {
        $tags = is_array($tags) ? $tags : [$tags];
        $normalized = [];

        foreach ($tags as $tag) {
            if (!is_scalar($tag) || trim((string) $tag) === '' || strpos((string) $tag, ';') !== false) {
                throw new \InvalidArgumentException('The Stack Exchange '.$name.' option contains an invalid tag.');
            }
            $normalized[] = trim((string) $tag);
        }

        return implode(';', $normalized);
    }
}
