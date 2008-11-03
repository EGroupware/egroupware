<?php
/**
 * eGroupWare: GroupDAV access: abstract baseclass for groupdav/caldav/carddav handlers
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007/8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * eGroupWare: GroupDAV access: abstract baseclass for groupdav/caldav/carddav handlers
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
	 * Reference to the translation class
	 *
	 * @var translation
	 */
	var $translation;
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
	 */
	function __construct($app,$debug=null,$base_uri=null)
	{
		$this->app = $app;
		if (!is_null($debug)) $this->debug = $debug;
		$this->base_uri = is_null($base_uri) ? $base_uri : $_SERVER['SCRIPT_NAME'];
		$this->agent = self::get_agent();

		$this->translation =& $GLOBALS['egw']->translation;
		$this->egw_charset = $this->translation->charset();
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
	 * Handle get request for an applications entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	abstract function get(&$options,$id);

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
	 * @return array
	 */
	static function extra_properties(array $props=array())
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
			error_log(__METHOD__."(".array2string($entry).") Cant create etag!");
			return false;
		}
		return '"'.$entry['id'].':'.(isset($entry['etag']) ? $entry['etag'] : $entry['modified']).'"';
	}

	/**
	 * Convert etag to the raw etag column value (without quotes, double colon and id)
	 *
	 * @param string $etag
	 * @return int
	 */
	static function etag2value($etag)
	{
		list(,$val) = explode(':',substr($etag,1,-1),2);

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
	 * @return array/string entry on success, string with http-error-code on failure, null for PUT on an unknown id
	 */
	function _common_get_put_delete($method,&$options,$id,&$return_no_access=false)
	{
		if (!in_array($this->app,array('principals','groups')) && !$GLOBALS['egw_info']['user']['apps'][$this->app])
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
				if ($this->debug) error_log(__METHOD__."($method,,$id,$return_no_access) is_null(\$entry)=".(int)is_null($entry).", set to false");
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
			if (isset($_SERVER['HTTP_IF_MATCH']) && ($this->http_if_match = $_SERVER['HTTP_IF_MATCH']) != $etag)
			{
				if ($this->debug) error_log(__METHOD__."($method,,$id) HTTP_IF_MATCH='$_SERVER[HTTP_IF_MATCH]', etag='$etag': 412 Precondition failed");
				return '412 Precondition Failed';
			}
			// if an IF_NONE_MATCH is given, check if we need to send a new export, or the current one is still up-to-date
			if ($method == 'GET' && isset($_SERVER['HTTP_IF_NONE_MATCH']) && $_SERVER['HTTP_IF_NONE_MATCH'] == $etag)
			{
				if ($this->debug) error_log(__METHOD__."($method,,$id) HTTP_IF_NONE_MATCH='$_SERVER[HTTP_IF_NONE_MATCH]', etag='$etag': 304 Not Modified");
				return '304 Not Modified';
			}
		}
		return $entry;
	}

	/**
	 * Get the handler for the given app
	 *
	 * @static
	 * @param string $app 'calendar', 'addressbook' or 'infolog'
	 * @param int $debug=null debug-level to set
	 * @param string $base_uri=null base url of handler
	 * @return groupdav_handler
	 */
	static function &app_handler($app,$debug=null,$base_uri=null)
	{
		static $handler_cache = array();

		if (!array_key_exists($app,$handler_cache))
		{
			$class = $app.'_groupdav';
			if (!class_exists($class) && !class_exists($class = 'groupdav_'.$app)) return null;

			$handler_cache[$app] = new $class($app,$debug,$base_uri);
		}
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
				'davkit'            => 'davkit',	// Apple iCal
				'bionicmessage.net' => 'funambol',	// funambol GroupDAV connector from bionicmessage.net
				'zideone'           => 'zideone',	// zideone outlook plugin
				'lightning'         => 'lightning',	// Lighting (SOGo connector for addressbook)
				'khtml'             => 'kde',		// KDE clients
				'cadaver'           => 'cadaver',
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
				error_log("Unrecogniced GroupDAV client: HTTP_USER_AGENT='$_SERVER[HTTP_USER_AGENT]'!");
			}
		}
		return $agent;
	}
}