<?php
/**
 * EGroupware importexport: tests for the DB-free parts of importexport_schedule_ui
 *
 * @link http://www.egroupware.org
 * @package importexport
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../../api/src/loader/common.php');

use PHPUnit\Framework\TestCase;

/**
 * importexport_schedule_ui::check_target() validates a scheduled import/export target
 * URL. Only two of its branches are DB-free: the 'file' scheme (always rejected outright)
 * and an unrecognised scheme (rejected because no stream wrapper is registered for it).
 * Every other branch needs either the VFS (DB-backed) or a live outbound HTTP request, so
 * isn't covered here.
 *
 * is__writable() is a private static filesystem-only helper (checks a real path is
 * writable, including the "path doesn't exist yet, but its parent dir is" case) - reached
 * here via reflection, using real temp files/dirs so the check itself isn't mocked.
 */
class ImportexportScheduleUiTest extends TestCase
{
	/**
	 * Direct filesystem access (file:// scheme) is always rejected, regardless of type,
	 * to force imports/exports through the VFS.
	 */
	public function testFileSchemeAlwaysRejected()
	{
		$data = array('target' => 'file:///etc/passwd', 'type' => 'import');

		$this->assertSame('Direct file access not allowed', importexport_schedule_ui::check_target($data));
	}

	/**
	 * A scheme with no registered PHP stream wrapper (and not the special-cased empty
	 * scheme, which gets prefixed with the VFS scheme instead) is rejected with a
	 * translated error naming the scheme.
	 */
	public function testUnknownSchemeRejected()
	{
		$data = array('target' => 'bogus-scheme://something', 'type' => 'import');

		$this->assertSame("Unable to access files with 'bogus-scheme'", importexport_schedule_ui::check_target($data));
	}

	/**
	 * is__writable() on a writable directory path (trailing slash) must return true -
	 * it creates and removes a uniquely-named temp file inside the directory to verify.
	 */
	public function testIsWritableAcceptsWritableDirectory()
	{
		$method = new ReflectionMethod(importexport_schedule_ui::class, 'is__writable');

		$this->assertTrue($method->invoke(null, sys_get_temp_dir().'/'));
	}

	/**
	 * is__writable() on a path whose parent directory doesn't exist at all must return
	 * false, not throw or emit a warning.
	 */
	public function testIsWritableRejectsPathWithMissingParentDirectory()
	{
		$method = new ReflectionMethod(importexport_schedule_ui::class, 'is__writable');

		$this->assertFalse($method->invoke(null, '/nonexistent_dir_for_test_xyz/subpath/file.tmp'));
	}
}
