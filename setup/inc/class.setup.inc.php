<?php
/**
 * Setup
 *
 * @link http://www.egroupware.org
 * @package setup
 * @author Joseph Engo <jengo@phpgroupware.org>
 * @author Dan Kuykendall <seek3r@phpgroupware.org>
 * @author Mark Peters <skeeter@phpgroupware.org>
 * @author Miles Lott <milos@groupwhere.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 */

use EGroupware\Api;
use EGroupware\Api\Link;
use EGroupware\Api\Egw;
use EGroupware\Api\Vfs;

class setup
{
	/**
	 * @var Api\Db
	 */
	var $db;
	var $config_table       = 'egw_config';
	var $applications_table = 'egw_applications';
	var $acl_table          = 'egw_acl';
	var $accounts_table     = 'egw_accounts';
	var $prefs_table        = 'egw_preferences';
	var $lang_table         = 'egw_lang';
	var $languages_table    = 'egw_languages';
	var $hooks_table        = 'egw_hooks';
	var $cats_table         = 'egw_categories';
	/**
	 * @var Api\Db\Schema
	 */
	var $oProc;
	var $cookie_domain;

	/**
	 * @var setup_detection
	 */
	var $detection;
	/**
	 * @var setup_process
	 */
	var $process;
	/**
	 * @var setup_translation
	 */
	var $translation;
	/**
	 * @var setup_html
	 */
	var $html;

	var $system_charset = 'utf-8';
	var $lang;

	var $ConfigDomain;

	/* table name vars */
	var $tbl_apps;
	var $tbl_config;
	var $tbl_hooks;

	/**
	 * php version required by EGroupware
	 *
	 * @var float
	 */
	var $required_php_version = 8.0;
	/**
	 * PHP Version recommended for EGroupware
	 *
	 * @var string
	 */
	var $recommended_php_version = '8.1';

	function __construct($html=False, $translation=False)
	{
		// setup us as $GLOBALS['egw_setup'], as this gets used in our sub-objects
		$GLOBALS['egw_setup'] =& $this;

		if (!is_object($GLOBALS['egw']))
		{
			$GLOBALS['phpgw'] = $GLOBALS['egw'] = new Egw\Base();
		}
		$this->detection = new setup_detection();
		$this->process   = new setup_process();

		/* The setup application needs these */
		if ($html) $this->html = new setup_html();
		if ($translation) $this->translation = new setup_translation();
	}

	/**
	 * include api db class for the ConfigDomain and connect to the db
	 */
	function loaddb($connect_and_setcharset=true)
	{
		if(!isset($this->ConfigDomain) || empty($this->ConfigDomain))
		{
			$this->ConfigDomain = isset($_REQUEST['ConfigDomain']) ? $_REQUEST['ConfigDomain'] : $_POST['FormDomain'];
		}
		$GLOBALS['egw_info']['server']['db_type'] = $GLOBALS['egw_domain'][$this->ConfigDomain]['db_type'];

		if ($GLOBALS['egw_info']['server']['db_type'] == 'pgsql')
		{
			$GLOBALS['egw_info']['server']['db_persistent'] = False;
		}

		try {
			$GLOBALS['egw']->db = $this->db = new Api\Db\Deprecated($GLOBALS['egw_domain'][$this->ConfigDomain]);
			$this->db->connect();
		}
		catch (Exception $e) {
			unset($e);
			return;
		}
		$this->db->set_app(Api\Db::API_APPNAME);

		if ($connect_and_setcharset)
		{
			try {
				$this->set_table_names();		// sets/checks config- and applications-table-name

				// Set the DB's client charset if a system-charset is set
				if (($this->system_charset = $this->db->select($this->config_table,'config_value',array(
						'config_app'  => 'phpgwapi',
						'config_name' => 'system_charset',
					),__LINE__,__FILE__)->fetchColumn()))
				{
					$this->db_charset_was = $this->db->Link_ID->GetCharSet();	// needed for the update

					// we can NOT set the DB charset for mysql, if the api version < 1.0.1.019, as it would mess up the DB content!!!
					if (substr($this->db->Type,0,5) === 'mysql')	// we need to check the api version
					{
						$api_version = $this->db->select($this->applications_table,'app_version',array(
								'app_name'  => 'phpgwapi',
							),__LINE__,__FILE__)->fetchColumn();
					}
					if (!$api_version || !$this->alessthanb($api_version,'1.0.1.019'))
					{
						$this->db->Link_ID->SetCharSet($this->system_charset === 'utf-8' &&
							$this->db->Type === 'mysql' ? 'utf8' : $this->system_charset);
					}
				}
			}
			catch (Api\Db\Exception $e) {
				// table might not be created at that stage
			}
		}
	}

	/**
	* Set the domain used for cookies
	*
	* @return string domain
	*/
	static function cookiedomain()
	{
		// Use HTTP_X_FORWARDED_HOST if set, which is the case behind a none-transparent proxy
		$cookie_domain = Api\Header\Http::host();

		// remove port from HTTP_HOST
		$arr = null;
		if (preg_match("/^(.*):(.*)$/",$cookie_domain,$arr))
		{
			$cookie_domain = $arr[1];
		}
		if (count(explode('.',$cookie_domain)) <= 1)
		{
			// setcookie dont likes domains without dots, leaving it empty, gets setcookie to fill the domain in
			$cookie_domain = '';
		}
		return $cookie_domain;
	}

