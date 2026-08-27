<?php
/**
 * EGroupware Api: JMAP Identity type, real-JMAP-over-HTTP implementation
 *
 * @link https://www.egroupware.org
 * @package api
 * @subpackage mail
 * @license https://opensource.org/license/gpl-2-0 GPL 2.0+ - GNU General Public License 2.0 or any higher version of your choice
 */

namespace EGroupware\Api\Mail\Jmap;

use EGroupware\Api\Jmap\Type;

/**
 * RFC 8621 §6 Identity - `Type`'s generic get()/set() defaults are sufficient as-is, nothing
 * mail-specific to add (yet). Part of the "submission" capability alongside EmailSubmission, see
 * Http::JMAP_SUBMISSION.
 */
class Identity extends Type
{
	const TYPE_NAME = 'Identity';
}
