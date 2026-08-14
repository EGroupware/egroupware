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
}
