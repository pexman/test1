<?php
class Slug
{
    public static function generate(string $text, string $delimiter = '-'): string
    {
        $text = strtolower(trim($text));
        $text = preg_replace('/[^a-z0-9\s-]/', '', $text);
        $text = preg_replace('/[\s-]+/', ' ', $text);
        $text = preg_replace('/\s/', $delimiter, $text);
        return trim($text, $delimiter);
    }
}
