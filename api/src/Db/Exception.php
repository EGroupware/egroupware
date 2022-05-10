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

namespace EGroupware\Api\Db;

/**
 * Exception thrown by the Api\Db class for everything not covered by extended classed below
 *
 * New Db\Exception has to extend deprecated egw_exception_db to allow legacy code
 * to catch exceptions thrown by Api\Db class!
 */
class Exception extends \egw_exception_db
{
	/**
	 * Constructor
	 *
	 * @param string $msg =null message, default "Database error!"
	 * @param int $code =100
	 */
	function __construct($msg=null, $code=100, \Exception $previous=null)
	{
		if (is_null($msg)) $msg = lang('Database error!');

		parent::__construct($msg, $code, $previous);
	}
}