<?php
$basePath = realpath(__DIR__ . '/..');
require $basePath . '/i18n/translations.php';
require $basePath . '/core/Router.php';
require $basePath . '/core/View.php';
require $basePath . '/core/Controller.php';
require $basePath . '/core/Model.php';
require $basePath . '/core/Bootstrap.php';
require $basePath . '/seo/Slug.php';
require $basePath . '/seo/SEO.php';
require $basePath . '/seo/Sitemap.php';
require $basePath . '/ai/AI.php';

$bootstrap = new Bootstrap();
$bootstrap->run();
