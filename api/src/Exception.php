<?php
/**
 * EGroupware API - Exceptions
 *
 * This file defines as set of Exceptions used in eGroupWare.
 *
 * Applications having the need for further exceptions should extends the from one defined in this file.
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage exception
 * @access public
 * @version $Id$
 */

namespace EGroupware\Api;

/**
 * EGroupware API - Exceptions
 *
 * All eGroupWare exceptions should extend this class, so we are able to e.g., add some logging later.
 *
 * The messages for most exceptions should be translated and ready to be displayed to the user.
 * The only exception to this is exceptions like Exception\AssertionFailed, Exception\WrongParameter
 * or Db\Exception, which are supposed to happen only during program development.
 */
class Exception extends \Exception
{
	/**
	 * @var string|null $detail further details about the exception, which will be logged by _egw_log_exception()
	 * @see _egw_log_exception()
	 */
	public $detail;

	function __construct($msg=null, $code=100, ?\Exception $previous=null, ?string $detail=null)
	{
		parent::__construct($msg, $code, $previous);

		$this->detail = $detail;
	}
}