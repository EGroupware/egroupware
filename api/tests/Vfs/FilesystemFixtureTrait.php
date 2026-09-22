<?php
/**
 * EGroupware API: a writable copy of the filesystem fixture, for tests that mount it
 *
 * @link https://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs;

/**
 * Mount a copy of api/tests/fixtures/Vfs/filesystem_mount, never the fixture itself.
 *
 * Tests that mount it write through the mount, and the fixture is a *tracked* directory owned by
 * whoever checked the repository out - which is neither the user running the tests nor the user
 * the webserver runs as.  Mounting it directly therefore:
 *
 *  - made writability depend on that ownership: as www-data, the way CI runs PHPUnit,
 *    Vfs::is_writable() on the mount is false and the test fails before it does anything;
 *  - let the suite litter the repository - a run as root leaves a root-owned "Vfs" directory
 *    inside the fixture, which then shows up in git status and stops PHPUnit even *enumerating*
 *    the suite as another user ("Failed to open directory: Permission denied");
 *  - and made a share of that mount depend on the webserver being able to read a path it has no
 *    business having rights to.
 *
 * A copy in the temp directory is owned by whoever is running, world-writable, and thrown away
 * afterwards, so none of the above applies.
 */
trait FilesystemFixtureTrait
{
	/**
	 * Copies made by filesystemFixtureCopy(), removed by removeFilesystemFixtureCopies()
	 *
	 * @var string[]
	 */
	protected $fs_fixture_copies = [];

	/**
	 * A writable copy of the filesystem fixture
	 *
	 * @return string absolute path to the copy
	 */
	protected function filesystemFixtureCopy() : string
	{
		$fixture = realpath(__DIR__ . '/../fixtures/Vfs/filesystem_mount');
		if(!$fixture)
		{
			$this->fail("Missing filesystem test directory 'api/tests/fixtures/Vfs/filesystem_mount'");
		}

		$copy = sys_get_temp_dir() . '/egw_fs_mount_' . getmypid() . '_' . count($this->fs_fixture_copies);
		self::removeDirectory($copy);
		if(!mkdir($copy, 0777, true))
		{
			$this->fail("Could not create the filesystem fixture copy '$copy'");
		}
		// umask would otherwise take bits back off both the directory and what we copy into it,
		// and the whole point is that any user involved can write here
		chmod($copy, 0777);

		foreach(scandir($fixture) as $entry)
		{
			if($entry[0] === '.') continue;
			$this->assertTrue(copy($fixture . '/' . $entry, $copy . '/' . $entry),
				"Could not copy fixture file '$entry' to '$copy'");
			chmod($copy . '/' . $entry, 0666);
		}

		$this->fs_fixture_copies[] = $copy;

		return $copy;
	}

	/**
	 * Throw the copies away - call from tearDown()
	 */
	protected function removeFilesystemFixtureCopies() : void
	{
		foreach($this->fs_fixture_copies as $copy)
		{
			self::removeDirectory($copy);
		}
		$this->fs_fixture_copies = [];
	}

	/**
	 * rm -rf, used only on the copies this trait made
	 *
	 * @param string $dir
	 */
	protected static function removeDirectory(string $dir) : void
	{
		if(!is_dir($dir)) return;

		foreach(scandir($dir) as $entry)
		{
			if($entry === '.' || $entry === '..') continue;

			$path = $dir . '/' . $entry;
			is_dir($path) && !is_link($path) ? self::removeDirectory($path) : @unlink($path);
		}
		@rmdir($dir);
	}
}
