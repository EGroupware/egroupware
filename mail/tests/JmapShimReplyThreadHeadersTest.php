<?php
/**
 * Test EGroupware\Api\Mail\Jmap\Imap's Thread-Topic/Thread-Index/List-Id reply-propagation
 * header properties (emailGet()/emailFromFetch()) - the READ side of the regression found live
 * 2026-09-09 (ralf, relaying a tester report about the related Reply-To bug, then asking: "Does
 * that mean they [Thread-Topic/Thread-Index/List-Id] are lost when replying to a mail? ... it
 * would be a real regression we need to fix"). Confirmed: classic mail_compose's own
 * getReplyData() propagated these from the original message onto a reply (2014 commit
 * 2172fc769d "support the propagation of Thread-Topic, Thread-Index and List-Id on reply too"),
 * but MailJmap.fetchForReply() (mail/js/jmap.ts) never requested or read them at all - this shim
 * method (its server-side counterpart) never even offered the properties in the first place.
 *
 * emailFromFetch() is public static and takes an already-constructed Horde_Imap_Client_Socket/
 * Horde_Imap_Client_Data_Fetch as plain parameters (no self::imapServer() call inside it), so -
 * same as the append/fetch primitives in JmapShimRawMessageByteFidelityTest.php - this is
 * directly testable with a stub socket and a real (plain, setter-based, no mocking needed)
 * Horde_Imap_Client_Data_Fetch fixture. No DB/IMAP/session needed.
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap as JmapShim;

class JmapShimReplyThreadHeadersTest extends \PHPUnit\Framework\TestCase
{
	private function emailFromFetch(\Horde_Imap_Client_Data_Fetch $data, bool $wantThreadHeaders) : array
	{
		$imap = $this->createStub(\Horde_Imap_Client_Socket::class);
		// wantPreview/wantBody both false - neither needs a working $imap (see emailFromFetch()'s
		// own docblock), keeping this test independent of any real IMAP connection
		return JmapShim::emailFromFetch($imap, 'INBOX', '42', $data,
			false, false, false, false, false, $wantThreadHeaders);
	}

	public function testThreadTopicIndexAndListIdArePopulatedWhenTheOriginalMessageHasThem()
	{
		$data = new \Horde_Imap_Client_Data_Fetch();
		$data->setHeaders('threadheaders',
			"Thread-Topic: Original subject\r\n".
			"Thread-Index: AQHTest1234567890abcdefg==\r\n".
			"List-Id: My List <mylist.example.org>\r\n\r\n");

		$email = $this->emailFromFetch($data, true);

		$this->assertSame('Original subject', $email[JmapShim::THREAD_TOPIC_HEADER_PROPERTY]);
		$this->assertSame('AQHTest1234567890abcdefg==', $email[JmapShim::THREAD_INDEX_HEADER_PROPERTY]);
		$this->assertSame('My List <mylist.example.org>', $email[JmapShim::LIST_ID_HEADER_PROPERTY]);
	}

	public function testThreadHeaderPropertiesAreNullWhenTheOriginalMessageHasNoneOfThem()
	{
		$data = new \Horde_Imap_Client_Data_Fetch();

		$email = $this->emailFromFetch($data, true);

		$this->assertNull($email[JmapShim::THREAD_TOPIC_HEADER_PROPERTY]);
		$this->assertNull($email[JmapShim::THREAD_INDEX_HEADER_PROPERTY]);
		$this->assertNull($email[JmapShim::LIST_ID_HEADER_PROPERTY]);
	}

	/** Only present at all when actually requested (properties list included them) - same "don't pay for it unless asked" contract as MDN/content-type. */
	public function testThreadHeaderPropertiesAreOmittedEntirelyWhenNotRequested()
	{
		$data = new \Horde_Imap_Client_Data_Fetch();
		$data->setHeaders('threadheaders', "Thread-Topic: Should not appear\r\n\r\n");

		$email = $this->emailFromFetch($data, false);

		$this->assertArrayNotHasKey(JmapShim::THREAD_TOPIC_HEADER_PROPERTY, $email);
		$this->assertArrayNotHasKey(JmapShim::THREAD_INDEX_HEADER_PROPERTY, $email);
		$this->assertArrayNotHasKey(JmapShim::LIST_ID_HEADER_PROPERTY, $email);
	}

	/** Only Thread-Topic present - Thread-Index/List-Id must independently stay null, not fall back to some other header's value. */
	public function testEachHeaderIsResolvedIndependently()
	{
		$data = new \Horde_Imap_Client_Data_Fetch();
		$data->setHeaders('threadheaders', "Thread-Topic: Only this one\r\n\r\n");

		$email = $this->emailFromFetch($data, true);

		$this->assertSame('Only this one', $email[JmapShim::THREAD_TOPIC_HEADER_PROPERTY]);
		$this->assertNull($email[JmapShim::THREAD_INDEX_HEADER_PROPERTY]);
		$this->assertNull($email[JmapShim::LIST_ID_HEADER_PROPERTY]);
	}
}
