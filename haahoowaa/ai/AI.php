<?php
class AI
{
    private string $apiKey;

    public function __construct(?string $apiKey = null)
    {
        $this->apiKey = $apiKey ?? '';
    }

    public function isEnabled(): bool
    {
        return !empty($this->apiKey);
    }

    public function suggestMeta(string $text): array
    {
        if (!$this->isEnabled()) {
            return [];
        }

        // Placeholder for AI integration.
        return [
            'title' => substr($text, 0, 60),
            'description' => substr($text, 0, 155),
            'slug' => Slug::generate($text),
        ];
    }
}
