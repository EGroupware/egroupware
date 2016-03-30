<?php

 /*
 * Egroupware
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @link http://www.egroupware.org
 * @author Nathan Gray
 * @version $Id$
 */

use EGroupware\Api;

/**
 * User lacks the right to run an application
 *
 * @deprecated use Api\Exception\NoPermission\App
 */
class egw_exception_no_permission_app extends Api\Exception\NoPermission\App {}
