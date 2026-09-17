<?php
/**
 * EGroupware Api: Test Api\Mail\Jmap\Mailbox::listAllPaths()
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Jmap;

use EGroupware\Api\Jmap\Base;

/**
 * Regression coverage for mail_acl.inc.php's folder search (ticket #124351/#124711 follow-up):
 * Compose::ajax_searchFolder() relies on Api\Mail::getFolderObjects(), which ultimately calls
 * Horde_Imap_Client_Socket::list(Subscribed)Mailboxes() - a real raw-IMAP call, unreachable for a
 * JMAP-only account like Stalwart (confirmed live: an empty result for every search except an
 * exact "INBOX" match). listAllPaths() is the JMAP-native replacement Imap\Jmap::
 * listMailboxPaths() delegates to.
 *
 * Same fake-session approach as MailboxFolderResolutionTest (this class's own jmapCall() never
 * touches a real HTTP/JMAP connection - a real one 401s even for CLI-authenticated PHPUnit runs,
 * confirmed live against this project's own Stalwart test account, acc_id=1).
 */
class MailboxListAllPathsTest extends \PHPUnit\Framework\TestCase
{
	private function fakeSession(string $accountId, array $mailboxes) : Base
	{
		return new class($accountId, $mailboxes) extends Base {
			public array $calls = [];
			public function __construct(public string $accountId, private array $mailboxes) {}
			public function jmapCall(array $methodCalls, $using=null, bool $emulate=false)
			{
				$this->calls[] = $methodCalls;
				return ['methodResponses' => [
					['Mailbox/query', ['ids' => array_column($this->mailboxes, 'id')], '0'],
					['Mailbox/get', ['list' => $this->mailboxes], '1'],
				]];
			}
		};
	}

	public function testFlatListOfTopLevelMailboxes()
	{
		$session = $this->fakeSession('acc1', [
			['id' => 'inbox-id', 'name' => 'Inbox', 'parentId' => null, 'role' => 'inbox'],
			['id' => 'trash-id', 'name' => 'Papierkorb', 'parentId' => null, 'role' => 'trash'],
			['id' => 'custom-id', 'name' => 'Kunden', 'parentId' => null, 'role' => null],
		]);
		$mailbox = new Mailbox($session);

		$paths = $mailbox->listAllPaths();

		$this->assertSame([
			'INBOX' => 'INBOX',
			'Papierkorb' => 'Trash',
			'Kunden' => 'Kunden',
		], $paths);
	}

	/**
	 * A role-identified folder (eg. Trash) gets its TRANSLATED label even when the JMAP server's
	 * own Mailbox.name is already a custom/localized string - mirrors buildMailboxPaths()'s own
	 * role-takes-priority-over-name rule for the label (the untranslated 'name' is still used for
	 * the 'path', so the value sent back on selection is stable regardless of UI language).
	 */
	public function testRoleLabelOverridesCustomNameForTranslation()
	{
		$session = $this->fakeSession('acc1', [
			['id' => 'trash-id', 'name' => 'Papierkorb', 'parentId' => null, 'role' => 'trash'],
		]);
		$mailbox = new Mailbox($session);

		$paths = $mailbox->listAllPaths();

		$this->assertSame('Trash', $paths['Papierkorb'], 'label must be the translated role name, not the raw JMAP name');
	}

	public function testNestedMailboxPathsAndLabelsAreSlashJoinedFromParentChain()
	{
		$session = $this->fakeSession('acc1', [
			['id' => 'inbox-id', 'name' => 'Inbox', 'parentId' => null, 'role' => 'inbox'],
			['id' => 'sub-id', 'name' => 'Projects', 'parentId' => 'inbox-id', 'role' => null],
			['id' => 'subsub-id', 'name' => '2026', 'parentId' => 'sub-id', 'role' => null],
		]);
		$mailbox = new Mailbox($session);

		$paths = $mailbox->listAllPaths();

		$this->assertArrayHasKey('INBOX/Projects/2026', $paths);
		$this->assertSame('INBOX/Projects/2026', $paths['INBOX/Projects/2026']);
	}

	/**
	 * Parent resolution must work regardless of list order - a child can appear before its own
	 * parent in the flat Mailbox/get response.
	 */
	public function testResolvesParentEvenWhenChildAppearsFirstInTheList()
	{
		$session = $this->fakeSession('acc1', [
			['id' => 'sub-id', 'name' => 'Sub', 'parentId' => 'inbox-id', 'role' => null],
			['id' => 'inbox-id', 'name' => 'Inbox', 'parentId' => null, 'role' => 'inbox'],
		]);
		$mailbox = new Mailbox($session);

		$paths = $mailbox->listAllPaths();

		$this->assertArrayHasKey('INBOX/Sub', $paths);
	}

	public function testUsesExplicitAccountIdOverTheSessionsOwnDefault()
	{
		$session = $this->fakeSession('session-default-acc', []);
		$mailbox = new Mailbox($session);

		$mailbox->listAllPaths('explicit-acc');

		$this->assertSame('explicit-acc', $session->calls[0][0][1]['accountId']);
		$this->assertSame('explicit-acc', $session->calls[0][1][1]['accountId']);
	}

	public function testEmptyAccountReturnsEmptyArray()
	{
		$session = $this->fakeSession('acc1', []);
		$mailbox = new Mailbox($session);

		$this->assertSame([], $mailbox->listAllPaths());
	}
}
