<?php
namespace Vendor\Extension\Tests\Unit;

use GuzzleHttp\Psr7\Request;
use Infomaniak\Auth\Service\AuthenticationService;
use Infomaniak\Auth\Service\OpenIdConnectService;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class AuthenticationServiceTest extends UnitTestCase
{
	public static function buildSimpleUrlProvider()
	{
		return [
			'simple url' => [
				'https://example.com',
				'',
			],
			'url with path' => [
				'https://example.com/with-path',
				'/with-path',
			],
			'params in path' => [
				'https://example.com?param=value',
				'?param=value',
			],
			'url with params' => [
				'https://example.com?param=value',
				'',
				['param' => 'value'],
			],
			'url with path and params' => [
				'https://example.com/with-path?param=value',
				'/with-path',
				['param' => 'value'],
			],
			'url with multiple params' => [
				'https://example.com/with-path?param=value&param1=value1&param2=value2',
				'/with-path',
				['param' => 'value', 'param1' => 'value1', 'param2' => 'value2'],
			],
			'url with params with multiple values' => [
				'https://example.com/with-path?param%5B0%5D=value&param%5B1%5D=value1&param%5B2%5D=value2',
				'/with-path',
				['param' => ['value', 'value1', 'value2']],
			],
		];
	}

	#[DataProvider('buildSimpleUrlProvider')]
	public function testBuildSimpleUrl(string $expected, string $path, array $params = [])
	{
		$requestMock = $this->getMockBuilder(Request::class)
			->disableOriginalConstructor()
			->getMock();

		$requestMock->expects($this->once())
			->method('getUri')
			->willReturn(new \GuzzleHttp\Psr7\Uri('https://example.com'));

		$this->assertSame(
			$expected,
			AuthenticationService::buildSimpleUrl(
				$requestMock,
				$path,
				$params
			)
		);
	}

	public function testValidateCode()
	{
		$_SESSION['infomaniakauth_oidc_state'] = [
			'code' => 'valid_code',
			'state' => 'random_state_value',
		];
		$requestMock = $this->getMockBuilder(Request::class)
			->disableOriginalConstructor()
			->getMock();
		
		$openIdConnectService = $this->getMockBuilder(OpenIdConnectService::class)
			->disableOriginalConstructor()
			->onlyMethods(['validateCode'])
			->getMock();

		$openIdConnectService->expects($this->once())
			->method('validateCode')
			->willReturn('valid_code');

		$this->assertSame(
			'valid_code',
			$openIdConnectService->validateCode(new Request('GET', 'https://example.com'))
		);
	}
}
