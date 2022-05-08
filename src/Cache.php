<?php namespace Model\Cache;

use Composer\InstalledVersions;
use Model\Config\Config;
use Symfony\Component\Cache\Adapter\FilesystemTagAwareAdapter;
use Symfony\Component\Cache\Adapter\RedisTagAwareAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapterInterface;

class Cache
{
	private static array $adapters = [];

	/**
	 * @param string|null $name
	 * @return TagAwareAdapterInterface
	 * @throws \Exception
	 */
	public static function getCacheAdapter(?string $name = null): TagAwareAdapterInterface
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
	 * Register the items that you want invalidate in the invalidation procedure (by tag or by key)
	 *
	 * @param string $type
	 * @param array $keys
	 * @return void
	 */
	public static function registerInvalidation(string $type, array $keys): void
	{
		$cache = self::getCacheAdapter();
		$item = $cache->getItem('model.cache.invalidations');
		$invalidations = $item->isHit() ? $item->get() : [];

		$invalidationKey = $type . '-' . json_encode($keys);
		$invalidations[$invalidationKey] = [
			'type' => $type,
			'keys' => $keys,
		];

		$item->set($invalidations);
		$cache->save($item);
	}

	/**
	 * Invalidate cache as instructed
	 *
	 * @return void
	 */
	public static function invalidate(): void
	{
		$cache = self::getCacheAdapter();
		$item = $cache->getItem('model.cache.invalidations');
		$invalidations = $item->isHit() ? $item->get() : [];

		foreach ($invalidations as $invalidation) {
			switch ($invalidation['type']) {
				case 'tag':
					$cache->invalidateTags($invalidation['keys']);
					break;
				case 'keys':
					$cache->deleteItems($invalidation['keys']);
					break;
			}
		}

		$cache->deleteItem('model.cache.invalidations');
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
