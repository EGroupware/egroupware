<?php
/**
 * EGroupware Mail: regression test for the mail_compose::send() -> Send extraction
 *
 * @link https://www.egroupware.org
 * @package mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

use EGroupware\Api;
use EGroupware\Mail\ComposeMessageBuilder;
use EGroupware\Mail\Send;

/**
 * mail_compose::send() was extracted into its own EGroupware\Mail\Send class 2026-09-08 (its only
 * real caller was already ApiHandler::send(), the REST API's non-interactive send path) - the MIME
 * -building logic it shares with mail_compose::saveAsDraft() (createMessage()/_getAttachmentLinks()/
 * resolveEmailAddressList()/convertHTMLToText()/_encrypt()/changeProfile()) moved into a shared
 * ComposeMessageBuilder trait instead of being duplicated or making Send depend on a full
 * mail_compose instance.
 *
 * Doesn't call send()/saveAsDraft()/initMailAccount() themselves - real IMAP/JMAP writes (and a
 * live IMAP connection at all - Api\Mail::getInstance() needs backend connectivity this
 * environment's own PHPUnit CLI context doesn't have, confirmed by reproducing the identical
 * failure against the pre-existing, unmodified mail_compose constructor too - not something this
 * refactor introduced) aren't safe/possible to exercise here. Covers the shape of the split
 * instead via newInstanceWithoutConstructor(): both classes' trait-supplied properties are
 * independent per-instance (not shared through the trait), mail_compose no longer has send() at
 * all, and the trait's own pure-logic methods behave identically through either class.
 */
class SendRefactorTest extends Api\LoggedInTest
{
	public function testTraitPropertiesAreIndependentPerInstance()
	{
		$send = (new ReflectionClass(Send::class))->newInstanceWithoutConstructor();
		$compose = (new ReflectionClass(mail_compose::class))->newInstanceWithoutConstructor();

		$send->mailPreferences = ['sendOptions' => 'TEST_MARKER_SEND_ONLY'];
		$compose->mailPreferences = ['sendOptions' => 'other'];

		$this->assertSame('TEST_MARKER_SEND_ONLY', $send->mailPreferences['sendOptions']);
		$this->assertNotSame($send->mailPreferences['sendOptions'], $compose->mailPreferences['sendOptions'],
			'Send and mail_compose must not share mailPreferences state through the trait');
	}

	public function testTraitCompositionShape()
	{
		$sendReflection = new ReflectionClass(Send::class);
		$composeReflection = new ReflectionClass(mail_compose::class);

		$this->assertContains(ComposeMessageBuilder::class, $sendReflection->getTraitNames());
		$this->assertContains(ComposeMessageBuilder::class, $composeReflection->getTraitNames());

		foreach (['createMessage', '_getAttachmentLinks', 'resolveEmailAddressList',
					 'convertHTMLToText', '_encrypt', 'changeProfile'] as $method)
		{
			$this->assertTrue($sendReflection->hasMethod($method), "Send::$method() missing");
			$this->assertTrue($composeReflection->hasMethod($method), "mail_compose::$method() missing");
		}

		$this->assertTrue($sendReflection->hasMethod('send'), 'Send::send() missing');
		$this->assertFalse($composeReflection->hasMethod('send'),
			'mail_compose::send() should be gone - moved to Send entirely, not shared via the trait');

		$this->assertTrue($composeReflection->hasMethod('saveAsDraft'),
			'mail_compose::saveAsDraft() should still exist, using createMessage() via the trait');
	}

	/**
	 * The trait's own pure-logic static helper - no mail_bo/IMAP connection needed - unaffected by
	 * which class it's called through.
	 */
	public function testResolveEmailAddressListUnaffectedByMove()
	{
		$plain = ['a@example.com', 'b@example.com'];

		$this->assertSame($plain, Send::resolveEmailAddressList($plain));
		$this->assertSame($plain, mail_compose::resolveEmailAddressList($plain));
	}

	public function testConvertHtmlToTextUnaffectedByMove()
	{
		$send = (new ReflectionClass(Send::class))->newInstanceWithoutConstructor();
		$compose = (new ReflectionClass(mail_compose::class))->newInstanceWithoutConstructor();
		$send->displayCharset = $compose->displayCharset = 'utf-8';
		$html = '<p>Hello <b>world</b></p>';

		$this->assertSame($send->convertHTMLToText($html), $compose->convertHTMLToText($html));
	}
}
