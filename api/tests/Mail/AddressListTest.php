<?php
/**
 * EGroupware Api: Mail\AddressList tests
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License Version 2+
 */

namespace EGroupware\Api\Mail;

use PHPUnit\Framework\TestCase;

/**
 * Pure tests for Mail\AddressList - no database/session/IMAP connection required, and no live
 * .eml fixtures (those contain private/confidential data and can't be committed), so every case
 * here uses sanitized example.com-style data. See api/tests/Mail/ParseAddressListTest.php for the
 * equivalent coverage through the Api\Mail::parseAddressList() delegating wrapper.
 */
class AddressListTest extends TestCase
{
	public function testStripRFC822AddressesKeepsOnlyTheBareEmail()
	{
		$result = AddressList::stripRFC822Addresses(['Jane Doe <jane.doe@example.com>', 'plain@example.com']);

		$this->assertSame(['jane.doe@example.com', 'plain@example.com'], $result);
	}

	public function testConvertAddressArrayToStringSkipsNilAndUndisclosedRecipients()
	{
		$result = AddressList::convertAddressArrayToString([
			['MAILBOX_NAME' => 'NIL', 'PERSONAL_NAME' => '', 'EMAIL' => '', 'RFC822_EMAIL' => ''],
			['MAILBOX_NAME' => 'undisclosed-recipients', 'PERSONAL_NAME' => '', 'EMAIL' => '', 'RFC822_EMAIL' => ''],
			['MAILBOX_NAME' => 'jane.doe', 'PERSONAL_NAME' => 'Jane Doe', 'EMAIL' => 'jane.doe@example.com', 'RFC822_EMAIL' => ''],
		]);

		$this->assertStringContainsString('Jane Doe', $result);
		$this->assertStringContainsString('jane.doe@example.com', $result);
		$this->assertStringNotContainsString('undisclosed-recipients', $result);
	}

	public function testConvertAddressArrayToStringPassesThroughAPlainString()
	{
		$result = AddressList::convertAddressArrayToString('=?utf-8?Q?Jane_Doe?= <jane.doe@example.com>');

		$this->assertStringContainsString('Jane Doe', $result);
		$this->assertStringContainsString('jane.doe@example.com', $result);
	}

	public function testDecodeHeaderDecodesRfc2047EncodedWord()
	{
		$this->assertSame('Jane Doe', AddressList::decode_header('=?utf-8?Q?Jane_Doe?='));
	}

	public function testDecodeHeaderRecursesIntoArrays()
	{
		$result = AddressList::decode_header(['=?utf-8?Q?Jane_Doe?=', '=?utf-8?Q?John_Smith?=']);

		$this->assertSame(['Jane Doe', 'John Smith'], $result);
	}

	public function testDecodeHeaderIdnaDecodesHostWhenRequested()
	{
		$result = AddressList::decode_header('Jane Doe <jane.doe@xn--mnchen-3ya.de>', true);

		$this->assertStringContainsString('münchen.de', $result);
	}

	public function testDecodeHeaderForceIdnaDecodesEvenWithoutAnAtSign()
	{
		$this->assertSame('münchen.de', AddressList::decode_header('xn--mnchen-3ya.de', 'FORCE'));
	}

	public function testDecodeSubjectReturnsPlaceholderForNil()
	{
		$this->assertSame('No Subject', AddressList::decode_subject('NIL'));
	}

	public function testDecodeSubjectDecodesRfc2047EncodedWord()
	{
		$this->assertSame('Jane Doe', AddressList::decode_subject('=?utf-8?Q?Jane_Doe?='));
	}

	public function testDecodeSubjectCanSkipDecoding()
	{
		$encoded = '=?utf-8?Q?Jane_Doe?=';

		$this->assertSame($encoded, AddressList::decode_subject($encoded, false));
	}
}
