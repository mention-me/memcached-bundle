<?php
/**
 * @author    Aaron Scherer <aequasi@gmail.com>
 * @date      2013
 * @license   http://www.apache.org/licenses/LICENSE-2.0.html Apache License, Version 2.0
 */

namespace MentionMe\Bundle\MemcachedBundle\Command;

use MentionMe\Bundle\MemcachedBundle\Cache\Memcached;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\ContainerInterface;
use Psr\Container\NotFoundExceptionInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * GetCommand
 *
 * Grabs the given key out of cache
 */
class SetCommand extends Command
{
	protected static $defaultName = 'memcached:set';

	protected ContainerInterface $container;

	public function __construct(ContainerInterface $container)
	{
		parent::__construct();

		$this->container = $container;
	}

	protected function configure()
	{
		$this
			->setDescription("Set a key's value to memcached")
			->addArgument('cluster', InputArgument::REQUIRED, 'What cluster do you want to use')
			->addArgument('key', InputArgument::REQUIRED, 'What key do you want to set')
			->addArgument('value', InputArgument::REQUIRED, 'What do you want the value to be')
			->addArgument(
				'lifeTime',
				InputArgument::OPTIONAL,
				'How long do you want it to be cached? ( 0 for infinite, Default: 60 seconds  )',
				60
			);
	}

	/**
	 * @param InputInterface $input
	 * @param OutputInterface $output
	 *
	 * @return void
	 * @throws ContainerExceptionInterface
	 * @throws NotFoundExceptionInterface
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		$cluster = $input->getArgument('cluster');
		$key = $input->getArgument('key');
		$value = $input->getArgument('value');
		$lifeTime = $input->getArgument('lifeTime');

		try {
			/** @var Memcached $memcached */
			$memcached = $this->container->get('memcached.' . $cluster);
			$memcached->set($key, $value, $lifeTime);
			if ($memcached->hasError()) {
				$output->writeln(sprintf('<error>%s</error>', $memcached->getError()));
			} else {
				$output->writeln(sprintf('<info>Key: %s</info>', $key));
				$output->writeln(sprintf('<info>Value: %s</info>', $value));
			}
		} catch (ServiceNotFoundException $e) {
			$output->writeln("<error>cluster '{$cluster}' is not found</error>");
		}
		$output->writeln("\n");
	}
}
