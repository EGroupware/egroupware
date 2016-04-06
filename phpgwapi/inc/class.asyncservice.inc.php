<?php
/**
 * EGroupware API - Timed Asynchron Services
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright Ralf Becker <RalfBecker-AT-outdoor-training.de>
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @access public
 * @version $Id$
 */

use EGroupware\Api;

/**
 * The class implements a general eGW service to execute callbacks at a given time.
 *
 * @deprecated use Api\AsyncService
 */
class asyncservice extends Api\Asyncservice {}
