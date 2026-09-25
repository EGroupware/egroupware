<?php
/**
 * EGroupware Mail: tests for the "bare" special case (ticket #125171) - a message whose ENTIRE
 * content is one application/pdf or image part, no multipart wrapper, no separate text/plain or
 * text/html body anywhere at all (found live: SAP NetWeaver sends an order PDF as a bare
 * top-level "Content-Type: application/pdf", nothing else).
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap as JmapShim;
use PHPUnit\Framework\Attributes\DataProvider;

/**
 * JmapShim::specialCaseType()'s "bare" detection - the dispatch decision that routes a message
 * through structureToHtml() (already covered for its own rendering by StructureToHtmlTest.php)
 * instead of the normal emailBodyFields()/assembleBodyHtml() path, which would otherwise show a
 * literally blank body (findBody() finds nothing to show, and assembleBodyHtml()'s own DOMPurify
 * config FORBID_TAGS includes 'embed'/'iframe'/'object', so a raw data: URI embed could never
 * survive that path even if something tried to synthesize one there - this "bare" case MUST route
 * through the same special-case mechanism S/MIME/TNEF already use, which bypasses client-side
 * sanitization entirely since the HTML is server-constructed from base64-encoded bytes only, not
 * attacker-controlled markup). See BareContentShimFetchTest.php/BareContentStalwartResolveTest.php
 * for the actual fetch->render chain each backend performs once this dispatch fires.
 *
 * Deliberately pure, no Horde_Imap_Client connection needed - specialCaseType() only inspects the
 * plain array shape bodyPartToJmap()/Email/get already produce.
 */
class BareContentSpecialCaseTest extends \PHPUnit\Framework\TestCase
{
	public static function bareShapesProvider() : array
	{
		return [
			'bare pdf' => [['type' => 'application/pdf'], 'bare'],
			'bare pdf, uppercase type' => [['type' => 'APPLICATION/PDF'], 'bare'],
			'bare png' => [['type' => 'image/png'], 'bare'],
			'bare jpeg' => [['type' => 'image/jpeg'], 'bare'],
		];
	}

	#[DataProvider('bareShapesProvider')]
	public function testBareWholeMessageIsDetected(array $bodyStructure, string $expected) : void
	{
		$this->assertSame($expected, JmapShim::specialCaseType($bodyStructure));
	}

	/**
	 * The critical negative case: a NORMAL multipart/mixed message with a proper text body PLUS a
	 * pdf/image ATTACHMENT must never be routed through the bare-content special case - its
	 * top-level type is multipart/mixed, and it has subParts, so this must return null (normal
	 * emailBodyFields()/assembleBodyHtml() path) regardless of what any of its children are.
	 * Getting this wrong would send an extra ajax_resolveSpecialCaseBody() round trip for every
	 * ordinary message with a pdf/image attachment - an extremely common shape.
	 */
	public function testMultipartMessageWithPdfAttachmentIsNotBare() : void
	{
		$bodyStructure = [
			'type' => 'multipart/mixed',
			'subParts' => [
				['type' => 'text/plain'],
				['type' => 'application/pdf'],
			],
		];
		$this->assertNull(JmapShim::specialCaseType($bodyStructure));
	}

	public function testPlainTextMessageIsNotBare() : void
	{
		$this->assertNull(JmapShim::specialCaseType(['type' => 'text/plain']));
	}

	public function testBareNonInlineableTypeIsNotBare() : void
	{
		// a bare, bodyless message that is neither pdf nor image (eg. some other attachment type
		// with no MIME wrapper) - matches structureToHtml()'s own fallback for this shape (returns
		// empty body rather than embedding something nonsensical)
		$this->assertNull(JmapShim::specialCaseType(['type' => 'application/octet-stream']));
	}

	// --- regression guards: the pre-existing smime/tnef special cases must stay unaffected ------

	public function testSmimeStillDetected() : void
	{
		$this->assertSame('smime', JmapShim::specialCaseType(['type' => 'application/pkcs7-mime']));
	}

	public function testTnefStillDetected() : void
	{
		$this->assertSame('tnef', JmapShim::specialCaseType(['type' => 'application/ms-tnef']));
	}

	public function testSmimeSignedStillDetected() : void
	{
		$bodyStructure = [
			'type' => 'multipart/signed',
			'subParts' => [
				['type' => 'text/plain'],
				['type' => 'application/pkcs7-signature'],
			],
		];
		$this->assertSame('smime', JmapShim::specialCaseType($bodyStructure));
	}
}
