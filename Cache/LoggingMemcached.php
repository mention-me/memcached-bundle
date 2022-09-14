<?php
/**
 * @author    Aaron Scherer <aequasi@gmail.com>
 * @date      2013
 * @license   http://www.apache.org/licenses/LICENSE-2.0.html Apache License, Version 2.0
 */

namespace MentionMe\Bundle\MemcachedBundle\Cache;

/**
 * Class to encapsulate PHP Memcached object for unit tests and to add logging in logging mode
 */
class LoggingMemcached extends Memcached implements LoggingMemcachedInterface
{
	protected array $calls;

	protected bool $logging;

	/**
	 * Constructor instantiates and stores Memcached object
	 *
	 * @param bool $enabled Are we caching?
	 * @param bool $debug Are we logging?
	 * @param string|null $persistentId Are we persisting?
	 */
	public function __construct(bool $enabled, bool $debug = false, string $persistentId = null)
	{
		$this->logging = $debug;
		parent::__construct($enabled, $debug, $persistentId);
	}

	/**
	 * Get the logged calls for this Memcached object
	 */
	public function getLoggedCalls(): array
	{
		return $this->calls;
	}

	/**
	 * @param $name
	 * @param $arguments
	 *
	 * @return mixed
	 */
	function __call($name, $arguments)
	{
		return $this->processRequest($name, $arguments);
	}

	/**
	 * @return mixed
	 */
	protected function processRequest(string $name, array $arguments)
	{
		$useId = $this->getAllowedTypes();

		if (in_array($name, $useId, true)) {
			$arguments[0] = $this->getNamespacedId($arguments[0]);
		}

		if ($this->logging) {
			$start = microtime(true);
			$result = call_user_func_array(
				[
					$this->memcached,
					$name,
				],
				$arguments
			);
			$time = microtime(true) - $start;
			$call = (object)compact('start', 'time', 'name', 'arguments', 'result');

			// Removing possible bad values from the data collector
			if (in_array($name,
				[
					'get',
					'getByKey',
					'getDelayed',
					'getDelayedByKey',
					'getMulti',
					'getMultiByKey',
				]
			)) {
				$call->result = $result !== false;
			}
			if (in_array($name,
				[
					'set',
					',setByKey',
					'setMulti',
					'setMultiByKey',
				]
			)) {
				$call->arguments = [$call->arguments[0]];
			}

			$this->calls[] = $call;
		} else {
			$result = call_user_func_array(
				[
					$this->memcached,
					$name,
				],
				$arguments
			);
		}

		return $result;
	}
}
