<?php
/**
 * eGroupWare - eTemplate request object storing the data in EGroupware instance cache
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright (c) 2014-16 by Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Request;

use EGroupware\Api\Etemplate;
use EGroupware\Api;

/**
 * Class to represent the persitent information stored on the server for each eTemplate request
 *
 * The information is stored in EGroupware tree cache with a given fixed expiration time.
 * We use tree cache with install-id as part of key to not loose all requests, if admin
 * clears instance cache (Admin >> Clear cache and register hooks)!
 *
 * To enable the use of this handler, you have to set (in etemplate/inc/class.etemplate_request.inc.php):
 *
 * 		Api\Etemplate\Request::$request_class = 'etemplate_request_cache';
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
 * Ajax requests can use this object to open the original request by using the id, they have to transmit back,
 * and register further variables, modify the registered ones or delete them AND then update the id, if it changed:
 *
 *	if (($new_id = $request->id()) != $id)
 *	{
 *		$response->addAssign('etemplate_exec_id','value',$new_id);
 *	}
 *
 * For an example look in link_widget::ajax_search()
 */

class Cache extends Etemplate\Request
{
	/**
	 * Expiration time of 4 hours
	 */
	const EXPIRATION = 14400;

	/**
	 * request id
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Private constructor to force the instancation of this class only via it's static factory method read
	 *
	 * @param string $_id
	 */
	private function __construct($_id=null)
	{
		$this->id = $_id ? $_id : self::request_id();
		//error_log(__METHOD__."($_id) this->id=$this->id");
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
	 * @return ?Etemplate\Request|false null if Request not found and $handle_not_found === false
	 */
	public static function read($id=null, $handle_not_found=true)
	{
		$request = new Cache($id);

		if (!is_null($id))
		{
			//error_log(__METHOD__."() reading $id");
			if (!($request->data = Api\Cache::getTree($GLOBALS['egw_info']['server']['install_id'].'_etemplate', $id)))
			{
				error_log("Error reading etemplate request data for id=$id!");
				return false;
			}
		}
		//error_log(__METHOD__."(id=$id) returning ".array2string($request));
		return $request;
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
		if ($this->remove_if_not_modified && !$this->data_modified && isset($this->data['last_saved']))
		{
			//error_log(__METHOD__."() destroying $this->id");
			Api\Cache::unsetTree($GLOBALS['egw_info']['server']['install_id'].'_etemplate', $this->id);
		}
		elseif (($this->data_modified ||
			// if half of expiration time is over, save it anyway, to restart expiration time
			isset($this->data['last_saved']) && (time()-$this->data['last_saved']) > self::EXPIRATION/2))
		{
			//error_log(__METHOD__."() saving $this->id".($this->data_modified?'':' data NOT modified, just keeping session alife'));
			$this->cleanup();
			$this->data['last_saved'] = time();
			if (!Api\Cache::setTree($GLOBALS['egw_info']['server']['install_id'].'_etemplate', $this->id, $this->data,
				// use bigger one of our own self::EXPIRATION=4h and session lifetime (session.gc_maxlifetime) as expiration time
				max(self::EXPIRATION, ini_get('session.gc_maxlifetime'))))
			{
				error_log("Error storing etemplate request data for id=$this->id!");
			}
		}
	}
}