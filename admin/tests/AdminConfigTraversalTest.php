<?php

/**
 * Tests for admin_config::ajax_upload_anon_images()'s temp-file path traversal hardening
 *
 * @link http://www.egroupware.org
 * @package admin
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

// test base providing common stuff
require_once __DIR__.'/CommandBase.php';

use EGroupware\Api;

/**
 * Regression coverage for commit 133266b4ef ("hardened tmp-file uses"), the
 * admin_config::ajax_upload_anon_images() site specifically. Before that commit, the uploaded
 * file's claimed name flowed unsanitized into the anon-images destination path
 * ($path.'/'.$file[$tmp_file[0]]['name']) - a name like '../../../x.php' would have written
 * outside the intended anon-images directory. The fix wraps it in Api\Vfs::basename().
 *
 * (The sibling half of that same fix - basename() on the temp-file array KEY used for the
 * rename() SOURCE path - is not independently exercised here: that value is never reflected
 * back in any observable response, so proving it requires either filesystem fixtures outside
 * temp_dir or reflection into rename()'s actual arguments, neither attempted in this pass.)
 */
class AdminConfigTraversalTest extends CommandBase
{
	protected function tearDown() : void
	{
		Api\Json\Response::get()->initResponseArray();
		parent::tearDown();
	}

	public function testUploadAnonImagesConfinesDestinationToBasename()
	{
		$this->asAdmin(function()
		{
			Api\Json\Response::get()->initResponseArray();

			(new \admin_config())->ajax_upload_anon_images(
				['some_tmp_key' => ['name' => '../../../evil.php', 'type' => 'text/plain']],
				[]
			);

			$result = Api\Json\Response::get()->initResponseArray();
			$message = (string)($result[0]['data'] ?? '');

			$this->assertStringNotContainsString('..', $message,
				'The destination path must be confined via Api\Vfs::basename(), not leak the traversal segments');
			$this->assertStringContainsString('evil.php', $message,
				'Sanity check: the basename portion of the malicious filename should still appear');
		});
	}
}
