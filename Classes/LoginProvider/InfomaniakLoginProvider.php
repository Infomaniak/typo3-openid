<?php

namespace Infomaniak\Auth\LoginProvider;

/*
 * This file is part of the TYPO3 CMS project.
 *
 * It is free software; you can redistribute it and/or modify it under
 * the terms of the GNU General Public License, either version 2
 * of the License, or any later version.
 *
 * For the full copyright and license information, please read the
 * LICENSE.txt file that was distributed with this source code.
 *
 * The TYPO3 project - inspiring people to share!
 */

use Infomaniak\Auth\Service\AuthenticationService;
use Random\RandomException;
use TYPO3\CMS\Backend\Controller\LoginController;
use TYPO3\CMS\Backend\LoginProvider\LoginProviderInterface;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Page\PageRenderer;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Fluid\View\StandaloneView;

/**
 * Class OpenIdLoginProvider
 *
 * Here we implement the login provider for Infomaniak
 * We handler two cases:
 * 1. The user is redirected to the Infomaniak login page because the user is not logged in and we do not have a code
 * yet
 * 2. We received a code and state from the Infomaniak login page, we need to redirect to the TYPO3 login page with the
 * code and state, and enter the login process
 */
class InfomaniakLoginProvider implements LoginProviderInterface
{
    /**
     * @param StandaloneView $view
     * @param PageRenderer $pageRenderer
     * @param LoginController $loginController
     * @throws PropagateResponseException
     * @throws RandomException
     */
    public function render(StandaloneView $view, PageRenderer $pageRenderer, LoginController $loginController): void
    {
        // Extract necessary parameters
        $request = $GLOBALS['TYPO3_REQUEST'];

        $queryParams = $request->getQueryParams();
        $loginProvider = $queryParams['loginProvider'] ?? null;
        $code = $queryParams['code'] ?? null;
        $state = $queryParams['state'] ?? null;

        // This is a Infomaniak login process, we handle it
        if ((int)$loginProvider === AuthenticationService::AUTH_INFOMANIAK_CODE && $code && $state && !isset($queryParams['tx_infomaniakauth_login'])) {
            // We have a code and state, so we redirect to the TYPO3 login process
            // Build redirect URL with additional query parameters for login
            $additionalParams = [
                'code' => $code,
                'state' => $state,
                'login_status' => 'login',
                'loginProvider' => AuthenticationService::AUTH_INFOMANIAK_CODE,
                'tx_infomaniakauth_login' => 1
            ];
            $redirectUrl = AuthenticationService::buildSimpleUrl($request, '/typo3/login', $additionalParams);

            // Redirect to the TYPO3 login page with the code and state, so we enter the login process
            throw new PropagateResponseException(new RedirectResponse($redirectUrl, 303));
        } elseif (isset($queryParams['tx_infomaniakauth_login'])) {
            // We already went through login process, but we are not logged in
            $redirectUrl = AuthenticationService::buildSimpleUrl($request, '/typo3/login', [
                'loginProvider' => AuthenticationService::AUTH_INFOMANIAK_CODE
            ]);
            throw new PropagateResponseException(new RedirectResponse($redirectUrl, 303));
        }


        // We are not logged in and it's the first load of the Infomaniak Login Provider
        // so we redirect to the Infomaniak login page


        /** @var FlashMessageService $flashMessageService */
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $flashMessageQueue = $flashMessageService->getMessageQueueByIdentifier('infomaniak_auth');
        try {
            $messages = $flashMessageQueue->getAllMessagesAndFlush();
        } catch (\Throwable) {
            $messages = [];
        }

        if (!empty($messages)) {
            $view->assign('messages', $messages);
        }

        $view->setTemplatePathAndFilename(
            GeneralUtility::getFileAbsFileName('EXT:infomaniak_auth/Resources/Private/Templates/Backend/Login.html')
        );
    }
}
