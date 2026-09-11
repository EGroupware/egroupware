<?php
/**
 * EGroupware Api: JMAP Quota type, real-JMAP-over-HTTP implementation
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail\Jmap;

use EGroupware\Api\Jmap\Type;

/**
 * RFC 9425 Quota - overrides get() to short-circuit to an empty result if the server doesn't
 * advertise the capability, instead of sending a request the server would reject - callers fall
 * back to a non-JMAP way of getting the quota in that case, matching the former
 * `Api\Mail\Jmap::getQuota()`'s "not supported" contract. Return shape stays the standard
 * `{list, notFound}` (same as every other Type::get()), for a consistent API across types -
 * callers read `['list']` themselves, same as for Mailbox/Email.
 */
class Quota extends Type
{
	const TYPE_NAME = 'Quota';

	/**
	 * @param string[]|null $ids null = all
	 * @param string[]|null $properties
	 * @param bool $fetchAllBodyValues ignored - Quota has no body values, kept only for
	 *  signature-compatibility with Type::get() (found live 2026-09-09: a PHP fatal
	 *  "Declaration must be compatible" broke this class' autoload entirely once Type::get()
	 *  gained this param, unrelated to this class' own logic)
	 * @param string|null $mailboxId ignored - Quota is never addressed by mailbox, kept only for
	 *  signature-compatibility with Type::get() (same class of bug found again live 2026-09-10,
	 *  this time for $mailboxId - see Type::get()'s own docblock)
	 * @return array{list: array[], notFound: string[]} empty of both if the server does NOT
	 *  advertise the quota capability
	 */
	public function get(?array $ids=null, ?array $properties=null, bool $fetchAllBodyValues=false, ?string $mailboxId=null) : array
	{
		if (!in_array(Http::JMAP_QUOTA, $this->jmap->capabilities ?? []))
		{
			return ['list' => [], 'notFound' => []];
		}
		return parent::get($ids, $properties, $fetchAllBodyValues, $mailboxId);
	}
}
