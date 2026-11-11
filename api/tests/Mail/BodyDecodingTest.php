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

	/**
	 * Ticket #117271: padding every colon to guarantee whitespace inside "{...}" (defeating a
	 * downstream template mechanism that replaces whitespace-free "{...}" spans) used to also pad
	 * pseudo-class colons like ":focus", turning them into the invalid ": focus" and breaking the
	 * selector. The fix pads the opening brace instead, which keeps pseudo-classes intact while
	 * still guaranteeing whitespace right after "{" for every rule.
	 */
	public function testGetStylesPreservesPseudoClassSelectors()
	{
		\EGroupware\Api\Mail::$displayCharset = 'utf-8';
		$bodyParts = [
			['body' => '<style>a:focus{color:red}a:hover{color:blue}</style>', 'mimeType' => 'text/html', 'charSet' => 'utf-8'],
		];

		$result = BodyDecoding::getStyles($bodyParts);

		$this->assertStringContainsString('a:focus', $result, 'pseudo-class selector must survive intact');
		$this->assertStringContainsString('a:hover', $result, 'pseudo-class selector must survive intact');
		$this->assertStringNotContainsString('a: focus', $result, 'must not pad the pseudo-class colon');
		$this->assertStringContainsString('{ color:red', $result, 'opening brace must still be padded');
		$this->assertStringContainsString('{ color:blue', $result, 'every rule\'s opening brace must be padded, not just the first');
	}

	/**
	 * Ticket #127551 (live report, forwarded newsletter): an MSO/Outlook conditional comment
	 * (`<!--[if gt mso 15]><style>...</style><![endif]-->`) wraps a `tr { font-size:0px; ... }`
	 * rule meant for Outlook alone. getStyles()'s own `<style>` extraction has no comment
	 * awareness, so it used to pull that style tag straight out of the comment and reapply it
	 * for every viewer - the Outlook-only rule went live everywhere, hiding the message's text.
	 * The whole comment (not just its `<!--`/`-->` markers) must now be dropped before extraction,
	 * so this hidden-for-everyone-but-Outlook rule never surfaces at all.
	 */
	public function testGetStylesDropsStyleBlockHiddenInConditionalComment()
	{
		\EGroupware\Api\Mail::$displayCharset = 'utf-8';
		$bodyParts = [
			['body' => "<!--[if gt mso 15]><style type=\"text/css\" media=\"all\">\n".
				"tr { font-size:0px; line-height:0px; }\n</style><![endif]-->".
				'<style>p{color:green}</style>', 'mimeType' => 'text/html', 'charSet' => 'utf-8'],
		];

		$result = BodyDecoding::getStyles($bodyParts);

		$this->assertStringNotContainsString('font-size:0px', $result,
			'an Outlook-only rule hidden inside a conditional comment must never apply to every viewer');
		$this->assertStringContainsString('color:green', $result,
			"an ordinary, uncommented <style> block elsewhere in the same message must still come through");
	}

	/**
	 * Distinct from the conditional-comment case above: a `<style><!-- real css --></style>`
	 * wrapper is a legacy "hide CSS from browsers that predate <style>" convention - the CSS
	 * content itself must survive, only the now-meaningless wrapper markers get dropped. This is
	 * why that case is handled separately (markers only, on the already-extracted CSS text), not
	 * by the whole-comment strip added for ticket #127551 above (which runs on the raw body,
	 * before extraction, and would otherwise delete this legitimate CSS along with its wrapper).
	 */
	public function testGetStylesKeepsCssContentOfLegacyCommentWrapper()
	{
		\EGroupware\Api\Mail::$displayCharset = 'utf-8';
		$bodyParts = [
			['body' => "<style><!--\nbody { color: red; }\n--></style>", 'mimeType' => 'text/html', 'charSet' => 'utf-8'],
		];

		$result = BodyDecoding::getStyles($bodyParts);

		$this->assertStringContainsString('color: red', $result,
			"a legacy <style><!-- css --></style> wrapper's actual CSS content must survive");
	}

	/**
	 * Same gap and fix as HtmLawed::getStyles() (that sibling's regression test has the full
	 * story) - the tag-level gate used to only catch tags containing the literal word "script",
	 * so it must now strip any on*= event-handler attribute too, not just that one keyword.
	 */
	public function testGetStylesStripsEventHandlerAttribute()
	{
		\EGroupware\Api\Mail::$displayCharset = 'utf-8';
		$bodyParts = [
			['body' => '<style>x{}<img src=y onerror=alert(1)></style>', 'mimeType' => 'text/html', 'charSet' => 'utf-8'],
		];

		$result = BodyDecoding::getStyles($bodyParts);

		$this->assertStringNotContainsString('onerror=', $result,
			'a comment-split tag must not be able to smuggle an event-handler attribute through');
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
