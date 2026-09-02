<?php
/**
 * EGroupware Api: Test Api\Mail\Jmap\Imap::buildMailerFromEmailProperties()
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Jmap;

require_once realpath(__DIR__.'/../../LoggedInTest.php');

use EGroupware\Api;

/**
 * doc/ai/projects/mail-compose-jmap-migration.md's Step 2 (IMAP-shim EmailSubmission emulation) -
 * buildMailerFromEmailProperties() is the shim's own equivalent of classic
 * mail_compose::createMessage(), translating an RFC 8621 Email property set into Api\Mailer
 * builder calls. It's also where the real body-vs-attachment bug (found live 2026-08-31) actually
 * lived, and the only piece of Step 2 testable without a live IMAP/SMTP connection - it never
 * calls send()/appendMessage() itself, only builds + serializes MIME via getRaw().
 *
 * Uses acc_id=1 (doc/phpunit.xml's own "shared/'Everyone' mail account", present on every
 * install) purely to satisfy Api\Mailer's constructor (Mail\Account::read()) - never sends or
 * touches any mailbox, so this is safe against a real/live account.
 *
 * NOT covered here (needs a live or properly mocked Horde_Imap_Client_Socket, out of scope for a
 * first pass): appendRawMessage(), emailSet()'s 'create' handling, emailSubmissionSet(), the
 * "mailbox:uid:partId" blobId branch of readUploadedBlob()/download(). See this class' own
 * docblock and the "upload:<token>" scheme (upload()/readUploadedBlob()) for the parts that ARE
 * network-free and covered.
 */
class ImapBuildMailerTest extends Api\LoggedInTest
{
	private const ACC_ID = '1';

	private function invokeBuildMailer(array $email) : Api\Mailer
	{
		$method = new \ReflectionMethod(Imap::class, 'buildMailerFromEmailProperties');
		$method->setAccessible(true);
		return $method->invoke(null, self::ACC_ID, $email);
	}

	/** Writes a real "upload:<token>" blob file, the same shape Imap::upload() produces. */
	private function uploadBlob(string $content) : string
	{
		$token = bin2hex(random_bytes(8));
		$pathMethod = new \ReflectionMethod(Imap::class, 'uploadPath');
		$pathMethod->setAccessible(true);
		file_put_contents($pathMethod->invoke(null, $token), $content);
		return 'upload:'.$token;
	}

	/** Parses a raw RFC822 message back into a Horde_Mime_Part tree for structural assertions. */
	private function parse(string $raw) : \Horde_Mime_Part
	{
		return \Horde_Mime_Part::parseMessage($raw);
	}

	/** Finds the first part anywhere in the tree matching a predicate, depth-first. */
	private function findPart(\Horde_Mime_Part $part, callable $predicate) : ?\Horde_Mime_Part
	{
		if ($predicate($part))
		{
			return $part;
		}
		foreach ($part->getParts() as $child)
		{
			if (($found = $this->findPart($child, $predicate)) !== null)
			{
				return $found;
			}
		}
		return null;
	}

	public function testPlainTextBodyOnlyViaTextBodyShortcut()
	{
		$email = [
			'from' => [['email' => 'sender@example.org', 'name' => 'Sender']],
			'to' => [['email' => 'recipient@example.org']],
			'subject' => 'Plain test',
			'textBody' => [['partId' => 'body', 'type' => 'text/plain']],
			'bodyValues' => ['body' => ['value' => "Hello world"]],
		];
		$raw = $this->invokeBuildMailer($email)->getRaw(false);
		$structure = $this->parse($raw);

		$this->assertNull($this->findPart($structure, fn($p) => $p->getDisposition() === 'attachment'),
			'a plain no-attachment body must never produce an attachment part');
		$body = $this->findPart($structure, fn($p) => $p->getType() === 'text/plain');
		$this->assertNotNull($body, 'text/plain body part missing entirely');
		$this->assertSame('Hello world', trim($body->getContents()));
	}

