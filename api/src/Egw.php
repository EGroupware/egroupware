<?php
/**
 * EGroupware API - Applications
 *
 * @link http://www.egroupware.org
 * This file was originaly written by Dan Kuykendall and Joseph Engo
 * Copyright (C) 2000, 2001 Dan Kuykendall
 * Parts Copyright (C) 2003 Free Software Foundation
 * @author	RalfBecker@outdoor-training.de
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @version $Id$
 */

namespace EGroupware\Api;

// explicitly list old, non-namespaced classes
// they are only used, if phpgwapi is installed
use accounts as egw_accounts;
use egw_session;
use common;

/**
 * New written class to create the eGW enviroment AND restore it from a php-session
 *
 * Rewritten by RalfBecker@outdoor-training.de to store the eGW enviroment
 * (egw-object and egw_info-array) in a php-session and restore it from
 * there instead of creating it completly new on each page-request.
 * The enviroment gets now created by the egw-class
 *
 * Extending Egw\Base which uses now a getter method to create the usual subobject on demand,
 * to allow a quicker header include on sites not using php4-restore.
 * This also makes a lot of application code, like the following, unnecessary:
 * if (!is_object($GLOBALS['egw']->ldap)
 * {
 * 		$GLOBALS['egw']->ldap = Api\Ldap::factory();
 * }
 * You can now simply use $GLOBALS['egw']->ldap, and the egw class instanciates it for you on demand.
 */
class Egw extends Egw\Base
{
	/**
	 * Turn on debug mode. Will output additional data for debugging purposes.
	 * @var	string
	 * @access	public
	 */
	var $debug = 0;		// This will turn on debugging information.
	/**
	 * Instance of the account object
	 *
	 * @var Accounts
	 */
	var $accounts;

	/**
	 * @var Session
	 */
	public $session;

	/**
	 * Constructor: Instantiates the sub-classes
	 *
	 * @author RalfBecker@outdoor-training.de
	 * @param array $domain_names array with valid egw-domain names
	 */
	function __construct($domain_names=null)
	{
		$GLOBALS['egw'] =& $this;	// we need to be immediately available there for the other classes we instantiate
		$this->setup($domain_names,True);
	}

