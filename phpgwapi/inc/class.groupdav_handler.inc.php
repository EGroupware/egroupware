<?php
/**
 * EGroupware: GroupDAV access: abstract baseclass for groupdav/caldav/carddav handlers
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-12 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
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
	 * Reference to the ACL class
	 *
	 * @var acl
	 */
	var $acl;
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
	 * Calling groupdav object
	 *
	 * @var groupdav
	 */
	var $groupdav;
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
	 * Extension to append to url/path
	 *
	 * @var string
	 */
	static $path_extension = '.ics';

	/**
	 * Which attribute to use to contruct name part of url/path
	 *
	 * @var string
	 */
	static $path_attr = 'id';

	/**
	 * Constructor
	 *
	 * @param string $app 'calendar', 'addressbook' or 'infolog'
	 * @param groupdav $groupdav calling class
	 */
	function __construct($app, groupdav $groupdav)
	{
		$this->app = $app;
		if (!is_null($parent->debug)) $this->debug = $groupdav->debug;
		$this->base_uri = $groupdav->base_uri;
		$this->groupdav = $groupdav;

		$this->agent = self::get_agent();

		$this->egw_charset = translation::charset();

		$this->accounts = $GLOBALS['egw']->accounts;
		$this->acl = $GLOBALS['egw']->acl;
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
	 * @param int $user=null account_id of owner of collection
	 * @return array
	 */
	public function extra_properties(array $props=array(), $displayname, $base_uri=null, $user=null)
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
		return $entry['id'].':'.(isset($entry['etag']) ? $entry['etag'] : $entry['modified']);
	}

	/**
	 * Convert etag to the raw etag column value (without quotes, double colon and id)
	 *
	 * @param string $etag
	 * @return int
	 */
	static function etag2value($etag)
	{
		list(,$val) = explode(':',$etag,2);

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
	 * @param int|string &$id on return self::$path_extension got removed
	 * @param boolean &$return_no_access=false if set to true on call, instead of '403 Forbidden' the entry is returned and $return_no_access===false
	 * @param boolean $ignore_if_match=false if true, ignore If-Match precondition
	 * @return array|string entry on success, string with http-error-code on failure, null for PUT on an unknown id
	 */
	function _common_get_put_delete($method,&$options,&$id,&$return_no_access=false,$ignore_if_match=false)
	{
		if (self::$path_extension) $id = basename($id,self::$path_extension);

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
			if (isset($_SERVER['HTTP_IF_MATCH']) && !$ignore_if_match)
			{
				$this->http_if_match = $_SERVER['HTTP_IF_MATCH'];
				// strip of quotes around etag, if they exist, that way we allow etag with and without quotes
				if ($this->http_if_match[0] == '"') $this->http_if_match = substr($this->http_if_match, 1, -1);

				if ($this->http_if_match !== $etag)
				{
					if ($this->debug) error_log(__METHOD__."($method,,$id) HTTP_IF_MATCH='$_SERVER[HTTP_IF_MATCH]', etag='$etag': 412 Precondition failed");
					return '412 Precondition Failed';
				}
			}
			if (isset($_SERVER['HTTP_IF_NONE_MATCH']))
			{
				$if_none_match = $_SERVER['HTTP_IF_NONE_MATCH'];
				// strip of quotes around etag, if they exist, that way we allow etag with and without quotes
				if ($if_none_match[0] == '"') $if_none_match = substr($if_none_match, 1, -1);

				// if an IF_NONE_MATCH is given, check if we need to send a new export, or the current one is still up-to-date
				if (in_array($method, array('GET','HEAD')) && $etag === $if_none_match)
				{
					if ($this->debug) error_log(__METHOD__."($method,,$id) HTTP_IF_NONE_MATCH='$_SERVER[HTTP_IF_NONE_MATCH]', etag='$etag': 304 Not Modified");
					return '304 Not Modified';
				}
				if ($method == 'PUT' && ($if_none_match == '*' || $if_none_match == $etag))
				{
					if ($this->debug) error_log(__METHOD__."($method,,$id) HTTP_IF_NONE_MATCH='$_SERVER[HTTP_IF_NONE_MATCH]', etag='$etag': 412 Precondition failed");
					return '412 Precondition Failed';
				}
			}
		}
		return $entry;
	}

	/**
	 * Get the handler for the given app
	 *
	 * @static
	 * @param string $app 'calendar', 'addressbook' or 'infolog'
	 * @param groupdav $groupdav calling class
	 * @return groupdav_handler
	 */
	static function app_handler($app, $groupdav)
	{
		static $handler_cache = array();

		if (!array_key_exists($app,$handler_cache))
		{
			$class = $app.'_groupdav';
			if (!class_exists($class) && !class_exists($class = 'groupdav_'.$app)) return null;

			$handler_cache[$app] = new $class($app, $groupdav);
		}

		if ($debug) error_log(__METHOD__."('$app')");

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
				'calendarstore'     => 'calendarstore',	// Apple iCal 5.0.1 under OS X 10.7.2
				'dataaccess'        => 'dataaccess',	// Apple addressbook iPhone
				'cfnetwork'         => 'cfnetwork',	// Apple Addressbook 10.6/7
				'bionicmessage.net' => 'funambol',	// funambol GroupDAV connector from bionicmessage.net
				'zideone'           => 'zideone',	// zideone outlook plugin
				'lightning'         => 'lightning',	// Lighting (SOGo connector for addressbook)
				'webkit'			=> 'webkit',	// Webkit Browser (also reports KHTML!)
				'akonadi'			=> 'akonadi',	// new KDE PIM framework (also reports KHTML!)
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
					case 'kde':
						// Akonadi (new KDE Pim framework) unfortunately has same user-agent as old kde
						// we can only assume KDE 4.7+ uses Akonadi native resource, while below this was not available
						// Unfortunately the old pre-Akonadi GroupDAV resource can still be used, but we have no way of detecting it
						if (preg_match('/KHTML\/([0-9.]+)/', $_SERVER['HTTP_USER_AGENT'], $matches) && (float)$matches[1] >= 4.7)
						{
							$agent = 'akonadi';
						}
						break;
				}
			}
		}

		if ($debug) error_log(__METHOD__."GroupDAV client: $agent");

		return $agent;
	}

	/**
	 * Return priviledges for current user, default is read and read-current-user-privilege-set
	 *
	 * Priviledges are for the collection, not the resources / entries!
	 *
	 * @param string $path path of collection
	 * @param int $user=null owner of the collection, default current user
	 * @return array with privileges
	 */
	public function current_user_privileges($path, $user=null)
	{
		static $grants;
		if (is_null($grants))
		{
			$grants = $this->acl->get_grants($this->app, $this->app != 'addressbook');
		}
		$priviledes = array('read-current-user-privilege-set' => 'read-current-user-privilege-set');

		if (!$user || $grants[$user] & EGW_ACL_READ)
		{
			$priviledes['read'] = 'read';
			// allows on all calendars/addressbooks to write properties, as we store them on a per-user basis
			// and only allow to modify explicit named properties in CalDAV, CardDAV or Calendarserver name-space
			$priviledes['write-properties'] = 'write-properties';
		}
		if (!$user || $grants[$user] & EGW_ACL_ADD)
		{
			$priviledes['bind'] = 'bind';	// PUT for new resources
		}
		if (!$user || $grants[$user] & EGW_ACL_EDIT)
		{
			$priviledes['write-content'] = 'write-content';	// otherwise iOS calendar does not allow to add events
		}
		if (!$user || $grants[$user] & EGW_ACL_DELETE)
		{
			$priviledes['unbind'] = 'unbind';	// DELETE
		}
		// copy/move of existing resources might require write-properties, thought we do not support an explicit PROPATCH
		return $priviledes;
	}

	/**
	 * Create the path/name for an entry
	 *
	 * @param array $entry
	 * @return string
	 */
	function get_path($entry)
	{
		return $entry[self::$path_attr].self::$path_extension;
	}

	/**
	 * Send response-headers for a PUT (or POST with add-member query parameter)
	 *
	 * @param int|array $entry id or array of new created entry
	 * @param string $path
	 * @param int|string $retval
	 * @param boolean $path_attr_is_name=true true: path_attr is ca(l|rd)dav_name, false: id (GroupDAV needs Location header)
	 */
	function put_response_headers($entry, $path, $retval, $path_attr_is_name=true)
	{
		// we should not return an etag here, as EGroupware never stores ical/vcard byte-by-byte
		//header('ETag: "'.$this->get_etag($entry).'"');

		// send Location header only if we dont use caldav_name as path-attribute or
		if ($retval !== true && (!$path_attr_is_name ||
			// POST with add-member query parameter
			$_SERVER['REQUEST_METHOD'] == 'POST' && isset($_GET['add-member'])))
		{
			$path = preg_replace('|(.*)/[^/]*|', '\1/', $path);
			header('Location: '.$this->base_uri.$path.$this->get_path($entry));
		}
	}

	/**
	 * Return calendars/addressbooks shared from other users with the current one
	 *
	 * return array account_id => account_lid pairs
	 */
	function get_shared()
	{
		return array();
	}

	/**
	 * Return appliction specific settings
	 *
	 * return array of array with settings
	 */
	static function get_settings()
	{
		return array();
	}

	/**
	 * Add a resource
	 *
	 * @param string $path path of collection, NOT entry!
	 * @param array $entry
	 * @param array $props
	 * @return array with values for keys 'path' and 'props'
	 */
	public function add_resource($path, array $entry, array $props)
	{
		foreach(array(
			'getetag' => $this->get_etag($entry),
			'getcontenttype' => 'text/calendar',
			'getlastmodified' => $entry['modified'],
			'displayname' => $entry['title'],
		) as $name => $value)
		{
			if (!isset($props[$name]))
			{
				$props[$name] = $value;
			}
		}
		// if requested add privileges
		$privileges = array('read', 'read-current-user-privilege-set');
		if ($this->groupdav->prop_requested('current-user-privilege-set') === true && !isset($props['current-user-privilege-set']))
		{
			if ($this->check_access(EGW_ACL_EDIT, $entry))
			{
				$privileges[] = 'write-content';
			}
		}
		if ($this->groupdav->prop_requested('owner') === true && !isset($props['owner']) &&
			($account_lid = $this->accounts->name2id($entry['owner'])))
		{
			$type = $this->accounts->get_type($entry['owner']) == 'u' ? 'users' : 'groups';
			$props['owner'] = HTTP_WebDAV_Server::mkprop('href', $this->base_uri.'/principals/'.$type.'/'.$account_lid.'/');
		}
		// we urldecode here, as HTTP_WebDAV_Server uses a minimal (#?%) urlencoding for incomming pathes and urlencodes pathes in propfind
		return $this->groupdav->add_resource($path.urldecode($this->get_path($entry)), $props, $privileges);
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
