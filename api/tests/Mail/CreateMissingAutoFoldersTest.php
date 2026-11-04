<?php
/**
 * EGroupware API: tests for ticket #127441 - standard/special-use folders auto-created for a new
 * user on a shared/multi-user mail account used to happen on first use, lost at some point.
 * Restored via Api\Mail::createMissingAutoFolders(), called once from
 * Imap\Dovecot::addAccount()/Imap\Jmap::addAccount() (the "addaccount" system hook, already scoped
 * to shared/multi-user accounts only by Account::is_multiple(), see Api\Mail\Hooks::
 * run_plugin_hooks()) - NOT on every login, which would reintroduce the "constant folder-exists
 * checking" performance cost this was deliberately kept out of the hot path for.
 *
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

use PHPUnit\Framework\TestCase;

class CreateMissingAutoFoldersTest extends TestCase
{
	protected function tearDown() : void
	{
		// Mail::$specialUseFolders is a process-wide static cache - never leak a test fixture into
		// any other test file sharing this PHPUnit process (same lesson as the Static ACL cache
		// leak memory: a user/account-scoped static must be reset, not just left for GC).
		Mail::$specialUseFolders = null;
		parent::tearDown();
	}

	/**
	 * @param array<string, array{folder: string|false, created: bool}|'throw'> $results keyed by
	 *  special-use type ('Drafts'|'Templates'|'Sent'|'Trash'|'Junk'|'Outbox'|'Archive')
	 */
	private function mailStubbingSpecialUseFolder(array $results) : Mail
	{
		return new class($results) extends Mail {
			public array $calls = [];
			private array $results;

			public function __construct(array $results)
			{
				$this->results = $results;
			}

			function _getSpecialUseFolder($_type, $_checkexistance = TRUE, &$created = null, $_bypassJmapGuard = false)
			{
				$this->calls[] = ['type' => $_type, 'checkexistance' => $_checkexistance, 'bypass' => $_bypassJmapGuard];
				$result = $this->results[$_type] ?? null;
				if ($result === 'throw')
				{
					throw new \Exception("simulated broken mail-server connection for $_type");
				}
				$created = $result['created'] ?? false;
				return $result['folder'] ?? false;
			}
		};
	}

	/**
	 * Every call must pass $_bypassJmapGuard=true (ticket #127441: that's the whole point - a
	 * JMAP/Stalwart account's folders never otherwise get checked/created at all), and Archive must
	 * be included alongside the six self::$autoFolders types, unlike check_create_autofolders().
	 */
	public function testLoopsOverAllAutoFoldersPlusArchiveWithBypassTrue() : void
	{
		$mail = $this->mailStubbingSpecialUseFolder([
			'Drafts'    => ['folder' => 'Drafts', 'created' => true],
			'Templates' => ['folder' => 'Templates', 'created' => false],
			'Sent'      => ['folder' => 'Sent', 'created' => true],
			'Trash'     => ['folder' => 'Trash', 'created' => false],
			'Junk'      => ['folder' => 'Junk', 'created' => true],
			'Outbox'    => ['folder' => 'Outbox', 'created' => true],
			'Archive'   => ['folder' => false, 'created' => false],
		]);

		$numCreated = $mail->createMissingAutoFolders();

		$this->assertSame(['Drafts', 'Templates', 'Sent', 'Trash', 'Junk', 'Outbox', 'Archive'],
			array_column($mail->calls, 'type'));
		foreach ($mail->calls as $call)
		{
			$this->assertTrue($call['checkexistance'], "checkexistance must be true for {$call['type']}");
			$this->assertTrue($call['bypass'], "bypass must be true for {$call['type']}");
		}
		// Drafts + Sent + Junk created; Outbox deliberately excluded from the count (same as
		// check_create_autofolders()'s own "&& $folder != 'Outbox'"); Templates/Trash already
		// existed; Archive was unconfigured
		$this->assertSame(3, $numCreated);
	}

	/**
	 * An unconfigured Archive folder (the common case - most accounts never set acc_folder_archive)
	 * must be silently skipped, not treated as a failure - same as _getSpecialUseFolder()'s own
	 * 'Archive' branch, which never auto-creates one with a guessed name.
	 */
	public function testUnconfiguredArchiveIsSilentlySkipped() : void
	{
		$mail = $this->mailStubbingSpecialUseFolder([
			'Drafts'    => ['folder' => 'Drafts', 'created' => false],
			'Templates' => ['folder' => 'Templates', 'created' => false],
			'Sent'      => ['folder' => 'Sent', 'created' => false],
			'Trash'     => ['folder' => 'Trash', 'created' => false],
			'Junk'      => ['folder' => 'Junk', 'created' => false],
			'Outbox'    => ['folder' => 'Outbox', 'created' => false],
			'Archive'   => ['folder' => false, 'created' => false],
		]);

		$this->assertSame(0, $mail->createMissingAutoFolders());
	}

	/**
	 * A broken mail-server connection failing on ONE folder type must not stop the rest, and must
	 * not propagate out of createMissingAutoFolders() at all - this runs from the addaccount hook,
	 * which must never stall/abort the account-creation (or LDAP/UCS sync) transaction it fires from.
	 */
	public function testExceptionForOneFolderDoesNotStopTheRestOrPropagate() : void
	{
		$mail = $this->mailStubbingSpecialUseFolder([
			'Drafts'    => 'throw',
			'Templates' => ['folder' => 'Templates', 'created' => true],
			'Sent'      => ['folder' => 'Sent', 'created' => true],
			'Trash'     => ['folder' => 'Trash', 'created' => true],
			'Junk'      => ['folder' => 'Junk', 'created' => true],
			'Outbox'    => ['folder' => 'Outbox', 'created' => true],
			'Archive'   => ['folder' => 'Archive', 'created' => true],
		]);

		$numCreated = $mail->createMissingAutoFolders();

		$this->assertCount(7, $mail->calls, 'the exception for Drafts must not stop the loop from reaching the other types');
		// Templates + Sent + Trash + Junk + Archive created; Outbox excluded from the count; Drafts failed
		$this->assertSame(5, $numCreated);
	}
}