	/**
	 * Called every time the constructor is called.  Also called by sessions to ensure the correct db,
	 *  in which case we do not recreate the session object.
	 * @author RalfBecker@outdoor-training.de (moved to setup() by milos@groupwhere.org
	 * @param array|null $domain_names =null array with valid egw-domain names
	 * @param boolean $createsessionobject True to create the session object (default=True)
	 */
	function setup($domain_names,$createsessionobject=True)
	{
		// create the DB-object
		// as SiteMgr, Wiki, KnowledgeBase and probably more still use eg next_record(), we stick with Db\Deprecated for now
		$this->db = new Db\Deprecated($GLOBALS['egw_info']['server']);
		if ($this->debug)
		{
			$this->db->Debug = 1;
		}
		$this->db->set_app(Db::API_APPNAME);

		// check if eGW is already setup, if not redirect to setup/
		try {
			$this->db->connect();
			$num_config = $this->db->select(Config::TABLE,'COUNT(config_name)',false,__LINE__,__FILE__)->fetchColumn();
		}
		catch(Db\Exception\Connection $e) {
			// ignore exception, get handled below
		}
		catch(Db\Exception\InvalidSql $e1) {
			unset($e1);	// not used
			try {
				$phpgw_config = $this->db->select('phpgw_config','COUNT(config_name)',false,__LINE__,__FILE__)->fetchColumn();
			}
			catch (Db\Exception\InvalidSql $e2) {
				unset($e2);	// not used
				// ignor error, get handled below
			}
		}
		if (!$num_config)
		{
			// we check for the old table too, to not scare updating users ;-)
			if ($phpgw_config)
			{
				throw new Exception('You need to update EGroupware before you can continue using it.',999);
			}
			if ($e)
			{
				throw new Db\Exception\Setup('Connection with '.$e->getMessage()."\n\n".
					'Maybe you not created a database for EGroupware yet.',999);
			}
			throw new Db\Exception\Setup('It appears that you have not created the database tables for EGroupware.',999);
		}
		// Set the DB's client charset if a system-charset is set and some other values needed by egw_cache (used in Config::read)
			foreach($this->db->select(Config::TABLE,'config_name,config_value',array(
			'config_app'  => 'phpgwapi',
			'config_name' => array('system_charset','install_id','temp_dir','server_timezone'),
		),__LINE__,__FILE__) as $row)
		{
			$GLOBALS['egw_info']['server'][$row['config_name']] = $row['config_value'];
		}
		if ($GLOBALS['egw_info']['server']['system_charset'] && $GLOBALS['egw_info']['server']['system_charset'] != 'utf-8')
		{
			$this->db->Link_ID->SetCharSet($GLOBALS['egw_info']['server']['system_charset']);
		}
		$this->db->SetTimeZone($GLOBALS['egw_info']['server']['server_timezone']);

		// load up the $GLOBALS['egw_info']['server'] array
		$GLOBALS['egw_info']['server'] += Config::read('phpgwapi');

		// if webserver_url does not match eg. because of proxying, fix it
		if (isset($_SERVER['HTTP_X_FORWARDED_URI']) &&
			($prefix = strpos($_SERVER['HTTP_X_FORWARDED_URI'],
				$GLOBALS['egw_info']['server']['webserver_url'])))
		{
			$GLOBALS['egw_info']['server']['webserver_url'] =
				substr($_SERVER['HTTP_X_FORWARDED_URI'], 0, $prefix).
				$GLOBALS['egw_info']['server']['webserver_url'];
		}
		if (isset($_SERVER['HTTP_X_FORWARDED_HOST']) && $GLOBALS['egw_info']['server']['webserver_url'][0] != '/')
		{
			$GLOBALS['egw_info']['server']['webserver_url'] =
				Header\Http::schema().'://'.Header\Http::host().
				parse_url($GLOBALS['egw_info']['server']['webserver_url'], PHP_URL_PATH);
		}

		// if no server timezone set, use date_default_timezone_get() to determine it once
		// it fills to log with deprecated warnings under 5.3 otherwise
		if (empty($GLOBALS['egw_info']['server']['server_timezone']) ||
			$GLOBALS['egw_info']['server']['server_timezone'] == 'System/Localtime')	// treat invalid tz like empty!
		{
			try
			{
				$tz = new \DateTimeZone(date_default_timezone_get());
				Config::save_value('server_timezone',$GLOBALS['egw_info']['server']['server_timezone'] = $tz->getName(),'phpgwapi');
				error_log(__METHOD__."() stored server_timezone=".$GLOBALS['egw_info']['server']['server_timezone']);
			}
			catch(Exception $e)
			{
				// do nothing if new DateTimeZone fails (eg. 'System/Localtime' returned), specially do NOT store it!
				error_log(__METHOD__."() NO valid 'date.timezone' set in your php.ini!");
			}
		}
		date_default_timezone_set($GLOBALS['egw_info']['server']['server_timezone']);

		// if phpgwapi exists we prefer accounts and egw_session, as they have some deprecated methods
		if (file_exists(EGW_SERVER_ROOT.'/phpgwapi'))
		{
			$this->accounts       = new egw_accounts();
			/* Do not create the session object if called by the sessions class.  This way
			 * we ensure the correct db based on the user domain.
			 */
			if($createsessionobject)
			{
				$this->session    = new egw_session($domain_names);
			}
		}
		else
		{
			$this->accounts       = new Accounts();
			/* Do not create the session object if called by the sessions class.  This way
			 * we ensure the correct db based on the user domain.
			 */
			if($createsessionobject)
			{
				$this->session    = new Session($domain_names);
			}
		}
		// setup the other subclasses
		$this->acl            = new Acl();
		$this->preferences    = new Preferences();
		$this->applications   = new Egw\Applications();

		if ($GLOBALS['egw_info']['flags']['currentapp'] != 'login' && $GLOBALS['egw_info']['flags']['currentapp'] != 'logout')
		{
			$this->define_egw_constants();

			$this->verify_session();
			$this->applications->read_installed_apps();	// to get translated app-titles, has to be after verify_session

			$this->check_app_rights();

			$this->load_optional_classes();
		}
		else	// set the defines for login, in case it's more then just login
		{
			$this->define_egw_constants();
		}
	}

