<?php
/**
 * EGroupware Api: Mail\Imap\Jmap::getNameSpaceArray() populates 'others' from shared JMAP accounts
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Mail\Imap;

use EGroupware\Api\Jmap\Base;

require_once realpath(__DIR__.'/../../AppTest.php');

/**
 * Ticket #126691 follow-up (doc/ai/projects/mail-jmap-shared-mailboxes.md): getNameSpaceArray()
 * used to always return an 'others'-free result ("RFC 8621 has no NAMESPACE concept"), which was
 * true of the protocol but not of whether shared mailboxes exist - confirmed live that Stalwart
 * already exposes them as extra Session accounts from existing ACL grants alone. 'others' is now
 * populated whenever the account actually has any.
 */
class JmapGetNameSpaceArraySharedAccountsTest extends \EGroupware\Api\AppTest
{
	private function jmapWithSharedAccounts(array $sharedAccounts) : Jmap
	{
		$client = new class($sharedAccounts) extends Base {
			public function __construct(public array $sharedAccounts) {}
		};
		$mock = $this->getMockBuilder(Jmap::class)
			->disableOriginalConstructor()
			->onlyMethods(['jmapClient'])
			->getMock();
		$mock->method('jmapClient')->willReturn($client);
		return $mock;
	}

	public function testOthersIsAbsentWhenThereAreNoSharedAccounts()
	{
		$jmap = $this->jmapWithSharedAccounts([]);

		$result = $jmap->getNameSpaceArray();

		$this->assertArrayHasKey('personal', $result);
		$this->assertArrayNotHasKey('others', $result, 'no empty/dead namespace entry when nothing is shared');
	}

	public function testOthersUsesTheClassicRawUserPrefixWhenSharedAccountsExist()
	{
		$jmap = $this->jmapWithSharedAccounts(['u' => ['name' => 'info@example.invalid', 'isPersonal' => false]]);

		$result = $jmap->getNameSpaceArray();

		$this->assertArrayHasKey('others', $result);
		$this->assertSame('user', $result['others'][0]['prefix'], 'raw prefix stays "user" - matches classic IMAP and folderTree.ts\'s isNamespaceRootName()');
		$this->assertSame('/', $result['others'][0]['delimiter']);
	}

	public function testPersonalIsAlwaysPresentRegardless()
	{
		$jmap = $this->jmapWithSharedAccounts(['u' => ['name' => 'info@example.invalid', 'isPersonal' => false]]);

		$result = $jmap->getNameSpaceArray();

		$this->assertSame('', $result['personal'][0]['prefix']);
	}
}
