<?php
/**
 * @author    Aaron Scherer <aequasi@gmail.com>
 * @date      2013
 * @license   http://www.apache.org/licenses/LICENSE-2.0.html Apache License, Version 2.0
 */

namespace Aequasi\Bundle\MemcachedBundle;

use Aequasi\Bundle\MemcachedBundle\DependencyInjection\Compiler\EnableSessionSupport;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\HttpKernel\Bundle\Bundle;

/**
 * MemcachedBundle Class
 */
class AequasiMemcachedBundle extends Bundle
{

	/**
	 * {@inheritDoc}
	 */
	public function build(ContainerBuilder $container)
	{
		parent::build($container);

		$container->addCompilerPass(new EnableSessionSupport());
	}
}