	/**
	 * __wakeup function gets called by php while unserializing the egw-object, eg. reconnects to the DB
	 *
	 * @author RalfBecker@outdoor-training.de
	 */
	function __wakeup()
	{
		$GLOBALS['egw'] =& $this;	// we need to be immediately available there for the other classes we instantiate
		// for the migration: reference us to the old phpgw object
		$GLOBALS['phpgw'] =& $this;

		try {
			// set our default charset
			$this->db->Link_ID->SetCharSet($this->db->Type === 'mysql' ? 'utf8' : 'utf-8');
		}
		catch (\Throwable $e) {
			_egw_log_exception($e);
		}
		// restoring server timezone, to avoid warnings under php5.3
		if (!empty($GLOBALS['egw_info']['server']['server_timezone']))
		{
			date_default_timezone_set($GLOBALS['egw_info']['server']['server_timezone']);
			$this->db->SetTimeZone($GLOBALS['egw_info']['server']['server_timezone']);
		}

		$this->define_egw_constants();
	}

	/**
	 * wakeup2 function needs to be called after unserializing the egw-object
	 *
	 * It adapts the restored object/enviroment to the changed (current) application / page-request
	 *
	 * @author RalfBecker@outdoor-training.de
	 */
	function wakeup2()
	{
		// do some application specific stuff, need to be done as we are different (current) app now
		if (isset($this->template))
		{
			$this->template->set_root(EGW_APP_TPL);
		}
		// init the translation class, necessary as own wakeup would run before our's
		Translation::init(isset($GLOBALS['egw_info']['flags']['load_translations']) ? $GLOBALS['egw_info']['flags']['load_translations'] : true);

		// verify the session
		$GLOBALS['egw']->verify_session();
		$GLOBALS['egw']->check_app_rights();

		$this->load_optional_classes();
	}

	/**
	 * load optional classes by mentioning them in egw_info[flags][enable_CLASS_class] => true
	 *
	 * Also loads the template-class if not egw_info[flags][disable_Template_class] is set
	 *
	 * Maybe the whole thing should be depricated ;-)
	 */
	function load_optional_classes()
	{
		// output the header unless the developer turned it off
		if (!@$GLOBALS['egw_info']['flags']['noheader'])
		{
			echo $GLOBALS['egw']->framework->header();

			if (!$GLOBALS['egw_info']['flags']['nonavbar'])
			{
			   echo $GLOBALS['egw']->framework->navbar();
			}
		}
	}