	/**
	* Set a cookie
	*
	* @param string $cookiename name of cookie to be set
	* @param string $cookievalue value to be used, if unset cookie is cleared (optional)
	* @param int $cookietime when cookie should expire, 0 for session only (optional)
	*/
	function set_cookie($cookiename,$cookievalue='',$cookietime=0)
	{
		if(!isset($this->cookie_domain))
		{
			$this->cookie_domain = self::cookiedomain();
		}
		setcookie($cookiename, $cookievalue, $cookietime, '/', $this->cookie_domain,
			// if called via HTTPS, only send cookie for https and only allow cookie access via HTTP (true)
			Api\Header\Http::schema() === 'https', true);
	}

	/**
	 * Get configuration language from $_POST or $_SESSION and validate it
	 *
	 * @param boolean $use_accept_language =false true: use Accept-Language header as fallback
	 * @return string
	 */
	static function get_lang($use_accept_language=false)
	{
		if (isset($_POST['ConfigLang']))
		{
			$ConfigLang = $_POST['ConfigLang'];
		}
		else
		{
			if (!isset($_SESSION)) self::session_start();
			$ConfigLang = $_SESSION['ConfigLang'];
		}
		$matches = null;
		if ($use_accept_language && empty($ConfigLang) &&
			preg_match_all('/(^|,)([a-z-]+)/', strtolower($_SERVER['HTTP_ACCEPT_LANGUAGE']), $matches))
		{
			$available = Api\Translation::get_available_langs(false);
			foreach($matches[2] as $lang)
			{
				if (isset($available[$lang]))
				{
					$ConfigLang = $lang;
					break;
				}
			}
		}
		if (!preg_match('/^[a-z]{2}(-[a-z]{2})?$/',$ConfigLang))
		{
			$ConfigLang = null;	// not returning 'en', as it suppresses the language selection in check_install and manageheader
		}
		//error_log(__METHOD__."() \$_POST['ConfigLang']=".array2string($_POST['ConfigLang']).", \$_SESSION['ConfigLang']=".array2string($_SESSION['ConfigLang']).", HTTP_ACCEPT_LANGUAGE=$_SERVER[HTTP_ACCEPT_LANGUAGE] --> returning ".array2string($ConfigLang));
		return $ConfigLang;
	}

	/**
	 * Name of session cookie
	 */
	const SESSIONID = 'sessionid';

	/**
	 * Session timeout in secs (1200 = 20min)
	 */
	const TIMEOUT = 1200;

	/**
	 * Start session
	 */
	private static function session_start()
	{
		// make sure we have session extension available, otherwise fail with exception that we need it
		check_load_extension('session', true);

		switch(session_status())
		{
			case PHP_SESSION_DISABLED:
				throw new \ErrorException('EGroupware requires PHP session extension!');
			case PHP_SESSION_NONE:
				if (headers_sent()) return true;
				ini_set('session.use_cookie', true);
				session_name(self::SESSIONID);
				session_set_cookie_params(0, '/', self::cookiedomain(),
					// if called via HTTPS, only send cookie for https and only allow cookie access via HTTP (true)
					Api\Header\Http::schema() === 'https', true);

				if (isset($_COOKIE[self::SESSIONID])) session_id($_COOKIE[self::SESSIONID]);

				$ok = @session_start();	// suppress notice if session already started or warning in CLI
				break;
			case PHP_SESSION_ACTIVE:
				$ok = true;
		}
		// need to decrypt session, in case session encryption is switched on in header.inc.php
		Api\Session::decrypt();
		//error_log(__METHOD__."() returning ".array2string($ok).' _SESSION='.array2string($_SESSION));
		return $ok;
	}

