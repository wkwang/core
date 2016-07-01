<?php

namespace OC\Core\Command\Background\Queue;

use OC\Console\CommandLogger;
use OCP\BackgroundJob\IJob;
use OCP\ILogger;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Helper\Table;
use Symfony\Component\Console\Helper\TableHelper;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

class Status extends Command {

	/** @var \OCP\BackgroundJob\IJobList */
	private $jobList;

	public function __construct() {
		$this->jobList = \OC::$server->getJobList();
		parent::__construct();
	}

	protected function configure() {
		$this
			->setName("background:queue:status")
			->setDescription("List queue status");
	}

	/**
	* @param InputInterface $input
	* @param OutputInterface $output
	*/
	protected function execute(InputInterface $input, OutputInterface $output) {
		/** @var TableHelper $t */
		$t = $this->getHelper('table');
		$t->setHeaders(['Id', 'Job', 'Last run', 'Arguments']);
		$this->jobList->listJobs(function (IJob $job) use ($t) {
			$t->addRow([$job->getId(), get_class($job), date('c', $job->getLastRun()), $job->getArgument()]);
		});
		$t->render($output);
	}
}
