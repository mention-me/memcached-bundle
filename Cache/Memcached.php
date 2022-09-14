<?php
/**
 * @author    Aaron Scherer <aequasi@gmail.com>
 * @date      2013
 * @license   http://www.apache.org/licenses/LICENSE-2.0.html Apache License, Version 2.0
 */

namespace MentionMe\Bundle\MemcachedBundle\Cache;

use Closure;

/**
 * Class to encapsulate PHP Memcached object
 *
 * @method add(string $key, mixed $value, int $expiration = 0, int $udf_flags = 0): bool
 * @method delete(string $key, int $time = 0): bool
 * @method deleteMulti(array $keys, int $time = 0): array
 * @method increment(string $key, int $offset = 1, int $initial_value = 0, int $expiry = 0): mixed
 * @method decrement(string $key, int $offset = 1, int $initial_value = 0, int $expiry = 0): mixed
 * @method get(string $key): mixed
 * @method getMulti(array $keys, int $flags = 0): mixed
 * @method set(string $key, mixed $value, int $expiration = 0, int $udf_flags = 0): bool
 * @method setMulti(array $items, int $expiration = 0, int $udf_flags = 0): bool
 * @method getServerList(): array
 * @method cas(float $cas_token, string $key, $value, int $expiration = null)
 * @method getAllKeys(): array|false
 * @method flush(int $delay = 0): bool
 *
 */
class Memcached
{
	const NAMESPACE_CACHEKEY = 'NamespaceCacheKey[%s]';
	/**
	 * 60 Second Cache
	 */
	const SIXTY_SECOND = 60;
	/**
	 * 30 Minute Cache
	 */
	const THIRTY_MINUTE = 1800;
	/**
	 * 1 Hour Cache
	 */
	const ONE_HOUR = 3600;
	/**
	 * 6 Hour Cache
	 */
	const SIX_HOUR = 21600;
	/**
	 * Infinite Cache
	 */
	const NO_EXPIRE = 0;
	/**
	 * No Cache
	 */
	const NO_CACHE = -1;
	/**
	 * @var string The namespace to prefix all cache ids with
	 */
	protected $namespace = '';
	/**
	 * @var string The namespace version
	 */
	protected $namespaceVersion;

	protected bool $enabled;

	protected bool $initialize;

	protected \Memcached $memcached;

	protected bool $persistent = false;

	/**
	 * @var string|null
	 */
	protected ?string $prefix = null;

	/**
	 * @var bool
	 */
	protected bool $debug;

	/**
	 * Constructor instantiates and stores Memcached object
	 *
	 * @param bool $enabled Are we caching?
	 * @param bool $debug
	 * @param string|null $persistentId Are we persisting?
	 */
	public function __construct(bool $enabled, bool $debug = false, string $persistentId = null)
	{
		$this->enabled = $enabled;
		$this->debug = $debug;
		if ($persistentId) {
			$this->memcached = new \Memcached($persistentId);
			$this->initialize = count($this->getServerList()) === 0;
			$this->persistent = true;
		} else {
			$this->memcached = new \Memcached();
			$this->initialize = true;
		}
	}

	/**
	 * Adds servers to the pool. If persistent, check count of current server list
	 *
	 * @param array $serverList List of servers
	 *
	 * @return bool
	 */
	public function addServers(array $serverList)
	{
		if ($this->persistent && count($this->getServerList()) > 0) {
			return false;
		}

		return $this->processRequest('addServers', [$serverList]);
	}

	/**
	 * @return mixed
	 */
	public function cache($key, $payload, int $time = self::NO_EXPIRE)
	{
		if ($this->isEnabled() && $time !== self::NO_CACHE) {
			$result = $this->get($key);
			if ($result !== false) {
				return $result;
			}
			$result = $this->getDataFromPayload($payload);
			$this->set($key, $result, $time);
		} else {
			$result = $this->getDataFromPayload($payload);
		}

		return $result;
	}

	public function setEnabled($enabled): Memcached
	{
		$this->enabled = $enabled;

		return $this;
	}

	public function isEnabled(): bool
	{
		return $this->enabled;
	}

	public function hasError(): bool
	{
		return $this->memcached->getResultCode() !== \Memcached::RES_SUCCESS;
	}

	public function getError(): string
	{
		return $this->memcached->getResultMessage();
	}

	/**
	 * @return mixed
	 */
	public function __call($name, $arguments)
	{
		return $this->processRequest($name, $arguments);
	}

    protected function getAllowedTypes(): array
    {
        return [
            'add',
            'delete',
            'deleteByKey',
            'deleteMulti',
            'deleteMultiByKey',
            'increment',
            'prepend',
            'prependByKey',
            'replace',
            'replaceByKey',
            'touch',
            'touchByKey',
            'addByKey',
            'append',
            'appendByKey',
            'decrement',
            'get',
            'getByKey',
            'getDelayed',
            'getDelayedByKey',
            'getMulti',
            'getMultiByKey',
            'set',
            'setByKey',
            'setMulti',
            'setMultiByKey',
        ];
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

		return call_user_func_array(
			[
				$this->memcached,
				$name,
			],
			$arguments
		);
	}

	/**
	 * @param Closure|callable|mixed $payload
	 *
	 * @return mixed
	 */
	protected function getDataFromPayload($payload)
	{
		/** @var $payload Closure|callable|mixed */
		if (is_callable($payload)) {
			if (is_object($payload) && get_class($payload) === 'Closure') {
				return $payload();
			}

			return call_user_func($payload);
		}

		return $payload;
	}

	public function setPrefix(string $prefix): Memcached
	{
		$this->prefix = $prefix;

		return $this;
	}

	public function getPrefix(): string
	{
		return $this->prefix;
	}

	public function hasPrefix(): bool
	{
		return !empty($this->prefix);
	}

	/**
	 * Set the namespace to prefix all cache ids with.
	 */
	public function setNamespace(string $namespace): void
	{
		$this->namespace = $namespace;
	}

	/**
	 * Retrieve the namespace that prefixes all cache ids.
	 */
	public function getNamespace(): string
	{
		return $this->namespace;
	}

	/**
	 * Prefix the passed id with the configured namespace value
	 *
	 * @param string|array $id The id(s) to namespace
	 *
	 * @return string|array $id The namespaced id(s)
	 */
	protected function getNamespacedId($id)
	{
		$namespaceVersion = $this->getNamespaceVersion();

		if (is_array($id)) {
			/*
			 * If the id is an array, then we need to adjust all keys consistently. This
			 * will recursively adjust all the keys to use the configured namespace values
			 */
			return array_map(
				function ($key) {
					return $this->getNamespacedId($key);
				},
				$id
			);
		}

		return sprintf('%s[%s][%s]', $this->namespace, $id, $namespaceVersion);
	}

	protected function getNamespaceCacheKey(): string
	{
		return sprintf(self::NAMESPACE_CACHEKEY, $this->namespace);
	}

	protected function getNamespaceVersion(): string
	{
		if (null !== $this->namespaceVersion) {
			return $this->namespaceVersion;
		}

		$namespaceCacheKey = $this->getNamespaceCacheKey();
		$namespaceVersion = $this->memcached->get($namespaceCacheKey);

		if (false === $namespaceVersion) {
			$namespaceVersion = 1;

			$this->memcached->set($namespaceCacheKey, $namespaceVersion);
		}

		$this->namespaceVersion = $namespaceVersion;

		return $this->namespaceVersion;
	}

}
