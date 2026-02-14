<?php

namespace JordJD\StackExchangeSearch;

use JordJD\BaseSearch\Interfaces\SearchResultInterface;

class StackExchangeSearchResult implements SearchResultInterface
{
    private $title;
    private $url;
    private $description;
    private $score;

    public function __construct(array $item, float $score)
    {
        $this->title = html_entity_decode($item['title'], ENT_QUOTES | ENT_HTML5);
        $this->url = $item['link'];

        if (isset($item['owner']['display_name'])) {
            $this->description = 'Posted by '.$item['owner']['display_name'].' and tagged as '.implode(', ', $item['tags']);
        } else {
            $this->description = 'Tagged as '.implode(', ', $item['tags']);
        }
        
        $this->score = $score;
    }

    public function getTitle(): string
    {
        return $this->title;
    }

    public function getDescription(): string
    {
        return $this->description;
    }

    public function getUrl(): string
    {
        return $this->url;
    }

    public function getScore(): float
    {
        return $this->score;
    }
}