<?php
/**
 * EGroupware API: Mail::decodeSharedMailboxId() - PHP counterpart of jmap.ts's own
 * MailJmap.decodeSharedMailboxId()
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api\Mail;

/**
 * Found live 2026-11-08 (ralf, acc_id 89, folder "user/info/INBOX"): opening a shared account's
 * message showed a completely unrelated message from the viewer's own mailbox - getRows() (JS)
 * already encodes the message's REAL owning accountId into its row_id's mailboxId segment
 * (SHARED_ACCOUNTS_ROOT_ID + accountId + SHARED_ACCOUNT_SEP + realMailboxId), but every per-row
 * JMAP call on the client just used the viewer's own accountId regardless - fixed client-side via
 * MailJmap.rowAccountId(). This is the PHP-side half of the fix: a couple of server-side row-id
 * consumers (AttachmentJmap::resolveAttachmentsJmap()/resolveWinmailJmap()) make their own direct
 * Email/get calls too, with the exact same bug - this decoder lets them recognize and decode the
 * encoding instead of silently treating it as a literal (wrong-account) JMAP Mailbox id.
 *
 * Also found live the same day: the original sentinel used NUL (\0) bytes, which a per-row JMAP
 * *action* (not a plain view) silently turned into a no-op for - the datagrid's own selection
 * tracking round-trips a row id through an HTML string somewhere, and the HTML5 tokenizer replaces
 * an embedded NUL with U+FFFD (every other C0 control byte survives). Switched to \x01/\x02 - see
 * Mail::SHARED_ACCOUNTS_ROOT_ID's own docblock.
 *
 * DB-free bare TestCase - a pure static string-decoding helper, no account/DB involved at all.
 */
class DecodeSharedMailboxIdTest extends \PHPUnit\Framework\TestCase
{
	public function testDecodesAValidSharedMailboxId()
	{
		$encoded = "\x01shared-accounts\x01".'o'."\x02".'real-inbox-id';

		$result = Mail::decodeSharedMailboxId($encoded);

		$this->assertSame(['accountId' => 'o', 'mailboxId' => 'real-inbox-id'], $result);
	}

	public function testDecodesWhenTheRealMailboxIdItselfContainsNoSpecialBytes()
	{
		// a Stalwart mailbox id can be any opaque string - only the FIRST \x02 after the sentinel
		// prefix is the separator, so a longer/multi-char accountId or mailboxId still round-trips
		$result = Mail::decodeSharedMailboxId("\x01shared-accounts\x01accountABC\x02mailboxXYZ");

		$this->assertSame(['accountId' => 'accountABC', 'mailboxId' => 'mailboxXYZ'], $result);
	}

	public function testReturnsNullForAnOrdinaryUnprefixedMailboxId()
	{
		$this->assertNull(Mail::decodeSharedMailboxId('INBOX'));
		$this->assertNull(Mail::decodeSharedMailboxId('a'));
	}

	public function testReturnsNullForNull()
	{
		$this->assertNull(Mail::decodeSharedMailboxId(null));
	}

	public function testReturnsNullForEmptyString()
	{
		$this->assertNull(Mail::decodeSharedMailboxId(''));
	}

	public function testReturnsNullWhenThePrefixMatchesButTheSeparatorIsMissing()
	{
		// malformed/truncated - must fail safely (treated as "not shared"), never half-decode
		$this->assertNull(Mail::decodeSharedMailboxId("\x01shared-accounts\x01justAnAccountIdNoSeparator"));
	}

	public function testBase64EncodedClassicFolderNameNeverFalsePositives()
	{
		// a classic (non-Stalwart) row's folder segment is base64_encode(folder name) - must never
		// be mistaken for the shared-account encoding, whatever bytes base64 happens to produce
		$this->assertNull(Mail::decodeSharedMailboxId(base64_encode('INBOX/Some Folder')));
	}
}
