<?php

/**
 * Test the basic Vfs::StreamWrapper
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2020  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs\Filesystem;

require_once __DIR__ . '/../StreamWrapperBase.php';

use EGroupware\Api;
use EGroupware\Api\Vfs;


class StreamWrapperTest extends Vfs\StreamWrapperBase
{
	public static $mountpoint = '/home/demo/filesystem';

	protected function setUp() : void
	{
		parent::setUp();

		$this->files[] = $this->test_file = $this->getFilename();
	}

	protected function tearDown() : void
	{
		parent::tearDown();
	}
	/**
	 * Check that a user with permission to a file can access the file
	 *
	 * @depends testSimpleReadWrite
	 * @throws Api\Exception\AssertionFailed
	 */
	public function testWithAccess() : void
	{
		$this->markTestSkipped("Filesystem StreamWrapper does not support giving access to a file by changing group permissions");
	}

	public function testSymlinkFromFolder($test_file = ''): void
	{
		// Pass a file inside the mountpoint.  It doesn't need to exists
		parent::testSymlinkFromFolder(static::$mountpoint . '/test');
	}

	protected function mount(): void
	{
		$this->mountFilesystem(static::$mountpoint);
	}

	protected function allowAccess(string $test_name, string &$test_file, int $test_user, string $needed) : void
	{
		// We'll allow access by putting test user in Default group
		$command = new \admin_cmd_edit_user($test_user, ['account_groups' => array_merge($this->account['account_groups'],['Default'])]);
		$command->run();

		// Add explicit permission on group
		Vfs::chmod($test_file, Vfs::mode2int('g+'.$needed));
	}

	/**
	 * Make a filename that reflects the current test
	 */
	protected function getFilename($path = null)
	{
		return parent::getFilename(static::$mountpoint);
	}
}