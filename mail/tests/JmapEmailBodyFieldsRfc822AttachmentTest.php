<?php
/**
 * EGroupware Mail: JmapShim::emailBodyFields() must not list a message/rfc822 attachment's OWN
 * internal sub-parts as separate top-level attachments of the containing message.
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap as JmapShim;
use Horde_Imap_Client_Data_Fetch;
use Horde_Imap_Client_Fetch_Results;
use Horde_Imap_Client_Socket;
use Horde_Mime_Part;

/**
 * Just enough of a Horde_Imap_Client_Socket to answer fetchBodyValue()'s own fetch() call for the
 * OUTER message's real text/plain+text/html body (needed so findBody() has something to resolve
 * to and this test stays a realistic "normal message with a forwarded attachment" shape) - never
 * touches a real connection, no login/network involved.
 */
class FakeImapSocketForEmailBodyFieldsTest extends Horde_Imap_Client_Socket
{
	/** @var array<string,string> partId => raw text */
	public array $bodyTexts = [];

	public function __construct()
	{
		// deliberately skip the real constructor (needs real connection params)
	}

	public function fetch($mailbox, $query, array $options = array())
	{
		$results = new Horde_Imap_Client_Fetch_Results();
		$data = new Horde_Imap_Client_Data_Fetch();
		foreach ($this->bodyTexts as $partId => $text)
		{
			$data->setBodyPart($partId, $text);
		}
		$results[(int)$options['ids']->ids[0]] = $data;
		return $results;
	}
}

/**
 * Found live 2026-09-25 (ralf): a genuine forward-as-attachment (a real GitHub notification
 * carried whole, itself multipart/alternative with its own text/plain+text/html) listed THREE
 * "attachments" in the popup - the real .eml itself, plus its own two internal alternative parts,
 * surfaced as "Unknown_Part2.1.txt"/"Unknown_Part2.2.htm". Root cause: emailBodyFields()'s
 * `partIterator()` walk is flat/recursive over the WHOLE MIME tree with no exclusion for a
 * message/rfc822 part's own descendants - Mail::getMessageAttachments() (the classic,
 * non-shim path) already solves this exact problem via its own $skipParts bookkeeping; this
 * mirrors that fix for the shim's JMAP-native emailBodyFields().
 *
 * Deliberately a real Horde_Mime_Part tree (not a plain array) - partIterator()/contentTypeMap()/
 * getMimeId() are genuine Horde behaviour this test relies on being exercised for real, not
 * re-implemented. findBody() resolves the OUTER message's own top-level text/plain+text/html (via
 * FakeImapSocketForEmailBodyFieldsTest above, answering fetchBodyValue()'s fetch() call) - the
 * nested message's own duplicate-shaped alternative parts are never fetched as body text at all,
 * only (mis)classified as attachments, which is exactly this bug.
 */
class JmapEmailBodyFieldsRfc822AttachmentTest extends \PHPUnit\Framework\TestCase
{
	private static function textPart(string $type, string $content) : Horde_Mime_Part
	{
		$part = new Horde_Mime_Part();
		$part->setType($type);
		$part->setContents($content);
		return $part;
	}

	public function testForwardedMessageIsOneAttachmentNotThree() : void
	{
		$outerText = self::textPart('text/plain', 'Test');
		$outerHtml = self::textPart('text/html', '<div>Test</div>');
		$outerAlt = new Horde_Mime_Part();
		$outerAlt->setType('multipart/alternative');
		$outerAlt->addPart($outerText);
		$outerAlt->addPart($outerHtml);

		$nestedText = self::textPart('text/plain', 'nested plain body');
		$nestedHtml = self::textPart('text/html', '<p>nested html body</p>');
		$nestedAlt = new Horde_Mime_Part();
		$nestedAlt->setType('multipart/alternative');
		$nestedAlt->addPart($nestedText);
		$nestedAlt->addPart($nestedHtml);

		$rfc822 = new Horde_Mime_Part();
		$rfc822->setType('message/rfc822');
		$rfc822->addPart($nestedAlt);

		$outer = new Horde_Mime_Part();
		$outer->setType('multipart/mixed');
		$outer->addPart($outerAlt);
		$outer->addPart($rfc822);

		$imap = new FakeImapSocketForEmailBodyFieldsTest();
		$imap->bodyTexts = [$outerText->getMimeId() => 'Test', $outerHtml->getMimeId() => '<div>Test</div>'];

		$fields = JmapShim::emailBodyFields($imap, 'INBOX', '185553', $outer);

		$attachmentIds = array_map(static fn($a) => $a['partId'], $fields['attachments']);
		$this->assertSame([$rfc822->getMimeId()], $attachmentIds,
			"only the message/rfc822 part itself must be listed - not its own internal alternative parts");
		$this->assertSame('message/rfc822', strtolower($fields['attachments'][0]['type']));
	}

	/**
	 * Regression guard for a normal (non-forwarded) attachment sitting alongside a message/rfc822
	 * one - must still be listed normally, only the message/rfc822's OWN descendants get skipped.
	 */
	public function testOrdinaryAttachmentAlongsideForwardedMessageIsStillListed() : void
	{
		$outerText = self::textPart('text/plain', 'Test');

		$nestedText = self::textPart('text/plain', 'nested plain body');
		$rfc822 = new Horde_Mime_Part();
		$rfc822->setType('message/rfc822');
		$rfc822->addPart($nestedText);

		$pdf = new Horde_Mime_Part();
		$pdf->setType('application/pdf');
		$pdf->setContents('%PDF-1.4 fake');
		$pdf->setDisposition('attachment');
		$pdf->setName('invoice.pdf');

		$outer = new Horde_Mime_Part();
		$outer->setType('multipart/mixed');
		$outer->addPart($outerText);
		$outer->addPart($rfc822);
		$outer->addPart($pdf);

		$imap = new FakeImapSocketForEmailBodyFieldsTest();
		$imap->bodyTexts = [$outerText->getMimeId() => 'Test'];

		$fields = JmapShim::emailBodyFields($imap, 'INBOX', '185553', $outer);

		$attachmentIds = array_map(static fn($a) => $a['partId'], $fields['attachments']);
		sort($attachmentIds);
		$expected = [$rfc822->getMimeId(), $pdf->getMimeId()];
		sort($expected);
		$this->assertSame($expected, $attachmentIds);
	}
}
