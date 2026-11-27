<?php
/**
 * EGroupware Api: JMAP Email type, real-JMAP-over-HTTP implementation
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail\Jmap;

use EGroupware\Api;
use EGroupware\Api\Jmap\Type;

/**
 * RFC 8621 §4 Email - get()/query()/set() use `Type`'s generic default; the methods below are
 * this app's own higher-level convenience wrappers (folder-path resolution, keyword patching,
 * move-as-mailboxIds-replace, ...), promoted from the former `Api\Mail\Jmap` client class.
 */
class Email extends Type
{
	const TYPE_NAME = 'Email';

	/**
	 * Fetch one Email via Email/get with the given properties
	 *
	 * @param string $id JMAP Email id
	 * @param array $properties e.g. ['bodyStructure','textBody','htmlBody','attachments','bodyValues']
	 * @param bool $fetchAllBodyValues
	 * @param string|null $accountId defaults to the session's own accountId - MUST be given
	 *  explicitly for an id belonging to a mail:share-shared account (same reasoning as
	 *  Mailbox::folderId2path()'s own $accountId param): a JMAP Email id is only unique WITHIN its
	 *  own account (no RFC 8621 global-id guarantee), so looking one up under the wrong accountId
	 *  doesn't reliably fail - it can silently return a real, unrelated Email that just happens to
	 *  share the same id in the WRONG account (see Api\Mail::decodeSharedMailboxId()'s own docblock)
	 * @return array the Email object
	 * @throws Api\Exception if not found
	 */
	public function emailGet(string $id, array $properties, bool $fetchAllBodyValues=true, ?string $accountId=null) : array
	{
		$args = [
			'accountId' => $accountId ?: $this->jmap->accountId,
			'ids' => [$id],
			'properties' => $properties,
		];
		if ($fetchAllBodyValues)
		{
			$args['fetchAllBodyValues'] = true;
		}
		$response = $this->jmap->call('Email/get', $args);
		return $response['list'][0] ?? throw new Api\Exception("Email '$id' not found via Email/get");
	}

	/**
	 * Query Email ids in a folder matching simple keyword/text conditions, sorted.
	 *
	 * @param string $folder folder-path e.g. "INBOX" (resolved to a Mailbox id internally)
	 * @param array $conditions JMAP FilterCondition objects, ANDed together (e.g. ['notKeyword' => '$seen'])
	 * @param string $sortProperty 'receivedAt'|'sentAt'|'subject'|'from'|'to'|'size'
	 * @param bool $ascending
	 * @param int $limit
	 * @return string[] JMAP Email ids, in sort order
	 * @throws Api\Exception folder not found, or on any JMAP error
	 */
	public function emailQuery(string $folder, array $conditions, string $sortProperty='receivedAt',
		bool $ascending=false, int $limit=1000) : array
	{
		$mailboxId = $this->jmap->mailbox->getMailboxId($folder);
		if ($mailboxId === null)
		{
			throw new Api\Exception("Folder '$folder' not found");
		}
		$response = $this->jmap->call('Email/query', [
			'accountId' => $this->jmap->accountId,
			'filter' => ['operator' => 'AND', 'conditions' => array_merge([['inMailbox' => $mailboxId]], $conditions)],
			'sort' => [['property' => $sortProperty, 'isAscending' => $ascending]],
			'limit' => $limit,
		]);
		return $response['ids'] ?? throw new Api\Exception(__METHOD__.': Unexpected response: '.json_encode($response));
	}

	/**
	 * RFC 8621 §4.8 Email/import - create a new message from an uploaded blob (see
	 * Http::uploadBlob()) into a mailbox.
	 *
	 * @param string $blobId from Http::uploadBlob()
	 * @param string $folder folder-path e.g. "INBOX/Drafts" (resolved to a Mailbox id internally)
	 * @param array $keywords JMAP keyword => true, e.g. ['$seen' => true]
	 * @param ?string $receivedAt RFC 8621 UTCDate - Api\Mail's own cross-account move/copy
	 *  (jmapCrossAccountTransfer()) passes the ORIGINAL message's own receivedAt through here, so
	 *  the imported copy keeps its real arrival date instead of getting "now" (RFC 8621 §4.8's own
	 *  default when omitted)
	 * @return string new Email.id
	 * @throws Api\Exception
	 */
	public function emailImport(string $blobId, string $folder, array $keywords=[], ?string $receivedAt=null) : string
	{
		$mailboxId = $this->jmap->mailbox->getMailboxId($folder);
		if (!$mailboxId)
		{
			throw new Api\Exception("Mailbox '$folder' not found");
		}
		$response = $this->jmap->call('Email/import', [
			'accountId' => $this->jmap->accountId,
			'emails' => [
				'x' => [
					'blobId' => $blobId,
					'mailboxIds' => [$mailboxId => true],
					'keywords' => $keywords ?: new \stdClass(),
					...($receivedAt ? ['receivedAt' => $receivedAt] : []),
				],
			],
		]);
		return $response['created']['x']['id'] ??
			throw new Api\Exception('Email/import failed: '.json_encode($response['notCreated'] ?? []));
	}

