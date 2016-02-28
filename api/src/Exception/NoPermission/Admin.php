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

/**
 * User is no eGroupWare admin (no right to run the admin application)
 *
 */
class Admin extends App
{
	function __construct($msg=null,$code=102)
	{
		if (is_null($msg)) $msg = 'admin';

		parent::__construct($msg,$code);
	}
}
