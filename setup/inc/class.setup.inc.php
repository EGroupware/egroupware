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
 * @version $Id$
 */

class setup
{
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
	var $oProc;
	var $cookie_domain;

	/**
	 * Instance of the hooks class
	 *
	 * @var hooks
	 */
	var $hooks;

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

	var $system_charset;
	var $lang;

	var $ConfigDomain;

	/* table name vars */
	var $tbl_apps;
	var $tbl_config;
	var $tbl_hooks;

	/**
	 * php version required by eGroupware
	 *
	 * @var float
	 */
	var $required_php_version = 5.2;
	/**
	 * PHP Version recommended for eGroupware
	 *
	 * @var string
	 */
	var $recommended_php_version = '5.3+';

	function setup($html=False, $translation=False)
	{
		// setup us as $GLOBALS['egw_setup'], as this gets used in our sub-objects
		$GLOBALS['egw_setup'] =& $this;

		if (!is_object($GLOBALS['egw']))
		{
			require_once(EGW_API_INC.'/class.egw.inc.php');
			$GLOBALS['phpgw'] = $GLOBALS['egw'] = new egw_minimal();
		}
		$this->detection = new setup_detection();
		$this->process   = new setup_process();

		if ($_REQUEST['system_charset']) $this->system_charset = $_REQUEST['system_charset'];

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
			$this->ConfigDomain = get_var('ConfigDomain',array('COOKIE','POST'),$_POST['FormDomain']);
		}
		$GLOBALS['egw_info']['server']['db_type'] = $GLOBALS['egw_domain'][$this->ConfigDomain]['db_type'];

		if ($GLOBALS['egw_info']['server']['db_type'] == 'pgsql')
		{
			$GLOBALS['egw_info']['server']['db_persistent'] = False;
		}

		try {
			$GLOBALS['egw']->db = $this->db = new egw_db($GLOBALS['egw_domain'][$this->ConfigDomain]);
			$this->db->connect();
		}
		catch (Exception $e) {
			return;
		}
		$this->db->set_app('phpgwapi');

