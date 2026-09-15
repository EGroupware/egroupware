<?php

/**
 * Regression tests for Sharing::validate_path()
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Vfs;

require_once __DIR__.'/../LoggedInTest.php';

use EGroupware\Api\LoggedInTest;

class SharingValidatePathTest extends LoggedInTest
{
	/**
	 * Regression test for https://my.egroupware.org tracker #124481 ("Creating new document in
	 * Collabora not possible"): Sharing::validate_path() (api/src/Vfs/Sharing.php) turns a plain,
	 * non-'vfs://'-scheme $path into a full vfs:// path via
	 * `Vfs::PREFIX.Vfs::parse_url($path, PHP_URL_PATH)`. A $path starting with two or more slashes
	 * (eg. built by a caller that concatenates an already slash-terminated directory with a leading
	 * "/", or a client sending a malformed "path" GET parameter) is indistinguishable from a
	 * scheme-relative "//host/path" network URL to parse_url() - it returns an empty PHP_URL_PATH,
	 * silently truncating $path down to the bare Vfs::PREFIX ("vfs://default") with nothing after it.
	 * Vfs::stat() then rejects that bare prefix with "File 'vfs://default' is not an absolute path!"
	 * (AssertionFailed) - exactly the error from the ticket, hit via Collabora\Wopi::create() ->
	 * Sharing::create() -> validate_path() -> Vfs::stat() when opening a just-created document.
	 *
	 * Pass criteria: a path with a doubled leading slash must resolve the SAME as its single-slash
	 * equivalent - proceeding past the absolute-path check to a normal Api\Exception\NotFound (the
	 * test file does not exist), never Api\Exception\AssertionFailed.
	 */
	public function testDoubleLeadingSlashDoesNotTruncateToBarePrefix()
	{
		$method = new \ReflectionMethod(Sharing::class, 'validate_path');
		$method->setAccessible(true);
		$mode = \EGroupware\Collabora\Wopi::WOPI_WRITABLE;

		try
		{
			$method->invoke(null, '//SharingValidatePathTest-does-not-exist.odt', $mode);
			$this->fail('Expected Api\Exception\NotFound for a non-existing file, but validate_path() did not throw');
		}
		catch (\EGroupware\Api\Exception\NotFound $e)
		{
			// expected - the doubled slash was collapsed and the (non-existing) path resolved normally
			$this->assertStringNotContainsString(
				"'".\EGroupware\Api\Vfs::PREFIX."' NOT found", $e->getMessage(),
				'validate_path() truncated the path down to the bare Vfs::PREFIX'
			);
		}
		catch (\EGroupware\Api\Exception\AssertionFailed $e)
		{
			$this->fail('validate_path() truncated a double-leading-slash path to the bare Vfs::PREFIX: '.$e->getMessage());
		}
	}
}
