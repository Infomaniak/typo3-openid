<?php

namespace Infomaniak\Tests\Unit\Classes\Controller;

use Infomaniak\Auth\Middleware\BackendCallbackMiddleware;
use Infomaniak\Auth\Service\AuthenticationService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UriInterface;
use Psr\Http\Server\RequestHandlerInterface;
use TYPO3\CMS\Core\Http\HtmlResponse;

class BackendCallbackMiddlewareTest extends TestCase
{
	public function testProcessRedirectsOnValidCallback()
	{
		$requestMock = $this->createMock(ServerRequestInterface::class);
		$uriMock = $this->createMock(UriInterface::class);
		$handlerMock = $this->createMock(RequestHandlerInterface::class);

		$requestMock->method('getQueryParams')->willReturn([
			'code' => 'test_code',
			'state' => 'test_state',
		]);
		$uriMock->method('getPath')->willReturn(BackendCallbackMiddleware::BACKEND_CALLBACK_PATH);
		$requestMock->method('getUri')->willReturn($uriMock);

		$loginUrl = '/typo3/login?code=test_code&state=test_state&loginProvider=' . AuthenticationService::AUTH_INFOMANIAK_CODE;

		$middleware = new BackendCallbackMiddleware();

		$response = $middleware->process($requestMock, $handlerMock);

		$this->assertInstanceOf(HtmlResponse::class, $response);
		$this->assertStringContainsString('Redirecting...', (string)$response->getBody());
		$this->assertStringContainsString($loginUrl, (string)$response->getBody());
	}

	public static function getQueryParamsProvider(): array
	{
		return [
			'valid path, code, state, invalid error' => [
				'/path/'.BackendCallbackMiddleware::BACKEND_CALLBACK_PATH,
				[
					'error' => true,
					'code' => '1234567890abcdef',
					'state' => 'random_state_value',
				]
			],
			'valid path, code, invalid error, state' => [
				'/path/'.BackendCallbackMiddleware::BACKEND_CALLBACK_PATH,
				[
					'error' => true,
					'code' => '1234567890abcdef',
				]
			],
			'valid path, invalid error, code, state' => [
				'/path/'.BackendCallbackMiddleware::BACKEND_CALLBACK_PATH,
				[
					'error' => true,
				]
			],
			'valid code, state, invalid error, path' => [
				'/path/',
				[
					'error' => true,
					'code' => '1234567890abcdef',
					'state' => 'random_state_value',
				]
			],
			'valid code, invalid error, path, state' => [
				'/path/',
				[
					'error' => true,
					'code' => '1234567890abcdef',
				]
			],
			'valid state, invalid error, path, code' => [
				'/path/',
				[
					'error' => true,
					'state' => 'random_state_value',
				]
			],
			'nothing valid' => [
				'/path/',
				[
					'error' => true,
				]
			],
			'valid error, path, code, invalid state' => [
				'/path/'.BackendCallbackMiddleware::BACKEND_CALLBACK_PATH,
				[
					'code' => '1234567890abcdef',
				]
			],
			'valid error, path, state, invalid code' => [
				'/path/'.BackendCallbackMiddleware::BACKEND_CALLBACK_PATH,
				[
					'state' => 'random_state_value',
				]
			],
			'valid error, code, state invalid path' => [
				'/path/',
				[
					'code' => '1234567890abcdef',
					'state' => 'random_state_value',
				]
			],
			'valid error, code, invalid state, path' => [
				'/path/',
				[
					'code' => '1234567890abcdef',
				]
			],
			'valid error, state, invalid code, path' => [
				'/path/',
				[
					'state' => 'random_state_value',
				]
			],
		];
	}

	#[DataProvider('getQueryParamsProvider')]
	public function testProcessDelegatesToHandlerOnNonCallback(string $path, array $queryParams)
	{
		$requestMock = $this->createMock(ServerRequestInterface::class);
		$uriMock = $this->createMock(UriInterface::class);
		$handlerMock = $this->createMock(RequestHandlerInterface::class);

		$requestMock->method('getQueryParams')->willReturn($queryParams);
		$uriMock->method('getPath')->willReturn($path);
		$requestMock->method('getUri')->willReturn($uriMock);

		$expectedResponse = $this->createMock(ResponseInterface::class);
		$handlerMock->expects($this->once())
			->method('handle')
			->with($requestMock)
			->willReturn($expectedResponse);

		$middleware = new BackendCallbackMiddleware();

		$response = $middleware->process($requestMock, $handlerMock);

		$this->assertSame($expectedResponse, $response);
	}
}
