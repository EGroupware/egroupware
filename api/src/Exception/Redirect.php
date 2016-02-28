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
 * Allow callbacks to request a redirect
 *
 * Can be caught be applications and is otherwise handled by global exception handler.
 */
class Redirect extends Api\Exception
{
	public $url;
	public $app;

	/**
	 * Constructor
	 *
	 * @param string $url
	 * @param string $app
	 * @param string $msg
	 * @param int $code
	 */
	function __construct($url,$app=null,$msg=null,$code=301)
	{
		$this->url = $url;
		$this->app = $app;

		parent::__construct($msg, $code);
	}
}
