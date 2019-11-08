<?php

/**
 * EGroupware Api: HTML handling tests
 *
 * @link http://egroupware.org
 * @package api
 * @subpackage mail
 * @author Nathan Gray
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail;

use EGroupware\Api;
use PHPUnit\Framework\TestCase;


/**
 * Tests for HTML handling
 *
 * @author nathan
 */
class HtmlTest extends TestCase {

	/**
	 * Test how HTML lists (ol & ul) get converted to a plain text equivalent
	 *
	 * @dataProvider listDataProvider
	 */
	public function testListToText($html, $expected_text)
	{

		$replaced = Html::replaceLists($html);

		$this->assertEquals($expected_text, $replaced);
	}

	/**
	 * Data for checking HTML list conversion to plain text
	 *
	 * HTML first, then expected text
	 */
	public function listDataProvider()
	{
		return array(
			// HTML
			// Plaintext
			['', ''],
			['Not actually HTML', 'Not actually HTML'],
			['HTML, but <b>NO</b> list here', 'HTML, but <b>NO</b> list here'],
			["<p>Unordered list:<ul><li>First</li>\r\n<li>Second</li>\r\n<li>Third</li>\r\n</ul>\r\nPost text</p>",
				"<p>Unordered list:\r\n * First\r\n * Second\r\n * Third\r\n<p>\r\nPost text</p></p>\n"],
			["Ordered list:".
				"<ol><li>First</li>\r\n"
				. "<li>Second</li>\r\n"
				. "<li>Third</li>\r\n"
				. "</ol>Post text",
				"<p>Ordered list:\r\n"
				. " 1. First\r\n"
				. " 2. Second\r\n"
				. " 3. Third\r\n"
				. "<p>Post text</p></p>\n"],
		);
	}
}
