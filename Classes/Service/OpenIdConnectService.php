<?php

namespace Infomaniak\Auth\Service;

// Required class imports
use GuzzleHttp\Exception\GuzzleException;
use Infomaniak\Auth\Middleware\BackendCallbackMiddleware;
use InvalidArgumentException;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use League\OAuth2\Client\Provider\GenericProvider;
use League\OAuth2\Client\Token\AccessToken;
use Psr\Http\Message\RequestInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerAwareInterface;
use Psr\Log\LoggerAwareTrait;
use Random\RandomException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\CMS\Core\Site\SiteFinder;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Frontend\ContentObject\ContentObjectRenderer;

class OpenIdConnectService implements LoggerAwareInterface
{
    use LoggerAwareTrait;

    // Holds the extension configuration
    protected array $config;

    // OAuth2 provider instance (cached)
    protected ?GenericProvider $provider = null;

    /**
     * Constructor
     * Loads configuration either from parameter or TYPO3 extension configuration.
     *
     * @param array $config Optional configuration array
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    public function __construct(array $config = [])
    {
        $this->config = $config ?: $this->loadExtensionConfig();

        // List of required keys for the configuration
        $requiredKeys = [
            'clientId',
            'clientSecret',
            'endpointAuthorize',
            'endpointToken',
            'endpointUserInfo',
            'clientScopes'
        ];

        // Check if all required keys are present in the configuration. If not, throw an exception.
        foreach ($requiredKeys as $key) {
            if (empty($this->config[$key])) {
                throw new InvalidArgumentException("Missing extension configuration: $key", 1715775147);
            }
        }
    }

    /**
     * Returns a configured OAuth2 GenericProvider instance.
     * Uses a cached version if already created.
     *
     * @param ServerRequestInterface|RequestInterface $request
     * @param string $context
     * @return GenericProvider
     */
    public function getProvider(
        ServerRequestInterface|RequestInterface $request,
        string $context = 'FE'
    ): GenericProvider {
        if ($this->provider !== null) {
            return $this->provider;
        }

        // Create new provider with configuration and Guzzle client
        $this->provider = new GenericProvider([
            'clientId' => $this->config['clientId'],
            'clientSecret' => $this->config['clientSecret'],
            'redirectUri' => $this->buildCallbackUrl(
                $request,
                ['type' => AuthenticationService::AUTH_INFOMANIAK_CODE],
                $context
            ),
            'urlAuthorize' => $this->config['endpointAuthorize'],
            'urlAccessToken' => $this->config['endpointToken'],
            'urlResourceOwnerDetails' => $this->config['endpointUserInfo'],
            'scopes' => $this->config['clientScopes'],
        ]);

        return $this->provider;
    }

    /**
     * Builds the OAuth2 authorization URL with optional custom redirect URI.
     * Stores the state in session for CSRF protection.
     *
     * @param ServerRequestInterface|RequestInterface $request
     * @param string|null $redirectUrl Optional override for the redirect URI
     * @param string $context
     * @return string
     * @throws RandomException
     */
    public function getAuthorizationUrl(
        ServerRequestInterface|RequestInterface $request,
        ?string $redirectUrl = null,
        string $context = 'FE'
    ): string {
        $provider = $this->getProvider($request, $context);
        $options = ['nonce' => bin2hex(random_bytes(16))];
        if ($redirectUrl !== null) {
            $options['redirect_uri'] = $redirectUrl;
        }

        $authorizationUrl = $provider->getAuthorizationUrl($options);

        $this->startSession();
        $_SESSION['infomaniakauth_oidc_state'] = $provider->getState();

        return $authorizationUrl;
    }

