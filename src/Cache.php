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
	 * Register the items that you want to invalidate in the invalidation procedure (by tag or by key)
	 *
	 * @param string $type
	 * @param array $keys
	 * @param string|null $adapter
	 * @return void
	 */
	public static function registerInvalidation(string $type, array $keys, ?string $adapter = null): void
	{
		$cache = self::getCacheAdapter();
		$item = $cache->getItem('model.cache.invalidations');
		$invalidations = $item->isHit() ? $item->get() : [];

		$invalidationKey = $type . '-' . json_encode($keys) . ($adapter ? '-' . $adapter : '');
		if (isset($invalidations[$invalidationKey]))
			return;

		$invalidations[$invalidationKey] = [
			'adapter' => $adapter,
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
			$adapter = !empty($invalidation['adapter']) ? self::getCacheAdapter($invalidation['adapter']) : $cache;
			switch ($invalidation['type']) {
				case 'tag':
					if (self::isTagAware($adapter))
						$adapter->invalidateTags($invalidation['keys']);
					break;
				case 'keys':
					$adapter->deleteItems($invalidation['keys']);
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
		return Config::get('cache', [
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
		]);
	}
}