		if ($connect_and_setcharset)
		{
			$this->db->Halt_On_Error = 'no';	// table might not be created at that stage

			$this->set_table_names();		// sets/checks config- and applications-table-name

			// Set the DB's client charset if a system-charset is set
			$this->db->select($this->config_table,'config_value',array(
				'config_app'  => 'phpgwapi',
				'config_name' => 'system_charset',
			),__LINE__,__FILE__);
			if ($this->db->next_record() && $this->db->f(0))
			{
				$this->system_charset = $this->db->f(0);
				$this->db_charset_was = $this->db->Link_ID->GetCharSet();	// needed for the update

				// we can NOT set the DB charset for mysql, if the api version < 1.0.1.019, as it would mess up the DB content!!!
				if (substr($this->db->Type,0,5) == 'mysql')	// we need to check the api version
				{
					$this->db->select($this->applications_table,'app_version',array(
						'app_name'  => 'phpgwapi',
					),__LINE__,__FILE__);
					$api_version = $this->db->next_record() ? $this->db->f(0) : false;
				}
				if (!$api_version || !$this->alessthanb($api_version,'1.0.1.019'))
				{
					$this->db->Link_ID->SetCharSet($this->system_charset);
				}
			}
			$this->db->Halt_On_Error = 'yes';	// setting the default again
		}
	}

	/**
	* Set the domain used for cookies
	*
	* @return string domain
	*/
	function set_cookiedomain()
	{
		// Use HTTP_X_FORWARDED_HOST if set, which is the case behind a none-transparent proxy
		$this->cookie_domain = isset($_SERVER['HTTP_X_FORWARDED_HOST']) ?  $_SERVER['HTTP_X_FORWARDED_HOST'] : $_SERVER['HTTP_HOST'];

		// remove port from HTTP_HOST
		if (preg_match("/^(.*):(.*)$/",$this->cookie_domain,$arr))
		{
			$this->cookie_domain = $arr[1];
		}
		if (count(explode('.',$this->cookie_domain)) <= 1)
		{
			// setcookie dont likes domains without dots, leaving it empty, gets setcookie to fill the domain in
			$this->cookie_domain = '';
		}
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
			$this->set_cookiedomain();
		}
		setcookie($cookiename,$cookievalue,$cookietime,'/',$this->cookie_domain);
	}

	/**
	 * Get configuration language from $_POST or $_COOKIE and validate it
	 *
	 * @return string
	 */
	static function get_lang()
	{
		$ConfigLang   = get_var('ConfigLang',  array('POST','COOKIE'));
		if (preg_match('/^[a-z]{2}(-[a-z]{2})?$/',$ConfigLang))
		{
			return $ConfigLang;
		}
		return null;	// not returning 'en', as it suppresses the language selection in check_install and manageheader
	}

	/**
	 * authenticate the setup user
	 *
	 * @param	$auth_type	???
	 */
	function auth($auth_type='Config')
	{
		#phpinfo();
		$FormLogout = get_var('FormLogout',  array('GET','POST'));
		$ConfigLang   = self::get_lang();
		if(!$FormLogout)
		{
			$ConfigLogin  = get_var('ConfigLogin', array('POST'));
			$HeaderLogin  = get_var('HeaderLogin', array('POST'));
			$FormDomain   = get_var('FormDomain',  array('POST'));
			$FormUser     = get_var('FormUser',    array('POST'));
			$FormPW       = get_var('FormPW',      array('POST'));

			$this->ConfigDomain = get_var('ConfigDomain',array('POST','COOKIE'));
			$ConfigUser   = get_var('ConfigUser',  array('POST','COOKIE'));
			$ConfigPW     = get_var('ConfigPW',    array('POST','COOKIE'));
			$HeaderUser   = get_var('HeaderUser',  array('POST','COOKIE'));
			$HeaderPW     = get_var('HeaderPW',    array('POST','COOKIE'));

			/* Setup defaults to aid in header upgrade to version 1.26.
			 * This was the first version to include the following values.
			 */
			if(!@isset($GLOBALS['egw_domain'][$FormDomain]['config_user']) && isset($GLOBALS['egw_domain'][$FormDomain]))
			{
				@$GLOBALS['egw_domain'][$FormDomain]['config_user'] = 'admin';
			}
			if(!@isset($GLOBALS['egw_info']['server']['header_admin_user']))
			{
				@$GLOBALS['egw_info']['server']['header_admin_user'] = 'admin';
			}
		}

		$remoteip   = $_SERVER['REMOTE_ADDR'];
		if(!empty($remoteip) && !$this->checkip($remoteip)) { return False; }

		/* If FormLogout is set, simply invalidate the cookies (LOGOUT) */
		switch(strtolower($FormLogout))
		{
			case 'config':
				/* config logout */
				$expire = time() - 86400;
				$this->set_cookie('ConfigUser','',$expire,'/');
				$this->set_cookie('ConfigPW','',$expire,'/');
				$this->set_cookie('ConfigDomain','',$expire,'/');
				$GLOBALS['egw_info']['setup']['LastDomain'] = $_COOKIE['ConfigDomain'];
				$GLOBALS['egw_info']['setup']['ConfigLoginMSG'] = lang('You have successfully logged out');
				$GLOBALS['egw_info']['setup']['HeaderLoginMSG'] = '';
				return False;
			case 'header':
				/* header admin logout */
				$expire = time() - 86400;
				$this->set_cookie('HeaderUser','',$expire,'/');
				$this->set_cookie('HeaderPW','',$expire,'/');
				$GLOBALS['egw_info']['setup']['HeaderLoginMSG'] = lang('You have successfully logged out');
				$GLOBALS['egw_info']['setup']['ConfigLoginMSG'] = '';
				return False;
		}

		/* We get here if FormLogout is not set (LOGIN or subsequent pages) */
		/* Expire login if idle for 20 minutes.  The cookies are updated on every page load. */
		$expire = (int)(time() + (1200*9));

		switch(strtolower($auth_type))
		{
			case 'header':
				if(!empty($HeaderLogin))
				{
					/* header admin login */
					/* New test is md5, cleartext version is for header < 1.26 */
					if ($this->check_auth($FormUser,$FormPW,$GLOBALS['egw_info']['server']['header_admin_user'],
						$GLOBALS['egw_info']['server']['header_admin_password']))
					{
						$this->set_cookie('HeaderUser',$FormUser,$expire,'/');
						$this->set_cookie('HeaderPW',md5($FormPW),$expire,'/');
						$this->set_cookie('ConfigLang',$ConfigLang,$expire,'/');
						return True;
					}
					else
					{
						$GLOBALS['egw_info']['setup']['HeaderLoginMSG'] = lang('Invalid password');
						$GLOBALS['egw_info']['setup']['ConfigLoginMSG'] = '';
						return False;
					}
				}
				elseif(!empty($HeaderPW) && $auth_type == 'Header')
				{
					// Returning after login to header admin
					/* New test is md5, cleartext version is for header < 1.26 */
					if ($this->check_auth($HeaderUser,$HeaderPW,$GLOBALS['egw_info']['server']['header_admin_user'],
						$GLOBALS['egw_info']['server']['header_admin_password']))
					{
						$this->set_cookie('HeaderUser',$HeaderUser,$expire,'/');
						$this->set_cookie('HeaderPW',$HeaderPW,$expire,'/');
						$this->set_cookie('ConfigLang',$ConfigLang,$expire,'/');
						return True;
					}
					else
					{
						$GLOBALS['egw_info']['setup']['HeaderLoginMSG'] = lang('Invalid password');
						$GLOBALS['egw_info']['setup']['ConfigLoginMSG'] = '';
						return False;
					}
				}
				break;
			case 'config':
				if(!empty($ConfigLogin))
				{
					/* config login */
					/* New test is md5, cleartext version is for header < 1.26 */
					if (isset($GLOBALS['egw_domain'][$FormDomain]) &&
						$this->check_auth($FormUser,$FormPW,@$GLOBALS['egw_domain'][$FormDomain]['config_user'],
						@$GLOBALS['egw_domain'][$FormDomain]['config_passwd']))
					{
						$this->set_cookie('ConfigUser',$FormUser,$expire,'/');
						$this->set_cookie('ConfigPW',md5($FormPW),$expire,'/');
						$this->set_cookie('ConfigDomain',$FormDomain,$expire,'/');
						/* Set this now since the cookie will not be available until the next page load */
						$this->ConfigDomain = $FormDomain;
						$this->set_cookie('ConfigLang',$ConfigLang,$expire,'/');
						return True;
					}
					else
					{
						$GLOBALS['egw_info']['setup']['ConfigLoginMSG'] = lang('Invalid password');
						$GLOBALS['egw_info']['setup']['HeaderLoginMSG'] = '';
						return False;
					}
				}
				elseif(!empty($ConfigPW))
				{
					// Returning after login to config
					/* New test is md5, cleartext version is for header < 1.26 */
					if ($this->check_auth($ConfigUser,$ConfigPW,@$GLOBALS['egw_domain'][$this->ConfigDomain]['config_user'],
						@$GLOBALS['egw_domain'][$this->ConfigDomain]['config_passwd']))
					{
						$this->set_cookie('ConfigUser',$ConfigUser,$expire,'/');
						$this->set_cookie('ConfigPW',$ConfigPW,$expire,'/');
						$this->set_cookie('ConfigDomain',$this->ConfigDomain,$expire,'/');
						$this->set_cookie('ConfigLang',$ConfigLang,$expire,'/');
						return True;
					}
					else
					{
						$GLOBALS['egw_info']['setup']['ConfigLoginMSG'] = lang('Invalid password');
						$GLOBALS['egw_info']['setup']['HeaderLoginMSG'] = '';
						return False;
					}
				}
				break;
		}

		return False;
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
    * @param string $conf_pw the configured password
    * @returns bool
    */
	function check_auth($user,$pw,$conf_user,$conf_pw)
	{
		#echo "<p>setup::check_auth('$user','$pw','$conf_user','$conf_pw')</p>\n";exit;
		if ($user != $conf_user)
		{
			return False; // wrong username
		}

		// Verify that $pw is not already encoded as md5
		if(!preg_match('/^[0-9a-f]{32}$/',$conf_pw))
		{
			$conf_pw = md5($conf_pw);
		}


		// Verify that $pw is not already encoded as md5
		if(!preg_match('/^[0-9a-f]{32}$/',$pw))
		{
			$pw = md5($pw);
		}

		return $pw == $conf_pw;

	}

	function checkip($remoteip='')
	{
		//echo "<p>setup::checkip($remoteip) against setup_acl='".$GLOBALS['egw_info']['server']['setup_acl']."'</p>\n";
		$allowed_ips = explode(',',@$GLOBALS['egw_info']['server']['setup_acl']);
		if(empty($GLOBALS['egw_info']['server']['setup_acl']) || !is_array($allowed_ips))
		{
			return True;	// no test
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
				return True;	// match
			}
		}
		$GLOBALS['egw_info']['setup']['HeaderLoginMSG'] = '';
		$GLOBALS['egw_info']['setup']['ConfigLoginMSG'] = lang('Invalid IP address');
		error_log(__METHOD__.'-> checking IP failed:'.print_r($remoteip,true));
		return False;
	}

	/**
	 * Return X.X.X major version from X.X.X.X versionstring
	 *
	 * @param	$
	 */
	function get_major($versionstring)
	{
		if(!$versionstring)
		{
			return False;
		}

		$version = str_replace('pre','.',$versionstring);
		$varray  = explode('.',$version);
		$major   = implode('.',array($varray[0],$varray[1],$varray[2]));

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
	 * @param	$appname	Application 'name' with a matching $setup_info[$appname] array slice
	 * @param	$enable		 * optional, set to True/False to override setup.inc.php setting
	 */
	function register_app($appname,$enable=99)
	{
		$setup_info = $GLOBALS['setup_info'];

		if(!$appname)
		{
			return False;
		}

		if($enable==99)
		{
			$enable = $setup_info[$appname]['enable'];
		}
		$enable = (int)$enable;

		if($GLOBALS['DEBUG'])
		{
			echo '<br>register_app(): ' . $appname . ', version: ' . $setup_info[$appname]['version'] . ', table: ' . $appstbl . '<br>';
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
			$this->db->insert($this->applications_table,array(
					'app_name'		=> $appname,
					'app_enabled'	=> $enable,
					'app_order'		=> $setup_info[$appname]['app_order'],
					'app_tables'	=> $tables,
					'app_version'	=> $setup_info[$appname]['version'],
					'app_index'     => $setup_info[$appname]['index'],
					'app_icon'      => $setup_info[$appname]['icon'],
					'app_icon_app'  => $setup_info[$appname]['icon_app'],
				),False,__LINE__,__FILE__);

			$this->clear_session_cache();
		}
	}

	/**
	 * Check if an application has info in the db
	 *
	 * @param	$appname	Application 'name' with a matching $setup_info[$appname] array slice
	 * @param	$enabled	optional, set to False to not enable this app
	 */
	function app_registered($appname)
	{
		$setup_info = $GLOBALS['setup_info'];

		if(!$appname)
		{
			return False;
		}

		if(@$GLOBALS['DEBUG'])
		{
			echo '<br>app_registered(): checking ' . $appname . ', table: ' . $this->applications_table;
			// _debug_array($setup_info[$appname]);
		}

		$this->db->select($this->applications_table,'COUNT(*)',array('app_name' => $appname),__LINE__,__FILE__);
		if($this->db->next_record() && $this->db->f(0))
		{
			if(@$GLOBALS['DEBUG'])
			{
				echo '... app previously registered.';
			}
			return True;
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
	 * @param	$appname	Application 'name' with a matching $setup_info[$appname] array slice
	 * @param	$enabled	optional, set to False to not enable this app
	 */
	function update_app($appname)
	{
		$setup_info = $GLOBALS['setup_info'];

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
				$tables = implode(',',$setup_info[$appname]['tables']);
			}
			$this->db->update($this->applications_table,array(
					'app_enabled'	=> $setup_info[$appname]['enable'],
					'app_order'		=> $setup_info[$appname]['app_order'],
					'app_tables'	=> $tables,
					'app_version'	=> $setup_info[$appname]['version'],
					'app_index'     => $setup_info[$appname]['index'],
					'app_icon'      => $setup_info[$appname]['icon'],
					'app_icon_app'  => $setup_info[$appname]['icon_app'],
				),array('app_name'=>$appname),__LINE__,__FILE__);
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
		$setup_info = $GLOBALS['setup_info'];

		// Remove categories
		$this->db->delete(categories::TABLE, array('cat_appname'=>$appname),__LINE__,__FILE__);
		// Remove config
		$this->db->delete(config::TABLE, array('config_app'=>$appname),__LINE__,__FILE__);
		//echo 'DELETING application: ' . $appname;
		$this->db->delete($this->applications_table,array('app_name'=>$appname),__LINE__,__FILE__);
		$this->clear_session_cache();
	}

	/**
	 * Register an application's hooks
	 *
	 * @param	$appname	Application 'name' with a matching $setup_info[$appname] array slice
	 */
	function register_hooks($appname)
	{
		$setup_info = $GLOBALS['setup_info'];

		if(!$appname)
		{
			return False;
		}

		if(!$this->hooks_table)	// No hooks table yet
		{
			return False;
		}

		if (!is_object($this->hooks))
		{
			$this->hooks =& CreateObject('phpgwapi.hooks',$this->db,$this->hooks_table);
		}
		$this->hooks->register_hooks($appname,$setup_info[$appname]['hooks']);
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
		if (isset($setup_info['hooks']['settings']))
		{
			$settings = ExecMethod($setup_info['hooks']['settings'],array('location' => 'settings','setup' => true));
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
		if ($appname == 'preferences' && file_exists($file = EGW_INCLUDE_ROOT.'/phpgwapi/templates/idots/hook_settings.inc.php'))
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
				$default[$name] = (string)$setting['default'];
			}
			if (isset($setting['forced']))
			{
				$forced[$name] = (string)$setting['forced'];
			}
		}
		// store default/forced preferences, if any found
		$preferences = new preferences();
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
	 * Update an application's hooks
	 *
	 * @param	$appname	Application 'name' with a matching $setup_info[$appname] array slice
	 */
	function update_hooks($appname)
	{
		$this->register_hooks($appname);
	}

	/**
	 * de-Register an application's hooks
	 *
	 * @param	$appname	Application 'name' with a matching $setup_info[$appname] array slice
	 * @return boolean|int false on error or number of removed hooks
	 */
	function deregister_hooks($appname)
	{
		if(!$this->hooks_table)	// No hooks table yet
		{
			return False;
		}

		if(!$appname)
		{
			return False;
		}

		//echo "DELETING hooks for: " . $setup_info[$appname]['name'];
		if (!is_object($this->hooks))
		{
			$this->hooks =& CreateObject('phpgwapi.hooks',$this->db,$this->hooks_table);
		}
		return $this->hooks->register_hooks($appname);
	}

	/**
	  * call the hooks for a single application
	  *
	  * @param $location hook location - required
	  * @param $appname application name - optional
	 */
	function hook($location, $appname='')
	{
		if (!is_object($this->hooks))
		{
			$this->hooks =& CreateObject('phpgwapi.hooks',$this->db,$this->hooks_table);
		}
		return $this->hooks->single($location,$appname,True,True);
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
		$newa = str_replace('pre','.',$a);
		$newb = str_replace('pre','.',$b);
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
	 * @var accounts
	 */
	var $accounts;

	function setup_account_object(array $config=array())
	{
		if (!isset($this->accounts) || $config)
		{
			if (!is_object($this->db))
			{
				$this->loaddb();
			}
			if (!$config)
			{
				// load the configuration from the database
				$this->db->select($this->config_table,'config_name,config_value',
					"config_name LIKE 'ldap%' OR config_name LIKE 'account_%' OR config_name LIKE '%encryption%' OR config_name='auth_type'",__LINE__,__FILE__);

				while(($row = $this->db->row(true)))
				{
					$GLOBALS['egw_info']['server'][$row['config_name']] = $config[$row['config_name']] = $row['config_value'];
				}
			}
			$this->accounts = new accounts($config);
			if (!isset($GLOBALS['egw']->accounts)) $GLOBALS['egw']->accounts = $this->accounts;
			accounts::cache_invalidate();	// the cache is shared for all instances of the class

			if($this->accounts->backend instanceof accounts_ldap && !$this->accounts->backend->ds)
			{
				printf("<b>Error: Error connecting to LDAP server %s!</b><br>",$config['ldap_host']);
				return false;
			}
		}
		return true;
	}

	/**
	 * add an user account or a user group
	 *
	 * if the $username already exists, only the id is returned, no new user / group gets created
	 *
	 * @param string $username alphanumerical username or groupname (account_lid)
	 * @param string $first first name
	 * @param string $last last name
	 * @param $passwd string cleartext pw
	 * @param string/boolean $primary_group Groupname for users primary group or False for a group, default 'Default'
	 * @param boolean $changepw user has right to change pw, default False = Pw change NOT allowed
	 * @param string $email
	 * @return int the numerical user-id
	 */
	function add_account($username,$first,$last,$passwd,$primary_group='Default',$changepw=False,$email='')
	{
		$this->setup_account_object();

		$primary_group_id = $primary_group ? $this->accounts->name2id($primary_group) : False;

		if(!($accountid = $this->accounts->name2id($username)))
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
				'account_members'   => ''
			);
			if (!($accountid = $this->accounts->save($account)))
			{
				error_log("setup::add_account('$username','$first','$last',\$passwd,'$primary_group',$changepw,'$email') failed! accountid=$accountid");
				return false;
			}
			// call vfs_home_hooks::add{account|group} hook to create the vfs-home-dirs
			// calling general add{account|group} hook fails, as we are only in setup
			// --> setup_cmd_admin execs "admin/admin-cli.php --edit-user" to run them
			if ($primary_group)
			{
				vfs_home_hooks::addAccount($account);
/*
 				$GLOBALS['hook_values'] = $account + array('new_passwd' => $account['account_passwd']);
				$GLOBALS['egw']->hooks->process($GLOBALS['hook_values']+array(
					'location' => 'addaccount'
				),False,True);	// called for every app now, not only enabled ones
*/
			}
			else
			{
				vfs_home_hooks::addGroup($account+array('account_name' => $account['account_lid']));
/*
				$GLOBALS['hook_values'] = $account+(array('account_name' => $account['account_lid']));
				$GLOBALS['egw']->hooks->process($GLOBALS['hook_values']+array(
					'location' => 'addgroup'
				),False,True);  // called for every app now, not only enabled ones)
*/
			}
		}
		if ($primary_group)	// only for users, NOT groups
		{
			$memberships = $this->accounts->memberships($accountid,true);

			if($primary_group_id && !in_array($primary_group_id,$memberships))
			{
				$memberships[] = $primary_group_id;

				$this->accounts->set_memberships($memberships,$accountid);
			}
			if (!$changepw) $this->add_acl('preferences','nopasswordchange',$accountid);
		}
		//error_log("setup::add_account('$username','$first','$last',\$passwd,'$primary_group',$changepw,'$email') successfull created accountid=$accountid");
		return $accountid;
	}

	/**
	 * Set the memberships of an account
	 *
	 * @param array $groups array of group-id's
	 * @param int $user account_id
	 */
	function set_memberships($groups,$user)
	{
		$this->setup_account_object();

		return $this->accounts->set_memberships($groups,$user);
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
		if (!$this->setup_account_object()) return false;

		$accounts = $this->accounts->search(array(
			'type'   => 'accounts',
			'start'  => 0,
			'offset' => 2	// we only need to check 2 accounts, if we just check for not anonymous
		));

		if (!$accounts || !is_array($accounts) || !count($accounts))
		{
			return false;
		}
		foreach($accounts as $account)
		{
			if ($account['account_lid'] != 'anonymous')
			{
				// we might add further checks, eg. if the account really has admin rights here
				return true;
			}
		}
		return false;
	}

	/**
	 * Add ACL rights
	 *
	 * Dont use it to set group-membership, use set_memberships instead!
	 *
	 * @param $app string/array with app-names
	 * @param $locations string eg. run
	 * @param $account int/string accountid or account_lid
	 * @param $rights int rights to set, default 1
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
