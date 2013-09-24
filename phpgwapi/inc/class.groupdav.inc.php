<?php
/**
 * EGroupware: CalDAV/CardDAV/GroupDAV access
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage groupdav
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2007-13 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

require_once('HTTP/WebDAV/Server.php');

/**
 * EGroupware: GroupDAV access
 *
 * Using a modified PEAR HTTP/WebDAV/Server class from egw-pear!
 *
 * One can use the following url's releative (!) to http://domain.com/egroupware/groupdav.php
 *
 * - /                        base of Cal|Card|GroupDAV tree, only certain clients (KDE, Apple) can autodetect folders from here
 * - /principals/             principal-collection-set for WebDAV ACL
 * - /principals/users/<username>/
 * - /principals/groups/<groupname>/
 * - /<username>/             users home-set with
 * - /<username>/addressbook/ addressbook of user or group <username> given the user has rights to view it
 * - /<current-username>/addressbook-<other-username>/ shared addressbooks from other user or group
 * - /<current-username>/addressbook-accounts/ all accounts current user has rights to see
 * - /<username>/calendar/    calendar of user <username> given the user has rights to view it
 * - /<current-username>/calendar-<other-username>/ shared calendar from other user or group (only current <username>!)
 * - /<username>/inbox/       scheduling inbox of user <username>
 * - /<username>/outbox/      scheduling outbox of user <username>
 * - /<username>/infolog/     InfoLog's of user <username> given the user has rights to view it
 * - /addressbook/ all addressbooks current user has rights to, announced as directory-gateway now
 * - /addressbook-accounts/ all accounts current user has rights to see
 * - /calendar/    calendar of current user
 * - /infolog/     infologs of current user
 * - /(resources|locations)/<resource-name>/calendar calendar of a resource/location, if user has rights to view
 * - /<current-username>/(resource|location)-<resource-name> shared calendar from a resource/location
 *
 * Shared addressbooks or calendars are only shown in in users home-set, if he subscribed to it via his CalDAV preferences!
 *
 * Calling one of the above collections with a GET request / regular browser generates an automatic index
 * from the data of a allprop PROPFIND, allow to browse CalDAV/CardDAV/GroupDAV tree with a regular browser.
 *
 * Permanent error_log() calls should use groupdav->log($str) instead, to be send to PHP error_log()
 * and our request-log (prefixed with "### " after request and response, like exceptions).
 *
 * @link http://www.groupdav.org/ GroupDAV spec
 * @link http://caldav.calconnect.org/ CalDAV resources
 * @link http://carddav.calconnect.org/ CardDAV resources
 * @link http://calendarserver.org/ Apple calendar and contacts server
 */
class groupdav extends HTTP_WebDAV_Server
{
	/**
	 * DAV namespace
	 */
	const DAV = 'DAV:';
	/**
	 * GroupDAV namespace
	 */
	const GROUPDAV = 'http://groupdav.org/';
	/**
	 * CalDAV namespace
	 */
	const CALDAV = 'urn:ietf:params:xml:ns:caldav';
	/**
	 * CardDAV namespace
	 */
	const CARDDAV = 'urn:ietf:params:xml:ns:carddav';
	/**
	 * Apple Calendarserver namespace (eg. for ctag)
	 */
	const CALENDARSERVER = 'http://calendarserver.org/ns/';
	/**
	 * Apple Addressbookserver namespace (eg. for ctag)
	 */
	const ADDRESSBOOKSERVER = 'http://addressbookserver.org/ns/';
	/**
	 * Apple iCal namespace (eg. for calendar color)
	 */
	const ICAL = 'http://apple.com/ns/ical/';
	/**
	 * Realm and powered by string
	 */
	const REALM = 'EGroupware CalDAV/CardDAV/GroupDAV server';

	var $dav_powered_by = self::REALM;
	var $http_auth_realm = self::REALM;

	/**
	 * Folders in root or user home
	 *
	 * @var array
	 */
	var $root = array(
		'addressbook' => array(
			'resourcetype' => array(self::GROUPDAV => 'vcard-collection', self::CARDDAV => 'addressbook'),
			'component-set' => array(self::GROUPDAV => 'VCARD'),
		),
		'calendar' => array(
			'resourcetype' => array(self::GROUPDAV => 'vevent-collection', self::CALDAV => 'calendar'),
			'component-set' => array(self::GROUPDAV => 'VEVENT'),
		),
		'inbox' => array(
			'resourcetype' => array(self::CALDAV => 'schedule-inbox'),
			'app' => 'calendar',
			'user-only' => true,	// display just in user home
		),
		'outbox' => array(
			'resourcetype' => array(self::CALDAV => 'schedule-outbox'),
			'app' => 'calendar',
			'user-only' => true,	// display just in user home
		),
		'infolog' => array(
			'resourcetype' => array(self::GROUPDAV => 'vtodo-collection', self::CALDAV => 'calendar'),
			'component-set' => array(self::GROUPDAV => 'VTODO'),
		),
	);
	/**
	 * Debug level: 0 = nothing, 1 = function calls, 2 = more info, 3 = complete $_SERVER array
	 *
	 * Can now be enabled on a per user basis in GroupDAV prefs, if it is set here to 0!
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
	 * Instance of our application specific handler
	 *
	 * @var groupdav_handler
	 */
	var $handler;
	/**
	 * current-user-principal URL
	 *
	 * @var string
	 */
	var $current_user_principal;
	/**
	 * Reference to the accounts class
	 *
	 * @var accounts
	 */
	var $accounts;
	/**
	 * Supported privileges with name and description
	 *
	 * privileges are hierarchical
	 *
	 * @var array
	 */
	var $supported_privileges = array(
		'all' => array(
			'*description*' => 'all privileges',
			'read' => array(
				'*description*' => 'read resource',
				'read-free-busy' => array(
					'*ns*' => self::CALDAV,
					'*description*' => 'allow free busy report query',
					'*only*' => '/calendar/',
				),
			),
			'write' => array(
				'*description*' => 'write resource',
				'write-properties' => 'write resource properties',
				'write-content' => 'write resource content',
				'bind' => 'add child resource',
				'unbind' => 'remove child resource',
			),
			'unlock' => 'unlock resource without ownership of lock',
			'read-acl' => 'read resource access control list',
			'write-acl' => 'write resource access control list',
			'read-current-user-privilege-set' => 'read privileges for current principal',
			'schedule-deliver' => array(
				'*ns*' => self::CALDAV,
				'*description*' => 'schedule privileges for current principal',
				'*only*' => '/inbox/',
			),
			'schedule-send' => array(
				'*ns*' => self::CALDAV,
				'*description*' => 'schedule privileges for current principal',
				'*only*' => '/outbox/',
			),
		),
	);
	/**
	 * $options parameter to PROPFIND request, eg. to check what props are requested
	 *
	 * @var array
	 */
	var $propfind_options;

	/**
	 * Reference to active instance, used by exception handler
	 *
	 * @var groupdav
	 */
	protected static $instance;

	function __construct()
	{
		if (!$this->debug) $this->debug = (int)$GLOBALS['egw_info']['user']['preferences']['groupdav']['debug_level'];

		if ($this->debug > 2) error_log('groupdav: $_SERVER='.array2string($_SERVER));

		// setting our own exception handler, to be able to still log the requests
		set_exception_handler(array(__CLASS__,'exception_handler'));

		// crrnd: client refuses redundand namespace declarations
		// setting redundand namespaces as the default for (Cal|Card|Group)DAV, as the majority of the clients either require or can live with it
		$this->crrnd = false;

		// identify clients, which do NOT support path AND full url in <D:href> of PROPFIND request
		switch(($agent = groupdav_handler::get_agent()))
		{
			case 'kde':	// KAddressbook (at least in 3.5 can NOT subscribe / does NOT find addressbook)
				$this->client_require_href_as_url = true;
				break;
			case 'cfnetwork':	// Apple addressbook app
			case 'dataaccess':	// iPhone addressbook
				$this->client_require_href_as_url = false;
				break;
			case 'davkit':	// iCal app in OS X 10.6 created wrong request, if full url given
			case 'coredav':	// iCal app in OS X 10.7
			case 'calendarstore':	// Apple iCal 5.0.1 under OS X 10.7.2
				$this->client_require_href_as_url = false;
				break;
			case 'cfnetwork_old':
				$this->crrnd = true; // Older Apple Addressbook.app does not cope with namespace redundancy
				break;
		}
		if ($this->debug) error_log(__METHOD__."() HTTP_USER_AGENT='$_SERVER[HTTP_USER_AGENT]' --> '$agent' --> client_requires_href_as_url=$this->client_require_href_as_url, crrnd(client refuses redundand namespace declarations)=$this->crrnd");

		// adding EGroupware version to X-Dav-Powered-By header eg. "EGroupware 1.8.001 CalDAV/CardDAV/GroupDAV server"
		$this->dav_powered_by = str_replace('EGroupware','EGroupware '.$GLOBALS['egw_info']['server']['versions']['phpgwapi'],
			$this->dav_powered_by);

		parent::HTTP_WebDAV_Server();
		// hack to allow to use query parameters in WebDAV, which HTTP_WebDAV_Server interprets as part of the path
		list($this->_SERVER['REQUEST_URI']) = explode('?',$this->_SERVER['REQUEST_URI']);
		/*if (substr($this->_SERVER['REQUEST_URI'],-13) == '/;add-member/')
		{
			$_GET['add-member'] = '';
			$this->_SERVER['REQUEST_URI'] = substr($this->_SERVER['REQUEST_URI'],0,-12);
		}*/
		//error_log($_SERVER['REQUEST_URI']." --> ".$this->_SERVER['REQUEST_URI']);

		$this->egw_charset = translation::charset();
		if (strpos($this->base_uri, 'http') === 0)
		{
			$this->current_user_principal = $this->_slashify($this->base_uri);
		}
		else
		{
			$this->current_user_principal = (@$_SERVER["HTTPS"] === "on" ? "https:" : "http:") .
				'//' . $_SERVER['HTTP_HOST'] . $_SERVER['SCRIPT_NAME'] . '/';
		}
		$this->current_user_principal .= 'principals/users/'.$GLOBALS['egw_info']['user']['account_lid'].'/';

		// if client requires pathes instead of URLs
		if (!$this->client_require_href_as_url)
		{
			$this->current_user_principal = parse_url($this->current_user_principal,PHP_URL_PATH);
		}
		$this->accounts = $GLOBALS['egw']->accounts;

		self::$instance = $this;
	}

