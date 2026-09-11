<?php
/**
 * EGroupware Api: JMAP Quota type, plain-IMAP-shim implementation
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail\Jmap\Imap;

use EGroupware\Api\Mail\Jmap\Quota as Base;
use EGroupware\Api\Mail\Jmap\Imap;

/**
 * RFC 9425 Quota, via the classic IMAP QUOTA extension (Imap::quotaFromImap()) - unlike the
 * real-JMAP (Http) side, capability-gating happens inside Imap::quotaGet() itself
 * ($imap->hasCapability('QUOTA')), not via a server-advertised JMAP capability list, so no
 * extra gate is needed here.
 */
class Quota extends Base
{
	/**
	 * $fetchAllBodyValues/$mailboxId are unused - Quota has no body values and is never addressed
	 * by mailbox, kept only for signature-compatibility with Api\Mail\Jmap\Quota::get()/
	 * Api\Jmap\Type::get() (see the latter's own docblock for why this must never be dropped).
	 */
	public function get(?array $ids=null, ?array $properties=null, bool $fetchAllBodyValues=false, ?string $mailboxId=null) : array
	{
		/** @var Imap $jmap */
		$jmap = $this->jmap;
		return Imap::quotaGet($jmap->accountId, array_filter([
			'ids' => $ids,
		], static fn($v) => $v !== null));
	}
}
