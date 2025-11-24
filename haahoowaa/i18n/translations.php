<?php
class I18n
{
    private static array $translations = [];
    private static array $languages = [];
    private static string $defaultLanguage = 'es';
    private static string $currentLanguage = 'es';

    public static function init(array $languages, array $translations, string $defaultLanguage): void
    {
        self::$languages = $languages;
        self::$translations = $translations;
        self::$defaultLanguage = $defaultLanguage;
        self::$currentLanguage = $defaultLanguage;
    }

    public static function setLanguage(string $lang): void
    {
        self::$currentLanguage = $lang;
    }

    public static function getLanguage(): string
    {
        return self::$currentLanguage;
    }

    public static function translate(string $key, ?string $lang = null): string
    {
        $language = $lang ?? self::$currentLanguage ?? self::$defaultLanguage;
        return self::$translations[$language][$key] ?? $key;
    }

    public static function languages(): array
    {
        return self::$languages;
    }
}

function t(string $key, ?string $lang = null): string
{
    return I18n::translate($key, $lang);
}

return [
    'es' => [
        'welcome' => 'Bienvenido a haahoowaa',
        'home_title' => 'Inicio',
        'products_title' => 'Productos',
    ],
    'en' => [
        'welcome' => 'Welcome to haahoowaa',
        'home_title' => 'Home',
        'products_title' => 'Products',
    ],
    'fr' => [
        'welcome' => 'Bienvenue sur haahoowaa',
        'home_title' => 'Accueil',
        'products_title' => 'Produits',
    ],
];