	/**
	 * get the handler for $app
	 *
	 * @param string $app
	 * @return groupdav_handler
	 */
	function app_handler($app)
	{
		if (isset($this->root[$app]['app'])) $app = $this->root[$app]['app'];

		return groupdav_handler::app_handler($app,$this);
	}

	/**
	 * OPTIONS request, allow to modify the standard responses from the pear-class
	 *
	 * @param string $path
	 * @param array &$dav
	 * @param array &$allow
	 */
	function OPTIONS($path, &$dav, &$allow)
	{
		// locking support
		if (!in_array('2', $dav)) $dav[] = '2';

		if (preg_match('#/(calendar(-[^/]+)?|inbox|outbox)/#', $path))	// eg. /<username>/calendar-<otheruser>/
		{
			$app = 'calendar';
		}
		elseif (preg_match('#/addressbook(-[^/]+)?/#', $path))	// eg. /<username>/addressbook-<otheruser>/
		{
			$app = 'addressbook';
		}
		// CalDAV and CardDAV
		$dav[] = 'access-control';

		if ($app !== 'addressbook')	// CalDAV
		{
			$dav[] = 'calendar-access';
			$dav[] = 'calendar-auto-schedule';
			$dav[] = 'calendar-proxy';
			// required by iOS iCal to use principal-property-search to autocomplete participants (and locations)
			$dav[] = 'calendarserver-principal-property-search';
			// required by iOS & OS X iCal to show private checkbox (X-CALENDARSERVER-ACCESS: CONFIDENTIAL on VCALENDAR)
			$dav[] = 'calendarserver-private-events';
			// managed attachments
			$dav[] = 'calendar-managed-attachments';
			// other capabilities calendarserver announces
			//$dav[] = 'calendar-schedule';
			//$dav[] = 'calendar-availability';
			//$dav[] = 'inbox-availability';
			//$dav[] = 'calendarserver-private-comments';
			//$dav[] = 'calendarserver-sharing';
			//$dav[] = 'calendarserver-sharing-no-scheduling';
		}
		if ($app !== 'calendar')	// CardDAV
		{
			$dav[] = 'addressbook';	// CardDAV uses "addressbook" NOT "addressbook-access"
		}
		//error_log(__METHOD__."('$path') --> app='$app' --> DAV: ".implode(', ', $dav));
	}

	/**
	 * PROPFIND and REPORT method handler
	 *
	 * @param  array  general parameter passing array
	 * @param  array  return array for file properties
	 * @return bool   true on success
	 */
	function PROPFIND(&$options, &$files, $method='PROPFIND')
	{
		if ($this->debug) error_log(__CLASS__."::$method(".array2string($options).')');

		// make options (readonly) available to all class methods, eg. prop_requested
		$this->propfind_options = $options;

		// parse path in form [/account_lid]/app[/more]
		if (!self::_parse_path($options['path'],$id,$app,$user,$user_prefix) && $app && !$user && $user !== 0)
		{
			if ($this->debug > 1) error_log(__CLASS__."::$method: user='$user', app='$app', id='$id': 404 not found!");
			return '404 Not Found';
		}
		if ($this->debug > 1) error_log(__CLASS__."::$method(path='$options[path]'): user='$user', user_prefix='$user_prefix', app='$app', id='$id'");

		$files = array('files' => array());
		$path = $user_prefix = $this->_slashify($user_prefix);

		if (!$app)	// user root folder containing apps
		{
			// add root with current users apps
			$this->add_home($files, $path, $user, $options['depth']);

			// add principals and user-homes
			if ($path == '/' && $options['depth'])
			{
				// principals collection
				$files['files'][] = $this->add_collection('/principals/', array(
					'displayname' => lang('Accounts'),
				));
				foreach($this->accounts->search(array('type' => 'both','order'=>'account_lid')) as $account)
				{
					$this->add_home($files, $path.$account['account_lid'].'/', $account['account_id'], $options['depth'] == 'infinity' ? 'infinity' : $options['depth']-1);
				}
			}
			return true;
		}
		if ($path == '/' && ($app == 'resources' || $app == 'locations'))
		{
			return $this->add_resources_collection($files, '/'.$app.'/', $options['depth']);
		}
		if ($app != 'principals' && !isset($GLOBALS['egw_info']['user']['apps'][$this->root[$app]['app'] ? $this->root[$app]['app'] : $app]))
		{
			if ($this->debug) error_log(__CLASS__."::$method(path=$options[path]) 403 Forbidden: no app rights for '$app'");
			return "403 Forbidden: no app rights for '$app'";	// no rights for the given app
		}
		if (($handler = self::app_handler($app)))
		{
			if ($method != 'REPORT' && !$id)	// no self URL for REPORT requests (only PROPFIND) or propfinds on an id
			{
				// KAddressbook doubles the folder, if the self URL contains the GroupDAV/CalDAV resourcetypes
				$files['files'][0] = $this->add_app($app,$app=='addressbook'&&$handler->get_agent()=='kde',$user,
					$this->_slashify($options['path']));

				// Hack for iOS 5.0.1 addressbook to stop asking directory gateway permissions with depth=1
				if ($method == 'PROPFIND' && $options['path'] == '/addressbook/' && $handler->get_agent() == 'dataaccess')
				{
					$this->log(__CLASS__."::$method(".array2string($options).') Enabling hack for iOS 5.0.1 addressbook: force Depth: 0 on PROPFIND for directory gateway!');
					return true;
				}
				if (!$options['depth']) return true;	// depth 0 --> show only the self url
			}
			return $handler->propfind($this->_slashify($options['path']),$options,$files,$user,$id);
		}
		return '501 Not Implemented';
	}

	/**
	 * Add a collection to a PROPFIND request
	 *
	 * @param string $path
	 * @param array $props=array() extra properties 'resourcetype' is added anyway, name => value pairs or name => HTTP_WebDAV_Server([namespace,]name,value)
	 * @param array $privileges=array('read') values for current-user-privilege-set
	 * @param array $supported_privileges=null default $this->supported_privileges
	 * @return array with values for keys 'path' and 'props'
	 */
	public function add_collection($path, array $props = array(), array $privileges=array('read','read-acl','read-current-user-privilege-set'), array $supported_privileges=null)
	{
		// resourcetype: collection
		$props['resourcetype'][] = self::mkprop('collection','');

		if (!isset($props['getcontenttype'])) $props['getcontenttype'] = 'httpd/unix-directory';

		return $this->add_resource($path, $props, $privileges, $supported_privileges);
	}

