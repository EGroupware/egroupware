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

namespace EGroupware\Api\Exception;

/**
 * Base class for all exceptions about missing permissions
 *
 * New NoPermisison excpetion has to extend deprecated egw_exception_no_permission
 * to allow legacy code to catch them!
 */
class NoPermission extends \egw_exception_no_permission
{
	/**
	 * Constructor
	 *
	 * @param string $msg =null message, default "Permission denied!"
	 * @param int $code =100 numerical code, default 100
	 */
	function __construct($msg=null,$code=100)
	{
		if (is_null($msg)) $msg = lang('Permisson denied!');

		parent::__construct($msg,$code);
	}
}
