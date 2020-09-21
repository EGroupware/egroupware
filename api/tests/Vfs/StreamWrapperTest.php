<?php

/**
 * Test the basic Vfs::StreamWrapper
 *
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @copyright (c) 2020  Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs;

require_once __DIR__ . '/StreamWrapperBase.php';

use EGroupware\Api;
use EGroupware\Api\LoggedInTest as LoggedInTest;
use EGroupware\Api\Vfs;
use EGroupware\Stylite\Vfs\Versioning;


class StreamWrapperTest extends StreamWrapperBase
{
	protected function setUp() : void
	{
		parent::setUp();
		$this->files[] = $this->test_file = $this->getFilename();
	}

	protected function tearDown() : void
	{
		// Do local stuff first, parent will remove stuff

		parent::tearDown();
	}

	public function testWithAccess() : void
	{
		// Put it in the group directory this time so we can give access
		$this->files[] = $this->test_file = $this->getFilename('/home/Default');

		parent::testWithAccess();
	}

	protected function mount(): void
	{
		// Nothing here
	}

	protected function allowAccess(string $test_name, string &$test_file, int $test_user, string $needed) : void
	{
		// We'll allow access by putting test user in Default group
		$command = new \admin_cmd_edit_user($test_user, ['account_groups' => array_merge($this->account['account_groups'],['Default'])]);
		$command->run();

		// Add explicit permission on group
		Vfs::chmod($test_file, Vfs::mode2int('g+'.$needed));

	}
}