    /**
     * Validates the "state" returned from the OAuth2 server.
     * Prevents CSRF by comparing it to the session value.
     *
     * @param ServerRequestInterface $request
     * @return string The authorization code
     */
    public function validateCode(ServerRequestInterface $request): string
    {
        $this->startSession();

        $sessionState = $_SESSION['infomaniakauth_oidc_state'] ?? null;
        unset($_SESSION['infomaniakauth_oidc_state']);

        $queryParams = $request->getQueryParams();
        $returnedState = $queryParams['state'] ?? null;
        $returnedCode = $queryParams['code'] ?? null;

        if (empty($sessionState) || $returnedState !== $sessionState) {
            throw new InvalidArgumentException('Invalid state', 1715775148);
        }

        return $returnedCode;
    }

    /**
     * Retrieves the authenticated user information from the provider.
     *
     * @param GenericProvider $provider
     * @param AccessToken $accessToken
     * @return array
     */
    public function getUserInfo(GenericProvider $provider, AccessToken $accessToken): array
    {
        try {
            return $provider
                ->getResourceOwner($accessToken)
                ->toArray();
        } catch (GuzzleException|IdentityProviderException $e) {
            $this->logger?->error('OIDC UserInfo error', ['exception' => $e]);
            return [];
        }
    }

    /**
     * Builds the callback URL based on the context (Frontend or Backend).
     *
     * @param ServerRequestInterface|RequestInterface $request
     * @param array $arguments
     * @param string $context
     * @return string
     * @throws InvalidArgumentException
     */
    protected function buildCallbackUrl(
        ServerRequestInterface|RequestInterface $request,
        array $arguments,
        string $context
    ): string {
        return match ($context) {
            'FE' => $this->buildUriToFrontendHomepage($request, $arguments),
            'BE' => $this->buildUriToBeLoginCallback($request),
            default => throw new InvalidArgumentException('Invalid context. Should be BE or FE', 1715775149),
        };
    }

    /**
     * Builds an absolute URL to the site's homepage with additional query parameters.
     *
     * @param ServerRequestInterface $request
     * @param array $arguments
     * @return string
     */
    public function buildUriToFrontendHomepage(ServerRequestInterface $request, array $arguments): string
    {
        /** @var Site $site */
        $site = $this->getSiteFromRequest($request);

        /** @var ContentObjectRenderer $cObj */
        $cObj = GeneralUtility::makeInstance(ContentObjectRenderer::class);

        return $cObj->createUrl([
            'parameter' => $site->getRootPageId(),
            'forceAbsoluteUrl' => true,
            'additionalParams' => '&' . http_build_query($arguments),
        ]);
    }

    protected function buildUriToBeLoginCallback(RequestInterface $request): string
    {
        return AuthenticationService::buildSimpleUrl(
            $request,
            '/typo3/' . BackendCallbackMiddleware::BACKEND_CALLBACK_PATH
        );
    }

    /**
     * Loads extension configuration from TYPO3.
     *
     * @return array
     * @throws ExtensionConfigurationExtensionNotConfiguredException
     * @throws ExtensionConfigurationPathDoesNotExistException
     */
    protected function loadExtensionConfig(): array
    {
        return GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('infomaniak_auth') ?? [];
    }

    /**
     * Starts the session if not already active.
     */
    protected function startSession(): void
    {
        if (session_status() !== PHP_SESSION_ACTIVE) {
            session_start();
        }
    }

    protected function getSiteFromRequest(ServerRequestInterface|RequestInterface $request)
    {
        // This is an Extbase Request, so it already has the site as an attribute
        $siteAttribute = $request instanceof ServerRequestInterface ? $request->getAttribute('site') : null;
        if ($siteAttribute instanceof Site) {
            return $siteAttribute;
        }

        // This is a standard request, so we need to find the site based on the host
        $currentHost = $request->getUri()->getHost();
        /** @var SiteFinder $siteFinder */
        $siteFinder = GeneralUtility::makeInstance(SiteFinder::class);
        $allSites = $siteFinder->getAllSites();
        foreach ($allSites as $site) {
            if ($site->getBase()->getHost() === $currentHost) {
                return $site;
            }
        }
        // Weird, we should have found a site
        throw new InvalidArgumentException('No site found in request', 1715775150);
    }
}
