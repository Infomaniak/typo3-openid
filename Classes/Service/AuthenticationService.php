<?php

/** @noinspection PhpUnused */

declare(strict_types=1);

namespace Infomaniak\Auth\Service;

use Doctrine\DBAL\Exception;
use GuzzleHttp\Exception\GuzzleException;
use Infomaniak\Auth\Middleware\BackendCallbackMiddleware;
use Infomaniak\Mock\Config;
use League\OAuth2\Client\Provider\Exception\IdentityProviderException;
use Psr\Http\Message\RequestInterface;
use Random\RandomException;
use RuntimeException;
use TYPO3\CMS\Core\Authentication\AbstractAuthenticationService;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationExtensionNotConfiguredException;
use TYPO3\CMS\Core\Configuration\Exception\ExtensionConfigurationPathDoesNotExistException;
use TYPO3\CMS\Core\Configuration\ExtensionConfiguration;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Database\Query\QueryBuilder;
use TYPO3\CMS\Core\Http\PropagateResponseException;
use TYPO3\CMS\Core\Http\RedirectResponse;
use TYPO3\CMS\Core\Http\ServerRequestFactory;
use TYPO3\CMS\Core\Messaging\FlashMessage;
use TYPO3\CMS\Core\Messaging\FlashMessageService;
use TYPO3\CMS\Core\Type\ContextualFeedbackSeverity;
use TYPO3\CMS\Core\Utility\GeneralUtility;

/**
 * OpenID Connect authentication service.
 */
class AuthenticationService extends AbstractAuthenticationService
{
    /**
     * Infomaniak authentication code
     */
    const int AUTH_INFOMANIAK_CODE = 0;

    /**
     * DB Field where we store the Infomaniak user ID
     */
    const string AUTH_INFOMANIAK_DB_FIELD = 'infomaniak_auth_id';

    /**
     * 200 - we did it
     * This provider was able to authenticate the user
     * and we are done with the authentication process.
     */
    const int STATUS_AUTHENTICATION_SUCCESS_BREAK = 0;

    /**
     * 100 - just go on.
     * This provider was not able to authenticate the user
     * but the next provider might be able to do so.
     */
    const int STATUS_AUTHENTICATION_FAILURE_CONTINUE = 0;

    /**
     * Global extension configuration
     *
     * @var array
     */
    protected array $config;

    /**
     * @var OpenIdConnectService $openIdConnectService
     */
    protected mixed $openIdConnectService;

    /**
     * 'FE' or 'BE'
     * @var string $loginMode
     */
    protected string $loginMode;

    /**
     * @var RequestInterface $request
     */
    protected RequestInterface $request;

    /**
     * @var array $userMapping ;
     */
    protected array $userMapping = [];

    /**
     * @var QueryBuilder $queryBuilder
     */
    protected QueryBuilder $queryBuilder;


    /**
     * AuthenticationService constructor.
     */
    public function __construct()
    {
        // $this->setRequest();
        try {
            $this->config = GeneralUtility::makeInstance(ExtensionConfiguration::class)->get('infomaniak_auth') ?? [];
            $this->openIdConnectService = GeneralUtility::makeInstance(OpenIdConnectService::class);
        } catch (ExtensionConfigurationExtensionNotConfiguredException|ExtensionConfigurationPathDoesNotExistException $e) {
            throw new RuntimeException(
                'Extension configuration not found: ' . $e->getMessage(),
                1715775147,
                $e
            );
        }
    }

    /**
     * Authenticates a user
     *
     * @param array $user
     * @return int
     */
    public function authUser(array $user): int
    {
        // This is not a valid user authenticated via OIDC
        if (empty($user[self::AUTH_INFOMANIAK_DB_FIELD])) {
            return self::STATUS_AUTHENTICATION_FAILURE_CONTINUE;
        }
        return self::STATUS_AUTHENTICATION_SUCCESS_BREAK;
    }

    /**
     * Finds a user.
     *
     * @return int|bool|array
     * @throws GuzzleException
     * @throws IdentityProviderException
     * @throws PropagateResponseException
     * @throws RandomException|Exception
     */
    public function getUser(): bool|array
    {
        $this->loginMode = $this->authInfo['loginType'];

        // This is not a Infomaniak Login Process, we do not handle this request
        switch ($this->loginMode) {
            case 'FE':
                // We try to log the user in, and if allowed,
                // create / update the user in the database
                return $this->handlerUserData();
            case 'BE':
                // This is not the Infomaniak Backend Login Process, we do not handle this request
                if ((int)$this->request->getQueryParams()['loginProvider'] !== self::AUTH_INFOMANIAK_CODE) {
                    return false;
                }
                // The Backend Login Form has been submitted
                // So we redirect to the Infomaniak login page
                if ($this->request->getMethod() === 'POST') {
                    $this->redirectToInfomaniakLoginPage();
                }
                // We try to log the user in, and if allowed,
                // create / update the user in the database
                return $this->handlerUserData();

            default:
                return false;
        }
    }


