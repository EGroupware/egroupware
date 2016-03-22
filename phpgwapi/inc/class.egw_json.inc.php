<?php
/**
 * EGroupware API: JSON - Contains functions and classes for doing JSON requests.
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage ajax
 * @author Andreas Stoeckel <as@stylite.de>
 * @version $Id$
 */

use EGroupware\Api\Json;

/**
 * Class handling JSON requests to the server
 *
 * @deprecated use Api\Json\Request
 */
class egw_json_request extends Json\Request {}

/**
 * Class used to send ajax responses
 *
 * @deprecated use Api\Json\Response
 */
class egw_json_response extends Json\Response
{
	/**
	 * xAjax compatibility function
	 */
	public function printOutput()
	{
		// do nothing, as output is triggered by egw::__destruct()
	}
}
