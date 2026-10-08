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

	/**
	 * beforeSendToClient() unconditionally instantiates an Ai widget from a literal XML string
	 * (`new Ai('<et2-ai/>')`) to "blindly turn on AI tools without regard to parent node" - that
	 * string is parsed as a standalone XML fragment (Widget::__construct(), via XMLReader), so it
	 * must be well-formed on its own. A prior version used the bare, unclosed `'<et2-ai>'` (no
	 * self-close, no closing tag), which XMLReader rejects as "Premature end of data in tag et2-ai
	 * line 1" - throwing every time ANY HtmlArea widget's beforeSendToClient() actually runs (found
	 * live 2026-09-17: an addressbook custom field of type "HTML area" crashed the whole nextmatch
	 * with exactly this error). The other 4 tests in this file already exercise this path
	 * incidentally via mockedRoundTrip() and would fail on the broken string too - this test names
	 * the actual bug directly instead of relying on that as a side effect.
	 */
	public function testBeforeSendToClientDoesNotThrow()
	{
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		$content = array(
			'html_widget'    => '',
			'ascii_widget'   => '',
			'dynamic_widget' => '',
			'edit_mode'      => 'html'
		);
		// mockedRoundTrip() itself would throw (uncaught) if beforeSendToClient() throws - no
		// assertion needed beyond "this completes at all", matching the real symptom (a hard
		// server error page, not a wrong value).
		$this->mockedRoundTrip($etemplate, $content, array(), array());
		$this->addToAssertionCount(1);
	}

	/**
	 * Ticket #125961 follow-up: htmLawed's own regex-heavy parsing can exhaust PHP's
	 * pcre.backtrack_limit on a sufficiently large value (eg. a multi-megabyte data: URI from an
	 * unresized pasted image) - found live via a real report: a ~2MB image pasted into a mail
	 * signature silently reduced the WHOLE field to a near-empty fragment on save, with nothing
	 * in any log. validate() must now reject the save with a clear, visible error instead of
	 * silently persisting the garbled result.
	 */
	public function testOversizedContentExhaustingPcreBacktrackLimitIsRejectedNotSilentlyCorrupted()
	{
		$etemplate = new Etemplate();
		$etemplate->read(static::TEST_TEMPLATE, 'test');

		// a large, genuinely valid data: URI (not just repeated bytes - htmLawed's own src=""
		// matching needs real base64-ish content to exercise the same regex path a real pasted
		// image hits) is enough on its own to exhaust the backtrack limit
		$dataUri = 'data:image/jpeg;base64,'.base64_encode(random_bytes(2 * 1024 * 1024));
		$huge = '<p>Best regards</p><img src="'.$dataUri.'" alt="sig">';

		$content = array(
			'html_widget'    => $huge,
			'ascii_widget'   => '',
			'dynamic_widget' => '',
			'edit_mode'      => 'html'
		);
		$result = $this->mockedRoundTrip($etemplate, $content, array(), array());

		$this->assertNotEmpty(
			Etemplate\Widget::get_validation_errors('exec[html_widget]') ?? Etemplate\Widget::get_validation_errors('html_widget'),
			'an oversized value that defeats htmLawed must surface a validation error, not silently save a corrupted result'
		);
		$this->assertStringNotContainsString($dataUri, $result['html_widget'] ?? '',
			'the oversized, unsanitized value must never reach storage either way');
	}
}
