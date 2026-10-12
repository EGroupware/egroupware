<?php
/**
 * Test EGroupware\Api\Mail\Imap::listSubscribedMailboxes()'s Cyrus LIST-EXTENDED fallback
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail;

use EGroupware\Api;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;

/**
 * Found live via ticket/report 2026-10-12 (Stefan Unverricht, a real customer's own large Cyrus
 * install): Mail::getFolderObjects(subscribedOnly:true) - the sole data source for the ACL
 * dialog's folder search (mail/src/Compose.php's ajax_searchFolder()) - relies entirely on this
 * method. Its MBOX_SUBSCRIBED_EXISTS call uses the same RFC 5258 LIST-EXTENDED "(SUBSCRIBED)"
 * selection option that getMailboxes()'s own MBOX_ALL_SUBSCRIBED already has a documented,
 * cached-per-host "some Cyrus servers" workaround for - this method had none at all, so on an
 * affected server it silently returned nothing, with no fallback, for every single search term.
 *
 * Reuses the SAME per-host 'cyrus-workaround-<host>' Api\Cache::INSTANCE flag getMailboxes()
 * already sets/checks (same underlying server defect) - setting it here ALSO makes getMailboxes()
 * itself skip straight to ITS OWN already-guarded ALL(4)+UNSUBSCRIBED(3) fallback immediately,
 * rather than attempting (and re-failing) MBOX_ALL_SUBSCRIBED(5) first - so every test below that
 * exercises the fallback path must itself emulate THAT two-call shape, not a single ALL_SUBSCRIBED
 * call. Each test uses its own unique host name to stay isolated from both other tests and any
 * real, persistent instance-wide cache state, and cleans its own flag up afterwards via
 * Api\Cache::unsetInstance().
 */
#[AllowMockObjectsWithoutExpectations]
class ImapListSubscribedMailboxesCyrusWorkaroundTest extends TestCase
{
	private string $host;

	protected function setUp() : void
	{
		$this->host = 'cyrus-test-'.uniqid('', true);
	}

	protected function tearDown() : void
	{
		Api\Cache::unsetInstance(Imap::class, 'cyrus-workaround-'.$this->host);
	}

	/** onlyMethods: getMailboxes() is deliberately left REAL, so the fallback delegation itself is exercised, not mocked away. */
	private function mockImap(array $onlyMethods = []) : Imap
	{
		$imap = $this->getMockBuilder(Imap::class)
			->disableOriginalConstructor()
			->onlyMethods(array_unique(array_merge(['__get', 'listMailboxes'], $onlyMethods)))
			->getMock();
		$imap->method('__get')->willReturnCallback(fn($name) => $name === 'acc_imap_host' ? $this->host : null);
		return $imap;
	}

	/**
	 * Emulates getMailboxes()'s own already-guarded fallback once the workaround flag is set:
	 * MBOX_ALL(4) returns every mailbox (defaulted SUBSCRIBED=true by getMailboxes() itself), then
	 * MBOX_UNSUBSCRIBED(3) returns $notSubscribed, flipping those entries' SUBSCRIBED back to false.
	 */
	private function allAndUnsubscribedCallback(array $all, array $notSubscribed) : callable
	{
		return function($pattern, $mode, $options) use ($all, $notSubscribed)
		{
			if ($mode === \Horde_Imap_Client::MBOX_ALL)
			{
				return $all;
			}
			if ($mode === \Horde_Imap_Client::MBOX_UNSUBSCRIBED)
			{
				return $notSubscribed;
			}
			$this->fail('unexpected listMailboxes() mode: '.$mode);
		};
	}

	public function testFallsBackToGetMailboxesWhenSubscribedExistsComesBackEmpty()
	{
		$imap = $this->mockImap();
		$all = [
			'INBOX.Sent' => ['attributes' => ['\\subscribed'], 'delimiter' => '.'],
			'INBOX.Drafts' => ['attributes' => [], 'delimiter' => '.'],
		];
		$fallback = $this->allAndUnsubscribedCallback($all, ['INBOX.Drafts' => $all['INBOX.Drafts']]);
		$imap->method('listMailboxes')->willReturnCallback(function($pattern, $mode, $options) use ($fallback)
		{
			if ($mode === \Horde_Imap_Client::MBOX_SUBSCRIBED_EXISTS)
			{
				return [];
			}
			return $fallback($pattern, $mode, $options);
		});

		$result = $imap->listSubscribedMailboxes('', 0, false);

		$this->assertSame(['INBOX.Sent'], $result, 'only the genuinely-subscribed mailbox, matching the plain-names contract');
	}

	public function testReturnsAttributedShapeWhenRequested()
	{
		$imap = $this->mockImap();
		$entry = ['attributes' => ['\\subscribed'], 'delimiter' => '.'];
		$fallback = $this->allAndUnsubscribedCallback(['INBOX.Sent' => $entry], []);
		$imap->method('listMailboxes')->willReturnCallback(function($pattern, $mode, $options) use ($fallback)
		{
			if ($mode === \Horde_Imap_Client::MBOX_SUBSCRIBED_EXISTS)
			{
				return [];
			}
			return $fallback($pattern, $mode, $options);
		});

		$result = $imap->listSubscribedMailboxes('', 0, true);

		$this->assertSame(['\\subscribed'], $result['INBOX.Sent']['ATTRIBUTES']);
		$this->assertTrue($result['INBOX.Sent']['SUBSCRIBED']);
		$this->assertSame('.', $result['INBOX.Sent']['delimiter']);
	}

	public function testRemembersTheWorkaroundSoTheBrokenCallIsNeverRetried()
	{
		// pre-set the flag, as getMailboxes() or an earlier call on this same host already would have
		Api\Cache::setInstance(Imap::class, 'cyrus-workaround-'.$this->host, true);
		$imap = $this->mockImap();
		$entry = ['attributes' => ['\\subscribed'], 'delimiter' => '.'];
		$fallback = $this->allAndUnsubscribedCallback(['INBOX.Sent' => $entry], []);
		$imap->method('listMailboxes')->willReturnCallback(function($pattern, $mode, $options) use ($fallback)
		{
			$this->assertNotSame(\Horde_Imap_Client::MBOX_SUBSCRIBED_EXISTS, $mode,
				'the already-known-broken call must never be attempted again once the flag is set');
			return $fallback($pattern, $mode, $options);
		});

		$result = $imap->listSubscribedMailboxes('', 0, false);

		$this->assertSame(['INBOX.Sent'], $result);
	}

	public function testDoesNotFallBackWhenSubscribedExistsGenuinelyWorks()
	{
		$imap = $this->mockImap();
		$imap->expects($this->once())->method('listMailboxes')
			->willReturn(['INBOX.Sent' => ['attributes' => ['\\subscribed'], 'delimiter' => '.']]);

		$result = $imap->listSubscribedMailboxes('', 0, false);

		$this->assertSame(['INBOX.Sent'], $result);
	}
}