	/**
	 * RFC 8620/8621 Email/set{destroy} - permanently delete messages by id.
	 *
	 * @param string[] $ids
	 * @param string|null $accountId defaults to the session's own accountId - MUST be given
	 *  explicitly for ids belonging to a mail:share-shared account, see Type::set()'s own docblock
	 * @throws Api\Exception
	 */
	public function emailDestroy(array $ids, ?string $accountId=null) : void
	{
		$response = $this->set(destroy: $ids, accountId: $accountId);
		if (($notDestroyed = $response['notDestroyed'] ?? []))
		{
			throw new Api\Exception('Email/set destroy failed: '.json_encode($notDestroyed));
		}
	}

	/**
	 * Patch keywords on one or more Emails via Email/set (RFC 8621 - a "keywords/$x": true|null
	 * PatchObject leaves every other keyword untouched, unlike a full keywords replace).
	 *
	 * @param string[] $ids JMAP Email ids
	 * @param array<string,bool|null> $patch e.g. ['keywords/$seen' => true, 'keywords/$flagged' => null]
	 * @param string|null $accountId defaults to the session's own accountId - MUST be given
	 *  explicitly for ids belonging to a mail:share-shared account, see Type::set()'s own docblock
	 * @throws Api\Exception on any failure
	 */
	public function emailSetKeywords(array $ids, array $patch, ?string $accountId=null) : void
	{
		if (!$ids)
		{
			return;
		}
		$response = $this->set(update: array_fill_keys($ids, $patch), accountId: $accountId);
		if (($notUpdated = $response['notUpdated'] ?? []))
		{
			throw new Api\Exception('Email/set update failed: '.json_encode($notUpdated));
		}
	}

	/**
	 * Move one or more Emails to a different mailbox WITHIN THE SAME ACCOUNT - a full mailboxIds
	 * replace (RFC 8621: moving is "set mailboxIds to just the target", unlike a mailboxIds/<id>
	 * patch which adds without removing, see emailImport()'s sibling use of the patch form
	 * elsewhere).
	 *
	 * NOT for a cross-account move (eg. a mail:share-shared mailbox's message into the viewer's
	 * OWN Trash) - an Email's mailboxIds patch can only ever reference mailboxes within its own
	 * account (RFC 8620 §1.2's "Id" type is account-scoped), so a foreign-account $accountId here
	 * combined with $targetFolder resolved in THIS session's own account would merely fail (or, if
	 * the two accounts happen to share a colliding raw Mailbox id, mutate the wrong mailbox) -
	 * use emailCopy() + emailDestroy() instead, see their own docblocks.
	 *
	 * @param string[] $ids JMAP Email ids, all within $accountId
	 * @param string $targetFolder folder-path e.g. "INBOX/Trash" (resolved to a Mailbox id internally)
	 * @param string|null $accountId defaults to the session's own accountId - MUST be given
	 *  explicitly for ids belonging to a mail:share-shared account, see Type::set()'s own docblock.
	 *  $targetFolder is always resolved in the session's OWN account regardless (see above) - this
	 *  only overrides which account the source ids/mutation apply to.
	 * @throws Api\Exception folder not found, or on any failure
	 */
	public function emailMove(array $ids, string $targetFolder, ?string $accountId=null) : void
	{
		if (!$ids)
		{
			return;
		}
		$targetMailboxId = $this->jmap->mailbox->getMailboxId($targetFolder);
		if ($targetMailboxId === null)
		{
			throw new Api\Exception("Folder '$targetFolder' not found");
		}
		$response = $this->set(update: array_fill_keys($ids, ['mailboxIds' => [$targetMailboxId => true]]), accountId: $accountId);
		if (($notUpdated = $response['notUpdated'] ?? []))
		{
			throw new Api\Exception('Email/set move failed: '.json_encode($notUpdated));
		}
	}

