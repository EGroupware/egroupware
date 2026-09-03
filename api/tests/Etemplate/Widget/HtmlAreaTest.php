<?php

/**
 * Test for htmlarea
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage etemplate
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Etemplate\Widget;

require_once realpath(__DIR__.'/../WidgetBaseTest.php');

use EGroupware\Api\Etemplate;

class HtmlAreaTest extends \EGroupware\Api\Etemplate\WidgetBaseTest
{

	const TEST_TEMPLATE = 'api.htmlarea_test';

	/**
	 * Plain text containing the things HtmLawed eats:
	 * "<TODO>" and "<user@example.com>" look like bogus tags to it.
	 */
	const ASCII_VALUE = "See <TODO> and write to <user@example.com>";

	/**
	 * mode="ascii" is plain text, it must reach storage byte for byte
	 */
	public function testAsciiModeIsNotPurified()
	{
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		$content = array(
			'html_widget'    => '',
			'ascii_widget'   => self::ASCII_VALUE,
			'dynamic_widget' => '',
			'edit_mode'      => 'html'
		);
		$result = $this->mockedRoundTrip($etemplate, $content, array(), array());

		$this->assertEquals(self::ASCII_VALUE, $result['ascii_widget']);
	}

	/**
	 * The mode may be bound to content, as tracker does with mode="@tr_edit_mode".
	 *
	 * expand_widget() only commits expanded attributes when the *type* is expandable, so
	 * validate() used to compare the literal "@edit_mode" against 'ascii' and purify anyway.
	 */
	public function testDynamicAsciiModeIsNotPurified()
	{
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		$content = array(
			'html_widget'    => '',
			'ascii_widget'   => '',
			'dynamic_widget' => self::ASCII_VALUE,
			'edit_mode'      => 'ascii'
		);
		$result = $this->mockedRoundTrip($etemplate, $content, array(), array());

		$this->assertEquals(self::ASCII_VALUE, $result['dynamic_widget']);
	}

	/**
	 * ... but a dynamic mode resolving to html still gets purified
	 */
	public function testDynamicHtmlModeIsPurified()
	{
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		$content = array(
			'html_widget'    => '',
			'ascii_widget'   => '',
			'dynamic_widget' => '<p>Hi</p><script>alert(1)</script>',
			'edit_mode'      => 'html'
		);
		$result = $this->mockedRoundTrip($etemplate, $content, array(), array());

		$this->assertStringNotContainsString('<script', $result['dynamic_widget']);
	}

	/**
	 * The default (html) mode must keep purifying - this is the XSS guard
	 */
	public function testHtmlModeIsPurified()
	{
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		$content = array(
			'html_widget'    => '<p>Hi</p><script>alert(1)</script>',
			'ascii_widget'   => '',
			'dynamic_widget' => '',
			'edit_mode'      => 'html'
		);
		$result = $this->mockedRoundTrip($etemplate, $content, array(), array());

		$this->assertStringNotContainsString('<script', $result['html_widget']);
		$this->assertStringContainsString('Hi', $result['html_widget']);
	}
}