	/**
	 * Verfiy there is a valid session
	 *
	 * One can specify a callback, which gets called if there's no valid session. If the callback returns true, the parameter
	 * containst account-details (in keys login, passwd and passwd_type) to automatic create an (anonymous session)
	 *
	 * It also checks if enforce_ssl is set in the DB and redirects to the https:// version of the site.
	 *
	 * If there is no valid session and none could be automatic created, the function will redirect to login and NOT return
	 */
	function verify_session()
	{
		if($GLOBALS['egw_info']['server']['enforce_ssl'] === 'redirect' && Header\Http::schema() !== 'https')
		{
			Header('Location: https://' . Header\Http::host() . $_SERVER['REQUEST_URI']);
			exit;
		}
		// check if we have a session, if not try to automatic create one
		if ($this->session->verify()) return true;

		$account = null;
		if (($account_callback = $GLOBALS['egw_info']['flags']['autocreate_session_callback']) && is_callable($account_callback) &&
			($sessionid = call_user_func_array($account_callback,array(&$account))) === true)	// $account_call_back returns true, false or a session-id
		{
			$sessionid = $this->session->create($account);
		}
		if (!$sessionid)
		{
			//echo "<p>account_callback='$account_callback', account=".print_r($account,true).", sessionid=$sessionid</p>\n"; exit;
			// we forward to the same place after the re-login
			if ($GLOBALS['egw_info']['server']['webserver_url'] && $GLOBALS['egw_info']['server']['webserver_url'] != '/' &&
				($webserver_path = parse_url($GLOBALS['egw_info']['server']['webserver_url'],PHP_URL_PATH)) && $webserver_path != '/')
			{
				// we have to use only path component, to cope with domains like http://egroupware.domain.com and /egroupware
				list(,$relpath) = explode($webserver_path,parse_url($_SERVER['PHP_SELF'],PHP_URL_PATH),2);
			}
			else	// the webserver-url is empty or just a slash '/' (eGW is installed in the docroot and no domain given)
			{
				$matches = null;
				if (preg_match('/^https?:\/\/[^\/]*\/(.*)$/',$relpath=$_SERVER['PHP_SELF'],$matches))
				{
					$relpath = $matches[1];
				}
			}

			// remove evtl. set caching headers, we dont want the "Session not verified" redirect to be cached
			header('Cache-Control: no-store, no-cache, must-revalidate');
			header('Expires: Thu, 19 Nov 1981 08:52:00 GMT');
			header('Pragma: no-cache');

			// this removes the sessiondata if its saved in the URL
			$query = preg_replace('/[&]?sessionid(=|%3D)[^&]+&kp3(=|%3D)[^&]+&domain=.*$/','',$_SERVER['QUERY_STRING']);
			if ($GLOBALS['egw_info']['server']['http_auth_types'])
			{
				$redirect = '/api/ntlm/index.php?';
			}
			else
			{
				$redirect = '/login.php?';
				// only add "your session could not be verified", if a sessionid is given (cookie or on url)
				if (Session::get_sessionid()) $redirect .= 'cd=10&';
			}
			if ($relpath) $redirect .= 'phpgw_forward='.urlencode($relpath.(!empty($query) ? '?'.$query : ''));
			self::redirect_link($redirect);
		}
	}

	/**
	 * Verify the user has rights for the requested app
	 *
	 * If the user has no rights for the app (eg. called via URL) he get a permission denied page (this function does NOT return)
	 *
	 * @throws Exception\Redirect for anonymous user accessing something he has no rights to
	 * @throws Exception\NoPermission\Admin
	 * @throws Exception\NoPermission\App
	 */
	function check_app_rights()
	{
		$this->currentapp = $GLOBALS['egw_info']['flags']['currentapp'];	// some apps change it later

		if (!in_array($GLOBALS['egw_info']['flags']['currentapp'], array('api','about')))	// give everyone implicit api rights
		{
			// This will need to use ACL in the future
			if (!$GLOBALS['egw_info']['user']['apps'][$currentapp = $GLOBALS['egw_info']['flags']['currentapp']] ||
				($GLOBALS['egw_info']['flags']['admin_only'] && !$GLOBALS['egw_info']['user']['apps']['admin']))
			{
				// present a login page, if anon user has no right for an application
				if ($this->session->session_flags == 'A')
				{
					// need to destroy a basic auth session here, because it will only be available on current url
					if (($sessionid = Session::get_sessionid(true)))
					{
						$GLOBALS['egw']->session->destroy($sessionid);
					}
					throw new Exception\Redirect(self::link('/logout.php'));
				}
				if ($currentapp == 'admin' || $GLOBALS['egw_info']['flags']['admin_only'])
				{
					throw new Exception\NoPermission\Admin();
				}
				throw new Exception\NoPermission\App($currentapp);
			}
		}
	}

