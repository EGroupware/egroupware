<?php
/**
 * EGroupware API - Db Exceptions
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage db
 * @access public
 */

namespace EGroupware\Api\Db\Exception;

/**
 * A caller passed an argument Api\Db can not safely use, eg. a raw (not Api\Db\SqlFragment
 * wrapped) value at an integer array key, while Api\Db::requireSqlFragmentObjects() is active
 * for that instance.
 *
 * Deliberately does NOT extend Api\Db\Exception / \egw_exception_db: this is a programming error
 * to be found and fixed, not a database-operation failure existing code should catch and handle -
 * extending the Db exception hierarchy would let a generic "catch (Db\Exception)" around a query
 * silently swallow it, masking the very bug this check exists to surface.
 */
class InvalidArgument extends \InvalidArgumentException { }
