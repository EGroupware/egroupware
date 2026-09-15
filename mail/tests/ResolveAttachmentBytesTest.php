<?php
/**
 * EGroupware Mail: tests for MessageDisplayHandler::resolveAttachmentBytes()
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api\Mail\Jmap\Imap as JmapImap;
use EGroupware\Mail\Ui;
use EGroupware\Mail\Ui\MessageDisplayHandler;
use PHPUnit\Framework\TestCase;

/**
 * Real report (tracker #124541, "Anhänge herunterladen aus der Ansicht übernimmt nicht den
 * korrekten Namen" - attachments "for downloading" from the view don't get the correct name):
 * "open this attachment" (mail.EGroupware\Mail\Ui.getAttachment, the popup/download-link target
 * AttachmentJmap::createAttachmentBlock() builds for IMAGE/PDF/TEXT/vCard/calendar/default
 * attachments) went through the classic $uid/$mailbox IMAP fetch unconditionally - unlike the
 * already-JMAP-aware direct-download action (downloadOneAsFile, mail/js/app.ts). A JMAP row's
 * opaque emailID/folderID never resolve to a real IMAP UID/mailbox pair the classic fetch needs,
 * so a real Stalwart account's "open this attachment" link either fataled outright
 * (`Mail::jmapResolveUid(): Argument #2 ($_folder) must be of type string, null given`, live
 * 2026-09-15) or fell back to a cryptic `mailbox_uidX_partY` filename, depending on the account.
 *
 * Fixed by giving resolveAttachmentBytes() the same JMAP-native blobId fast path
 * (AttachmentJmap::fetchBlobBytes(), the local shim's "upload:"/mailbox:uid:partId resolution
 * fixed alongside [[project_mail_download_link_attachments_broken]] the same session) the direct
 * -download action already had, falling back to the pre-existing classic fetch (now its own
 * classicAttachmentFetch() method, purely so a test double can stub it out here) when there's no
 * blobId or the blob itself is gone.
 *
 * getAttachment() itself ends in echo+exit() and can't be unit tested directly - this file covers
 * the extracted decision instead. Both fixes live-verified end-to-end against
 * boulder.egroupware.org: a real Stalwart account's own "1000214413.jpg" attachment (previously a
 * hard 500) and the local shim's own PastedGraphic-2.png both now return 200 with the correct
 * Content-Disposition filename and exact byte count; the pre-existing no-blobId classic path was
 * re-confirmed unchanged.
 */
class ResolveAttachmentBytesTest extends TestCase
{
	private function handler() : ResolveAttachmentBytesTestFixtureHandler
	{
		// Ui(false) skips the constructor's own real IMAP-connecting body entirely (see its own
		// docblock) - safe here since every test below either short-circuits before ever touching
		// $this->ui->mail_bo (the blobId-success path) or goes through the stubbed
		// classicAttachmentFetch() override instead of the real one.
		return new ResolveAttachmentBytesTestFixtureHandler(new Ui(false));
	}

	private function writeUploadFixture(string $bytes) : string
	{
		$token = bin2hex(random_bytes(16));
		$path = JmapImap::uploadPath($token);
		file_put_contents($path, $bytes);
		$this->addFileCleanup($path);
		return 'upload:'.$token;
	}

	private array $filesToCleanUp = [];

	private function addFileCleanup(string $path) : void
	{
		$this->filesToCleanUp[] = $path;
	}

	protected function tearDown() : void
	{
		foreach ($this->filesToCleanUp as $path)
		{
			@unlink($path);
		}
		$this->filesToCleanUp = [];
		parent::tearDown();
	}

