<?php
/**
 * EGroupware Api: Mail::parseAddressList() regression tests
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License Version 2+
 */

namespace EGroupware\Api\Mail;

use EGroupware\Api\Mail;
use PHPUnit\Framework\TestCase;

/**
 * Pure parsing tests for Mail::parseAddressList() - no database/session/IMAP connection
 * required. Real header text only, no live eml fixtures (those contain private/confidential
 * data and can't be committed) - the malformed shape below is a sanitized (example.com
 * addresses, replaced names) reproduction of a real header that broke JmapShim's Cc parsing.
 */
class ParseAddressListTest extends TestCase
{
	public function testSingleAddressWithEncodedDisplayName()
	{
		$list = Mail::parseAddressList('=?utf-8?Q?Jane_Doe?= <jane.doe@example.com>');

		$this->assertCount(1, $list);
		$this->assertSame('Jane Doe', $list[0]->personal);
		$this->assertSame('jane.doe@example.com', $list[0]->bare_address);
	}

	public function testTwoWellFormedAddressesSplitCorrectly()
	{
		$list = Mail::parseAddressList('=?utf-8?Q?Jane_Doe?= <jane.doe@example.com>, ' .
			'=?utf-8?Q?John_Smith?= <john.smith@example.com>');

		$this->assertCount(2, $list);
		$this->assertSame('Jane Doe', $list[0]->personal);
		$this->assertSame('John Smith', $list[1]->personal);
	}

	/**
	 * Regression test: a real-world Cc header where the second address' display name is a
	 * quoted phrase CONTAINING A LITERAL COMMA ("Example Corp, Consulting"), but the sending
	 * MUA wrongly wrapped the whole quoted phrase - quotes included - inside a SINGLE RFC 2047
	 * encoded-word instead of using proper RFC 5322 quoted-string phrase syntax. That's valid
	 * per RFC 2047 (Q-encoding never requires escaping a comma), so a naive address-list
	 * splitter that breaks on any raw comma before recognizing/skipping over "=?...?=" tokens
	 * will wrongly treat it as two addresses and mangle both.
	 *
	 * This exact pattern took down JmapShim's Cc parsing: it trusted the IMAP server's own
	 * ENVELOPE-parsed addresses (Dovecot's address splitter is not RFC 2047-aware either), so
	 * the malformed header appeared as an empty-mailbox/host address fragment glued onto the
	 * next one. Api\Mail::parseAddressList() already has repair logic for this exact shape (see
	 * its "no mailbox or host part" handling) - JmapShim was fixed to route through it instead
	 * of the envelope, restoring the same robustness the classic pre-JMAP code always had.
	 */
	public function testEncodedWordWithEmbeddedCommaInQuotedDisplayNameStaysOneAddress()
	{
		$header = "=?utf-8?Q?Jane_Doe?= <jane.doe@example.com>,\r\n" .
			' =?utf-8?Q?"Example_Corp,_Consulting"?= <info@example.com>';

		$list = Mail::parseAddressList($header);

		$this->assertCount(2, $list, 'the embedded comma must not create a bogus 3rd address fragment');
		$this->assertSame('Jane Doe', $list[0]->personal);
		$this->assertSame('jane.doe@example.com', $list[0]->bare_address);
		$this->assertSame('info@example.com', $list[1]->bare_address);
		$this->assertStringContainsString('Example Corp', $list[1]->personal);
		$this->assertStringContainsString('Consulting', $list[1]->personal);
	}
}
