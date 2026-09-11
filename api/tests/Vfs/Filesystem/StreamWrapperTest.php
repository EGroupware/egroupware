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
	 * @throws Api\Exception\AssertionFailed
	 */
	#[\PHPUnit\Framework\Attributes\Depends('testSimpleReadWrite')]
	public function testWithAccess() : void
	{
		$this->markTestSkipped("Filesystem StreamWrapper does not support giving access to a file by changing group permissions");
	}

	public function testSymlinkFromFolder($test_file = ''): void
	{
		// Pass a file inside the mountpoint.  It doesn't need to exist
		parent::testSymlinkFromFolder(static::$mountpoint . '/test');
	}

	/**
	 * Filesystem\StreamWrapper defines no chmod() method at all - the
	 * fixed, mount-configured mode (the mount url's `mode=` query param, set
	 * up by StreamWrapperBase::mountFilesystem()) can't be changed per-file,
	 * unlike a real filesystem or sqlfs. _call_on_backend() falls through to
	 * "method does not exist on backend" and returns false
	 * (api/src/Vfs/Base.php:672-679).
	 */
	public function testChmodIsUnsupported() : void
	{
		file_put_contents(Vfs::PREFIX . $this->test_file, 'x');
		$before = Vfs::stat($this->test_file)['mode'] & 0777;

		$this->assertFalse(@Vfs::chmod($this->test_file, 0777));
		Vfs::clearstatcache();
		$this->assertEquals($before, Vfs::stat($this->test_file)['mode'] & 0777);
	}

	/**
	 * The test mount (StreamWrapperBase::mountFilesystem()) does not set
	 * `exec=1`, so writing a script-extension file must be denied -
	 * Filesystem\StreamWrapper::deny_script(),
	 * SCRIPT_EXTENSIONS_PREG='/\.(php[0-9]*|pl|py)$/' (Filesystem/
	 * StreamWrapper.php:120,755-761), checked from stream_open() for any
	 * non-read-only open (:166).
	 */
	public function testDenyScriptWithoutExecParam() : void
	{
		$script = $this->getFilename() . '.php';
		$this->files[] = $script;

		$this->assertFalse(@file_put_contents(Vfs::PREFIX . $script, '<?php echo "no"; '));
		$this->assertFalse(Vfs::file_exists($script));
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
