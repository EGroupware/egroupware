<?php
/**
 * EGroupware API: cross-account move/copy involving a real-JMAP (Stalwart) account
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Mail;
use PHPUnit\Framework\TestCase;

/**
 * forum/live report 2026-09-29 (ralf, copying an NDN bounce from the real-JMAP/Stalwart test
 * account into a plain-IMAP one): Api\Mail::moveMessages()'s cross-account branch opens a real
 * raw IMAP socket to BOTH accounts (Horde FETCH+APPEND) - which fails outright ("Error when
 * communicating with the mail server") for any account that has no real IMAP endpoint at all,
 * e.g. a Stalwart account whose acc_imap_port is its JMAP(S) endpoint, not a real dual-protocol
 * IMAP port. Api\Mail::jmapCrossAccountTransfer() fixes this by fetching/appending via that
 * account's own JMAP HTTP session instead, for whichever side needs it - deliberately kept
 * server-side (not routed through the browser, an earlier draft of this fix): ralf, "copying/
 * moving a possibly huge number of mails is probably the only thing where doing that on the host
 * is quicker (at least not slower) than doing it on the client".
 *
 * DB-free bare TestCase: jmapCrossAccountTransfer() itself never touches Mail\Account::read()/a
 * real DB or network connection - only whatever $source/$target objects it's given directly -
 * and Mail\Imap::init_static()'s own trailing top-level call (triggered just by autoloading
 * Mail\Imap\Jmap, this test's $source/$target stand-ins' parent class) now only reads
 * Api\Config::read('mail') when a DB connection actually exists, see its own docblock.
 */
class JmapCrossAccountTransferTest extends TestCase
{
	private function mailInstance() : Mail
	{
		return new class extends Mail
		{
			public function __construct()
			{
			}
		};
	}

	/** @return object a fake Http-shaped jmapClient() result - only the methods this code calls */
	private function fakeJmapClient(array $overrides = []) : object
	{
		return new class($overrides) {
			public array $uploadedBlobs = [];
			public array $imported = [];
			public ?array $destroyed = null;
			private array $overrides;
			public function __construct(array $overrides) { $this->overrides = $overrides; }
			public function emailGet(string $id, array $properties, bool $fetchAllBodyValues = true) : array
			{
				if (isset($this->overrides['emailGet']))
				{
					return ($this->overrides['emailGet'])($id);
				}
				return ['blobId' => 'blob-'.$id, 'keywords' => ['$seen' => true], 'receivedAt' => '2026-01-15T10:00:00Z'];
			}
			public function downloadBlob(string $blobId, string $name = 'blob', string $type = 'application/octet-stream') : string
			{
				return "Subject: test\r\n\r\nBody for $blobId\r\n";
			}
			public function uploadBlob(string $raw, string $type = 'message/rfc822') : string
			{
				$this->uploadedBlobs[] = $raw;
				return 'uploaded-'.count($this->uploadedBlobs);
			}
			public function emailImport(string $blobId, string $folder, array $keywords = [], ?string $receivedAt = null) : string
			{
				$this->imported[] = compact('blobId', 'folder', 'keywords', 'receivedAt');
				return 'new-email-'.count($this->imported);
			}
			public function emailDestroy(array $ids) : void
			{
				$this->destroyed = $ids;
			}
		};
	}

	/** A real-JMAP account stand-in - Mail\Imap\Jmap::jmapClient() overridden to return the fake above. */
	private function fakeJmapAccount(object $jmapClient) : Mail\Imap\Jmap
	{
		return new class($jmapClient) extends Mail\Imap\Jmap
		{
			private object $fakeClient;
			public function __construct(object $fakeClient)
			{
				$this->fakeClient = $fakeClient;
			}
			public function jmapClient()
			{
				return $this->fakeClient;
			}
		};
	}

	/** A plain-IMAP account stand-in - captures getMailbox()/openMailbox()/fetch()/append()/search() calls. */
	private function fakePlainImapAccount(array $overrides = []) : object
	{
		return new class($overrides) {
			public array $appends = [];
			public ?string $openedMailbox = null;
			private array $overrides;
			public function __construct(array $overrides) { $this->overrides = $overrides; }
			public function getMailbox($folder) { return $folder; }
			public function openMailbox($mailbox) : void { $this->openedMailbox = $mailbox; }
			public function fetch($mailbox, $query, $opts)
			{
				if (isset($this->overrides['fetch']))
				{
					return ($this->overrides['fetch'])($mailbox, $opts);
				}
				return [];
			}
			public function append($mailbox, array $data)
			{
				$this->appends[] = ['mailbox' => $mailbox, 'data' => $data[0]];
				return true;	// forces the search() fallback below, matching a real Horde_Imap_Client_Ids response
			}
			public function search($mailbox, $query, $opts)
			{
				if (isset($this->overrides['search']))
				{
					return ($this->overrides['search'])();
				}
				return ['match' => new \Horde_Imap_Client_Ids([42])];
			}
		};
	}

