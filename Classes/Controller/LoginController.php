<?php

/** @noinspection PhpInternalEntityUsedInspection */

namespace Infomaniak\Auth\Controller;

use Infomaniak\Auth\Service\AuthenticationService;
use Infomaniak\Auth\Service\OpenIdConnectService;
use Psr\Http\Message\ResponseInterface;
use Random\RandomException;
use TYPO3\CMS\Core\Security\RequestTokenException;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Mvc\Controller\ActionController;

class LoginController extends ActionController
{
    protected OpenIdConnectService $openIdConnectService;

    public function __construct()
    {
        /** @var OpenIdConnectService $openIdConnectService */
        $openIdConnectService = GeneralUtility::makeInstance(OpenIdConnectService::class);
        $this->openIdConnectService = $openIdConnectService;
    }

    /**
     * @throws RandomException
     * @throws RequestTokenException
     */
    public function loginAction(): ResponseInterface
    {
        // We are in the callback process after login on Infomaniak
        if ((int) $this->request->getAttribute('routing')->getPageType() === AuthenticationService::AUTH_INFOMANIAK_CODE) {
            return $this->callbackAction();
        }

        // We clicked on the login button, so we need to redirect to Infomaniak login page
        if ($this->request->getMethod() === 'POST' && strtolower($this->request->getParsedBody()['logintype']) !== 'logout') {
            $uri = $this->openIdConnectService->getAuthorizationUrl($this->request);
            return $this->redirectToUri($uri);
        }

        return $this->htmlResponse();
    }

    /**
     * @return ResponseInterface
     */
    public function callbackAction(): ResponseInterface
    {
        // This is the callback action after the user has logged in on Infomaniak
        // The users clicked on the Deny button
        if (isset($this->request->getQueryParams()['error'])) {
            $redirectUrl = $this->openIdConnectService->buildUriToFrontendHomepage($this->request,[]);
        }
        // The users clicked on the Authorise button
        // We build the redirect URL to the TYPO3 login page with the code, so we enter the login process
        else {
            $redirectUrl = $this->openIdConnectService->buildUriToFrontendHomepage(
                $this->request,
                [
                    'tx_infomaniakauth_login' => [
                        'action' => 'callback',
                    ],
                    'code' => $this->openIdConnectService->validateCode($this->request),
                    'logintype' => 'login'
                ]
            );
        }
        return $this->redirectToUri($redirectUrl);
    }

}

