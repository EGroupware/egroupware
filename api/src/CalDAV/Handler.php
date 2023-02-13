<?php
/**
 * EGroupware: CalDAV/CardDAV/GroupDAV access: abstract baseclass for application handlers
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage caldav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-16 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

namespace EGroupware\Api\CalDAV;

use EGroupware\Api;

/**
 * EGroupware: GroupDAV access: abstract baseclass for groupdav/caldav/carddav handlers
 *
 * Permanent error_log() calls should use $this->caldav->log($str) instead, to be send to PHP error_log()
 * and our request-log (prefixed with "### " after request and response, like exceptions).
 *
 * @ToDo: If precondition for PUT, see https://tools.ietf.org/html/rfc6578#section-5
 */
abstract class Handler
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
	 * @var Api\Accounts
	 */
	var $accounts;
	/**
	 * Reference to the ACL class
	 *
	 * @var Api\Acl
	 */
	var $acl;
	/**
	 * Translates method names into ACL bits
	 *
	 * @var array
	 */
	var $method2acl = array(
		'GET' => Api\Acl::READ,
		'PUT' => Api\Acl::EDIT,
		'PATCH' => Api\Acl::EDIT,
		'DELETE' => Api\Acl::DELETE,
	);
	/**
	 * eGW application responsible for the handler
	 *
	 * @var string
	 */
	var $app;
	/**
	 * Calling CalDAV object
	 *
	 * @var Api\CalDAV
	 */
	var $caldav;
	/**
	 * Base url of handler, need to prefix all pathes not automatic handled by Api\CalDAV
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
	 * New id of put/post stored here by put_response_headers for check_return_representation
	 *
	 * @var string
	 */
	var $new_id;

	/**
	 * Regular expression to identify content requiring QUOTED-PRINTABLE encoding
	 *
	 * Used in {addressbook,calendar,infolog}/inc/class.*cal.inc.php
	 */
	const REQUIRE_QUOTED_PRINTABLE_ENCODING = '/([\000-\012\013\015\016\020-\037\075])/';

	/**
	 * Constructor
	 *
	 * @param string $app 'calendar', 'addressbook' or 'infolog'
	 * @param Api\CalDAV $caldav calling class
	 */
	function __construct($app, Api\CalDAV $caldav)
	{
		$this->app = $app;
		if (!is_null($caldav->debug)) $this->debug = $caldav->debug;
		$this->base_uri = $caldav->base_uri;
		$this->caldav = $caldav;

		$this->agent = self::get_agent();

		$this->egw_charset = Api\Translation::charset();

		$this->accounts = $GLOBALS['egw']->accounts;
		$this->acl = $GLOBALS['egw']->acl;
	}

	/**
	 * Handle propfind request for an application folder
	 *
	 * @param string $path
	 * @param array &$options
	 * @param array &$files
	 * @param int $user account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	abstract function propfind($path,&$options,&$files,$user);

	/**
	 * Propfind callback, if interator is used
	 *
	 * @param string $path
	 * @param array &$filter
	 * @param array|boolean $start false=return all or array(start,num)
	 * @return array with "files" array with values for keys path and props
	 */
	function &propfind_callback($path, array &$filter, $start)
	{
		unset($path, $filter, $start);	// not used, but required by function signature
	}

	/**
	 * Handle get request for an applications entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user =null account_id
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	abstract function get(&$options,$id,$user=null);

	/**
	 * Handle get request for an applications entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user =null account_id of owner, default null
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	abstract function put(&$options,$id,$user=null);

	/**
	 * Handle get request for an applications entry
	 *
	 * @param array &$options
	 * @param int $id
	 * @param int $user account_id of collection owner
	 * @return mixed boolean true on success, false on failure or string with http status (eg. '404 Not Found')
	 */
	abstract function delete(&$options,$id,$user);

	/**
	 * Read an entry
	 *
	 * @param string|int $id
	 * @param string $path =null implementation can use it, used in call from _common_get_put_delete
	 * @return array|boolean array with entry, false if no read rights, null if $id does not exist
	 */
	abstract function read($id /*,$path=null*/);

	/**
	 * Get id from entry-array returned by read()
	 *
	 * @param int|string|array $entry
	 * @return int|string
	 */
	function get_id($entry)
	{
		return is_array($entry) ? $entry['id'] : $entry;
	}

	/**
	 * Check if user has the neccessary rights on an entry
	 *
	 * @param int $acl Api\Acl::READ, Api\Acl::EDIT or Api\Acl::DELETE
	 * @param array|int $entry entry-array or id
	 * @return boolean null if entry does not exist, false if no access, true if access permitted
	 */
	abstract function check_access($acl,$entry);

	/**
	 * Add extra properties for collections
	 *
	 * @param array $props =array() regular props by the groupdav handler
	 * @param string $displayname
	 * @param string $base_uri =null base url of handler
	 * @param int $user =null account_id of owner of collection
	 * @return array
	 */
	public function extra_properties(array $props, $displayname, $base_uri=null, $user=null)
	{
		unset($displayname, $base_uri, $user);	// not used, but required by function signature

		return $props;
	}

	/**
	 * Get the etag for an entry, can be reimplemented for other algorithm or field names
	 *
	 * @param array|int $entry array with event or cal_id
	 * @return string|boolean string with etag or false
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
	 * @param array& $options
	 * @param int|string& $id on return self::$path_extension got removed
	 * @param boolean& $return_no_access =false if set to true on call, instead of '403 Forbidden' the entry is returned and $return_no_access===false
	 * @param boolean $ignore_if_match =false if true, ignore If-Match precondition
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
		if ($id && !($entry = $this->read($id, $options['path'])) && ($method != 'PUT' || $entry === false) ||
			($extra_acl != Api\Acl::READ && $this->check_access($extra_acl,$entry) === false))
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
					if ($this->debug) error_log(__METHOD__."($method,path=$options[path],$id) HTTP_IF_MATCH='$_SERVER[HTTP_IF_MATCH]', etag='$etag': 412 Precondition failed".array2string($entry));
					// honor Prefer: return=representation for 412 too (no need for client to explicitly reload)
					$this->check_return_representation($options, $id);
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
					// honor Prefer: return=representation for 412 too (no need for client to explicitly reload)
					$this->check_return_representation($options, $id);
					return '412 Precondition Failed';
				}
			}
		}
		return $entry;
	}

	/**
	 * Return representation, if requested by HTTP Prefer header
	 *
	 * @param array $options
	 * @param int $id
	 * @param int $user =null account_id
	 * @return string|boolean http status of get or null if no representation was requested
	 */
	public function check_return_representation($options, $id, $user=null)
	{
		//error_log(__METHOD__."(, $id, $user) start ".function_backtrace());
		if (isset($_SERVER['HTTP_PREFER']) && in_array('return=representation', preg_split('/, ?/', $_SERVER['HTTP_PREFER'])))
		{
			if ($_SERVER['REQUEST_METHOD'] == 'POST')
			{
				header('Content-Location: '.Api\Framework::getUrl($this->caldav->base_uri.$options['path']));
			}

			// remove If-Match or If-None-Match headers, otherwise HTTP status 412 goes into endless loop!
			unset($_SERVER['HTTP_IF_MATCH']);
			unset($_SERVER['HTTP_IF_NONE_MATCH']);

			if (($ret = $this->get($options, $id ? $id : $this->new_id, $user)) && !empty($options['data']))
			{
				if (!$this->caldav->use_compression()) header('Content-Length: '.$this->caldav->bytes($options['data']));
				header('Content-Type: '.$options['mimetype']);
				echo $options['data'];
			}
		}
		//error_log(__METHOD__."(, $id, $user) returning ".array2string($ret));
		return $ret;
	}

	/**
	 * Update etag, ctag and sync-token to reflect changed attachments
	 *
	 * Not abstract, as not need to implement for apps not supporting managed attachments
	 *
	 * @param array|string|int $entry array with entry data from read, or id
	 */
	public function update_tags($entry)
	{
		unset($entry);	// not used, but required by function signature
	}

	/**
	 * Get the handler for the given app
	 *
	 * @static
	 * @param string $app 'calendar', 'addressbook' or 'infolog'
	 * @param Api\CalDAV $groupdav calling class
	 * @return self
	 */
	static function app_handler($app, Api\CalDAV $groupdav)
	{
		static $handler_cache = array();

		if (!array_key_exists($app,$handler_cache))
		{
			$class = $app.'_groupdav';
			if (!class_exists($class) && !class_exists($class = __NAMESPACE__.'\\'.ucfirst($app))) return null;

			$handler_cache[$app] = new $class($app, $groupdav);
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
		static $agent=null;

		if (is_null($agent))
		{
			$agent = false;
			// identify the agent (GroupDAV client) from the HTTP_USER_AGENT header
			$user_agent = strtolower($_SERVER['HTTP_USER_AGENT']);
			foreach(array(
				'carddav-sync'      => 'carddav-sync',	// dmfs.org CardDAV client for Android: CardDAV-Sync (Android) (like iOS/5.0.1 (9A405) dataaccessd/1.0) gzip
				'iphone'            => 'iphone',	// Apple iPhone iCal
				'davkit'            => 'davkit',	// Apple iCal 10.6
				'coredav'           => 'coredav',	// Apple iCal 10.7
				'calendarstore'     => 'calendarstore',	// Apple iCal 5.0.1 under OS X 10.7.2
				'calendaragent/'    => 'calendaragent',	// Apple iCal OS X 10.8*: Mac OS X/10.8.2 (12C54) CalendarAgent/55
				'dataaccess'        => 'dataaccess',	// Apple addressbook iPhone
				'cfnetwork'         => 'cfnetwork',	// Apple Addressbook 10.6/7
				'addressbook/'      => 'cfnetwork',	// Apple Addressbook OS X 10.8*: Mac OS X/10.8.2 (12C54) AddressBook/1167
				'bionicmessage.net' => 'funambol',	// funambol GroupDAV connector from bionicmessage.net
				'zideone'           => 'zideone',	// zideone outlook plugin
				'lightning'         => 'lightning',	// Lighting (incl. SOGo connector for addressbook)
				'webkit'			=> 'webkit',	// Webkit Browser (also reports KHTML!)
				'akonadi'			=> 'akonadi',	// new KDE PIM framework (also reports KHTML!)
				'khtml'             => 'kde',		// KDE clients
				'neon'              => 'neon',
				'ical4ol'			=> 'ical4ol',	// iCal4OL client
				'evolution'         => 'evolution',	// Evolution
				'thunderbird'       => 'thunderbird',	// SOGo connector for addressbook, no Lightning installed
				'caldavsynchronizer'=> 'caldavsynchronizer',	// Outlook CalDAV Synchroniser (https://caldavsynchronizer.org/)
				'davx5'             => 'davx5',     // DAVx5 (https://www.davx5.com/)
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
						$matches = null;
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
		//error_log(__METHOD__."GroupDAV client: $agent");
		return $agent;
	}

	/**
	 * Get grants of current user and app
	 *
	 * @return array user-id => Api\Acl::ADD|Api\Acl::READ|Api\Acl::EDIT|Api\Acl::DELETE pairs
	 */
	public function get_grants()
	{
		return $this->acl->get_grants($this->app, $this->app != 'addressbook');
	}

	/**
	 * Return priviledges for current user, default is read and read-current-user-privilege-set
	 *
	 * Priviledges are for the collection, not the resources / entries!
	 *
	 * @param string $path path of collection
	 * @param int $user =null owner of the collection, default current user
	 * @return array with privileges
	 */
	public function current_user_privileges($path, $user=null)
	{
		unset($path);	// not used, but required by function signature

		$grants = $this->get_grants();
		$priviledes = array('read-current-user-privilege-set' => 'read-current-user-privilege-set');

		if (is_null($user) || $grants[$user] & Api\Acl::READ)
		{
			$priviledes['read'] = 'read';
			// allows on all calendars/addressbooks to write properties, as we store them on a per-user basis
			// and only allow to modify explicit named properties in CalDAV, CardDAV or Calendarserver name-space
			$priviledes['write-properties'] = 'write-properties';
		}
		if (is_null($user) || $grants[$user] & Api\Acl::ADD)
		{
			$priviledes['bind'] = 'bind';	// PUT for new resources
		}
		if (is_null($user) || $grants[$user] & Api\Acl::EDIT)
		{
			$priviledes['write-content'] = 'write-content';	// otherwise iOS calendar does not allow to add events
		}
		if (is_null($user) || $grants[$user] & Api\Acl::DELETE)
		{
			$priviledes['unbind'] = 'unbind';	// DELETE
		}
		// copy/move of existing resources might require write-properties, thought we do not support an explicit PROPATCH
		//error_log(__METHOD__."('$path', ".array2string($user).') returning '.array2string($priviledes).' '.function_backtrace());
		return $priviledes;
	}

	/**
	 * Create the path/name for an entry
	 *
	 * @param int|string|array $entry
	 * @return string
	 */
	function get_path($entry)
	{
		return (is_array($entry) ? $entry[self::$path_attr] : $entry).self::$path_extension;
	}

	/**
	 * Send response-headers for a PUT (or POST with add-member query parameter)
	 *
	 * @param int|array $entry id or array of new created entry
	 * @param string $path
	 * @param int|string $retval
	 * @param boolean $path_attr_is_name =true true: path_attr is ca(l|rd)dav_name, false: id (GroupDAV needs Location header)
	 * @param string $etag =null etag, to not calculate it again (if != null)
	 * @param string $prefix='' prefix for id
	 */
	function put_response_headers($entry, $path, $retval, $path_attr_is_name=true, $etag=null, string $prefix='')
	{
		//error_log(__METHOD__."(".array2string($entry).", '$path', ".array2string($retval).", path_attr_is_name=$path_attr_is_name, etag=".array2string($etag).")");
		// we should not return an etag here, as EGroupware never stores ical/vcard byte-by-byte
		// as SOGO Connector requires ETag header to recognice as successful PUT, we are sending them again for it
		// --> as all clients dislike not getting an ETag for a PUT, we sending it again even not storing byte-by-byte
		//if (get_class($this) == 'addressbook_groupdav' && in_array(self::get_agent(),array('thunderbird','lightning')))
		{
			if (is_null($etag)) $etag = $this->get_etag($entry);
			header('ETag: "'.$etag.'"');
		}

		// store (new) id for check_return_representation
		$this->new_id = $this->get_path($entry);

		// send Location header only on success AND if we dont use caldav_name as path-attribute or
		if ((is_bool($retval) ? $retval : $retval[0] === '2') && (!$path_attr_is_name ||
			// POST with add-member query parameter
			$_SERVER['REQUEST_METHOD'] == 'POST') ||
			// in case we choose to use a different name for the resource, give the client a hint
			basename($path) !== $prefix.$this->new_id)
		{
			$path = preg_replace('|(.*)/[^/]*|', '\1/', $path);
			header('Location: '.$this->base_uri.$path.$this->new_id);
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
	 * @param array $hook_data
	 * @return array of array with settings
	 */
	static function get_settings($hook_data)
	{
		unset($hook_data);	// not used, but required by function signature

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
		// only run get_etag, if we really need it, as it might be expensive (eg. calendar)
		if (!isset($props['getetag']))
		{
			$props['getetag'] = $this->get_etag($entry);
		}
		foreach(array(
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
		if ($this->caldav->prop_requested('current-user-privilege-set') === true && !isset($props['current-user-privilege-set']))
		{
			if ($this->check_access(Api\Acl::EDIT, $entry))
			{
				$privileges[] = 'write-content';
			}
		}
		if ($this->caldav->prop_requested('owner') === true && !isset($props['owner']) &&
			($account_lid = $this->accounts->id2name($entry['owner'])))
		{
			$type = $this->accounts->get_type($entry['owner']) == 'u' ? 'users' : 'groups';
			$props['owner'] = Api\CalDAV::mkprop('href', $this->base_uri.'/principals/'.$type.'/'.$account_lid.'/');
		}
		return $this->caldav->add_resource($path.$this->get_path($entry), $props, $privileges);
	}

	/**
	 * Return base uri, making sure it's either a full uri (incl. protocoll and host) or just a path
	 *
	 * base_uri of WebDAV class can be both, depending on EGroupware config
	 *
	 * @param boolean $full_uri =true
	 * @return string eg. https://domain.com/egroupware/groupdav.php
	 */
	public function base_uri($full_uri=true)
	{
		static $uri=null;
		static $path=null;

		if (!isset($uri))
		{
			$uri = $path = $this->caldav->base_uri;
			if ($uri[0] == '/')
			{
				$uri = Api\Framework::getUrl($uri);
			}
			else
			{
				$path = parse_url($uri, PHP_URL_PATH);
			}
		}
		return $full_uri ? $uri : $path;
	}

	/**
	 * sync-token to be filled by propfind_callback and returned by get_sync_token method
	 */
	protected $sync_collection_token;

	/**
	 * Query sync-token from a just run sync-collection report
	 *
	 * Modified time is taken from value filled by propfind_callback in sync_collection_token.
	 *
	 * @param string $path
	 * @param int $user parameter necessary to call getctag, if no $token specified
	 * @return string
	 */
	public function get_sync_collection_token($path, $user=null, $more_results=null)
	{
		//error_log(__METHOD__."('$path', $user, more_results=$more_results) this->sync_collection_token=".$this->sync_collection_token);
		if ($more_results)
		{
			if (Api\CalDAV::isJSON())
			{
				$error = ",\n\t".'"more-results": true';
			}
			else
			{
				$error =
					' <D:response>
  <D:href>' . htmlspecialchars($this->caldav->base_uri . $this->caldav->path) . '</D:href>
  <D:status>HTTP/1.1 507 Insufficient Storage</D:status>
  <D:error><D:number-of-matches-within-limits/></D:error>
 </D:response>
';
				if ($this->caldav->crrnd)
				{
					$error = str_replace(array('<D:', '</D:'), array('<', '</'), $error);
				}
			}
			echo $error;
		}
		return $this->get_sync_token($path, $user, $this->sync_collection_token);
	}

	/**
	 * Query sync-token
	 *
	 * We use ctag / max. modification time as sync-token. As garnularity is 1sec, we can never be sure,
	 * if there are more modifications to come in the current second.
	 *
	 * Therefor we are never returning current time, but 1sec less!
	 *
	 * Modified time is either taken from value filled by propfind_callback in $this->sync_token or
	 * by call to getctag();
	 *
	 * @param string $path
	 * @param int $user parameter necessary to call getctag, if no $token specified
	 * @param int $token =null modification time, default call getctag($path, $user) to fetch it
	 * @return string
	 */
	public function get_sync_token($path, $user, $token=null)
	{
		if (!isset($token)) $token = $this->getctag($path, $user);

		// never return current time, as more modifications might happen due to second granularity --> return 1sec less
		if (is_numeric($token) && $token >= (int)$GLOBALS['egw_info']['flags']['page_start_time'])
		{
			$token = (int)$GLOBALS['egw_info']['flags']['page_start_time'] - 1;
		}
		return $this->base_uri().$path.$token;
	}
}