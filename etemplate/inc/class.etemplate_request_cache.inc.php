<?php
/**
 * eGroupWare - eTemplate request object storing the data in EGroupware instance cache
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @subpackage api
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright (c) 2014 by Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

/**
 * Class to represent the persitent information stored on the server for each eTemplate request
 *
 * The information is stored in EGroupware tree cache with a given fixed expiration time.
 * We use tree cache with install-id as part of key to not loose all requests, if admin
 * clears instance cache (Admin >> Clear cache and register hooks)!
 *
 * To enable the use of this handler, you have to set (in etemplate/inc/class.etemplate_request.inc.php):
 *
 * 		etemplate_request::$request_class = 'etemplate_request_cache';
 *
 * The request object should be instancated only via the factory method etemplate_request::read($id=null)
 *
 * $request = etemplate_request::read();
 *
 * // add request data
 *
 * $id = $request->id();
 *
 * b) open or modify an existing request:
 *
 * if (!($request = etemplate_request::read($id)))
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

class etemplate_request_cache extends etemplate_request
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
	public function id()
	{
		//error_log(__METHOD__."() id=$this->id");
		return $this->id;
	}

	/**
	 * Factory method to get a new request object or the one for an existing request
	 *
	 * @param string $id=null
	 * @return etemplate_request|boolean the object or false if $id is not found
	 */
	static function read($id=null)
	{
		$request = new etemplate_request_cache($id);

		if (!is_null($id))
		{
			//error_log(__METHOD__."() reading $id");
			if (!($request->data = egw_cache::getTree($GLOBALS['egw_info']['server']['install_id'].'_etemplate', $id)))
			{
				error_log("Error reading etemplate request data for id=$id!");
				return false;
			}
		}
		//error_log(__METHOD__."(id=$id) returning ".array2string($request));
		return $request;
	}

	/**
	 * creates a new unique request-id
	 *
	 * @return string
	 */
	static function request_id()
	{
		// As we replace spaces with + for those account ids which contain spaces, therefore we need to do the same for getting request id too.
		$userID = str_replace(' ', '+', rawurldecode($GLOBALS['egw_info']['user']['account_lid']));
		return uniqid($GLOBALS['egw_info']['flags']['currentapp'].'_'.$userID.'_',true);
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
			egw_cache::unsetTree($GLOBALS['egw_info']['server']['install_id'].'_etemplate', $this->id);
		}
		elseif (($this->data_modified ||
			// if half of expiration time is over, save it anyway, to restart expiration time
			isset($this->data['last_saved']) && (time()-$this->data['last_saved']) > self::EXPIRATION/2))
		{
			//error_log(__METHOD__."() saving $this->id".($this->data_modified?'':' data NOT modified, just keeping session alife'));
			$this->data['last_saved'] = time();
			if (!egw_cache::setTree($GLOBALS['egw_info']['server']['install_id'].'_etemplate', $this->id, $this->data, self::EXPIRATION))
			{
				error_log("Error storing etemplate request data for id=$this->id!");
			}
		}
	}
}