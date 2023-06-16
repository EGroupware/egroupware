<?php
/**
 * EGroupware API - Authentication Required Exceptions
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage exception
 * @access public
 */

namespace EGroupware\Api\Exception\NoPermission;

use EGroupware\Api\Exception\NoPermission;

/**
 * User is not authenticated
 */
class AuthenticationRequired extends NoPermission
{
	function __construct($msg=null, $code=401)
	{
		parent::__construct($msg,$code);
	}
}