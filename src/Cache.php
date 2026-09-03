<?php namespace Model\Cache;

use Composer\InstalledVersions;
use Model\Config\Config;
use Psr\Log\LoggerAwareInterface;
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
			$namespace = 'modelcache-' . ($config['namespace'] ?? '');

			switch ($name) {
				case 'redis':
					if (!InstalledVersions::isInstalled('model/redis'))
						throw new \Exception('Please install model/redis');

					$redis = \Model\Redis\Redis::getClient();
					if (!$redis)
						throw new \Exception('Invalid Redis configuration');

					self::$adapters[$name] = new RedisTagAwareAdapter($redis, $namespace);
					break;

				case 'file':
					$directory = $config['directory'] ?? null;
					self::checkDirectory($directory, $namespace);

					self::$adapters[$name] = function_exists('symlink') ? new FilesystemTagAwareAdapter($namespace, 0, $directory) : new FilesystemAdapter($namespace, 0, $directory);
					break;

				default:
					throw new \Exception('Unrecognized cache adapter');
			}

			// Symfony's adapters silently swallow every failure, unless a logger is attached
			if (self::$adapters[$name] instanceof LoggerAwareInterface)
				self::$adapters[$name]->setLogger(new ErrorLogger);
		}

		return self::$adapters[$name];
	}

	/**
	 * Makes sure the directory the filesystem adapter is going to use actually exists and is writable, as the adapter itself would just silently fail
	 *
	 * @param string|null $directory
	 * @param string $namespace
	 * @return void
	 * @throws \Exception
	 */
	private static function checkDirectory(?string $directory, string $namespace): void
	{
		// Mirrors what Symfony's FilesystemCommonTrait::init does to build the actual path
		if (!isset($directory[0]))
			$directory = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'symfony-cache';
		else
			$directory = realpath($directory) ?: $directory;

		$directory .= DIRECTORY_SEPARATOR . (isset($namespace[0]) ? $namespace : '@');

		if (!is_dir($directory)) {
			// Look for the closest existing ancestor: it's the one that needs to be writable for the cache directory to be created
			$existing = $directory;
			while (!file_exists($existing)) {
				$parent = dirname($existing);
				if ($parent === $existing)
					break;
				$existing = $parent;
			}

			if (!is_dir($existing))
				throw new \Exception('Cache directory "' . $directory . '" cannot be created, as "' . $existing . '" is not a directory');
			if (!is_writable($existing))
				throw new \Exception('Cache directory "' . $directory . '" does not exist and cannot be created, as "' . $existing . '" is not writable');

			if (!@mkdir($directory, 0777, true) and !is_dir($directory))
				throw new \Exception('Cannot create cache directory "' . $directory . '"');
		}

		if (!is_writable($directory))
			throw new \Exception('Cache directory "' . $directory . '" is not writable');
	}

	/**
	 * @param AdapterInterface|null $adapter
	 * @return bool
	 */
	public static function isTagAware(?AdapterInterface $adapter = null): bool
	{
		if ($adapter === null)
			$adapter = self::getCacheAdapter();
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
