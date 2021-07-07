<?php

/**
 * Test file properties (proppatch)
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


class ProppatchTest extends LoggedInTest
{
	protected function setUp() : void
	{
		parent::setUp();

	}

	protected function tearDown() : void
	{
		// Remove any added files (as root to limit versioning issues)

		$backup = Vfs::$is_root;
		Vfs::$is_root = true;
		if(in_array('/',$this->files))
		{
			$this->fail('Tried to remove root');
		}
		foreach($this->files as $file)
		{
			if(Vfs::is_dir($file) && !Vfs::is_link(($file)))
			{
				Vfs::rmdir($file);
			}
			else
			{
				Vfs::unlink($file);
			}
		}
		Vfs::$is_root = $backup;
		parent::tearDown();
	}

	/**
	 * Check read / write / delete proppatch
	 */
	public function testProppatch()
	{
		$this->files[] = $test_file = $this->getFilename();
		$contents = $this->getName() . "\nJust a test ;)\n";
		$this->assertNotFalse(
				file_put_contents(Vfs::PREFIX . $test_file, $contents),
			"Could not write test file $test_file"
		);

		$proppatch = [['ns' => Vfs::DEFAULT_PROP_NAMESPACE, 'name' => 'test', 'val' => 'something']];
		$this->assertTrue(
			Vfs::proppatch($test_file, $proppatch),
			"Could not set properties"
		);
		$read = Vfs::propfind($test_file);

		$this->assertEquals($proppatch, $read,
		"Read proppatch does not match what was written"
		);

		// Try to delete, while we're here
		$proppatch[0]['val'] = null;
		$this->assertTrue(
				Vfs::proppatch($test_file, $proppatch),
				"Could not delete properties by setting val = null"
		);
		$read_deleted = Vfs::propfind($test_file);
		$this->assertNotFalse($read_deleted, "Problem reading properties after deleting");
		$this->assertEquals([], $read_deleted, "Found properties after deleting");
	}

	/**
	 * Test that an invalid proppatch is not accepted
	 */
	public function testInvalidProppatch()
	{
		$this->files[] = $test_file = $this->getFilename();
		$contents = $this->getName() . "\nJust a test ;)\n";
		$this->assertNotFalse(
				file_put_contents(Vfs::PREFIX . $test_file, $contents),
			"Could not write test file $test_file"
		);

		$proppatch = [['No worky']];
		$this->assertFalse(
			Vfs::proppatch($test_file, $proppatch),
			"Managed to set invalid properties"
		);
	}

	public function testWriteWithNoPermissionsFails()
	{
		$this->files[] = $test_file = $this->getFilename();
		Vfs::remove($test_file);
		$contents = $this->getName() . "\nJust a test ;)\n";
		$this->assertNotFalse(
				file_put_contents(Vfs::PREFIX . $test_file, $contents),
			"Could not write test file $test_file"
		);

		// Change owner so we lose permission
		Vfs::$is_root = true;
		$this->assertTrue(
			Vfs::chown($test_file, 'anonymous'),
			"Could not chown test file '$test_file'"
		);
		Vfs::$is_root = false;

		// Try to set property
		$proppatch = [['ns' => Vfs::DEFAULT_PROP_NAMESPACE, 'name' => 'test', 'val' => 'something']];
		$this->assertFalse(
			Vfs::proppatch($test_file, $proppatch),
			"Managed to set properties with no permission"
		);

	}

	/**
	 * Make a filename that reflects the current test
	 */
	protected function getFilename($path = null)
	{
		if(is_null($path)) $path = Vfs::get_home_dir().'/';
		if(substr($path,-1,1) !== '/') $path = $path . '/';

		$reflect = new \ReflectionClass($this);
		return $path . $reflect->getShortName() . '_' . $this->getName(false) . '.txt';
	}

}