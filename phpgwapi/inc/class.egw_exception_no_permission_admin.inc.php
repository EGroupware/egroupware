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
 * User is no eGroupWare admin (no right to run the admin application)
 *
 * @deprecated use Api\Exception\NoPermission\Admin
 */
class egw_exception_no_permission_admin extends Api\Exception\NoPermission\Admin {}