    /**
     * Handles the user data after the Infomaniak login process
     * Creates or updates the user in the database, depending on the extension configuration
     * @throws GuzzleException
     * @throws IdentityProviderException
     * @throws Exception
     */
    protected function handlerUserData(): array|bool
    {
        $userData = $this->getUserFromOidc();
        $sub = $userData['sub'] ?? null;
        if (empty($sub)) {
            return false;
        }

        switch ($this->loginMode) {
            case 'BE':
                $this->userMapping = [
                    $this->db_user['username_column'] => $userData['email'],
                    self::AUTH_INFOMANIAK_DB_FIELD => $sub,
                    'username' => $userData['email'],
                    'email' => $userData['email'],
                    'realName' => $userData['name'],
                    'usergroup' => $this->config['beuser']['defaultGroups'] ?? '',
                ];
                break;
            case 'FE':
                $this->userMapping = [
                    $this->db_user['username_column'] => $userData['email'],
                    self::AUTH_INFOMANIAK_DB_FIELD => $sub,
                    'username' => $userData['email'],
                    'email' => $userData['email'],
                    'name' => $userData['name'],
                    'first_name' => $userData['given_name'],
                    'last_name' => $userData['family_name'],
                    'usergroup' => $this->config['feuser']['defaultGroups'] ?? '',
                ];
                break;
        }

        // Check if the user exists in the database
        if ($userUid = $this->userExists($userData)) {
            // Update the user data
            if ($this->canUpdateUser()) {
                $userUid = $this->userUpdate($userUid);
            }
        } else {
            if ($this->canCreateUser()) {
                $userUid = $this->userCreate($userData);
            } else {
                $this->registerErrorFlashMessage('User not found and creation is not allowed', 'User not found');
            }
        }

        return $userUid ? $this->getUserByUid((int) $userUid) : false;
    }


    /**
     * We are not logged in and it's the first load of the Infomaniak Login Provider
     * so we redirect to the Infomaniak login page
     * @throws RandomException
     * @throws PropagateResponseException
     */
    protected function redirectToInfomaniakLoginPage()
    {
        // Build redirect URL for OAuth authorization callback
        $redirectUrl = self::buildSimpleUrl(
            $this->request,
            '/typo3/' . BackendCallbackMiddleware::BACKEND_CALLBACK_PATH
        );
        $infomaniakAuthFormUrl = $this->openIdConnectService->getAuthorizationUrl($this->request, $redirectUrl, 'BE');

        // Redirecting to Infomaniak login page
        throw new PropagateResponseException(new RedirectResponse($infomaniakAuthFormUrl, 303), 1746548701);
    }

    /**
     * Generates a full redirect URL with optional query parameters.
     *
     * @param RequestInterface $request The current request object.
     * @param string $path The URL path.
     * @param array $params Optional query parameters.
     * @return string          Constructed full URL.
     */
    static function buildSimpleUrl(RequestInterface $request, string $path, array $params = []): string
    {
        $uri = $request->getUri();
        $scheme = $uri->getScheme();
        $host = $uri->getHost();
        $queryString = $params ? '?' . http_build_query($params) : '';
        return $scheme . '://' . $host . $path . $queryString;
    }


    /**
     * @throws IdentityProviderException
     * @throws GuzzleException
     */
    protected function getUserFromOidc(): array
    {
        $params = $this->request->getQueryParams();
        $code = $params['code'] ?? null;
        $provider = $this->openIdConnectService->getProvider($this->request, $this->loginMode);

        // Try to get an access token using the authorization code grant.
        $tokens = $provider->getAccessToken('authorization_code', [
            'code' => $code,
        ]);

        return $this->openIdConnectService->getUserInfo($provider, $tokens);
    }

    /**
     * Check if the user exists in the database.
     * Returns the user ID if the user exists, false otherwise.
     * @param array $userData Array of user data found in the OIDC response
     * @throws Exception
     */
    protected function userExists($userData): mixed
    {
        $row = null;
        // First we try to find a user having the sub identifier
        if (isset($userData['sub'])) {
            $row = $this->getUserBySub($userData['sub']);
        }
        // If not found, we try to find a user having the email address
        if (!$row && isset($userData['email'])) {
            $row = $this->getUserByEmail($userData['email']);
        }
        // If we found a user, we return the user ID
        if ($row && isset($row['uid'])) {
            return $row['uid'];
        }
        // User not found
        return false;
    }

