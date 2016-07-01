<?php

namespace OC\Core\Command\Background\Queue;

use OC\Console\CommandLogger;
use OCP\BackgroundJob\IJob;
use OCP\ILogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableHelper;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Delete extends Command {

	/** @var \OCP\BackgroundJob\IJobList */
	private $jobList;

	public function __construct() {
		$this->jobList = \OC::$server->getJobList();
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName("background:queue:delete")
			->setDescription("Delete a job from the queue")
			->addArgument('id', InputArgument::REQUIRED, 'id of the job to be deleted');
	}

	/**
	* @param InputInterface $input
	* @param OutputInterface $output
	*/
	protected function execute(InputInterface $input, OutputInterface $output) {
		$id = $input->getArgument('id');

		$job = $this->jobList->getById($id);
		if (is_null($job)) {
			$output->writeln("Job with id <$id> is not known.");
			return;
		}

		$this->jobList->removeById($id);
		$output->writeln("Job has been deleted.");
	}
}
