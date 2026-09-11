<?php
/**
 * EGroupware Mail: Test Ui\AttachmentHandler::getdisplayableBody()
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail\Ui;

use EGroupware\Api;
use EGroupware\Mail\Ui;

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

/**
 * doc/ai/projects/mail-test-coverage.md's priority-4 entry: AttachmentHandler.php had zero
 * coverage. getdisplayableBody() is the last step before a message body is actually shown to the
 * user - real risk here across several dimensions at once: HTML sanitization (a broken
 * script-tag-removal path would be an XSS hole, not just a display bug), charset/entity handling,
 * and multi-part joining.
 *
 * Takes `$_bodyParts` directly (no IMAP/DB fetch inside the method itself), but reads
 * `$this->ui->mailbox`/`uid`/`partID` for inline-image link building (`BodyHandler::
 * resolveInlineImages()`'s default callback, `Api\Egw::link()`) - `new Ui(false)` skips Ui's own
 * heavy constructor, these three are then just set directly as plain properties.
 */
class AttachmentHandlerGetDisplayableBodyTest extends Api\LoggedInTest
{
	private function handler() : AttachmentHandler
	{
		$ui = new Ui(false);
		$ui->mailbox = 'INBOX';
		$ui->uid = '42';
		$ui->partID = '1';
		return new AttachmentHandler($ui);
	}

	public function testReturnsAnEmptyStringForEmptyBodyParts()
	{
		$this->assertSame('', $this->handler()->getdisplayableBody([]));
	}

	public function testPlainTextIsHtmlEntityEncodedAndWrappedInPre()
	{
		$body = $this->handler()->getdisplayableBody([
			['mimeType' => 'text/plain', 'charSet' => 'UTF-8', 'body' => 'Hello <world> & "friends"'],
		]);

		$this->assertStringStartsWith('<pre>', $body);
		$this->assertStringContainsString('Hello &lt;world&gt; &amp; &quot;friends&quot;', $body);
	}

	public function testPlainTextAutoActivatesUrlsAsLinks()
	{
		$body = $this->handler()->getdisplayableBody([
			['mimeType' => 'text/plain', 'charSet' => 'UTF-8', 'body' => 'See https://example.org'],
		]);

		$this->assertStringContainsString('<a href="https://example.org" target="_blank">', $body);
	}

	/** Real risk: a broken sanitization path here would be an XSS hole, not just a display bug. */
	public function testHtmlScriptTagsAreCompletelyRemovedIncludingTheirContent()
	{
		$body = $this->handler()->getdisplayableBody([
			['mimeType' => 'text/html', 'charSet' => 'UTF-8', 'body' => '<p>Hello</p><script>alert(1)</script>'],
		]);

		$this->assertStringContainsString('<p>Hello</p>', $body);
		$this->assertStringNotContainsString('<script', $body);
		$this->assertStringNotContainsString('alert(1)', $body, "the script TAG's own content must be removed too, not just the tag markers");
	}

	public function testMultiplePartsAreJoinedWithAHorizontalRuleSeparator()
	{
		$body = $this->handler()->getdisplayableBody([
			['mimeType' => 'text/plain', 'charSet' => 'UTF-8', 'body' => 'First part'],
			['mimeType' => 'text/plain', 'charSet' => 'UTF-8', 'body' => 'Second part'],
		]);

		$this->assertStringContainsString('First part', $body);
		$this->assertStringContainsString('Second part', $body);
		$this->assertStringContainsString('<hr style="border:dotted 1px silver;">', $body);
	}

	public function testAWhitespaceOnlyPartContributesNothingNotEvenAStraySeparator()
	{
		$body = $this->handler()->getdisplayableBody([
			['mimeType' => 'text/plain', 'charSet' => 'UTF-8', 'body' => 'Real content'],
			['mimeType' => 'text/plain', 'charSet' => 'UTF-8', 'body' => '   '],
		]);

		$this->assertStringNotContainsString('<hr', $body, "no second real part means no separator, even though there were 2 entries");
	}

	public function testWindowsShareBackslashPathsBecomeFileLinks()
	{
		$body = $this->handler()->getdisplayableBody([
			['mimeType' => 'text/plain', 'charSet' => 'UTF-8', 'body' => 'See \\\\server\\share'],
		]);

		$this->assertStringContainsString('<a href="file:\\\\server\\share" target="_blank">', $body);
	}

	/** modifyURI=false must suppress both link-activation forms, not just one. */
	public function testModifyUriFalseSuppressesLinkActivation()
	{
		$body = $this->handler()->getdisplayableBody([
			['mimeType' => 'text/plain', 'charSet' => 'UTF-8', 'body' => 'See https://example.org'],
		], false);

		$this->assertStringNotContainsString('<a href=', $body);
		$this->assertStringContainsString('https://example.org', $body, "the URL text itself must still be present, just not turned into a link");
	}

	/** A nested (multipart) shape - an entry with no 'body' key of its own, itself a list of parts - recurses instead of treating it as a leaf. */
	public function testNestedBodyPartsAreRecursedInto()
	{
		$body = $this->handler()->getdisplayableBody([
			[['mimeType' => 'text/plain', 'charSet' => 'UTF-8', 'body' => 'Nested content']],
		]);

		$this->assertStringContainsString('Nested content', $body);
	}
}
