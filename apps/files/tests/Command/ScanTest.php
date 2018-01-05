<?php
/**
 * @author Sujith Haridasan <sharidasan@owncloud.com>
 *
 * @copyright Copyright (c) 2018, ownCloud GmbH
 * @license AGPL-3.0
 *
 * This code is free software: you can redistribute it and/or modify
 * it under the terms of the GNU Affero General Public License, version 3,
 * as published by the Free Software Foundation.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
 * GNU Affero General Public License for more details.
 *
 * You should have received a copy of the GNU Affero General Public License, version 3,
 * along with this program.  If not, see <http://www.gnu.org/licenses/>
 *
 */

namespace OCA\Files\Tests\Command;

use OCA\Files\Command\Scan;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\Lock\ILockingProvider;
use OCP\Files\IMimeTypeLoader;
use OCP\IConfig;
use Test\TestCase;

/**
 * Class ScanTest
 *
 * @group DB
 * @package OCA\Files\Tests\Command
 */
class ScanTest extends TestCase {
	/** @var  IUserManager | \PHPUnit_Framework_MockObject_MockObject */
	private $userManager;
	/** @var  IGroupManager | \PHPUnit_Framework_MockObject_MockObject */
	private $groupManager;
	/** @var  ILockingProvider | \PHPUnit_Framework_MockObject_MockObject */
	private $lockingProvider;
	/** @var  IMimeTypeLoader | \PHPUnit_Framework_MockObject_MockObject */
	private $mimeTypeLoader;
	/** @var  IConfig | \PHPUnit_Framework_MockObject_MockObject */
	private $iconfig;
	/** @var  Scan */
	private $command;
	/** @var  string */
	private $user1;

	protected function setUp() {
		parent::setUp();

		$this->userManager = $this->createMock(IUserManager::class);
		$this->groupManager = $this->createMock(IGroupManager::class);
		$this->lockingProvider = $this->createMock(ILockingProvider::class);
		$this->mimeTypeLoader = $this->createMock(IMimeTypeLoader::class);
		$this->iconfig = $this->createMock(IConfig::class);
		$this->user1 = $this->getUniqueID('user1_');

		$userManager = \OC::$server->getUserManager();
		$userManager->createUser($this->user1, 'pass');

		$this->command = new Scan($this->userManager, $this->groupManager, $this->lockingProvider, $this->mimeTypeLoader, $this->iconfig);
	}

	public function testGroupScan() {
		$group = \OC::$server->getGroupManager()->createGroup('foo');
		$user = \OC::$server->getUserManager()->get($this->user1);
		$group->addUser($user);

		$input = $this->getMockBuilder('Symfony\Component\Console\Input\InputInterface')
			->disableOriginalConstructor()
			->getMock();
		$output = $this->getMockBuilder('Symfony\Component\Console\Output\OutputInterface')
			->disableOriginalConstructor()
			->getMock();

		$input->method('getOption')
			->will($this->returnValue('foo'));

		$this->groupManager->method('findUsersInGroup')
			->willReturn($user);
		$this->loginAsUser($this->user1);

		global $outputText;
		$output->expects($this->at(1))
			->method('writeln')
			->willReturnCallback(function ($value){
				global $outputText;
				$outputText .= $value . "\n";
			});

		$this->invokePrivate($this->command, 'execute', [$input, $output]);
		$this->assertSame('Scanning group foo', trim($outputText, "\n"));
	}
}