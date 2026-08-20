<?php
/**
 * EGroupware filemanager: regression test for ajax_action()'s temp_dir share guard
 *
 * @link http://www.egroupware.org
 * @package filemanager
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api\Storage\Base;

require_once realpath(__DIR__.'/../../api/tests/AppTest.php');

/**
 * Before the fix, filemanager_ui::ajax_action()'s shareWritableLink/shareReadonlyLink
 * cases passed the client-supplied $selected straight into Vfs\Sharing::create(), which
 * for a path under the server's temp_dir only did a raw filesystem is_readable() check -
 * bypassing normal VFS ACL entirely for any guessable/known temp_dir path.
 */
class FilemanagerUiTest extends \EGroupware\Api\AppTest
{
	private $temp_path;

	protected function tearDown(): void
	{
		if ($this->temp_path)
		{
			@unlink($this->temp_path);
			(new Base('api', 'egw_sharing'))->delete(array('share_path' => $this->temp_path));
			$this->temp_path = null;
		}
	}

	/**
	 * Pass criteria: a path under temp_dir must be rejected with WrongParameter, and no
	 * share row created for it - even though the file exists and is readable.
	 */
	public function testRejectsTempDirPath()
	{
		$temp_dir = $GLOBALS['egw_info']['server']['temp_dir'];
		$this->temp_path = $temp_dir.'/phpunit_probe_'.bin2hex(random_bytes(6)).'.txt';
		file_put_contents($this->temp_path, 'phpunit probe');

		$this->expectException(\EGroupware\Api\Exception\WrongParameter::class);

		try
		{
			filemanager_ui::ajax_action('shareReadonlyLink', $this->temp_path, '/');
		}
		finally
		{
			$row = (new Base('api', 'egw_sharing'))->read(array('share_path' => $this->temp_path));
			$this->assertEmpty($row, 'no share row must be created for a temp_dir path');
		}
	}

	/**
	 * Pass criteria: a path that merely starts with a similar but non-matching prefix
	 * (not actually inside temp_dir) must not be rejected by this specific guard - it
	 * must fail (if at all) for a different reason further down (e.g. NotFound), proving
	 * the check is a real prefix match and not overly broad.
	 */
	public function testDoesNotRejectNonTempDirPath()
	{
		$temp_dir = $GLOBALS['egw_info']['server']['temp_dir'];
		// same prefix characters, but not actually temp_dir + '/'
		$look_alike = rtrim($temp_dir, '/').'-not-temp-dir/some/file.txt';

		try
		{
			filemanager_ui::ajax_action('shareReadonlyLink', $look_alike, '/');
			$this->fail('expected an exception for a non-existent VFS path');
		}
		catch (\EGroupware\Api\Exception\WrongParameter $e)
		{
			$this->assertNotSame('Invalid path for sharing!', $e->getMessage(),
				'the temp_dir guard must not fire for a path outside temp_dir');
		}
		catch (\Exception $e)
		{
			// any other exception (e.g. NotFound) is fine - proves we got past the guard
		}
	}
}
