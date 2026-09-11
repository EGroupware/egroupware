<?php
/**
 * EGroupware Api: Mail\FolderHelpers tests
 *
 * @link http://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License Version 2+
 */

namespace EGroupware\Api\Mail;

use PHPUnit\Framework\TestCase;

/**
 * Pure tests for Mail\FolderHelpers - no database/session/IMAP connection required.
 */
class FolderHelpersTest extends TestCase
{
	public function testDecodeEntityFolderNameRemovesHtmlEntities()
	{
		\EGroupware\Api\Mail::$displayCharset = 'utf-8';

		$this->assertSame('Foo & Bar', FolderHelpers::decodeEntityFolderName('Foo &amp; Bar'));
	}

	public function testSearchValueInFolderObjectsFindsTheKey()
	{
		$haystack = [
			'INBOX' => ['MAILBOX' => 'INBOX'],
			'INBOX.Drafts' => ['MAILBOX' => 'INBOX.Drafts'],
		];

		$this->assertSame('INBOX.Drafts', FolderHelpers::searchValueInFolderObjects('INBOX.Drafts', $haystack));
	}

	public function testSearchValueInFolderObjectsReturnsFalseWhenNotFound()
	{
		$this->assertFalse(FolderHelpers::searchValueInFolderObjects('missing', ['a' => ['x']]));
	}

	public function testPathToFolderDataExtractsNameAndParent()
	{
		$result = FolderHelpers::pathToFolderData('1::INBOX.Sub.Leaf', '.');

		$this->assertSame('Leaf', $result['name']);
		$this->assertSame('INBOX.Sub', $result['parent']);
		$this->assertSame('INBOX.Sub.Leaf', $result['mailbox']);
	}

	public function testPathToFolderDataHandlesTopLevelFolder()
	{
		$result = FolderHelpers::pathToFolderData('1::INBOX', '.');

		$this->assertSame('INBOX', $result['name']);
		$this->assertSame('', $result['parent']);
	}
}
