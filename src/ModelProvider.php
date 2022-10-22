<?php namespace Model\Cache;

use Model\Core\AbstractModelProvider;

class ModelProvider extends AbstractModelProvider
{
	public static function realign(): void
	{
		Cache::invalidate();
	}
}
