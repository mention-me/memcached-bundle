<?php

namespace MentionMe\Bundle\MemcachedBundle\DataCollector;

use MentionMe\Bundle\MemcachedBundle\Cache\LoggingMemcachedInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\DataCollector\DataCollector;

/**
 * MemcachedDataCollector
 *
 * Based on Lsw\MemcacheBundle
 */
class MemcachedDataCollector extends DataCollector
{
	private array $clusters = [];

	private array $options = [];

	/**
	 * @var array
	 */
	protected $data = [];

	/**
	 * Add a Memcached object to the collector
	 */
	public function addCluster(string $name, array $options, LoggingMemcachedInterface $memcached): void
	{
		$this->clusters[$name] = $memcached;
		$this->options[$name] = $options;
	}

	/**
	 * {@inheritdoc}
	 */
	public function collect(Request $request, Response $response)
	{
		$empty = [
			'calls' => [],
			'config' => [],
			'options' => [],
			'statistics' => [],
		];
		$this->data = [
			'clusters' => $empty,
			'total' => $empty,
		];
		foreach ($this->clusters as $name => $memcached) {
			/** @var LoggingMemcachedInterface $memcached */
			$calls = $memcached->getLoggedCalls();
			$this->data['clusters']['calls'][$name] = $calls;
			$this->data['clusters']['options'][$name] = $this->options[$name];
		}
		$this->data['clusters']['statistics'] = $this->calculateStatistics();
		$this->data['total']['statistics'] = $this->calculateTotalStatistics(
			$this->data['clusters']['statistics']
		);
	}

	public function getName(): string
	{
		return 'memcached';
	}

	/**
	 * Method returns amount of logged Memcached reads: "get" calls
	 *
	 * @return number
	 */
	public function getStatistics()
	{
		return $this->data['clusters']['statistics'];
	}

	/**
	 * Method returns the statistic totals
	 *
	 * @return number
	 */
	public function getTotals()
	{
		return $this->data['total']['statistics'];
	}

	/**
	 * Method returns all logged Memcached call objects
	 *
	 * @return mixed
	 */
	public function getCalls()
	{
		return $this->data['clusters']['calls'];
	}

	/**
	 * Method returns all Memcached options
	 *
	 * @return mixed
	 */
	public function getOptions()
	{
		return $this->data['clusters']['options'];
	}

	private function calculateStatistics(): array
	{
		$statistics = [];
		foreach ($this->data['clusters']['calls'] as $name => $calls) {
			$statistics[$name] = [
				'calls' => 0,
				'time' => 0,
				'reads' => 0,
				'hits' => 0,
				'misses' => 0,
				'writes' => 0,
			];
			foreach ($calls as $call) {
				$statistics[$name]['calls'] += 1;
				$statistics[$name]['time'] += $call->time;
				if ($call->name === 'get') {
					$statistics[$name]['reads'] += 1;
					if ($call->result !== false) {
						$statistics[$name]['hits'] += 1;
					} else {
						$statistics[$name]['misses'] += 1;
					}
				} elseif ($call->name === 'get') {
					$statistics[$name]['writes'] += 1;
				}
			}
			if ($statistics[$name]['reads']) {
				$statistics[$name]['ratio'] = 100 * $statistics[$name]['hits'] / $statistics[$name]['reads'] . '%';
			} else {
				$statistics[$name]['ratio'] = 'N/A';
			}
		}

		return $statistics;
	}

	private function calculateTotalStatistics(array $statistics): array
	{
		$totals = [
			'calls' => 0,
			'time' => 0,
			'reads' => 0,
			'hits' => 0,
			'misses' => 0,
			'writes' => 0,
		];
		foreach ($statistics as $name => $values) {
			foreach ($totals as $key => $value) {
				$totals[$key] += $statistics[$name][$key];
			}
		}
		if ($totals['reads']) {
			$totals['ratio'] = 100 * $totals['hits'] / $totals['reads'] . '%';
		} else {
			$totals['ratio'] = 'N/A';
		}

		return $totals;
	}

	/**
	 * Clear $this->data so the instance can be reused
	 *
	 * Required for Symfony 4, but is introduced in Symfony 3
	 */
	public function reset()
	{
		$this->data = [];
	}
}
