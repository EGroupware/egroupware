<?php
/**
 * EGroupware Mail: Test Ui\FolderHandler::setFolderStatus()'s guard clauses
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail\Ui;

use EGroupware\Api;
use EGroupware\Mail\Ui;
use EGroupware\Api\Mail\Imap\Jmap as ImapJmap;

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

/**
 * doc/ai/projects/mail-test-coverage.md's priority-4 entry: FolderHandler.php had zero coverage.
 * `folderSubscription()` has no self-contained slice at all (immediately does real IMAP work), but
 * `setFolderStatus()`'s own JMAP-account early return is both testable in isolation AND already
 * flagged as fragile by its own docblock note ("JMAP-FALLTHROUGH-GUARD" - a real live bug found
 * 2026-08-24: falling through to a raw-socket call that hangs/misconnects against a JMAP(S)
 * endpoint) - exactly the kind of guard worth a dedicated regression test.
 *
 * `new Ui(false)` skips its own constructor's real IMAP/DB bootstrap entirely (`$run_constructor`
 * false-early-returns right after building the (harmless) MailTree sidebox helper) - `mail_bo` is
 * then just a public property, set directly to a minimal stub that throws if any of the
 * icServer/getHierarchyDelimiter/getFolderStatus calls neither guard is supposed to reach are
 * actually invoked. Extends Api\LoggedInTest because referencing Mail\Imap\Jmap at all triggers
 * its own static init (Config::read()), which needs a real DB connection.
 */
class FolderHandlerSetFolderStatusTest extends Api\LoggedInTest
{
	private function handlerWithIcServer(object $icServer) : FolderHandler
	{
		$ui = new Ui(false);
		$ui->mail_bo = new class($icServer) {
			public function __construct(public $icServer) {}
			public function getHierarchyDelimiter($x) { throw new \Exception('getHierarchyDelimiter() must not be called'); }
			public function getFolderStatus(...$args) { throw new \Exception('getFolderStatus() must not be called'); }
		};
		return new FolderHandler($ui);
	}

	public function testReturnsImmediatelyForAJmapAccountWithoutTouchingHierarchyOrFolderStatus()
	{
		$handler = $this->handlerWithIcServer(new class extends ImapJmap { public function __construct() {} });

		// the stub mail_bo throws if either guarded call is reached - reaching here at all,
		// without an exception, is the assertion
		$handler->setFolderStatus(['1::INBOX'], false);
		$this->assertTrue(true);
	}

	public function testDoesNothingForAnEmptyFolderListEvenOnANonJmapAccount()
	{
		$handler = $this->handlerWithIcServer(new class {});

		$handler->setFolderStatus([], false);
		$this->assertTrue(true);
	}

	public function testDoesNothingForANullFolderListEvenOnANonJmapAccount()
	{
		$handler = $this->handlerWithIcServer(new class {});

		$handler->setFolderStatus(null, false);
		$this->assertTrue(true);
	}
}
