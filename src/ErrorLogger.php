<?php namespace Model\Cache;

use Psr\Log\AbstractLogger;

/**
 * Symfony's adapters never throw: every failure (a non-writable cache directory or file, a dead Redis connection, ...) is just
 * handed over to the logger, and with no logger attached it becomes a silenced E_USER_WARNING - so the cache looks like it's
 * working, while nothing is actually being stored. This logger turns write failures into actual exceptions; read failures are
 * left as warnings, as the cache can legitimately recover from those (a corrupted or half-written file simply counts as a miss).
 */
class ErrorLogger extends AbstractLogger
{
	private const FATAL_PREFIXES = [
		'Failed to save',
		'Failed to clear',
		'Failed to delete',
	];

	/**
	 * @param mixed $level
	 * @param \Stringable|string $message
	 * @param array $context
	 * @return void
	 * @throws \Exception
	 */
	public function log($level, \Stringable|string $message, array $context = []): void
	{
		$replace = [];
		foreach ($context as $k => $v) {
			if (is_scalar($v))
				$replace['{' . $k . '}'] = $v;
		}
		$message = strtr((string)$message, $replace);

		if ($this->isFatal($message) and !$this->inDestructor())
			throw new \Exception($message, 0, ($context['exception'] ?? null) instanceof \Throwable ? $context['exception'] : null);

		trigger_error($message, E_USER_WARNING);
	}

	/**
	 * @param string $message
	 * @return bool
	 */
	private function isFatal(string $message): bool
	{
		foreach (self::FATAL_PREFIXES as $prefix) {
			if (str_starts_with($message, $prefix))
				return true;
		}
		return false;
	}

	/**
	 * Adapters commit their deferred items from their destructor as well; throwing from there would only result in an unhelpful fatal error, so in that case we fall back to a warning
	 *
	 * @return bool
	 */
	private function inDestructor(): bool
	{
		foreach (debug_backtrace(DEBUG_BACKTRACE_IGNORE_ARGS) as $frame) {
			if (($frame['function'] ?? null) === '__destruct')
				return true;
		}
		return false;
	}
}
