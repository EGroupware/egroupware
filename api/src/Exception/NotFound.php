<?php
/**
 * EGroupware API - Exceptions
 *
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage exception
 * @access public
 * @version $Id$
 */

namespace EGroupware\Api\Exception;

use EGroupware\Api;

/**
 * A record or application entry was not found for the given id
 */
class NotFound extends Api\Exception
{
	/**
	 * Constructor
	 *
	 * @param string $msg =null message, default "Entry not found!"
	 * @param int $code =99 numerical code, default 2
	 */
	function __construct($msg=null,$code=2)
	{
		if (is_null($msg)) $msg = lang('Entry not found!');

		parent::__construct($msg,$code);
	}
}
