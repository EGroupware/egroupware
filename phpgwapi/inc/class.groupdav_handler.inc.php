<?php
/**
 * EGroupware: GroupDAV access: abstract baseclass for groupdav/caldav/carddav handlers
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-9 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * EGroupware: GroupDAV access: abstract baseclass for groupdav/caldav/carddav handlers
 */
abstract class groupdav_handler
{
	/**
	 * Debug level: 0 = nothing, 1 = function calls, 2 = more info, eg. complete $_SERVER array
	 *
	 * The debug messages are send to the apache error_log
	 *
	 * @var integer
	 */
	var $debug = 0;

	/**
	 * eGW's charset
	 *
	 * @var string
	 */
	var $egw_charset;
	/**
	 * Reference to the accounts class
	 *
	 * @var accounts
	 */
	var $accounts;
	/**
	 * Translates method names into ACL bits
	 *
	 * @var array
	 */
	var $method2acl = array(
		'GET' => EGW_ACL_READ,
		'PUT' => EGW_ACL_EDIT,
		'DELETE' => EGW_ACL_DELETE,
	);
	/**
	 * eGW application responsible for the handler
	 *
	 * @var string
	 */
	var $app;
	/**
	 * Base url of handler, need to prefix all pathes not automatic handled by HTTP_WebDAV_Server
	 *
	 * @var string
	 */
	var $base_uri;
	/**
	 * principal URL
	 *
	 * @var string
	 */
	var $principalURL;
	/**
	 * HTTP_IF_MATCH / etag of current request / last call to _common_get_put_delete() method
	 *
	 * @var string
	 */
	var $http_if_match;
	/**
	 * Identified user agent
	 *
	 * @var string
	 */
	var $agent;

	/**
	 * Constructor
	 *
	 * @param string $app 'calendar', 'addressbook' or 'infolog'
	 * @param int $debug=null debug-level to set
	 * @param string $base_uri=null base url of handler
	 * @param string $principalURL=null pricipal url of handler
	 */
	function __construct($app,$debug=null,$base_uri=null,$principalURL=null)
	{
		$this->app = $app;
		if (!is_null($debug)) $this->debug = $debug;
		$this->base_uri = is_null($base_uri) ? $base_uri : $_SERVER['SCRIPT_NAME'];
		if (is_null($principalURL))
		{
			$this->principalURL = (@$_SERVER["HTTPS"] === "on" ? "https:" : "http:") .
				'//'.$_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '/';
		}
		else
		{
			$this->principalURL = $principalURL.'principals/users/'.
				$GLOBALS['egw_info']['user']['account_lid'].'/';
		}

		$this->agent = self::get_agent();

		$this->egw_charset = translation::charset();
		$this->accounts = $GLOBALS['egw']->accounts;
	}

	/**
	 * Handle propfind request for an application folder
	 *
	 * @param string $path
	 * @param array $options
	 * @param array &$files
	 * @param int $user account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	abstract function propfind($path,$options,&$files,$user);

	/**
	 * Propfind callback, if interator is used
	 *
	 * @param string $path
	 * @param array $filter
	 * @param array|boolean $start false=return all or array(start,num)
	 * @param int &$total
	 * @return array with "files" array with values for keys path and props
	 */
	function &propfind_callback($path, array $filter,$start,&$total) { }

	/**
	 * Handle get request for an applications entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user=null account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	abstract function get(&$options,$id,$user=null);

	/**
	 * Handle get request for an applications entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user=null account_id of owner, default null
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	abstract function put(&$options,$id,$user=null);

	/**
	 * Handle get request for an applications entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	abstract function delete(&$options,$id);

	/**
	 * Read an entry
	 *
	 * @param string/int $id
	 * @return array/boolean array with entry, false if no read rights, null if $id does not exist
	 */
	abstract function read($id);

	/**
	 * Check if user has the neccessary rights on an entry
	 *
	 * @param int $acl EGW_ACL_READ, EGW_ACL_EDIT or EGW_ACL_DELETE
	 * @param array/int $entry entry-array or id
	 * @return boolean null if entry does not exist, false if no access, true if access permitted
	 */
	abstract function check_access($acl,$entry);

