<?php
/**
 * EGroupware API: flag/delete mutations on a mail:share-shared mailbox via the real-JMAP
 * (Stalwart) PHP fallback path
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Mail;
use PHPUnit\Framework\TestCase;

/**
 * Nordenham ticket (2026-11-25, handed over from another session): Api\Mail::jmapFlagMessages()/
 * jmapDeleteMessages() always sent Email/set under the VIEWER's own JMAP accountId, even for a
 * row from a mail:share-shared mailbox owned by a DIFFERENT account - a JMAP Email id is only
 * unique WITHIN its own account (no RFC 8621 global-id guarantee), so this produced
 * "Email/set update failed: {id:{type:notFound}}" (seen for irimod01's flagMessages() calls) and
 * could, worse, silently mutate an unrelated same-id message in the viewer's own account.
 *
 * Fixed by decoding the row-id's folderID (Mail::decodeSharedMailboxId(), already used
 * server-side for reads via AttachmentJmap.php) and threading the real owning accountId into
 * Api\Mail\Jmap\Email::emailSetKeywords()/emailDestroy()/emailMove() (mirroring emailGet()'s own
 * $accountId parameter), plus a new emailCopy()/emailKeywords() pair for the "move a shared
 * mailbox's message into MY OWN Trash" case, which is architecturally a cross-account transfer
 * (RFC 8620 §1.2 - mailboxIds can only reference the SAME account) and needs Email/copy +
 * destroy-the-originals instead of a plain mailboxIds patch, same split
 * MailJmap.moveOrCopyIds() already uses client-side (mail/js/jmap.ts).
 */
class JmapSharedMailboxMutationTest extends TestCase
{
	private function mailInstance() : Mail
	{
		return new class extends Mail
		{
			public function __construct()
			{
			}

			/** avoid _getSpecialUseFolder()'s real preference/DB lookup */
			function getTrashFolder($_checkexistance=true)
			{
				return 'INBOX/Trash';
			}
		};
	}

	/** @return object a fake Http-shaped jmapClient() result - captures every call this code makes */
	private function fakeJmapClient(array $overrides = []) : object
	{
		return new class($overrides) {
			public array $setKeywordsCalls = [];
			public array $destroyCalls = [];
			public array $moveCalls = [];
			public array $copyCalls = [];
			public array $keywordsCalls = [];
			private array $overrides;
			public function __construct(array $overrides) { $this->overrides = $overrides; }
			public function emailSetKeywords(array $ids, array $patch, ?string $accountId=null) : void
			{
				$this->setKeywordsCalls[] = compact('ids', 'patch', 'accountId');
			}
			public function emailDestroy(array $ids, ?string $accountId=null) : void
			{
				$this->destroyCalls[] = compact('ids', 'accountId');
			}
			public function emailMove(array $ids, string $targetFolder, ?string $accountId=null) : void
			{
				$this->moveCalls[] = compact('ids', 'targetFolder', 'accountId');
			}
			public function emailCopy(string $fromAccountId, array $ids, string $targetMailboxId, array $keywordsById=[]) : array
			{
				$this->copyCalls[] = compact('fromAccountId', 'ids', 'targetMailboxId', 'keywordsById');
				return array_combine($ids, array_map(static fn($id) => 'new-'.$id, $ids));
			}
			public function emailKeywords(string $accountId, array $ids) : array
			{
				$this->keywordsCalls[] = compact('accountId', 'ids');
				return $this->overrides['emailKeywords'] ?? array_fill_keys($ids, ['$seen' => true]);
			}
			public function getMailboxId(string $folder, ?string $accountId=null) : ?string
			{
				return $this->overrides['getMailboxId'] ?? 'own-trash-mailbox-id';
			}
		};
	}

	/** A real-JMAP account stand-in - Mail\Imap\Jmap::jmapClient() overridden to return the fake above. */
	private function fakeJmapAccount(object $jmapClient) : Mail\Imap\Jmap
	{
		return new class($jmapClient) extends Mail\Imap\Jmap
		{
			private object $fakeClient;
			public function __construct(object $fakeClient)
			{
				$this->fakeClient = $fakeClient;
			}
			public function jmapClient()
			{
				return $this->fakeClient;
			}
		};
	}

	private function sharedFolderID(string $accountId, string $mailboxId='shared-mailbox-id') : string
	{
		return Mail::SHARED_ACCOUNTS_ROOT_ID.$accountId.Mail::SHARED_ACCOUNT_SEP.$mailboxId;
	}

