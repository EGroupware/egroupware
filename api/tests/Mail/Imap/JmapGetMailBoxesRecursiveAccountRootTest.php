<?php
/**
 * EGroupware Api: Mail\Imap\Jmap::getMailBoxesRecursive() expands the account root into every
 * top-level sibling of INBOX
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Imap;

use EGroupware\Api\Jmap\Base;

require_once realpath(__DIR__.'/../../AppTest.php');

/**
 * Nordenham/Birgit, 2026-11-25: mail_acl::edit()'s "recursive / incl. subfolders" option, starting
 * from the account root (Jmap::getUserMailboxString()'s own 'INBOX' sentinel), only ever touched
 * INBOX itself on a Stalwart account - Sent/Trash/Drafts/Junk/Templates/... are TOP-LEVEL siblings
 * of INBOX there (unlike classic/Dovecot, where they were nested under INBOX, eg. INBOX.Sent, so
 * recursing from INBOX already covered them). The same getSubfolders() call backs
 * mail_acl::ajax_folders()'s folder-picker too, so this fixes both: the ACL "apply to subfolders"
 * action, and the folder picker listing nothing below the account root.
 */
class JmapGetMailBoxesRecursiveAccountRootTest extends \EGroupware\Api\AppTest
{
	private const TREE = [
		['id' => 'm1', 'parentId' => null, 'role' => 'inbox', 'name' => 'INBOX'],
		['id' => 'm2', 'parentId' => null, 'role' => 'sent', 'name' => 'Sent'],
		['id' => 'm3', 'parentId' => null, 'role' => 'trash', 'name' => 'Trash'],
	];

	/** @return Jmap a real instance with jmapClient() mocked to answer every Mailbox/query+get from a fixed stub tree */
	private function jmapWithTree(array $mailboxes) : Jmap
	{
		$client = new class($mailboxes) extends Base
		{
			public string $accountId = 'acc1';
			public function __construct(public array $mailboxes)
			{
			}
			public function jmapCall(array $methodCalls) : array
			{
				// every call this code makes is one Mailbox/query (ids) + one Mailbox/get (list) -
				// the stub ignores the actual filter/ids arguments and always hands back the same
				// fixed tree, same as a real Mailbox/query+get pair scoped to one accountId would
				return ['methodResponses' => [
					['Mailbox/query', ['ids' => array_column($this->mailboxes, 'id')], '0'],
					['Mailbox/get', ['list' => $this->mailboxes], '1'],
				]];
			}
		};
		$mock = $this->getMockBuilder(Jmap::class)
			->disableOriginalConstructor()
			->onlyMethods(['jmapClient'])
			->getMock();
		$mock->method('jmapClient')->willReturn($client);
		return $mock;
	}

	public function testAccountRootExpandsToEveryTopLevelSibling()
	{
		$jmap = $this->jmapWithTree(self::TREE);

		$result = $jmap->getMailBoxesRecursive('INBOX', '/', '');

		sort($result);
		$this->assertSame(['INBOX', 'Sent', 'Trash'], $result,
			"recursing from the account root must cover every top-level mailbox, not just INBOX itself");
	}

	/** A deeper, explicitly-named mailbox (not the account root) is completely unaffected. */
	public function testNonRootCallIsUnaffected()
	{
		$jmap = $this->jmapWithTree([
			['id' => 'm2', 'parentId' => null, 'role' => 'sent', 'name' => 'Sent'],
		]);

		$result = $jmap->getMailBoxesRecursive('Sent', '/', '');

		$this->assertSame(['Sent'], $result);
	}

	/** A recursive sub-call for 'INBOX' at a deeper level (reclevel>0) must never re-trigger the expansion (would otherwise loop). */
	public function testRecursiveSubCallAtNonZeroLevelIsUnaffectedEvenForInbox()
	{
		$jmap = $this->jmapWithTree([
			['id' => 'm1', 'parentId' => null, 'role' => 'inbox', 'name' => 'INBOX'],
		]);

		$result = $jmap->getMailBoxesRecursive('INBOX', '/', '', 1);

		$this->assertSame(['INBOX'], $result);
	}
}
