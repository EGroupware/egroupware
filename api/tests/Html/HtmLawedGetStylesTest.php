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
}
