<?php
/**
 * EGroupware Api: JMAP Thread type, plain-IMAP-shim implementation
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail\Jmap\Imap;

use EGroupware\Api\Mail\Jmap\Thread as Base;
use EGroupware\Api\Mail\Jmap\Imap;

/**
 * RFC 8621 §3 Thread, real IMAP calls (via the owning Imap session's held connection - IMAP
 * THREAD command). Shares the owning session's $context with Imap\Email, so a thread id can
 * fall back to a preceding Email/query's remembered mailbox (see Imap::threadGet()'s docblock).
 */
class Thread extends Base
{
	public function get(?array $ids=null, ?array $properties=null) : array
	{
		/** @var Imap $jmap */
		$jmap = $this->jmap;
		return Imap::threadGet($jmap->accountId, array_filter([
			'ids' => $ids,
		], static fn($v) => $v !== null), $jmap->context);
	}
}
