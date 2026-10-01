<?php
/**
 * Test EGroupware\Api\Mail\Jmap\Imap's emailSubmissionSet() no-recipients/no-subject guards
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Mail;

use EGroupware\Api\Mail\Jmap\Imap;

/**
 * Ticket #125161 follow-up (Noje, live: the "Field must not be empty!!!" warning box showed but
 * the mail still sent with a blank subject) - MailCompose.hasNoSubject()/hasNoRecipientsAtAll()
 * (mail/js/compose.ts) are client-side only and run inside trySendViaJmap(), whose exact bypass
 * mechanism for this report was never confirmed. hasNoRecipientsAtAll()/hasNoSubject() here are
 * emailSubmissionSet()'s own server-side mirror of those same two checks - the one place every
 * shim submission passes through regardless of how it got there, so a check here can't be
 * bypassed by any client-side path. Both are private static and pure (plain array in, bool out,
 * no IMAP/DB), so directly reachable via reflection without any LoggedInTest/account setup.
 */
class JmapShimEmailSubmissionGuardsTest extends \PHPUnit\Framework\TestCase
{
	private function hasNoRecipientsAtAll(array $email) : bool
	{
		$reflection = new \ReflectionMethod(Imap::class, 'hasNoRecipientsAtAll');
		$reflection->setAccessible(true);
		return $reflection->invoke(null, $email);
	}

	private function hasNoSubject(array $email) : bool
	{
		$reflection = new \ReflectionMethod(Imap::class, 'hasNoSubject');
		$reflection->setAccessible(true);
		return $reflection->invoke(null, $email);
	}

	// --- hasNoRecipientsAtAll() ---

	public function testTrueWhenToCcBccAreAllAbsent()
	{
		$this->assertTrue($this->hasNoRecipientsAtAll([]));
	}

	public function testTrueWhenToCcBccAreAllEmptyArrays()
	{
		$this->assertTrue($this->hasNoRecipientsAtAll(['to' => [], 'cc' => [], 'bcc' => []]));
	}

	/** A re-fetched JMAP Email's own address-list shape ({name, email} objects), never a raw string. */
	public function testFalseWhenToHasAtLeastOneRealAddress()
	{
		$this->assertFalse($this->hasNoRecipientsAtAll(['to' => [['name' => 'Jane', 'email' => 'jane@example.org']]]));
	}

	public function testFalseWhenOnlyCcHasAnAddressToAndBccAreEmpty()
	{
		$this->assertFalse($this->hasNoRecipientsAtAll([
			'to' => [], 'cc' => [['email' => 'cc@example.org']], 'bcc' => [],
		]));
	}

	public function testFalseWhenOnlyBccHasAnAddress()
	{
		$this->assertFalse($this->hasNoRecipientsAtAll(['bcc' => [['email' => 'hidden@example.org']]]));
	}

	/** An entry with no 'email' key at all (malformed) must not count as a real recipient. */
	public function testTrueWhenTheOnlyEntryHasNoEmailKey()
	{
		$this->assertTrue($this->hasNoRecipientsAtAll(['to' => [['name' => 'Jane']]]));
	}

	// --- hasNoSubject() ---

	public function testTrueWhenSubjectIsAbsent()
	{
		$this->assertTrue($this->hasNoSubject([]));
	}

	public function testTrueWhenSubjectIsAnEmptyString()
	{
		$this->assertTrue($this->hasNoSubject(['subject' => '']));
	}

	public function testTrueWhenSubjectIsWhitespaceOnly()
	{
		$this->assertTrue($this->hasNoSubject(['subject' => "  \t\n"]));
	}

	public function testFalseWhenSubjectHasRealContent()
	{
		$this->assertFalse($this->hasNoSubject(['subject' => 'Hello']));
	}
}
