<?php

use Infomaniak\Auth\EventListener\ProcessRequestTokenListener;
use TYPO3\CMS\Core\Authentication\Event\BeforeRequestTokenProcessedEvent;
use TYPO3\CMS\Core\Authentication\AbstractUserAuthentication;
use TYPO3\CMS\Core\Security\RequestToken;
use Psr\Http\Message\ServerRequestInterface;
use PHPUnit\Framework\TestCase;

class ProcessRequestTokenListenerTest extends TestCase
{
	public function testDoesNothingIfTokenIsAlreadyRequestToken()
	{
		$user = $this->createMock(AbstractUserAuthentication::class);
		$requestMock = $this->createMock(ServerRequestInterface::class);
		$requestTokenMock = $this->createMock(RequestToken::class);

		$event = new BeforeRequestTokenProcessedEvent($user, $requestMock, $requestTokenMock);

		$listener = new ProcessRequestTokenListener();
		$listener($event);

		$this->assertSame($requestTokenMock, $event->getRequestToken());
	}

	public function testSetsRequestTokenIfParamPresent()
	{
		$user = $this->createMock(AbstractUserAuthentication::class);
		$requestMock = $this->createMock(ServerRequestInterface::class);
		$requestMock->method('getQueryParams')->willReturn(['tx_infomaniakauth_login' => 1]);

		$event = new BeforeRequestTokenProcessedEvent($user, $requestMock, null);

		$listener = new ProcessRequestTokenListener();
		$listener($event);

		$token = $event->getRequestToken();
		$this->assertInstanceOf(RequestToken::class, $token);
		$this->assertSame('core/user-auth/' . $user->loginType, $token->scope);
	}

	public function testDoesNothingIfParamNotPresent()
	{
		$user = $this->createMock(AbstractUserAuthentication::class);
		$requestMock = $this->createMock(ServerRequestInterface::class);
		$requestMock->method('getQueryParams')->willReturn([]);

		$event = new BeforeRequestTokenProcessedEvent($user, $requestMock, null);

		$listener = new ProcessRequestTokenListener();
		$listener($event);

		$this->assertNull($event->getRequestToken());
	}
}
