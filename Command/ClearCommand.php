<?php
/**
 * @author    Aaron Scherer <aequasi@gmail.com>
 * @date      2013
 * @license   http://www.apache.org/licenses/LICENSE-2.0.html Apache License, Version 2.0
 */

namespace Aequasi\Bundle\MemcachedBundle\Command;

use Aequasi\Bundle\MemcachedBundle\Cache\Memcached;
use Exception;
use Psr\Container\ContainerInterface;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\ProgressBar;
use Symfony\Component\Console\Helper\QuestionHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\ConfirmationQuestion;
use Symfony\Component\DependencyInjection\Exception\ServiceNotFoundException;

/**
 * ClearCommand
 *
 * Flushed the given memcached cluster
 */
class ClearCommand extends Command
{
	protected static $defaultName = "memcached:clear";

	private ContainerInterface $container;

	protected function configure()
	{
		$this
			->setDescription('Invalidate all Memcached items for this app (requires use of prefix)')
			->addArgument('cluster', InputArgument::REQUIRED, 'What cluster do you want to use?')
			->addOption('clearAll', null, InputOption::VALUE_NONE, 'Do you want to clear the entire cluster?');
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
	 * @throws Exception
	 */
	protected function execute(InputInterface $input, OutputInterface $output)
	{
		/** @var QuestionHelper $dialog */
		$dialog = $this->getHelperSet()->get('question');
		$cluster = $input->getArgument('cluster');

		try {
			/** @var Memcached $memcached */
			$memcached = $this->container->get('memcached.' . $cluster);
			if ($input->getOption('clearAll')) {
				$output->writeln("Attempting to flush the memcached cluster");
				$memcached->flush();
			} else {
				$output->writeln("Attempting to flush the memcached cluster that belong to this app");
				if (!$memcached->hasPrefix()) {
					throw new Exception(
						"Cannot clear a cluster that isn't using a prefix. Did you mean to --clearAll?"
					);
				}

				$question = new ConfirmationQuestion(
					'This function cannot be guaranteed to clear all of the keys. Are you sure you want to run this?',
					false
				);

				if ($dialog->ask($input, $output, $question) === false) {
					return;
				}

				$progress = new ProgressBar($output, 50);

				for ($i = 0; $i <= 50; $i++) {
					$keys = $memcached->getAllKeys();
					foreach ($keys as $index => $key) {
						if (strpos($index, $memcached->getPrefix() . '_') !== 0) {
							unset($keys[$index]);
						}
					}
					$memcached->deleteMulti($keys);
					$progress->advance();
				}
			}
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
