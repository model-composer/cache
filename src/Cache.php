<?php namespace Model\Cache;

use Composer\InstalledVersions;
use Model\Config\Config;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;

class Cache
{
	private static array $adapters = [];

	/**
	 * @param string|null $name
	 * @return AdapterInterface
	 * @throws \Exception
	 */
	public static function getCacheAdapter(?string $name = null): AdapterInterface
	{
		$config = self::getConfig();

		if ($name === null)
			$name = $config['default_adapter'];

		if (!isset(self::$adapters[$name])) {
			switch ($name) {
				case 'redis':
					if (!InstalledVersions::isInstalled('model/redis'))
						throw new \Exception('Please install model/redis');

					$redis = \Model\Redis\Redis::getClient();
					if (!$redis)
						throw new \Exception('Invalid Redis configuration');

					$namespace = $config['namespace'] ?? \Model\Redis\Redis::getNamespace() ?? '';
					self::$adapters[$name] = new RedisTagAwareAdapter($redis, $namespace);
					break;

				case 'file':
					self::$adapters[$name] = new FilesystemTagAwareAdapter($config['namespace'] ?? '');
					break;

				default:
					throw new \Exception('Unrecognized cache adapter');
			}
		}

		return self::$adapters[$name];
	}

	/**
	 * Config retriever
	 *
	 * @return array
	 * @throws \Exception
	 */
	private static function getConfig(): array
	{
		return Config::get('cache', function () {
			return [
				'default_adapter' => 'file',
				'namespace' => null,
			];
		});
	}
}
