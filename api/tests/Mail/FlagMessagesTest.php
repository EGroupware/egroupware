<?php
/**
 * EGroupware API: Mail flag mutation tests
 *
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

use PHPUnit\Framework\TestCase;

class FlagMessagesTest extends TestCase
{
	/**
	 * A colored custom flag implies the standard flagged state, so clearing the
	 * standard flag must remove every colored keyword too.
	 *
	 * The fake classic IMAP server captures the single store operation. The test
	 * passes when that operation removes \Flagged and all five custom flags together.
	 */
	public function testUnflaggedRemovesAllCustomFlags()
	{
		$mail = new class extends Mail {
			public function __construct()
			{
			}
		};
		$mail->icServer = new class {
			public $ImapServerId = 1;
			public $stores = array();
			private $mailbox;

			public function openMailbox($mailbox)
			{
				$this->mailbox = $mailbox;
			}

			public function getCurrentMailbox()
			{
				return $this->mailbox;
			}

			public function store($mailbox, array $options)
			{
				$this->stores[] = array('mailbox' => $mailbox, 'options' => $options);
			}
		};

		$this->assertTrue($mail->flagMessages('unflagged', 17, 'INBOX'));
		$this->assertCount(1, $mail->icServer->stores);
		$this->assertSame('INBOX', $mail->icServer->stores[0]['mailbox']);
		$this->assertSame(
			array('\\Flagged', '$customflag1', '$customflag2', '$customflag3', '$customflag4', '$customflag5'),
			$mail->icServer->stores[0]['options']['remove']
		);
	}
}
