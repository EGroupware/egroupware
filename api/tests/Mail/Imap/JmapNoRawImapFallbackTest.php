<?php
/**
 * EGroupware Api: Mail\Imap\Jmap must never fall back to a raw IMAP connection
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Imap;

require_once realpath(__DIR__.'/../../AppTest.php');

/**
 * getACL()/setACL()/deleteACL() used to call parent::method() (Horde_Imap_Client_Base's real,
 * raw-socket IMAP implementation) whenever mailShareSupported() was false. Stalwart (the only real
 * subclass of this class) is JMAP-only - it has no real IMAP connection behind it, ever, unlike
 * JmapShim, which wraps an actual IMAP server. That fallback could therefore only ever
 * raw-socket-connect to a JMAP(S)-only endpoint via openMailbox() (unguarded on this class - see
 * project_jmap_imap_fallthrough_cleanup) and hang until a slow connect timeout, before finally
 * failing with Horde's generic "Error when communicating with the mail server".
 *
 * Confirmed live (ticket #124351 follow-up, real access/error log from boulder.egroupware.org):
 * opening the mail_acl dialog for a real Stalwart account (mail:share not detected as supported for
 * that request) hung for ~20s before mail_acl.inc.php's own error handling finally showed "Die
 * Zugriffsrechte konnten auf dem IMAP Server nicht gelesen werden!".
 *
 * These tests use a constructor-skipping mock (real construction needs a live JMAP session) with
 * only mailShareSupported() stubbed, so the real getACL()/setACL()/deleteACL() code runs - proving
 * they return/throw immediately without ever reaching the inherited parent:: methods (which would
 * touch uninitialized Horde_Imap_Client_Base internals this test double never sets up, and fail
 * completely differently - a TypeError/Error, not the controlled behaviour asserted here).
 */
class JmapNoRawImapFallbackTest extends \EGroupware\Api\AppTest
{
	private function jmapWithShareSupport(bool $supported) : Jmap
	{
		$mock = $this->getMockBuilder(Jmap::class)
			->disableOriginalConstructor()
			->onlyMethods(['mailShareSupported'])
			->getMock();
		$mock->method('mailShareSupported')->willReturn($supported);
		return $mock;
	}

	public function testGetACLReturnsFalseWithoutRawImapFallback()
	{
		$jmap = $this->jmapWithShareSupport(false);

		$this->assertFalse($jmap->getACL('INBOX'));
	}

	public function testSetACLThrowsWithoutRawImapFallback()
	{
		$jmap = $this->jmapWithShareSupport(false);

		$this->expectException(\Horde_Imap_Client_Exception::class);
		$jmap->setACL('INBOX', 'someone@example.com', ['rights' => 'lr']);
	}

	public function testDeleteACLThrowsWithoutRawImapFallback()
	{
		$jmap = $this->jmapWithShareSupport(false);

		$this->expectException(\Horde_Imap_Client_Exception::class);
		$jmap->deleteACL('INBOX', 'someone@example.com');
	}
}
