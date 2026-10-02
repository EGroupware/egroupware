<?php
/**
 * EGroupware Api: Test Api\Mail\Jmap\Identity::synthesize()'s isStandard/isPersonal flags
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Jmap;

require_once realpath(__DIR__.'/../../LoggedInTest.php');

use EGroupware\Api;
use EGroupware\Api\Mail\Account;

/**
 * Ticket #125092: the client's mail/defaultIdentity preference resolution ('default'/'personal')
 * used to guess an account's standard/personal identity from id comparisons alone (id===acc_id,
 * lowest id) - both wrong (ralf, correcting two successive drafts of this fix): the REAL standard
 * identity is whichever one `egw_ea_accounts.ident_id` points to (Account::$ident_id, admin-
 * settable, independent of acc_id - see Account::IDENTITY_JOIN), and the REAL "personal" marker is
 * `egw_ea_identities.account_id` equalling the CURRENT user's own account_id (0 = general, usable
 * by anyone with access to the mail account, same as the standard identity's own account_id).
 *
 * Api\Mail\Jmap\Identity::synthesize() is where these get resolved server-side and sent to the
 * client as each identity's own isStandard/isPersonal flags - this is the one place that can
 * verify them against real `Account::identities()`/`Account::read()` data, the client-side tests
 * (mail/js/test/MailComposeDefaultIdentityPreference.test.ts etc.) only ever stub these flags as
 * already-known inputs.
 *
 * Uses acc_id=1 (doc/phpunit.xml's own shared/"Everyone" mail account, present on every install -
 * see ImapBuildMailerTest.php's own docblock) - its sole pre-existing identity (account_id=0) is
 * acc_id=1's own standard identity (Account::$ident_id points to it). A second, genuinely personal
 * identity (account_id = the current test user) is created via Account::save_identity() and
 * removed again in tearDown(), so this test's own side effect never outlives it.
 */
class IdentitySynthesizeIsStandardIsPersonalTest extends Api\LoggedInTest
{
	const ACC_ID = 1;

	private ?int $personalIdentId = null;

	protected function tearDown() : void
	{
		if ($this->personalIdentId)
		{
			Account::delete_identity($this->personalIdentId);
		}
		parent::tearDown();
	}

	public function testStandardAndPersonalFlagsReflectRealAccountAndAccountIdColumns()
	{
		$account = Account::read(self::ACC_ID);
		$standardIdentId = (string)$account->ident_id;

		$this->personalIdentId = Account::save_identity([
			'ident_id' => 'new',
			'acc_id' => self::ACC_ID,
			'ident_realname' => 'Test Personal Identity',
			'ident_org' => '',
			'ident_email' => 'personal-identity-test@example.org',
			'ident_signature' => '',
			'account_id' => $GLOBALS['egw_info']['user']['account_id'],
		]);
		$this->assertGreaterThan(0, $this->personalIdentId, 'save_identity() must return the new ident_id');

		$result = Identity::synthesize(self::ACC_ID);
		$byId = [];
		foreach ($result['list'] as $identity)
		{
			$byId[$identity['id']] = $identity;
		}

		$this->assertArrayHasKey($standardIdentId, $byId);
		$this->assertTrue($byId[$standardIdentId]['isStandard'],
			"the account's own pre-existing identity (account_id=0) must be flagged isStandard - it's what Account::\$ident_id points to");
		$this->assertFalse($byId[$standardIdentId]['isPersonal'],
			'account_id=0 (general) must never be flagged isPersonal');

		$personalIdentId = (string)$this->personalIdentId;
		$this->assertArrayHasKey($personalIdentId, $byId);
		$this->assertFalse($byId[$personalIdentId]['isStandard'],
			'the newly-created additional identity must NOT be flagged isStandard, regardless of its own ident_id value');
		$this->assertTrue($byId[$personalIdentId]['isPersonal'],
			"account_id equals the current user's own account_id - must be flagged isPersonal");
	}
}
