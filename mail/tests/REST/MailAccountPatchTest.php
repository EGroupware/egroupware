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
 * PATCH/GET only ever touch identRealname/identEmail/identOrg - display/From-header fields,
 * never any acc_imap_ or acc_smtp_ transport setting - so the account's working IMAP/SMTP/
 * JMAP configuration is never at risk.
 *
 * @covers \EGroupware\Mail\ApiHandler::put
 * @covers \EGroupware\Mail\ApiHandler::get
 */
class MailAccountPatchTest extends RestBase
{
	/**
	 * ident_id of the shared identity on acc_id=1 (Stalwart/JMAP test account, per project
	 * memory). Not discovered via GET /{user}/mail: that bare, id-less collection URL never
	 * reaches ApiHandler::get()'s own '/mail' case at all - Api\CalDAV::_parse_path()
	 * requires a trailing id segment to route to any app handler, so a bare app URL falls
	 * through to the generic sync-collection-shortcut handling instead (returns
	 * {"responses": {}} for mail, which has no syncable "collection members" of that kind).
	 * Hardcoded here the same way admin/tests/SmimeGenerateTest.php hardcodes acc_id=1.
	 */
	const IDENT_ID = 1;

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

	/**
	 * Like getAccountJson(), but for a caller (eg. admin) not supported by getClient()'s
	 * plain lid-based auth() lookup (only EGW_USER or users created via createUser()).
	 */
	private function getAccountJsonWithClient(\GuzzleHttp\Client $client, string $url_user, $ident_id) : array
	{
		$response = $client->get($this->identityUrl($url_user, $ident_id), [
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
	 * Restored per test: the identRealname/identEmail/identOrg/accUserEditable values found
	 * before the test ran. Read via the admin client - a test may deliberately leave
	 * identRealname/identEmail genuinely blank at some point, and the admin GET is the only
	 * one guaranteed to return them as-stored rather than placeholder-substituted (see
	 * testPatchDoesNotPersistPlaceholderSubstitutedIdentityFields()).
	 */
	private $ident_id = self::IDENT_ID;
	private $original_realname;
	private $original_email;
	private $original_org;
	private $original_editable;

	protected function setUp() : void
	{
		$account = $this->getAccountJsonWithClient($this->adminClient(), $this->organizerLid(), $this->ident_id);
		$this->original_realname = $account['identRealname'] ?? null;
		$this->original_email = $account['identEmail'] ?? null;
		$this->original_org = $account['identOrg'] ?? null;
		$this->original_editable = $account['accUserEditable'] ?? null;
	}

	protected function tearDown() : void
	{
		// admin write bypasses the ownership/accUserEditable check entirely, so this always
		// succeeds regardless of what a test left behind
		$this->adminClient()->patch($this->identityUrl($this->organizerLid(), $this->ident_id), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::BODY => $this->jsonBody(array_filter([
				'identRealname' => $this->original_realname,
				'identEmail' => $this->original_email,
				'identOrg' => $this->original_org,
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

	/**
	 * A genuinely blank identRealname/identEmail is transparently substituted with the
	 * CURRENT VIEWER's own account_fullname/account_email on every read
	 * (Credentials::from_session(), by design) - so each user of a shared/multi-user identity
	 * sees their own name, not whoever's it happened to be last. PATCH must NOT let that
	 * substituted display value leak into storage: patching a completely unrelated field
	 * while identRealname/identEmail are genuinely blank must leave them blank in the
	 * database, not permanently bake in the patching user's own name/email (see
	 * [[project-mail-patch-identity-placeholder-bug]] / commit 550178237d).
	 *
	 * Verified by having a DIFFERENT authenticated viewer (admin) read the same identity
	 * afterwards: if the bug were present, the organizer's PATCH would have persisted the
	 * organizer's own substituted name/email into storage, and admin's GET would incorrectly
	 * show the ORGANIZER's name/email too, instead of admin's own.
	 */
	public function testPatchDoesNotPersistPlaceholderSubstitutedIdentityFields()
	{
		// blank both fields as admin (bypasses ownership/accUserEditable checks) - this is
		// the state the shared "Everyone" identity is meant to be in
		$blank = $this->adminClient()->patch($this->identityUrl($this->organizerLid(), $this->ident_id), [
			RequestOptions::HEADERS => $this->jsonHeaders(),
			RequestOptions::BODY => $this->jsonBody(['identRealname' => '', 'identEmail' => '']),
		]);
		$this->assertHttpStatus(204, $blank, 'admin blanking identRealname/identEmail');
		$this->setAccUserEditable($this->ident_id, true);

		// captured for the trailing sanity check below - NOT used to gate the actual
		// regression check, since whether it's substituted with a real name or stays blank
		// itself (see below) doesn't affect the merge-base/persistence bug this test targets
		$asOrganizerBefore = $this->getAccountJson($this->organizerLid(), $this->ident_id);

		// the account owner patches a COMPLETELY unrelated field - must not touch realname/email
		$response = $this->patchAccount($this->organizerLid(), $this->organizerLid(), $this->ident_id,
			['identOrg' => 'PHPUnit Regression Org']);
		$this->assertHttpStatus(204, $response, 'patching an unrelated field');

		// a DIFFERENT viewer (admin) reads the SAME identity - admin viewing ANOTHER user's
		// identity never gets placeholder substitution (see getAccountJsonWithClient()'s
		// docblock), so it must show the RAW stored value, i.e. still genuinely blank. This is
		// the actual regression check, and it holds regardless of whether the organizer's own
		// account happens to have a fullname/email set - see the trailing sanity check below.
		$asAdmin = $this->getAccountJsonWithClient($this->adminClient(), $this->organizerLid(), $this->ident_id);
		$this->assertSame('PHPUnit Regression Org', $asAdmin['identOrg'], 'the actually-patched field must round-trip');
		$this->assertSame('', $asAdmin['identRealname'],
			'admin viewing another user\'s identity must see the RAW stored (blank) value - the patch must not have persisted the organizer\'s substituted name into storage');
		$this->assertSame('', $asAdmin['identEmail'],
			'admin viewing another user\'s identity must see the RAW stored (blank) value - the patch must not have persisted the organizer\'s substituted email into storage');

		// sanity check, informational only (does not gate the regression check above, which
		// already ran and passed): if the organizer's account has a real fullname/email set,
		// their OWN earlier GET should have shown it substituted in rather than blank - proving
		// the substitution mechanism was actually exercised here, not vacuously blank-in/
		// blank-out. Skipped on an environment (eg. a freshly seeded CI account) where the
		// organizer has neither set - there is nothing to observe there, but the bug this test
		// targets wouldn't be observable in that case either (a blank substituted value looks
		// identical to a blank raw value), so it's not a gap in the check above.
		if ($asOrganizerBefore['identRealname'] === '' && $asOrganizerBefore['identEmail'] === '')
		{
			$this->markTestSkipped('organizer account has no account_fullname/account_email '.
				'set in this environment - cannot demonstrate the substitution itself, though '.
				'the core persistence check above already ran and passed');
		}
	}
}
