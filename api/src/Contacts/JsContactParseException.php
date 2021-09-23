<?php
/**
 * EGroupware API - JsContact
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @package addressbook
 * @copyright (c) 2021 by Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

namespace EGroupware\Api\Contacts;

use Throwable;

/**
 * Error parsing JsContact format
 *
 * @link https://datatracker.ietf.org/doc/html/draft-ietf-jmap-jscontact-07
 */
class JsContactParseException extends \InvalidArgumentException
{
	public function __construct($message = "", $code = 422, Throwable $previous = null)
	{
		parent::__construct($message, $code ?: 422, $previous);
	}
}