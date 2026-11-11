<?php
/**
 * EGroupware Api: HtmLawed::getStyles() CSS handling tests
 *
 * @link https://www.egroupware.org
 * @package api
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use PHPUnit\Framework\TestCase;
use EGroupware\Api\Html\HtmLawed;

require_once realpath(__DIR__.'/../../src/Html/HtmLawed.php');

/**
 * Ticket #117271: HtmLawed::getStyles() used to pad every colon with a space (": ") to guarantee
 * CSS rule blocks contain whitespace - defeating a downstream template mechanism that replaces
 * whitespace-free "{...}" spans. That also padded pseudo-class colons like ":focus", turning them
 * into the invalid ": focus" and breaking the selector (eg. on the login screen). The fix pads the
 * opening brace instead, which keeps pseudo-classes intact while still guaranteeing whitespace
 * right after "{" for every rule.
 */
class HtmLawedGetStylesTest extends TestCase
{
	public function testGetStylesPreservesPseudoClassSelectors()
	{
		$html = '<div>text</div><style>a:focus{color:red}a:hover{color:blue}</style>';

		$result = HtmLawed::getStyles($html);

		$this->assertStringContainsString('a:focus', $result, 'pseudo-class selector must survive intact');
		$this->assertStringContainsString('a:hover', $result, 'pseudo-class selector must survive intact');
		$this->assertStringNotContainsString('a: focus', $result, 'must not pad the pseudo-class colon');
		$this->assertStringContainsString('{ color:red', $result, 'opening brace must still be padded');
		$this->assertStringContainsString('{ color:blue', $result, "every rule's opening brace must be padded, not just the first");
		$this->assertStringNotContainsString('<style', $html, 'the style tag must be removed from the original html');
	}

	/**
	 * Ticket #127551 (live report, forwarded newsletter): an MSO/Outlook conditional comment
	 * (`<!--[if gt mso 15]><style>...</style><![endif]-->`) wraps a `tr { font-size:0px; ... }`
	 * rule meant for Outlook alone. The <style> extraction above has no comment awareness, so it
	 * used to pull that style tag straight out of the comment and reapply it for every viewer -
	 * the Outlook-only rule went live everywhere, hiding the message's text. The whole comment
	 * (not just its `<!--`/`-->` markers) must now be dropped before extraction, so this
	 * hidden-for-everyone-but-Outlook rule never surfaces at all.
	 */
	public function testGetStylesDropsStyleBlockHiddenInConditionalComment()
	{
		$html = "<!--[if gt mso 15]><style type=\"text/css\" media=\"all\">\n".
			"tr { font-size:0px; line-height:0px; }\n</style><![endif]-->".
			'<p>real content</p><style>p{color:green}</style>';

		$result = HtmLawed::getStyles($html);

		$this->assertStringNotContainsString('font-size:0px', $result,
			'an Outlook-only rule hidden inside a conditional comment must never apply to every viewer');
		$this->assertStringContainsString('color:green', $result,
			'an ordinary, uncommented <style> block elsewhere in the same message must still come through');
		$this->assertStringContainsString('real content', $html,
			"stripping the conditional comment must not touch the message's actual visible content");
	}

	/**
	 * Distinct from the conditional-comment case above: a `<style><!-- real css --></style>`
	 * wrapper is a legacy "hide CSS from browsers that predate <style>" convention - the CSS
	 * content itself must survive, only the now-meaningless wrapper markers get dropped. This is
	 * why that case is handled separately (markers only, on the already-extracted CSS text), not
	 * by the whole-comment strip added for ticket #127551 above (which runs on the raw html,
	 * before extraction, and would otherwise delete this legitimate CSS along with its wrapper).
	 */
	public function testGetStylesKeepsCssContentOfLegacyCommentWrapper()
	{
		$html = "<style><!--\nbody { color: red; }\n--></style>";

		$result = HtmLawed::getStyles($html);

		$this->assertStringContainsString('color: red', $result,
			"a legacy <style><!-- css --></style> wrapper's actual CSS content must survive");
	}

	/**
	 * getStyles()'s tag-level filtering used to gate only on the literal word "script". An
	 * event-handler attribute on any other element (`onerror`, `onload`, ...) is just as capable
	 * of executing script once et2-html renders the purified value, so the gate now strips any
	 * on*= attribute, not just tags spelling "script".
	 */
	public function testGetStylesStripsEventHandlerAttribute()
	{
		$html = '<style>x{}<img src=y onerror=alert(1)></style>';

		$result = HtmLawed::getStyles($html);

		$this->assertStringNotContainsString('onerror=', $result, 'an event-handler attribute must not survive');
	}

	/**
	 * Companion to the test above: the on*= strip must not corrupt real CSS. Child combinators,
	 * attribute selectors, media queries and ampersands in content are all ordinary stylesheet
	 * syntax and must survive byte-for-byte.
	 */
	public function testGetStylesPreservesOrdinaryCssAroundTheEventHandlerStrip()
	{
		$html = '<style>div > p[data-x="a&b"] { color: red; } @media (min-width: 600px) { .a { color: green; } }</style>';

		$result = HtmLawed::getStyles($html);

		$this->assertStringContainsString('div > p[data-x="a&b"]', $result, 'child combinator and attribute selector must survive untouched');
		$this->assertStringContainsString('@media (min-width: 600px)', $result, 'media query must survive untouched');
	}
}
