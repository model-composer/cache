<?php namespace Model\Cache\Providers;

use Model\Core\AbstractModelProvider;

class ModelProvider extends AbstractModelProvider
{
	public static function realign(): void
	{
		Cache::invalidate();
	}
}
