<?php
/**
 * EGroupware Api: Mail\Imap\Jmap listMailboxes()/status()/openMailbox()/createMailbox()/
 * renameMailbox()/deleteMailbox() - JMAP-native, no raw IMAP fallthrough
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
 * doc/ai/projects/mail-zpush-jmap-folders.md: the 7 remaining Imap\Jmap folder-management
 * fallthrough gaps found via mail_zpush (ActiveSync)'s AlterPingChanges() -> Api\Mail::
 * getFolderStatus() -> icServer->getMailboxes()/getStatus() chain, which attempted a real raw
 * IMAP handshake against a JMAP-only (Stalwart) endpoint and broke ActiveSync entirely right
 * after migrating egroupware.org to Stalwart (see that doc's "How this was found"). Covers the 6
 * methods that needed a real JMAP-native implementation (mailboxExist() needed none - it's an
 * EGroupware-only wrapper around currentMailbox()/openMailbox(), both already safe once
 * openMailbox() is).
 *
 * Same constructor-skipping mock + fake-session approach as JmapSubscriptionNoRawImapFallbackTest
 * / MailboxListAllPathsTest (jmapClient() is stubbed to return a fake Base subclass; this class's
 * own jmapCall() never touches a real HTTP/JMAP connection).
 */
class JmapFolderOpsNoRawImapFallbackTest extends \EGroupware\Api\AppTest
{
	private function jmapWithClient(Base $fakeClient) : Jmap
	{
		$mock = $this->getMockBuilder(Jmap::class)
			->disableOriginalConstructor()
			->onlyMethods(['jmapClient'])
			->getMock();
		$mock->method('jmapClient')->willReturn($fakeClient);
		return $mock;
	}

	private function fakeClient(array $mailboxes, array $setResult=[]) : Base
	{
		return new class($mailboxes, $setResult) extends Base {
			public string $accountId = 'acc1';
			public array $calls = [];
			public function __construct(private array $mailboxes, private array $setResult) {}
			public function getMailboxId(string $folder, ?string $accountId=null) : ?string
			{
				// resolves a full '/'-delimited path by walking the parentId chain, matching
				// Jmap\Mailbox::getMailboxId()'s real per-segment behaviour closely enough for
				// these tests' fixtures (flat 2-level trees)
				$parentId = null;
				$id = null;
				foreach (explode('/', $folder) as $part)
				{
					$id = null;
					foreach ($this->mailboxes as $mailbox)
					{
						$name = ($mailbox['role'] ?? null) === 'inbox' ? 'INBOX' : $mailbox['name'];
						if ($name === $part && ($mailbox['parentId'] ?? null) === $parentId)
						{
							$id = $mailbox['id'];
							break;
						}
					}
					if ($id === null)
					{
						return null;
					}
					$parentId = $id;
				}
				return $id;
			}
			public function jmapCall(array $methodCalls, $using=null, bool $emulate=false)
			{
				$this->calls[] = $methodCalls;
				switch ($methodCalls[0][0])
				{
					case 'Mailbox/query':
						// listAll()'s batched query+get - every mailbox, unfiltered
						return ['methodResponses' => [
							['Mailbox/query', ['ids' => array_column($this->mailboxes, 'id')], '0'],
							['Mailbox/get', ['list' => $this->mailboxes], '1'],
						]];

					case 'Mailbox/get':
						// status()'s single-mailbox-by-id lookup
						$id = $methodCalls[0][1]['ids'][0];
						$found = array_values(array_filter($this->mailboxes, static fn($m) => $m['id'] === $id));
						return ['methodResponses' => [
							['Mailbox/get', ['list' => $found], '0'],
						]];

					default: // Mailbox/set - create/update/destroy
						$args = $methodCalls[0][1];
						$key = array_key_first($args['create'] ?? $args['update'] ?? []) ?? ($args['destroy'][0] ?? null);
						$resultKey = isset($args['create']) ? 'created' : (isset($args['update']) ? 'updated' : 'destroyed');
						$failKey = isset($args['create']) ? 'notCreated' : (isset($args['update']) ? 'notUpdated' : 'notDestroyed');
						if (isset($this->setResult[$failKey]))
						{
							return ['methodResponses' => [['Mailbox/set', [$resultKey => [], $failKey => $this->setResult[$failKey]], '0']]];
						}
						return ['methodResponses' => [['Mailbox/set', [
							$resultKey => $resultKey === 'destroyed' ? [$key] : [$key => ['id' => 'new-id']],
						], '0']]];
				}
			}
		};
	}

