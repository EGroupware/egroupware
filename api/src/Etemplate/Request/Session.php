<?php
/**
 * EGroupware - eTemplate request object storing the data in the session
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright (c) 2007-16 by Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api\Etemplate\Request;

use EGroupware\Api\Etemplate;
use EGroupware\Api;

/**
 * Class to represent the persitent information stored on the server for each eTemplate request
 *
 * The information is stored in the users session, which causes the session to constantly grow.
 * We implement here some garbadge collection to remove old requests.
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
class Session extends Etemplate\Request
{
	/**
	 * request id
	 *
	 * @var string
	 */
	protected $id;

	/**
	 * Private constructor to force the instancation of this class only via it's static factory method read
	 *
	 * @param string|null $id =null
	 */
	private function __construct($id=null)
	{
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
		$request = new Session($id);

		if (!is_null($id))
		{
			if (!($data = Api\Cache::getSession('etemplate', $id)))
			{
				return false;	// request not found
			}
			$request->data = $data;
		}
		//error_log(__METHOD__."(id=$id");
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
		if ($this->remove_if_not_modified && !$this->data_modified)
		{
			//error_log(__METHOD__."() destroying $this->id");
			Api\Cache::unsetSession('etemplate', $this->id);
		}
		elseif (!$this->destroyed && $this->data_modified)
		{
			$this->cleanup();

			Api\Cache::setSession('etemplate', $this->id, $this->data);
		}
		if (!$this->garbage_collection_done)
		{
			$this->_php4_request_garbage_collection();
		}
	}

	/**
	 * a little bit of garbage collection for php4 sessions (their size is limited by memory_limit)
	 *
	 * With constant eTemplate use it can grow quite big and lead to unusable sessions (php terminates
	 * before any output with "Allowed memory size of ... exhausted").
	 * We delete now sessions once used after 10min and sessions never or multiple used after 60min.
	 */
	protected function _php4_request_garbage_collection()
	{
		// now we are on php4 sessions and do a bit of garbage collection
		$appsessions =& $_SESSION[Api\Session::EGW_APPSESSION_VAR]['etemplate'];
		$session_used =& $appsessions['session_used'];

		if ($this->id)
		{
			//echo "session_used[$id_used]='".$session_used[$id_used]."'<br/>\n";
			++$session_used[$this->id];	// count the number of times a session got used
		}
		$this->garbage_collection_done = true;

		if (count($appsessions) < 20) return;	// we dont need to care

		$now = (int) (100 * microtime(true));	// gives precision of 1/100 sec

		foreach(array_keys($appsessions) as $id)
		{
			list(,$time) = explode(':',$id);

			if (!$time) continue;	// other data, no session

			//echo ++$n.') '.$id.': '.(($now-$time)/100.0)."secs old, used=".$session_used[$id].", size=".strlen($appsessions[$id])."<br>\n";

			if ($session_used[$id] == 1 && $time < $now - 10*6000 || // session used and older then 10min
				$time < $now - 30*6000)	// session not used and older then 30min
			{
				//echo "<p>boetemplate::php4_session_garbage_collection('$id_used'): unsetting session '$id' (now=$now)</p>\n";
				unset($appsessions[$id]);
				unset($session_used[$id]);
			}
		}
	}
}