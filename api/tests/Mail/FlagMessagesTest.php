<?php
/**
 * EGroupware API: Mail flag mutation and flag-search tests
 *
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api;

use PHPUnit\Framework\TestCase;
use ReflectionClass;

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

	/**
	 * The colored custom flags are searchable statuses, so mail's app-header flag filter can
	 * reach the classic IMAP query used by every "apply to all matching filter" operation
	 * (mail's MessageActionHandler::statusCriteria()).
	 *
	 * The fixture asks createIMAPFilter() for each of the five flags plus a lookalike that is
	 * not one. It passes when each real flag becomes a search for its own $customflagN keyword,
	 * and the lookalike is not interpreted as a status at all.
	 */
	public function testCustomFlagStatusIsSearchable()
	{
		$mail = (new ReflectionClass(Mail::class))->newInstanceWithoutConstructor();

		foreach (range(1, 5) as $n)
		{
			$query = $mail->createIMAPFilter('INBOX', array('status' => 'customFlag'.$n))->build();
			$this->assertStringContainsString(
				'KEYWORD $CUSTOMFLAG'.$n,
				(string)$query['query'],
				"customFlag$n should search for its own IMAP keyword"
			);
		}

		$unknown = $mail->createIMAPFilter('INBOX', array('status' => 'customFlag6'))->build();
		$this->assertStringNotContainsString(
			'CUSTOMFLAG',
			(string)$unknown['query'],
			'Only customFlag1-5 exist - anything else must not be inferred to be a keyword status'
		);
	}

	/**
	 * The flag filter is an own criterion the list ANDs with the status filter, so both have to
	 * survive into one IMAP query when they are set together.
	 *
	 * The fixture passes the two as the array of statuses MessageActionHandler::statusCriteria()
	 * produces. It passes when the resulting query searches for the unread state AND the flag.
	 */
	public function testStatusAndCustomFlagCombine()
	{
		$mail = (new ReflectionClass(Mail::class))->newInstanceWithoutConstructor();

		$query = (string)$mail->createIMAPFilter('INBOX', array('status' => array('unseen', 'customFlag2')))->build()['query'];

		$this->assertStringContainsString('UNSEEN', $query, 'the status filter must survive');
		$this->assertStringContainsString('KEYWORD $CUSTOMFLAG2', $query, 'the flag filter must survive');
	}
}
