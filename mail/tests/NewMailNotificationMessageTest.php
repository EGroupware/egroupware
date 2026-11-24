<?php
/**
 * Tests for the html message of the notification about new mails
 *
 * From, To and Subject are headers of the received mails,
 * so everyone able to send a mail controls what the notification popup renders.
 *
 * @link http://www.egroupware.org
 * @package mail
 * @subpackage tests
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use PHPUnit\Framework\TestCase;

class NewMailNotificationMessageTest extends TestCase
{
	/**
	 * Html in the headers of a mail is shown as text
	 */
	public function testHeadersAreEscaped() : void
	{
		$message = \mail_hooks::newMailNotificationMessage([
			[],	// content array starts at index 1
			[
				'mail_from' => '<img src="/logout.php"> <sender@example.org>',
				'mail_to' => '<base href="https://example.org/"> <user@example.org>',
				'mail_subject' => '<meta http-equiv="refresh" content="0;url=https://example.org/">',
			],
		]);

		$this->assertStringContainsString('&lt;img src=&quot;/logout.php&quot;&gt; &lt;sender@example.org&gt;', $message);
		$this->assertStringContainsString('&lt;base href=', $message);
		$this->assertStringContainsString('&lt;meta http-equiv=', $message);
		// only our own markup is left
		$this->assertSame($message, strip_tags($message, '<br><strong>'));
	}

	/**
	 * Mails without sender are skipped, like the empty first row
	 */
	public function testMailWithoutSenderIsSkipped() : void
	{
		$this->assertSame('', \mail_hooks::newMailNotificationMessage([[], ['mail_subject' => 'Test']]));
	}

	/**
	 * A regular mail is listed with its headers
	 */
	public function testRegularMail() : void
	{
		$message = \mail_hooks::newMailNotificationMessage([[
			'mail_from' => 'Sender',
			'mail_to' => 'user@example.org',
			'mail_subject' => 'Hello & welcome',
		]]);

		$this->assertStringContainsString('</strong>Sender<br/>', $message);
		$this->assertStringContainsString('</strong>user@example.org<br/>', $message);
		$this->assertStringContainsString('</strong>Hello &amp; welcome<br/>', $message);
	}
}
