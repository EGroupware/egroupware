<?php
/**
 * EGroupware API - JsCalendar
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @package calendar
 * @copyright (c) 2023 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\CalDAV;

use Throwable;

/**
 * Error parsing JsContact format
 *
 * @link  * @link https://datatracker.ietf.org/doc/html/rfc8984
 */
class JsParseException extends \InvalidArgumentException
{
	public function __construct($message = "", $code = 422, Throwable $previous = null)
	{
		parent::__construct($message, $code ?: 422, $previous);
	}
}