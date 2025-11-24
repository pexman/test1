<?php
class Bootstrap
{
    private array $config;
    private array $routes;
    private PDO $db;

    public function __construct()
    {
        $this->config = require __DIR__ . '/../config/config.php';
        date_default_timezone_set($this->config['timezone'] ?? 'UTC');

        $database = require __DIR__ . '/../config/database.php';
        $dsn = sprintf('mysql:host=%s;port=%s;dbname=%s;charset=%s', $database['host'], $database['port'], $database['database'], $database['charset']);
        $this->db = new PDO($dsn, $database['username'], $database['password'], [
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        ]);

        $translations = require __DIR__ . '/../i18n/translations.php';
        $languages = require __DIR__ . '/../i18n/languages.php';
        I18n::init($languages, $translations, $this->config['default_language']);

        $this->routes = require __DIR__ . '/../config/routes.php';
    }

    public function run(): void
    {
        $router = new Router($this->routes, $this->config, $this->db);
        $router->dispatch();
    }
}
