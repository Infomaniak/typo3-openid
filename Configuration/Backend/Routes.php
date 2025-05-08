<?php

use Infomaniak\Auth\Middleware\BackendCallbackMiddleware;
use TYPO3\CMS\Backend\Controller;

return [
    // Login screen of the TYPO3 Backend
    BackendCallbackMiddleware::BACKEND_CALLBACK_PATH => [
        'path' => '/' . BackendCallbackMiddleware::BACKEND_CALLBACK_PATH,
        'access' => 'public',
        'target' => Controller\LoginController::class . '::formAction',
        'options' => [
            'parameters' => [
                'loginProvider' => 1,
                "state" => 1,
                "code" => 1
            ],
        ],
    ]

];