	/**
	 * authenticate the setup user
	 *
	 * @param string $_auth_type ='config' 'config' or 'header' (caseinsensitiv)
	 */
	function auth($_auth_type='config')
	{
		$auth_type = strtolower($_auth_type);
		$GLOBALS['egw_info']['setup']['HeaderLoginMSG'] = $GLOBALS['egw_info']['setup']['ConfigLoginMSG'] = '';

		if (($GLOBALS['egw_info']['setup']['ConfigLoginMSG'] = self::checkip()))
		{
			return false;
		}

		if (!self::session_start() || isset($_REQUEST['FormLogout']) ||
			empty($_SESSION['egw_setup_auth_type']) || $_SESSION['egw_setup_auth_type'] != $auth_type)
		{
			//error_log(__METHOD__."('$auth_type') \$_COOKIE['".self::SESSIONID."'] = ".array2string($_COOKIE[self::SESSIONID]).", \$_SESSION=".array2string($_SESSION).", \$_POST=".array2string($_POST));
			if (isset($_REQUEST['FormLogout']))
			{
				$this->set_cookie(self::SESSIONID, '', time()-86400);
				session_destroy();
				if ($_REQUEST['FormLogout'] == 'config')
				{
					$GLOBALS['egw_info']['setup']['ConfigLoginMSG'] = lang('You have successfully logged out');
				}
				else
				{
					$GLOBALS['egw_info']['setup']['HeaderLoginMSG'] = lang('You have successfully logged out');
				}
				return false;
			}
			if (!isset($_POST['FormUser']) || !isset($_POST['FormPW']))
			{
				return false;
			}
			switch($auth_type)
			{
				case 'config':
					if (!isset($GLOBALS['egw_domain'][$_POST['FormDomain']]) ||
						!$this->check_auth($_POST['FormUser'], $_POST['FormPW'],
							$GLOBALS['egw_domain'][$_POST['FormDomain']]['config_user'],
							$GLOBALS['egw_domain'][$_POST['FormDomain']]['config_passwd']) &&
						!$this->check_auth($_POST['FormUser'], $_POST['FormPW'],
							$GLOBALS['egw_info']['server']['header_admin_user'],
							$GLOBALS['egw_info']['server']['header_admin_password']))
					{
						//error_log(__METHOD__."() Invalid password: $_POST[FormDomain]: used $_POST[FormUser]/md5($_POST[FormPW])=".md5($_POST['FormPW'])." != {$GLOBALS['egw_info']['server']['header_admin_user']}/{$GLOBALS['egw_info']['server']['header_admin_password']}");
						$GLOBALS['egw_info']['setup']['ConfigLoginMSG'] = lang('Invalid password');
						return false;
					}
					$this->ConfigDomain = $_SESSION['egw_setup_domain'] = $_POST['FormDomain'];
					break;

				default:
				case 'header':
					if (!$this->check_auth($_POST['FormUser'], $_POST['FormPW'],
								$GLOBALS['egw_info']['server']['header_admin_user'],
								$GLOBALS['egw_info']['server']['header_admin_password']))
					{
						$GLOBALS['egw_info']['setup']['ConfigLoginMSG'] = lang('Invalid password');
						return false;
					}
					break;
			}
			$_SESSION['egw_setup_auth_type'] = $auth_type;
			$_SESSION['ConfigLang'] = self::get_lang();
			$_SESSION['egw_last_action_time'] = time();
			session_regenerate_id(true);
			$this->set_cookie(self::SESSIONID, session_id(), 0);

			return true;
		}
		//error_log(__METHOD__."('$auth_type') \$_COOKIE['".self::SESSIONID."'] = ".array2string($_COOKIE[self::SESSIONID]).", \$_SESSION=".array2string($_SESSION));
		if ($_SESSION['egw_last_action_time'] < time() - self::TIMEOUT)
		{
			$this->set_cookie(self::SESSIONID, '', time()-86400);
			session_destroy();
			$GLOBALS['egw_info']['setup'][$_SESSION['egw_setup_auth_type'] == 'config' ? 'ConfigLoginMSG' : 'HeaderLoginMSG'] =
				lang('Session expired');
			return false;
		}
		if ($auth_type == 'config')
		{
			$this->ConfigDomain = $_SESSION['egw_setup_domain'];
		}
		// update session timeout
		$_SESSION['egw_last_action_time'] = time();

		// we have a valid session for $auth_type
		return true;
	}

    /**
    * check if username and password is valid
    *
    * this function compares the supplied and stored username and password
	* as any of the passwords can be clear text or md5 we convert them to md5
	* internal and compare always the md5 hashs
    *
    * @param string $user the user supplied username
    * @param string $pw the user supplied password
    * @param string $conf_user the configured username
    * @param string $hash hash to check password agains (no {prefix} for plain and md5!)
    * @returns bool true on success
    */
	static function check_auth($user, $pw, $conf_user, $hash)
	{
		if ($user !== $conf_user)
		{
			$ret = false; // wrong username
		}
		else
		{
			// support for old plain-text passwords without "{plain}" prefix
			if ($hash[0] !== '{' && !preg_match('/^[0-9a-f]{32}$/', $hash))
			{
				$hash = '{plain}'.$hash;
			}
			$ret = Api\Auth::compare_password($pw, $hash, 'md5');
		}
		//error_log(__METHOD__."('$user', '$pw', '$conf_user', '$hash') returning ".array2string($ret));
		return $ret;
	}

	/**
	 * Check for correct IP, if an IP address should be enforced
	 *
	 * @param string $remoteip
	 * @return string error-message or null on success
	 */
	public static function checkip($remoteip=null)
	{
		if (!isset($remoteip))
		{
			$remoteip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?: $_SERVER['REMOTE_ADDR'];
		}
		//echo "<p>setup::checkip($remoteip) against setup_acl='".$GLOBALS['egw_info']['server']['setup_acl']."'</p>\n";
		$allowed_ips = explode(',',@$GLOBALS['egw_info']['server']['setup_acl']);
		if(empty($GLOBALS['egw_info']['server']['setup_acl']) || !is_array($allowed_ips))
		{
			return null;	// no test
		}
		$remotes = explode('.',$remoteip);
		foreach($allowed_ips as $value)
		{
			if (!preg_match('/^[0-9.]+$/',$value))
			{
				$value = gethostbyname($was=$value);		// resolve domain-name, eg. a dyndns account
				//echo "resolving '$was' to '$value'<br>\n";
			}
			$values = explode('.',$value);
			for($i = 0; $i < count($values); ++$i)
			{
				if ((int) $values[$i] != (int) $remotes[$i])
				{
					break;
				}
			}
			if ($i == count($values))
			{
				return null;	// match
			}
		}
		error_log(__METHOD__.'-> checking IP failed:'.print_r($remoteip,true));
		return lang('Invalid IP address').' '.$remoteip;
	}