    /**
     * Get the user by sub identifier
     * @param string $sub User sub identifier
     * @return array|false
     * @throws Exception
     */
    protected function getUserBySub(string $sub): array|false
    {
        $this->getQueryBuilder(true);
        $conditions = [
            $this->getQueryBuilder()->expr()->eq(
                self::AUTH_INFOMANIAK_DB_FIELD,
                $this->getQueryBuilder()->createNamedParameter($sub)
            ),
        ];
        return $this->querySelectInDb($conditions);
    }

    /**
     * Get the user by uid
     * @param int $uid User Uid
     * @return array|false
     * @throws Exception
     */
    protected function getUserByUid(int $uid): array|false
    {
        $this->getQueryBuilder(true);
        $conditions = [
            $this->getQueryBuilder()->expr()->eq('uid', $this->getQueryBuilder()->createNamedParameter($uid)),
        ];
        return $this->querySelectInDb($conditions);
    }

    /**
     * Get the user by email address
     * @param string $email
     * @return array|false
     * @throws Exception
     */
    protected function getUserByEmail(string $email): array|false
    {
        $this->getQueryBuilder(true);
        $conditions = [
            $this->getQueryBuilder()->expr()->eq('email', $this->getQueryBuilder()->createNamedParameter($email)),
        ];
        return $this->querySelectInDb($conditions);
    }


    /**
     * Update the user in the database
     * @param int $userUid User ID
     * @return int|bool
     * @throws Exception
     */
    protected function userUpdate(int $userUid): int|bool
    {
        $this->getQueryBuilder(true)
            ->update($this->db_user['table'])
            ->where(
                $this->getQueryBuilder()->expr()->eq('uid', $this->getQueryBuilder()->createNamedParameter($userUid))
            );

        foreach ($this->userMapping as $field => $value) {
            if ($field !== 'uid') {
                $this->getQueryBuilder()->set($field, $value);
            }
        }

        $this->getQueryBuilder()->executeQuery();

        $row = $this->getUserByUid($userUid);
        // If we found a user, we return the user ID
        if ($row && isset($row['uid'])) {
            return $row['uid'];
        }
        return false;
    }

    /**
     * Create the user in the database
     * @param array $userData Array of user data found in the OIDC response
     * @return bool|int
     * @throws Exception
     */
    protected function userCreate(array $userData): bool|int
    {
        $this->getQueryBuilder(true)
            ->insert($this->db_user['table'])
            ->values($this->userMapping)
            ->executeQuery();


        $row = $this->getUserBySub((string)$userData['sub']);
        // If we found a user, we return the user ID
        if ($row && isset($row['uid'])) {
            return $row['uid'];
        }

        return false;
    }


    public function canCreateUser(): bool
    {
        $key = $this->loginMode === 'BE' ? 'beuser' : 'feuser';
        return (bool)$this->config[$key]['createIfNotExist'] ?? false;
    }

    public function canUpdateUser(): bool
    {
        $key = $this->loginMode === 'BE' ? 'beuser' : 'feuser';
        return (bool)$this->config[$key]['updateIfExist'] ?? false;
    }

    /**
     * Query the database to find a user
     * @param array $conditions Array of conditions to apply to the query
     * @throws Exception
     */
    protected function querySelectInDb(array $conditions): array|false
    {
        return $this->getQueryBuilder()
            ->select('*')
            ->from($this->db_user['table'])
            ->where(...$conditions)
            ->executeQuery()
            ->fetchAssociative();
    }

    /**
     * Get the query builder for the user table.
     * Inits it if not already done.
     * @param bool $reset Force to reset the query builder
     * @return QueryBuilder
     */
    protected function getQueryBuilder(bool $reset = false): QueryBuilder
    {
        if (isset($this->queryBuilder) && !$reset) {
            return $this->queryBuilder;
        }
        // reset the query builder
        $this->queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable($this->db_user['table']);
        $this->queryBuilder->getRestrictions()->removeAll();
        return $this->queryBuilder;
    }


    /**
     * Set the request object
     * @return void
     */
    public function setRequest(): void
    {
        $this->request = $this->authInfo['request'] ?? $GLOBALS['TYPO3_REQUEST'] ?? ServerRequestFactory::fromGlobals();
    }

    /**
     * Stores an error message in the flash message queue
     * @param string $message
     * @param string $title
     * @return void
     */
    protected function registerErrorFlashMessage(string $message, string $title): void
    {
        $flashMessageService = GeneralUtility::makeInstance(FlashMessageService::class);
        $flashMessageService->getMessageQueueByIdentifier('infomaniak_auth')->addMessage(
            new FlashMessage(
                $message,
                $title,
                ContextualFeedbackSeverity::ERROR,
                true,
            )
        );
    }

    /**
     * Set the login mode
     * @param string $loginMode
     * @return void
     */
    public function setLoginMode(string $loginMode)
    {
        $this->loginMode = $loginMode;
    }

    /**
     * Get the login mode
     * @return string
     */
    public function getLoginMode(): string
    {
        return $this->loginMode;
    }

}