	/**
	 * Add a resource to a PROPFIND request
	 *
	 * @param string $path
	 * @param array $props=array() extra properties 'resourcetype' is added anyway, name => value pairs or name => HTTP_WebDAV_Server([namespace,]name,value)
	 * @param array $privileges=array('read') values for current-user-privilege-set
	 * @param array $supported_privileges=null default $this->supported_privileges
	 * @return array with values for keys 'path' and 'props'
	 */
	public function add_resource($path, array $props = array(), array $privileges=array('read','read-current-user-privilege-set'), array $supported_privileges=null)
	{
		// props for all collections: current-user-principal and principal-collection-set
		$props['current-user-principal'] = array(
			self::mkprop('href',$this->current_user_principal));
		$props['principal-collection-set'] = array(
			self::mkprop('href',$this->base_uri.'/principals/'));

		// required props per WebDAV standard
		foreach(array(
			'displayname'      => basename($path),
			'getetag'          => 'none',
			'getcontentlength' => '',
			'getlastmodified'  => '',
			'getcontenttype'   => '',
			'resourcetype'     => '',
		) as $name => $default)
		{
			if (!isset($props[$name])) $props[$name] = $default;
		}

		// if requested add privileges
		if (is_null($supported_privileges)) $supported_privileges = $this->supported_privileges;
		if ($this->prop_requested('current-user-privilege-set') === true)
		{
			foreach($privileges as $name)
			{
				$props['current-user-privilege-set'][] = self::mkprop('privilege', array(
					is_array($name) ? self::mkprop($name['ns'], $name['name'], '') : self::mkprop($name, '')));
			}
		}
		if ($this->prop_requested('supported-privilege-set') === true)
		{
			foreach($supported_privileges as $name => $data)
			{
				$props['supported-privilege-set'][] = $this->supported_privilege($name, $data, $path);
			}
		}
		if (!isset($props['owner']) && $this->prop_requested('owner') === true)
		{
			$props['owner'] = '';
		}

		if ($this->debug > 1) error_log(__METHOD__."(path='$path', props=".array2string($props).')');

		// convert simple associative properties to HTTP_WebDAV_Server ones
		foreach($props as $name => &$prop)
		{
			if (!is_array($prop) || !isset($prop['name']))
			{
				$prop = self::mkprop($name, $prop);
			}
			// add quotes around etag, if they are not already there
			if ($prop['name'] == 'getetag' && $prop['val'][0] != '"')
			{
				$prop['val'] = '"'.$prop['val'].'"';
			}
		}

		return array(
			'path' => $path,
			'props' => $props,
		);
	}

	/**
	 * Generate (hierachical) supported-privilege property
	 *
	 * @param string $name name of privilege
	 * @param string|array $data string with describtion or array with agregated privileges plus value for key '*description*', '*ns*', '*only*'
	 * @param string $path=null path to match with $data['*only*']
	 * @return array of self::mkprop() arrays
	 */
	protected function supported_privilege($name, $data, $path=null)
	{
		$props = array();
		$props[] = self::mkprop('privilege', array(is_array($data) && $data['*ns*'] ?
			self::mkprop($data['*ns*'], $name, '') : self::mkprop($name, '')));
		$props[] = self::mkprop('description', is_array($data) ? $data['*description*'] : $data);
		if (is_array($data))
		{
			foreach($data as $name => $data)
			{
				if ($name[0] == '*') continue;
				if (is_array($data) && $data['*only*'] && strpos($path, $data['*only*']) === false)
				{
					continue;	// wrong path
				}
				$props[] = $this->supported_privilege($name, $data, $path);
			}
		}
		return self::mkprop('supported-privilege', $props);
	}

	/**
	 * Checks if a given property was requested in propfind request
	 *
	 * @param string $name property name
	 * @param string $ns=null namespace, if that is to be checked too
	 * @param boolean $return_prop=false if true return the property array with values for 'name', 'xmlns', 'attrs', 'children'
	 * @return boolean|string|array true: $name explicitly requested (or autoindex), "all": allprop or "names": propname requested, false: $name was not requested
	 */
	function prop_requested($name, $ns=null, $return_prop=false)
	{
		if (!is_array($this->propfind_options) || !isset($this->propfind_options['props']))
		{
			$ret = true;	// no props set, should happen only in autoindex, we return true to show all available props
		}
		elseif (!is_array($this->propfind_options['props']))
		{
			$ret = $this->propfind_options['props'];	// "all": allprop or "names": propname
		}
		else
		{
			$ret = false;
			foreach($this->propfind_options['props'] as $prop)
			{
				if ($prop['name'] == $name && (is_null($ns) || $prop['xmlns'] == $ns))
				{
					$ret = $return_prop ? $prop : true;
					break;
				}
			}
		}
		//error_log(__METHOD__."('$name', '$ns', $return_prop) propfind_options=".array2string($this->propfind_options));
		return $ret;
	}

