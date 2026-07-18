<?php

namespace JordJD\StackExchangeSearch;

use JordJD\BaseSearch\SearchResult;

class StackExchangeSearchResult extends SearchResult
{
    /** @var int */
    private $questionId;

    /** @var string */
    private $owner;

    /** @var string[] */
    private $tags;

    /** @var int */
    private $answerCount;

    /** @var bool */
    private $answered;

    /** @var int|null */
    private $creationDate;

    public function __construct(array $item, float $score)
    {
        if (!isset($item['title'], $item['link']) || !is_string($item['title']) || !is_string($item['link'])) {
            throw new \UnexpectedValueException('Stack Exchange result title or link is missing.');
        }

        if (trim($item['title']) === '' || trim($item['link']) === '') {
            throw new \UnexpectedValueException('Stack Exchange result title or link is empty.');
        }

        $this->questionId = isset($item['question_id']) ? (int) $item['question_id'] : 0;
        $this->owner = isset($item['owner']['display_name']) && is_string($item['owner']['display_name'])
            ? html_entity_decode($item['owner']['display_name'], ENT_QUOTES | ENT_HTML5, 'UTF-8')
            : '';
        $this->tags = isset($item['tags']) && is_array($item['tags'])
            ? array_values(array_filter($item['tags'], 'is_string'))
            : [];
        $this->answerCount = isset($item['answer_count']) ? (int) $item['answer_count'] : 0;
        $this->answered = !empty($item['is_answered']);
        $this->creationDate = isset($item['creation_date']) ? (int) $item['creation_date'] : null;

        if ($this->owner !== '' && $this->tags !== []) {
            $description = 'Posted by '.$this->owner.' and tagged as '.implode(', ', $this->tags);
        } elseif ($this->owner !== '') {
            $description = 'Posted by '.$this->owner;
        } elseif ($this->tags !== []) {
            $description = 'Tagged as '.implode(', ', $this->tags);
        } else {
            $description = 'Stack Exchange question';
        }

        parent::__construct(
            html_entity_decode($item['title'], ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            $description,
            $item['link'],
            $score
        );
    }

    public function getQuestionId(): int
    {
        return $this->questionId;
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    /**
     * @return string[]
     */
    public function getTags(): array
    {
        return $this->tags;
    }

    public function getAnswerCount(): int
    {
        return $this->answerCount;
    }

    public function isAnswered(): bool
    {
        return $this->answered;
    }

    public function getCreationDate(): ?int
    {
        return $this->creationDate;
    }

    public function toArray(): array
    {
        return parent::toArray() + [
            'question_id' => $this->questionId,
            'owner' => $this->owner,
            'tags' => $this->tags,
            'answer_count' => $this->answerCount,
            'is_answered' => $this->answered,
            'creation_date' => $this->creationDate,
        ];
    }
}
