<?php
/**
 * EGroupware Api: Test Mail\Smtp\Stalwart's group Stalwart-sync-mode storage
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once realpath(__DIR__.'/../LoggedInTest.php');

use EGroupware\Api;
use EGroupware\Api\Mail;

/**
 * groupSyncMode()/saveGroupSyncMode() - the explicit per-group Stalwart-sync-mode storage added
 * for the "configurable Stalwart group-sync modes" feature (none/acl/list/mailbox). Pure DB logic
 * (one row in egw_mailaccounts, mail_type=TYPE_GROUP_SYNC), no JMAP/network calls involved, so this
 * needs no live Stalwart server - same "no live server needed, just DB" shape as
 * AccountSmtpTransportTest.
 *
 * Uses a group account_id clearly outside the real account range, cleaned up in tearDown() so
 * repeat runs never see a stale row from a previous run.
 */
class StalwartGroupSyncModeTest extends Api\LoggedInTest
{
	const TEST_ACCOUNT_ID = -987654;

	private Mail\Smtp\Stalwart $stalwart;

	protected function setUp() : void
	{
		parent::setUp();
		$this->stalwart = new Mail\Smtp\Stalwart();
	}

	/**
	 * saveGroupSyncMode() is intentionally protected (internal-only, only ever reached through the
	 * normal group-save hook dispatch) - same Reflection convention already used elsewhere in this
	 * test suite (eg. JmapIssetTest) to exercise a protected method directly without widening its
	 * real visibility just for testability.
	 */
	private function saveGroupSyncMode(int $account_id, string $mode) : void
	{
		$method = new \ReflectionMethod(Mail\Smtp\Stalwart::class, 'saveGroupSyncMode');
		$method->setAccessible(true);
		$method->invoke($this->stalwart, $account_id, $mode);
	}

	protected function tearDown() : void
	{
		$GLOBALS['egw']->db->delete('egw_mailaccounts', [
			'account_id' => self::TEST_ACCOUNT_ID,
		], __LINE__, __FILE__, 'api');
		parent::tearDown();
	}

	public function testDefaultsToAclWhenNoEmailAndNoStoredRow()
	{
		$this->assertSame(Mail\Smtp\Stalwart::GROUP_SYNC_ACL,
			$this->stalwart->groupSyncMode(self::TEST_ACCOUNT_ID, null));
	}

	public function testDefaultsToListWhenEmailSetAndNoStoredRow()
	{
		$this->assertSame(Mail\Smtp\Stalwart::GROUP_SYNC_LIST,
			$this->stalwart->groupSyncMode(self::TEST_ACCOUNT_ID, 'test-group@example.org'));
	}

	public function testDefaultIsPersistedNotJustComputed()
	{
		// first read with an email present computes+persists 'list' ...
		$this->stalwart->groupSyncMode(self::TEST_ACCOUNT_ID, 'test-group@example.org');

		// ... a SECOND read, even with no email passed at all, must still return the now-stored
		// 'list' value - it must not silently re-derive 'acl' from the (here, absent) email param
		$this->assertSame(Mail\Smtp\Stalwart::GROUP_SYNC_LIST,
			$this->stalwart->groupSyncMode(self::TEST_ACCOUNT_ID));
	}

	public function testSaveThenReadRoundTripsEveryMode()
	{
		foreach ([
			Mail\Smtp\Stalwart::GROUP_SYNC_NONE,
			Mail\Smtp\Stalwart::GROUP_SYNC_ACL,
			Mail\Smtp\Stalwart::GROUP_SYNC_LIST,
			Mail\Smtp\Stalwart::GROUP_SYNC_MAILBOX,
		] as $mode)
		{
			$this->saveGroupSyncMode(self::TEST_ACCOUNT_ID, $mode);
			$this->assertSame($mode, $this->stalwart->groupSyncMode(self::TEST_ACCOUNT_ID));
		}
	}

	public function testSavingAgainReplacesThePreviousValue()
	{
		$this->saveGroupSyncMode(self::TEST_ACCOUNT_ID, Mail\Smtp\Stalwart::GROUP_SYNC_LIST);
		$this->saveGroupSyncMode(self::TEST_ACCOUNT_ID, Mail\Smtp\Stalwart::GROUP_SYNC_NONE);

		$this->assertSame(Mail\Smtp\Stalwart::GROUP_SYNC_NONE,
			$this->stalwart->groupSyncMode(self::TEST_ACCOUNT_ID));

		// exactly one row for this (account_id, mail_type) pair - insert() must REPLACE, not
		// accumulate duplicate rows
		$rows = iterator_to_array($GLOBALS['egw']->db->select('egw_mailaccounts', '*', [
			'account_id' => self::TEST_ACCOUNT_ID,
			'mail_type' => Mail\Smtp\Sql::TYPE_GROUP_SYNC,
		], __LINE__, __FILE__, false, '', 'api'));
		$this->assertCount(1, $rows);
	}
}
