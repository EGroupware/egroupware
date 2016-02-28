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

/**
 * Classic invalid SQL error
 *
 * New InvalidSql exception has to extend deprecated egw_exception_db_invalid_sql
 * to allow legacy code to catch exceptions thrown by Api\Db!
 */
class InvalidSql extends \egw_exception_db_invalid_sql {}