	/**
	 * Copy one or more Emails from a DIFFERENT JMAP account (eg. a mail:share-shared mailbox) into
	 * THIS session's own account, via RFC 8620 §5.4 Email/copy - the only JMAP mechanism for a
	 * cross-account transfer (see emailMove()'s own docblock for why a plain mailboxIds patch
	 * can't do this). PHP counterpart of MailJmap.copyEmailCrossAccount() (mail/js/jmap.ts), used
	 * by Api\Mail::jmapDeleteMessages() for "move a shared mailbox's message to MY OWN Trash".
	 *
	 * @param string $fromAccountId source account (the shared mailbox's real owner)
	 * @param string[] $ids source Email ids - all must live in $fromAccountId
	 * @param string $targetMailboxId destination Mailbox id, already resolved in THIS session's
	 *  own account (eg. via Mailbox::getMailboxId())
	 * @param array<string,array<string,bool>> $keywordsById {sourceEmailId: keywords}, from
	 *  emailKeywords(), to preserve on the copy - RFC 8620's Email/copy create object follows
	 *  normal Email/set create semantics, where mailboxIds is the only property the RFC requires,
	 *  but omitting keywords here would silently reset every flag (read/flagged/labels/...) on the
	 *  new copy, which is not what a user-facing move/copy action should do.
	 * @return array<string,string> new id, keyed by SOURCE id, for every id that copied successfully
	 * @throws Api\Exception if any id failed to copy - callers should treat this as all-or-nothing,
	 *  same as emailSetKeywords()/emailMove() already do
	 */
	public function emailCopy(string $fromAccountId, array $ids, string $targetMailboxId, array $keywordsById=[]) : array
	{
		if (!$ids)
		{
			return [];
		}
		$create = [];
		foreach ($ids as $id)
		{
			$create[$id] = [
				'id' => $id,
				'mailboxIds' => [$targetMailboxId => true],
				'keywords' => $keywordsById[$id] ?? new \stdClass(),
			];
		}
		$response = $this->jmap->call('Email/copy', [
			'fromAccountId' => $fromAccountId,
			'accountId' => $this->jmap->accountId,
			'create' => $create,
		]);
		$created = [];
		foreach ($response['created'] ?? [] as $sourceId => $obj)
		{
			$created[$sourceId] = $obj['id'];
		}
		$notCreated = $response['notCreated'] ?? [];
		// Stalwart (and RFC 8621) refuses to copy a message which already exists in the destination account
		// (same blob, in ANY folder of it) with "alreadyExists" + the id of the existing copy. A move is copy
		// + destroy-the-original, so ignoring that error would lose the message from where the user wants it,
		// and failing it means the move/delete never works. The existing copy is therefore ADDED to the target
		// mailbox (it may live in another folder, eg. the user copied the message to a project folder earlier
		// and now deletes it, which moves it to the Trash too) before the caller destroys the original.
		foreach ($notCreated as $sourceId => $error)
		{
			if (($error['type'] ?? null) === 'alreadyExists' && !empty($error['existingId']))
			{
				$this->emailEnsureInMailbox($error['existingId'], $targetMailboxId);
				$created[$sourceId] = $error['existingId'];
				unset($notCreated[$sourceId]);
			}
		}
		if ($notCreated)
		{
			throw new Api\Exception('Email/copy failed: '.json_encode($notCreated));
		}
		return $created;
	}

	/**
	 * Make sure an Email of THIS session's own account is (also) in the given mailbox, keeping every
	 * mailbox and keyword it already has - emailCopy()'s answer to "alreadyExists".
	 *
	 * @param string $id Email id in this session's own account
	 * @param string $mailboxId Mailbox id in this session's own account
	 * @throws Api\Exception if the Email is gone or could not be added to the mailbox - the caller must then NOT
	 *  destroy the original
	 */
	protected function emailEnsureInMailbox(string $id, string $mailboxId) : void
	{
		$email = $this->emailGet($id, ['mailboxIds'], false);
		if (!empty($email['mailboxIds'][$mailboxId]))
		{
			return;	// the existing copy is already where the message is wanted
		}
		$response = $this->set(update: [$id => ['mailboxIds/'.$mailboxId => true]]);
		if (!array_key_exists($id, $response['updated'] ?? []) || ($response['notUpdated'] ?? []))
		{
			throw new Api\Exception('Email/set could not add the existing copy to the target mailbox: '.json_encode($response['notUpdated'] ?? []));
		}
	}

	/**
	 * Batch-fetch {Email.id: keywords} for emailCopy()'s own flag-preservation.
	 *
	 * @param string $accountId account the ids live in
	 * @param string[] $ids
	 * @return array<string,array<string,bool>> empty map for an empty $ids
	 */
	public function emailKeywords(string $accountId, array $ids) : array
	{
		if (!$ids)
		{
			return [];
		}
		$response = $this->jmap->call('Email/get', [
			'accountId' => $accountId,
			'ids' => $ids,
			'properties' => ['id', 'keywords'],
		]);
		$byId = [];
		foreach ($response['list'] ?? [] as $email)
		{
			$byId[$email['id']] = $email['keywords'] ?? [];
		}
		return $byId;
	}

