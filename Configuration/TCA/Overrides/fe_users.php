<?php

defined('TYPO3') or die();

$tempColumns = [
    'infomaniak_auth_id' => [
        'exclude' => true,
        'label' => 'LLL:EXT:infomaniak_auth/Resources/Private/Language/locallang_db.xlf:fe_users.infomaniak_auth_id',
        'config' => [
            'type' => 'input',
            'size' => 30,
            'readOnly' => true,
        ]
    ],
];

\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addTCAcolumns('fe_users', $tempColumns);
\TYPO3\CMS\Core\Utility\ExtensionManagementUtility::addToAllTCAtypes('fe_users', 'infomaniak_auth_id', '', 'after:email');