	/**
	 * Add extra properties for collections
	 *
	 * @param array $props=array() regular props by the groupdav handler
	 * @param string $displayname
	 * @param string $base_uri=null base url of handler
	 * @return array
	 */
	static function extra_properties(array $props=array(), $displayname, $base_uri=null)
	{
		return $props;
	}

	/**
	 * Get the etag for an entry, can be reimplemented for other algorithm or field names
	 *
	 * @param array/int $event array with event or cal_id
	 * @return string/boolean string with etag or false
	 */
	function get_etag($entry)
	{
		if (!is_array($entry))
		{
			$entry = $this->read($entry);
		}
		if (!is_array($entry) || !isset($entry['id']) || !(isset($entry['modified']) || isset($entry['etag'])))
		{
		//	error_log(__METHOD__."(".array2string($entry).") Cant create etag!");
			return false;
		}
		return 'EGw-'.$entry['id'].':'.(isset($entry['etag']) ? $entry['etag'] : $entry['modified']).'-wGE';
	}

	/**
	 * Convert etag to the raw etag column value (without quotes, double colon and id)
	 *
	 * @param string $etag
	 * @return int
	 */
	static function etag2value($etag)
	{
		list(,$val) = explode(':',substr($etag,4,-4),2);

		return $val;
	}

	/**
	 * Handle common stuff for get, put and delete requests:
	 *  - application rights
	 *  - entry level acl, incl. edit and delete rights
	 *  - etag handling for precondition failed and not modified
	 *
	 * @param string $method GET, PUT, DELETE
	 * @param array &$options
	 * @param int $id
	 * @param boolean &$return_no_access=false if set to true on call, instead of '403 Forbidden' the entry is returned and $return_no_access===false
	 * @return array|string entry on success, string with http-error-code on failure, null for PUT on an unknown id
	 */
	function _common_get_put_delete($method,&$options,$id,&$return_no_access=false)
	{
		if ($this->app != 'principals' && !$GLOBALS['egw_info']['user']['apps'][$this->app])
		{
			if ($this->debug) error_log(__METHOD__."($method,,$id) 403 Forbidden: no app rights for '$this->app'");
			return '403 Forbidden';		// no app rights
		}
		$extra_acl = $this->method2acl[$method];
		if (!($entry = $this->read($id)) && ($method != 'PUT' || $entry === false) ||
			($extra_acl != EGW_ACL_READ && $this->check_access($extra_acl,$entry) === false))
		{
			if ($return_no_access && !is_null($entry))
			{
				if ($this->debug) error_log(__METHOD__."($method,,$id,$return_no_access) \$entry=".array2string($entry).", \$return_no_access set to false");
				$return_no_access = false;
			}
			else
			{
				if ($this->debug) error_log(__METHOD__."($method,,$id) 403 Forbidden/404 Not Found: read($id)==".($entry===false?'false':'null'));
				return !is_null($entry) ? '403 Forbidden' : '404 Not Found';
			}
		}
		if ($entry)
		{
			$etag = $this->get_etag($entry);
			// If the clients sends an "If-Match" header ($_SERVER['HTTP_IF_MATCH']) we check with the current etag
			// of the calendar --> on failure we return 412 Precondition failed, to not overwrite the modifications
			if (isset($_SERVER['HTTP_IF_MATCH']))
			{
				if (strstr($_SERVER['HTTP_IF_MATCH'], $etag) === false)
				{
					$this->http_if_match = $_SERVER['HTTP_IF_MATCH'];
					if ($this->debug) error_log(__METHOD__."($method,,$id) HTTP_IF_MATCH='$_SERVER[HTTP_IF_MATCH]', etag='$etag': 412 Precondition failed");
					return '412 Precondition Failed';
				}
				else
				{
					$this->http_if_match = $etag;
					// if an IF_NONE_MATCH is given, check if we need to send a new export, or the current one is still up-to-date
					if ($method == 'GET' &&	isset($_SERVER['HTTP_IF_NONE_MATCH']))
					{
						if ($this->debug) error_log(__METHOD__."($method,,$id) HTTP_IF_NONE_MATCH='$_SERVER[HTTP_IF_NONE_MATCH]', etag='$etag': 304 Not Modified");
						return '304 Not Modified';
					}
				}
			}
			if (isset($_SERVER['HTTP_IF_NONE_MATCH']))
			{
				if ($this->debug) error_log(__METHOD__."($method,,$id) HTTP_IF_NONE_MATCH='$_SERVER[HTTP_IF_NONE_MATCH]', etag='$etag': 412 Precondition failed");
				return '412 Precondition Failed';
			}
		}
		return $entry;
	}

