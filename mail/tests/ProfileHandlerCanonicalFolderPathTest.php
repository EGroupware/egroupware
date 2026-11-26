<?php
/**
 * EGroupware Mail: Mail\Ui\ProfileHandler::canonicalFolderPath()'s raw-IMAP-name translation
 *
 * @link http://www.egroupware.org
 * @package mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License Version 2+
 */

use EGroupware\Api\Mail\Imap;
use EGroupware\Mail\Ui\ProfileHandler;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;

require_once realpath(__DIR__.'/../../api/tests/LoggedInTest.php');

/**
 * Ticket #128701 (2026-11-26, a real external IMAP account using Dovecot's classic "."
 * delimiter): jmapBootstrap() handed the client trashFolder/junkFolder straight from
 * acc_folder_trash/_junk - the server's OWN raw IMAP mailbox name (eg. "INBOX.Trash") - but
 * MailJmap.mailboxId() (mail/js/jmap.ts) resolves that value as an EGroupware-canonical
 * "/"-joined path, one segment at a time. A raw name using any delimiter other than "/" split
 * into one bogus top-level segment, which correctly matched no mailbox - "Folder 'INBOX.Trash'
 * not found" deleting any message (context menu, the trash toolbar icon, and
 * moveAllMatching()'s "empty trash"/"empty junk" fast path all go through the same
 * trashFolder/junkFolder bootstrap value). Previously masked for the common case only because
 * EGroupware's own hosted Dovecot (and most modern Dovecot defaults) already use "/" as the
 * delimiter, making the missing translation a no-op there.
 *
 * canonicalFolderPath() is jmapBootstrap()'s fix: translate the raw configured name through
 * Mail\Jmap\Imap::canonicalPath() (the shim's own reverse-of-hordeMailbox() helper) before
 * handing it to the client.
 */
#[AllowMockObjectsWithoutExpectations]
class ProfileHandlerCanonicalFolderPathTest extends \PHPUnit\Framework\TestCase
{
	private function mockImap(string $delimiter) : Imap
	{
		$imap = $this->getMockBuilder(Imap::class)
			->disableOriginalConstructor()
			->onlyMethods(['getNameSpaceArray'])
			->getMock();
		$imap->method('getNameSpaceArray')->willReturn([
			'personal' => [['delimiter' => $delimiter, 'name' => '']],
		]);
		return $imap;
	}

	private function call(Imap $imap, ?string $rawName) : ?string
	{
		$method = new ReflectionMethod(ProfileHandler::class, 'canonicalFolderPath');
		$method->setAccessible(true);
		return $method->invoke(null, $imap, $rawName);
	}

	public function testDotDelimiterRawNameIsTranslatedToASlashJoinedPath()
	{
		$this->assertSame('INBOX/Trash', $this->call($this->mockImap('.'), 'INBOX.Trash'));
	}

	public function testSlashDelimiterServerIsAlreadyCanonicalAndUnaffected()
	{
		$this->assertSame('INBOX/Trash', $this->call($this->mockImap('/'), 'INBOX/Trash'));
	}

	public function testBareTopLevelNameNeedsNoTranslationEitherWay()
	{
		$this->assertSame('Trash', $this->call($this->mockImap('.'), 'Trash'));
	}

	public function testEmptyOrNullRawNameReturnsNullRatherThanCallingTheServerAtAll()
	{
		$this->assertNull($this->call($this->mockImap('.'), null));
		$this->assertNull($this->call($this->mockImap('.'), ''));
	}

	public function testAConnectionFailureDegradesToTheRawNameUnchangedRatherThanThrowing()
	{
		$imap = $this->getMockBuilder(Imap::class)
			->disableOriginalConstructor()
			->onlyMethods(['getNameSpaceArray'])
			->getMock();
		$imap->method('getNameSpaceArray')->willThrowException(new \Exception('Could not connect'));

		$this->assertSame('INBOX.Trash', $this->call($imap, 'INBOX.Trash'));
	}
}
