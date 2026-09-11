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
}