	/**
	 * Query Mailbox and Email state for give folder
	 *
	 * @param string $folder
	 * @param string|null $accountId
	 * @param string|null &$sessionState
	 * @return string[] states for keys "Mailbox" and "Email"
	 * @throws Api\Exception
	 */
	public function getStates(string $folder='INBOX', ?string $accountId=null, ?string &$sessionState=null) : array
	{
		$response = $this->jmap->jmapCall([
			['Mailbox/query', ['accountId' => $accountId ?: $this->jmap->accountId, 'filter' => ['name' => $folder]], 't0'],
			['Email/get', ['accountId' => $accountId ?: $this->jmap->accountId, '#inMailbox' => ['name' => 'Mailbox/query', 'path' => '/ids', 'resultOf' => 't0'], 'ids' => []], 't1'],
		]);
		$sessionState = $response['sessionState'] ?? null;
		return [
			'Mailbox' => $response['methodResponses'][0][1]['queryState'] ?? throw new Api\Exception("Could not query Mailbox state using folder '$folder'!"),
			'Email' => $response['methodResponses'][1][1]['state'] ?? throw new Api\Exception("Could not query Email state of folder '$folder'!"),
		];
	}

	/**
	 * Query changes from a subscription push
	 *
	 * @link https://jmap.io/client.html#staying-in-sync
	 * @param ?string $accountId defaults to $this->jmap->accountId
	 * @param array $states state-object (e.g. "Email" or "Mailbox") => sinceState pairs
	 * @param string $mailbox
	 * @param string|null $sessionState
	 * @return array[] with responses for keys "(email|mailbox)-(changes|created|updated)" - no
	 *  "-destroyed" key (see the "destroyed" comments in this method's body for why); the plain
	 *  destroyed-id list is in "(email|mailbox)-changes"' own "destroyed" property instead
	 */
	public function getChanges(?string $accountId, array $states, string $mailbox='INBOX', ?string &$sessionState=null)
	{
		static $mailboxIds = ['inbox' => 'a'];
		if (strtolower($mailbox) === 'inbox')
		{
			$mailbox = 'inbox';
		}
		elseif (!isset($mailboxIds[$mailbox]))
		{
			$mailboxIds[$mailbox] = $this->jmap->mailbox->getMailboxId($mailbox, $accountId);
		}

		$methodCalls = !isset($states['Mailbox']) ? [] : [
			// Fetch a list of mailbox ids that have changed
			["Mailbox/changes", [
				"accountId" => $accountId ?: $this->jmap->accountId,
				"sinceState" => $states['Mailbox'],
			], "mailbox-changes"],
			// Fetch any mailboxes that have been created
			["Mailbox/get", [
				"accountId" => $accountId ?: $this->jmap->accountId,
				"#ids" => [
					"name" => "Mailbox/changes",
					"path" => "/created",
					"resultOf" => "mailbox-changes"
				]
			], "mailbox-created"],
			// Fetch any mailboxes that have been updated
			["Mailbox/get", [
				"accountId" => $accountId ?: $this->jmap->accountId,
				"#ids" => [
					"name" => "Mailbox/changes",
					"path" => "/updated",
					"resultOf" => "mailbox-changes"
				],
				"#properties" => [
					"name" => "Mailbox/changes",
					"path" => "/updatedProperties",
					"resultOf" => "mailbox-changes"
				]
			], "mailbox-updated"],
			// Deliberately no "mailbox-destroyed" Mailbox/get call: a destroyed mailbox can never
			// be fetched (always resolves to notFound, never list - JMAP semantics), so it could
			// only ever return an empty list even when it works. The plain destroyed-id list is
			// already in "mailbox-changes" itself (its own "destroyed" property).
		];
		if (isset($states['Email']))
		{
			$methodCalls = array_merge($methodCalls, [
				// Fetch a list of created/updated/deleted Emails
				["Email/changes", [
					"accountId" => $accountId ?: $this->jmap->accountId,
					"sinceState" => $states['Email'],
					"maxChanges" => 30
				], "email-changes"],
				["Email/get", [
					"accountId" => $accountId ?: $this->jmap->accountId,
					"#ids" => [
						"name" => "Email/changes",
						"path" => "/created",
						"resultOf" => "email-changes"
					],
					"properties" => ["id", "mailboxIds", "from", "subject", "preview", "messageId"],
				], "email-created"],
				["Email/get", [
					"accountId" => $accountId ?: $this->jmap->accountId,
					"#ids" => [
						"name" => "Email/changes",
						"path" => "/updated",
						"resultOf" => "email-changes"
					],
					"properties" => ["id", "mailboxIds", "messageId", "keywords"],
				], "email-updated"],
				// Deliberately no "email-destroyed" Email/get call - same reasoning as
				// "mailbox-destroyed" above.
			]);
		}
		$response = $this->jmap->jmapCall($methodCalls);
		$sessionState = $response['sessionState'] ?? null;
		$ret = [];
		foreach($response['methodResponses'] as $methodResponse)
		{
			$ret[$methodResponse[2]] = $methodResponse[1];
		}
		return $ret;
	}
}
