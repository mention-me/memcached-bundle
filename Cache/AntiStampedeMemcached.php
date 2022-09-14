<?php
/**
 * @author    Aaron Scherer <aaron@undergroundelephant.com>
 * @date      2013
 * @copyright Underground Elephant
 */

namespace MentionMe\Bundle\MemcachedBundle\Cache;

/**
 * AntiStampedeMemcached Class
 */
class AntiStampedeMemcached extends LoggingMemcached
{
	const MAX_TTL = 2592000;

	/**
	 * Function to get value by key using Anti-Stampede algorithm.
	 * NB: On every invalidation only one call will return false,
	 * other calls will just return the previous value. Whenever false
	 * is returned it is the programmers responsibility to call setAdp()
	 * to set a new value.
	 *
	 * @param string $key Key of the value you are trying to retrieve
	 *
	 * @return string|bool Value read from cache or false if cache is stale
	 */
	public function getAdp($key)
	{
		$cas = 0;
		$value = $this->get($key, null, $cas);
		if ($value === false) {
			return false;
		}
		list($exp, $ttl, $val) = explode('|', $value, 3);
		$val = json_decode($val);

		$time = time();
		if ($time > $exp) {
			$value = implode('|',
				[
					$time + $ttl,
					$ttl,
					json_encode($val),
				]
			);
			$result = $this->cas($cas, $key, $value, 0);

			if ($result) {
				return false;
			}
		}

		return $val;
	}

	/**
	 * Function to set value by key using Anti-Stampede algorithm.
	 * NB: Anti Dog Pile algorithm will use JSON as a serializer.
	 *
	 * @param string $key Key of the value you are storing
	 * @param string $value Value you want to store
	 * @param int $ttl Seconds before the cache item is returning false (once)
	 *
	 * @return boolean True on success, false on failure
	 */
	public function setAdp(string $key, string $value, int $ttl = 0): bool
	{
		if ($ttl === 0) {
			$ttl = self::MAX_TTL;
		}
		$time = time();
		$value = implode('|',
			[
				$time + $ttl,
				$ttl,
				json_encode($value),
			]
		);
		return $this->set($key, $value, 0);
	}

}
