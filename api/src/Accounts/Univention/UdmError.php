<?php
/**
 * EGroupware support for Univention UDM REST Api
 *
 * @link https://www.egroupware.org
 * @author Ralf Becker <rb@egroupware.org>
 *
 * @link https://www.univention.com/blog-en/2019/07/udm-rest-api-beta-version-released/
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage accounts
 */

namespace EGroupware\Api\Accounts\Univention;

/**
 * UDM Rest API returned a JSON error object
 */
class UdmError extends UdmException
{
	public function __construct($msg = null, $code = 100, \Exception $previous = null)
	{
		parent::__construct($msg, $code, $previous);
	}
}