	/**
	 * Return X.X.X major version from X.X.X.X versionstring
	 *
	 * @param string $versionstring
	 * @return string major version or '' for !$versionstring, e.g. for null
	 */
	function get_major($versionstring)
	{
		if(!$versionstring)
		{
			return '';
		}

		$version = str_replace('pre','.', $versionstring);
		$varray  = explode('.', $version);
		$major   = implode('.', array_slice($varray, 0, 3));

		return $major;
	}

	/**
	 * Clear system/user level cache so as to have it rebuilt with the next access
	 *
	 * @deprecated AFAIK this code is not used anymore -- RalfBecker 2005/11/04
	 */
	function clear_session_cache()
	{
	}

	/**
	 * Add an application to the phpgw_applications table
	 *
	 * @param string $appname Application 'name' with a matching $setup_info[$appname] array slice
	 * @param $_enable =99 set to True/False to override setup.inc.php setting
	 * @param array $setup_info =null default use $GLOBALS['setup_info']
	 */
	function register_app($appname, $_enable=99, array $setup_info=null)
	{
		if (!isset($setup_info)) $setup_info = $GLOBALS['setup_info'];

		if(!$appname)
		{
			return False;
		}

		if($_enable == 99)
		{
			$_enable = $setup_info[$appname]['enable'];
		}
		$enable = (int)$_enable;

		if($GLOBALS['DEBUG'])
		{
			echo '<br>register_app(): ' . $appname . ', version: ' . $setup_info[$appname]['version'] . ', tables: ' . implode(', ',(array)$setup_info[$appname]['tables']) . '<br>';
			// _debug_array($setup_info[$appname]);
		}

		if($setup_info[$appname]['version'])
		{
			if($setup_info[$appname]['tables'])
			{
				$tables = implode(',',$setup_info[$appname]['tables']);
			}
			if ($setup_info[$appname]['tables_use_prefix'] == True)
			{
				if($GLOBALS['DEBUG'])
				{
					echo "<br>$appname uses tables_use_prefix, storing ". $setup_info[$appname]['tables_prefix']." as prefix for tables\n";
				}
				$this->db->insert($this->config_table,array(
						'config_app'	=> $appname,
						'config_name'	=> $appname.'_tables_prefix',
						'config_value'	=> $setup_info[$appname]['tables_prefix'],
					),False,__LINE__,__FILE__);
			}
			try {
				$this->db->insert($this->applications_table, [
					'app_enabled'	=> $enable,
					'app_order'		=> $setup_info[$appname]['app_order'],
					'app_tables'	=> (string)$tables,	// app_tables is NOT NULL
					'app_version'	=> $setup_info[$appname]['version'],
					'app_index'     => $setup_info[$appname]['index'],
					'app_icon'      => $setup_info[$appname]['icon'],
					'app_icon_app'  => $setup_info[$appname]['icon_app'],
				], [
					'app_name'		=> $appname,
				], __LINE__, __FILE__);
			}
			catch (Api\Db\Exception\InvalidSql $e)
			{
				// ease update from pre 1.6 eg. 1.4 not having app_index, app_icon, app_icon_app columns
				_egw_log_exception($e);

				$this->db->insert($this->applications_table,array(
						'app_name'		=> $appname,
						'app_enabled'	=> $enable,
						'app_order'		=> $setup_info[$appname]['app_order'],
						'app_tables'	=> (string)$tables,	// app_tables is NOT NULL
						'app_version'	=> $setup_info[$appname]['version'],
					),False,__LINE__,__FILE__);
			}
			Api\Egw\Applications::invalidate();
		}
	}

	/**
	 * Check if an application has info in the db
	 *
	 * @param	$appname	Application 'name' with a matching $setup_info[$appname] array slice
	 * @return boolean|null null: autoinstalled app which got uninstalled
	 */
	function app_registered($appname)
	{
		if(!$appname)
		{
			return False;
		}

		if(@$GLOBALS['DEBUG'])
		{
			echo '<br>app_registered(): checking ' . $appname . ', table: ' . $this->applications_table;
			// _debug_array($setup_info[$appname]);
		}

		if (($enabled = $this->db->select($this->applications_table, 'app_enabled', ['app_name' => $appname], __LINE__,__FILE__)->fetchColumn()) !== false)
		{
			if(@$GLOBALS['DEBUG'])
			{
				echo '... app previously registered.';
			}
			return $enabled <= -1 ? null : true;
		}
		if(@$GLOBALS['DEBUG'])
		{
			echo '... app not registered';
		}
		return False;
	}

