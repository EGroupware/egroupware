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
	 * ids must look each one up individually (exact, non-wildcard name), never scan the whole
	 * account with a '*' pattern - that would defeat the point for accounts with hundreds of
	 * folders.
	 */
	public function testMailboxGetInternalExplicitIdsNeverScansWholeAccount()
	{
		$imap = $this->mockImap(['personal' => [['delimiter' => '.']]]);
		$imap->expects($this->exactly(2))->method('listMailboxes')
			->willReturnCallback(function($pattern, $mode, $opts)
			{
				$this->assertNotSame('*', $pattern);
				$fixtures = [
					'INBOX' => ['INBOX' => ['attributes' => ['\\subscribed']]],
					'INBOX.Sent' => ['INBOX.Sent' => ['attributes' => ['\\subscribed', '\\sent']]],
				];
				return $fixtures[$pattern] ?? [];
			});
		$imap->method('status')->willReturn(['messages' => 0, 'unseen' => 0]);

		$result = $this->invokePrivate('mailboxGetInternal', [
			$imap, [base64_encode('INBOX'), base64_encode('INBOX/Sent')], null,
		]);

		$this->assertCount(2, $result['list']);
		$this->assertSame([], $result['notFound']);
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
			->with('*', \Horde_Imap_Client::MBOX_ALL_SUBSCRIBED, ['attributes' => true, 'special_use' => true, 'children' => true])
			->willReturn([
				'INBOX' => ['attributes' => ['\\subscribed']],
				'INBOX.Sent' => ['attributes' => ['\\subscribed', '\\sent']],
			]);
		$imap->method('status')->willReturn(['messages' => 0, 'unseen' => 0]);

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
