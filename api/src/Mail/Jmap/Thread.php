<?php
/**
 * EGroupware Api: JMAP Thread type, real-JMAP-over-HTTP implementation
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail\Jmap;

use EGroupware\Api\Jmap\Type;

/**
 * RFC 8621 §3 Thread - `Type`'s generic get() default (there's no Thread/query or Thread/set in
 * the spec) is sufficient as-is, nothing mail-specific to add (yet).
 */
class Thread extends Type
{
	const TYPE_NAME = 'Thread';
}
