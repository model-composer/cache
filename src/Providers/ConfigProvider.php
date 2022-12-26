<?php namespace Model\Cache\Providers;

use Model\Config\AbstractConfigProvider;

class ConfigProvider extends AbstractConfigProvider
{
	public static function migrations(): array
	{
		return [
			[
				'version' => '0.3.0',
				'migration' => function (array $config, string $env) {
					if ($config) // Already existing
						return $config;

					return [
						'default_adapter' => 'file',
						'namespace' => null,
					];
				},
			],
		];
	}
}
