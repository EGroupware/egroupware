<?php
/**
 * EGroupware API - Db Exceptions
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage db
 * @access public
 * @version $Id$
 */

namespace EGroupware\Api\Db\Exception;

use EGroupware\Api\Db;

/**
 * EGroupware not (fully) installed, visit setup
 */
class Setup extends Db\Exception { }
