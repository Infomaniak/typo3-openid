<?php

declare(strict_types=1);

use Infomaniak\Auth\Controller\LoginController;
use Infomaniak\Auth\LoginProvider\InfomaniakLoginProvider;
use Infomaniak\Auth\Service\AuthenticationService;
use TYPO3\CMS\Core\Utility\ExtensionManagementUtility;
use TYPO3\CMS\Extbase\Utility\ExtensionUtility;

// Ensure the script is executed only in the TYPO3 context
defined('TYPO3') or die();

// Always load the extension typoscript
// Setting the typenum for the Infomaniak callback
ExtensionManagementUtility::addTypoScript(
    'infomaniak_auth',
    'setup',
    '
    @import \'EXT:infomaniak_auth/Configuration/TypoScript/setup.typoscript\'
    infomaniakOauthCallback.typeNum = ' . AuthenticationService::AUTH_INFOMANIAK_CODE . '
    ',
);

// Configure a TYPO3 frontend plugin for the 'InfomaniakAuth' extension
ExtensionUtility::configurePlugin(
    'InfomaniakAuth',
    'InfomaniakAuthLogin',
    // List of controller actions available for this plugin
    [
        LoginController::class => 'login,callback',
    ],
    // List of non-cacheable controller actions (same as above in this case)
    [
        LoginController::class => 'login,callback',
    ],
    // Specify that this is a content element plugin
    ExtensionUtility::PLUGIN_TYPE_CONTENT_ELEMENT,
);

// Register an authentication service in TYPO3
ExtensionManagementUtility::addService(
    'infomaniak_auth',
    'auth',
    AuthenticationService::class,
    // Additional metadata about the service
    [
        'title' => 'Authentication service',
        'description' => 'Authentication service for Infomaniak Auth.',
        'subtype' => 'getUserFE,authUserFE,getUserBE,authUserBE',
        'available' => true,
        'priority' => 60,
        'quality' => 80,
        'os' => '',
        'exec' => '',
        'className' => AuthenticationService::class,
    ]
);

// Exclude specific parameters ('state', 'code') from frontend cache keys otherwise the chash will be invalid
$GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'] = array_merge(
    $GLOBALS['TYPO3_CONF_VARS']['FE']['cacheHash']['excludedParameters'],
    ['state', 'code', 'tx_infomaniakauth_login']
);

// Configure a custom login provider for the TYPO3 backendAuthenticationService::AUTH_INFOMANIAK_CODE
$GLOBALS['TYPO3_CONF_VARS']['EXTCONF']['backend']['loginProviders'][AuthenticationService::AUTH_INFOMANIAK_CODE] = [
    'provider' => InfomaniakLoginProvider::class,
    'sorting' => 25,
    'iconIdentifier' => 'infomaniak_auth_square',
    'label' => 'LLL:EXT:infomaniak_auth/Resources/Private/Language/locallang.xlf:login.link'
];
