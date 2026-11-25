<?php
/**
 * EGroupware Api: JMAP Mailbox type, real-JMAP-over-HTTP implementation
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
 * RFC 8621 §2 Mailbox - get()/query()/set() use `Type`'s generic default (proxy to the owning
 * `Http` session's jmapCall()); folder-path <-> Mailbox-id resolution is this app's own concern,
 * not part of the generic contract, so it's added here as bespoke methods.
 */
class Mailbox extends Type
{
	const TYPE_NAME = 'Mailbox';

	/**
	 * Get id of a folder-path e.g. INBOX/folder/subfolder (id corresponds to subfolder in INBOX/folder!)
	 *
	 * RFC 8621 §2 Mailbox/query's "name" filter condition is a case-insensitive SUBSTRING match,
	 * NOT exact - eg. filtering for 'Fälle' also matches 'Sterbefälle'. A plain Mailbox/query +
	 * ids[0] therefore silently resolves to the WRONG mailbox whenever one folder's name is
	 * contained in a sibling's (Fälle/Sterbefälle, but also Sent/"Sent Messages",
	 * Draft/Drafts, ...), with no reliable way to narrow it further at the query/filter level: a
	 * parentId filter is no help either when the colliding names share the same parent (eg. both
	 * top-level, parentId=null - confirmed live, Nordenham/stesie01, 2026-11-25: opening "Fälle"
	 * showed "Sterbefälle"'s messages instead, "Sterbefälle"'s id happened to sort first in the
	 * substring-matched result). So each segment's Mailbox/query is only ever used here as a
	 * candidate net; Mailbox/get then fetches name+parentId for those candidates, and the actual
	 * segment is picked by exact (case-insensitive) name equality AND the correct parentId chain -
	 * the only check that can't be fooled by a substring collision, since JMAP mailbox names are
	 * case-insensitive unique per parent.
	 *
	 * @param string $folder folder-path
	 * @param string|null $accountId
	 * @return string|null null = not found
	 */
	public function getMailboxId(string $folder, ?string $accountId=null) : ?string
	{
		$accountId = $accountId ?: $this->jmap->accountId;
		$parts = explode('/', $folder);

		$methodCalls = [];
		foreach ($parts as $key => $part)
		{
			$queryId = 'q'.$key;
			$methodCalls[] = ['Mailbox/query', [
				'accountId' => $accountId,
				'filter' => ['name' => $part],
			], $queryId];
			$methodCalls[] = ['Mailbox/get', [
				'accountId' => $accountId,
				'#ids' => ['name' => 'Mailbox/query', 'path' => '/ids', 'resultOf' => $queryId],
				'properties' => ['id', 'name', 'parentId'],
			], 'g'.$key];
		}
		$response = $this->jmap->jmapCall($methodCalls);

		$candidatesByKey = [];
		foreach ($response['methodResponses'] as [$name, $args, $callId])
		{
			if ($name === 'Mailbox/get' && preg_match('/^g(\d+)$/', $callId, $matches))
			{
				$candidatesByKey[(int)$matches[1]] = $args['list'] ?? [];
			}
		}

		$parentId = null;
		foreach ($parts as $key => $part)
		{
			$match = null;
			foreach ($candidatesByKey[$key] ?? [] as $candidate)
			{
				if (mb_strtolower($candidate['name'], 'UTF-8') === mb_strtolower($part, 'UTF-8') &&
					($candidate['parentId'] ?? null) === $parentId)
				{
					$match = $candidate;
					break;
				}
			}
			if (!$match)
			{
				return null;
			}
			$parentId = $match['id'];
		}
		return $parentId;
	}

	/**
	 * Convert a folderId to the full path e.g. INBOX/folder/subfolder
	 *
	 * @param string $folderId
	 * @param string|null $accountId defaults to the session's own accountId - MUST be given
	 *  explicitly for a folderId belonging to a mail:share-shared account, as Mailbox ids are
	 *  namespaced per JMAP account on the server: looking one up under the wrong accountId
	 *  resolves nothing (empty path), not an error (found live 2026-11-05: push updates for a
	 *  shared account's folders silently got an empty/wrong folder name this way)
	 * @return string
	 */
	public function folderId2path(string $folderId, ?string $accountId=null)
	{
		static $folderPaths = [];

		$cacheKey = ($accountId ?: $this->jmap->accountId).':'.$folderId;
		if (!isset($folderPaths[$cacheKey]))
		{
			$id = $folderId;
			$parts = [];
			while ($id)
			{
				$response = $this->jmap->jmapCall([
					['Mailbox/get', [
						'accountId' => $accountId ?: $this->jmap->accountId,
						'ids' => [$folderId],
						'properties' => ['parentId', 'name'],
					], 'f0'],
					['Mailbox/get', [
						'accountId' => $accountId ?: $this->jmap->accountId,
						'#ids' => [
							"name" => "Mailbox/get",
							"path" => "/parentId",
							"resultOf" => "f0"
						],
						'properties' => ['parentId', 'name'],
					], 'f1'],
					['Mailbox/get', [
						'accountId' => $accountId ?: $this->jmap->accountId,
						'#ids' => [
							"name" => "Mailbox/get",
							"path" => "/parentId",
							"resultOf" => "f1"
						],
						'properties' => ['parentId', 'name'],
					], 'f2'],
					['Mailbox/get', [
						'accountId' => $accountId ?: $this->jmap->accountId,
						'#ids' => [
							"name" => "Mailbox/get",
							"path" => "/parentId",
							"resultOf" => "f2"
						],
						'properties' => ['parentId', 'name'],
					], 'f3'],
				]);
				foreach ($response['methodResponses'] as $methodResponse)
				{
					if ($methodResponse[1]['list'])
					{
						if (!$parts && strtolower($methodResponse[1]['list'][0]['name']) === 'inbox')
						{
							$parts[] = 'INBOX';
						}
						else
						{
							$parts[] = $methodResponse[1]['list'][0]['name'];
						}
						if (empty($methodResponse[1]['list'][0]['parentId']))
						{
							break;
						}
					}
				}
				$id = $methodResponse[1]['list'][0]['parentId'] ?? null;
			}
			$folderPaths[$cacheKey] = implode('/', array_reverse($parts));
		}
		return $folderPaths[$cacheKey] ?? null;
	}