	/**
	 * Regression test for the bug found live 2026-08-31: bodyPartToJmap() (a real Email/get
	 * response, eg. via emailSubmissionSet()'s own re-fetch) sets a `blobId` on EVERY part,
	 * body text included (RFC 8621-correct - every part gets one, not just attachments) -
	 * buildMailerFromEmailProperties() used to test `empty($part['blobId'])` to decide "is this
	 * body text or an attachment", wrongly routing both real body parts into the attachments
	 * bucket (addStringAttachment() with filename="attachment") instead of setBody()/
	 * setHtmlBody(), producing an empty leading part and a message that never delivered.
	 */
	public function testBodyPartsWithBlobIdAreNotTreatedAsAttachments()
	{
		$email = [
			'from' => [['email' => 'sender@example.org']],
			'to' => [['email' => 'recipient@example.org']],
			'subject' => 'HTML test',
			'bodyValues' => [
				'bodyText' => ['value' => 'plain version'],
				'body' => ['value' => '<p>html version</p>'],
			],
			'bodyStructure' => [
				'type' => 'multipart/alternative',
				'subParts' => [
					// simulates emailGet()'s own bodyPartToJmap() output - blobId present on a
					// real body part, the exact shape that triggered the bug
					['partId' => 'bodyText', 'type' => 'text/plain', 'blobId' => 'bWJveA==:123:1'],
					['partId' => 'body', 'type' => 'text/html', 'blobId' => 'bWJveA==:123:2'],
				],
			],
		];
		$raw = $this->invokeBuildMailer($email)->getRaw(false);
		$structure = $this->parse($raw);

		$this->assertNull($this->findPart($structure, fn($p) => $p->getDisposition() === 'attachment'),
			'body parts must never be sent as attachments, even when they carry a blobId');
		$plain = $this->findPart($structure, fn($p) => $p->getType() === 'text/plain');
		$html = $this->findPart($structure, fn($p) => $p->getType() === 'text/html');
		$this->assertNotNull($plain, 'text/plain body part missing');
		$this->assertNotNull($html, 'text/html body part missing');
		$this->assertSame('plain version', trim($plain->getContents()));
		$this->assertSame('<p>html version</p>', trim($html->getContents()));
	}

	public function testUploadBlobAttachmentIsAttachedNotInlinedIntoBody()
	{
		$blobId = $this->uploadBlob('attachment file content');
		$email = [
			'from' => [['email' => 'sender@example.org']],
			'to' => [['email' => 'recipient@example.org']],
			'subject' => 'With attachment',
			'bodyValues' => ['body' => ['value' => 'body text']],
			'bodyStructure' => [
				'type' => 'multipart/mixed',
				'subParts' => [
					['partId' => 'body', 'type' => 'text/plain'],
					['blobId' => $blobId, 'type' => 'text/plain', 'name' => 'notes.txt', 'disposition' => 'attachment'],
				],
			],
		];
		$raw = $this->invokeBuildMailer($email)->getRaw(false);
		$structure = $this->parse($raw);

		$body = $this->findPart($structure, fn($p) => $p->getType() === 'text/plain' && $p->getDisposition() !== 'attachment');
		$this->assertNotNull($body);
		$this->assertSame('body text', trim($body->getContents()));

		$attachment = $this->findPart($structure, fn($p) => $p->getDisposition() === 'attachment');
		$this->assertNotNull($attachment, 'attachment part missing');
		$this->assertSame('notes.txt', $attachment->getName());
		$this->assertSame('attachment file content', $attachment->getContents());
	}

	/**
	 * Regression test for a bug found live 2026-08-31: forward-as-attachment's carried
	 * message/rfc822 entry was actually sent (confirmed from the raw Sent-folder source), but with
	 * NO Content-Disposition header at all - so it never showed up as a visible "Attachments"
	 * block anywhere. addStringAttachment() (the path a `blobId`-shaped attachment always takes)
	 * does call setDisposition('attachment') unconditionally - this test exists to pin down exactly
	 * where that gets lost for a message/rfc822-typed attachment specifically.
	 */
	public function testMessageRfc822AttachmentGetsAttachmentDisposition()
	{
		$nested = "From: nested-from@example.org\r\nTo: nested-to@example.org\r\nSubject: nested\r\n"
			."Date: Mon, 1 Jan 2026 00:00:00 +0000\r\nMessage-ID: <nested@example.org>\r\n\r\nnested body\r\n";
		$blobId = $this->uploadBlob($nested);
		$email = [
			'from' => [['email' => 'sender@example.org']],
			'to' => [['email' => 'recipient@example.org']],
			'subject' => 'Fwd as attachment',
			'bodyValues' => ['body' => ['value' => 'body text']],
			'bodyStructure' => [
				'type' => 'multipart/mixed',
				'subParts' => [
					['partId' => 'body', 'type' => 'text/plain'],
					['blobId' => $blobId, 'type' => 'message/rfc822', 'name' => 'nested.eml', 'disposition' => 'attachment'],
				],
			],
		];
		$raw = $this->invokeBuildMailer($email)->getRaw(false);
		$structure = $this->parse($raw);

		$rfc822 = $this->findPart($structure, fn($p) => $p->getType() === 'message/rfc822');
		$this->assertNotNull($rfc822, 'message/rfc822 part missing entirely');
		$this->assertSame('attachment', $rfc822->getDisposition(),
			'message/rfc822 attachment must have Content-Disposition: attachment, found live 2026-08-31 that it was silently missing');
	}

