<?php
/**
 * EGroupware API: ticket #127441 - Api\Mail::_getSpecialUseFolder()'s JMAP-FALLTHROUGH-GUARD must
 * only skip the real existence-check/auto-create logic by default (the hot-path case: compose,
 * send, delete, ...), and must actually run it when $_bypassJmapGuard=true (createMissingAutoFolders(),
 * called once per new user via the addaccount hook).
 *
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

use PHPUnit\Framework\TestCase;

class SpecialUseFolderJmapGuardBypassTest extends TestCase
{
	protected function tearDown() : void
	{
		// same process-wide static cache reset as CreateMissingAutoFoldersTest - never leak into
		// another test file sharing this PHPUnit process
		Mail::$specialUseFolders = null;
		parent::tearDown();
	}

	private function jmapIcServer(array $params) : Mail\Imap\Jmap
	{
		return new class($params) extends Mail\Imap\Jmap {
			public function __construct(array $params)
			{
				$this->params = $params;
			}
		};
	}

	/**
	 * @return Mail a Mail instance whose icServer is a (fake) Mail\Imap\Jmap, with Mail::$specialUseFolders
	 *  pre-populated so getSpecialUseFolders() (called unconditionally before the guard check) never
	 *  touches Api\Cache or $GLOBALS['egw_info'] - irrelevant to what this test verifies
	 */
	private function mailWithFolderExistsSpy(array $icServerParams) : Mail
	{
		Mail::$specialUseFolders = ['dummy' => 'Dummy'];	// non-empty -> getSpecialUseFolders() skipped

		$mail = new class extends Mail {
			public array $folderExistsCalls = [];

			public function __construct()
			{
			}

			function folderExists($_folder, $_forceCheck = false)
			{
				$this->folderExistsCalls[] = $_folder;
				return true;	// pretend the folder already exists - no creation attempt needed here
			}
		};
		$mail->icServer = $this->jmapIcServer($icServerParams);
		return $mail;
	}

	public function testGuardShortCircuitsWithoutBypass() : void
	{
		$mail = $this->mailWithFolderExistsSpy(['acc_id' => 1, 'acc_folder_sent' => 'Sent']);

		$created = null;
		$result = $mail->_getSpecialUseFolder('Sent', true, $created);

		$this->assertSame('Sent', $result);
		$this->assertFalse($created);
		$this->assertSame([], $mail->folderExistsCalls,
			'the JMAP-FALLTHROUGH-GUARD must return before ever calling folderExists()');
	}

	public function testBypassReachesTheRealExistenceCheck() : void
	{
		$mail = $this->mailWithFolderExistsSpy(['acc_id' => 1, 'acc_folder_sent' => 'Sent']);

		$created = null;
		$result = $mail->_getSpecialUseFolder('Sent', true, $created, true);

		$this->assertSame('Sent', $result);
		$this->assertSame(['Sent'], $mail->folderExistsCalls,
			'$_bypassJmapGuard=true must reach the real existence check instead of blindly trusting the configured name');
	}

	/**
	 * Archive stays "never auto-create with a guessed name" even with the guard bypassed - an
	 * unconfigured acc_folder_archive must come back false/falsy, not attempt folderExists()/
	 * createFolder() with an empty name.
	 */
	public function testBypassStillNeverGuessesAnArchiveFolderName() : void
	{
		$mail = $this->mailWithFolderExistsSpy(['acc_id' => 1, 'acc_folder_archive' => '']);

		$created = null;
		$result = $mail->_getSpecialUseFolder('Archive', true, $created, true);

		// an empty configured name just comes back unchanged (falsy, no creation attempted) -
		// what matters is that folderExists()/createFolder() are never reached for it
		$this->assertEmpty($result);
		$this->assertFalse($created);
		$this->assertSame([], $mail->folderExistsCalls,
			'an unconfigured Archive folder must never even reach folderExists()');
	}
}