	/** Flagging a shared mailbox's message must patch keywords under ITS OWN (not the viewer's) accountId. */
	public function testFlagMessagesPassesSharedAccountId()
	{
		$mail = $this->mailInstance();
		$client = $this->fakeJmapClient();
		$mail->icServer = $this->fakeJmapAccount($client);

		$mail->flagMessages('flagged', ['e1'], 'user/shared-user/Spam', $this->sharedFolderID('shared-acc-42'));

		$this->assertCount(1, $client->setKeywordsCalls);
		$this->assertSame(['e1'], $client->setKeywordsCalls[0]['ids']);
		$this->assertSame(['keywords/$flagged' => true], $client->setKeywordsCalls[0]['patch']);
		$this->assertSame('shared-acc-42', $client->setKeywordsCalls[0]['accountId']);
	}

	/** Flagging the viewer's OWN mailbox (no shared-account encoding) must pass a null accountId override. */
	public function testFlagMessagesOwnAccountPassesNullAccountId()
	{
		$mail = $this->mailInstance();
		$client = $this->fakeJmapClient();
		$mail->icServer = $this->fakeJmapAccount($client);

		$mail->flagMessages('flagged', ['e1'], 'INBOX');

		$this->assertNull($client->setKeywordsCalls[0]['accountId']);
	}

	/** An explicit permanent-delete on a shared mailbox's message destroys it under ITS OWN accountId. */
	public function testDeleteMessagesDestroyPassesSharedAccountId()
	{
		$mail = $this->mailInstance();
		$client = $this->fakeJmapClient();
		$mail->icServer = $this->fakeJmapAccount($client);

		$mail->deleteMessages(['e1', 'e2'], 'user/shared-user/Spam', 'remove_immediately',
			$this->sharedFolderID('shared-acc-42'));

		$this->assertCount(1, $client->destroyCalls);
		$this->assertSame(['e1', 'e2'], $client->destroyCalls[0]['ids']);
		$this->assertSame('shared-acc-42', $client->destroyCalls[0]['accountId']);
		$this->assertCount(0, $client->copyCalls, 'a destroy must never go through the copy path');
	}

	/**
	 * Moving a shared mailbox's message to the viewer's OWN Trash is a cross-account transfer
	 * (RFC 8620 §1.2) - must copy (preserving keywords) into the viewer's own Trash and only then
	 * destroy the originals in the shared account, NOT a plain same-account mailboxIds patch.
	 */
	public function testDeleteMessagesMoveToTrashSharedAccountUsesCopyThenDestroy()
	{
		$mail = $this->mailInstance();
		$client = $this->fakeJmapClient(['emailKeywords' => ['e1' => ['$seen' => true, '$flagged' => true]]]);
		$mail->icServer = $this->fakeJmapAccount($client);

		$mail->deleteMessages(['e1'], 'user/shared-user/Spam', 'move_to_trash', $this->sharedFolderID('shared-acc-42'));

		$this->assertCount(0, $client->moveCalls, 'a cross-account move must never use the same-account move path');
		$this->assertCount(1, $client->keywordsCalls);
		$this->assertSame('shared-acc-42', $client->keywordsCalls[0]['accountId']);
		$this->assertCount(1, $client->copyCalls);
		$this->assertSame('shared-acc-42', $client->copyCalls[0]['fromAccountId']);
		$this->assertSame(['e1'], $client->copyCalls[0]['ids']);
		$this->assertSame('own-trash-mailbox-id', $client->copyCalls[0]['targetMailboxId']);
		$this->assertSame(['$seen' => true, '$flagged' => true], $client->copyCalls[0]['keywordsById']['e1']);
		$this->assertCount(1, $client->destroyCalls, 'the originals must be destroyed in the SHARED account, once copied');
		$this->assertSame('shared-acc-42', $client->destroyCalls[0]['accountId']);
	}

	/** Moving the viewer's OWN message to their own Trash is unaffected - still a plain same-account move. */
	public function testDeleteMessagesMoveToTrashOwnAccountStillUsesPlainMove()
	{
		$mail = $this->mailInstance();
		$client = $this->fakeJmapClient();
		$mail->icServer = $this->fakeJmapAccount($client);

		$mail->deleteMessages(['e1'], 'INBOX', 'move_to_trash');

		$this->assertCount(1, $client->moveCalls);
		$this->assertSame(['e1'], $client->moveCalls[0]['ids']);
		$this->assertSame('INBOX/Trash', $client->moveCalls[0]['targetFolder']);
		$this->assertNull($client->moveCalls[0]['accountId']);
		$this->assertCount(0, $client->copyCalls);
		$this->assertCount(0, $client->destroyCalls);
	}
}
