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
 * User lacks a record level permission, eg. he's not the owner and has no grant from the owner
 *
 * @deprecated use Api\Exception\NoPermission\Record
 */
class egw_exception_no_permission_record extends Api\Exception\NoPermission\Record {}
