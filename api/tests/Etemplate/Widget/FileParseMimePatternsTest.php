<?php

/**
 * Test for File widget's parseMimePatterns() helper
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage etemplate
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate\Widget;

/**
 * Ticket #126721 (2026-10-20, Stefan Unverricht, an EGroupware technician): importing a genuinely
 * valid .eml into mail rejected it with "File is of wrong type (text/plain != /^message\/|/i)!" -
 * mail/templates/default/importMessage.xet's own `mime="/^message\//i"` only ever matched against
 * the file's server-side BYTE-SNIFFED type (Api\MimeMagic::analyze_file()), which has no distinct
 * magic signature for a raw RFC822 header block and reports it as the equally generic "text/plain",
 * indistinguishable from actual plain text by content alone. parseMimePatterns() - extracted from
 * 'accept''s own pre-existing comma-splitting logic, now shared with 'mime' too - lets the .xet
 * additionally accept a trusted ".eml" extension as an alternative, fixing this without touching
 * 'accept'/checkMime() (Et2File.ts) at all, which has no comma-splitting of its own.
 */
class FileParseMimePatternsTest extends \PHPUnit\Framework\TestCase
{
	private function callParseMimePatterns(?string $patterns) : array
	{
		$reflection = new \ReflectionMethod(File::class, 'parseMimePatterns');
		$reflection->setAccessible(true);

		return $reflection->invoke(null, $patterns);
	}

	public function testEmptyOrNullReturnsEmptyArray()
	{
		$this->assertSame([], $this->callParseMimePatterns(null));
		$this->assertSame([], $this->callParseMimePatterns(''));
	}

	public function testSingleRegexPatternUnchanged()
	{
		$this->assertSame(['/^message\//i'], $this->callParseMimePatterns('/^message\//i'));
	}

	public function testSingleMimeTypeUnchanged()
	{
		$this->assertSame(['message/rfc822'], $this->callParseMimePatterns('message/rfc822'));
	}

	public function testBareExtensionGetsADotPrefixed()
	{
		$this->assertSame(['.eml'], $this->callParseMimePatterns('eml'));
	}

	public function testExtensionAlreadyDottedIsLeftAlone()
	{
		$this->assertSame(['.eml'], $this->callParseMimePatterns('.eml'));
	}

	/**
	 * The exact ticket #126721 fix: a regex-or-extension list, comma-separated same as 'accept'.
	 */
	public function testRegexAndExtensionCombinedCommaList()
	{
		$this->assertSame(['/^message\//i', '.eml'], $this->callParseMimePatterns('/^message\//i,.eml'));
	}

	public function testMultipleBareExtensionsEachGetADot()
	{
		$this->assertSame(['.eml', '.msg'], $this->callParseMimePatterns('eml,msg'));
	}

	/**
	 * The pre-existing splitting regex this reuses from 'accept' already tolerates an optional
	 * space after the comma, even though every current .xet (eg. admin/templates/default/
	 * config.xet's accept="svg,png,jpeg,jpg") only ever omits it.
	 */
	public function testToleratesASpaceAfterTheComma()
	{
		$this->assertSame(['image/png', 'image/jpeg'], $this->callParseMimePatterns('image/png, image/jpeg'));
	}
}
