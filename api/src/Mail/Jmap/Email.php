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
	 * @return array the Email object
	 * @throws Api\Exception if not found
	 */
	public function emailGet(string $id, array $properties, bool $fetchAllBodyValues=true) : array
	{
		$args = [
			'accountId' => $this->jmap->accountId,
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
	 * @return string new Email.id
	 * @throws Api\Exception
	 */
	public function emailImport(string $blobId, string $folder, array $keywords=[]) : string
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
	 * @throws Api\Exception
	 */
	public function emailDestroy(array $ids) : void
	{
		$response = $this->set(destroy: $ids);
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
	 * @throws Api\Exception on any failure
	 */
	public function emailSetKeywords(array $ids, array $patch) : void
	{
		if (!$ids)
		{
			return;
		}
		$response = $this->set(update: array_fill_keys($ids, $patch));
		if (($notUpdated = $response['notUpdated'] ?? []))
		{
			throw new Api\Exception('Email/set update failed: '.json_encode($notUpdated));
		}
	}

	/**
	 * Move one or more Emails to a different mailbox - a full mailboxIds replace (RFC 8621:
	 * moving is "set mailboxIds to just the target", unlike a mailboxIds/<id> patch which adds
	 * without removing, see emailImport()'s sibling use of the patch form elsewhere).
	 *
	 * @param string[] $ids JMAP Email ids
	 * @param string $targetFolder folder-path e.g. "INBOX/Trash" (resolved to a Mailbox id internally)
	 * @throws Api\Exception folder not found, or on any failure
	 */
	public function emailMove(array $ids, string $targetFolder) : void
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
		$response = $this->set(update: array_fill_keys($ids, ['mailboxIds' => [$targetMailboxId => true]]));
		if (($notUpdated = $response['notUpdated'] ?? []))
		{
			throw new Api\Exception('Email/set move failed: '.json_encode($notUpdated));
		}
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
