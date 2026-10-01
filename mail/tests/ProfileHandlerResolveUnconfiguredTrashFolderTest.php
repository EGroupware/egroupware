<?php
/**
 * EGroupware Mail: Mail\Ui\ProfileHandler::resolveUnconfiguredTrashFolder()'s own fallback contract
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License Version 2+
 */

use EGroupware\Api;
use EGroupware\Mail\Ui\ProfileHandler;

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

/**
 * Ticket-driven regression (help forum, "26.9.20260928, Mail werden nicht gelöscht" - bzubi):
 * jmapBootstrap()'s own `$imapServer->acc_folder_trash ?: 'Trash'` fallback broke a local (non-
 * Stalwart) account whose Dovecot uses its own personal-namespace prefix (eg. "INBOX/Trash") and
 * had never had acc_folder_trash explicitly configured - EGroupware kept sending the bare "Trash"
 * name, which Dovecot rejected ("Client tried to access nonexistent namespace. (Mailbox name
 * should probably be prefixed with: INBOX/)"). resolveUnconfiguredTrashFolder() replaces that bare
 * guess with Mail::getTrashFolder()'s own real special-use/namespace-prefix-aware detection - but
 * ONLY for this one unconfigured case (jmapBootstrap()'s own call site keeps the cheap, no-round-
 * trip path unchanged whenever acc_folder_trash IS configured - the common case, confirmed by
 * inspection of that call site rather than here, since this method is only ever reached at all in
 * the rare unconfigured branch).
 *
 * This file only covers the "must never throw / degrades to the bare name" contract - the actual
 * successful detection needs a real local (non-Stalwart) IMAP connection with special-use
 * folders, which this container's test DB doesn't have (acc_id=1 here is Stalwart/JMAP - see
 * feedback_docker_container_test_db_differs) - verify that path live instead.
 */
class ProfileHandlerResolveUnconfiguredTrashFolderTest extends Api\LoggedInTest
{
	private function call(int $profileID) : string
	{
		$method = new ReflectionMethod(ProfileHandler::class, 'resolveUnconfiguredTrashFolder');
		$method->setAccessible(true);
		return $method->invoke(null, $profileID);
	}

	public function testFallsBackToTheBareNameInsteadOfThrowingForAnInvalidProfile()
	{
		// no account with this id can exist - Mail::getInstance() must throw internally, and this
		// method must swallow it rather than let jmapBootstrap()'s own top-level catch turn one
		// account's trash-folder detection hiccup into a hard failure of the WHOLE bootstrap
		$this->assertSame('Trash', $this->call(999999999));
	}
}