	/**
	 * Get the handler for the given app
	 *
	 * @static
	 * @param string $app 'calendar', 'addressbook' or 'infolog'
	 * @param int $user=null owner of the collection, default current user
	 * @param int $debug=null debug-level to set
	 * @param string $base_uri=null base url of handler
	 * @param string $principalURL=null pricipal url of handler
	 * @return groupdav_handler
	 */
	static function &app_handler($app,$debug=null,$base_uri=null,$principalURL=null)
	{
		static $handler_cache = array();

		if (!array_key_exists($app,$handler_cache))
		{
			$class = $app.'_groupdav';
			if (!class_exists($class) && !class_exists($class = 'groupdav_'.$app)) return null;

			$handler_cache[$app] = new $class($app,$debug,$base_uri,$principalURL);
		}
		$handler_cache[$app]->$debug = $debug;
		$handler_cache[$app]->$base_uri = $base_uri;
		$handler_cache[$app]->$principalURL = $principalURL;

		if ($debug) error_log(__METHOD__."('$app', '$base_uri', '$principalURL')");

		return $handler_cache[$app];
	}

	/**
	 * Identify know GroupDAV agents by HTTP_USER_AGENT header
	 *
	 * @return string|boolean agent name or false
	 */
	static function get_agent()
	{
		static $agent;

		if (is_null($agent))
		{
			$agent = false;
			// identify the agent (GroupDAV client) from the HTTP_USER_AGENT header
			$user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
			foreach(array(
				'iphone'            => 'iphone',	// Apple iPhone iCal
				'davkit'            => 'davkit',	// Apple iCal 10.6
				'coredav'           => 'coredav',	// Apple iCal 10.7
				'dataaccess'        => 'dataaccess',	// Apple addressbook iPhone
				'cfnetwork'         => 'cfnetwork',	// Apple Addressbook 10.6/7
				'bionicmessage.net' => 'funambol',	// funambol GroupDAV connector from bionicmessage.net
				'zideone'           => 'zideone',	// zideone outlook plugin
				'lightning'         => 'lightning',	// Lighting (SOGo connector for addressbook)
				'webkit'			=> 'webkit',	// Webkit Browser (also reports KHTML!)
				'khtml'             => 'kde',		// KDE clients
				'neon'              => 'neon',
				'ical4ol'			=> 'ical4ol',	// iCal4OL client
				'evolution'         => 'evolution',	// Evolution
			) as $pattern => $name)
			{
				if (strpos($user_agent,$pattern) !== false)
				{
					$agent = $name;
					break;
				}
			}
			if (!$agent)
			{
				//error_log("Unrecogniced GroupDAV client: HTTP_USER_AGENT='$_SERVER[HTTP_USER_AGENT]'!");
			}
			else
			{
				switch ($agent)
				{
					case 'cfnetwork':
						if (preg_match('/address%20book\/([0-9.]+)/', $user_agent, $matches))
						{
							if ((int)$matches[1] < 868) $agent .= '_old';
						}
						break;
				}
			}
		}

		if ($debug) error_log(__METHOD__."GroupDAV client: $agent");

		return $agent;
	}
}

