<?php
namespace Infomaniak\Tests\Unit\Classes\Service;

use GuzzleHttp\Psr7\Request;
use Infomaniak\Auth\Service\OpenIdConnectService;
use League\OAuth2\Client\Provider\GenericProvider;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\Exception;
use Psr\Http\Message\ServerRequestInterface;
use Random\RandomException;
use TYPO3\CMS\Backend\Controller\DummyController;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class OpenIdConnectServiceTest extends UnitTestCase
{
	public function testGenerateNonceReturns16BytesHex()
	{
		// On crée l'objet réel (avec un tableau de config bidon pour éviter l'exception)
		$service = $this->getMockBuilder(OpenIdConnectService::class)
			->disableOriginalConstructor()
			->onlyMethods([])
			->getMock();

		// On appelle la vraie méthode
		$nonce = $service->generateNonce();

		// bin2hex(random_bytes(16)) retourne une chaîne de 32 caractères hexadécimaux
		$this->assertEquals(32, strlen($nonce), 'Le nonce doit faire 32 caractères (16 bytes en hex)');
		$this->assertMatchesRegularExpression('/^[a-f0-9]{32}$/', $nonce, 'Le nonce doit être hexadécimal');
	}

	public function testGenerateNonceReturns32BytesHex()
	{
		// On crée l'objet réel (avec un tableau de config bidon pour éviter l'exception)
		$service = $this->getMockBuilder(OpenIdConnectService::class)
			->disableOriginalConstructor()
			->onlyMethods([])
			->getMock();

		// On appelle la vraie méthode
		$nonce = $service->generateNonce(32);

		// bin2hex(random_bytes(32)) retourne une chaîne de 64 caractères hexadécimaux
		$this->assertEquals(64, strlen($nonce), 'Le nonce doit faire 64 caractères (32 bytes en hex)');
		$this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $nonce, 'Le nonce doit être hexadécimal');
	}

	public static function getAuthorizationUrlProvider(): array
	{
		return [
			'all params' => [
				'https://example.com/oauth/authorize?nonce=1234567890abcdef&state=random_state_value&scope=openid%2Cprofile%2Cemail&response_type=code&approval_prompt=auto&client_id=test_client_id',
				[
					'clientId' => 'test_client_id',
					'clientSecret' => 'test_client_secret',
					'urlAuthorize' => 'https://example.com/oauth/authorize',
					'urlAccessToken' => 'https://example.com/oauth/token',
					'urlResourceOwnerDetails' => 'https://example.com/oauth/resource',
					'scopes' => ['openid', 'profile', 'email'],
				]
			],
			'with minimal params' => [
				'https://example.com/oauth/authorize?nonce=1234567890abcdef&state=random_state_value&response_type=code&approval_prompt=auto',
				[
					'urlAuthorize' => 'https://example.com/oauth/authorize',
					'urlAccessToken' => 'https://example.com/oauth/token',
					'urlResourceOwnerDetails' => 'https://example.com/oauth/resource',
				]
			],
		];
	}

	/**
	 * @throws Exception
	 * @throws RandomException
	 */
	#[DataProvider('getAuthorizationUrlProvider')]
	public function testGetAuthorizationUrl(string $expectedUrl, array $options)
	{
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

		$generatedUrl = $serviceMock->getAuthorizationUrl($requestMock);
		$this->assertSame(
			$expectedUrl,
			$generatedUrl,
			"The generated authorization URL should match the expected URL.\nExpected: $expectedUrl\nActual: $generatedUrl\n"
			);
	}

	public function testValidateCode()
	{
		$_SESSION['infomaniakauth_oidc_state'] = 'random_state_value';

		$requestMock = $this->getMockBuilder(ServerRequestInterface::class)
			->disableOriginalConstructor()
			->getMock();
		
		$requestMock->expects($this->once())
			->method('getQueryParams')
			->willReturn(['state' => 'random_state_value', 'code' => 'valid_code']);

		$serviceMock = $this->getMockBuilder(OpenIdConnectService::class)
			->disableOriginalConstructor()
			->onlyMethods([])
			->getMock();

		$code = $serviceMock->validateCode(request: $requestMock);
		
		$this->assertSame('valid_code', $code, 'The code should match the one stored in the session.');
		
		unset($_SESSION['infomaniakauth_oidc_state']);
	}

	public function testValidateCodeStateDoesntMatch()
	{
		$_SESSION['infomaniakauth_oidc_state'] = 'random_state_value_invalid';

		$requestMock = $this->getMockBuilder(ServerRequestInterface::class)
			->disableOriginalConstructor()
			->getMock();
		
		$requestMock->expects($this->once())
			->method('getQueryParams')
			->willReturn(['state' => 'random_state_value', 'code' => 'valid_code']);

		$serviceMock = $this->getMockBuilder(OpenIdConnectService::class)
			->disableOriginalConstructor()
			->onlyMethods([])
			->getMock();

		$this->expectException(\InvalidArgumentException::class);
		$serviceMock->validateCode(request: $requestMock);
		unset($_SESSION['infomaniakauth_oidc_state']);
	}
}