	/**
	 * doc/ai/projects/mail-compose-jmap-migration.md's VFS-attach follow-up (2026-08-31, ralf:
	 * "leave the attachment on the EGroupware server... no round-trip via the client") -
	 * buildMailerFromEmailProperties() must read a `vfsPath` attachment directly off the Vfs
	 * stream wrapper (Api\Vfs::fopen(), fed as a resource into addAttachment()), never via
	 * readUploadedBlob()/an uploaded blob at all.
	 */
	public function testVfsPathAttachmentIsReadDirectlyFromVfs()
	{
		// VFS root isn't necessarily writable directly (eg. an S3-backed default filesystem) -
		// the logged-in test user's own home dir always is.
		$path = Api\Vfs::get_home_dir().'/tmp_phpunit_imap_buildmailer_'.uniqid().'.txt';
		file_put_contents(Api\Vfs::PREFIX.$path, 'vfs attachment content');
		try
		{
			$email = [
				'from' => [['email' => 'sender@example.org']],
				'to' => [['email' => 'recipient@example.org']],
				'subject' => 'With VFS attachment',
				'bodyValues' => ['body' => ['value' => 'body text']],
				'bodyStructure' => [
					'type' => 'multipart/mixed',
					'subParts' => [
						['partId' => 'body', 'type' => 'text/plain'],
						['vfsPath' => $path, 'type' => 'text/plain', 'name' => 'vfsfile.txt', 'disposition' => 'attachment'],
					],
				],
			];
			$raw = $this->invokeBuildMailer($email)->getRaw(false);
			$structure = $this->parse($raw);

			$attachment = $this->findPart($structure, fn($p) => $p->getDisposition() === 'attachment');
			$this->assertNotNull($attachment, 'VFS attachment part missing');
			$this->assertSame('vfsfile.txt', $attachment->getName());
			$this->assertSame('vfs attachment content', $attachment->getContents());
		}
		finally
		{
			Api\Vfs::unlink($path);
		}
	}

	/** A vfsPath pointing at a non-existent file must be skipped, not fatal. */
	public function testMissingVfsPathAttachmentIsSkippedNotFatal()
	{
		$email = [
			'from' => [['email' => 'sender@example.org']],
			'to' => [['email' => 'recipient@example.org']],
			'subject' => 'Missing VFS attachment',
			'bodyValues' => ['body' => ['value' => 'body text']],
			'bodyStructure' => [
				'type' => 'multipart/mixed',
				'subParts' => [
					['partId' => 'body', 'type' => 'text/plain'],
					['vfsPath' => '/tmp_phpunit_does_not_exist_'.uniqid().'.txt', 'type' => 'text/plain',
						'name' => 'ghost.txt', 'disposition' => 'attachment'],
				],
			],
		];
		$raw = $this->invokeBuildMailer($email)->getRaw(false);
		$structure = $this->parse($raw);

		$this->assertNull($this->findPart($structure, fn($p) => $p->getDisposition() === 'attachment'));
		$body = $this->findPart($structure, fn($p) => $p->getType() === 'text/plain');
		$this->assertSame('body text', trim($body->getContents()));
	}