/**
 * Iterator for propfinds using propfind callback of a groupdav_handler to query results in chunks
 *
 * The propfind method just computes a filter and then returns an instance of this iterator instead of the files:
 *
 *	function propfind($path,$options,&$files,$user,$id='')
 *	{
 *		$filter = array();
 * 		// compute filter from path, options, ...
 *
 * 		$files['files'] = new groupdav_propfind_iterator($this,$filter,$files['files']);
 *
 * 		return true;
 * 	}
 */
class groupdav_propfind_iterator implements Iterator
{
	/**
	 * current path
	 *
	 * @var string
	 */
	protected $path;

	/**
	 * Handler to call for entries
	 *
	 * @var groupdav_handler
	 */
	protected $handler;

	/**
	 * Filter of propfind call
	 *
	 * @var array
	 */
	protected $filter;

	/**
	 * Extra responses to return too
	 *
	 * @var array
	 */
	protected $common_files;

	/**
	 * current chunk
	 *
	 * @var array
	 */
	protected $files;


	/**
	 * Start value for callback
	 *
	 * @var int
	 */
	protected $start=0;

	/**
	 * Number of entries queried from callback in one call
	 *
	 */
	const CHUNK_SIZE = 500;

	/**
	 * Log calls via error_log()
	 *
	 * @var boolean
	 */
	public $debug = false;

	/**

	/**
	 * Constructor
	 *
	 * @param groupdav_handler $handler
	 * @param array $filter filter for propfind call
	 * @param array $files=array() extra files/responses to return too
	 */
	public function __construct(groupdav_handler $handler, $path, array $filter,array &$files=array())
	{
		if ($this->debug) error_log(__METHOD__."('$path', ".array2string($filter).",)");
		$this->path    = $path;
		$this->handler = $handler;
		$this->filter  = $filter;
		$this->files   = $this->common_files = $files;
		reset($this->files);
	}

	/**
	 * Return the current element
	 *
	 * @return array
	 */
	public function current()
	{
		if ($this->debug) error_log(__METHOD__."() returning ".array2string(current($this->files)));
		return current($this->files);
	}

	/**
	 * Return the key of the current element
	 *
	 * @return int|string
	 */
	public function key()
	{
		$current = current($this->files);

		if ($this->debug) error_log(__METHOD__."() returning ".array2string($current['path']));
		return $current['path'];	// we return path as key
	}

	/**
	 * Move forward to next element (called after each foreach loop)
	 */
	public function next()
	{
		if (next($this->files) !== false)
		{
			if ($this->debug) error_log(__METHOD__."() returning TRUE");
			return true;
		}
		// check if previous query gave less then CHUNK_SIZE entries --> we're done
		if ($this->start && count($this->files) < self::CHUNK_SIZE)
		{
			if ($this->debug) error_log(__METHOD__."() returning FALSE (no more entries)");
			return false;
		}
		// try query further files via propfind callback of handler and store result in $this->files
		$this->files = $this->handler->propfind_callback($this->path,$this->filter,array($this->start,self::CHUNK_SIZE));
		if (!is_array($this->files) || !($entries = count($this->files)))
		{
			if ($this->debug) error_log(__METHOD__."() returning FALSE (no more entries)");
			return false;	// no further entries
		}
		$this->start += self::CHUNK_SIZE;
		reset($this->files);

		if ($this->debug) error_log(__METHOD__."() this->start=$this->start, entries=$entries, count(this->files)=".count($this->files)." returning ".array2string(current($this->files) !== false));

		return current($this->files) !== false;
	}

	/**
	 * Rewind the Iterator to the first element (called at beginning of foreach loop)
	 */
	public function rewind()
	{
		if ($this->debug) error_log(__METHOD__."()");

		$this->start = 0;
		$this->files = $this->common_files;
		if (!$this->files) $this->next();	// otherwise valid will return false and nothing get returned
		reset($this->files);
	}

	/**
	 * Checks if current position is valid
	 *
	 * @return boolean
	 */
	public function valid ()
	{
		if ($this->debug) error_log(__METHOD__."() returning ".array2string(current($this->files) !== false));
		return current($this->files) !== false;
	}
}