	/**
	 * Update application info in the db
	 *
	 * @param string $appname	Application 'name' with a matching $setup_info[$appname] array slice
	 * @param array $setup_info =null default use $GLOBALS['setup_info']
	 */
	function update_app($appname, array $setup_info=null)
	{
		if (!isset($setup_info)) $setup_info = $GLOBALS['setup_info'];

		if(!$appname)
		{
			return False;
		}

		if($GLOBALS['DEBUG'])
		{
			echo '<br>update_app(): ' . $appname . ', version: ' . $setup_info[$appname]['currentver'] . ', table: ' . $this->applications_table . '<br>';
			// _debug_array($setup_info[$appname]);
		}

		if(!$this->app_registered($appname))
		{
			return False;
		}

		if($setup_info[$appname]['version'])
		{
			//echo '<br>' . $setup_info[$appname]['version'];
			if($setup_info[$appname]['tables'])
			{
				$tables = implode(',', (array)$setup_info[$appname]['tables']);
			}
			$this->db->update($this->applications_table,array(
					'app_enabled'	=> $setup_info[$appname]['enable'],
					'app_order'		=> $setup_info[$appname]['app_order'],
					'app_tables'	=> (string)$tables,	// app_tables is NOT NULL
					'app_version'	=> $setup_info[$appname]['version'],
					'app_index'     => $setup_info[$appname]['index'],
					'app_icon'      => $setup_info[$appname]['icon'],
					'app_icon_app'  => $setup_info[$appname]['icon_app'],
				),array('app_name'=>$appname),__LINE__,__FILE__);

			Api\Egw\Applications::invalidate();
		}
	}

	/**
	 * Update application version in applications table, post upgrade
	 *
	 * @param	$setup_info		 * Array of application information (multiple apps or single)
	 * @param	$appname		 * Application 'name' with a matching $setup_info[$appname] array slice
	 * @param	$tableschanged	???
	 */
	function update_app_version($setup_info, $appname, $tableschanged = True)
	{
		if(!$appname)
		{
			return False;
		}

		if($tableschanged == True)
		{
			$GLOBALS['egw_info']['setup']['tableschanged'] = True;
		}
		if($setup_info[$appname]['currentver'])
		{
			$this->db->update($this->applications_table,array(
					'app_version'	=> $setup_info[$appname]['currentver'],
				),array('app_name'=>$appname),__LINE__,__FILE__);

			Api\Egw\Applications::invalidate();
		}
		return $setup_info;
	}

	/**
	 * de-Register an application
	 *
	 * @param	$appname	Application 'name' with a matching $setup_info[$appname] array slice
	 */
	function deregister_app($appname)
	{
		if(!$appname)
		{
			return False;
		}

		// Remove categories
		$this->db->delete(Api\Categories::TABLE, array('cat_appname'=>$appname),__LINE__,__FILE__);
		Api\Categories::invalidate_cache($appname);

		// Remove config, if we are not deinstalling old phpgwapi (as that's global api config!)
		if ($appname != 'phpgwapi')
		{
			$this->db->delete(Api\Config::TABLE, array('config_app'=>$appname),__LINE__,__FILE__);
		}
		//echo 'DELETING application: ' . $appname;

		// when uninstalling an autoinstall app, we must mark it deleted in the DB, otherwise it will install again the next update
		if (file_exists($file = EGW_SERVER_ROOT.'/'.$appname.'/setup/setup.inc.php'))
		{
			$setup_info = [];
			include($file);
		}
		if (!empty($setup_info[$appname]['autoinstall']) && $setup_info[$appname]['autoinstall'] === true)
		{
			$this->db->update($this->applications_table, [
				'app_enabled' => -1,
				'app_tables'  => '',
				'app_version' => 'uninstalled',
				'app_index'   => null,
			], [
				'app_name' => $appname,
			], __LINE__, __FILE__);
		}
		else
		{
			$this->db->delete($this->applications_table, ['app_name' => $appname], __LINE__, __FILE__);
		}

		Api\Egw\Applications::invalidate();

		// unregister hooks, before removing links
		unset($GLOBALS['egw_info']['apps'][$appname]);
		Api\Hooks::read(true);

		// Remove links to the app
		Link::unlink(0, $appname);
	}

	/**
	 * Register an application's hooks
	 *
	 * @param	$appname	Application 'name' with a matching $setup_info[$appname] array slice
	 */
	function register_hooks($appname)
	{
		if(!$appname)
		{
			return False;
		}

		Api\Hooks::read(true);
	}

	/**
	 * Setup default and forced preferences, when an application gets installed
	 *
	 * @param string $appname
	 * @return boolean false app not found or no hook settings, true settings found and defaull & forced prefs stored, if there are any defined
	 */
	function set_default_preferences($appname)
	{
		$setup_info = $GLOBALS['setup_info'][$appname];

		if (!isset($setup_info) || !isset($setup_info['hooks']))
		{
			return false;	// app not found or no hook
		}
		$GLOBALS['settings'] = array();
		$hook_data = array('location' => 'settings','setup' => true);
		if (isset($setup_info['hooks']['settings']))
		{
			$settings = ExecMethod($setup_info['hooks']['settings'],$hook_data);
		}
		elseif(in_array('settings',$setup_info['hooks']) && file_exists($file = EGW_INCLUDE_ROOT.'/'.$appname.'/inc/hook_settings.inc.php'))
		{
			include_once($file);
		}
		if (!isset($settings) || !is_array($settings))
		{
			$settings = $GLOBALS['settings'];	// old file hook or not updated new hook
		}
		if (!is_array($settings) || !count($settings))
		{
			return false;
		}
		// include idots template prefs for (common) preferences
		if ($appname == 'preferences' && (file_exists($file = EGW_INCLUDE_ROOT.'/pixelegg/hook_settings.inc.php') ||
			file_exists($file = EGW_INCLUDE_ROOT.'/jdots/hook_settings.inc.php') ||
			file_exists($file = EGW_INCLUDE_ROOT.'/phpgwapi/templates/idots/hook_settings.inc.php')))
		{
			$GLOBALS['settings'] = array();
			include_once($file);
			if ($GLOBALS['settings']) $settings = array_merge($settings,$GLOBALS['settings']);
		}
		$default = $forced = array();
		foreach($settings as $name => $setting)
		{
			if (isset($setting['default']))
			{
				$default[$name] = $setting['default'] === false && $setting['type'] === 'check' ? '0' : (string)$setting['default'];
			}
			if (isset($setting['forced']))
			{
				$forced[$name] = $setting['forced'] === false && $setting['type'] === 'check' ? '0' : (string)$setting['forced'];
			}
		}
		// store default/forced preferences, if any found
		$preferences = new Api\Preferences();
		$preferences->read_repository(false);
		foreach(array(
			'default' => $default,
			'forced'  => $forced,
		) as $type => $prefs)
		{
			if ($prefs)
			{
				foreach($prefs as $name => $value)
				{
					$preferences->add($appname == 'preferences' ? 'common' : $appname, $name, $value, $type);
				}
				$preferences->save_repository(false, $type);
				//error_log(__METHOD__."('$appname') storing ".($owner==preferences::DEFAULT_ID?'default':'forced')." prefs=".array2string($prefs));
			}
		}
		return true;
	}

