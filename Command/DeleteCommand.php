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
class DeleteCommand extends Command
{
	protected static $defaultName = 'memcached:delete';

	protected ContainerInterface $container;

	protected function configure()
	{
		$this
			->setDescription("Delete a key from memcached")
			->addArgument('cluster', InputArgument::REQUIRED, 'What cluster do you want to use')
			->addArgument('key', InputArgument::REQUIRED, 'What key do you want to delete');
	}

	public function __construct(ContainerInterface $container)
	{
		parent::__construct();

		$this->container = $container;
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
		$key = $input->getArgument('key');
		$cluster = $input->getArgument('cluster');

		try {
			/** @var Memcached $memcached */
			$memcached = $this->container->get('memcached.' . $cluster);
			$memcached->delete($key);
			if ($memcached->hasError()) {
				$output->writeln(sprintf('<error>%s</error>', $memcached->getError()));
			} else {
				$output->writeln('<info>OK</info>');
			}
		} catch (ServiceNotFoundException $e) {
			$output->writeln("<error>cluster '{$cluster}' is not found</error>");
		}
		$output->writeln("\n");
	}
}
