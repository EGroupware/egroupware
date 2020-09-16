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

	}

	protected function tearDown() : void
	{
		// Do local stuff first, parent will remove stuff

		parent::tearDown();
	}

	/**
	 * Simple test that we can write something and it's there
	 */
	public function testSimpleReadWrite() : void
	{
		$this->files[] = $test_file = $this->getFilename();
		$contents = $this->getName() . "\nJust a test ;)\n";
		$this->assertNotFalse(
				file_put_contents(Vfs::PREFIX . $test_file, $contents),
			"Could not write file $test_file"
		);

		// Check contents are unchanged
		$this->assertEquals(
				$contents, file_get_contents(Vfs::PREFIX . $test_file),
				"Read file contents do not match what was written"
		);
	}

	/**
	 * Simple delete of a file
	 */
	public function testDelete() : void
	{
		$this->files[] = $test_file = $this->getFilename();

		// Check that the file is not there
		$pre_start = Vfs::stat($test_file);
		$this->assertEquals(null,$pre_start,
				"File '$test_file' was there before we started, check clean up"
		);

		// Write
		$contents = $this->getName() . "\nJust a test ;)\n";
		$this->assertNotFalse(
				file_put_contents(Vfs::PREFIX . $test_file, $contents),
			"Could not write file $test_file"
		);

		$start = Vfs::stat($test_file);
		$this->assertNotNull(
				$start,
				"File '$test_file' was not what we expected to find after writing"
		);

		Vfs::unlink($test_file);

		$post = Vfs::stat($test_file);
		$this->assertEquals(null,$post,
				"File '$test_file' was there after deleting"
		);
	}
}