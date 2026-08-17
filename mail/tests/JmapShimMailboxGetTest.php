<?php
/**
 * Test EGroupware\Mail\JmapShim's Mailbox/get and Mailbox/query "list children" mode - the
 * lazy per-level folder-tree loading pair (see doc/ai/projects/mail-folder-tree-jmap.md).
 *
 * The private listChildIds()/mailboxGetInternal()/mailboxNode()/roleFor()/canonicalPath()
 * helpers are exercised directly via ReflectionMethod against a mocked Mail\Imap connection -
 * same style JmapShimMailboxSetTest.php already uses - so no live IMAP server is needed.
 *
 * Pass criteria per test: the exact Horde_Imap_Client_Socket method(s) are called with the
 * exact expected arguments, and the returned node data has the exact expected shape/values -
 * in particular, that an explicit-ids request never falls back to a '*' full-account scan
 * (the whole point of lazy per-level loading for accounts with hundreds of folders).
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Imap;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

#[AllowMockObjectsWithoutExpectations]
class JmapShimMailboxGetTest extends \PHPUnit\Framework\TestCase
{
	private function mockImap(array $namespaces = [], array $onlyMethods = []) : Imap
	{
		$imap = $this->getMockBuilder(Imap::class)
			->disableOriginalConstructor()
			->onlyMethods(array_unique(array_merge(
				['getNameSpaceArray', 'getUserMailboxString', 'listMailboxes', 'status', '__get'],
				$onlyMethods,
			)))
			->getMock();
		$imap->method('getNameSpaceArray')->willReturn([
			'personal' => [['delimiter' => $namespaces['personal'][0]['delimiter'] ?? '.']],
			'others' => [['delimiter' => $namespaces['others'][0]['delimiter'] ?? '\\']],
		]);
		// no acc_folder_* configured unless a test explicitly overrides __get()
		$imap->method('__get')->willReturn(null);
		return $imap;
	}

	private function invokePrivate(string $method, array $args)
	{
		$reflection = new \ReflectionMethod(JmapShim::class, $method);
		$reflection->setAccessible(true);
		return $reflection->invoke(null, ...$args);
	}

	public function testCanonicalPathRoundtripsWithHordeMailbox()
	{
		$imap = $this->mockImap(['personal' => [['delimiter' => '.']]]);

		$this->assertSame('INBOX/Project', JmapShim::canonicalPath($imap, 'INBOX.Project'));
		$this->assertSame('INBOX', JmapShim::canonicalPath($imap, 'INBOX'));
		$this->assertSame('INBOX.Project', JmapShim::hordeMailbox($imap, 'INBOX/Project'));
	}

	public function testListChildIdsTopLevel()
	{
		$imap = $this->mockImap(['personal' => [['delimiter' => '.']]]);
		$imap->expects($this->once())->method('listMailboxes')
			->with('%', \Horde_Imap_Client::MBOX_ALL_SUBSCRIBED, ['children' => true])
			->willReturn(['INBOX' => [], 'Sent' => [], 'Trash' => []]);

		$ids = $this->invokePrivate('listChildIds', [$imap, '']);

		$this->assertSame([
			base64_encode('INBOX'), base64_encode('Sent'), base64_encode('Trash'),
		], $ids);
	}

	public function testListChildIdsUnderParent()
	{
		$imap = $this->mockImap(['personal' => [['delimiter' => '.']]]);
		$imap->expects($this->once())->method('listMailboxes')
			->with('INBOX.%', \Horde_Imap_Client::MBOX_ALL_SUBSCRIBED, ['children' => true])
			->willReturn(['INBOX.Project' => [], 'INBOX.Archive' => []]);

		$ids = $this->invokePrivate('listChildIds', [$imap, 'INBOX']);

		$this->assertSame([base64_encode('INBOX/Project'), base64_encode('INBOX/Archive')], $ids);
	}

	/**
	 * The whole point of lazy per-level loading: fetching details for a small explicit set of
	 * ids must look every one of them up in ONE batched LIST(+STATUS) call, exact (non-wildcard)
	 * names, never a '*' full-account scan (that would defeat the point for accounts with
	 * hundreds of folders) - and, since the fix, never a separate status() round-trip per
	 * mailbox either. The previous per-id loop (one listMailboxes() + one status() call PER
	 * requested mailbox) could take many sequential IMAP round-trips for a single folder-tree
	 * level, slow enough on a real account with dozens of siblings to time out the whole request
	 * and silently fall back to the classic ajax_foldertree path with no visible error at all.
	 */
	public function testMailboxGetInternalExplicitIdsUsesOneBatchedListCall()
	{
		$imap = $this->mockImap(['personal' => [['delimiter' => '.']]]);
		$imap->expects($this->once())->method('listMailboxes')
			->with(['INBOX', 'INBOX.Sent'], \Horde_Imap_Client::MBOX_ALL_SUBSCRIBED, [
				'attributes' => true, 'special_use' => true, 'children' => true,
				'status' => \Horde_Imap_Client::STATUS_MESSAGES | \Horde_Imap_Client::STATUS_UNSEEN,
			])
			->willReturn([
				'INBOX' => ['attributes' => ['\\subscribed'], 'status' => ['messages' => 3, 'unseen' => 1]],
				'INBOX.Sent' => ['attributes' => ['\\subscribed', '\\sent'], 'status' => ['messages' => 0, 'unseen' => 0]],
			]);
		$imap->expects($this->never())->method('status');

		$result = $this->invokePrivate('mailboxGetInternal', [
			$imap, [base64_encode('INBOX'), base64_encode('INBOX/Sent')], null,
		]);

		$this->assertCount(2, $result['list']);
		$this->assertSame([], $result['notFound']);
		$this->assertSame(3, $result['list'][0]['totalEmails']);
		$this->assertSame(1, $result['list'][0]['unreadEmails']);
	}

	public function testMailboxGetInternalReportsNotFound()
	{
		$imap = $this->mockImap();
		$imap->method('listMailboxes')->willReturn([]);

		$result = $this->invokePrivate('mailboxGetInternal', [$imap, [base64_encode('INBOX/Gone')], null]);

		$this->assertSame([], $result['list']);
		$this->assertSame([base64_encode('INBOX/Gone')], $result['notFound']);
	}

	public function testMailboxGetInternalIdsNullScansWholeAccount()
	{
		$imap = $this->mockImap();
		$imap->expects($this->once())->method('listMailboxes')
			->with('*', \Horde_Imap_Client::MBOX_ALL_SUBSCRIBED, [
				'attributes' => true, 'special_use' => true, 'children' => true,
				'status' => \Horde_Imap_Client::STATUS_MESSAGES | \Horde_Imap_Client::STATUS_UNSEEN,
			])
			->willReturn([
				'INBOX' => ['attributes' => ['\\subscribed'], 'status' => ['messages' => 0, 'unseen' => 0]],
				'INBOX.Sent' => ['attributes' => ['\\subscribed', '\\sent'], 'status' => ['messages' => 0, 'unseen' => 0]],
			]);
		$imap->expects($this->never())->method('status');

		$result = $this->invokePrivate('mailboxGetInternal', [$imap, null, null]);

		$this->assertCount(2, $result['list']);
	}

	public function testMailboxNodeShapeAndSubscribed()
	{
		$imap = $this->mockImap(['personal' => [['delimiter' => '.']]]);
		$imap->method('status')->willReturn(['messages' => 5, 'unseen' => 2]);

		$node = $this->invokePrivate('mailboxNode', [$imap, 'INBOX.Project', ['\\subscribed']]);

		$this->assertSame(base64_encode('INBOX/Project'), $node['id']);
		$this->assertSame('Project', $node['name']);
		$this->assertSame(base64_encode('INBOX'), $node['parentId']);
		$this->assertTrue($node['isSubscribed']);
		$this->assertSame(5, $node['totalEmails']);
		$this->assertSame(2, $node['unreadEmails']);
	}

	public function testMailboxNodeUnsubscribedAndTopLevelHasNullParent()
	{
		$imap = $this->mockImap();
		$imap->method('status')->willReturn(['messages' => 0, 'unseen' => 0]);

		$node = $this->invokePrivate('mailboxNode', [$imap, 'INBOX', []]);

		$this->assertSame('INBOX', $node['name']);
		$this->assertNull($node['parentId']);
		$this->assertFalse($node['isSubscribed']);
	}

	public function testMailboxNodeStatusFailureDefaultsToZeroCounts()
	{
		$imap = $this->mockImap();
		$imap->method('status')->willThrowException(new \Horde_Imap_Client_Exception('no select'));

		$node = $this->invokePrivate('mailboxNode', [$imap, 'INBOX.Noselect', []]);

		$this->assertSame(0, $node['totalEmails']);
		$this->assertSame(0, $node['unreadEmails']);
	}

	public function testMailboxNodeHasChildrenFromAttributes()
	{
		$imap = $this->mockImap();
		$imap->method('status')->willReturn(['messages' => 0, 'unseen' => 0]);

		$this->assertTrue($this->invokePrivate('mailboxNode', [$imap, 'INBOX', ['\\haschildren']])['hasChildren']);
		$this->assertFalse($this->invokePrivate('mailboxNode', [$imap, 'INBOX', ['\\hasnochildren']])['hasChildren']);
		// neither attribute present (server without LIST-EXTENDED) -> assume expandable;
		// Et2Tree's own lazy-load self-corrects on an empty first expand
		$this->assertTrue($this->invokePrivate('mailboxNode', [$imap, 'INBOX', []])['hasChildren']);
	}

	public function testRoleForInbox()
	{
		$imap = $this->mockImap();
		$this->assertSame('inbox', JmapShim::roleFor($imap, 'INBOX', []));
		$this->assertSame('inbox', JmapShim::roleFor($imap, 'inbox', []));
	}

	public function testRoleForSpecialUseAttribute()
	{
		$imap = $this->mockImap();
		$this->assertSame('trash', JmapShim::roleFor($imap, 'Trash', ['\\trash']));
		$this->assertSame('sent', JmapShim::roleFor($imap, 'Sent', ['\\sent']));
		$this->assertSame('drafts', JmapShim::roleFor($imap, 'Drafts', ['\\drafts']));
		$this->assertSame('junk', JmapShim::roleFor($imap, 'Junk', ['\\junk']));
		$this->assertSame('archive', JmapShim::roleFor($imap, 'Archive', ['\\archive']));
	}

	/**
	 * Servers without SPECIAL-USE never return the attribute at all (Horde silently drops the
	 * option) - roleFor() must still identify special folders via the account's own configured
	 * acc_folder_* names in that case.
	 */
	public function testRoleForFallsBackToAccFolderWhenNoSpecialUseAttribute()
	{
		$imap = $this->getMockBuilder(Imap::class)
			->disableOriginalConstructor()
			->onlyMethods(['__get'])
			->getMock();
		$imap->method('__get')->willReturnCallback(static fn($name) => [
			'acc_folder_trash' => 'Papierkorb',
			'acc_folder_sent' => 'Gesendet',
		][$name] ?? null);

		$this->assertSame('trash', JmapShim::roleFor($imap, 'Papierkorb', []));
		$this->assertSame('sent', JmapShim::roleFor($imap, 'Gesendet', []));
		$this->assertNull(JmapShim::roleFor($imap, 'SomeOtherFolder', []));
	}

	/**
	 * Templates/Outbox have neither an IMAP SPECIAL-USE attribute nor a JMAP role at all (RFC 8621
	 * doesn't define either) - classic mail_tree.inc.php's own $definedFolders identifies them
	 * purely via the account's own acc_folder_template/acc_folder_outbox config, same mechanism
	 * as trash/sent/drafts/junk's acc_folder_* fallback above.
	 */
	public function testRoleForTemplatesAndOutboxViaAccFolderConfig()
	{
		$imap = $this->getMockBuilder(Imap::class)
			->disableOriginalConstructor()
			->onlyMethods(['__get'])
			->getMock();
		$imap->method('__get')->willReturnCallback(static fn($name) => [
			'acc_folder_template' => 'Vorlagen',
			'acc_folder_outbox' => 'Postausgang',
		][$name] ?? null);

		$this->assertSame('templates', JmapShim::roleFor($imap, 'Vorlagen', []));
		$this->assertSame('outbox', JmapShim::roleFor($imap, 'Postausgang', []));
	}

	/**
	 * Horde's MBOX_ALL_SUBSCRIBED is a confusingly-named constant: per its own docblock it
	 * returns "all mailboxes regardless of subscription status", not "only subscribed" - the
	 * default (no isSubscribed filter) must keep using it unchanged, matching mailboxQuery()'s
	 * long-standing behaviour before this filter existed.
	 */
	public function testListChildIdsDefaultsToAllRegardlessOfSubscription()
	{
		$imap = $this->mockImap(['personal' => [['delimiter' => '.']]]);
		$imap->expects($this->once())->method('listMailboxes')
			->with('%', \Horde_Imap_Client::MBOX_ALL_SUBSCRIBED, ['children' => true])
			->willReturn([]);

		$this->invokePrivate('listChildIds', [$imap, '']);
	}

	/**
	 * filter.isSubscribed:true (RFC 8621 MailboxFilterCondition) must switch to Horde's
	 * MBOX_SUBSCRIBED mode - matching classic mail_ui's own default browsing behaviour
	 * (mail_tree.inc.php's getInitialIndexTree() call: $_subscribedOnly =
	 * !showAllFoldersInFolderPane), which JmapShim ignored entirely before this fix, flooding the
	 * tree with every unsubscribed/stale mailbox on the account.
	 */
	public function testListChildIdsSubscribedOnlyUsesSubscribedMode()
	{
		$imap = $this->mockImap(['personal' => [['delimiter' => '.']]]);
		$imap->expects($this->once())->method('listMailboxes')
			->with('%', \Horde_Imap_Client::MBOX_SUBSCRIBED, ['children' => true])
			->willReturn(['INBOX' => []]);

		$ids = $this->invokePrivate('listChildIds', [$imap, '', true]);

		$this->assertSame([base64_encode('INBOX')], $ids);
	}

	/**
	 * Same defensive fallback as Api\Mail\Imap::getMailboxes()'s own "cyrus workaround": some
	 * accounts/servers never report ANY mailbox (not even INBOX) as subscribed at all - a real
	 * account hit this exactly (isSubscribed:true returned zero mailboxes, including its own
	 * INBOX, leaving the tree looking permanently empty with no way to recover). Rather than show
	 * a folder level that's completely empty, fall back to the unfiltered listing.
	 */
	public function testListChildIdsFallsBackToAllWhenSubscribedModeReturnsNothing()
	{
		$imap = $this->mockImap(['personal' => [['delimiter' => '.']]]);
		$imap->expects($this->exactly(2))->method('listMailboxes')
			->willReturnCallback(function($pattern, $mode, $opts)
			{
				if ($mode === \Horde_Imap_Client::MBOX_SUBSCRIBED) return [];
				$this->assertSame(\Horde_Imap_Client::MBOX_ALL_SUBSCRIBED, $mode);
				return ['INBOX' => []];
			});

		$ids = $this->invokePrivate('listChildIds', [$imap, '', true]);

		$this->assertSame([base64_encode('INBOX')], $ids);
	}

	public function testMailboxQueryPassesIsSubscribedFilterThroughToListChildIds()
	{
		$result = JmapShim::mailboxQuery('0', ['filter' => ['parentId' => base64_encode('INBOX'), 'isSubscribed' => true]]);
		// accountId '0' returns early (no connection) - this only confirms mailboxQuery() doesn't
		// choke on the new filter key; the mode-switch itself is covered by the listChildIds()
		// tests above
		$this->assertSame([], $result['ids']);
	}

	public function testMailboxQueryNameGivenStaysPureEncodingNoImapCall()
	{
		// must not construct a real connection at all (accountId '999' would throw in
		// self::imapServer() if ever reached) - confirms the cheap path used by
		// MailJmap.mailboxId() has zero regression
		$result = JmapShim::mailboxQuery('999', ['filter' => ['name' => 'INBOX']]);
		$this->assertSame([base64_encode('INBOX')], $result['ids']);
	}

	public function testMailboxQueryNoConnectionReturnsEmpty()
	{
		$result = JmapShim::mailboxQuery('0', ['filter' => ['parentId' => base64_encode('INBOX')]]);
		$this->assertSame([], $result['ids']);
	}

	public function testMailboxGetNoConnectionReturnsRequestedIdsAsNotFound()
	{
		$result = JmapShim::mailboxGet('0', ['ids' => [base64_encode('INBOX')]]);
		$this->assertSame([], $result['list']);
		$this->assertSame([base64_encode('INBOX')], $result['notFound']);
	}
}
