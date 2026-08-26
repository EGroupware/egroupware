<?php
/**
 * Test EGroupware\Mail\JmapShim's Mailbox/set implementation (create/rename/move/
 * (un)subscribe/delete real IMAP mailboxes for the local-shim path).
 *
 * The private mailboxCreate()/mailboxUpdate()/mailboxDestroy() helpers are exercised directly
 * via ReflectionMethod against a mocked Mail\Imap connection (same reflection-into-private-
 * internals style JmapTest::searchState() already uses for Horde_Imap_Client_Search_Query) -
 * this verifies the path-resolution/Horde-call logic without a live IMAP server, which
 * self::imapServer()'s own Account::read() call would otherwise require. mailboxSet() itself is
 * only exercised via the accountId "0" (no-connection) path, which is the one accountId that
 * never reaches self::imapServer() at all.
 *
 * Pass criteria per test: the exact Horde_Imap_Client_Socket method(s) expected for that
 * operation are called with the exact translated mailbox name(s), and no unexpected method is
 * called (e.g. a validation failure must not have already performed a partial rename).
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap as JmapShim;

use EGroupware\Api\Mail\Imap;

class JmapShimMailboxSetTest extends \PHPUnit\Framework\TestCase
{
	/**
	 * @param array $namespaces {personal?: array{0: array{delimiter: string}}, others?: array{0: array{delimiter: string}}}
	 * @param array $onlyMethods additional methods (beyond the namespace/admin ones already stubbed) the test wants to assert on
	 */
	private function mockImap(array $namespaces = [], array $onlyMethods = []) : Imap
	{
		$imap = $this->getMockBuilder(Imap::class)
			->disableOriginalConstructor()
			->onlyMethods(array_unique(array_merge(
				['getNameSpaceArray', 'getUserMailboxString', 'createMailbox', 'renameMailbox',
					'deleteMailbox', 'subscribeMailbox', 'status'],
				$onlyMethods,
			)))
			->getMock();
		$imap->method('getNameSpaceArray')->willReturn([
			'personal' => [['delimiter' => $namespaces['personal'][0]['delimiter'] ?? '.']],
			'others' => [['delimiter' => $namespaces['others'][0]['delimiter'] ?? '\\']],
		]);
		return $imap;
	}

	private function invokePrivate(string $method, array $args)
	{
		$reflection = new \ReflectionMethod(JmapShim::class, $method);
		$reflection->setAccessible(true);
		return $reflection->invoke(null, ...$args);
	}

	public function testSplitPathTopLevel()
	{
		$this->assertSame(['', 'INBOX'], JmapShim::splitPath('INBOX'));
	}

	public function testSplitPathNested()
	{
		$this->assertSame(['INBOX/Project', '2026'], JmapShim::splitPath('INBOX/Project/2026'));
	}

	/**
	 * accountId "0" never reaches self::imapServer() - every create/update/destroy must fail
	 * cleanly as "forbidden" rather than silently no-op or fatal.
	 */
	public function testMailboxSetNoConnectionRejectsEverything()
	{
		$result = JmapShim::mailboxSet('0', [
			'create' => ['c0' => ['name' => 'New']],
			'update' => ['aWQ=' => ['name' => 'New']],
			'destroy' => ['aWQ='],
		]);

		$this->assertSame([], (array)$result['created']);
		$this->assertSame('forbidden', ((array)$result['notCreated'])['c0']['type']);
		$this->assertSame([], (array)$result['updated']);
		$this->assertSame('forbidden', ((array)$result['notUpdated'])['aWQ=']['type']);
		$this->assertSame([], $result['destroyed']);
		$this->assertSame('forbidden', ((array)$result['notDestroyed'])['aWQ=']['type']);
	}

	public function testMailboxCreateTopLevelSubscribesByDefault()
	{
		$imap = $this->mockImap();
		$imap->expects($this->once())->method('createMailbox')->with('New');
		$imap->expects($this->once())->method('subscribeMailbox')->with('New', true);

		$result = $this->invokePrivate('mailboxCreate', [$imap, ['name' => 'New'], null]);

		$this->assertSame(base64_encode('New'), $result['id']);
	}

	public function testMailboxCreateRespectsIsSubscribedFalse()
	{
		$imap = $this->mockImap();
		$imap->expects($this->once())->method('subscribeMailbox')->with('New', false);

		$this->invokePrivate('mailboxCreate', [$imap, ['name' => 'New', 'isSubscribed' => false], null]);
	}

	public function testMailboxCreateUnderParentUsesPersonalDelimiter()
	{
		$imap = $this->mockImap(['personal' => [['delimiter' => '.']]]);
		$imap->expects($this->once())->method('createMailbox')->with('INBOX.Project');

		$this->invokePrivate('mailboxCreate', [$imap, ['name' => 'Project', 'parentId' => base64_encode('INBOX')], null]);
	}

	public function testMailboxCreateRequiresName()
	{
		$imap = $this->mockImap();
		$imap->expects($this->never())->method('createMailbox');

		$this->expectException(\InvalidArgumentException::class);
		$this->invokePrivate('mailboxCreate', [$imap, [], null]);
	}

	/**
	 * Admin-impersonated create must resolve under the impersonated user's own namespace root
	 * (getUserMailboxString()), joined with the "others" namespace's own delimiter - not the
	 * connection-owner's personal one, which mockImap() deliberately sets to a different value
	 * ('.' vs '\\') so a test bug (using the wrong delimiter) would be caught.
	 */
	public function testMailboxCreateAdminImpersonationUsesOthersNamespaceRoot()
	{
		$imap = $this->mockImap(['personal' => [['delimiter' => '.']], 'others' => [['delimiter' => '\\']]]);
		$imap->method('getUserMailboxString')->willReturnCallback(
			static fn($username, $folder = '') => 'Other Users\\'.$username.($folder !== '' ? '\\'.$folder : ''),
		);
		$imap->expects($this->once())->method('createMailbox')->with('Other Users\\42\\Project');

		$this->invokePrivate('mailboxCreate', [$imap, ['name' => 'Project'], '42']);
	}

	public function testMailboxUpdateRenameOnNameChange()
	{
		$imap = $this->mockImap(['personal' => [['delimiter' => '.']]]);
		$imap->expects($this->once())->method('renameMailbox')->with('INBOX.Old', 'INBOX.New');
		$imap->expects($this->never())->method('subscribeMailbox');

		$this->invokePrivate('mailboxUpdate', [$imap, base64_encode('INBOX/Old'), ['name' => 'New'], null]);
	}

	public function testMailboxUpdateMoveOnParentIdChange()
	{
		$imap = $this->mockImap(['personal' => [['delimiter' => '.']]]);
		$imap->expects($this->once())->method('renameMailbox')->with('INBOX.Project', 'Archive.Project');

		$this->invokePrivate('mailboxUpdate', [
			$imap, base64_encode('INBOX/Project'), ['parentId' => base64_encode('Archive')], null,
		]);
	}

	public function testMailboxUpdateSubscribeOnlyDoesNotRename()
	{
		$imap = $this->mockImap(['personal' => [['delimiter' => '.']]]);
		$imap->expects($this->never())->method('renameMailbox');
		$imap->expects($this->once())->method('subscribeMailbox')->with('INBOX.Project', false);

		$this->invokePrivate('mailboxUpdate', [$imap, base64_encode('INBOX/Project'), ['isSubscribed' => false], null]);
	}

	public function testMailboxUpdateRejectsUnknownPropertyBeforeAnyHordeCall()
	{
		$imap = $this->mockImap();
		$imap->expects($this->never())->method('renameMailbox');
		$imap->expects($this->never())->method('subscribeMailbox');

		$this->expectException(\InvalidArgumentException::class);
		$this->invokePrivate('mailboxUpdate', [$imap, base64_encode('INBOX'), ['role' => 'trash'], null]);
	}

	public function testMailboxUpdateRejectsRootId()
	{
		$imap = $this->mockImap();
		$imap->expects($this->never())->method('renameMailbox');
		$imap->expects($this->never())->method('subscribeMailbox');

		$this->expectException(\InvalidArgumentException::class);
		$this->invokePrivate('mailboxUpdate', [$imap, base64_encode(''), ['name' => 'New'], null]);
	}

	public function testMailboxDestroyRejectsNonEmptyMailboxByDefault()
	{
		$imap = $this->mockImap();
		$imap->method('status')->willReturn(['messages' => 3]);
		$imap->expects($this->never())->method('deleteMailbox');

		$this->expectExceptionMessage('mailboxHasEmail');
		$this->invokePrivate('mailboxDestroy', [$imap, base64_encode('INBOX/Old'), false, null]);
	}

	public function testMailboxDestroySkipsStatusCheckWhenRemoveEmailsTrue()
	{
		$imap = $this->mockImap();
		$imap->expects($this->never())->method('status');
		$imap->expects($this->once())->method('deleteMailbox')->with('INBOX');

		$this->invokePrivate('mailboxDestroy', [$imap, base64_encode('INBOX'), true, null]);
	}

	public function testMailboxDestroyUnsubscribesThenDeletesEmptyMailbox()
	{
		$imap = $this->mockImap();
		$imap->method('status')->willReturn(['messages' => 0]);
		$imap->expects($this->once())->method('subscribeMailbox')->with('INBOX', false);
		$imap->expects($this->once())->method('deleteMailbox')->with('INBOX');

		$this->invokePrivate('mailboxDestroy', [$imap, base64_encode('INBOX'), false, null]);
	}

	public function testMailboxDestroyRejectsRootId()
	{
		$imap = $this->mockImap();
		$imap->expects($this->never())->method('status');
		$imap->expects($this->never())->method('deleteMailbox');

		$this->expectException(\InvalidArgumentException::class);
		$this->invokePrivate('mailboxDestroy', [$imap, base64_encode(''), true, null]);
	}
}
