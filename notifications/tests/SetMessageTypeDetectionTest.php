<?php
/**
 * EGroupware Notifications: set_message() type auto-detection regression test
 *
 * @link http://www.egroupware.org
 * @package notifications
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

require_once __DIR__.'/../../api/tests/LoggedInTest.php';

use EGroupware\Api\LoggedInTest;

/**
 * Ticket #94561: a plain-text message containing a bracketed email address (eg. an
 * "Owner: Name <user@example.org>" line - calendar's own invitation notifications always have
 * one) used to get misclassified as html by notifications::set_message()'s own auto-detect:
 * strip_tags() treats ANY "<...>" pair as a tag, including <user@example.org>, changing the
 * string's length and tripping the plain-vs-html guess. The raw plain text then got stored
 * verbatim as the html message (no escaping, no nl2br) while the plain part was derived via
 * Api\Mail\Html::convertHTMLToText(), which collapses "insignificant" bare newlines the way a
 * browser would - correct for real html, wrong for plain text that was never html at all.
 */
class SetMessageTypeDetectionTest extends LoggedInTest
{
	private function messagePlain(notifications $n) : string
	{
		$prop = new ReflectionProperty(notifications::class, 'message_plain');
		$prop->setAccessible(true);
		return $prop->getValue($n);
	}

	private function messageHtml(notifications $n) : string
	{
		$prop = new ReflectionProperty(notifications::class, 'message_html');
		$prop->setAccessible(true);
		return $prop->getValue($n);
	}

	public function testPlainTextWithBracketedEmailAddressIsNotMisclassifiedAsHtml()
	{
		$message = "Owner:               Jane Doe <jane.doe@example.org>\nParticipants:        Doe, Jane (Accepted)";

		$n = new notifications();
		$n->set_message($message);

		$this->assertSame($message, $this->messagePlain($n), "must be stored as the PLAIN message");
		$this->assertSame('', $this->messageHtml($n), "must NOT be stored as the html message");
	}

	public function testRealHtmlIsStillDetectedAsHtml()
	{
		$message = "<br/><strong>From:</strong> someone";

		$n = new notifications();
		$n->set_message($message);

		$this->assertSame($message, $this->messageHtml($n), "must be stored as the HTML message");
		$this->assertSame('', $this->messagePlain($n), "must NOT be stored as the plain message");
	}

	/**
	 * A single, isolated tag-like fragment (eg. an import/validation error message that happens
	 * to quote one) must not flip the whole message to html by itself - only count() >= 2 does.
	 */
	public function testASingleTagLikeFragmentAloneIsNotEnoughToBeDetectedAsHtml()
	{
		$message = "Import failed: unexpected element <li> found at line 5, column 12.";

		$n = new notifications();
		$n->set_message($message);

		$this->assertSame($message, $this->messagePlain($n), "must be stored as the PLAIN message");
		$this->assertSame('', $this->messageHtml($n), "must NOT be stored as the html message");
	}

	public function testPlainTextWithoutAnyBracketsIsDetectedAsPlain()
	{
		$message = "No special characters here at all.";

		$n = new notifications();
		$n->set_message($message);

		$this->assertSame($message, $this->messagePlain($n));
		$this->assertSame('', $this->messageHtml($n));
	}

	public function testExplicitTypeIsNeverOverriddenByAutoDetection()
	{
		// a caller that already knows what it has (eg. calendar's own set_message(..., 'plain')
		// call, ticket #94561's own narrow fix) must never be second-guessed.
		$message = "<br/>looks like html but isn't meant to be";

		$n = new notifications();
		$n->set_message($message, 'plain');

		$this->assertSame($message, $this->messagePlain($n));
		$this->assertSame('', $this->messageHtml($n));
	}
}
