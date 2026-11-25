<?php
/**
 * EGroupware Mail: Test Ui\ImportHandler::importMessageToFolder()'s JMAP-native import path
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail\Ui;

use EGroupware\Api;
use EGroupware\Api\Mail;
use EGroupware\Api\Mail\Jmap\Http;
use EGroupware\Mail\Ui;

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

/**
 * doc/ai/projects/mail-test-coverage.md's priority-4 entry: ImportHandler.php had zero coverage.
 * importMessageToFolder()'s JMAP branch is the same "JMAP-FALLTHROUGH-GUARD" pattern already
 * tested for FolderHandler::setFolderStatus(), but more substantial - a real live bug found
 * 2026-09-03 (ralf: clicking an InfoLog-attached .eml on a Stalwart account failed with "Zielordner
 * Drafts existiert nicht"): folderExists()/appendMessage() fall through to raw-socket methods,
 * unguarded for a JMAP account, so this materializes the import via genuine Email/import
 * (RFC 8621 §4.8) instead.
 *
 * `$_formData['file']` uses the `egw-data://` scheme throughout - Mail::checkFileBasics() returns
 * immediately for that scheme (no real temp-file/filesystem dependency at all), keeping these
 * tests focused on the JMAP-import logic itself. The fake JMAP session is the same
 * fake-Http-subclass technique TransportSendTest.php already established (Http's own constructor
 * does a real network bootstrap, so a no-op override is needed either way).
 */
class ImportHandlerImportMessageToFolderTest extends Api\LoggedInTest
{
	/**
	 * @param ?string $mailboxId what Mailbox::getMailboxId()'s own per-segment jmapCall() round
	 *  trips should resolve the LAST segment to - null simulates "folder not found"
	 * @param callable $callResponder handles the single Email/import call() (and any other
	 *  direct call() a test wants to inject a failure for)
	 * @param int $totalSegments number of '/'-separated segments in the folder path this fake
	 *  will be asked to resolve (every test here uses a 2-segment "INBOX/X" path except the
	 *  trailing-delimiter one, already normalized down to a bare 1-segment "INBOX" by the time
	 *  getMailboxId() is ever called) - needed because getMailboxId() now issues one jmapCall()
	 *  round trip PER segment (ticket #128691: a later segment's own query needs an earlier
	 *  segment's already-resolved id as its filter.parentId - the local shim's mailboxQuery()
	 *  requires this to do anything beyond a bogus top-level-only path synthesis, see
	 *  Mailbox.php's own docblock), so this fake can no longer tell "is this the last segment"
	 *  from a single batch's own size the way it used to.
	 */
	private function fakeHttp(?string $mailboxId, callable $callResponder, int $totalSegments=2) : Http
	{
		return new class($mailboxId, $callResponder, $totalSegments) extends Http
		{
			public array $calls = [];
			public array $uploadedBlobs = [];
			private int $segmentCalls = 0;
			private ?string $parentId = null;
			public function __construct(private ?string $mailboxId, private $callResponder, private int $totalSegments)
			{
				$this->accountId = 'acc1';
			}
			public function call(string $method, array $args) : array
			{
				$this->calls[] = [$method, $args];
				return ($this->callResponder)($method, $args);
			}
			public function jmapCall(array $methodCalls, $using=null, bool $emulate=false)
			{
				// Mailbox::getMailboxId()'s per-segment Mailbox/query+get pair (2026-11-25, RFC 8621's
				// "name" filter is a substring match, so query is only ever a candidate net - a chained
				// Mailbox/get verifies exact name+parentId before a match is accepted, see its own
				// docblock), one segment's own round trip per jmapCall() invocation (ticket #128691
				// follow-up - see this method's own @param totalSegments): when $this->mailboxId is
				// set, the LAST segment's candidate resolves to it; every earlier (intermediate)
				// segment resolves to an arbitrary-but-consistent placeholder id, chained via parentId
				// so the whole path verifies. When $this->mailboxId is null, every segment's
				// Mailbox/get comes back empty (not found).
				[$queryName, $queryArgs, $queryCallId] = $methodCalls[0];
				[$getName, , $getCallId] = $methodCalls[1];
				// ImportHandler::importMessageToFolder() resolves the folder itself BEFORE ever
				// calling Email::emailImport() - which then resolves the SAME folder path a second,
				// entirely independent time internally (its own existence check). An explicit
				// filter.parentId===null marks the first segment of a NEW resolution (this method's
				// own getMailboxId() always passes the real, already-resolved parentId for every
				// later segment, never null again) - reset the per-resolution counters there so the
				// second resolution starts fresh instead of carrying over the first one's state.
				if (($queryArgs['filter']['parentId'] ?? null) === null)
				{
					$this->segmentCalls = 0;
					$this->parentId = null;
				}
				$this->segmentCalls++;
				if ($this->mailboxId === null)
				{
					return ['methodResponses' => [
						[$queryName, ['ids' => []], $queryCallId],
						[$getName, ['list' => []], $getCallId],
					]];
				}
				$isLastSegment = $this->segmentCalls >= $this->totalSegments;
				$id = $isLastSegment ? $this->mailboxId : 'ancestor-'.$this->segmentCalls;
				$response = ['methodResponses' => [
					[$queryName, ['ids' => [$id]], $queryCallId],
					[$getName, ['list' => [[
						'id' => $id, 'name' => $queryArgs['filter']['name'], 'parentId' => $this->parentId,
					]]], $getCallId],
				]];
				$this->parentId = $id;
				return $response;
			}
			public function uploadBlob(string $raw, string $type='message/rfc822') : string
			{
				$this->uploadedBlobs[] = [$raw, $type];
				return 'blob-'.count($this->uploadedBlobs);
			}
		};
	}

