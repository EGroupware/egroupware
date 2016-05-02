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
 * Classic invalid SQL error
 *
 * New InvalidSql exception has to extend deprecated egw_exception_db_invalid_sql
 * to allow legacy code to catch exceptions thrown by Api\Db!
 *
 * @deprecated use Api\Db\Exception\InvalidSql
 */
class egw_exception_db_invalid_sql extends Api\Db\Exception {}
