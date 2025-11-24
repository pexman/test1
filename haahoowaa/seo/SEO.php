<?php
class SEO
{
    public static function meta(array $data = []): array
    {
        return [
            'title' => $data['title'] ?? '',
            'description' => $data['description'] ?? '',
            'canonical' => $data['canonical'] ?? '',
            'og' => $data['og'] ?? [],
        ];
    }
}
