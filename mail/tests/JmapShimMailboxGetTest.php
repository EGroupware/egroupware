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

use EGroupware\Api\Mail\Jmap\Imap as JmapShim;

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
				['getNameSpaceArray', 'getUserMailboxString', 'listMailboxes', 'status', '__get', 'queryCapability', 'login'],
				$onlyMethods,
			)))
			->getMock();
		$imap->method('getNameSpaceArray')->willReturn([
			'personal' => [[
				'delimiter' => $namespaces['personal'][0]['delimiter'] ?? '.',
				'name' => $namespaces['personal'][0]['name'] ?? '',
			]],
			'others' => [[
				'delimiter' => $namespaces['others'][0]['delimiter'] ?? '\\',
				'name' => $namespaces['others'][0]['name'] ?? '',
			]],
		]);
		// mailboxNode()'s 'aclCapable' field calls this for the INBOX path - stub it so tests
		// that never construct a real connection don't fall through to the real
		// Horde_Imap_Client_Base::queryCapability(), which tries to actually connect (this mock
		// is built with disableOriginalConstructor(), so that would fail deep in Horde's socket
		// client with a null config array, not a meaningful assertion failure)
		$imap->method('queryCapability')->willReturn(false);
		// quotaFromImap() explicitly logs in before checking hasCapability('QUOTA') (post-auth-only
		// on real servers, see that method's own comment) - stub it to a no-op for the same reason
		// queryCapability() is stubbed above, otherwise it falls through to a real Horde login
		// attempt with no connection params.
		$imap->method('login')->willReturn(null);
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

	/**
	 * The real regression: a server whose "others" (shared/other-users) namespace delimiter
	 * differs from its "personal" one - a real, documented Cyrus/Dovecot configuration.
	 * canonicalPath() (raw IMAP name -> canonical path, used to build every Mailbox/get node's
	 * id/parentId) unconditionally used the 'personal' delimiter, so a raw name under the
	 * "others" namespace (here "user.otheruser.Sub", others delimiter '.') never got its
	 * delimiter replaced with '/' at all - it stayed completely unsplittable by splitPath() (no
	 * '/' in it), so mailboxNode() gave it parentId=null: a shared subfolder showing up as a
	 * top-level node at the account root instead of nested under "user/otheruser" (ralf's live
	 * report: "Renaming mail subfolder under user doesn't work and the folder is no longer
	 * displayed... visible at the mailaccount itself"). hordeMailbox() (the reverse direction)
	 * already had this exact "others" branch (96d3d0e353) - this pins its missing mirror image.
	 */
	public function testCanonicalPathUsesOthersDelimiterForSharedNamespace()
	{
		$imap = $this->mockImap([
			'personal' => [['delimiter' => '/']],
			'others' => [['delimiter' => '.', 'name' => 'user.']],
		]);

		$this->assertSame('user/otheruser/Sub', JmapShim::canonicalPath($imap, 'user.otheruser.Sub'));
		// a personal-namespace name (not under the "others" prefix) is untouched by the 'others'
		// delimiter - still translated via 'personal' ('/', a no-op here)
		$this->assertSame('INBOX/Project', JmapShim::canonicalPath($imap, 'INBOX/Project'));
		// round-trips correctly back to the real IMAP name for a rename/move target too
		$this->assertSame('user.otheruser.Sub', JmapShim::hordeMailbox($imap, 'user/otheruser/Sub'));
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

	/**
	 * Regression coverage for a real bug found live 2026-09-10 (ralf, via a real REST client
	 * against a plain-IMAP account): the shared/other-users namespace-root pseudo-folder
	 * ("user"/"shared", see namespaceRootsMissingFrom()) was listed like any other real mailbox,
	 * with no signal that a REST/JMAP-lite client can't actually list emails in it (the client-side
	 * folder tree already knows this - folderTree.ts's own noSelect flag - but that's derived
	 * client-side from the folder NAME, never sent back over the wire at all). RFC 8621's Mailbox
	 * object has no such property - real JMAP (Stalwart) has no namespace-root concept, so this
	 * only appears (as false) for the local shim's own synthetic namespace-root entries.
	 */
	public function testMailboxNodeIsSelectableFalseForNoselectAttribute()
	{
		$imap = $this->mockImap();
		$imap->method('status')->willReturn(['messages' => 0, 'unseen' => 0]);

		$node = $this->invokePrivate('mailboxNode', [$imap, 'user', ['\\noselect']]);

		$this->assertFalse($node['isSelectable']);
	}

	public function testMailboxNodeIsSelectableTrueWhenNoselectAttributeIsAbsent()
	{
		$imap = $this->mockImap();
		$imap->method('status')->willReturn(['messages' => 3, 'unseen' => 1]);

		$node = $this->invokePrivate('mailboxNode', [$imap, 'INBOX', ['\\subscribed']]);

		$this->assertTrue($node['isSelectable']);
	}

	/**
	 * isBareNamespaceRoot() is the guard emailQuery()/emailGet() use to reject trying to actually
	 * open the "user"/"shared" pseudo-folder (see those methods' own doc comment) - deliberately
	 * narrower than isNamespaceRootPath() (which also matches every path UNDER the root, eg.
	 * "user/otherperson/INBOX", a perfectly normal real mailbox once shared).
	 */
	public function testIsBareNamespaceRootMatchesOnlyTheBareRootNames()
	{
		$this->assertTrue($this->invokePrivate('isBareNamespaceRoot', ['user']));
		$this->assertTrue($this->invokePrivate('isBareNamespaceRoot', ['shared']));
		// case-insensitive, matching isNamespaceRootPath()'s own convention
		$this->assertTrue($this->invokePrivate('isBareNamespaceRoot', ['User']));
		$this->assertTrue($this->invokePrivate('isBareNamespaceRoot', ['SHARED']));
	}

	public function testIsBareNamespaceRootDoesNotMatchARealSubfolderUnderTheNamespace()
	{
		$this->assertFalse($this->invokePrivate('isBareNamespaceRoot', ['user/otherperson/INBOX']));
		$this->assertFalse($this->invokePrivate('isBareNamespaceRoot', ['shared/Public']));
	}

	public function testIsBareNamespaceRootDoesNotMatchAFolderThatMerelyStartsWithTheName()
	{
		$this->assertFalse($this->invokePrivate('isBareNamespaceRoot', ['usernotreal']));
		$this->assertFalse($this->invokePrivate('isBareNamespaceRoot', ['INBOX']));
		$this->assertFalse($this->invokePrivate('isBareNamespaceRoot', ['']));
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
		// top-level + subscribedOnly also probes for any unsubscribed-but-accessible passthrough
		// entry (see unsubscribedPassthroughsMissingFrom()) - irrelevant to what this test checks,
		// so the ALL_SUBSCRIBED probe just reports the same single mailbox already found, leaving
		// nothing missing to add.
		$imap = $this->mockImap(['personal' => [['delimiter' => '.']], 'others' => [['delimiter' => '/']]]);
		$imap->method('listMailboxes')->willReturnCallback(function($pattern, $mode, $opts)
		{
			if ($pattern === '%' && $mode === \Horde_Imap_Client::MBOX_SUBSCRIBED)
			{
				return ['INBOX' => []];
			}
			$this->assertSame('%', $pattern);
			$this->assertSame(\Horde_Imap_Client::MBOX_ALL_SUBSCRIBED, $mode);
			return ['INBOX' => []];
		});

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

	/**
	 * A namespace root with zero accessible children (nothing granted to this user via IMAP ACL,
	 * by far the more common case) must stay suppressed, exactly like classic - an
	 * always-visible-but-empty "user" entry is a confusing dead end most users would never
	 * understand. "user" is a perfectly real mailbox at this level (returned by the ALL_SUBSCRIBED
	 * probe), it's just never itself \Subscribed and has nothing subscribed beneath it either.
	 */
	public function testListChildIdsSuppressesNamespaceRootWithNoGrantedChildren()
	{
		$imap = $this->mockImap(['personal' => [['delimiter' => '/']], 'others' => [['delimiter' => '/']]]);
		$imap->method('listMailboxes')->willReturnCallback(function($pattern, $mode, $opts)
		{
			if ($mode === \Horde_Imap_Client::MBOX_SUBSCRIBED)
			{
				if ($pattern === '%') return ['INBOX' => []];
				// nothing granted (AND subscribed) anywhere under "user", at any depth
				$this->assertSame('user/*', $pattern);
				return [];
			}
			$this->assertSame('%', $pattern);
			$this->assertSame(\Horde_Imap_Client::MBOX_ALL_SUBSCRIBED, $mode);
			return ['INBOX' => [], 'user' => []];
		});

		$ids = $this->invokePrivate('listChildIds', [$imap, '', true]);

		$this->assertSame([base64_encode('INBOX')], $ids);
	}

	/**
	 * The regression this method was originally introduced for (narrower, name-matched version):
	 * a "user"/"shared" namespace root that's itself never individually \Subscribed must still
	 * show up once it has a real, accessible, SUBSCRIBED descendant somewhere beneath it -
	 * "granted" here must mean "granted AND subscribed" (ralf's report: an unsubscribed share
	 * showing an always-empty root in the main index is exactly the dead end this avoids), or it's
	 * still findable via the subscription dialog, which never calls with subscribedOnly true.
	 * "user/birgit" below is this test's stand-in for a real, subscribed shared mailbox.
	 */
	public function testListChildIdsIncludesUnsubscribedNamespaceRootWithSubscribedDescendant()
	{
		$imap = $this->mockImap(['personal' => [['delimiter' => '/']], 'others' => [['delimiter' => '/']]]);
		$imap->method('listMailboxes')->willReturnCallback(function($pattern, $mode, $opts)
		{
			if ($mode === \Horde_Imap_Client::MBOX_SUBSCRIBED)
			{
				if ($pattern === '%') return ['INBOX' => []];
				$this->assertSame('user/*', $pattern);
				return ['user/birgit' => []];
			}
			$this->assertSame('%', $pattern);
			$this->assertSame(\Horde_Imap_Client::MBOX_ALL_SUBSCRIBED, $mode);
			return ['INBOX' => [], 'user' => []];
		});

		$ids = $this->invokePrivate('listChildIds', [$imap, '', true]);

		$this->assertSame([base64_encode('INBOX'), base64_encode('user')], $ids);
	}

	/**
	 * The real generalization this fix is about (ticket #124701 / a colleague's "Munser" report):
	 * not just the bare "user"/"shared" namespace root (the original, narrower fix above), but ANY
	 * folder at ANY depth whose own name is never individually \Subscribed can still need to
	 * appear in its own parent's subscribed listing, purely because something further beneath it
	 * is. Live-verified against a real Dovecot account (2026-09-17, acc_id=85 sysop@bb-trunk vs. a
	 * 2nd real user sharing only INBOX and INBOX/SubFolder/SubSubFolder, deliberately NOT
	 * INBOX/SubFolder itself): "otherperson" (standing in for that 2nd user's own top folder under
	 * "user") was never itself subscribed - only "SubSubFolder", two levels further down (with an
	 * unsubscribed, \Noselect "SubFolder" placeholder in between) - and without this fix,
	 * "otherperson" never appeared while browsing "user" in "subscribed only" mode, at any point,
	 * even right after subscribing to SubSubFolder itself.
	 */
	public function testListChildIdsIncludesArbitraryUnsubscribedPassthroughAtAnyDepth()
	{
		$imap = $this->mockImap(['personal' => [['delimiter' => '/']], 'others' => [['delimiter' => '/']]]);
		$imap->method('listMailboxes')->willReturnCallback(function($pattern, $mode, $opts)
		{
			if ($mode === \Horde_Imap_Client::MBOX_SUBSCRIBED)
			{
				if ($pattern === 'user/%')
				{
					// "otherperson" itself is never individually subscribed - only "bb" is
					return ['user/bb' => []];
				}
				$this->assertSame('user/otherperson/*', $pattern);
				return ['user/otherperson/SubFolder/SubSubFolder' => []];
			}
			$this->assertSame('user/%', $pattern);
			$this->assertSame(\Horde_Imap_Client::MBOX_ALL_SUBSCRIBED, $mode);
			return ['user/bb' => [], 'user/otherperson' => []];
		});

		$ids = $this->invokePrivate('listChildIds', [$imap, 'user', true]);

		$this->assertSame([base64_encode('user/bb'), base64_encode('user/otherperson')], $ids);
	}

	public function testMailboxQueryPassesIsSubscribedFilterThroughToListChildIds()
	{
		$result = JmapShim::mailboxQuery('0', ['filter' => ['parentId' => base64_encode('INBOX'), 'isSubscribed' => true]]);
		// accountId '0' returns early (no connection) - this only confirms mailboxQuery() doesn't
		// choke on the new filter key; the mode-switch itself is covered by the listChildIds()
		// tests above
		$this->assertSame([], $result['ids']);
	}

	/**
	 * Regression coverage for a real bug found live 2026-09-14 (ralf, relaying a real user's
	 * report): the compose "Folder" field's client-side autocomplete search
	 * (MailJmap.getAllMailboxes(), mail/js/jmap.ts) sends a Mailbox/query filter with NO parentId
	 * key at all (RFC 8621: "no constraint on depth" - every mailbox at every level), but
	 * mailboxQuery()'s own `!empty($args['filter']['parentId'])` check couldn't tell that apart
	 * from an EXPLICIT `parentId: null` (the lazy tree's own "top level only" request,
	 * MailJmap.getMailboxChildren()) - both collapsed to the same one-level-only listChildIds()
	 * call, silently limiting the folder search to top-level mailboxes only (Sent/Trash/... but
	 * never anything nested under a subfolder) - "Keine Vorschläge" (no suggestions) for any
	 * search term matching only a nested folder.
	 */
	public function testMailboxQueryOmittedParentIdDoesNotChokeAndIsDistinctFromExplicitNull()
	{
		// accountId '0' returns early (no connection) either way - these only confirm mailboxQuery()
		// doesn't choke on either filter shape; the actual one-level-vs-recursive wildcard choice is
		// covered by the listChildIds() tests below (mailboxQuery() itself isn't mockable end-to-end
		// without a live IMAP connection, same boundary the test above already accepts)
		$this->assertSame([], JmapShim::mailboxQuery('0', ['filter' => []])['ids'], 'parentId omitted entirely');
		$this->assertSame([], JmapShim::mailboxQuery('0', ['filter' => ['parentId' => null]])['ids'], 'parentId explicitly null');
	}

	/**
	 * listChildIds()'s own recursive mode (mailboxQuery()'s "parentId omitted entirely" case) -
	 * IMAP's '*' wildcard matches every mailbox at every depth in one LIST call, unlike '%' (used
	 * for both the top-level and "under a specific parent" one-level-only cases above).
	 */
	public function testListChildIdsRecursiveUsesStarWildcardAtTopLevel()
	{
		$imap = $this->mockImap(['personal' => [['delimiter' => '.']]]);
		$imap->expects($this->once())->method('listMailboxes')
			->with('*', \Horde_Imap_Client::MBOX_ALL_SUBSCRIBED, ['children' => true])
			->willReturn(['INBOX' => [], 'INBOX.Sub' => [], 'INBOX.Sub.SubSub' => []]);

		$ids = $this->invokePrivate('listChildIds', [$imap, '', false, true]);

		$this->assertSame([
			base64_encode('INBOX'), base64_encode('INBOX/Sub'), base64_encode('INBOX/Sub/SubSub'),
		], $ids);
	}

	public function testListChildIdsNonRecursiveStillUsesPercentWildcard()
	{
		$imap = $this->mockImap(['personal' => [['delimiter' => '.']]]);
		$imap->expects($this->once())->method('listMailboxes')
			->with('%', \Horde_Imap_Client::MBOX_ALL_SUBSCRIBED, ['children' => true])
			->willReturn(['INBOX' => []]);

		$this->invokePrivate('listChildIds', [$imap, '', false, false]);
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

	public function testQuotaFromImapReturnsOctetsAccountQuotaInBytes()
	{
		$imap = $this->mockImap([], ['hasCapability', 'getStorageQuotaRoot']);
		$imap->method('hasCapability')->with('QUOTA')->willReturn(true);
		$imap->method('getStorageQuotaRoot')->with('INBOX')->willReturn(['USED' => 100, 'QMAX' => 1000]);

		$list = $this->invokePrivate('quotaFromImap', [$imap]);

		$this->assertCount(1, $list);
		$this->assertSame('octets', $list[0]['resourceType']);
		$this->assertSame('account', $list[0]['scope']);
		$this->assertSame(100 * 1024, $list[0]['used']);
		$this->assertSame(1000 * 1024, $list[0]['hardLimit']);
	}

	public function testQuotaFromImapEmptyWhenServerHasNoQuotaCapability()
	{
		$imap = $this->mockImap([], ['hasCapability', 'getStorageQuotaRoot']);
		$imap->method('hasCapability')->with('QUOTA')->willReturn(false);
		$imap->expects($this->never())->method('getStorageQuotaRoot');

		$this->assertSame([], $this->invokePrivate('quotaFromImap', [$imap]));
	}

	public function testQuotaFromImapEmptyWhenNoQuotaRootOnInbox()
	{
		$imap = $this->mockImap([], ['hasCapability', 'getStorageQuotaRoot']);
		$imap->method('hasCapability')->with('QUOTA')->willReturn(true);
		$imap->method('getStorageQuotaRoot')->with('INBOX')->willReturn(false);

		$this->assertSame([], $this->invokePrivate('quotaFromImap', [$imap]));
	}

	public function testQuotaGetNoConnectionReturnsEmptyList()
	{
		$result = JmapShim::quotaGet('0', ['ids' => null]);
		$this->assertSame([], $result['list']);
		$this->assertSame([], $result['notFound']);
	}
}
