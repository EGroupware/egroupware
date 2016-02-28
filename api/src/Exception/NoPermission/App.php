<?php
/**
 * EGroupware API - No Permission Exceptions
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage exception
 * @access public
 * @version $Id$
 */

namespace EGroupware\Api\Exception\NoPermission;

use EGroupware\Api\Exception;

/**
 * User lacks the right to run an application
 *
 */
class App extends Exception\NoPermission
{
	function __construct($msg=null,$code=101)
	{
		if (isset($GLOBALS['egw_info']['apps'][$msg]))
		{
			if ($msg == 'admin')
			{
				$msg = lang('You need to be an eGroupWare administrator to access this functionality!');
			}
			else
			{
				$currentapp = $GLOBALS['egw_info']['flags']['currentapp'];
				$app = isset($GLOBALS['egw_info']['apps'][$currentapp]['title']) ?
					$GLOBALS['egw_info']['apps'][$currentapp]['title'] : $msg;

				$msg = lang('You\'ve tried to open the eGroupWare application: %1, but you have no permission to access this application.',
						'"'.$app.'"');
			}
		}
		parent::__construct($msg,$code);
	}
}
