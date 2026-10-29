<?php
/**
 * EGroupware Api: Mail\Imap\Jmap listSubscribedMailboxes()/subscribeMailbox() - JMAP-native, no raw IMAP fallthrough
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
 * Ticket #126691 follow-up (2026-10-29, right after migrating egroupware.org to Stalwart):
 * listSubscribedMailboxes()/subscribeMailbox() were un-overridden on this class (same fallthrough
 * class as getACL()/setACL(), see JmapNoRawImapFallbackTest) and fell through to
 * Horde_Imap_Client_Socket's raw IMAP calls via a real socket connect to a JMAP(S)-only endpoint.
 * mail/src/Ui.php's subscription() hit this live: listSubscribedMailboxes() threw, its catch-block
 * never set $subscribed, and array_map() crashed right after with "Argument #2 ($array) must be of
 * type array, null given".
 *
 * Same constructor-skipping mock + fake-session approach as JmapNoRawImapFallbackTest /
 * MailboxListAllPathsTest (jmapClient() is stubbed to return a fake Base subclass; this class's own
 * jmapCall() never touches a real HTTP/JMAP connection - a real one 401s even for
 * CLI-authenticated PHPUnit runs, confirmed live against this project's own Stalwart test account).
 */
class JmapSubscriptionNoRawImapFallbackTest extends \EGroupware\Api\AppTest
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

	private function fakeClient(array $mailboxes, ?array $notUpdated=null) : Base
	{
		return new class($mailboxes, $notUpdated) extends Base {
			public string $accountId = 'acc1';
			public array $calls = [];
			public function __construct(private array $mailboxes, private ?array $notUpdated) {}
			public function getMailboxId(string $folder, ?string $accountId=null) : ?string
			{
				foreach ($this->mailboxes as $mailbox)
				{
					if ($mailbox['name'] === $folder || (($mailbox['role'] ?? null) === 'inbox' && $folder === 'INBOX'))
					{
						return $mailbox['id'];
					}
				}
				return null;
			}
			public function jmapCall(array $methodCalls, $using=null, bool $emulate=false)
			{
				$this->calls[] = $methodCalls;
				if ($methodCalls[0][0] === 'Mailbox/query')
				{
					return ['methodResponses' => [
						['Mailbox/query', ['ids' => array_column($this->mailboxes, 'id')], '0'],
						['Mailbox/get', ['list' => $this->mailboxes], '1'],
					]];
				}
				$mailboxId = array_key_first($methodCalls[0][1]['update']);
				return ['methodResponses' => [
					['Mailbox/set', [
						'updated' => $this->notUpdated ? [] : [$mailboxId => null],
						'notUpdated' => $this->notUpdated ?? [],
					], '0'],
				]];
			}
		};
	}

	public function testListSubscribedMailboxesReturnsOnlySubscribedPathsWithoutAttributes()
	{
		$client = $this->fakeClient([
			['id' => 'inbox-id', 'name' => 'Inbox', 'parentId' => null, 'role' => 'inbox', 'isSubscribed' => true],
			['id' => 'trash-id', 'name' => 'Trash', 'parentId' => null, 'role' => 'trash', 'isSubscribed' => false],
		]);
		$jmap = $this->jmapWithClient($client);

		$this->assertSame(['INBOX'], $jmap->listSubscribedMailboxes());
	}

	public function testListSubscribedMailboxesWithAttributesMatchesParentContractShape()
	{
		$client = $this->fakeClient([
			['id' => 'inbox-id', 'name' => 'Inbox', 'parentId' => null, 'role' => 'inbox', 'isSubscribed' => true],
		]);
		$jmap = $this->jmapWithClient($client);

		$this->assertSame(
			['INBOX' => ['MAILBOX' => 'INBOX', 'ATTRIBUTES' => [], 'delimiter' => '/', 'SUBSCRIBED' => true]],
			$jmap->listSubscribedMailboxes('', 0, true)
		);
	}

	public function testSubscribeMailboxPatchesIsSubscribedViaMailboxSet()
	{
		$client = $this->fakeClient([
			['id' => 'inbox-id', 'name' => 'Inbox', 'parentId' => null, 'role' => 'inbox', 'isSubscribed' => false],
		]);
		$jmap = $this->jmapWithClient($client);

		$jmap->subscribeMailbox('INBOX', true);

		$this->assertSame(
			['Mailbox/set', ['accountId' => 'acc1', 'update' => ['inbox-id' => ['isSubscribed' => true]]], '0'],
			$client->calls[0][0]
		);
	}

	public function testSubscribeMailboxThrowsWithoutRawImapFallbackWhenMailboxNotFound()
	{
		$client = $this->fakeClient([]);
		$jmap = $this->jmapWithClient($client);

		$this->expectException(\Horde_Imap_Client_Exception::class);
		$jmap->subscribeMailbox('Nonexistent', true);
	}

	public function testSubscribeMailboxThrowsWhenJmapReportsNotUpdated()
	{
		$client = $this->fakeClient(
			[['id' => 'inbox-id', 'name' => 'Inbox', 'parentId' => null, 'role' => 'inbox', 'isSubscribed' => false]],
			['inbox-id' => ['type' => 'invalidProperties']]
		);
		$jmap = $this->jmapWithClient($client);

		$this->expectException(\Horde_Imap_Client_Exception::class);
		$jmap->subscribeMailbox('INBOX', true);
	}
}
