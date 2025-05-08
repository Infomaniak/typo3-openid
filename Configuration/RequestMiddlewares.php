<?php

use Infomaniak\Auth\Middleware\BackendCallbackMiddleware;
return [
    'backend' => [
        BackendCallbackMiddleware::class => [
            'target' => BackendCallbackMiddleware::class,
            'before' => [
                'typo3/cms-backend/authentication',
            ],
        ]
    ],
];
