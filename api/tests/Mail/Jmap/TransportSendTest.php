<?php
/**
 * EGroupware Api: Test Api\Mail\Jmap\Transport, the JMAP EmailSubmission Horde_Mail_Transport
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Jmap;

use EGroupware\Api\Mail;

/**
 * doc/ai/projects/mail-test-coverage.md's priority-3 entry: Transport.php's sendJmap()/
 * resolveMailboxesAndIdentities() had only an assertInstanceOf smoke test.
 *
 * jmapClient() itself (which opens a real HTTP/IMAP connection) is bypassed entirely - `$jmap`
 * is a protected property, injected directly via Reflection with a fake Http subclass (a Base
 * anonymous-class fake was not possible here: Transport's own $jmap property is typed to the
 * CONCRETE Http class, not an interface, so the fake must actually extend it). The fake's own
 * $types map still points at the REAL Mailbox/Email/EmailSubmission Type classes, so
 * $jmap->mailbox->get() etc. exercise that real, already-tested translation layer too - only the
 * single call()/jmapCall()/uploadBlob() entry points are faked, same "fake session" approach as
 * EmailTest.php/EmailSubmissionTest.php.
 *
 * sendJmap()/resolveMailboxesAndIdentities() are both private - invoked via ReflectionMethod, same
 * pattern as ImapBuildMailerTest.php's invokeBuildMailer().
 *
 * Real Horde_Mime_Mail/Horde_Mime_Part objects build the $recipients/$headers/$body triple exactly
 * the way Api\Mailer really does (Horde_Mime_Part::send() -> $mailer->send(...)), rather than
 * hand-crafting raw MIME text - this also naturally exercises the Bcc-inference-by-diff branch
 * (Horde_Mime_Mail never puts Bcc in the transmitted headers, only in $recipients).
 *
 * Uses acc_id=0 for Mail\Account (its own constructor skips every DB read entirely for a
 * non-positive acc_id - see its own "tracker_mailhandling instantiates class without our
 * database" comment) - Transport's own error-message strings are the only place this instance is
 * ever touched.
 */
class TransportSendTest extends \PHPUnit\Framework\TestCase
{
	private function fakeHttp(callable $callResponder) : Http
	{
		return new class($callResponder) extends Http {
			public array $calls = [];
			public array $uploadedBlobs = [];
			public function __construct(private $callResponder)
			{
				$this->accountId = 'acc1';
			}
			public function call(string $method, array $args) : array
			{
				$this->calls[] = [$method, $args];
				return ($this->callResponder)($method, $args, count($this->calls));
			}
			public function uploadBlob(string $raw, string $type='message/rfc822') : string
			{
				$this->uploadedBlobs[] = [$raw, $type];
				return 'blob-'.count($this->uploadedBlobs);
			}
		};
	}

	private function transport(Http $jmap) : Transport
	{
		$account = new Mail\Account(['acc_id' => 0]);
		$transport = new Transport($account);
		$prop = new \ReflectionProperty(Transport::class, 'jmap');
		$prop->setAccessible(true);
		$prop->setValue($transport, $jmap);
		return $transport;
	}

	private function invokeSendJmap(Transport $transport, $recipients, array $headers, $body) : void
	{
		$method = new \ReflectionMethod(Transport::class, 'sendJmap');
		$method->setAccessible(true);
		$method->invoke($transport, $recipients, $headers, $body);
	}

	private function invokeResolveMailboxesAndIdentities(Transport $transport, Http $jmap) : array
	{
		$method = new \ReflectionMethod(Transport::class, 'resolveMailboxesAndIdentities');
		$method->setAccessible(true);
		return $method->invoke($transport, $jmap);
	}

	/** Default fake call() responder covering every JMAP call sendJmap() makes on a happy path. */
	private function happyPathResponder(array $overrides = []) : callable
	{
		$defaults = [
			'Mailbox/get' => ['list' => [['id' => 'drafts-id', 'role' => 'drafts'], ['id' => 'sent-id', 'role' => 'sent']]],
			'Identity/get' => ['list' => [['id' => 'ident1', 'email' => 'sender@example.org', 'name' => 'Sender Name']]],
			'Email/set' => ['created' => ['s1' => ['id' => 'new-email-id']]],
			'EmailSubmission/set' => ['created' => ['s1' => ['id' => 'sub1']]],
		];
		$responses = array_merge($defaults, $overrides);
		return function($method, $args) use ($responses) { return $responses[$method] ?? []; };
	}