	/**
	  * call the hooks for a single application
	  *
	  * @param $location hook location - required
	  * @param $appname application name - optional
	 */
	static function hook($location, $appname='')
	{
		return Api\Hooks::single($location,$appname,True,True);
	}

	/**
	 * egw version checking, is param 1 < param 2 in phpgw versionspeak?
	 * @param	$a	phpgw version number to check if less than $b
	 * @param	$b	phpgw version number to check $a against
	 * @return	True if $a < $b
	 */
	function alessthanb($a,$b,$DEBUG=False)
	{
		$num = array('1st','2nd','3rd','4th');

		if($DEBUG)
		{
			echo'<br>Input values: '
				. 'A="'.$a.'", B="'.$b.'"';
		}
		$newa = str_replace('pre','.', $a ?? '');
		$newb = str_replace('pre','.', $b ?? '');
		$testa = explode('.',$newa);
		if(@$testa[1] == '')
		{
			$testa[1] = 0;
		}

		$testb = explode('.',$newb);
		if(@$testb[1] == '')
		{
			$testb[1] = 0;
		}
		if(@$testb[3] == '')
		{
			$testb[3] = 0;
		}
		$less = 0;

		for($i=0;$i<count($testa);$i++)
		{
			if($DEBUG) { echo'<br>Checking if '. (int)$testa[$i] . ' is less than ' . (int)$testb[$i] . ' ...'; }
			if((int)$testa[$i] < (int)$testb[$i])
			{
				if ($DEBUG) { echo ' yes.'; }
				$less++;
				if($i<3)
				{
					/* Ensure that this is definitely smaller */
					if($DEBUG) { echo"  This is the $num[$i] octet, so A is definitely less than B."; }
					$less = 5;
					break;
				}
			}
			elseif((int)$testa[$i] > (int)$testb[$i])
			{
				if($DEBUG) { echo ' no.'; }
				$less--;
				if($i<2)
				{
					/* Ensure that this is definitely greater */
					if($DEBUG) { echo"  This is the $num[$i] octet, so A is definitely greater than B."; }
					$less = -5;
					break;
				}
			}
			else
			{
				if($DEBUG) { echo ' no, they are equal or of different length.'; }
				// makes sure eg. '1.0.0' is counted less the '1.0.0.xxx' !
				$less = count($testa) < count($testb) ? 1 : 0;
			}
		}
		if($DEBUG) { echo '<br>Check value is: "'.$less.'"'; }
		if($less>0)
		{
			if($DEBUG) { echo '<br>A is less than B'; }
			return True;
		}
		elseif($less<0)
		{
			if($DEBUG) { echo '<br>A is greater than B'; }
			return False;
		}
		else
		{
			if($DEBUG) { echo '<br>A is equal to B'; }
			return False;
		}
	}

	/**
	 * egw version checking, is param 1 > param 2 in phpgw versionspeak?
	 *
	 * @param	$a	phpgw version number to check if more than $b
	 * @param	$b	phpgw version number to check $a against
	 * @return	True if $a < $b
	 */
	function amorethanb($a,$b,$DEBUG=False)
	{
		$num = array('1st','2nd','3rd','4th');

		if($DEBUG)
		{
			echo'<br>Input values: '
				. 'A="'.$a.'", B="'.$b.'"';
		}
		$newa = str_replace('pre','.',$a);
		$newb = str_replace('pre','.',$b);
		$testa = explode('.',$newa);
		if($testa[3] == '')
		{
			$testa[3] = 0;
		}
		$testb = explode('.',$newb);
		if($testb[3] == '')
		{
			$testb[3] = 0;
		}
		$less = 0;

		for($i=0;$i<count($testa);$i++)
		{
			if($DEBUG) { echo'<br>Checking if '. (int)$testa[$i] . ' is more than ' . (int)$testb[$i] . ' ...'; }
			if((int)$testa[$i] > (int)$testb[$i])
			{
				if($DEBUG) { echo ' yes.'; }
				$less++;
				if($i<3)
				{
					/* Ensure that this is definitely greater */
					if($DEBUG) { echo"  This is the $num[$i] octet, so A is definitely greater than B."; }
					$less = 5;
					break;
				}
			}
			elseif((int)$testa[$i] < (int)$testb[$i])
			{
				if($DEBUG) { echo ' no.'; }
				$less--;
				if($i<2)
				{
					/* Ensure that this is definitely smaller */
					if($DEBUG) { echo"  This is the $num[$i] octet, so A is definitely less than B."; }
					$less = -5;
					break;
				}
			}
			else
			{
				if($DEBUG) { echo ' no, they are equal.'; }
				$less = 0;
			}
		}
		if($DEBUG) { echo '<br>Check value is: "'.$less.'"'; }
		if($less>0)
		{
			if($DEBUG) { echo '<br>A is greater than B'; }
			return True;
		}
		elseif($less<0)
		{
			if($DEBUG) { echo '<br>A is less than B'; }
			return False;
		}
		else
		{
			if($DEBUG) { echo '<br>A is equal to B'; }
			return False;
		}
	}