	/**
	 * Add user home with addressbook, calendar, infolog
	 *
	 * @param array $files
	 * @param string $path / or /<username>/
	 * @param int $user
	 * @param int $depth
	 * @return string|boolean http status or true|false
	 */
	protected function add_home(array &$files, $path, $user, $depth)
	{
		if ($user)
		{
			$account_lid = $this->accounts->id2name($user);
		}
		else
		{
			$account_lid = $GLOBALS['egw_info']['user']['account_lid'];
		}
		$account = $this->accounts->read($account_lid);

		$calendar_user_address_set = array(
			self::mkprop('href','urn:uuid:'.$account['account_lid']),
		);
		if ($user < 0)
		{
			$principalType = 'groups';
			$displayname = lang('Group').' '.$account['account_lid'];
		}
		else
		{
			$principalType = 'users';
			$displayname = $account['account_fullname'];
			$calendar_user_address_set[] = self::mkprop('href','MAILTO:'.$account['account_email']);
		}
		$calendar_user_address_set[] = self::mkprop('href',$this->base_uri.'/principals/'.$principalType.'/'.$account['account_lid'].'/');

		if ($depth && $path == '/')
		{
			$displayname = 'EGroupware (Cal|Card|Group)DAV server';
		}

		$displayname = translation::convert($displayname, translation::charset(),'utf-8');
		// self url
		$props = array(
			'displayname' => $displayname,
			'owner' => $path == '/' ? '' : array(self::mkprop('href',$this->base_uri.'/principals/'.$principalType.'/'.$account_lid.'/')),
		);

		if ($path != '/')
		{
			// add props modifyable via proppatch from client, eg. jqcalendar stores it's preferences there
			foreach((array)$GLOBALS['egw_info']['user']['preferences']['groupdav'] as $name => $value)
			{
				list($prop,$prop4path,$ns) = explode(':', $name, 3);
				if ($prop4path == $path && (!in_array($ns,self::$ns_needs_explicit_named_props) ||
					isset(self::$proppatch_props[$prop]) && self::$proppatch_props[$prop] === $ns))
				{
					$props[] = self::mkprop($ns, $prop, $value);
					//error_log(__METHOD__."() arbitrary $ns:$prop=".array2string($value));
				}
			}
		}
		$files['files'][] = $this->add_collection($path, $props);

		if ($depth)
		{
			foreach($this->root as $app => $data)
			{
				if (!$GLOBALS['egw_info']['user']['apps'][$data['app'] ? $data['app'] : $app]) continue;	// no rights for the given app
				if (!empty($data['user-only']) && ($path == '/' || $user < 0)) continue;

				$files['files'][] = $this->add_app($app,false,$user,$path.$app.'/');

				// only add global /addressbook-accounts/ as the one in home-set is added (and controled) by add_shared
				if ($path == '/' && $app == 'addressbook' &&
					!$GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'])
				{
					$file = $this->add_app($app,false,0,$path.$app.'-accounts/');
					$file['props']['resourcetype']['val'][] = self::mkprop(self::CALENDARSERVER,'shared','');
					$files['files'][] = $file;
				}
				// added shared calendars or addressbooks
				$this->add_shared($files['files'], $path, $app, $user);
			}
			if ($path == '/' && $GLOBALS['egw_info']['user']['apps']['resources'])
			{
				$this->add_resources_collection($files, $path.'resources/');
				$this->add_resources_collection($files, $path.'locations/');
			}
		}
		return true;
	}

	/**
	 * Add collection with available resources or locations calendar-home-sets
	 *
	 * @param array &$files
	 * @param string $path / or /<username>/
	 * @param int $depth=0
	 * @return string|boolean http status or true|false
	 */
	protected function add_resources_collection(array &$files, $path, $depth=0)
	{
		if (!isset($GLOBALS['egw_info']['user']['apps']['resources']))
		{
			if ($this->debug) error_log(__CLASS__."::$method(path=$path) 403 Forbidden: no app rights for 'resources'");
			return "403 Forbidden: no app rights for 'resources'";	// no rights for the given app
		}
		list(,$what) = explode('/', $path);
		if (($is_location = ($what == 'locations')))
		{
			$files['files'][] = $this->add_collection('/locations/', array('displayname' => lang('Location calendars')));
		}
		else
		{
			$files['files'][] = $this->add_collection('/resources/', array('displayname' => lang('Resource calendars')));
		}
		if ($depth)
		{
			foreach(groupdav_principals::get_resources() as $res_id => $resource)
			{
				if ($is_location == groupdav_principals::resource_is_location($resource))
				{
					$files['files'][] = $this->add_app('calendar', false, 'r'.$resource['res_id'],
						'/'.groupdav_principals::resource2name($resource, $is_location).'/');
				}
			}
		}
		return true;
	}

	/**
	 * Add shared addressbook, calendar, infolog to user home
	 *
	 * @param array &$files
	 * @param string $path /<username>/
	 * @param int $app
	 * @param int $user
	 * @return string|boolean http status or true|false
	 */
	protected function add_shared(array &$files, $path, $app, $user)
	{
		// currently only show shared calendars/addressbooks for current user and not in the root
		if ($path == '/' || $user != $GLOBALS['egw_info']['user']['account_id'] ||
			!isset($GLOBALS['egw_info']['user']['apps'][$app]))	// also avoids principals, inbox and outbox
		{
			return true;
		}
		$handler = $this->app_handler($app);
		if (($shared = $handler->get_shared()))
		{
			foreach($shared as $id => $owner)
			{
				$file = $this->add_app($app,false,$id,$path.$owner.'/');
				// mark other users calendar as shared (iOS 5.0.1 AB does NOT display AB marked as shared!)
				if ($app == 'calendar') $file['props']['resourcetype']['val'][] = self::mkprop(self::CALENDARSERVER,'shared','');
				$files[] = $file;
			}
		}
		return true;
	}

	/**
	 * Format an account-name for use in displayname
	 *
	 * @param int|array $account
	 * @return string
	 */
	public function account_name($account)
	{
		if (is_array($account))
		{
			if ($account['account_id'] < 0)
			{
				$name = lang('Group').' '.$account['account_lid'];
			}
			else
			{
				$name = $account['account_fullname'];
			}
		}
		else
		{
			if ($account < 0)
			{
				$name = lang('Group').' '.$this->accounts->id2name($account,'account_lid');
			}
			else
			{
				$name = $this->accounts->id2name($account,'account_fullname');
			}
			if (empty($name)) $name = '#'.$account;
		}
		return $name;
	}

	/**
	 * Add an application collection to a user home or the root
	 *
	 * @param string $app
	 * @param boolean $no_extra_types=false should the GroupDAV and CalDAV types be added (KAddressbook has problems with it in self URL)
	 * @param int $user=null owner of the collection, default current user
	 * @param string $path='/'
	 * @return array with values for keys 'path' and 'props'
	 */
	protected function add_app($app,$no_extra_types=false,$user=null,$path='/')
	{
		if ($this->debug) error_log(__METHOD__."(app='$app', no_extra_types=$no_extra_types, user='$user', path='$path')");
		$user_preferences = $GLOBALS['egw_info']['user']['preferences'];
		if (is_string($user) && $user[0] == 'r' && ($resource = groupdav_principals::read_resource(substr($user, 1))))
		{
			$is_location = groupdav_principals::resource_is_location($resource);
			list($principalType, $account_lid) = explode('/', groupdav_principals::resource2name($resource, $is_location, $displayname));
		}
		elseif ($user)
		{
			$account_lid = $this->accounts->id2name($user);
			if ($user >= 0 && $GLOBALS['egw']->preferences->account_id != $user)
			{
				$GLOBALS['egw']->preferences->__construct($user);
				$user_preferences = $GLOBALS['egw']->preferences->read_repository();
				$GLOBALS['egw']->preferences->__construct($GLOBALS['egw_info']['user']['account_lid']);
			}
			$principalType = $user < 0 ? 'groups' : 'users';
		}
		else
		{
			$account_lid = $GLOBALS['egw_info']['user']['account_lid'];
			$principalType = 'users';
		}
		if (!isset($displayname)) $displayname = $this->account_name($user);

		$props = array(
			'owner' => array(self::mkprop('href',$this->base_uri.'/principals/'.$principalType.'/'.$account_lid.'/')),
		);

		switch ($app)
		{
			case 'inbox':
				$props['displayname'] = lang('Scheduling inbox').' '.$displayname;
				break;
			case 'outbox':
				$props['displayname'] = lang('Scheduling outbox').' '.$displayname;
				break;
			case 'addressbook':
				if ($path == '/addressbook/')
				{
					$props['displayname'] = lang('All addressbooks');
					break;
				}
				elseif(!$user && !$GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'])
				{
					unset($props['owner']);
					$props['displayname'] = lang($app).' '.lang('Accounts');
					break;
				}
				// fall through
			default:
				$props['displayname'] = translation::convert(lang($app).' '.$displayname, $this->egw_charset, 'utf-8');
		}

		// rfc 5995 (Use POST to add members to WebDAV collections): we use collection path with add-member query param
		// leaving it switched off, until further testing, because OS X iCal seem to ignore it and OS X Addressbook uses POST to full URL without ?add-member
		if ($app && !in_array($app,array('inbox','outbox','principals')))	// not on inbox, outbox or principals
		{
			$props['add-member'][] = self::mkprop('href',$this->base_uri.$path.'?add-member');
		}

		// add props modifyable via proppatch from client, eg. calendar-color, see self::$proppatch_props
		foreach((array)$GLOBALS['egw_info']['user']['preferences'][$app] as $name => $value)
		{
			unset($ns);
			list($prop,$prop4user,$ns) = explode(':', $name, 3);
			if ($prop4user == (string)$user && isset(self::$proppatch_props[$prop]) && !isset($ns))
			{
				$props[$prop] = self::mkprop(self::$proppatch_props[$prop], $prop, $value);
				//error_log(__METHOD__."() explicit ".self::$proppatch_props[$prop].":$prop=".array2string($value));
			}
			// props in arbitrary namespaces not mentioned in self::$ns_needs_explicit_named_props
			elseif(isset($ns) && !in_array($ns,self::$ns_needs_explicit_named_props))
			{
				$props[] = self::mkprop($ns, $prop, $value);
				//error_log(__METHOD__."() arbitrary $ns:$prop=".array2string($value));
			}
		}

		foreach((array)$this->root[$app] as $prop => $values)
		{
			switch($prop)
			{
				case 'resourcetype';
					if (!$no_extra_types)
					{
						foreach($this->root[$app]['resourcetype'] as $ns => $type)
						{
							$props['resourcetype'][] = self::mkprop($ns,$type,'');
						}
						// add /addressbook/ as directory gateway
						if ($path == '/addressbook/')
						{
							$props['resourcetype'][] = self::mkprop(self::CARDDAV, 'directory', '');
						}
					}
					break;
				case 'app':
				case 'user-only':
					break;	// no props, already handled
				default:
					if (is_array($values))
					{
						foreach($values as $ns => $value)
						{
							$props[$prop] = self::mkprop($ns,$prop,$value);
						}
					}
					else
					{
						$props[$prop] = $values;
					}
					break;
			}
		}
		// add other handler specific properties
		if (($handler = self::app_handler($app)))
		{
			if (method_exists($handler,'extra_properties'))
			{
				$props = $handler->extra_properties($props, $displayname, $this->base_uri, $user, $path);
			}
			// add ctag if handler implements it
			if (method_exists($handler,'getctag') && $this->prop_requested('getctag') === true)
			{
				$props['getctag'] = self::mkprop(
					groupdav::CALENDARSERVER,'getctag',$handler->getctag($path,$user));
			}
			// add sync-token url if handler supports sync-collection report
			if (isset($props['supported-report-set']['sync-collection']) && $this->prop_requested('sync-token') === true)
			{
				$props['sync-token'] = $handler->get_sync_token($path,$user);
			}
		}
		if ($handler && !is_null($user))
		{
			return $this->add_collection($path, $props, $handler->current_user_privileges($path, $user));
		}
		return $this->add_collection($path, $props);
	}

	/**
	 * CalDAV/CardDAV REPORT method handler
	 *
	 * just calls PROPFIND()
	 *
	 * @param  array  general parameter passing array
	 * @param  array  return array for file properties
	 * @return bool   true on success
	 */
	function REPORT(&$options, &$files)
	{
		if ($this->debug > 1) error_log(__METHOD__.'('.array2string($options).')');

		return $this->PROPFIND($options,$files,'REPORT');
	}

	/**
	 * CalDAV/CardDAV REPORT method handler to get HTTP_WebDAV_Server to process REPORT requests
	 *
	 * Just calls http_PROPFIND()
	 */
	function http_REPORT()
	{
		parent::http_PROPFIND('REPORT');
	}

	/**
	 * GET method handler
	 *
	 * @param  array $options parameter passing array
	 * @return bool   true on success
	 */
	function GET(&$options)
	{
		if ($this->debug) error_log(__METHOD__.'('.array2string($options).')');

		if (!$this->_parse_path($options['path'],$id,$app,$user) || $app == 'principals')
		{
			return $this->autoindex($options);
		}
		if (($handler = self::app_handler($app)))
		{
			return $handler->get($options,$id,$user);
		}
		error_log(__METHOD__."(".array2string($options).") 501 Not Implemented");
		return '501 Not Implemented';
	}

	/**
	 * Display an automatic index (listing and properties) for a collection
	 *
	 * @param array $options parameter passing array, index "path" contains requested path
	 */
	protected function autoindex($options)
	{
		$propfind_options = array(
			'path'  => $options['path'],
			'depth' => 1,
		);
		$files = array();
		if (($ret = $this->PROPFIND($propfind_options,$files)) !== true)
		{
			return $ret;	// no collection
		}
		header('Content-type: text/html; charset='.translation::charset());
		echo "<html>\n<head>\n\t<title>".'EGroupware (Cal|Card|Group)DAV server '.htmlspecialchars($options['path'])."</title>\n";
		echo "\t<meta http-equiv='content-type' content='text/html; charset=utf-8' />\n";
		echo "\t<style type='text/css'>\n.th { background-color: #e0e0e0; }\n.row_on { background-color: #F1F1F1; vertical-align: top; }\n".
			".row_off { background-color: #ffffff; vertical-align: top; }\ntd { padding-left: 5px; }\nth { padding-left: 5px; text-align: left; }\n\t</style>\n";
		echo "</head>\n<body>\n";

		echo '<h1>(Cal|Card|Group)DAV ';
		$path = '/groupdav.php';
		foreach(explode('/',$this->_unslashify($options['path'])) as $n => $name)
		{
			$path .= ($n != 1 ? '/' : '').$name;
			echo html::a_href(htmlspecialchars($name.'/'),$path);
		}
		echo "</h1>\n";

		static $props2show = array(
			'DAV:displayname'      => 'Displayname',
			'DAV:getlastmodified'  => 'Last modified',
			'DAV:getetag'          => 'ETag',
			//'CalDAV:schedule-tag'  => 'Schedule-Tag',
			'DAV:getcontenttype'   => 'Content type',
			'DAV:resourcetype'     => 'Resource type',
			//'http://calendarserver.org/ns/:created-by' => 'Created by',
			//'http://calendarserver.org/ns/:updated-by' => 'Updated by',
			//'DAV:owner'            => 'Owner',
			//'DAV:current-user-privilege-set' => 'current-user-privilege-set',
			//'DAV:getcontentlength' => 'Size',
			//'DAV:sync-token' => 'sync-token',
		);
		$n = 0;
		foreach($files['files'] as $file)
		{
			if (!isset($collection_props))
			{
				$collection_props = $this->props2array($file['props']);
				echo '<h3>'.lang('Collection listing').': '.htmlspecialchars($collection_props['DAV:displayname'])."</h3>\n";
				continue;	// own entry --> displaying properies later
			}
			if(!$n++)
			{
				echo "<table>\n\t<tr class='th'>\n\t\t<th>#</th>\n\t\t<th>".lang('Name')."</th>";
				foreach($props2show as $label) echo "\t\t<th>".lang($label)."</th>\n";
				echo "\t</tr>\n";
			}
			$props = $this->props2array($file['props']);
			//echo $file['path']; _debug_array($props);
			$class = $class == 'row_on' ? 'row_off' : 'row_on';

			if (substr($file['path'],-1) == '/')
			{
				$name = basename(substr($file['path'],0,-1)).'/';
			}
			else
			{
				$name = basename($file['path']);
			}

			echo "\t<tr class='$class'>\n\t\t<td>$n</td>\n\t\t<td>".
				html::a_href(htmlspecialchars($name),'/groupdav.php'.strtr($file['path'], array(
					'%' => '%25',
					'#' => '%23',
					'?' => '%3F',
				)))."</td>\n";
			foreach($props2show as $prop => $label)
			{
				echo "\t\t<td>".($prop=='DAV:getlastmodified'&&!empty($props[$prop])?date('Y-m-d H:i:s',$props[$prop]):$props[$prop])."</td>\n";
			}
			echo "\t</tr>\n";
		}
		if (!$n)
		{
			echo '<p>'.lang('Collection empty.')."</p>\n";
		}
		else
		{
			echo "</table>\n";
		}
		echo '<h3>'.lang('Properties')."</h3>\n";
		echo "<table>\n\t<tr class='th'><th>".lang('Namespace')."</th><th>".lang('Name')."</th><th>".lang('Value')."</th></tr>\n";
		foreach($collection_props as $name => $value)
		{
			$class = $class == 'row_on' ? 'row_off' : 'row_on';
			$ns = explode(':',$name);
			$name = array_pop($ns);
			$ns = implode(':',$ns);
			echo "\t<tr class='$class'>\n\t\t<td>".htmlspecialchars($ns)."</td><td style='white-space: nowrap'>".htmlspecialchars($name)."</td>\n";
			echo "\t\t<td>".$value."</td>\n\t</tr>\n";
		}
		echo "</table>\n";
		$dav = array(1);
		$allow = false;
		$this->OPTIONS($options['path'], $dav, $allow);
		echo "<p>DAV: ".implode(', ', $dav)."</p>\n";

		echo "</body>\n</html>\n";

		common::egw_exit();
	}

	/**
	 * Format a property value for output
	 *
	 * @param mixed $value
	 * @return string
	 */
	protected function prop_value($value)
	{
		if (is_array($value))
		{
			if (isset($value[0]['ns']))
			{
				$value = $this->_hierarchical_prop_encode($value);
			}
			$value = array2string($value);
		}
		if ($value[0] == '<' && function_exists('tidy_repair_string'))
		{
			$value = tidy_repair_string($value, array(
				'indent'          => true,
				'show-body-only'  => true,
				'output-encoding' => 'utf-8',
				'input-encoding'  => 'utf-8',
				'input-xml'       => true,
				'output-xml'      => true,
				'wrap'            => 0,
			));
		}
		if (($href=preg_match('/\<(D:)?href\>[^<]+\<\/(D:)?href\>/i',$value)))
		{
			$value = preg_replace('/\<(D:)?href\>('.preg_quote($this->base_uri.'/','/').')?([^<]+)\<\/(D:)?href\>/i','<\\1href><a href="\\2\\3">\\3</a></\\4href>',$value);
		}
		$value = $value[0] == '<'  || strpos($value, "\n") !== false ? '<pre>'.htmlspecialchars($value).'</pre>' : htmlspecialchars($value);

		if ($href)
		{
			$value = preg_replace('/&lt;a href=&quot;(.+)&quot;&gt;/', '<a href="\\1">', $value);
			$value = str_replace('&lt;/a&gt;', '</a>', $value);
		}
		return $value;
	}

	/**
	 * Return numeric indexed array with values for keys 'ns', 'name' and 'val' as array 'ns:name' => 'val'
	 *
	 * @param array $props
	 * @return array
	 */
	protected function props2array(array $props)
	{
		$arr = array();
		foreach($props as $prop)
		{
			$ns_hash = array('DAV:' => 'D');
			switch($prop['ns'])
			{
				case 'DAV:';
					$ns = 'DAV';
					break;
				case self::CALDAV:
					$ns = $ns_hash[$prop['ns']] = 'CalDAV';
					break;
				case self::CARDDAV:
					$ns = $ns_hash[$prop['ns']] = 'CardDAV';
					break;
				case self::GROUPDAV:
					$ns = $ns_hash[$prop['ns']] = 'GroupDAV';
					break;
				default:
					$ns = $prop['ns'];
			}
			if (is_array($prop['val']))
			{
				$prop['val'] = $this->_hierarchical_prop_encode($prop['val'], $prop['ns'], $ns_defs='', $ns_hash);
				// hack to show real namespaces instead of not (visibly) defined shortcuts
				unset($ns_hash['DAV:']);
				$value = strtr($v=$this->prop_value($prop['val']),array_flip($ns_hash));
			}
			else
			{
				$value = $this->prop_value($prop['val']);
			}
			$arr[$ns.':'.$prop['name']] = $value;
		}
		return $arr;
	}

	/**
	 * POST method handler
	 *
	 * @param  array  parameter passing array
	 * @return bool   true on success
	 */
	function POST(&$options)
	{
		// for some reason OS X Addressbook (CFNetwork user-agent) uses now (DAV:add-member given with collection URL+"?add-member")
		// POST to the collection URL plus a UID like name component (like for regular PUT) to create new entrys
		if (isset($_GET['add-member']) || groupdav_handler::get_agent() == 'cfnetwork')
		{
			$_GET['add-member'] = '';	// otherwise we give no Location header
			return $this->PUT($options);
		}
		if ($this->debug) error_log(__METHOD__.'('.array2string($options).')');

		$this->_parse_path($options['path'],$id,$app,$user);

		if (($handler = self::app_handler($app)))
		{
			// managed attachments
			if (isset($_GET['action']) && substr($_GET['action'], 0, 11) === 'attachment-')
			{
				return $this->managed_attachements($options, $id, $handler, $_GET['action']);
			}

			if (method_exists($handler, 'post'))
			{
				// read the content in a string, if a stream is given
				if (isset($options['stream']))
				{
					$options['content'] = '';
					while(!feof($options['stream']))
					{
						$options['content'] .= fread($options['stream'],8192);
					}
				}
				return $handler->post($options,$id,$user);
			}
		}
		return '501 Not Implemented';
	}

	/**
	 * HTTP header containing managed id
	 */
	const MANAGED_ID_HEADER = 'Cal-Managed-ID';

	/**
	 * Add, update or remove attachments
	 *
	 * @param array &$options
	 * @param string|int $id
	 * @param groupdav_handler $handler
	 * @param string $action 'attachment-add', 'attachment-update', 'attachment-remove'
	 * @return string http status
	 *
	 * @todo support for rid parameter
	 * @todo managed-id does NOT change on update
	 * @todo updates of attachments through vfs need to call $handler->update_tags($id) too
	 */
	protected function managed_attachements(&$options, $id, groupdav_handler $handler, $action)
	{
		error_log(__METHOD__."(path=$options[path], id=$id, ..., action=$action) _GET=".array2string($_GET));
		$entry = $handler->_common_get_put_delete('GET', $options, $id);

		if (!is_array($entry))
		{
			return $entry ? $entry : "404 Not found";
		}

		if (!egw_link::file_access($handler->app, $entry['id'], EGW_ACL_EDIT))
		{
			return '403 Forbidden';
		}

		switch($action)
		{
			case 'attachment-add':
				if (isset($this->_SERVER['HTTP_CONTENT_DISPOSITION']) &&
					substr($this->_SERVER['HTTP_CONTENT_DISPOSITION'], 0, 10) === 'attachment' &&
					preg_match('/filename="?([^";]+)/', $this->_SERVER['HTTP_CONTENT_DISPOSITION'], $matches))
				{
					$filename = egw_vfs::basename($matches[1]);
				}
				if (!($to = self::fopen_attachment($handler->app, $handler->get_id($entry), $filename, $this->_SERVER['CONTENT_TYPE'], $path)) ||
					isset($options['stream']) && ($copied=stream_copy_to_stream($options['stream'], $to)) === false ||
					isset($options['content']) && ($copied=fwrite($to, $options['content'])) === false)
				{
					return '403 Forbidden';
				}
				fclose($to);
				error_log(__METHOD__."() content-type=$options[content_type], filename=$filename: $path created $copied bytes copied");
				$ret = '201 Created';
				header(self::MANAGED_ID_HEADER.': '.self::path2managed_id($path));
				header('Location: '.self::path2location($path));
				break;

			case 'attachment-remove':
			case 'attachment-update':
				if (empty($_GET['managed-id']) || !($path = self::managed_id2path($_GET['managed-id'], $app, $id)))
				{
					return '404 Not found';
				}
				if ($action == 'attachment-remove')
				{
					if (!egw_vfs::unlink($path))
					{
						return '403 Forbidden';
					}
					$ret = '204 No content';
				}
				else
				{
					if (!($to = egw_vfs::fopen($path, 'w')) ||
						isset($options['stream']) && ($copied=stream_copy_to_stream($options['stream'], $to)) === false ||
						isset($options['content']) && ($copied=fwrite($to, $options['content'])) === false)
					{
						return '403 Forbidden';
					}
					fclose($to);
					error_log(__METHOD__."() content-type=$options[content_type], filename=$filename: $path updated $copied bytes copied");
					$ret = '200 Ok';
					header(self::MANAGED_ID_HEADER.': '.self::path2managed_id($path));
				}
				break;

			default:
				return '501 Unknown action parameter '.$action;
		}
		// update etag/ctag/sync-token by updating modification time
		$handler->update_tags($entry);

		// check/handle Prefer: return-representation
		$handler->check_return_representation($options, $id, $user);

		return $ret;
	}

	/**
	 * Handle ATTACH attribute on importing iCals
	 *
	 * - turn inline attachments into managed attachments
	 * - delete NOT included attachments, $delete_via_put is true
	 * @todo: store URLs not from our managed attachments
	 *
	 * @param string $app eg. 'calendar'
	 * @param int|string $id
	 * @param array $attach array of array with values for keys 'name', 'params', 'value'
	 * @param boolean $delete_via_put
	 */
	public static function handle_attach($app, $id, $attach, $delete_via_put=false)
	{
		error_log(__METHOD__."('$app', $id, attach=".array2string($attach).", delete_via_put=".array2string($delete_via_put).')');

		if (!egw_link::file_access($app, $id, EGW_ACL_EDIT))
		{
			error_log(__METHOD__."('$app', $id, ...) no rights to update attachments");
			return;	// no rights --> nothing to do
		}
		if (!is_array($attach)) $attach = array();	// could be PEAR_Error if not set

		if ($delete_via_put)
		{
			foreach(egw_vfs::find(egw_link::vfs_path($app, $id, '', true), array('type' => 'F')) as $path)
			{
				$found = false;
				foreach($attach as $key => $attr)
				{
					if ($attr['params']['MANAGED-ID'] === self::path2managed_id($path))
					{
						$found = true;
						unset($attach[$key]);
						break;
					}
				}
				if (!$found)
				{
					$ok = egw_vfs::unlink($path);
					error_log(__METHOD__."('$app', $id, ...) egw_vfs::unlink('$path') returned ".array2string($ok));
				}
			}
		}
		// turn inline attachments into managed ones
		foreach($attach as $key => $attr)
		{
			if ($attr['params']['VALUE'] === 'BINARY')
			{
				if (!($to = self::fopen_attachment($app, $id, $filename=$attr['params']['FILENAME'], $attr['params']['FMTTYPE'], $path)) ||
					($copied=fwrite($to, $attr['value'])) === false)
				{
					error_log(__METHOD__."('$app', $id, ...) failed to add attachment ".array2string($attr).") ");
					continue;
				}
				fclose($to);
				error_log(__METHOD__."('$app', $id, ...)) content-type={$attr['params']['FMTTYPE']}, filename=$filename: $path created $copied bytes copied");
			}
			else
			{
				error_log(__METHOD__."('$app', $id, ...) unsupported URI attachment ".array2string($attr));
			}
		}
	}

	/**
	 * Open attachment for writing
	 *
	 * @param string $app
	 * @param int|string $id
	 * @param string $filename defaults to 'attachment'
	 * @param string $mime=null mime-type to generate extension
	 * @param string &$path=null on return path opened
	 * @return resource
	 */
	protected static function fopen_attachment($app, $id, $filename, $mime=null, &$path=null)
	{
		$filename = empty($filename) ? 'attachment' : egw_vfs::basename($filename);

		if (strpos($mime, ';')) list($mime) = explode(';', $mime);	// in case it contains eg. charset info

		$ext = !empty($mime) ? mime_magic::mime2ext($mime) : '';

		if (!$ext || substr($filename, -strlen($ext)-1) == '.'.$ext ||
			preg_match('/\.([^.]+)$/', $filename, $matches) && mime_magic::ext2mime($matches[1]) == $mime)
		{
			$parts = explode('.', $filename);
			$ext = '.'.array_pop($parts);
			$filename = implode('.', $parts);
		}
		else
		{
			$ext = '.'.$ext;
		}
		for($i = 1; $i < 100; ++$i)
		{
			$path = egw_link::vfs_path($app, $id, $filename.($i > 1 ? '-'.$i : '').$ext, true);
			if (!egw_vfs::stat($path)) break;
		}
		if ($i >= 100) return null;

		if (!egw_vfs::file_exists($dir = egw_vfs::dirname($path)) && !egw_vfs::mkdir($dir))
		{
			error_log(__METHOD__."('$app', $id, ...) failed to create entry dir $dir!");
			return false;
		}

		return egw_vfs::fopen($path, 'w');
	}

	/**
	 * Get attachment location from path
	 *
	 * @param string $path
	 * @return string
	 */
	protected static function path2location($path)
	{
		static $url_prefix;
		if (!isset($url_prefix))
		{
			$url_prefix = '';
			if ($GLOBALS['egw_info']['server']['webserver_url'][0] == '/')
			{
				$url_prefix = ($_SERVER['HTTPS'] ? 'https' : 'http').'://'.$_SERVER['HTTP_HOST'];
			}
		}
		return $url_prefix.egw::link(egw_vfs::download_url($path));
	}

	/**
	 * Add ATTACH attribute(s) for iCal
	 *
	 * @param string $app eg. 'calendar'
	 * @param int|string $id
	 * @param array &$attributes
	 * @param array &$parameters
	 */
	public static function add_attach($app, $id, array &$attributes, array &$parameters)
	{
		foreach(egw_vfs::find(egw_link::vfs_path($app, $id, '', true), array(
			'type' => 'F',
			'need_mime' => true,
		), true) as $path => $stat)
		{
			$attributes['ATTACH'][] = self::path2location($path);
			$parameters['ATTACH'][] = array(
				'MANAGED-ID' => groupdav::path2managed_id($path),
				'FMTTYP'     => $stat['mime'],
				'SIZE'       => $stat['size'],
				'FILENAME'   => egw_vfs::basename($path),
			);
		}
		// if we have attachments, set X-attribute to enable deleting them by put
		// (works around events synced before without ATTACH attributes)
		if ($attributes['ATTACH']) $attributes['X-EGROUPWARE-ATTACH-INCLUDED'] = 'TRUE';
	}

	/**
	 * Return managed-id of a vfs-path
	 *
	 * @param string $path "/apps/$app/$id/something"
	 * @return string
	 */
	static public function path2managed_id($path)
	{
		return base64_encode($path);
	}

	/**
	 * Return vfs-path of a managed-id
	 *
	 * @param string $managed_id
	 * @param string $app=null app-name to check against path
	 * @param string|int $id=null id to check agains path
	 * @return string|boolean "/apps/$app/$id/something" or false if not found or not belonging to given $app/$id
	 */
	static public function managed_id2path($managed_id, $app=null, $id=null)
	{
		$path = base64_decode($managed_id);

		if (!$path || substr($path, 0, 6) != '/apps/' || !egw_vfs::stat($path))
		{
			$path = false;
		}
		elseif (!empty($app) && !empty($id))
		{
			list(,,$a,$i) = explode('/', $path);
			if ($a !== $app || $i !== (string)$id)
			{
				$path = false;
			}
		}
		error_log(__METHOD__."('$managed_id', $app, $id) base64_decode('$managed_id')=".array2string(base64_decode($managed_id)).' returning '.array2string($path));
		return $path;
	}

	/**
	 * Namespaces which need to be eplicitly named in self::$proppatch_props,
	 * because we consider them protected, if not explicitly named
	 *
	 * @var array
	 */
	static $ns_needs_explicit_named_props = array(self::DAV, self::CALDAV, self::CARDDAV, self::CALENDARSERVER);
	/**
	 * props modifyable via proppatch from client for name-spaces mentioned in self::$ns_needs_explicit_named_props
	 *
	 * Props named here are stored in prefs without namespace!
	 *
	 * @var array name => namespace pairs
	 */
	static $proppatch_props = array(
		'displayname' => self::DAV,
		'calendar-description' => self::CALDAV,
		'addressbook-description' => self::CARDDAV,
		'calendar-color' => self::ICAL,	// only mentioned that old prefs still work
		'calendar-order' => self::ICAL,
		'default-alarm-vevent-date' => self::CALDAV,
		'default-alarm-vevent-datetime' => self::CALDAV,
	);

	/**
	 * PROPPATCH method handler
	 *
	 * @param array &$options general parameter passing array
	 * @return string with responsedescription or null, individual status in $options['props'][]['status']
	 */
	function PROPPATCH(&$options)
	{
		if ($this->debug) error_log(__CLASS__."::$method(".array2string($options).')');

		// parse path in form [/account_lid]/app[/more]
		self::_parse_path($options['path'],$id,$app,$user,$user_prefix);	// allways returns false if eg. !$id
		if ($app == 'principals' || $id || $options['path'] == '/')
		{
			if ($this->debug > 1) error_log(__CLASS__."::$method: user='$user', app='$app', id='$id': 404 not found!");
			foreach($options['props'] as &$prop) $prop['status'] = '403 Forbidden';
			return 'NOT allowed to PROPPATCH that resource!';
		}
		// store selected props in preferences, eg. calendar-color, see self::$proppatch_props
		foreach($options['props'] as &$prop)
		{
			if ((isset(self::$proppatch_props[$prop['name']]) && self::$proppatch_props[$prop['name']] === $prop['xmlns'] ||
				!in_array($prop['xmlns'],self::$ns_needs_explicit_named_props)))
			{
				if (!$app)
				{
					$app = 'groupdav';
					$name = $prop['name'].':'.$options['path'].':'.$prop['ns'];
				}
				else
				{
					$name = $prop['name'].':'.$user.(isset(self::$proppatch_props[$prop['name']]) &&
						self::$proppatch_props[$prop['name']] == $prop['ns'] ? '' : ':'.$prop['ns']);
				}
				//error_log("preferences['user']['$app']['$name']=".array2string($GLOBALS['egw_info']['user']['preferences'][$app][$name]).($GLOBALS['egw_info']['user']['preferences'][$app][$name] !== $prop['val'] ? ' !== ':' === ')."prop['val']=".array2string($prop['val']));
				if ($GLOBALS['egw_info']['user']['preferences'][$app][$name] !== $prop['val'])	// nothing to change otherwise
				{
					if (isset($prop['val']))
					{
						$GLOBALS['egw']->preferences->add($app, $name, $prop['val']);
					}
					else
					{
						$GLOBALS['egw']->preferences->delete($app, $name);
					}
					$need_save = true;
				}
				$prop['status'] = '200 OK';
			}
			else
			{
				$prop['status'] = '409 Conflict';	// could also be "403 Forbidden"
			}
		}
		if ($need_save) $GLOBALS['egw']->preferences->save_repository();
	}

	/**
	 * PUT method handler
	 *
	 * @param  array  parameter passing array
	 * @return bool   true on success
	 */
	function PUT(&$options)
	{
		// read the content in a string, if a stream is given
		if (isset($options['stream']))
		{
			$options['content'] = '';
			while(!feof($options['stream']))
			{
				$options['content'] .= fread($options['stream'],8192);
			}
		}

		if ($this->debug) error_log(__METHOD__.'('.array2string($options).')');

		if (!$this->_parse_path($options['path'],$id,$app,$user,$prefix))
		{
			return '404 Not Found';
		}
		if (($handler = self::app_handler($app)))
		{
			$status = $handler->put($options,$id,$user,$prefix);

			// set default stati: true --> 204 No Content, false --> should be already handled
			if (is_bool($status)) $status = $status ? '204 No Content' : '400 Something went wrong';

			// check/handle Prefer: return-representation
			if ($status[0] === '2')
			{
				$handler->check_return_representation($options, $id, $user);
			}
			return $status;
		}
		return '501 Not Implemented';
	}

	/**
	 * DELETE method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function DELETE($options)
	{
		if ($this->debug) error_log(__METHOD__.'('.array2string($options).')');

		if (!$this->_parse_path($options['path'],$id,$app,$user))
		{
			return '404 Not Found';
		}
		if (($handler = self::app_handler($app)))
		{
			$status = $handler->delete($options,$id);
			// set default stati: true --> 204 No Content, false --> should be already handled
			if (is_bool($status)) $status = $status ? '204 No Content' : '400 Something went wrong';
			return $status;
		}
		return '501 Not Implemented';
	}

	/**
	 * MKCOL method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function MKCOL($options)
	{
		if ($this->debug) error_log(__METHOD__.'('.array2string($options).')');

		return '501 Not Implemented';
	}

	/**
	 * MOVE method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function MOVE($options)
	{
		if ($this->debug) error_log(__METHOD__.'('.array2string($options).')');

		return '501 Not Implemented';
	}

	/**
	 * COPY method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function COPY($options, $del=false)
	{
		if ($this->debug) error_log('groupdav::'.($del ? 'MOVE' : 'COPY').'('.array2string($options).')');

		return '501 Not Implemented';
	}

	/**
	 * LOCK method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function LOCK(&$options)
	{
		self::_parse_path($options['path'],$id,$app,$user);
		$path = egw_vfs::app_entry_lock_path($app,$id);

		if ($this->debug) error_log(__METHOD__.'('.array2string($options).") path=$path");

		// get the app handler, to check if the user has edit access to the entry (required to make locks)
		$handler = self::app_handler($app);

		// TODO recursive locks on directories not supported yet
		if (!$id || !empty($options['depth']) || !$handler->check_access(EGW_ACL_EDIT,$id))
		{
			return '409 Conflict';
		}
		$options['timeout'] = time()+300; // 5min. hardcoded

		// dont know why, but HTTP_WebDAV_Server passes the owner in D:href tags, which get's passed unchanged to checkLock/PROPFIND
		// that's wrong according to the standard and cadaver does not show it on discover --> strip_tags removes eventual tags
		if (($ret = egw_vfs::lock($path,$options['locktoken'],$options['timeout'],strip_tags($options['owner']),
			$options['scope'],$options['type'],isset($options['update']),false)) && !isset($options['update']))		// false = no ACL check
		{
			return $ret ? '200 OK' : '409 Conflict';
		}
		return $ret;
	}

	/**
	 * UNLOCK method handler
	 *
	 * @param  array  general parameter passing array
	 * @return bool   true on success
	 */
	function UNLOCK(&$options)
	{
		self::_parse_path($options['path'],$id,$app,$user);
		$path = egw_vfs::app_entry_lock_path($app,$id);

		if ($this->debug) error_log(__METHOD__.'('.array2string($options).") path=$path");
		return egw_vfs::unlock($path,$options['token']) ? '204 No Content' : '409 Conflict';
	}

	/**
	 * checkLock() helper
	 *
	 * @param  string resource path to check for locks
	 * @return bool   true on success
	 */
	function checkLock($path)
	{
		self::_parse_path($path,$id,$app,$user);
		$path = egw_vfs::app_entry_lock_path($app,$id);

		return egw_vfs::checkLock($path);
	}

	/**
	 * ACL method handler
	 *
	 * @param  array  general parameter passing array
	 * @return string HTTP status
	 */
	function ACL(&$options)
	{
		self::_parse_path($options['path'],$id,$app,$user);

		if ($this->debug) error_log(__METHOD__.'('.array2string($options).") path=$path");

		$options['errors'] = array();
		switch ($app)
		{
			case 'calendar':
			case 'addressbook':
			case 'infolog':
				$status = '200 OK'; // grant all
				break;
			default:
				$options['errors'][] = 'no-inherited-ace-conflict';
				$status = '403 Forbidden';
		}

		return $status;
	}

	/**
	 * Parse a path into it's id, app and user parts
	 *
	 * @param string $path
	 * @param int &$id
	 * @param string &$app addressbook, calendar, infolog (=infolog)
	 * @param int &$user
	 * @param string &$user_prefix=null
	 * @return boolean true on success, false on error
	 */
	function _parse_path($path,&$id,&$app,&$user,&$user_prefix=null)
	{
		if ($this->debug)
		{
			error_log(__METHOD__." called with ('$path') id=$id, app='$app', user=$user");
		}
		if ($path[0] == '/')
		{
			$path = substr($path, 1);
		}
		$parts = explode('/', $this->_unslashify($path));

		// /(resources|locations)/<resource-id>-<resource-name>/calendar
		if ($parts[0] == 'resources' || $parts[0] == 'locations')
		{
			if (!empty($parts[1]))
			{
				$user = $parts[0].'/'.$parts[1];
				array_shift($parts);
				$res_id = (int)array_shift($parts);
				if (!groupdav_principals::read_resource($res_id))
				{
					return false;
				}
				$account_id = 'r'.$res_id;
				$app = 'calendar';
			}
		}
		elseif (($account_id = $this->accounts->name2id($parts[0], 'account_lid')) ||
			($account_id = $this->accounts->name2id($parts[0]=urldecode($parts[0]))))
		{
			// /$user/$app/...
			$user = array_shift($parts);
		}

		if (!isset($app)) $app = array_shift($parts);

		// /addressbook-accounts/
		if (!$account_id && $app == 'addressbook-accounts')
		{
			$app = 'addressbook';
			$user = 0;
			$user_prefix = '/';
		}
		// shared calendars/addressbooks at /<currentuser>/(calendar|addressbook|infolog|resource|location)-<username>
		elseif ($account_id == $GLOBALS['egw_info']['user']['account_id'] && strpos($app, '-') !== false)
		{
			$user_prefix = '/'.$GLOBALS['egw_info']['user']['account_lid'].'/'.$app;
			list($app, $username) = explode('-', $app, 2);
			if ($username == 'accounts' && !$GLOBALS['egw_info']['user']['preferences']['addressbook']['hide_accounts'])
			{
				$account_id = 0;
			}
			elseif($app == 'resource' || $app == 'location')
			{
				if (!groupdav_principals::read_resource($res_id = (int)$username))
				{
					return false;
				}
				$account_id = 'r'.$res_id;
				$app = 'calendar';
			}
			elseif (!($account_id = $this->accounts->name2id($username, 'account_lid')) &&
					!($account_id = $this->accounts->name2id($username=urldecode($username))))
			{
				return false;
			}
			$user = $account_id;
		}
		elseif ($user)
		{
			$user_prefix = '/'.$user;
			$user = $account_id;
		}
		else
		{
			$user_prefix = '';
			$user = $GLOBALS['egw_info']['user']['account_id'];
		}

		$id = array_pop($parts);

		$ok = ($id || isset($_GET['add-member']) && $_SERVER['REQUEST_METHOD'] == 'POST') &&
			($user || $user === 0) && in_array($app,array('addressbook','calendar','infolog','principals'));

		if ($this->debug)
		{
			error_log(__METHOD__."('$path') returning " . ($ok ? 'true' : 'false') . ": id='$id', app='$app', user='$user', user_prefix='$user_prefix'");
		}
		return $ok;
	}

	protected static $request_starttime;
	/**
	 * Log level from user prefs: $GLOBALS['egw_info']['user']['preferences']['groupdav']['debug_level'])
	 * - 'f' files directory
	 * - 'r' to error-log, but only shortend requests
	 *
	 * @var string
	 */
	protected static $log_level;

	/**
	 * Serve WebDAV HTTP request
	 *
	 * Reimplemented to add logging
	 */
	function ServeRequest()
	{
		if ((self::$log_level=$GLOBALS['egw_info']['user']['preferences']['groupdav']['debug_level']) === 'r' ||
			self::$log_level === 'f' || $this->debug)
		{
			self::$request_starttime = microtime(true);
			// do NOT log non-text attachments
			$this->store_request = $_SERVER['REQUEST_METHOD'] != 'POST' || !isset($_GET['action']) ||
				!in_array($_GET['action'], array('attachment-add', 'attachment-update')) ||
				substr($_SERVER['CONTENT_TYPE'], 0, 5) == 'text/';
			ob_start();
		}
		parent::ServeRequest();

		if (self::$request_starttime) self::log_request();
	}

	/**
	 * Log the request
	 *
	 * @param string $extra='' extra text to add below request-log, eg. exception thrown
	 */
	protected function log_request($extra='')
	{
		if (self::$request_starttime)
		{
			if (self::$log_level === 'f')
			{
				$msg_file = $GLOBALS['egw_info']['server']['files_dir'];
				$msg_file .= '/groupdav';
				if (!file_exists($msg_file) && !mkdir($msg_file,0700))
				{
					error_log(__METHOD__."() Could NOT create directory '$msg_file'!");
					return;
				}
				$msg_file .= '/'.$GLOBALS['egw_info']['user']['account_lid'].'-'.
					str_replace('/','!',$_SERVER['HTTP_USER_AGENT']).'.log';

				$content = '*** '.$_SERVER['REMOTE_ADDR'].' '.date('c')."\n";
			}
			$content .= $_SERVER['REQUEST_METHOD'].' '.$_SERVER['REQUEST_URI'].' HTTP/1.1'."\n";
			// reconstruct headers
			foreach($_SERVER as $name => $value)
			{
				list($type,$name) = explode('_',$name,2);
				if ($type == 'HTTP' || $type == 'CONTENT')
				{
					$content .= str_replace(' ','-',ucwords(strtolower(($type=='HTTP'?'':$type.' ').str_replace('_',' ',$name)))).
						': '.($name=='AUTHORIZATION'?'Basic ***************':$value)."\n";
				}
			}
			$content .= "\n";
			if ($this->request)
			{
				$content .= $this->request."\n";
			}
			$content .= 'HTTP/1.1 '.$this->_http_status."\n";
			foreach(headers_list() as $line) $content .= $line."\n";
			if (($c = ob_get_flush())) $content .= "\n";
			if (self::$log_level !== 'f' && strlen($c) > 1536) $c = substr($c,0,1536)."\n*** LOG TRUNKATED\n";
			$content .= $c;
			if ($extra) $content .= $extra;
			if ($this->to_log) $content .= "\n### ".implode("\n### ", $this->to_log)."\n";
			$content .= $this->_http_status[0] == '4' && substr($this->_http_status,0,3) != '412' ||
				$this->_http_status[0] == '5' ? '###' : '***';	// mark failed requests with ###, instead of ***
			$content .= sprintf(' %s --> "%s" took %5.3f s',$_SERVER['REQUEST_METHOD'].($_SERVER['REQUEST_METHOD']=='REPORT'?' '.$this->propfind_options['root']['name']:'').' '.$_SERVER['PATH_INFO'],$this->_http_status,microtime(true)-self::$request_starttime)."\n\n";

			if ($msg_file && ($f = fopen($msg_file,'a')))
			{
				flock($f,LOCK_EX);
				fwrite($f,$content);
				flock($f,LOCK_UN);
				fclose($f);
			}
			else
			{
				foreach(explode("\n",$content) as $line) error_log($line);
			}
		}
	}

	/**
	 * Content of log() calls, to be appended to request_log
	 *
	 * @var array
	 */
	private $to_log = array();

	/**
	 * Log unconditional to own request- and PHP error-log
	 *
	 * @param string $str
	 */
	public function log($str)
	{
		$this->to_log[] = $str;

		error_log($str);
	}

	/**
	 * Exception handler, which additionally logs the request (incl. a trace)
	 *
	 * Does NOT return and get installed in constructor.
	 *
	 * @param Exception $e
	 */
	public static function exception_handler(Exception $e)
	{
		// logging exception as regular egw_execption_hander does
		_egw_log_exception($e,$headline);

		// exception handler sending message back to the client as basic auth message
		$error = str_replace(array("\r", "\n"), array('', ' | '), $e->getMessage());
		header('WWW-Authenticate: Basic realm="'.$headline.': '.$error.'"');
		header('HTTP/1.1 401 Unauthorized');
		header('X-WebDAV-Status: 401 Unauthorized', true);

		// if our own logging is active, log the request plus a trace, if enabled in server-config
		if (self::$request_starttime && isset(self::$instance))
		{
			self::$instance->_http_status = '401 Unauthorized';	// to correctly log it
			if ($GLOBALS['egw_info']['server']['exception_show_trace'])
			{
				self::$instance->log_request("\n".$e->getTraceAsString()."\n");
			}
			else
			{
				self::$instance->log_request();
			}
		}
		if (is_object($GLOBALS['egw']))
		{
			common::egw_exit();
		}
		exit;
	}
}