	private function buildAndSend(Transport $transport, \Horde_Mime_Mail $mail) : void
	{
		$mail->send($transport);
	}

	// --- resolveMailboxesAndIdentities() ---

	public function testResolveMailboxesAndIdentitiesFindsDraftsAndSentByRoleAndFetchesRealIdentities()
	{
		$jmap = $this->fakeHttp($this->happyPathResponder());
		$transport = $this->transport($jmap);

		[$drafts_id, $sent_id, $identities] = $this->invokeResolveMailboxesAndIdentities($transport, $jmap);

		$this->assertSame('drafts-id', $drafts_id);
		$this->assertSame('sent-id', $sent_id);
		$this->assertSame([['id' => 'ident1', 'email' => 'sender@example.org', 'name' => 'Sender Name']], $identities);
	}

	public function testResolveMailboxesAndIdentitiesFetchesIdentitiesViaARawCallNotTheIdentityType()
	{
		$jmap = $this->fakeHttp($this->happyPathResponder());
		$transport = $this->transport($jmap);

		$this->invokeResolveMailboxesAndIdentities($transport, $jmap);

		$this->assertSame('Identity/get', $jmap->calls[1][0],
			"must bypass Mail\\Jmap\\Identity's local-synthesis override - that returns OUR ident_id, which EmailSubmission/set would reject");
	}

	public function testResolveMailboxesAndIdentitiesThrowsWhenDraftsRoleIsMissing()
	{
		$jmap = $this->fakeHttp($this->happyPathResponder([
			'Mailbox/get' => ['list' => [['id' => 'sent-id', 'role' => 'sent']]],
		]));
		$transport = $this->transport($jmap);

		$this->expectException(\Horde_Mail_Exception::class);
		$this->invokeResolveMailboxesAndIdentities($transport, $jmap);
	}

	public function testResolveMailboxesAndIdentitiesThrowsWhenNoIdentityIsReturned()
	{
		$jmap = $this->fakeHttp($this->happyPathResponder(['Identity/get' => ['list' => []]]));
		$transport = $this->transport($jmap);

		$this->expectException(\Horde_Mail_Exception::class);
		$this->invokeResolveMailboxesAndIdentities($transport, $jmap);
	}

	// --- sendJmap() ---

	public function testSendJmapThrowsWhenThereIsNoFromAddress()
	{
		$jmap = $this->fakeHttp($this->happyPathResponder());
		$transport = $this->transport($jmap);

		$this->expectException(\Horde_Mail_Exception::class);
		$this->invokeSendJmap($transport, 'to@example.org', ['To' => 'to@example.org'], 'body');
	}

	public function testSendJmapBuildsToAndCcFromHeadersAndBodyValuesFromASimplePlainTextMessage()
	{
		$jmap = $this->fakeHttp($this->happyPathResponder());
		$transport = $this->transport($jmap);
		$mail = new \Horde_Mime_Mail();
		$mail->addHeader('From', 'Sender Name <sender@example.org>');
		$mail->addHeader('To', 'Recipient <to@example.org>');
		$mail->addHeader('Cc', 'cc@example.org');
		$mail->addHeader('Subject', 'Test subject');
		$mail->setBody('Hello world');

		$this->buildAndSend($transport, $mail);

		$create = $this->findCall($jmap, 'Email/set')[1]['create']['s1'];
		$this->assertSame([['email' => 'to@example.org', 'name' => 'Recipient']], $create['to']);
		$this->assertSame([['email' => 'cc@example.org']], $create['cc']);
		$this->assertSame('Test subject', $create['subject']);
		$this->assertSame("Hello world\n", $create['bodyValues']['plain']['value']);
		$this->assertSame([['partId' => 'plain', 'type' => 'text/plain']], $create['textBody']);
		$this->assertArrayNotHasKey('htmlBody', $create);
	}

