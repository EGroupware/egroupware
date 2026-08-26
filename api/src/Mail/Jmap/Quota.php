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
	 * @return array{list: array[], notFound: string[]} empty of both if the server does NOT
	 *  advertise the quota capability
	 */
	public function get(?array $ids=null, ?array $properties=null) : array
	{
		if (!in_array(Http::JMAP_QUOTA, $this->jmap->capabilities ?? []))
		{
			return ['list' => [], 'notFound' => []];
		}
		return parent::get($ids, $properties);
	}
}