	/**
	 * Regression test for the bug caught before live-testing (2026-08-31): an earlier draft took
	 * an explicit $identityId param and resolved `from` via Account::read_identity(), ignoring the
	 * client's own explicit `from` property - would have silently sent every shim message under
	 * the account's default identity.
	 */
	public function testFromPropertyIsUsedDirectlyNotAccountDefaultIdentity()
	{
		$email = [
			'from' => [['email' => 'explicit-from@example.org', 'name' => 'Explicit From']],
			'to' => [['email' => 'recipient@example.org']],
			'subject' => 'From test',
			'bodyValues' => ['body' => ['value' => 'x']],
		];
		$raw = $this->invokeBuildMailer($email)->getRaw(false);

		$this->assertMatchesRegularExpression('/^From:.*explicit-from@example\.org/mi', $raw);
	}

	public function testEmptyCcBccAreOmittedNotSentAsBlankHeaders()
	{
		$email = [
			'from' => [['email' => 'sender@example.org']],
			'to' => [['email' => 'recipient@example.org']],
			'subject' => 'No cc/bcc',
			'bodyValues' => ['body' => ['value' => 'x']],
		];
		$raw = $this->invokeBuildMailer($email)->getRaw(false);

		$this->assertDoesNotMatchRegularExpression('/^Cc:/mi', $raw);
		$this->assertDoesNotMatchRegularExpression('/^Bcc:/mi', $raw);
	}

	/** inReplyTo/references must be wrapped in angle brackets, matching RFC 5322. */
	public function testThreadingHeadersAreAngleBracketWrapped()
	{
		$email = [
			'from' => [['email' => 'sender@example.org']],
			'to' => [['email' => 'recipient@example.org']],
			'subject' => 'Re: threading',
			'inReplyTo' => ['original-message-id@example.org'],
			'references' => ['first@example.org', 'original-message-id@example.org'],
			'bodyValues' => ['body' => ['value' => 'x']],
		];
		$raw = $this->invokeBuildMailer($email)->getRaw(false);

		$this->assertMatchesRegularExpression('/^In-Reply-To: <original-message-id@example\.org>/mi', $raw);
		$this->assertMatchesRegularExpression(
			'/^References: <first@example\.org> <original-message-id@example\.org>/mi', $raw);
	}

	/**
	 * doc/ai/projects/mail-compose-jmap-migration.md's S/MIME encrypt-only/sign+encrypt design
	 * (2026-08-27): createDraftEmail()'s bodyOverride swaps a `{type: 'application/pkcs7-mime',
	 * blobId}` single opaque leaf in for bodyValues/textBody/htmlBody entirely - meant to BE the
	 * entire message body verbatim, not one attachment among others.
	 *
	 * Regression test for a real bug found live 2026-09-02 investigating whether encrypt-only/
	 * sign+encrypt actually work against the IMAP-shim account (never live-tested there, only
	 * against Stalwart, which never reaches this method at all - Email/set create is handled
	 * natively there): before this method special-cased this exact shape, a blobId part with no
	 * matching bodyValues entry always fell into the generic $collect()/addAttachmentPart() path,
	 * and Api\Mailer::_send() (via getRaw()'s own null-transport fallback) unconditionally wraps
	 * $this->_parts together with an always-truthy (even when empty) placeholder body part in a
	 * fresh multipart/mixed container - a received shim-sent encrypted mail showed exactly that:
	 * multipart/mixed containing an empty application/octet-stream leaf, a second empty
	 * "attachment" leaf, and the REAL pkcs7-mime bytes as a THIRD, attachment-dispositioned part,
	 * instead of the message's own top-level Content-Type simply BEING application/pkcs7-mime.
	 */
	public function testSmimeEncryptedBodyOverrideProducesOpaqueTopLevelPart()
	{
		$blobId = $this->uploadBlob('opaque pkcs7-mime bytes (would be real ciphertext live)');
		$email = [
			'from' => [['email' => 'sender@example.org']],
			'to' => [['email' => 'recipient@example.org']],
			'subject' => 'S/MIME encrypted',
			'bodyStructure' => ['type' => 'application/pkcs7-mime', 'blobId' => $blobId],
		];
		$raw = $this->invokeBuildMailer($email)->getRaw(false);
		$structure = $this->parse($raw);

		$this->assertEquals('application/pkcs7-mime', strtolower($structure->getType()),
			'the message itself must be application/pkcs7-mime directly, not wrapped in multipart/mixed');
		$this->assertEmpty($structure->getParts(), 'must be a single opaque leaf, no sub-parts at all');
		$this->assertSame('opaque pkcs7-mime bytes (would be real ciphertext live)', $structure->getContents());
	}
}