	/**
	 * Regression test for a real bug found live 2026-09-09 while writing this coverage:
	 * Horde_Mime_Part::send()'s own toString(['stream' => true]) call - the actual mechanism that
	 * builds $body for every real send - leaves the stream's pointer at its END (it was just
	 * WRITTEN there, not read), never at the start. Without rewind() first, sendJmap() silently
	 * parsed an EMPTY body (and found zero attachments) for every real JMAP-transport send -
	 * confirmed against Horde's own Horde_Mail_Transport_Mock::send(), which already does this
	 * same rewind() first, for the identical reason. This test builds $body as a stream and
	 * deliberately reads it to EOF before calling sendJmap(), matching the real, normal case.
	 */
	public function testSendJmapRewindsAStreamBodyThatArrivesAlreadyAtEndOfFile()
	{
		$jmap = $this->fakeHttp($this->happyPathResponder());
		$transport = $this->transport($jmap);
		$stream = fopen('php://temp', 'r+');
		fwrite($stream, 'Hello world');
		// deliberately leave the pointer at EOF, matching Horde_Mime_Part::send()'s own stream

		$this->invokeSendJmap($transport, 'to@example.org',
			['From' => 'sender@example.org', 'To' => 'to@example.org', 'Subject' => 'x', 'Content-Type' => 'text/plain'],
			$stream);

		$create = $this->findCall($jmap, 'Email/set')[1]['create']['s1'];
		$this->assertSame('Hello world', $create['bodyValues']['plain']['value']);
	}

	public function testSendJmapInfersBccByDiffingRecipientsAgainstToAndCcWhenNoBccHeaderIsPresent()
	{
		$jmap = $this->fakeHttp($this->happyPathResponder());
		$transport = $this->transport($jmap);

		$this->invokeSendJmap($transport, ['to@example.org', 'cc@example.org', 'bcc-only@example.org'],
			['From' => 'sender@example.org', 'To' => 'to@example.org', 'Cc' => 'cc@example.org', 'Subject' => 'x'],
			"Content-Type: text/plain\r\n\r\nbody");

		$create = $this->findCall($jmap, 'Email/set')[1]['create']['s1'];
		$this->assertSame([['email' => 'bcc-only@example.org']], $create['bcc']);
	}

	public function testSendJmapUsesAnExplicitBccHeaderDirectlyInsteadOfInferringFromRecipients()
	{
		$jmap = $this->fakeHttp($this->happyPathResponder());
		$transport = $this->transport($jmap);
		$mail = new \Horde_Mime_Mail();
		$mail->addHeader('From', 'sender@example.org');
		$mail->addHeader('To', 'to@example.org');
		$mail->addHeader('Bcc', 'explicit-bcc@example.org');
		$mail->addHeader('Subject', 'x');
		$mail->setBody('body');

		$this->buildAndSend($transport, $mail);

		$create = $this->findCall($jmap, 'Email/set')[1]['create']['s1'];
		$this->assertSame([['email' => 'explicit-bcc@example.org']], $create['bcc']);
	}

	public function testSendJmapUploadsARegularAttachmentAsABlobWithNameAndDisposition()
	{
		$jmap = $this->fakeHttp($this->happyPathResponder());
		$transport = $this->transport($jmap);
		$mail = new \Horde_Mime_Mail();
		$mail->addHeader('From', 'sender@example.org');
		$mail->addHeader('To', 'to@example.org');
		$mail->addHeader('Subject', 'x');
		$mail->setBody('body');
		$part = new \Horde_Mime_Part();
		$part->setType('text/plain');
		$part->setContents('attachment content');
		$part->setName('note.txt');
		$part->setDisposition('attachment');
		$mail->addMimePart($part);

		$this->buildAndSend($transport, $mail);

		$this->assertCount(1, $jmap->uploadedBlobs);
		$this->assertSame('attachment content', $jmap->uploadedBlobs[0][0]);
		$create = $this->findCall($jmap, 'Email/set')[1]['create']['s1'];
		$this->assertCount(1, $create['attachments']);
		$this->assertSame('blob-1', $create['attachments'][0]['blobId']);
		$this->assertSame('note.txt', $create['attachments'][0]['name']);
		$this->assertSame('attachment', $create['attachments'][0]['disposition']);
	}

	public function testSendJmapPrefersTheIdentityMatchingTheFromAddressCaseInsensitively()
	{
		$jmap = $this->fakeHttp($this->happyPathResponder([
			'Identity/get' => ['list' => [
				['id' => 'other-ident', 'email' => 'other@example.org'],
				['id' => 'matching-ident', 'email' => 'Sender@Example.ORG'],
			]],
		]));
		$transport = $this->transport($jmap);

		$this->invokeSendJmap($transport, 'to@example.org',
			['From' => 'sender@example.org', 'To' => 'to@example.org', 'Subject' => 'x'],
			"Content-Type: text/plain\r\n\r\nbody");

		$submitArgs = $this->findCall($jmap, 'EmailSubmission/set')[1]['create']['s1'];
		$this->assertSame('matching-ident', $submitArgs['identityId']);
	}