	private function mailboxes() : array
	{
		return [
			['id' => 'inbox-id', 'name' => 'Inbox', 'parentId' => null, 'role' => 'inbox',
				'isSubscribed' => true, 'totalEmails' => 42, 'unreadEmails' => 3],
			['id' => 'archive-id', 'name' => 'Archives', 'parentId' => 'inbox-id', 'role' => null,
				'isSubscribed' => false, 'totalEmails' => 5, 'unreadEmails' => 0],
		];
	}

	public function testListMailboxesWildcardReturnsEveryPathWithSubscribedAttribute()
	{
		$jmap = $this->jmapWithClient($this->fakeClient($this->mailboxes()));

		$ret = $jmap->listMailboxes('*');

		$this->assertSame(['INBOX', 'INBOX/Archives'], array_keys($ret));
		$this->assertSame([Jmap::SUBSCRIBED_ATTRIBUTE], $ret['INBOX']['attributes']);
		$this->assertSame([], $ret['INBOX/Archives']['attributes']);
		$this->assertSame('/', $ret['INBOX']['delimiter']);
	}

	public function testListMailboxesExactPathReturnsOnlyThatMailbox()
	{
		$jmap = $this->jmapWithClient($this->fakeClient($this->mailboxes()));

		$ret = $jmap->listMailboxes('INBOX/Archives');

		$this->assertSame(['INBOX/Archives'], array_keys($ret));
	}

	public function testStatusReturnsRealCountsAndSyntheticButStableUidvalidity()
	{
		$jmap = $this->jmapWithClient($this->fakeClient($this->mailboxes()));

		$status1 = $jmap->status('INBOX/Archives');
		$status2 = $jmap->status('INBOX/Archives');

		$this->assertSame(5, $status1['messages']);
		$this->assertSame(0, $status1['unseen']);
		$this->assertSame(0, $status1['recent']);
		$this->assertSame(6, $status1['uidnext']);
		$this->assertArrayNotHasKey('highestmodseq', $status1);
		// stable across calls - real IMAP UIDVALIDITY must never spuriously change
		$this->assertSame($status1['uidvalidity'], $status2['uidvalidity']);
	}

	public function testStatusWithHighestmodseqFlagIncludesIt()
	{
		$jmap = $this->jmapWithClient($this->fakeClient($this->mailboxes()));

		$status = $jmap->status('INBOX', \Horde_Imap_Client::STATUS_ALL | \Horde_Imap_Client::STATUS_HIGHESTMODSEQ);

		$this->assertSame(0, $status['highestmodseq']);
	}

	public function testStatusThrowsForMultipleMailboxesWithoutRawImapFallback()
	{
		$jmap = $this->jmapWithClient($this->fakeClient($this->mailboxes()));

		$this->expectException(\Horde_Imap_Client_Exception::class);
		$jmap->status(['INBOX', 'INBOX/Archives']);
	}

	public function testStatusThrowsWithoutRawImapFallbackWhenMailboxNotFound()
	{
		$jmap = $this->jmapWithClient($this->fakeClient([]));

		$this->expectException(\Horde_Imap_Client_Exception::class);
		$jmap->status('Nonexistent');
	}

	public function testOpenMailboxSucceedsSilentlyForAnExistingMailbox()
	{
		$jmap = $this->jmapWithClient($this->fakeClient($this->mailboxes()));

		$jmap->openMailbox('INBOX/Archives');
		$this->assertNull($jmap->currentMailbox());
	}

