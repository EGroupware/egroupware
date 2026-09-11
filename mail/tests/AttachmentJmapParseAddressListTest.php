<?php
/**
 * EGroupware Mail: EGroupware\Mail\Ui\AttachmentJmap::parseAddressList() tests
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License Version 2+
 */

use EGroupware\Mail\Ui\AttachmentJmap;
use PHPUnit\Framework\TestCase;

/**
 * Pure tests for AttachmentJmap::parseAddressList() - no database/session/IMAP connection required.
 * See mail_ui::ajax_parseAddressList()'s docblock for the on-demand-repair story this serves.
 */
class AttachmentJmapParseAddressListTest extends TestCase
{
	public function testDecodesAndParsesEncodedWordAddress()
	{
		$result = AttachmentJmap::parseAddressList('=?utf-8?Q?Jane_Doe?= <jane.doe@example.com>');

		$this->assertSame([['email' => 'jane.doe@example.com', 'name' => 'Jane Doe']], $result);
	}

	public function testEmbeddedCommaInQuotedDisplayNameStaysOneAddress()
	{
		$header = "=?utf-8?Q?Jane_Doe?= <jane.doe@example.com>,\r\n".
			' =?utf-8?Q?"Example_Corp,_Consulting"?= <info@example.com>';

		$result = AttachmentJmap::parseAddressList($header);

		$this->assertCount(2, $result);
		$this->assertSame('jane.doe@example.com', $result[0]['email']);
		$this->assertSame('info@example.com', $result[1]['email']);
	}

	public function testOversizedHeaderIsTruncatedRatherThanRejected()
	{
		$huge = str_repeat('a', 20000).'@example.com';

		// must not throw / time out - the 8000-char cap is applied before parsing
		$result = AttachmentJmap::parseAddressList($huge);

		$this->assertIsArray($result);
	}
}
