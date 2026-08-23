<?php
/**
 * EGroupware Mail: REST API tests for PATCH /mail/{id} (existing mail-account editing)
 *
 * @link https://www.egroupware.org
 * @package mail
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\RestBase;
use GuzzleHttp\RequestOptions;

require_once __DIR__.'/../../../api/tests/RestBase.php';

/**
 * REST API tests for the only mail-account REST endpoint that currently exists,
 * PATCH /mail/{id} (ApiHandler::put(), {id} is an ident_id, NOT an acc_id). There is no
 * account-creation endpoint - Mail Wizard (admin_mail/mail_wizard) discovery/creation logic
 * is covered separately by admin/tests/AdminMail*Test.php and mail/tests/
 * MailWizardDifferentialTest.php (pure PHP-logic level, no REST surface).
 *
 * Uses acc_id=1 (Stalwart/JMAP), confirmed a multi-user "Everyone" account
 * (Mail\Account::is_multiple()===true, account_id===[0]). Because of that, there is a
 * SINGLE shared identity row (not one per real user) - so "a non-admin editing another
 * user's account" is exercised not via a second identity, but by requesting via a
 * DIFFERENT real account_lid's URL prefix (/<other_user>/mail/{id}) while authenticated as
 * someone else: ApiHandler::put()'s ownership check compares the URL-derived $user against
 * the AUTHENTICATED session's account_id, so a mismatched prefix hits the real 403 branch
 * regardless of how many identity rows exist.
 *
 * acc_user_editable on this account defaults to false in the test environment - every test
 * that needs a particular value sets it explicitly via the admin client (which bypasses the
 * check entirely) and setUp()/tearDown() restore the pre-test identRealname/accUserEditable
 * values read at the start of each test, so the account's real config is never left changed.
 *
 * PATCH/GET only ever touch identRealname (ident_realname) - a display/From-header field,
 * never any acc_imap_ or acc_smtp_ transport setting - so the account's working IMAP/SMTP/
 * JMAP configuration is never at risk.
 *
 * @covers \EGroupware\Mail\ApiHandler::put
 * @covers \EGroupware\Mail\ApiHandler::get
 */
class MailAccountPatchTest extends RestBase
{
	/**
	 * A second real, always-present account_lid (per doc/ai/testing.md) distinct from
	 * EGW_USER, used only as a URL prefix - never authenticated as directly except via the
	 * admin client.
	 */
	private function otherUser() : string
	{
		return $GLOBALS['EGW_ADMIN_USER'];
	}

	private function adminClient() : \GuzzleHttp\Client
	{
		return $this->getClient([
			RequestOptions::AUTH => [$GLOBALS['EGW_ADMIN_USER'], $GLOBALS['EGW_ADMIN_PASSWORD']],
		]);
	}

	private function identityUrl(string $user, $ident_id) : string
	{
		return $this->url('/'.$user.'/mail/'.$ident_id);
	}

