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
 * User lacks a record level permission, eg. he's not the owner and has no grant from the owner
 *
 */
class Record extends Exception\NoPermission { }