	/**
	 * create all the defines / constants of the eGW-environment (plus the deprecated phpgw ones)
	 */
	function define_egw_constants()
	{
		define('EGW_ACL_READ',1);
		define('EGW_ACL_ADD',2);
		define('EGW_ACL_EDIT',4);
		define('EGW_ACL_DELETE',8);
		define('EGW_ACL_PRIVATE',16);
		define('EGW_ACL_GROUP_MANAGERS',32);
		define('EGW_ACL_CUSTOM_1',64);
		define('EGW_ACL_CUSTOM_2',128);
		define('EGW_ACL_CUSTOM_3',256);
		// and the old ones
		define('PHPGW_ACL_READ',1);
		define('PHPGW_ACL_ADD',2);
		define('PHPGW_ACL_EDIT',4);
		define('PHPGW_ACL_DELETE',8);
		define('PHPGW_ACL_PRIVATE',16);
		define('PHPGW_ACL_GROUP_MANAGERS',32);
		define('PHPGW_ACL_CUSTOM_1',64);
		define('PHPGW_ACL_CUSTOM_2',128);
		define('PHPGW_ACL_CUSTOM_3',256);
		// A few hacker resistant constants that will be used throught the program
		if (file_exists(EGW_SERVER_ROOT.'/phpgwapi'))
		{
			define('EGW_TEMPLATE_DIR', Framework\Template::get_dir('phpgwapi'));
			define('EGW_IMAGES_DIR', common::get_image_path('phpgwapi'));
			define('EGW_IMAGES_FILEDIR', common::get_image_dir('phpgwapi'));
			define('EGW_APP_ROOT', common::get_app_dir());
			define('EGW_APP_INC', common::get_inc_dir());
			try {
				define('EGW_APP_TPL', Framework\Template::get_dir());
			}
			catch (Exception\WrongParameter $e) {
				unset($e);
				define('EGW_APP_TPL', null);
			}
			define('EGW_IMAGES', common::get_image_path());
			define('EGW_APP_IMAGES_DIR', common::get_image_dir());
			// and the old ones
			define('PHPGW_TEMPLATE_DIR',EGW_TEMPLATE_DIR);
			define('PHPGW_IMAGES_DIR',EGW_IMAGES_DIR);
			define('PHPGW_IMAGES_FILEDIR',EGW_IMAGES_FILEDIR);
			define('PHPGW_APP_ROOT',EGW_APP_ROOT);
			define('PHPGW_APP_INC',EGW_APP_INC);
			define('PHPGW_APP_TPL',EGW_APP_TPL);
			define('PHPGW_IMAGES',EGW_IMAGES);
			define('PHPGW_APP_IMAGES_DIR',EGW_APP_IMAGES_DIR);
		}
	}

	/**
	 * force the session cache to be re-created, because some of it's data changed
	 *
	 * Needs to be called if user-preferences, system-config or enabled apps of the current user have been changed and
	 * the change should have immediate effect
	 */
	static function invalidate_session_cache()
	{
		// if sharing is active, we must not invalidate the session, as it can not be regenerated
		if (empty($GLOBALS['egw']->sharing))
		{
			unset($_SESSION['egw_info_cache']);
			unset($_SESSION['egw_object_cache']);
		}
	}

	/**
	 * run string through htmlspecialchars and stripslashes
	 *
	 * @param string $s
	 * @return string The string with html special characters replaced with entities
	 */
	static function strip_html($s)
	{
		return htmlspecialchars(stripslashes($s));
	}

	/**
	 * Link url generator
	 *
	 * @param string $url url link is for
	 * @param string|array $extravars ='' extra params to be added to url
	 * @param string $link_app =null if appname or true, some templates generate a special link-handler url
	 * @return string	The full url after processing
	 */
	static function link($url, $extravars = '', $link_app=null)
	{
		return $GLOBALS['egw']->framework->link($url, $extravars, $link_app);
	}

	/**
	 * Redirects direct to a generated link
	 *
	 * @param string $url url link is for
	 * @param string|array $extravars ='' extra params to be added to url
	 * @param string $link_app =null if appname or true, some templates generate a special link-handler url
	 * @return string	The full url after processing
	 */
	static function redirect_link($url, $extravars='', $link_app=null)
	{
		return $GLOBALS['egw']->framework->redirect_link($url, $extravars, $link_app);
	}

