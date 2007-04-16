<?php
/**
 * eGroupWare - eTemplate request object
 *
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package etemplate
 * @link http://www.egroupware.org
 * @author Ralf Becker <RalfBecker@outdoor-training.de>
 * @copyright (c) 2007 by Ralf Becker <RalfBecker@outdoor-training.de>
 * @version $Id$
 */

/**
 * Class to represent the persitent information stored on the server for each eTemplate request
 * 
 * The information is stored in the users session
 * 
 * There are two ways to instanciate a request object:
 * 
 * a) a new request: 
 * 
 * $request = new etemplate_request(); $id = $request->id();
 * 
 * b) open or modify an existing request: 
 * 
 * if (!($request = etemplate_request::read($id)))
 * {
 * 		// request not found
 * }
 * 
 * Ajax request can use this object to open the original request by using the id, they have to transmitt back, 
 * and register further variables, modify the registered ones or delete them.
 */
class etemplate_request
{
	/**
	 * here is the request data stored
	 *
	 * @var array
	 */
	private $data=array();
	/**
	 * Flag if data has been modified and therefor need to be stored again in the session
	 *
	 * @var boolean
	 */
	private $data_modified=false;
	/**
	 * request id
	 *
	 * @var string
	 */
	private $id;
	
	/**
	 * Enter description here...
	 *
	 * @param array $id_data
	 * @return etemplate_request
	 */
	function __construct($id=null)
	{
		if (!$id) $id = $this->request_id();
		
		$this->id = $id;
	}
	
	/**
	 * return the id of this request
	 *
	 * @return string
	 */
	function id()
	{
		return $this->id;
	}
	
	/**
	 * Read the request via it's id, returns a request_object or false
	 *
	 * @param string $id
	 * @return etempalte_request/boolean the object or false if $id is not found
	 */
	static function read($id)
	{
		if (!($data = $GLOBALS['egw']->session->request($id,'etemplate')))
		{
			return false;	// request not found
		}
		$request = new etemplate_request($id);
		$request->data = $data;
		
		return $request;
	}
	
	/**
	 * Register a form-variable to be processed
	 *
	 * @param string $form_name form-name
	 * @param string $type etemplate type
	 * @param array $data=array() optional extra data
	 */
	public function set_to_process($form_name,$type,$data=array())
	{
		if (!$form_name || !$type) return;
		
		$data['type'] = $type;
		
		$this->data['to_process'][$form_name] = $data;
		$this->data_modified = true;
	}
	
	/**
	 * Unregister a form-variable to be no longer processed
	 *
	 * @param string $form_name form-name
	 */
	public function unset_to_process($form_name)
	{
		unset($this->data['to_process'][$form_name]);
		$this->data_modified = true;
	}
	
	/**
	 * return the data of a form-var to process or the whole array
	 *
	 * @param string $form_name=null
	 * @return array
	 */
	public function get_to_process($form_name=null)
	{
		return $form_name ? $this->data['to_process'][$form_name] : $this->data['to_process'];
	}
	
	/**
	 * check if something set for a given $form_name
	 *
	 * @param string $form_name
	 * @return boolean
	 */
	public function isset_to_process($form_name)
	{
		return isset($this->data['to_process'][$form_name]);
	}
	
	/**
	 * magic function to set all request-vars, used eg. as $request->method = 'app.class.method';
	 *
	 * @param string $var
	 * @param mixed $val
	 */
	function __set($var,$val)
	{
		if ($this->data[$var] !== $val)
		{
			$this->data[$var] = $val;
			$this->data_modified = true;
		}
	}
	
	/**
	 * magic function to access the request-vars, used eg. as $method = $request->method;
	 *
	 * @param string $var
	 * @return mixed
	 */
	function __get($var)
	{
		return $this->data[$var];
	}

	/**
	 * creates a new request-id via microtime()
	 * 
	 * @return string
	 */
	function _request_id()
	{
		list($msec,$sec) = explode(' ',microtime());
		$time = 100 * $sec + (int)(100 * $msec);	// gives precision of 1/100 sec
		$id = $GLOBALS['egw_info']['flags']['currentapp'] .':'. $time;

		return $id;
	}

	/**
	 * saves content,readonlys,template-keys, ... via eGW's app_session function
	 *
	 * As a user may open several windows with the same content/template wie generate a location-id from microtime
	 * which is used as location for request to descriminate between the different windows. This location-id
	 * is then saved as a hidden-var in the form. The above mentions session-id has nothing to do / is different
	 * from the session-id which is constant for all windows opened in one session.
	 */
	function __destruct()
	{
		if ($this->data_modified) $GLOBALS['egw']->session->app_session($this->id,'etemplate',$this->data);

		if (substr($GLOBALS['egw_info']['server']['sessions_type'],0,4) == 'php4' && !$this->garbage_collection_done)
		{
			$this->php4_request_garbage_collection();
		}
	}

	/**
	 * a little bit of garbage collection for php4 sessions (their size is limited by memory_limit)
	 *
	 * With constant eTemplate use it can grow quite big and lead to unusable sessions (php terminates
	 * before any output with "Allowed memory size of ... exhausted").
	 * We delete now sessions once used after 10min and sessions never or multiple used after 60min.
	 */
	private function _php4_request_garbage_collection()
	{
		// now we are on php4 sessions and do a bit of garbage collection
		$app_sessions =& $_SESSION[EGW_SESSION_VAR]['app_sessions']['etemplate'];
		$session_used =& $app_sessions['session_used'];
		
		if ($this->id)
		{
			//echo "session_used[$id_used]='".$session_used[$id_used]."'<br/>\n";
			++$session_used[$this->id];	// count the number of times a session got used
		}
		$this->garbage_collection_done = true;

		if (count($app_sessions) < 20) return;	// we dont need to care

		list($msec,$sec) = explode(' ',microtime());
		$now = 	100 * $sec + (int)(100 * $msec);	// gives precision of 1/100 sec

		foreach(array_keys($app_sessions) as $id)
		{
			list(,$time) = explode(':',$id);
			
			if (!$time) continue;	// other data, no session
			
			//echo ++$n.') '.$id.': '.(($now-$time)/100.0)."secs old, used=".$session_used[$id].", size=".strlen($app_sessions[$id])."<br>\n";

			if ($session_used[$id] == 1 && $time < $now - 10*6000 || // session used and older then 10min
				$time < $now - 60*6000)	// session not used and older then 1h
			{
				//echo "<p>boetemplate::php4_session_garbage_collection('$id_used'): unsetting session '$id' (now=$now)</p>\n";
				unset($app_sessions[$id]);
				unset($session_used[$id]);
			}
		}
	}
}