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
	 * @param string $folder folder-path
	 * @param string|null $accountId
	 * @return string|null null = not found
	 */
	public function getMailboxId(string $folder, ?string $accountId=null) : ?string
	{
		$methodCalls = [];
		$key = 0;
		foreach(explode('/', $folder) as $part)
		{
			$query = [
				'accountId' => $accountId ?: $this->jmap->accountId,
				'filter' => ['name' => $part],
			];
			if ($key)
			{
				$query['#parentId'] = [
					'name' => 'Mailbox/query',
					'path' => '/ids',
					// the PRECEDING segment's own call id (RFC 8620 §3.7: resultOf must reference
					// an earlier method call) - not $key itself, which is this segment's own
					// about-to-be-assigned id (found 2026-09-09 while adding test coverage: every
					// multi-segment lookup was self-referencing, never actually resolving via a
					// real JMAP server)
					'resultOf' => (string)($key - 1),
				];
			}
			$methodCalls[] = ['Mailbox/query', $query, (string)$key++];
		}
		$response = $this->jmap->jmapCall($methodCalls);
		$lastMethodResponse = array_pop($response['methodResponses']);
		return $lastMethodResponse[1]['ids'][0] ?? null;
	}

	/**
	 * Convert a folderId to the full path e.g. INBOX/folder/subfolder
	 *
	 * @param string $folderId
	 * @return string
	 */
	public function folderId2path(string $folderId)
	{
		static $folderPaths = [];

		if (!isset($folderPaths[$folderId]))
		{
			$id = $folderId;
			$parts = [];
			while ($id)
			{
				$response = $this->jmap->jmapCall([
					['Mailbox/get', [
						'accountId' => $this->jmap->accountId,
						'ids' => [$folderId],
						'properties' => ['parentId', 'name'],
					], 'f0'],
					['Mailbox/get', [
						'accountId' => $this->jmap->accountId,
						'#ids' => [
							"name" => "Mailbox/get",
							"path" => "/parentId",
							"resultOf" => "f0"
						],
						'properties' => ['parentId', 'name'],
					], 'f1'],
					['Mailbox/get', [
						'accountId' => $this->jmap->accountId,
						'#ids' => [
							"name" => "Mailbox/get",
							"path" => "/parentId",
							"resultOf" => "f1"
						],
						'properties' => ['parentId', 'name'],
					], 'f2'],
					['Mailbox/get', [
						'accountId' => $this->jmap->accountId,
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
			$folderPaths[$folderId] = implode('/', array_reverse($parts));
		}
		return $folderPaths[$folderId] ?? null;
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
	 * @return array path => translated label
	 */
	public function listAllPaths(?string $accountId=null) : array
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
		$paths = [];
		foreach ($mailboxes as $mailbox)
		{
			$r = $resolve($mailbox);
			$paths[$r['path']] = $r['label'];
		}
		return $paths;
	}
}
