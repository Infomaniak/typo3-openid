<?php
namespace Vendor\Extension\Tests\Unit;

use GuzzleHttp\Psr7\Request;
use Infomaniak\Auth\Service\AuthenticationService;
use Infomaniak\Auth\Service\OpenIdConnectService;
use League\OAuth2\Client\Provider\GenericProvider;
use TYPO3\CMS\Core\Site\Entity\Site;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class OpenIdConnectServiceTest extends UnitTestCase
{
	public function testGetAuthorizationUrl()
	{
		$options = [
			'clientId' => 'test_client_id',
			'clientSecret' => 'test_client_secret',
			'urlAuthorize' => 'https://example.com/oauth/authorize',
			'urlAccessToken' => 'https://example.com/oauth/token',
			'urlResourceOwnerDetails' => 'https://example.com/oauth/resource',
			'scopes' => ['openid', 'profile', 'email'],
		];

		$requestMock = $this->createMock(Request::class);
		$genericProviderMock = $this->getMockBuilder(GenericProvider::class)
			->setConstructorArgs([$options])
			->onlyMethods(['getRandomState'])
			->getMock();

		$genericProviderMock->expects($this->once())
			->method('getRandomState')
			->willReturn('random_state_value');
	
		$serviceMock = $this->getMockBuilder(OpenIdConnectService::class)
			->disableOriginalConstructor()
			->onlyMethods(['getProvider', 'generateNonce'])
			->getMock();
	
		$serviceMock->expects($this->once())
			->method('getProvider')
			->willReturn($genericProviderMock);

		$serviceMock->expects($this->once())
			->method('generateNonce')
			->willReturn('1234567890abcdef');

		$this->assertSame(
			"https://example.com/oauth/authorize?nonce=1234567890abcdef&state=random_state_value&scope=openid%2Cprofile%2Cemail&response_type=code&approval_prompt=auto&client_id=test_client_id",
			$serviceMock->getAuthorizationUrl($requestMock, null, 'FE')
			);
	}
}
