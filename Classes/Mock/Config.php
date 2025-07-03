<?php

namespace Infomaniak\Mock;

class Config {
	protected array $config = [
		'infomaniak_auth' => [
			'beuser' => [
				'loginMode' => 'BE',
				'createIfNotExists' => true,
				'updateIfExists' => true,
			],
			'feuser' => [
				'loginMode' => 'FE',
				'createIfNotExists' => true,
				'updateIfExists' => true,
			],
		],
	];

	public function get(string $key, mixed $default = null): mixed
	{
		return $this->config[$key] ?? $default;
	}
}