	private function fakeJmapIcServer(Http $fakeSession) : Mail\Imap\Jmap
	{
		return new class($fakeSession) extends Mail\Imap\Jmap
		{
			public function __construct(private Http $fakeSession) {}
			public function jmapClient() { return $this->fakeSession; }
		};
	}

	/** A minimal mail_bo stub - folderExists()/appendMessage() throw, proving the JMAP branch never falls through to them. */
	private function fakeMailBo(object $icServer, string $delimiter='/') : object
	{
		return new class($icServer, $delimiter) {
			public $profileID = '1';
			public $lastParsedInto = null;
			public function __construct(public $icServer, private string $delimiter) {}
			public function parseFileIntoMailObject($mailObject, $tmpFileName)
			{
				$this->lastParsedInto = $tmpFileName;
				$part = new \Horde_Mime_Part();
				$part->setType('message/rfc822');
				$part->setContents('parsed content for '.$tmpFileName);
				$mailObject->setBasePart($part);
			}
			public function openConnection() {}
			public function getHierarchyDelimiter() { return $this->delimiter; }
			public function folderExists($folder, $subscribe_only=false) { throw new \Exception('folderExists() must not be called for a JMAP account'); }
			public function appendMessage($folder, $raw, $flags=null, $extra=null) { throw new \Exception('appendMessage() must not be called for a JMAP account'); }
		};
	}

	private function handler(object $mail_bo) : ImportHandler
	{
		$ui = new Ui(false);
		$ui->mail_bo = $mail_bo;
		return new ImportHandler($ui);
	}

	private function formData() : array
	{
		return ['name' => 'test.eml', 'type' => 'message/rfc822', 'size' => 100, 'file' => 'egw-data://testtoken'];
	}

	public function testImportsViaEmailImportAndReturnsTheJmapShapedRowId()
	{
		$jmap = $this->fakeHttp('mbx1', fn($method) => match ($method)
		{
			'Email/import' => ['created' => ['x' => ['id' => 'new-email-id']]],
			default => $this->fail("unexpected call: $method"),
		});
		$folder = 'INBOX/Drafts';

		$rowId = $this->handler($this->fakeMailBo($this->fakeJmapIcServer($jmap)))
			->importMessageToFolder($this->formData(), $folder);

		$account_id = $GLOBALS['egw_info']['user']['account_id'];
		$this->assertSame("mail::$account_id::1::mbx1::new-email-id", $rowId);
	}

