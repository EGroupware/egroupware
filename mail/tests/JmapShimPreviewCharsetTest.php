<?php
/**
 * Follow-up to JmapShimFetchBodyValueCharsetTest.php (2026-09-18, ralf): Api\Mail\Jmap\Imap::
 * preview() - the message-LIST "Sneak preview" snippet - had the identical undeclared-charset
 * bug as fetchBodyValue()/structureToHtml(), just never wired through decodePartJsonSafe() at
 * all. It also had a second, independent bug found while fixing the first: the singlepart
 * fallback branch (Horde_Imap_Client_Fetch_Query::bodyText(), unlike bodyPart(), has no 'decode'
 * option) never reversed the Content-Transfer-Encoding either, so a base64/quoted-printable
 * singlepart message previewed as raw transfer-encoded gibberish.
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap as JmapShim;

class JmapShimPreviewCharsetTest extends \PHPUnit\Framework\TestCase
{
	private function multipartStructureWithPlainPart(?string $charset) : \Horde_Mime_Part
	{
		$root = new \Horde_Mime_Part();
		$root->setType('multipart/mixed');
		$plain = new \Horde_Mime_Part();
		$plain->setType('text/plain');
		if ($charset !== null)
		{
			$plain->setContentTypeParameter('charset', $charset);
		}
		$root->addPart($plain);
		$root->buildMimeIds();
		return $root;
	}

	private function stubImapForPart(string $partId, string $rawBytes, string $decode) : \Horde_Imap_Client_Socket
	{
		$imap = $this->createStub(\Horde_Imap_Client_Socket::class);
		$fetchFixture = new \Horde_Imap_Client_Data_Fetch();
		$fetchFixture->setBodyPart($partId, $rawBytes, $decode);
		$imap->method('fetch')->willReturn([1 => $fetchFixture]);
		return $imap;
	}

	/**
	 * The multipart branch's own real repro shape - no charset declared on the text/plain part,
	 * raw Windows-1252 bytes, same as this ticket's original html-body bug.
	 */
	public function testMultipartUndeclaredCharsetPreviewDecodesViaDetection()
	{
		$structure = $this->multipartStructureWithPlainPart(null);
		$plainId = $structure->findBody('plain');
		$raw = "Gesamtsumme betr\xe4gt: 63,98 EUR";
		$imap = $this->stubImapForPart($plainId, $raw, '8bit');

		$emptyData = new \Horde_Imap_Client_Data_Fetch(); // never reached - multipart branch wins

		$preview = JmapShim::preview($imap, 'INBOX', '1', $structure, $emptyData);

		$this->assertTrue(mb_check_encoding($preview, 'UTF-8'));
		$this->assertSame('Gesamtsumme beträgt: 63,98 EUR', $preview);
	}

	/**
	 * A declared charset must still be honoured exactly as before.
	 */
	public function testMultipartDeclaredCharsetPreviewIsHonoured()
	{
		$structure = $this->multipartStructureWithPlainPart('iso-8859-1');
		$plainId = $structure->findBody('plain');
		$raw = "caf\xe9"; // iso-8859-1 "é"
		$imap = $this->stubImapForPart($plainId, $raw, '8bit');

		$preview = JmapShim::preview($imap, 'INBOX', '1', $structure, new \Horde_Imap_Client_Data_Fetch());

		$this->assertSame('café', $preview);
	}

	/**
	 * The singlepart fallback (Horde_Imap_Client_Fetch_Query::bodyText() has no 'decode' option,
	 * unlike bodyPart()) - a base64-transfer-encoded singlepart message must still be reverse-
	 * transfer-decoded before the preview is built, not shown as raw base64 text.
	 */
	public function testSinglepartBase64BodyTextGetsTransferDecoded()
	{
		$structure = new \Horde_Mime_Part();
		$structure->setType('text/plain');
		$structure->setTransferEncoding('base64');

		$imap = $this->createStub(\Horde_Imap_Client_Socket::class);
		$data = new \Horde_Imap_Client_Data_Fetch();
		$data->setBodyText(0, base64_encode('Hello world, plain ascii body'));

		$preview = JmapShim::preview($imap, 'INBOX', '1', $structure, $data);

		$this->assertSame('Hello world, plain ascii body', $preview);
	}

	/**
	 * Same undeclared-charset repro as the multipart test above, but for a genuinely singlepart
	 * message (no multipart wrapper at all) - the OTHER place preview() builds its snippet from.
	 */
	public function testSinglepartUndeclaredCharsetBodyTextDecodesViaDetection()
	{
		$structure = new \Horde_Mime_Part();
		$structure->setType('text/plain');
		$structure->setTransferEncoding('8bit');

		$imap = $this->createStub(\Horde_Imap_Client_Socket::class);
		$data = new \Horde_Imap_Client_Data_Fetch();
		$data->setBodyText(0, "Gesamtsumme betr\xe4gt: 63,98 EUR");

		$preview = JmapShim::preview($imap, 'INBOX', '1', $structure, $data);

		$this->assertTrue(mb_check_encoding($preview, 'UTF-8'));
		$this->assertSame('Gesamtsumme beträgt: 63,98 EUR', $preview);
	}
}
