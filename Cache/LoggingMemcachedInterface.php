<?php
/**
 * @author    Aaron Scherer <aequasi@gmail.com>
 * @date      2013
 * @license   http://www.apache.org/licenses/LICENSE-2.0.html Apache License, Version 2.0
 */

namespace MentionMe\Bundle\MemcachedBundle\Cache;

/**
 * Interface to allow for DataCollector to retrieve logged calls
 */
interface LoggingMemcachedInterface
{
	public function getLoggedCalls(): array;
}
