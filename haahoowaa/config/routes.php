<?php
return [
    'home' => [
        'path' => '/',
        'module' => 'pages',
        'action' => 'home',
    ],
    'product_list' => [
        'path' => '/products',
        'module' => 'products',
        'action' => 'list',
    ],
    'product_detail' => [
        'path' => '/products/{slug}',
        'module' => 'products',
        'action' => 'detail',
    ],
    'shop_detail' => [
        'path' => '/shops/{slug}',
        'module' => 'shops',
        'action' => 'detail',
    ],
];