	private function getAccountJson(string $user, $ident_id) : array
	{
		$response = $this->getClient($user)->get($this->identityUrl($user, $ident_id), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
		]);
		$this->assertHttpStatus(200, $response, 'reading current account state');
		return $this->jsonDecode($response);
	}

	private function patchAccount(string $as_user, string $target_user, $ident_id, array $patch) : \Psr\Http\Message\ResponseInterface
	{
		return $this->getClient($as_user)->patch($this->identityUrl($target_user, $ident_id), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::BODY => $this->jsonBody($patch),
		]);
	}

	private function setAccUserEditable($ident_id, bool $editable) : void
	{
		$response = $this->adminClient()->patch($this->identityUrl($this->organizerLid(), $ident_id), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::BODY => $this->jsonBody(['accUserEditable' => $editable]),
		]);
		$this->assertHttpStatus(204, $response, 'admin toggling accUserEditable');
	}

	/**
	 * Discovered/restored per test: the shared identity id on the test account, and the
	 * identRealname/accUserEditable values found before the test ran.
	 */
	private $ident_id;
	private $original_realname;
	private $original_editable;

	protected function setUp() : void
	{
		$response = $this->getClient($this->organizerLid())->get($this->url('/'.$this->organizerLid().'/mail'), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
		]);
		$this->assertHttpStatus(200, $response, 'discovering own identities');
		$identities = $this->jsonDecode($response);
		$this->assertNotEmpty($identities, 'test user must have at least one mail identity');
		$this->ident_id = array_key_first($identities);

		$account = $this->getAccountJson($this->organizerLid(), $this->ident_id);
		$this->original_realname = $account['identRealname'] ?? null;
		$this->original_editable = $account['accUserEditable'] ?? null;
	}

	protected function tearDown() : void
	{
		if (!isset($this->ident_id))
		{
			return;
		}
		// admin write bypasses the ownership/accUserEditable check entirely, so this always
		// succeeds regardless of what a test left behind
		$this->adminClient()->patch($this->identityUrl($this->organizerLid(), $this->ident_id), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::BODY => $this->jsonBody(array_filter([
				'identRealname' => $this->original_realname,
				'accUserEditable' => $this->original_editable,
			], static fn($value) => $value !== null)),
		]);
	}

	/**
	 * The account owner can PATCH an editable field on their own identity and see it
	 * round-trip via a subsequent GET.
	 */
	public function testSelfEditableFieldPatchSucceeds()
	{
		$this->setAccUserEditable($this->ident_id, true);

		$response = $this->patchAccount($this->organizerLid(), $this->organizerLid(), $this->ident_id,
			['identRealname' => 'PHPUnit REST Test']);
		$this->assertHttpStatus(204, $response, 'self-edit of an editable field');

		$account = $this->getAccountJson($this->organizerLid(), $this->ident_id);
		$this->assertSame('PHPUnit REST Test', $account['identRealname']);
	}

	/**
	 * A non-admin requesting a DIFFERENT real user's mail collection - authenticated as one
	 * user but addressing another user's URL prefix - must be rejected with 403, regardless
	 * of accUserEditable, because the URL-derived owner doesn't match the authenticated
	 * session's account_id.
	 */
	public function testNonAdminPatchingAnotherUsersIdentityReturns403()
	{
		$this->setAccUserEditable($this->ident_id, true);

		$response = $this->patchAccount($this->organizerLid(), $this->otherUser(), $this->ident_id,
			['identRealname' => 'Should Not Apply']);
		$this->assertHttpStatus(403, $response);
	}

	/**
	 * When accUserEditable is false, even the account's own (non-admin) user must be
	 * rejected from self-editing.
	 */
	public function testAccUserEditableFalseBlocksOwnersSelfEditWith403()
	{
		$this->setAccUserEditable($this->ident_id, false);

		$response = $this->patchAccount($this->organizerLid(), $this->organizerLid(), $this->ident_id,
			['identRealname' => 'Should Not Apply']);
		$this->assertHttpStatus(403, $response);
	}

	/**
	 * An admin can PATCH any user's mail account, bypassing the ownership/accUserEditable
	 * check entirely - proven here while accUserEditable is explicitly false, so the
	 * success can only come from the admin bypass, not a permissive accUserEditable value.
	 */
	public function testAdminPatchingAnotherUsersIdentitySucceeds()
	{
		$this->setAccUserEditable($this->ident_id, false);

		$response = $this->adminClient()->patch($this->identityUrl($this->organizerLid(), $this->ident_id), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::BODY => $this->jsonBody(['identRealname' => 'PHPUnit Admin Edit']),
		]);
		$this->assertHttpStatus(204, $response);

		$account = $this->getAccountJson($this->organizerLid(), $this->ident_id);
		$this->assertSame('PHPUnit Admin Edit', $account['identRealname']);
	}

	/**
	 * PATCHing a non-existent identity id must return 404, not silently succeed or 403.
	 */
	public function testPatchingNonExistentIdentityReturns404()
	{
		$response = $this->patchAccount($this->organizerLid(), $this->organizerLid(), 999999999,
			['identRealname' => 'Should Not Apply']);
		$this->assertHttpStatus(404, $response);
	}
}