	/**
	 * Own instance of the accounts class
	 *
	 * @var Api\Accounts
	 */
	var $accounts;

	function setup_account_object(array $config=array())
	{
		if (!isset($this->accounts) || $this->accounts->config || $config)
		{
			if (!is_object($this->db))
			{
				$this->loaddb();
			}
			if (!$config)
			{
				// load the configuration from the database
				foreach($this->db->select($this->config_table,'config_name,config_value',
					"config_name LIKE 'ads%' OR config_name LIKE 'ldap%' OR config_name LIKE 'account_%' OR config_name LIKE '%encryption%' OR config_name='auth_type'",
					__LINE__,__FILE__) as $row)
				{
					$GLOBALS['egw_info']['server'][$row['config_name']] = $config[$row['config_name']] = $row['config_value'];
				}
			}
			try {
				$this->accounts = new Api\Accounts($config);
			}
			catch (Exception $e) {
				echo "<p><b>".$e->getMessage()."</b></p>\n";
				return false;
			}
			if (!isset($GLOBALS['egw']->accounts)) $GLOBALS['egw']->accounts = $this->accounts;
			Api\Accounts::cache_invalidate();	// the cache is shared for all instances of the class
		}
		return true;
	}

	/**
	 * Used to store and share password of user "anonymous" between calls to add_account from different app installs
	 * @var unknown
	 */
	protected $anonpw;

	/**
	 * add an user account or a user group
	 *
	 * If the $username already exists, only the id is returned, no new user / group gets created,
	 * but if a non-empty password is given it will be changed to that.
	 *
	 * @param string $username alphanumerical username or groupname (account_lid)
	 * @param string $first first name
	 * @param string $last last name
	 * @param string $_passwd cleartext pw or empty or '*unchanged*', to not change pw for existing users
	 * @param string/boolean $primary_group Groupname for users primary group or False for a group, default 'Default'
	 * @param boolean $changepw user has right to change pw, default False = Pw change NOT allowed
	 * @param string $email
	 * @param string &$anonpw=null on return password for anonymous user
	 * @return int the numerical user-id
	 */
	function add_account($username,$first,$last,$_passwd,$primary_group='Default',$changepw=False,$email='',&$anonpw=null)
	{
		$this->setup_account_object();

		$primary_group_id = $primary_group ? $this->accounts->name2id($primary_group) : False;

		$passwd = $_passwd;
		if ($username == 'anonymous')
		{
			if (!isset($this->anonpw)) $this->anonpw = Api\Auth::randomstring(16);
			$passwd = $anonpw = $this->anonpw;
		}

		if(!($accountid = $this->accounts->name2id($username, 'account_lid', $primary_group ? 'u' : 'g')))
		{
			$account = array(
				'account_type'      => $primary_group ? 'u' : 'g',
				'account_lid'       => $username,
				'account_passwd'    => $passwd,
				'account_firstname' => $first,
				'account_lastname'  => $last,
				'account_status'    => 'A',
				'account_primary_group' => $primary_group_id,
				'account_expires'   => -1,
				'account_email'     => $email,
				'account_members'   => array()
			);

			// check if egw_accounts.account_description already exists, as the update otherwise fails
			$meta = $GLOBALS['egw_setup']->db->metadata('egw_accounts', true);
			if (!isset($meta['meta']['account_description']))
			{
				$GLOBALS['egw_setup']->oProc->AddColumn('egw_accounts','account_description',array(
					'type' => 'varchar',
					'precision' => '255',
					'comment' => 'group description'
				));
			}

			if (!($accountid = $this->accounts->save($account)))
			{
				error_log("setup::add_account('$username','$first','$last',\$passwd,'$primary_group',$changepw,'$email') failed! accountid=$accountid");
				return false;
			}
		}
		// set password for existing account, if given and not '*unchanged*'
		elseif($_passwd && $_passwd != '*unchanged*')
		{
			try {
				$auth = new Api\Auth;
				$pw_changed = $auth->change_password(null, $passwd, $accountid);
			}
			catch (Exception $e)
			{
				unset($e);
				$pw_changed = false;
			}
			if (!$pw_changed)
			{
				error_log("setup::add_account('$username','$first','$last',\$passwd,'$primary_group',$changepw,'$email') changin password of exisiting account #$accountid failed!");
				return false;
			}
		}
		// call Vfs\Hooks::add{account|group} hook to create the vfs-home-dirs
		// calling general add{account|group} hook fails, as we are only in setup
		// --> setup_cmd_admin execs "admin/admin-cli.php --edit-user" to run them
		if ($primary_group)
		{
			Vfs\Hooks::addAccount(array(
				'account_id' => $accountid,
				'account_lid' => $username,
			));
		}
		else
		{
			Vfs\Hooks::addGroup(array(
				'account_id' => $accountid,
				'account_lid' => $username,
			));
		}
		if ($primary_group)	// only for users, NOT groups
		{
			$this->set_memberships(array($primary_group_id), $accountid);

			if (!$changepw) $this->add_acl('preferences','nopasswordchange',$accountid);
		}
		//error_log("setup::add_account('$username','$first','$last',\$passwd,'$primary_group',$changepw,'$email') successfull created accountid=$accountid");
		return $accountid;
	}

