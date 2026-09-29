<?php
/**
 * EGroupware Mail: regression test for Compose::setDefaults() and a stale LastSignatureIDUsed pref
 *
 * @link https://www.egroupware.org
 * @package mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

use EGroupware\Api;
use EGroupware\Api\Mail;
use EGroupware\Mail\Compose;

/**
 * Ticket #125541: a customer could no longer compose mail at all - no identity/signature was
 * preselected. Root cause: the user's `mail/LastSignatureIDUsed` preference (eg.
 * `{"3":"283"}`) referenced an identity that had since been deleted / now belongs to a
 * different account. setDefaults() trusted that value without validating it still belongs to
 * the current user, so the bogus ident_id was passed straight through instead of falling back
 * to the account's default identity - the old (since-deleted) mail_compose::compose() validated
 * this via a try/catch around read_identity(), which was lost when that class was removed.
 */
class ComposeSetDefaultsIdentityTest extends Api\LoggedInTest
{
	private $origPref;

	protected function setUp() : void
	{
		parent::setUp();
		$this->origPref = $GLOBALS['egw_info']['user']['preferences']['mail']['LastSignatureIDUsed'] ?? null;
	}

	protected function tearDown() : void
	{
		if (isset($this->origPref))
		{
			$GLOBALS['egw_info']['user']['preferences']['mail']['LastSignatureIDUsed'] = $this->origPref;
		}
		else
		{
			unset($GLOBALS['egw_info']['user']['preferences']['mail']['LastSignatureIDUsed']);
		}
		parent::tearDown();
	}

	private function composeForProfile(int $profileID) : Compose
	{
		$compose = (new ReflectionClass(Compose::class))->newInstanceWithoutConstructor();
		$compose->mail_bo = (object)['profileID' => $profileID];
		return $compose;
	}

	public function testStaleIdentityPreferenceFallsBackInsteadOfBeingUsed()
	{
		// acc_id=1 only has ident_id=1 (demo@boulder.egroupware.org) - 999999999 can never be valid
		$GLOBALS['egw_info']['user']['preferences']['mail']['LastSignatureIDUsed'] = [1 => 999999999];

		$content = $this->composeForProfile(1)->setDefaults(['mimeType' => true]);

		$this->assertNotEquals(999999999, $content['mailidentity'],
			'A stale/foreign LastSignatureIDUsed entry must not be trusted - it should fall back '.
			'to a real identity instead of leaving compose without a usable sender');
		$this->assertEquals(1, $content['mailidentity'],
			'acc_id=1 has exactly one identity (ident_id=1) - that is the only valid fallback');
	}

	public function testValidIdentityPreferenceIsStillHonored()
	{
		$validIdentity = current(iterator_to_array(Mail\Account::identities(1, true, 'params')))['ident_id'];
		$GLOBALS['egw_info']['user']['preferences']['mail']['LastSignatureIDUsed'] = [1 => $validIdentity];

		$content = $this->composeForProfile(1)->setDefaults(['mimeType' => true]);

		$this->assertEquals($validIdentity, $content['mailidentity'],
			'A LastSignatureIDUsed entry that still resolves to a real identity of the current '.
			'user must keep being used, unchanged from before this fix');
	}
}
