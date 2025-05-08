<?php

/** @noinspection PhpUnused */
/** @noinspection PhpInternalEntityUsedInspection */

declare(strict_types=1);

namespace Infomaniak\Auth\EventListener;

use TYPO3\CMS\Core\Authentication\Event\BeforeRequestTokenProcessedEvent;
use TYPO3\CMS\Core\Security\RequestToken;

/**
 * Final class to handle events related to processing request tokens.
 */
final class ProcessRequestTokenListener
{
    /**
     * Invokes the listener to handle the BeforeRequestTokenProcessedEvent.
     *
     * @param BeforeRequestTokenProcessedEvent $event The event triggered before a request token is processed.
     */
    public function __invoke(BeforeRequestTokenProcessedEvent $event): void
    {
        // Retrieve the user associated with the current event.
        $user = $event->getUser();

        // Get the current request token.
        $requestToken = $event->getRequestToken();

        // Check if the current token is already a valid instance of RequestToken.
        if ($requestToken instanceof RequestToken) {
            // If the token is valid, no further changes are needed.
            return;
        }
        // Check if the specific parameter 'tx_infomaniakauth_login' exists in the request query parameters.
        if (isset($event->getRequest()->getQueryParams()['tx_infomaniakauth_login'])) {
            // We create a new request token using the user's login type.
            $event->setRequestToken(
                RequestToken::create('core/user-auth/' . strtolower($user->loginType))
            );
        }
    }
}
