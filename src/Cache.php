<?php namespace Model\Cache;

use Composer\InstalledVersions;
use Model\Config\Config;
use Symfony\Component\Cache\Adapter\AdapterInterface;
use Symfony\Component\Cache\Adapter\FilesystemAdapter;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

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
		$config = Config::get('cache');

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
					self::$adapters[$name] = function_exists('symlink') ? new FilesystemTagAwareAdapter($config['namespace'] ?? '', 0, $config['directory'] ?? null) : new FilesystemAdapter($config['namespace'] ?? '', 0, $config['directory'] ?? null);
					break;

				default:
					throw new \Exception('Unrecognized cache adapter');
			}
		}

		return self::$adapters[$name];
	}

	/**
	 * @param AdapterInterface $adapter
	 * @return bool
	 */
	public static function isTagAware(AdapterInterface $adapter): bool
	{
		return $adapter instanceof TagAwareAdapterInterface;
	}

	/**
	 * @param string|null $adapter
	 * @return void
	 */
	public static function clear(?string $adapter = null): void
	{
		$cache = self::getCacheAdapter($adapter);
		$cache->clear();
	}
}
