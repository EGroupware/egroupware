<?php
/**
 * EGroupware API - old deprecated exceptions
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage accounts
 * @access public
 * @version $Id$
 */

use EGroupware\Api;

/**
 * Base class for all exceptions about missing permissions
 *
 * New NoPermisison excpetion has to extend deprecated egw_exception_no_permission
 * to allow legacy code to catch them!
 *
 * @deprecated use Api\Exception\NoPermission
 */
class egw_exception_no_permission extends Api\Exception {}
