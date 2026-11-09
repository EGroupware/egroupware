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

	/**
	 * Three levels deep (INBOX -> Archives -> Old), needed to actually distinguish a one-level
	 * ('%') match from an any-depth ('*') one - mailboxes()'s own 2-level tree can't tell the two
	 * apart, since there's only ever one possible child either way.
	 */
	private function mailboxesWithGrandchild() : array
	{
		return array_merge($this->mailboxes(), [
			['id' => 'old-id', 'name' => 'Old', 'parentId' => 'archive-id', 'role' => null,
				'isSubscribed' => false, 'totalEmails' => 1, 'unreadEmails' => 0],
		]);
	}

	/**
	 * getDelimiter() (Mail\Imap::getDelimiter()) used to call $this->getNamespaces() directly -
	 * Horde_Imap_Client_Base's own raw NAMESPACE command, a different method than the
	 * already-guarded getNameSpaceArray() - so it fell through to a real IMAP handshake against
	 * the JMAP(S) endpoint. mail_acl::getSubfolders() (the recursive-ACL-grant/revoke long task's
	 * folder-enumeration step) calls it directly, so a JMAP account crashed with "Error when
	 * communicating with the mail server" the moment anyone tried to recursively grant/revoke
	 * ACL rights (found live 2026-11-09, a sibling report to ticket #126501).
	 */
	public function testGetDelimiterReturnsSlashWithoutRawImapFallback()
	{
		$jmap = $this->jmapWithClient($this->fakeClient($this->mailboxes()));

		$this->assertSame('/', $jmap->getDelimiter());
		$this->assertSame('/', $jmap->getDelimiter('shared'));
		// never even touched jmapCall(), let alone a raw IMAP socket - purely hardcoded
		$this->assertSame([], $jmap->jmapClient()->calls);
	}

	/**
	 * _connect()'s last-resort rescue (see its own docblock) for every remaining/future
	 * not-yet-JMAP-native raw-IMAP fallthrough - a configured port that doesn't look like real
	 * IMAP (eg. 443, this account's actual JMAP(S) port) gets rescued onto 993; one that already
	 * is a real IMAP port (993 itself, or 143/STARTTLS) is left alone, unrescued.
	 */
	public function testRescuePortForSubstitutesOnlyForNonImapPorts()
	{
		$this->assertSame(993, Jmap::rescuePortFor(443));
		$this->assertSame(993, Jmap::rescuePortFor(8080));
		$this->assertNull(Jmap::rescuePortFor(993));
		$this->assertNull(Jmap::rescuePortFor(143));
	}

	public function testListMailboxesWildcardReturnsEveryPathWithSubscribedAttribute()
	{
		$jmap = $this->jmapWithClient($this->fakeClient($this->mailboxes()));

		$ret = $jmap->listMailboxes('*');

		$this->assertSame(['INBOX', 'INBOX/Archives'], array_keys($ret));
		$this->assertSame([Jmap::SUBSCRIBED_ATTRIBUTE, '\HasChildren'], $ret['INBOX']['attributes']);
		$this->assertSame(['\HasNoChildren'], $ret['INBOX/Archives']['attributes']);
		$this->assertSame('/', $ret['INBOX']['delimiter']);
	}

	/**
	 * getSubfolders()/getMailBoxesRecursive() (see listMailboxes()'s own docblock) only ever
	 * recurses into a mailbox's children when its own '\HasChildren' attribute is set - without
	 * it a JMAP account's recursive ACL grant/revoke silently stopped after the root folder even
	 * though real subfolders existed (found live 2026-11-09).
	 */
	public function testListMailboxesMarksParentHasChildrenAndLeafHasNoChildren()
	{
		$jmap = $this->jmapWithClient($this->fakeClient($this->mailboxes()));

		$ret = $jmap->listMailboxes('*');

		$this->assertContains('\HasChildren', $ret['INBOX']['attributes']);
		$this->assertNotContains('\HasNoChildren', $ret['INBOX']['attributes']);
		$this->assertContains('\HasNoChildren', $ret['INBOX/Archives']['attributes']);
		$this->assertNotContains('\HasChildren', $ret['INBOX/Archives']['attributes']);
	}

	/**
	 * A trailing '%' must match only the IMMEDIATE children of the prefix, same as real IMAP
	 * LIST's one-level wildcard - Mail\Imap::getMailBoxesRecursive() relies on this exact
	 * semantics (restriction_search=2 in getMailboxes()) to walk the tree one level at a time.
	 * Treating '%' the same as '*' (any depth) would pull grandchildren in too early.
	 */
	public function testListMailboxesOneLevelWildcardExcludesGrandchildren()
	{
		$jmap = $this->jmapWithClient($this->fakeClient($this->mailboxesWithGrandchild()));

		$ret = $jmap->listMailboxes('INBOX/%');

		$this->assertSame(['INBOX/Archives'], array_keys($ret));
	}

	public function testListMailboxesAnyDepthWildcardIncludesGrandchildren()
	{
		$jmap = $this->jmapWithClient($this->fakeClient($this->mailboxesWithGrandchild()));

		$ret = $jmap->listMailboxes('INBOX/*');

		$this->assertSame(['INBOX/Archives', 'INBOX/Archives/Old'], array_keys($ret));
	}

	/**
	 * End-to-end regression test for the live bug (2026-11-09): recursively granting/revoking
	 * mail ACL rights on a JMAP account silently only ever touched the root folder, even though
	 * real subfolders existed - mail_acl::getSubfolders() calls exactly this inherited
	 * (not overridden) Mail\Imap method, which walks the tree via listMailboxes() one level at a
	 * time using both the '\HasChildren' attribute and the '%'-vs-'*' distinction fixed above;
	 * either one alone being wrong silently truncates the walk to the root with no error at all.
	 */
	public function testGetMailBoxesRecursiveDescendsIntoJmapChildren()
	{
		$jmap = $this->jmapWithClient($this->fakeClient($this->mailboxesWithGrandchild()));

		$folders = $jmap->getMailBoxesRecursive('INBOX', '/', '');
		sort($folders);

		$this->assertSame(['INBOX', 'INBOX/Archives', 'INBOX/Archives/Old'], $folders);
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
