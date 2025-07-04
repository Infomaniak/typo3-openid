<?php
namespace Infomaniak\Tests\Unit\Classes\Service;

use GuzzleHttp\Psr7\Request;
use GuzzleHttp\Psr7\Uri;
use Infomaniak\Auth\Service\AuthenticationService;
use Infomaniak\Auth\Service\OpenIdConnectService;
use PHPUnit\Framework\Attributes\DataProvider;
use TYPO3\TestingFramework\Core\Unit\UnitTestCase;

class AuthenticationServiceTest extends UnitTestCase
{
	public static function buildSimpleUrlProvider(): array
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
			->willReturn(new Uri('https://example.com'));

		$this->assertSame(
			$expected,
			AuthenticationService::buildSimpleUrl(
				$requestMock,
				$path,
				$params
			)
		);
	}
}
