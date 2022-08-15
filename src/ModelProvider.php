<?php namespace Model\Cache;

use Model\Core\ModelProviderInterface;

class ModelProvider implements ModelProviderInterface
{
	public static function realign(): void
	{
		Cache::invalidate();
	}

	public static function getDependencies(): array
	{
		return [];
	}
}
