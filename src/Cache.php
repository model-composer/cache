<?php namespace Model\Cache;

use Composer\InstalledVersions;
use Symfony\Component\Cache\Adapter\AbstractAdapter;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;

class Cache
{
	private static array $adapters = [];

	/**
	 * @param string $name
	 * @return AbstractAdapter
	 * @throws \Exception
	 */
	public static function getCacheAdapter(string $name): AbstractAdapter
	{
		if (!isset(self::$adapters[$name])) {
			switch ($name) {
				case 'redis':
					if (!InstalledVersions::isInstalled('model/redis'))
						throw new \Exception('Please install model/redis');

					$redis = \Model\Redis\Redis::getClient();
					if (!$redis)
						throw new \Exception('Invalid Redis configuration');

					self::$adapters[$name] = new RedisTagAwareAdapter($redis);
					break;

				case 'file':
					self::$adapters[$name] = new FilesystemTagAwareAdapter();
					break;

				default:
					throw new \Exception('Unrecognized cache adapter');
			}
		}

		return self::$adapters[$name];
	}
}