	public function testBlobIdSuccessReturnsRealBytesWithoutTouchingClassicFetch() : void
	{
		$handler = $this->handler();
		$blobId = $this->writeUploadFixture('resolveAttachmentBytes test content');

		$result = $handler->resolveAttachmentBytes('1', null, null, null, false,
			$blobId, 'real-name.txt', 'text/plain');

		$this->assertSame('resolveAttachmentBytes test content', $result['attachment']);
		$this->assertSame('real-name.txt', $result['name']);
		$this->assertSame('real-name.txt', $result['filename']);
		$this->assertSame('text/plain', $result['type']);
		$this->assertFalse($handler->classicFetchCalled,
			'a successful blobId fetch must never fall through to the classic (and, for a real '.
			'JMAP row, broken) IMAP fetch');
	}

	public function testOrphanedBlobFallsThroughToClassicFetch() : void
	{
		$handler = $this->handler();
		// "upload:" shaped but no backing file - AttachmentJmap::fetchBlobBytes() returns null,
		// same as a real expired/gone blob
		$blobId = 'upload:'.bin2hex(random_bytes(16));

		$result = $handler->resolveAttachmentBytes('1', 'uid123', 'INBOX', '2', false,
			$blobId, 'name.txt', 'text/plain');

		$this->assertTrue($handler->classicFetchCalled,
			'an unresolvable blob must fall through to the classic fetch, best-effort');
		$this->assertSame(['uid123', 'INBOX', '2', false], $handler->classicFetchArgs);
		$this->assertSame($handler->classicFetchReturn, $result);
	}

	public function testNoBlobIdAtAllGoesStraightToClassicFetch() : void
	{
		$handler = $this->handler();

		$result = $handler->resolveAttachmentBytes('1', 'uid123', 'INBOX', '2', false,
			null, null, null);

		$this->assertTrue($handler->classicFetchCalled);
		$this->assertSame(['uid123', 'INBOX', '2', false], $handler->classicFetchArgs);
		$this->assertSame($handler->classicFetchReturn, $result);
	}

	/**
	 * The exact "open this attachment" URL shape AttachmentJmap::createAttachmentBlock() builds -
	 * a real, non-stubbed round trip through the actual fetchBlobBytes() fix, not just this
	 * class's own dispatch decision (which the tests above already isolate that from).
	 */
	public function testRealShimBlobIdRoundTrip() : void
	{
		$handler = $this->handler();
		$blobId = $this->writeUploadFixture('a real shim-shaped upload blob');

		$result = $handler->resolveAttachmentBytes('42', null, null, null, false,
			$blobId, 'shim-attachment.bin', 'application/octet-stream');

		$this->assertSame('a real shim-shaped upload blob', $result['attachment']);
		$this->assertFalse($handler->classicFetchCalled);
	}
}

/**
 * Stubs out classicAttachmentFetch() (the one piece that needs a real IMAP connection) so
 * resolveAttachmentBytes()'s own blobId-vs-classic DISPATCH DECISION can be tested in isolation -
 * same reasoning/pattern as AttachmentLinksBodyTestFixtureCompose's _getAttachmentLinks() stub.
 */
class ResolveAttachmentBytesTestFixtureHandler extends MessageDisplayHandler
{
	public bool $classicFetchCalled = false;
	public array $classicFetchArgs = [];
	public array $classicFetchReturn = [
		'attachment' => 'classic-fetch-bytes',
		'name'       => null,
		'filename'   => 'classic-fallback-name.bin',
		'type'       => 'application/octet-stream',
		'charset'    => 'utf-8',
	];

	protected function classicAttachmentFetch(?string $uid, ?string $mailbox, ?string $part, $is_winmail) : array
	{
		$this->classicFetchCalled = true;
		$this->classicFetchArgs = [$uid, $mailbox, $part, $is_winmail];
		return $this->classicFetchReturn;
	}

	/** resolveAttachmentBytes() is `protected` (an internal implementation detail, not a real API surface) - this test-only public wrapper reaches it without widening that. */
	public function resolveAttachmentBytes(?string $icServerID, ?string $uid, ?string $mailbox, ?string $part,
		$is_winmail, ?string $blobId, ?string $name, ?string $type) : array
	{
		return parent::resolveAttachmentBytes($icServerID, $uid, $mailbox, $part, $is_winmail, $blobId, $name, $type);
	}
}