	public function testSendJmapFallsBackToTheFirstIdentityWhenNoneMatchesTheFromAddress()
	{
		$jmap = $this->fakeHttp($this->happyPathResponder([
			'Identity/get' => ['list' => [['id' => 'first-ident', 'email' => 'unrelated@example.org']]],
		]));
		$transport = $this->transport($jmap);

		$this->invokeSendJmap($transport, 'to@example.org',
			['From' => 'sender@example.org', 'To' => 'to@example.org', 'Subject' => 'x'],
			"Content-Type: text/plain\r\n\r\nbody");

		$submitArgs = $this->findCall($jmap, 'EmailSubmission/set')[1]['create']['s1'];
		$this->assertSame('first-ident', $submitArgs['identityId']);
	}

	public function testSendJmapMovesTheEmailFromDraftsToSentAndMarksItSeenOnSuccessfulSubmission()
	{
		$jmap = $this->fakeHttp($this->happyPathResponder());
		$transport = $this->transport($jmap);

		$this->invokeSendJmap($transport, 'to@example.org',
			['From' => 'sender@example.org', 'To' => 'to@example.org', 'Subject' => 'x'],
			"Content-Type: text/plain\r\n\r\nbody");

		$submitCall = $this->findCall($jmap, 'EmailSubmission/set')[1];
		$this->assertSame('new-email-id', $submitCall['create']['s1']['emailId']);
		$this->assertSame([
			'mailboxIds/drafts-id' => null,
			'mailboxIds/sent-id' => true,
			'keywords/$draft' => null,
			'keywords/$seen' => true,
		], $submitCall['onSuccessUpdateEmail']['#s1']);
	}

	public function testSendJmapThrowsWhenEmailSetCreateFails()
	{
		$jmap = $this->fakeHttp($this->happyPathResponder([
			'Email/set' => ['notCreated' => ['s1' => ['type' => 'invalidProperties']]],
		]));
		$transport = $this->transport($jmap);

		$this->expectException(\Horde_Mail_Exception::class);
		$this->invokeSendJmap($transport, 'to@example.org',
			['From' => 'sender@example.org', 'To' => 'to@example.org', 'Subject' => 'x'],
			"Content-Type: text/plain\r\n\r\nbody");
	}

	public function testSendJmapThrowsWhenEmailSubmissionSetFails()
	{
		$jmap = $this->fakeHttp($this->happyPathResponder([
			'EmailSubmission/set' => ['notCreated' => ['s1' => ['type' => 'invalidProperties']]],
		]));
		$transport = $this->transport($jmap);

		$this->expectException(\Horde_Mail_Exception::class);
		$this->invokeSendJmap($transport, 'to@example.org',
			['From' => 'sender@example.org', 'To' => 'to@example.org', 'Subject' => 'x'],
			"Content-Type: text/plain\r\n\r\nbody");
	}

	// --- send() ---

	public function testSendRethrowsAHordeMailExceptionAsIs()
	{
		$jmap = $this->fakeHttp(function() { throw new \Horde_Mail_Exception('boom'); });
		$transport = $this->transport($jmap);

		try
		{
			$transport->send('to@example.org', ['From' => 'sender@example.org', 'To' => 'to@example.org'],
				"Content-Type: text/plain\r\n\r\nbody");
			$this->fail('expected a Horde_Mail_Exception');
		}
		catch (\Horde_Mail_Exception $e)
		{
			$this->assertSame('boom', $e->getMessage());
		}
	}

	public function testSendWrapsAnyOtherThrowableIntoAHordeMailException()
	{
		$jmap = $this->fakeHttp(function() { throw new \RuntimeException('unexpected'); });
		$transport = $this->transport($jmap);

		$this->expectException(\Horde_Mail_Exception::class);
		$transport->send('to@example.org', ['From' => 'sender@example.org', 'To' => 'to@example.org'],
			"Content-Type: text/plain\r\n\r\nbody");
	}

	/** Finds the first logged call() for a given JMAP method - $jmap->calls is [[method, args], ...]. */
	private function findCall($jmap, string $method) : array
	{
		foreach ($jmap->calls as $call)
		{
			if ($call[0] === $method) return $call;
		}
		$this->fail("no '$method' call was made");
	}
}
