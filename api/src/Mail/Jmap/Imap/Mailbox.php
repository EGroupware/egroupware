<?php
/**
 * EGroupware Api: JMAP Mailbox type, plain-IMAP-shim implementation
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail\Jmap\Imap;

use EGroupware\Api\Mail\Jmap\Mailbox as Base;
use EGroupware\Api\Mail\Jmap\Imap;

/**
 * RFC 8621 §2 Mailbox, real IMAP calls (via the owning Imap session's held connection) -
 * thin adapter translating Api\Jmap\Type's generic get/query/set contract into Imap's own
 * (unchanged) mailboxGet()/mailboxQuery()/mailboxSet() static methods.
 */
class Mailbox extends Base
{
	public function get(?array $ids=null, ?array $properties=null) : array
	{
		/** @var Imap $jmap */
		$jmap = $this->jmap;
		return Imap::mailboxGet($jmap->accountId, array_filter([
			'ids' => $ids,
		], static fn($v) => $v !== null), $jmap->calledFor);
	}

	public function query(array $filter=[], array $sort=[]) : array
	{
		/** @var Imap $jmap */
		$jmap = $this->jmap;
		return Imap::mailboxQuery($jmap->accountId, ['filter' => $filter]);
	}

	public function set(array $create=[], array $update=[], array $destroy=[]) : array
	{
		/** @var Imap $jmap */
		$jmap = $this->jmap;
		return Imap::mailboxSet($jmap->accountId, array_filter([
			'create' => $create ?: null,
			'update' => $update ?: null,
			'destroy' => $destroy ?: null,
		], static fn($v) => $v !== null), $jmap->calledFor);
	}

	/**
	 * A shim mailbox id IS just base64(folder-path) - see Imap::mailboxQuery()'s own docblock
	 * ("Id is just base64(EGroupware-canonical "/"-joined folder path) - a pure encoding, not a
	 * lookup") - so this never needs an IMAP round-trip, unlike the real-JMAP side's
	 * Mail\Jmap\Mailbox::getMailboxId(), which genuinely has to look a real Mailbox id up.
	 * $accountId kept in the signature only to match the parent contract - unused here, the
	 * owning session is already scoped to one account.
	 *
	 * @param string $folder folder-path
	 * @param string|null $accountId unused
	 * @return string|null null only for an empty path
	 */
	public function getMailboxId(string $folder, ?string $accountId=null) : ?string
	{
		return $folder === '' ? null : base64_encode($folder);
	}

	/**
	 * @param string $folderId base64-encoded folder path
	 * @return string
	 */
	public function folderId2path(string $folderId)
	{
		return Imap::folderPath($folderId);
	}
}
