<?php
  /**************************************************************************\
  * phpGroupWare - Setup                                                     *
  * http://www.phpgroupware.org                                              *
  * --------------------------------------------                             *
  * This file written by Joseph Engo<jengo@phpgroupware.org>                 *
  *  and Dan Kuykendall<seek3r@phpgroupware.org>                             *
  *  and Mark Peters<skeeter@phpgroupware.org>                               *
  *  and Miles Lott<milosch@phpgroupware.org>                                *
  * --------------------------------------------                             *
  *  This program is free software; you can redistribute it and/or modify it *
  *  under the terms of the GNU General Public License as published by the   *
  *  Free Software Foundation; either version 2 of the License, or (at your  *
  *  option) any later version.                                              *
  \**************************************************************************/

  /* $Id$ */

	class setup
	{
		var $db;
		var $oProc;

		var $detection = '';
		var $process = '';
		var $lang = '';
		var $html = '';
		var $appreg = '';

		/* table name vars */
		var $tbl_apps;
		var $tbl_config;
		var $tbl_hooks;

		function setup($html=False, $translation=False)
		{
			$this->detection = CreateObject('phpgwapi.setup_detection');
			$this->process   = CreateObject('phpgwapi.setup_process');
			$this->appreg    = CreateObject('phpgwapi.app_registry');

			/* The setup application needs these */
			$this->html = $html ? CreateObject('phpgwapi.setup_html') : '';
			$this->translation = $translation ? CreateObject('phpgwapi.setup_translation') : '';

//			$this->tbl_apps    = $this->get_apps_table_name();
//			$this->tbl_config  = $this->get_config_table_name();
			$this->tbl_hooks   = $this->get_hooks_table_name();
		}

		/*!
		@function loaddb
		@abstract include api db class for the ConfigDomain and connect to the db
		*/
		function loaddb()
		{
			if(!isset($this->ConfigDomain) || empty($this->ConfigDomain))
			{
				$this->ConfigDomain = get_var('ConfigDomain',array('COOKIE','POST'),$_POST['FormDomain']);
			}

			$GLOBALS['phpgw_info']['server']['db_type'] = $GLOBALS['phpgw_domain'][$this->ConfigDomain]['db_type'];

			$this->db           = CreateObject('phpgwapi.db');
			$this->db->Host     = $GLOBALS['phpgw_domain'][$this->ConfigDomain]['db_host'];
			$this->db->Port     = $GLOBALS['phpgw_domain'][$this->ConfigDomain]['db_port'];
			$this->db->Type     = $GLOBALS['phpgw_domain'][$this->ConfigDomain]['db_type'];
			$this->db->Database = $GLOBALS['phpgw_domain'][$this->ConfigDomain]['db_name'];
			$this->db->User     = $GLOBALS['phpgw_domain'][$this->ConfigDomain]['db_user'];
			$this->db->Password = $GLOBALS['phpgw_domain'][$this->ConfigDomain]['db_pass'];
		}

		/**
		* Set the domain used for cookies
		*
		* @return string domain
		*/
		function set_cookiedomain()
		{
			$dom = $_SERVER['HTTP_HOST'];
			if (preg_match("/^(.*):(.*)$/",$dom,$arr))
			{
				$dom = $arr[1];
			}
			$parts = explode('.',$dom);
			if (count($parts) > 2)
			{
				if (!ereg('[0-9]+',$parts[1]))
				{
					for($i=1;$i<count($parts);$i++)
					{
						$this->cookie_domain .= '.'.$parts[$i];
					}
				}
				else
				{
					$this->cookie_domain = '';
				}
			}
			else
			{
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
			if(!$this->cookie_domain)
			{
				$this->set_cookiedomain();
			}
			setcookie($cookiename,$cookievalue,$cookietime,'/',$this->cookie_domain); 
		}

		/*!
		@function auth
		@abstract authenticate the setup user
		@param	$auth_type	???
		*/
		function auth($auth_type='Config')
		{
			#phpinfo();
			#$remoteip   = $_SERVER['REMOTE_ADDR'];

			$FormLogout = get_var('FormLogout',  array('GET','POST'));
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
				$ConfigLang   = get_var('ConfigLang',  array('POST','COOKIE'));

				/* Setup defaults to aid in header upgrade to version 1.26.
				 * This was the first version to include the following values.
				 */
				if(!@isset($GLOBALS['phpgw_domain'][$FormDomain]['config_user']) && isset($GLOBALS['phpgw_domain'][$FormDomain]))
				{
					@$GLOBALS['phpgw_domain'][$FormDomain]['config_user'] = 'admin';
				}
				if(!@isset($GLOBALS['phpgw_info']['server']['header_admin_user']))
				{
					@$GLOBALS['phpgw_info']['server']['header_admin_user'] = 'admin';
				}
			}

			/* if(!empty($remoteip) && !$this->checkip($remoteip)) { return False; } */

			/* If FormLogout is set, simply invalidate the cookies (LOGOUT) */
			switch(strtolower($FormLogout))
			{
				case 'config':
					/* config logout */
					$expire = time() - 86400;
					$this->set_cookie('ConfigUser','',$expire,'/');
					$this->set_cookie('ConfigPW','',$expire,'/');
					$this->set_cookie('ConfigDomain','',$expire,'/');
					$this->set_cookie('ConfigLang','',$expire,'/');
					$GLOBALS['phpgw_info']['setup']['LastDomain'] = $_COOKIE['ConfigDomain'];
					$GLOBALS['phpgw_info']['setup']['ConfigLoginMSG'] = lang('You have successfully logged out');
					$GLOBALS['phpgw_info']['setup']['HeaderLoginMSG'] = '';
					return False;
				case 'header':
					/* header admin logout */
					$expire = time() - 86400;
					$this->set_cookie('HeaderUser','',$expire,'/');
					$this->set_cookie('HeaderPW','',$expire,'/');
					$this->set_cookie('ConfigLang','',$expire,'/');
					$GLOBALS['phpgw_info']['setup']['HeaderLoginMSG'] = lang('You have successfully logged out');
					$GLOBALS['phpgw_info']['setup']['ConfigLoginMSG'] = '';
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
						if($FormUser == stripslashes($GLOBALS['phpgw_info']['server']['header_admin_user']) &&
							(md5($FormPW) == stripslashes($GLOBALS['phpgw_info']['server']['header_admin_password']) ||
							$FormPW == stripslashes($GLOBALS['phpgw_info']['server']['header_admin_password']))
						)
						{
							$this->set_cookie('HeaderUser',"$FormUser",$expire,'/');
							$this->set_cookie('HeaderPW',"$FormPW",$expire,'/');
							$this->set_cookie('ConfigLang',"$ConfigLang",$expire,'/');
							return True;
						}
						else
						{
							$GLOBALS['phpgw_info']['setup']['HeaderLoginMSG'] = lang('Invalid password');
							$GLOBALS['phpgw_info']['setup']['ConfigLoginMSG'] = '';
							return False;
						}
					}
					elseif(!empty($HeaderPW) && $auth_type == 'Header')
					{
						// Returning after login to header admin
						/* New test is md5, cleartext version is for header < 1.26 */
						if($HeaderUser == stripslashes($GLOBALS['phpgw_info']['server']['header_admin_user']) &&
							(md5($HeaderPW) == stripslashes($GLOBALS['phpgw_info']['server']['header_admin_password']) ||
							$HeaderPW == stripslashes($GLOBALS['phpgw_info']['server']['header_admin_password']))
						)
						{
							$this->set_cookie('HeaderUser',"$HeaderUser",$expire,'/');
							$this->set_cookie('HeaderPW',"$HeaderPW",$expire,'/');
							$this->set_cookie('ConfigLang',"$ConfigLang",$expire,'/');
							return True;
						}
						else
						{
							$GLOBALS['phpgw_info']['setup']['HeaderLoginMSG'] = lang('Invalid password');
							$GLOBALS['phpgw_info']['setup']['ConfigLoginMSG'] = '';
							return False;
						}
					}
					break;
				case 'config':
					if(!empty($ConfigLogin))
					{
						/* config login */
						/* New test is md5, cleartext version is for header < 1.26 */
						if(isset($GLOBALS['phpgw_domain'][$FormDomain]) &&
							$FormUser == stripslashes(@$GLOBALS['phpgw_domain'][$FormDomain]['config_user']) &&
							(md5($FormPW) == stripslashes(@$GLOBALS['phpgw_domain'][$FormDomain]['config_passwd']) ||
							$FormPW == stripslashes(@$GLOBALS['phpgw_domain'][$FormDomain]['config_passwd']))
						)
						{
							$this->set_cookie('ConfigUser',"$FormUser",$expire,'/');
							$this->set_cookie('ConfigPW',"$FormPW",$expire,'/');
							$this->set_cookie('ConfigDomain',"$FormDomain",$expire,'/');
							/* Set this now since the cookie will not be available until the next page load */
							$this->ConfigDomain = "$FormDomain";
							$this->set_cookie('ConfigLang',"$ConfigLang",$expire,'/');
							return True;
						}
						else
						{
							$GLOBALS['phpgw_info']['setup']['ConfigLoginMSG'] = lang('Invalid password');
							$GLOBALS['phpgw_info']['setup']['HeaderLoginMSG'] = '';
							return False;
						}
					}
					elseif(!empty($ConfigPW))
					{
						// Returning after login to config
						/* New test is md5, cleartext version is for header < 1.26 */
						if($ConfigUser == stripslashes($GLOBALS['phpgw_domain'][$this->ConfigDomain]['config_user']) &&
							(md5($ConfigPW) == stripslashes($GLOBALS['phpgw_domain'][$this->ConfigDomain]['config_passwd']) ||
							$ConfigPW == stripslashes($GLOBALS['phpgw_domain'][$this->ConfigDomain]['config_passwd']))
						)
						{
							$this->set_cookie('ConfigUser',"$ConfigUser",$expire,'/');
							$this->set_cookie('ConfigPW',"$ConfigPW",$expire,'/');
							$this->set_cookie('ConfigDomain',$this->ConfigDomain,$expire,'/');
							$this->set_cookie('ConfigLang',"$ConfigLang",$expire,'/');
							return True;
						}
						else
						{
							$GLOBALS['phpgw_info']['setup']['ConfigLoginMSG'] = lang('Invalid password');
							$GLOBALS['phpgw_info']['setup']['HeaderLoginMSG'] = '';
							return False;
						}
					}
					break;
			}

			return False;
		}

		function checkip($remoteip='')
		{
			$allowed_ips = split(',',$GLOBALS['phpgw_info']['server']['setup_acl']);
			if(is_array($allowed_ips))
			{
				$foundip = False;
				while(list(,$value) = @each($allowed_ips))
				{
					$test = split("\.",$value);
					if(count($test) < 3)
					{
						$value .= ".0.0";
						$tmp = split("\.",$remoteip);
						$tmp[2] = 0;
						$tmp[3] = 0;
						$testremoteip = join('.',$tmp);
					}
					elseif(count($test) < 4)
					{
						$value .= ".0";
						$tmp = split("\.",$remoteip);
						$tmp[3] = 0;
						$testremoteip = join('.',$tmp);
					}
					elseif(count($test) == 4 &&
						(int)$test[3] == 0)
					{
						$tmp = split("\.",$remoteip);
						$tmp[3] = 0;
						$testremoteip = join('.',$tmp);
					}
					else
					{
						$testremoteip = $remoteip;
					}

					//echo '<br>testing: ' . $testremoteip . ' compared to ' . $value;

					if($testremoteip == $value)
					{
						//echo ' - PASSED!';
						$foundip = True;
					}
				}
				if(!$foundip)
				{
					$GLOBALS['phpgw_info']['setup']['HeaderLoginMSG'] = '';
					$GLOBALS['phpgw_info']['setup']['ConfigLoginMSG'] = lang('Invalid IP address');
					return False;
				}
			}
			return True;
		}

		/*!
		@function get_major
		@abstract Return X.X.X major version from X.X.X.X versionstring
		@param	$
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

		/*!
		@function clear_session_cache
		@abstract Clear system/user level cache so as to have it rebuilt with the next access
		@param	None
		*/
		function clear_session_cache()
		{
			$tables = Array();
			$tablenames = $this->db->table_names();
			foreach($tablenames as $key => $val)
			{
				$tables[] = $val['table_name'];
			}
			if(in_array('phpgw_app_sessions',$tables))
			{
				$this->db->lock(array('phpgw_app_sessions'));
				@$this->db->query("DELETE FROM phpgw_app_sessions WHERE sessionid = '0' and loginid = '0' and app = 'phpgwapi' and location = 'config'",__LINE__,__FILE__);
				@$this->db->query("DELETE FROM phpgw_app_sessions WHERE app = 'phpgwapi' and location = 'phpgw_info_cache'",__LINE__,__FILE__);
				$this->db->unlock();
			}
		}

		/*!
		@function register_app
		@abstract Add an application to the phpgw_applications table
		@param	$appname	Application 'name' with a matching $setup_info[$appname] array slice
		@param	$enable		optional, set to True/False to override setup.inc.php setting
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

			/*
			Use old applications table if the currentver is less than 0.9.10pre8,
			but not if the currentver = '', which probably means new install.
			*/
			if($this->alessthanb($setup_info['phpgwapi']['currentver'],'0.9.10pre8') && ($setup_info['phpgwapi']['currentver'] != ''))
			{
				$appstbl = 'applications';
			}
			else
			{
				$appstbl = 'phpgw_applications';
				if($this->amorethanb($setup_info['phpgwapi']['currentver'],'0.9.13.014'))
				{
					$use_appid = True;
				}
			}

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
					echo $setup_info[$appname]['name'] . ' uses tables_use_prefix, storing ' 
						. $setup_info[$appname]['tables_prefix']
						. ' as prefix for ' . $setup_info[$appname]['name'] . " tables\n";

					$sql = "INSERT INTO phpgw_config (config_app,config_name,config_value) "
						."VALUES ('".$setup_info[$appname]['name']."','"
						.$appname."_tables_prefix','".$setup_info[$appname]['tables_prefix']."');";
					$this->db->query($sql,__LINE__,__FILE__);
				}
				if($use_appid)
				{
					$this->db->query("SELECT MAX(app_id) FROM $appstbl");
					$this->db->next_record();
					if($this->db->f(0))
					{
						$app_id = ($this->db->f(0) + 1) . ',';
						$app_idstr = 'app_id,';
					}
					else
					{
						srand(100000);
						$app_id = rand(1,100000) . ',';
						$app_idstr = 'app_id,';
					}
				}
				$this->db->query("INSERT INTO $appstbl "
					. "($app_idstr app_name,app_enabled,app_order,app_tables,app_version) "
					. "VALUES ("
					. $app_id
					. "'" . $setup_info[$appname]['name'] . "',"
					. $enable . ","
					. (int)$setup_info[$appname]['app_order'] . ","
					. "'" . $tables . "',"
					. "'" . $setup_info[$appname]['version'] . "');"
				);
				$this->clear_session_cache();
			}
		}

		/*!
		@function app_registered
		@abstract Check if an application has info in the db
		@param	$appname	Application 'name' with a matching $setup_info[$appname] array slice
		@param	$enabled	optional, set to False to not enable this app
		*/
		function app_registered($appname)
		{
			$setup_info = $GLOBALS['setup_info'];

			if(!$appname)
			{
				return False;
			}

			if($this->alessthanb($setup_info['phpgwapi']['currentver'],'0.9.10pre8') && ($setup_info['phpgwapi']['currentver'] != ''))
			{
				$appstbl = 'applications';
			}
			else
			{
				$appstbl = 'phpgw_applications';
			}

			if(@$GLOBALS['DEBUG'])
			{
				echo '<br>app_registered(): checking ' . $appname . ', table: ' . $appstbl;
				// _debug_array($setup_info[$appname]);
			}

			$this->db->query("SELECT COUNT(app_name) FROM $appstbl WHERE app_name='".$appname."'");
			$this->db->next_record();
			if($this->db->f(0))
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

		/*!
		@function update_app
		@abstract Update application info in the db
		@param	$appname	Application 'name' with a matching $setup_info[$appname] array slice
		@param	$enabled	optional, set to False to not enable this app
		*/
		function update_app($appname)
		{
			$setup_info = $GLOBALS['setup_info'];

			if(!$appname)
			{
				return False;
			}

			if($this->alessthanb($setup_info['phpgwapi']['currentver'],'0.9.10pre8') && ($setup_info['phpgwapi']['currentver'] != ''))
			{
				$appstbl = 'applications';
			}
			else
			{
				$appstbl = 'phpgw_applications';
			}

			if($GLOBALS['DEBUG'])
			{
				echo '<br>update_app(): ' . $appname . ', version: ' . $setup_info[$appname]['currentver'] . ', table: ' . $appstbl . '<br>';
				// _debug_array($setup_info[$appname]);
			}

			$this->db->query("SELECT COUNT(app_name) FROM $appstbl WHERE app_name='".$appname."'");
			$this->db->next_record();
			if(!$this->db->f(0))
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

				$sql = "UPDATE $appstbl "
					. "SET app_name='" . $setup_info[$appname]['name'] . "',"
					. " app_enabled=" . (int)$setup_info[$appname]['enable'] . ","
					. " app_order=" . (int)$setup_info[$appname]['app_order'] . ","
					. " app_tables='" . $tables . "',"
					. " app_version='" . $setup_info[$appname]['version'] . "'"
					. " WHERE app_name='" . $appname . "'";
				//echo $sql; exit;

				$this->db->query($sql);
			}
		}

		/*!
		@function update_app_version
		@abstract Update application version in applications table, post upgrade
		@param	$setup_info		Array of application information (multiple apps or single)
		@param	$appname		Application 'name' with a matching $setup_info[$appname] array slice
		@param	$tableschanged	???
		*/
		function update_app_version($setup_info, $appname, $tableschanged = True)
		{
			if(!$appname)
			{
				return False;
			}

			if($this->alessthanb($setup_info['phpgwapi']['currentver'],'0.9.10pre8') && ($setup_info['phpgwapi']['currentver'] != ''))
			{
				$appstbl = 'applications';
			}
			else
			{
				$appstbl = 'phpgw_applications';
			}

			if($tableschanged == True)
			{
				$GLOBALS['phpgw_info']['setup']['tableschanged'] = True;
			}
			if($setup_info[$appname]['currentver'])
			{
				$this->db->query("UPDATE $appstbl SET app_version='" . $setup_info[$appname]['currentver'] . "' WHERE app_name='".$appname."'");
			}
			return $setup_info;
		}

		/*!
		@function deregister_app
		@abstract de-Register an application
		@param	$appname	Application 'name' with a matching $setup_info[$appname] array slice
		*/
		function deregister_app($appname)
		{
			if(!$appname)
			{
				return False;
			}
			$setup_info = $GLOBALS['setup_info'];

			if($this->alessthanb($setup_info['phpgwapi']['currentver'],'0.9.10pre8') && ($setup_info['phpgwapi']['currentver'] != ''))
			{
				$appstbl = 'applications';
			}
			else
			{
				$appstbl = 'phpgw_applications';
			}

			//echo 'DELETING application: ' . $appname;
			$this->db->query("DELETE FROM $appstbl WHERE app_name='". $appname ."'");
			$this->clear_session_cache();
		}

		/*!
		@function register_hooks
		@abstract Register an application's hooks
		@param	$appname	Application 'name' with a matching $setup_info[$appname] array slice
		*/
		function register_hooks($appname)
		{
			$setup_info = $GLOBALS['setup_info'];

			if(!$appname)
			{
				return False;
			}

			if($this->alessthanb($setup_info['phpgwapi']['currentver'],'0.9.8pre5') && ($setup_info['phpgwapi']['currentver'] != ''))
			{
				/* No phpgw_hooks table yet. */
				return False;
			}

			if (!is_object($this->hooks))
			{
				$this->hooks = CreateObject('phpgwapi.hooks',$this->db);
			}
			$this->hooks->register_hooks($appname,$setup_info[$appname]['hooks']);
		}

		/*!
		@function update_hooks
		@abstract Update an application's hooks
		@param	$appname	Application 'name' with a matching $setup_info[$appname] array slice
		*/
		function update_hooks($appname)
		{
			$this->register_hooks($appname);
		}

		/*!
		@function deregister_hooks
		@abstract de-Register an application's hooks
		@param	$appname	Application 'name' with a matching $setup_info[$appname] array slice
		*/
		function deregister_hooks($appname)
		{
			if($this->alessthanb($setup_info['phpgwapi']['currentver'],'0.9.8pre5'))
			{
				/* No phpgw_hooks table yet. */
				return False;
			}

			if(!$appname)
			{
				return False;
			}
			
			//echo "DELETING hooks for: " . $setup_info[$appname]['name'];
			if (!is_object($this->hooks))
			{
				$this->hooks = CreateObject('phpgwapi.hooks',$this->db);
			}
			$this->hooks->register_hooks($appname);
		}

		/*!
		 @function hook
		 @abstract call the hooks for a single application
		 @param $location hook location - required
		 @param $appname application name - optional
		*/
		function hook($location, $appname='')
		{
			if (!is_object($this->hooks))
			{
				$this->hooks = CreateObject('phpgwapi.hooks',$this->db);
			}
			return $this->hooks->single($location,$appname,True,True);
		}

		/*
		@function alessthanb
		@abstract phpgw version checking, is param 1 < param 2 in phpgw versionspeak?
		@param	$a	phpgw version number to check if less than $b
		@param	$b	phpgw version number to check $a against
		#return	True if $a < $b
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
			if(@$testa[3] == '')
			{
				$testa[3] = 0;
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
					if($DEBUG) { echo ' no, they are equal.'; }
					$less = 0;
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

		/*!
		@function amorethanb
		@abstract phpgw version checking, is param 1 > param 2 in phpgw versionspeak?
		@param	$a	phpgw version number to check if more than $b
		@param	$b	phpgw version number to check $a against
		#return	True if $a < $b
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

		function get_hooks_table_name()
		{
			if(@$this->alessthanb($GLOBALS['setup_info']['phpgwapi']['currentver'],'0.9.8pre5') &&
			   @$GLOBALS['setup_info']['phpgwapi']['currentver'] != '')
			{
				/* No phpgw_hooks table yet. */
				return False;
			}
			return 'phpgw_hooks';
		}

		function setup_account_object()
		{
			if (!is_object($GLOBALS['phpgw']->accounts))
			{
				if (!is_object($this->db))
				{
					$this->loaddb();
				}
				/* Load up some configured values */
				$this->db->query("SELECT config_name,config_value FROM phpgw_config ".
					"WHERE config_name LIKE 'ldap%' OR config_name LIKE 'account_%'",__LINE__,__FILE__);
				while ($this->db->next_record())
				{
					$GLOBALS['phpgw_info']['server'][$this->db->f('config_name')] = $this->db->f('config_value');
				}
				if (!is_object($GLOBALS['phpgw']))
				{
					$GLOBALS['phpgw'] = CreateObject('phpgwapi.phpgw');
				}
				copyobj($this->db,$GLOBALS['phpgw']->db);
				$GLOBALS['phpgw']->common      = CreateObject('phpgwapi.common');
				$GLOBALS['phpgw']->accounts    = CreateObject('phpgwapi.accounts');

				if(($GLOBALS['phpgw_info']['server']['account_repository'] == 'ldap') &&
					!$GLOBALS['phpgw']->accounts->ds)
				{
					printf("<b>Error: Error connecting to LDAP server %s!</b><br>",$GLOBALS['phpgw_info']['server']['ldap_host']);
					exit;
				}
			}
		}

		/*!
		@function add_account
		@abstract add an user account or a user group
		@param username string alphanumerical username or groupname (account_lid)
		@param first, last string first / last name
		@param $passwd string cleartext pw
		@param $group string/boolean Groupname for users primary group or False for a group, default 'Default'
		@param $changepw boolean user has right to change pw, default False
		@returns the numerical user-id
		@note if the $username already exists, only the id is returned, no new user / group gets created
		*/
		function add_account($username,$first,$last,$passwd,$group='default',$changepw=False)
		{
			$this->setup_account_object();

			$groupid = $group ? $GLOBALS['phpgw']->accounts->name2id($group) : False;

			if(!($accountid = $GLOBALS['phpgw']->accounts->name2id($username)))
			{
				$accountid = $accountid ? $accountid : $GLOBALS['phpgw']->accounts->create(array(
					'account_type'      => $group ? 'u' : 'g',
					'account_lid'       => $username,
					'account_passwd'    => $passwd,
					'account_firstname' => $first,
					'account_lastname'  => $last,
					'account_status'    => 'A',
					'account_primary_group' => $groupid,
					'account_expires'   => -1
				));
			}
			$accountid = (int)$accountid;
			if($groupid)
			{
				$this->add_acl('phpgw_group',(int)$groupid,$accountid);
			}
			$this->add_acl('preferences','changepassword',$accountid,(int)$changepw);

			return $accountid;
		}

		/*!
		@function add_acl
		@abstract Add ACL rights
		@param $app string/array with app-names
		@param $locations string eg. run
		@param $account int/string accountid or account_lid
		@param $rights int rights to set, default 1
		*/
		function add_acl($apps,$location,$account,$rights=1)
		{
			if (!is_int($account))
			{
				$this->setup_account_object();
				$account = $GLOBALS['phpgw']->accounts->name2id($account);
			}
			$rights = (int)$rights;
			if (!is_object($this->db)) $this->loaddb();

			if (!is_array($apps)) $apps = array($apps);
			foreach($apps as $app)
			{
				$this->db->query("INSERT INTO phpgw_acl(acl_appname,acl_location,acl_account,acl_rights) VALUES('$app','$location',$account,$rights)");
			}
		}
	}
?>
