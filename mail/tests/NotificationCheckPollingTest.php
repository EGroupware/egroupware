<?php
/**
 * EGroupware Mail: mail_hooks::needsNotificationCheckPolling() regression test
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once __DIR__.'/../../api/tests/LoggedInTest.php';

use EGroupware\Api\LoggedInTest;
use EGroupware\Api\Mail;

/**
 * mail_hooks::needsNotificationCheckPolling() (doc/ai/projects/push-fallback-longpoll.md's "Mail
 * notification-check polling" follow-up note) - tells notifications/js/app.ts whether it needs to
 * keep polling notifications.notifications_ajax.get_notifications() (which runs the check_notify
 * hook) even once egw.pushAvailable() is true, because neither Dovecot's nor JMAP's mail-server
 * push currently triggers the actual notify_folders check itself.
 *
 * Setup strategy: LoggedInTest boots a real session for the configured test user. This test picks
 * that user's own first mail account (Mail\Account::search(true, true) - same call
 * needsNotificationCheckPolling() itself makes) and writes/deletes a real notify_folders row for
 * it via Mail\Notifications::write()/delete(), always restoring the original value in a
 * finally-block - if the test user genuinely has no mail account configured at all, the test
 * skips rather than fabricating one, since it's specifically the (account, notify_folders)
 * relationship under test, not account creation.
 *
 * Pass criteria: returns true only once notify_folders is genuinely non-empty for at least one of
 * the user's accounts, false again once cleared - regardless of what other accounts/state happen
 * to already exist (the method must return true the moment ANY one account qualifies).
 */
class NotificationCheckPollingTest extends LoggedInTest
{
	private $acc_id;
	private $account_id;
	private $original_folders;

	protected function setUp() : void
	{
		parent::setUp();

		$this->account_id = $GLOBALS['egw_info']['user']['account_id'];
		$this->acc_id = null;
		foreach(Mail\Account::search(true, true) as $acc_id => $name)
		{
			$this->acc_id = $acc_id;
			break;
		}
		if (!$this->acc_id)
		{
			$this->markTestSkipped('Test user has no mail account configured - nothing to test against');
		}
		$this->original_folders = Mail\Notifications::read($this->acc_id)['notify_folders'] ?? [];
	}

	protected function tearDown() : void
	{
		if (isset($this->acc_id))
		{
			if (empty($this->original_folders))
			{
				Mail\Notifications::delete($this->acc_id, $this->account_id);
			}
			else
			{
				Mail\Notifications::write($this->acc_id, $this->account_id, $this->original_folders);
			}
		}
		parent::tearDown();
	}

	public function testFalseWithoutAnyNotifyFolders()
	{
		Mail\Notifications::delete($this->acc_id, $this->account_id);

		$this->assertFalse(mail_hooks::needsNotificationCheckPolling());
	}

	public function testTrueOnceAnAccountHasNotifyFoldersConfigured()
	{
		Mail\Notifications::write($this->acc_id, $this->account_id, ['INBOX']);

		$this->assertTrue(mail_hooks::needsNotificationCheckPolling());
	}
}