	/**
	 * Handles redirects under iis and apache, it does NOT return (calls exit)
	 *
	 * This function handles redirects under iis and apache it assumes that $phpgw->link() has already been called
	 *
	 * @param string $url url to redirect to
	 * @param string $link_app =null appname to redirect for, default currentapp
	 */
	static function redirect($url, $link_app=null)
	{
		Framework::redirect($url, $link_app);
	}

	/**
	 * Shortcut to translation class
	 *
	 * This function is a basic wrapper to Translation::translate()
	 *
	 * @deprecated only used in the old timetracker
	 * @param  string	The key for the phrase
	 * @see	Translation::translate()
	 */
	static function lang($key,$args=null)
	{
		if (!is_array($args))
		{
			$args = func_get_args();
			array_shift($args);
		}
		return Translation::translate($key,$args);
	}

	/**
	 * registered shutdown callbacks and optional arguments
	 *
	 * @var array
	 */
	private static $shutdown_callbacks = array();

	/**
	 * Register a callback to run on shutdown AFTER output send to user
	 *
	 * Allows eg. static classes (no destructor) to run on shutdown AND
	 * garanties to run AFTER output send to user.
	 *
	 * @param callable $callback use array($classname, $method) for static methods
	 * @param array $args =array()
	 */
	public static function on_shutdown($callback, array $args=array())
	{
		array_unshift($args, $callback);

		// prepend new callback, to run them in oposite order they are registered
		array_unshift(self::$shutdown_callbacks, $args);
	}

	/**
	 * Shutdown handler running all registered on_shutdown callbacks and then disconnecting from db
	 */
	function __destruct()
	{
		if (!defined('EGW_SHUTDOWN'))
		{
			define('EGW_SHUTDOWN',True);

			// send json response BEFORE flushing output
			if (Json\Request::isJSONRequest())
			{
				Json\Response::sendResult();
			}

			// run all on_shutdown callbacks with session in their name (eg. egw_link::save_session_cache), do NOT stop on exceptions
			foreach(self::$shutdown_callbacks as $n => $data)
			{
				try {
					//error_log(__METHOD__."() running ".array2string($data));
					$callback = array_shift($data);
					if (!is_array($callback) || strpos($callback[1], 'session') === false) continue;
					call_user_func_array($callback, $data);
				}
				catch (\Exception $ex) {
					_egw_log_exception($ex);
				}
				unset(self::$shutdown_callbacks[$n]);
			}
			// now we can close the session
			// without closing the session fastcgi_finish_request() will NOT send output to user
			if (isset($GLOBALS['egw']->session) && is_object($GLOBALS['egw']->session)) $GLOBALS['egw']->session->commit_session();

			// flush all output to user
			/* does NOT work on Apache :-(
			for($i = 0; ob_get_level() && $i < 10; ++$i)
			{
				ob_end_flush();
			}
			flush();*/
			// working for fastCGI :-)
			if (function_exists('fastcgi_finish_request') && substr($_SERVER['PHP_SELF'], -18) != '/asyncservices.php')
			{
				fastcgi_finish_request();
				ini_set('error_log', dirname($GLOBALS['egw_info']['server']['files_dir']) . '/on-shutdown.log');
			}

			// run all on_shutdown, do NOT stop on exceptions
			foreach(self::$shutdown_callbacks as $data)
			{
				try {
					//error_log(__METHOD__."() running ".array2string($data));
					$callback = array_shift($data);
					call_user_func_array($callback, $data);
				}
				catch (\Throwable $ex) {
					_egw_log_exception($ex);
				}
			}
			// call the asyncservice check_run function if it is not explicitly set to cron-only
			if (!$GLOBALS['egw_info']['server']['asyncservice'])	// is default
			{
				$async = new Asyncservice();
				$async->check_run('fallback');
			}
			$this->db->disconnect();
		}
	}
}