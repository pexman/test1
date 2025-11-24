<?php
class View
{
    private string $theme;
    private string $templateBase;

    public function __construct(string $theme, string $basePath)
    {
        $this->theme = $theme;
        $this->templateBase = $basePath . '/templates/' . $theme;
    }

    public function render(string $module, string $template, array $data = []): void
    {
        $layoutPath = $this->templateBase . '/layout.html';
        $templatePath = $this->templateBase . '/' . $module . '/' . $template . '.html';

        if (!file_exists($templatePath)) {
            http_response_code(404);
            echo "Template not found";
            return;
        }

        $defaults = [
            'lang' => I18n::getLanguage(),
            'year' => date('Y'),
        ];

        $content = file_get_contents($templatePath);
        $content = $this->parseTemplate($content, $data);

        $layout = file_exists($layoutPath) ? file_get_contents($layoutPath) : '{{content}}';
        $layout = $this->parseTemplate($layout, array_merge($defaults, $data, ['content' => $content]));

        echo $layout;
    }

    private function parseTemplate(string $content, array $data): string
    {
        // Includes
        $content = preg_replace_callback('/{{include:([^}]+)}}/', function ($matches) use ($data) {
            $includePath = $this->templateBase . '/' . $matches[1];
            if (pathinfo($includePath, PATHINFO_EXTENSION) === '') {
                $includePath .= '.html';
            }
            if (!file_exists($includePath)) {
                return '';
            }
            $includeContent = file_get_contents($includePath);
            return $this->parseTemplate($includeContent, $data);
        }, $content);

        // Translations
        $content = preg_replace_callback('/{{t:([^}]+)}}/', function ($matches) {
            return t($matches[1]);
        }, $content);

        // Loops
        $content = preg_replace_callback('/{{loop:([a-zA-Z0-9_]+)}}(.*?){{endloop}}/s', function ($matches) use ($data) {
            $key = $matches[1];
            $body = $matches[2];
            $result = '';
            if (!empty($data[$key]) && is_array($data[$key])) {
                foreach ($data[$key] as $item) {
                    $result .= $this->parseTemplate($body, array_merge($data, (array) $item));
                }
            }
            return $result;
        }, $content);

        // Variables
        $content = preg_replace_callback('/{{([a-zA-Z0-9_]+)}}/', function ($matches) use ($data) {
            $key = $matches[1];
            return $data[$key] ?? '';
        }, $content);

        return $content;
    }
}
