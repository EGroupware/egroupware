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
 * The copy is world-writable, owned by whoever is running, and thrown away afterwards, so none of
 * the above applies.
 *
 * It lives under the EGroupware installation root rather than in the system temp directory,
 * because a share of the mount is fetched over HTTP and the webserver has to be able to read the
 * very same absolute path: the two are not always the same machine or the same container, and
 * then /tmp is not shared - the test creates the directory, the webserver stats a path that does
 * not exist for it, and the share 404s.  The installation root is the one directory the webserver
 * is guaranteed to see, since it is what it serves.
 *
 * The name is deliberately stable rather than per-process.  Vfs::mount() persists the mount to
 * egw_config, and a webserver that has already cached its configuration keeps resolving the
 * mount to whatever path it cached; a stable name still points at a directory that exists.
 * Two suites running against one instance already collide on the mount point itself, so this
 * adds no new restriction.
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
	 * Where the copies go, relative to the EGroupware installation root - gitignored
	 */
	const FIXTURE_COPY_DIR = '.vfs-test-mounts';

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

		$copy = realpath(__DIR__ . '/../../..') . '/' . self::FIXTURE_COPY_DIR . '/mount_' .
			count($this->fs_fixture_copies);
		if(!is_dir($parent = dirname($copy)) && !mkdir($parent, 0777, true) && !is_dir($parent))
		{
			$this->fail("Could not create the filesystem fixture copy directory '$parent'");
		}
		@chmod($parent, 0777);
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