	/**
	 * List every mailbox in this account as flat "path => translated label" pairs, eg.
	 * "INBOX/Trash" => "INBOX/Papierkorb" - one batched Mailbox/query (no filter, everything)
	 * + Mailbox/get call, mirroring mail/js/folderTree.ts's buildMailboxPaths() (same role ->
	 * lang() key map, same path-joining), for server-side folder search/enumeration where
	 * client-side JMAP isn't an option (mail_acl.inc.php - see Imap\Jmap::listMailboxPaths(),
	 * the thin per-account wrapper around this that real callers use).
	 *
	 * @param ?string $accountId defaults to the session's own accountId
	 * @param bool $subscribedOnly true: only mailboxes with isSubscribed=true (ancestors are
	 *  still resolved from the full, unfiltered set, so a subscribed child's path never breaks
	 *  just because an unsubscribed parent was filtered out)
	 * @return array path => translated label
	 */
	public function listAllPaths(?string $accountId=null, bool $subscribedOnly=false) : array
	{
		$paths = [];
		foreach ($this->listAll($accountId) as $path => $mailbox)
		{
			if ($subscribedOnly && empty($mailbox['isSubscribed']))
			{
				continue;
			}
			$paths[$path] = $mailbox['_label'];
		}
		return $paths;
	}

	/**
	 * List every mailbox in this account as flat "path => full Mailbox object" pairs - same
	 * path-resolution as listAllPaths(), but keeping every JMAP property (id, parentId, name,
	 * isSubscribed, totalEmails, unreadEmails, myRights, ...) instead of just the translated
	 * label, plus a synthetic '_label' entry (what listAllPaths() returns). Used by
	 * Imap\Jmap's listMailboxes()/openMailbox()/createMailbox()/renameMailbox()/deleteMailbox()
	 * overrides, which all need the real mailboxId behind a canonical IMAP-style path.
	 *
	 * @param ?string $accountId defaults to the session's own accountId
	 * @return array path => Mailbox object (+ '_label')
	 */
	public function listAll(?string $accountId=null) : array
	{
		static $roleLabelKeys = [
			'inbox' => 'INBOX', 'trash' => 'Trash', 'sent' => 'Sent', 'drafts' => 'Drafts',
			'junk' => 'Junk', 'templates' => 'Templates', 'outbox' => 'Outbox', 'archive' => 'Archive',
		];
		$accountId = $accountId ?: $this->jmap->accountId;
		$response = $this->jmap->jmapCall([
			['Mailbox/query', ['accountId' => $accountId], '0'],
			['Mailbox/get', [
				'accountId' => $accountId,
				'#ids' => ['name' => 'Mailbox/query', 'path' => '/ids', 'resultOf' => '0'],
			], '1'],
		]);
		$mailboxes = $response['methodResponses'][1][1]['list'] ?? [];
		$byId = [];
		foreach ($mailboxes as $mailbox)
		{
			$byId[$mailbox['id']] = $mailbox;
		}

		$resolved = [];
		$resolve = function(array $mailbox) use (&$resolve, &$resolved, $byId, $roleLabelKeys)
		{
			if (isset($resolved[$mailbox['id']]))
			{
				return $resolved[$mailbox['id']];
			}
			$parent = !empty($mailbox['parentId']) ? ($byId[$mailbox['parentId']] ?? null) : null;
			$parentResolved = $parent ? $resolve($parent) : null;
			$pathSegment = ($mailbox['role'] ?? null) === 'inbox' ? 'INBOX' : $mailbox['name'];
			$roleLabelKey = !empty($mailbox['role']) ? ($roleLabelKeys[$mailbox['role']] ?? null) : null;
			$labelSegment = $roleLabelKey ? lang($roleLabelKey) : $mailbox['name'];
			$result = [
				'path'  => $parentResolved ? $parentResolved['path'].'/'.$pathSegment : $pathSegment,
				'label' => $parentResolved ? $parentResolved['label'].'/'.$labelSegment : $labelSegment,
			];
			return $resolved[$mailbox['id']] = $result;
		};
		$byPath = [];
		foreach ($mailboxes as $mailbox)
		{
			$r = $resolve($mailbox);
			$byPath[$r['path']] = $mailbox + ['_label' => $r['label']];
		}
		return $byPath;
	}
}
