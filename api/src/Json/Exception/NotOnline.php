<?php
/**
 * EGroupware API: push JSON commands to client
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage json
 * @author Ralf Becker <rb@stylite.de>
 * @version $Id$
 */

namespace EGroupware\Api\Json\Exception;

use EGroupware\Api\Json;

/**
 * Exception thrown, if message can not be pushed
 */
class NotOnline extends Json\Exception
{

}