	/**
	 * Adding memberships to an account (keeping existing ones!)
	 *
	 * @param array $_groups array of group-id's to add
	 * @param int $user account_id
	 */
	function set_memberships($_groups, $user)
	{
		$this->setup_account_object();

		if (!($groups = $this->accounts->memberships($user, true)))
		{
			$groups = $_groups;
		}
		else
		{
			$groups = array_unique(array_merge($groups, $_groups));
		}
		$this->accounts->set_memberships($groups, $user);
	}

	/**
	 * Check if accounts other then the automatically installed anonymous account exist
	 *
	 * We check via the account object, to deal with different account-storages
	 *
	 * @return boolean
	 */
	function accounts_exist()
	{
		try {
			if (!$this->setup_account_object()) return false;

			$this->accounts->search(array(
				'type'   => 'accounts',
				'start'  => 0,
				'offset' => 2	// we only need to check 2 accounts, if we just check for not anonymous
			));

			if ($this->accounts->total != 1)
			{
				return $this->accounts->total > 1;
			}

			// one account, need to check it's not the anonymous one
			$this->accounts->search(array(
				'type'   => 'accounts',
				'start'  => 0,
				'offset' => 0,
				'query_type' => 'lid',
				'query'  => 'anonymous',
			));
		}
		catch (Api\Db\Exception $e) {
			_egw_log_exception($e);
			return false;
		}
		return !$this->accounts->total;
	}

	/**
	 * Add ACL rights
	 *
	 * Dont use it to set group-membership, use set_memberships instead!
	 *
	 * @param string|array $apps app-names
	 * @param string $location eg. "run"
	 * @param int|string $account accountid or account_lid
	 * @param int $rights rights to set, default 1
	 */
	function add_acl($apps,$location,$account,$rights=1)
	{
		//error_log("setup::add_acl(".(is_array($apps) ? "array('".implode("','",$apps)."')" : "'$apps'").",'$location',$account,$rights)");
		if (!is_numeric($account))
		{
			$this->setup_account_object();
			$account = $this->accounts->name2id($account);
		}
		if(!is_object($this->db))
		{
			$this->loaddb();
		}

		if(!is_array($apps))
		{
			$apps = array($apps);
		}
		foreach($apps as $app)
		{
			$this->db->delete($this->acl_table,array(
				'acl_appname'  => $app,
				'acl_location' => $location,
				'acl_account'  => $account
			),__LINE__,__FILE__);

			if ((int) $rights)
			{
				$this->db->insert($this->acl_table,array(
					'acl_rights' => $rights
				),array(
					'acl_appname'  => $app,
					'acl_location' => $location,
					'acl_account'  => $account,
				),__LINE__,__FILE__);
			}
		}
	}

	/**
	 * checks if one of the given tables exist, returns the first match
	 *
	 * @param array $tables array with possible table-names
	 * @return string/boolean tablename or false
	 */
	function table_exist($tables,$force_refresh=False)
	{
		static $table_names = False;

		if(!is_object($this->db)) $this->loaddb();

		if (!$table_names || $force_refresh) $table_names = $this->db->table_names();

		if (!$table_names) return false;

		foreach($table_names as $data)
		{
			if (($key = array_search($data['table_name'],$tables)) !== false)
			{
				return $tables[$key];
			}
		}
		return false;
	}

	/**
	 * Checks and set the names of the tables, which get accessed before an update: eg. config- and applications-table
	 *
	 * Other tables can always use the most up to date name
	 */
	function set_table_names($force_refresh=False)
	{
		foreach(array(
			'config_table'       => array('egw_config','phpgw_config','config'),
			'applications_table' => array('egw_applications','phpgw_applications','applications'),
			'accounts_table'     => array('egw_accounts','phpgw_accounts'),
			'acl_table'          => array('egw_acl','phpgw_acl'),
			'lang_table'         => array('egw_lang','phpgw_lang','lang'),
			'languages_table'    => array('egw_languages','phpgw_languages','languages'),
		) as $name => $tables)
		{
			$table = $this->table_exist($tables,$force_refresh);

			if ($table && $table != $this->$name)	// only overwrite the default name, if we realy got one (important for new installs)
			{
				$this->$name = $table;
			}
			//echo "<p>setup::set_table_names: $name = '{$this->$name}'</p>\n";
		}
	}
}