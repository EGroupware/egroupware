<?php
/**
 * EGroupware Api: Mail\BodyDecoding tests
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License Version 2+
 */

namespace EGroupware\Api\Mail;

use PHPUnit\Framework\TestCase;

/**
 * Pure tests for Mail\BodyDecoding - no database/session/IMAP connection required.
 */
class BodyDecodingTest extends TestCase
{
	public function testWordwrapBreaksLongLines()
	{
		$result = BodyDecoding::wordwrap('one two three four five six seven eight nine ten', 20, "\n");

		$this->assertGreaterThan(1, substr_count($result, "\n"), 'a line longer than the wrap width should be split');
		foreach (['one', 'five', 'ten'] as $word)
		{
			$this->assertStringContainsString($word, $result);
		}
	}

	public function testWordwrapDoesNotBreakLinesContainingAHref()
	{
		$line = 'a very long line with an <a href="https://example.com/some/very/long/path">link</a> in it';

		$result = BodyDecoding::wordwrap($line, 20, "\n");

		$this->assertSame($line."\n", $result);
	}

	public function testNormalizeBodyPartsFlattensNestedArrays()
	{
		$nested = [
			['body' => 'first'],
			[['body' => 'second'], ['body' => 'third']],
		];

		$result = BodyDecoding::normalizeBodyParts($nested);

		$this->assertSame([['body' => 'first'], ['body' => 'second'], ['body' => 'third']], $result);
	}

	public function testNormalizeBodyPartsPassesThroughNonArrays()
	{
		$this->assertNull(BodyDecoding::normalizeBodyParts(null));
	}

	public function testGetCleanHTMLStripsHeadAndDoctype()
	{
		$html = "<!doctype html>\n<html><head><title>t</title></head><body><p>hello</p></body></html>";

		BodyDecoding::getCleanHTML($html);

		$this->assertStringNotContainsString('<!doctype', $html);
		$this->assertStringNotContainsString('<head>', $html);
		$this->assertStringContainsString('hello', $html);
	}

	public function testGetStylesExtractsStyleTagContent()
	{
		\EGroupware\Api\Mail::$displayCharset = 'utf-8';
		$bodyParts = [
			['body' => '<style>body { color: red; }</style>', 'mimeType' => 'text/html', 'charSet' => 'utf-8'],
		];

		$result = BodyDecoding::getStyles($bodyParts);

		$this->assertStringContainsString('color:', $result);
		$this->assertStringContainsString('red', $result);
	}

	public function testGetStylesReturnsEmptyStringForNoBodyParts()
	{
		$this->assertSame('', BodyDecoding::getStyles([]));
	}

	public function testHtmlentitiesEncodesWithExplicitCharset()
	{
		$this->assertSame('&lt;b&gt;', BodyDecoding::htmlentities('<b>', 'utf-8'));
	}

	// --- decodeIfStillBase64() (ticket #125171 follow-up) -------------------------------------

	/**
	 * The actual bug: a sender declares Content-Type but omits Content-Transfer-Encoding entirely,
	 * while the body is still literally base64 TEXT - found live via a real customer .eml (SAP
	 * NetWeaver, no Content-Transfer-Encoding header at all).
	 */
	public function testDecodesUndeclaredBase64Text()
	{
		$realBytes = "%PDF-1.4 fake but stable bytes for the test";
		$base64Text = chunk_split(base64_encode($realBytes));

		$this->assertSame($realBytes, BodyDecoding::decodeIfStillBase64($base64Text));
	}

	/**
	 * The critical negative case: genuine binary content must pass through completely unchanged -
	 * random bytes are virtually certain to contain at least one byte outside the base64 alphabet,
	 * so this must never even attempt a decode.
	 */
	public function testGenuineBinaryContentIsUnchanged()
	{
		$binary = "%PDF-1.4\r\n".random_bytes(200);

		$this->assertSame($binary, BodyDecoding::decodeIfStillBase64($binary));
	}

	public function testEmptyStringIsUnchanged()
	{
		$this->assertSame('', BodyDecoding::decodeIfStillBase64(''));
	}

	/**
	 * Text that merely LOOKS base64-alphabet-safe but doesn't decode to anything meaningful (or
	 * isn't validly padded) must be left alone rather than replaced with garbage.
	 */
	public function testTextWithInvalidBase64PaddingIsUnchanged()
	{
		$notBase64 = 'abcde'; // 5 chars - not a multiple of 4, invalid base64 length

		$this->assertSame($notBase64, BodyDecoding::decodeIfStillBase64($notBase64));
	}

	/**
	 * Memory-safety regression guard (ralf, live: "we must be careful not to exceed PHP
	 * memory_limit, as PDFs can be quite big!") - a large genuinely-binary attachment must be
	 * rejected via ONLY its cheap prefix check, never triggering the full-string preg_replace/
	 * base64_decode pass at all. Asserted via memory_get_peak_usage() rather than just timing -
	 * the whole point is that this must cost (essentially) nothing extra on top of whatever
	 * already held $big in memory, not just be "fast enough".
	 */
	public function testLargeGenuineBinaryContentNeverTriggersFullStringPass()
	{
		$big = "%PDF-1.4\r\n".random_bytes(20 * 1024 * 1024); // 20MB, a realistic large PDF size

		$before = memory_get_peak_usage(true);
		$result = BodyDecoding::decodeIfStillBase64($big);
		$after = memory_get_peak_usage(true);

		$this->assertSame($big, $result);
		// a full extra copy (+ preg_replace's own internal one) of a 20MB string would show up as
		// several MB of peak usage growth - the cheap prefix-only path allocates essentially none
		$this->assertLessThan(2 * 1024 * 1024, $after - $before,
			'must reject large binary content via its cheap prefix check alone, without copying the full string');
	}
}