	public function testUploadsTheParsedRawMessageBytesAsTheBlob()
	{
		$jmap = $this->fakeHttp('mbx1', fn() => ['created' => ['x' => ['id' => 'e1']]]);
		$folder = 'INBOX/Drafts';

		$this->handler($this->fakeMailBo($this->fakeJmapIcServer($jmap)))
			->importMessageToFolder($this->formData(), $folder);

		$this->assertCount(1, $jmap->uploadedBlobs);
		$this->assertStringContainsString('parsed content for egw-data://testtoken', $jmap->uploadedBlobs[0][0]);
		$this->assertSame('message/rfc822', $jmap->uploadedBlobs[0][1]);
	}

	public function testNormalizesABareInboxWithTrailingDelimiterBeforeResolvingTheFolder()
	{
		$jmap = $this->fakeHttp('inbox-id', fn() => ['created' => ['x' => ['id' => 'e1']]], totalSegments: 1);
		$folder = 'INBOX/';

		$this->handler($this->fakeMailBo($this->fakeJmapIcServer($jmap), '/'))
			->importMessageToFolder($this->formData(), $folder);

		$this->assertSame('INBOX', $folder, "'INBOX' + trailing delimiter must be normalized to bare 'INBOX'");
	}

	public function testThrowsWithoutUploadingAnythingWhenTheTargetFolderDoesNotExist()
	{
		$jmap = $this->fakeHttp(null, fn($method) => $this->fail("unexpected call: $method"));
		$folder = 'INBOX/Nonexistent';

		try
		{
			$this->handler($this->fakeMailBo($this->fakeJmapIcServer($jmap)))
				->importMessageToFolder($this->formData(), $folder);
			$this->fail('expected an Api\Exception\WrongUserinput');
		}
		catch (Api\Exception\WrongUserinput $e)
		{
			$this->assertStringContainsString('does not exist', $e->getMessage());
		}
		$this->assertEmpty($jmap->uploadedBlobs, "must never upload a blob when the destination folder can't be resolved");
	}

	public function testWrapsAnyOtherJmapFailureIntoAWrongUserinputExceptionWithTheOriginalMessage()
	{
		$jmap = $this->fakeHttp('mbx1', function()
		{
			throw new \RuntimeException('server exploded');
		});
		$folder = 'INBOX/Drafts';

		try
		{
			$this->handler($this->fakeMailBo($this->fakeJmapIcServer($jmap)))
				->importMessageToFolder($this->formData(), $folder);
			$this->fail('expected an Api\Exception\WrongUserinput');
		}
		catch (Api\Exception\WrongUserinput $e)
		{
			$this->assertStringContainsString('server exploded', $e->getMessage());
		}
	}

	public function testThrowsWithoutTouchingJmapAtAllWhenTheFolderArgumentIsEmpty()
	{
		$jmap = $this->fakeHttp('mbx1', fn() => $this->fail('Email/import must not be called when the folder is empty'));
		$folder = '';

		try
		{
			$this->handler($this->fakeMailBo($this->fakeJmapIcServer($jmap)))
				->importMessageToFolder($this->formData(), $folder);
			$this->fail('expected an Api\Exception\WrongUserinput');
		}
		catch (Api\Exception\WrongUserinput $e)
		{
			// case-insensitive: mail/lang/egw_en.lang translates the raw "Destination Folder not
			// set." source literal to "Destination folder not set." (lowercase 'f') - found via a
			// real CI failure 2026-09-10, where the full language file is loaded; a local dev
			// container with an incomplete/stale translation cache can silently fall back to the
			// raw literal's own capitalization instead, masking the mismatch
			$this->assertStringContainsStringIgnoringCase('destination folder not set', $e->getMessage());
		}
		$this->assertEmpty($jmap->uploadedBlobs);
	}
}