	public function testOpenMailboxThrowsWithoutRawImapFallbackWhenMailboxNotFound()
	{
		$jmap = $this->jmapWithClient($this->fakeClient([]));

		$this->expectException(\Horde_Imap_Client_Exception::class);
		$jmap->openMailbox('Nonexistent');
	}

	public function testCreateMailboxResolvesParentAndMapsSpecialUseToRole()
	{
		$client = $this->fakeClient($this->mailboxes());
		$jmap = $this->jmapWithClient($client);

		$jmap->createMailbox('INBOX/New', ['special_use' => ['\\Sent']]);

		$this->assertSame(
			['Mailbox/set', ['accountId' => 'acc1', 'create' => ['0' => ['name' => 'New', 'parentId' => 'inbox-id', 'role' => 'sent']]], '0'],
			$client->calls[0][0]
		);
	}

	public function testCreateMailboxAtTopLevelUsesNullParentId()
	{
		$client = $this->fakeClient($this->mailboxes());
		$jmap = $this->jmapWithClient($client);

		$jmap->createMailbox('TopLevel');

		$this->assertSame(
			['Mailbox/set', ['accountId' => 'acc1', 'create' => ['0' => ['name' => 'TopLevel', 'parentId' => null]]], '0'],
			$client->calls[0][0]
		);
	}

	public function testCreateMailboxThrowsWithoutRawImapFallbackWhenParentNotFound()
	{
		$jmap = $this->jmapWithClient($this->fakeClient($this->mailboxes()));

		$this->expectException(\Horde_Imap_Client_Exception::class);
		$jmap->createMailbox('Nonexistent/New');
	}

	public function testCreateMailboxThrowsWhenJmapReportsNotCreated()
	{
		$jmap = $this->jmapWithClient($this->fakeClient(
			$this->mailboxes(), ['notCreated' => ['0' => ['type' => 'invalidProperties']]]
		));

		$this->expectException(\Horde_Imap_Client_Exception::class);
		$jmap->createMailbox('TopLevel');
	}

	public function testRenameMailboxPatchesNameAndParentIdViaMailboxSet()
	{
		$client = $this->fakeClient($this->mailboxes());
		$jmap = $this->jmapWithClient($client);

		$jmap->renameMailbox('INBOX/Archives', 'Archives2024');

		$this->assertSame(
			['Mailbox/set', ['accountId' => 'acc1', 'update' => ['archive-id' => ['name' => 'Archives2024', 'parentId' => null]]], '0'],
			$client->calls[0][0]
		);
	}

	public function testRenameMailboxThrowsWithoutRawImapFallbackWhenMailboxNotFound()
	{
		$jmap = $this->jmapWithClient($this->fakeClient($this->mailboxes()));

		$this->expectException(\Horde_Imap_Client_Exception::class);
		$jmap->renameMailbox('Nonexistent', 'New');
	}

	public function testDeleteMailboxDestroysViaMailboxSet()
	{
		$client = $this->fakeClient($this->mailboxes());
		$jmap = $this->jmapWithClient($client);

		$jmap->deleteMailbox('INBOX/Archives');

		$this->assertSame(
			['Mailbox/set', ['accountId' => 'acc1', 'destroy' => ['archive-id']], '0'],
			$client->calls[0][0]
		);
	}

	public function testDeleteMailboxThrowsWithoutRawImapFallbackWhenMailboxNotFound()
	{
		$jmap = $this->jmapWithClient($this->fakeClient($this->mailboxes()));

		$this->expectException(\Horde_Imap_Client_Exception::class);
		$jmap->deleteMailbox('Nonexistent');
	}

	public function testDeleteMailboxThrowsWhenJmapReportsNotDestroyed()
	{
		$jmap = $this->jmapWithClient($this->fakeClient(
			$this->mailboxes(), ['notDestroyed' => ['archive-id' => ['type' => 'notFound']]]
		));

		$this->expectException(\Horde_Imap_Client_Exception::class);
		$jmap->deleteMailbox('INBOX/Archives');
	}
}
