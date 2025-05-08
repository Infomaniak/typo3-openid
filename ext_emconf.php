<?php
$EM_CONF[$_EXTKEY] = [
    'title' => 'Infomaniak OpenID Connect Authentication',
    'description' => 'This extension uses OpenID Connect to authenticate users.',
    'category' => 'services',
    'author' => 'Someone',
    'author_company' => 'Infomaniak',
    'author_email' => 'someone@infomaniak.com',
    'state' => 'stable',
    'version' => '1.0.0',
    'constraints' => [
        'depends' => [
            'php' => '8.1.99-8.4.99',
            'typo3' => '11.5.0-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
