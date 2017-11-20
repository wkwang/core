<?php
/**
 * @author Vincent Petry <pvince81@owncloud.com>
 *
 * @copyright Copyright (c) 2017, ownCloud GmbH
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
use Test\TestCase;
use OCP\IUserManager;
use OCP\IConfig;
use OCP\Files\IMimeTypeLoader;
use OCP\Lock\ILockingProvider;
use Symfony\Component\Console\Tester\CommandTester;
use OCP\IUser;
use OCP\IDBConnection;

/**
 * Class ScanTest
 *
 * @group DB
 *
 * @package OCA\Files\Tests\Command
 */
class ScanTest extends TestCase {

	/**
	 * @var IDBConnection
	 */
	private $connection;

	/**
	 * @var CommandTester
	 */
	private $commandTester;

	/**
	 * @var IUserManager
	 */
	private $userManager;

	/**
	 * @var ILockingProvider | \PHPUnit_Framework_MockObject_MockObject
	 */
	private $lockingProvider;

	/**
	 * @var IMimeTypeLoader | \PHPUnit_Framework_MockObject_MockObject
	 */
	private $mimeTypeLoader;

	/**
	 * @var IConfig | \PHPUnit_Framework_MockObject_MockObject
	 */
	private $config;

	/**
	 * @var IUser
	 */
	private $user1;

	/**
	 * @var IUser
	 */
	private $user2;

	protected function setup() {
		parent::setUp();

		$this->connection = \OC::$server->getDatabaseConnection();
		$this->userManager = \OC::$server->getUserManager();
		$this->lockingProvider = $this->createMock(ILockingProvider::class);
		$this->mimeTypeLoader = $this->createMock(IMimeTypeLoader::class);
		$this->config = $this->createMock(IConfig::class);

		$this->user1 = $this->userManager->createUser('user1' . uniqid(), 'user1');
		$this->user2 = $this->userManager->createUser('user2' . uniqid(), 'user2');

		$command = new Scan(
			$this->userManager,
			$this->lockingProvider,
			$this->mimeTypeLoader,
			$this->config
		);

		$this->commandTester = new CommandTester($command);

		$this->dataDir = \OC::$server->getConfig()->getSystemValue('datadirectory', \OC::$SERVERROOT . '/data-autotest');

		@mkdir($this->dataDir . '/' . $this->user1->getUID() . '/files/toscan', 0777, true);
	}

	protected function tearDown() {
		$this->user1->delete();
		$this->user2->delete();
		parent::tearDown();
	}

	public function testScanAll() {
		@mkdir($this->dataDir . '/' . $this->user2->getUID() . '/files/toscan2', 0777, true);

		$input = [
			'--all' => true,
		];

		$result = $this->commandTester->execute($input);
		$this->assertEquals(0, $result);

		// new entry was found for both users
		$storageId = $this->getStorageId('home::' . $this->user1->getUID());
		$entry = $this->getFileCacheEntry($storageId, 'files/toscan');
		$this->assertEquals('files/toscan', $entry['path']);

		$storageId2 = $this->getStorageId('home::' . $this->user2->getUID());
		$entry2 = $this->getFileCacheEntry($storageId2, 'files/toscan2');
		$this->assertEquals('files/toscan2', $entry2['path']);

	}

	public function testScanOne() {
		@mkdir($this->dataDir . '/' . $this->user2->getUID() . '/files/toscan2', 0777, true);

		$input = [
			'user_id' => [$this->user2->getUID()],
		];

		$result = $this->commandTester->execute($input);
		$this->assertEquals(0, $result);

		// new entry was found only for user2
		$storageId = $this->getStorageId('home::' . $this->user1->getUID());
		$this->assertFalse($this->getFileCacheEntry($storageId, 'files/toscan'));

		$storageId2 = $this->getStorageId('home::' . $this->user2->getUID());
		$entry2 = $this->getFileCacheEntry($storageId2, 'files/toscan2');
		$this->assertEquals('files/toscan2', $entry2['path']);
	}

	public function maintenanceConfigsProvider() {
		return [
			[
				[
					['singleuser', false, true],
					['maintenance', false, false],
				],
			],
			[
				[
					['singleuser', false, false],
					['maintenance', false, true],
				],
			],
		];
	}

	/**
	 * Test running repair all
	 *
	 * @dataProvider maintenanceConfigsProvider
	 */
	public function testScanRepairAllInMaintenanceMode($config) {
		$this->config->method('getSystemValue')
			->will($this->returnValueMap($config));

		$input = [
			'--all' => true,
			'--repair' => true,
		];

		$result = $this->commandTester->execute($input);

		// TODO: find a way to test that repair code has run

		// new entry was found
		$storageId = $this->getStorageId('home::' . $this->user1->getUID());
		$entry = $this->getFileCacheEntry($storageId, 'files/toscan');
		$this->assertEquals('files/toscan', $entry['path']);

		$this->assertEquals(0, $result);
	}

	/**
	 * Returns storage numeric id for the given string id
	 *
	 * @param string $storageStringId
	 * @return int|null numeric id
	 */
	private function getStorageId($storageStringId) {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('numeric_id')
			->from('storages')
			->where($qb->expr()->eq('id', $qb->createNamedParameter($storageStringId)));
		$results = $qb->execute();
		$result = $results->fetch();
		$results->closeCursor();

		if ($result) {
			return (int)$result['numeric_id'];
		}

		return null;
	}

	/**
	 * Returns file cache DB entry for given path
	 *
	 * @param int $storageId storage numeric id
	 * @param string $path path
	 * @return array file cache DB entry
	 */
	private function getFileCacheEntry($storageId, $path) {
		$qb = $this->connection->getQueryBuilder();
		$qb->select('*')
			->from('filecache')
			->where($qb->expr()->eq('storage', $qb->createNamedParameter($storageId)))
			->andWhere($qb->expr()->eq('path_hash', $qb->createNamedParameter(md5($path))));
		$results = $qb->execute();
		$result = $results->fetch();
		$results->closeCursor();

		return $result;
	}

	/**
	 * Test repair all error message when not in maintenance mode
	 *
	 */
	public function testScanRepairAllNoSingleUserMode() {
		$this->config->method('getSystemValue')
			->will($this->returnValueMap([
				['singleuser', false, false],
				['maintenance', false, false],
			]));

		$input = [
			'--all' => true,
			'--repair' => true,
		];

		$result = $this->commandTester->execute($input);

		$this->assertEquals(1, $result);

		$output = $this->commandTester->getDisplay();

		$this->assertContains('Please switch to single user mode', $output);
		$this->assertContains('specify a user to repair', $output);

		$storageId = $this->getStorageId('home::' . $this->user1->getUID());
		$this->assertFalse($this->getFileCacheEntry($storageId, 'files/toscan'));
	}
}

