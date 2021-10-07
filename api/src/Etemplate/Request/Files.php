<?php
/**
 * EGroupWare - eTemplate request object storing the data in the filesystem
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright (c) 2009-16 by Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Request;

use EGroupware\Api\Etemplate;

/**
 * Class to represent the persitent information stored on the server for each eTemplate request
 *
 * The information is stored in the filesystem. The admin has to take care of regulary cleaning of
 * the used directory, as old requests get NOT deleted by this handler.
 *
 * To enable the use of this handler, you have to set (in etemplate/inc/class.etemplate_request.inc.php):
 *
 * 		Api\Etemplate\Request::$request_class = 'etemplate_request_files';
 *
 * The request object should be instancated only via the factory method Api\Etemplate\Request::read($id=null)
 *
 * $request = Api\Etemplate\Request::read();
 *
 * // add request data
 *
 * $id = $request->id();
 *
 * b) open or modify an existing request:
 *
 * if (!($request = Api\Etemplate\Request::read($id)))
 * {
 * 		// request not found
 * }
 *
 * Ajax requests can use this object to open the original request by using the id, they have to transmitt back,
 * and register further variables, modify the registered ones or delete them AND then update the id, if it changed:
 *
 *	if (($new_id = $request->id()) != $id)
 *	{
 *		$response->addAssign('etemplate_exec_id','value',$new_id);
 *	}
 *
 * For an example look in link_widget::ajax_search()
 */
class Files extends Etemplate\Request
{
	/**
	 * request id
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Name of the directory to store the request data, by default $GLOBALS['egw_info']['server']['temp_dir']
	 *
	 * @var string
	 */
	static public $directory;

	/**
	 * Private constructor to force the instancation of this class only via it's static factory method read
	 *
	 * @param string|null $id =null
	 */
	private function __construct($id=null)
	{
		if (is_null(self::$directory))
		{
			self::$directory = $GLOBALS['egw_info']['server']['temp_dir'];
		}
		if (!$id) $id = self::request_id();

		$this->id = $id;
	}

	/**
	 * return the id of this request
	 *
	 * @return string
	 */
	public function &id()
	{
		//error_log(__METHOD__."() id=$this->id");
		return $this->id;
	}

	/**
	 * Factory method to get a new request object or the one for an existing request
	 *
	 * @param string $id =null
	 * @param bool $handle_not_found =true true: handle not found by trying to redirect, false: just return null
	 * @return Request|null null if Request not found and $handle_not_found === false
	 */
	public static function read($id=null, $handle_not_found=true)
	{
		$request = new Files($id);

		if (!is_null($id))
		{
			if (!file_exists($filename = self::$directory.'/'.$id) || !is_readable($filename))
			{
				error_log("Error opening '$filename' to read the etemplate request data!");
				return false;
			}
			$request->data = unserialize(file_get_contents($filename));
			if ($request->data === false) error_log("Error unserializing '$filename' to read the etemplate request data!");
		}
		//error_log(__METHOD__."(id=$id");
		return $request;
	}

	/**
	 * creates a new request-id via microtime()
	 *
	 * @return string
	 */
	static function request_id()
	{
		do
		{
			$id = parent::request_id();
		}
		while (file_exists(self::$directory.'/'.$id));

		return $id;
	}

	/**
	 * saves content,readonlys,template-keys, ... via eGW's appsession function
	 *
	 * As a user may open several windows with the same content/template wie generate a location-id from microtime
	 * which is used as location for request to descriminate between the different windows. This location-id
	 * is then saved as a hidden-var in the form. The above mentions session-id has nothing to do / is different
	 * from the session-id which is constant for all windows opened in one session.
	 */
	function __destruct()
	{
		if ($this->remove_if_not_modified && !$this->data_modified)
		{
			//error_log(__METHOD__."() destroying $this->id");
			@unlink(self::$directory.'/'.$this->id);
		}
		elseif (!$this->destroyed && $this->data_modified)
		{
			$this->cleanup();

			if (!file_put_contents($filename = self::$directory.'/'.$this->id,serialize($this->data)))
			{
				error_log("Error opening '$filename' to store the etemplate request data!");
			}
		}
	}
}