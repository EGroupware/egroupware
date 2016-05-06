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
 * Exception thrown by the Api\Db class for everything not covered by extended classed below
 *
 * New Db\Exception has to extend deprecated egw_exception_db to allow legacy code
 * to catch exceptions thrown by Api\Db class!
 *
 * @deprecated use Api\Db\Exception
 */
class egw_exception_db extends Api\Exception {}