	private function invokeTransfer(Mail $mail, $source, $target, string $sourceFolder, string $targetFolder,
		$ids, bool $deleteAfterMove, bool $returnUIDs)
	{
		$method = new \ReflectionMethod(Mail::class, 'jmapCrossAccountTransfer');
		$method->setAccessible(true);
		return $method->invoke($mail, $source, $target, $sourceFolder, $targetFolder, $ids, $deleteAfterMove, $returnUIDs);
	}

	/** A real-JMAP source's own fetched blobId/keywords/receivedAt pass straight into the target's Email/import. */
	public function testStalwartToStalwartCopiesKeywordsAndReceivedAtDirectly()
	{
		$mail = $this->mailInstance();
		$sourceClient = $this->fakeJmapClient();
		$targetClient = $this->fakeJmapClient();
		$source = $this->fakeJmapAccount($sourceClient);
		$target = $this->fakeJmapAccount($targetClient);

		$result = $this->invokeTransfer($mail, $source, $target, 'INBOX', 'Archive', ['e1'], false, true);

		$this->assertSame(['new-email-1'], $result);
		$this->assertCount(1, $targetClient->imported);
		$this->assertSame([
			'blobId' => 'uploaded-1', 'folder' => 'Archive',
			'keywords' => ['$seen' => true], 'receivedAt' => '2026-01-15T10:00:00Z',
		], $targetClient->imported[0]);
		$this->assertNull($targetClient->destroyed, "a COPY (deleteAfterMove=false) must not destroy anything");
	}

	/** A real-JMAP source into a plain-IMAP target: keywords -> IMAP flags, receivedAt -> internaldate. */
	public function testStalwartSourceToPlainImapTargetConvertsKeywordsToFlags()
	{
		$mail = $this->mailInstance();
		$source = $this->fakeJmapAccount($this->fakeJmapClient());
		$target = $this->fakePlainImapAccount();

		$this->invokeTransfer($mail, $source, $target, 'INBOX', 'Archive', ['e1'], false, false);

		$this->assertCount(1, $target->appends);
		$appended = $target->appends[0]['data'];
		$this->assertSame(['\\Seen'], $appended['flags']);
		$this->assertInstanceOf(\Horde_Imap_Client_DateTime::class, $appended['internaldate']);
		$this->assertSame('2026-01-15T10:00:00Z', $appended['internaldate']->format('Y-m-d\TH:i:s\Z'));
	}

	/** A plain-IMAP source into a real-JMAP target: IMAP flags -> keywords, IMAP date -> receivedAt. */
	public function testPlainImapSourceToStalwartTargetConvertsFlagsToKeywords()
	{
		$mail = $this->mailInstance();
		$fetchResult = new class {
			public function getFlags() { return ['\\Seen', '\\Flagged']; }
			public function getFullMsg() { return "Subject: test\r\n\r\nBody\r\n"; }
			public function getImapDate() { return new \Horde_Imap_Client_DateTime('2026-02-01T08:30:00Z'); }
		};
		$source = $this->fakePlainImapAccount(['fetch' => fn() => [7 => $fetchResult]]);
		$targetClient = $this->fakeJmapClient();
		$target = $this->fakeJmapAccount($targetClient);

		$this->invokeTransfer($mail, $source, $target, 'INBOX', 'Archive', [7], false, false);

		$this->assertCount(1, $targetClient->imported);
		$this->assertSame(['$seen' => true, '$flagged' => true], $targetClient->imported[0]['keywords']);
		$this->assertSame('2026-02-01T08:30:00Z', $targetClient->imported[0]['receivedAt']);
	}

	/** A cross-account MOVE (deleteAfterMove=true) with a real-JMAP source destroys the originals via JMAP, once imported. */
	public function testMoveWithJmapSourceDestroysOriginalsAfterImport()
	{
		$mail = $this->mailInstance();
		$sourceClient = $this->fakeJmapClient();
		$source = $this->fakeJmapAccount($sourceClient);
		$target = $this->fakePlainImapAccount();

		$this->invokeTransfer($mail, $source, $target, 'INBOX', 'Archive', ['e1', 'e2'], true, false);

		$this->assertSame(['e1', 'e2'], $sourceClient->destroyed);
	}

	/** A genuine failure (eg. the source blobId can't be resolved) must surface as Api\Exception, matching the classic branch's own error shape. */
	public function testWrapsAFailureIntoTheSameExceptionShapeAsTheClassicBranch()
	{
		$mail = $this->mailInstance();
		$sourceClient = $this->fakeJmapClient(['emailGet' => fn($id) => throw new \RuntimeException('boom')]);
		$source = $this->fakeJmapAccount($sourceClient);
		$target = $this->fakePlainImapAccount();

		$this->expectException(Api\Exception::class);
		$this->expectExceptionMessageMatches('/Copying to Folder Archive failed! Error:.*boom/');

		$this->invokeTransfer($mail, $source, $target, 'INBOX', 'Archive', ['e1'], false, false);
	}
}
