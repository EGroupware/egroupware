<?php
  /**************************************************************************\
  * phpGroupWare API - Session management                                    *
  * This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
  * and Joseph Engo <jengo@phpgroupware.org>                                 *
  * Copyright (C) 2000, 2001 Dan Kuykendall                                  *
  * -------------------------------------------------------------------------*
  * This library is part of the phpGroupWare API                             *
  * http://www.phpgroupware.org/api                                          * 
  * ------------------------------------------------------------------------ *
  * This library is free software; you can redistribute it and/or modify it  *
  * under the terms of the GNU Lesser General Public License as published by *
  * the Free Software Foundation; either version 2.1 of the License,         *
  * or any later version.                                                    *
  * This library is distributed in the hope that it will be useful, but      *
  * WITHOUT ANY WARRANTY; without even the implied warranty of               *
  * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.                     *
  * See the GNU Lesser General Public License for more details.              *
  * You should have received a copy of the GNU Lesser General Public License *
  * along with this library; if not, write to the Free Software Foundation,  *
  * Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA            *
  \**************************************************************************/

	/* $Id$ */

	class sessions
	{
		var $login;
		var $passwd;
		var $account_id;
		var $account_lid;
		var $account_domain;
		var $sessionid;
		var $kp3;
		var $data;
		var $db;
		var $db2;

		/*************************************************************************\
		* Constructor just loads up some defaults from cookies                    *
		\*************************************************************************/
		function sessions()
		{
			global $phpgw, $phpgw_info, $sessionid, $kp3;

			$this->db        = $phpgw->db;
			$this->db2       = $phpgw->db;
			$this->sessionid = $sessionid;
			$this->kp3       = $kp3;
		}

		/*************************************************************************\
		* Functions for creating and verifying the session                        *
		\*************************************************************************/
		function getuser_ip()
		{
			global $REMOTE_ADDR, $HTTP_X_FORWARDED_FOR;
       
			if ($HTTP_X_FORWARDED_FOR)
			{
				return $HTTP_X_FORWARDED_FOR;
			}
			else
			{
				return $REMOTE_ADDR;
			}
		}

		function verify()
		{
			global $phpgw, $phpgw_info, $sessionid, $kp3;

			$db              = $phpgw->db;
			$db2             = $phpgw->db;
			$this->sessionid = $sessionid;
			$this->kp3       = $kp3;

			$phpgw->common->key  = md5($this->kp3 . $this->sessionid . $phpgw_info['server']['encryptkey']);
			$phpgw->common->iv   = $phpgw_info['server']['mcrypt_iv'];

			$cryptovars[0] = $phpgw->common->key;      
			$cryptovars[1] = $phpgw->common->iv;      
			$phpgw->crypto = CreateObject('phpgwapi.crypto', $cryptovars);

			$db->query("select * from phpgw_sessions where session_id='" . $this->sessionid . "'",__LINE__,__FILE__);
			$db->next_record();

			// This is going to be replace with the session_flag field       
			if ($db->f('session_info') == '' || $db->f('session_info') == 'NULL')
			{
/*        $this->account_lid = $db->f('session_lid');
          $phpgw_info['user']['sessionid']   = $this->sessionid;
          $phpgw_info['user']['session_ip']  = $db->f('session_ip');

          $t = explode('@',$db->f('session_lid'));
          $this->account_lid = $t[0];

          // Now we need to re-read eveything
          $db->query("select * from phpgw_sessions where session_id='$this->sessionid'",__LINE__,__FILE__);
          $db->next_record();   */
			}

			$login_array = explode('@', $db->f('session_lid'));
			$this->account_lid = $login_array[0];

			if ($login_array[1] != '')
			{
				$this->account_domain = $login_array[1];
			}
			else
			{
				$this->account_domain = $phpgw_info['server']['default_domain'];
			}

			$phpgw_info['user']['kp3'] = $this->kp3;
			$phpgw_info_flags    = $phpgw_info['flags'];

			$phpgw_info['flags'] = $phpgw_info_flags;
			$userid_array = explode('@',$db->f('session_lid'));
			$this->account_lid = $userid_array[0];
			$this->update_dla();
			$this->account_id = $phpgw->accounts->name2id($this->account_lid);

			if ($phpgw_info['server']['cache_phpgw_info'])
			{
				$t = $this->appsession('phpgw_info_cache','phpgwapi');
				$phpgw_info['server'] = $t['server'];
				$phpgw_info['user']   = $t['user'];
				$phpgw_info['hooks']  = $t['hooks'];
			}
			else
			{
				$this->read_repositories();
				$phpgw_info['user']  = $this->user;
				$phpgw_info['hooks'] = $this->hooks;
			}

			$phpgw_info['user']['session_ip']  = $db->f('session_ip');
			$phpgw_info['user']['passwd']		= $this->appsession('password','phpgwapi');

			if ($userid_array[1] != $phpgw_info['user']['domain'])
			{
				return False;
			}

			if (PHP_OS != 'Windows' && (! $phpgw_info['user']['session_ip'] || $phpgw_info['user']['session_ip'] != $this->getuser_ip()))
			{
				return False;
			}

			$phpgw->acl->acl($this->account_id);
			$phpgw->accounts->accounts($this->account_id);
			$phpgw->preferences->preferences($this->account_id);
			$phpgw->applications->applications($this->account_id);

			if (! $this->account_lid)
			{
				return False;
			}
			else
			{
				return True;
			}
		}

		// This will remove stale sessions out of the database
		function clean_sessions()
		{
			global $phpgw_info, $phpgw;

			if (!isset($phpgw_info['server']['cron_apps']) || ! $phpgw_info['server']['cron_apps'])
			{
				$phpgw->db->query("delete from phpgw_sessions where session_dla <= '" . (time() -  7200)
									 . "'",__LINE__,__FILE__);
			}
		}

		function create($login,$passwd)
		{
			global $phpgw_info, $phpgw;

			$this->login  = $login;
			$this->passwd = $passwd;
			$this->clean_sessions();
			$login_array = explode('@', $login);
			$this->account_lid = $login_array[0];
			$now = time();

			if ($login_array[1] != '')
			{
				$this->account_domain = $login_array[1];
			}
			else
			{
				$this->account_domain = $phpgw_info['server']['default_domain'];
			}

			if ($phpgw_info['server']['global_denied_users'][$this->account_lid])
			{
				return False;
			}

			if (! $phpgw->auth->authenticate($this->account_lid, $passwd))
			{
				return False;
				exit;
			}

			if (!$phpgw->accounts->exists($this->account_lid) && $phpgw_info['server']['auto_create_acct'] == True)
			{
				$this->account_id = $phpgw->accounts->auto_add($this->account_lid, $passwd);
			}
			else
			{
				$this->account_id = $phpgw->accounts->name2id($this->account_lid);
			}
			$phpgw->accounts->account_id = $this->account_id;

			$this->sessionid    = md5($phpgw->common->randomstring(10));
			$this->kp3          = md5($phpgw->common->randomstring(15));

			$phpgw->common->key = md5($this->kp3 . $this->sessionid . $phpgw_info['server']['encryptkey']);
			$phpgw->common->iv  = $phpgw_info['server']['mcrypt_iv'];
			$cryptovars[0] = $phpgw->common->key;
			$cryptovars[1] = $phpgw->common->iv;
			$phpgw->crypto = CreateObject('phpgwapi.crypto', $cryptovars);

			if ($phpgw_info['server']['usecookies'])
			{
				Setcookie('sessionid',$this->sessionid);
				Setcookie('kp3',$this->kp3);
				Setcookie('domain',$this->account_domain);
				Setcookie('last_domain',$this->account_domain,$now+1209600);
				if ($this->account_domain == $phpgw_info['server']['default_domain'])
				{
					Setcookie('last_loginid', $this->account_lid ,$now+1209600);  // For 2 weeks
				}
				else
				{
					Setcookie('last_loginid', $login ,$now+1209600);              // For 2 weeks
				}
				unset ($phpgw_info['server']['default_domain']);                 // we kill this for security reasons
			}

			$this->read_repositories();
			$phpgw_info['user']  = $this->user;
			$phpgw_info['hooks'] = $this->hooks;
			if ($phpgw_info['server']['cache_phpgw_info'])
			{
				$this->appsession('phpgw_info_cache','phpgwapi',$phpgw_info);
			}

			// If they are not useing cache, we need to store it somewhere
			$this->appsession('password','phpgwapi',$this->passwd);

			$phpgw->db->query("insert into phpgw_sessions values ('" . $this->sessionid
								. "','".$login."','" . $this->getuser_ip() . "','"
								. $now . "','" . $now . "','".$info_string."')",__LINE__,__FILE__);

			$phpgw->db->query("insert into phpgw_access_log values ('" . $this->sessionid . "','"
								. "$login','" . $this->getuser_ip() . "','$now','') ",__LINE__,__FILE__);

			$phpgw->auth->update_lastlogin($this->account_id,$this->getuser_ip());

			return $this->sessionid;
		}

		// This will update the DateLastActive column, so the login does not expire
		function update_dla()
		{
			global $phpgw_info, $phpgw, $PHP_SELF;

			$phpgw->db->query("update phpgw_sessions set session_dla='" . time() . "', session_action='$PHP_SELF'"
								. " where session_id='" . $this->sessionid."'",__LINE__,__FILE__);
		}
    
		function destroy()
		{
			global $phpgw, $phpgw_info, $sessionid, $kp3;
			$phpgw_info['user']['sessionid'] = $sessionid;
			$phpgw_info['user']['kp3'] = $kp3;
	 
			$phpgw->db->query("delete from phpgw_sessions where session_id='"
								. $phpgw_info['user']['sessionid'] . "'",__LINE__,__FILE__);
			$phpgw->db->query("delete from phpgw_app_sessions where sessionid='"
								. $phpgw_info['user']['sessionid'] . "'",__LINE__,__FILE__);
			$phpgw->db->query("update phpgw_access_log set lo='" . time() . "' where sessionid='"
								. $phpgw_info['user']['sessionid'] . "'",__LINE__,__FILE__);
			if ($phpgw_info['server']['usecookies'])
			{
				Setcookie('sessionid');
				Setcookie('kp3');
				if ($phpgw_info['multiable_domains'])
				{
					Setcookie('domain');
				}
			}
			$this->clean_sessions();
			return True;
		}

		/*************************************************************************\
		* Functions for appsession data and session cache                         *
		\*************************************************************************/
		function read_repositories()
		{
			global $phpgw;
			$phpgw->acl->acl($this->account_id);
			$phpgw->accounts->accounts($this->account_id);
			$phpgw->preferences->preferences($this->account_id);
			$phpgw->applications->applications($this->account_id);
	
			$this->user                = $phpgw->accounts->read_repository();
			$this->user['acl']         = $phpgw->acl->read_repository();
			$this->user['preferences'] = $phpgw->preferences->read_repository();
			$this->user['apps']        = $phpgw->applications->read_repository();
			//@reset($this->data['user']['apps']);
	
			$this->user['domain']      = $this->account_domain;
			$this->user['sessionid']   = $this->sessionid;
			$this->user['kp3']         = $this->kp3;
			$this->user['session_ip']  = $this->getuser_ip();
			$this->user['session_lid'] = $this->account_lid.'@'.$this->account_domain;
			$this->user['account_id']  = $this->account_id;
			$this->user['account_lid'] = $this->account_lid;
			$this->user['userid']      = $this->account_lid;
			$this->user['passwd']      = $this->passwd;
			$this->hooks               = $phpgw->hooks->read();
		}
	
		function save_repositories()
		{
			global $phpgw, $phpgw_info;
			
			$phpgw_info_temp = $phpgw_info;
			$phpgw_info_temp['user']['kp3'] = '';
			$phpgw_info_temp['flags'] = array();
			
			if ($phpgw_info['server']['cache_phpgw_info'])
			{
				$this->appsession('phpgw_info_cache','phpgwapi',$phpgw_info_temp);
			}
		}
	
		function appsession($location = 'default', $appname = '', $data = '##NOTHING##')
		{
			global $phpgw_info, $phpgw;
			
			if (! $appname)
			{
				$appname = $phpgw_info['flags']['currentapp'];
			}
			
			/* This allows the user to put "" as the value. */
			if ($data == '##NOTHING##') {
				$query = "select content from phpgw_app_sessions where"
				." sessionid = '".$this->sessionid."' and loginid = '".$this->account_id."'"
				." and app = '".$appname."' and location = '".$location."'";
	
				$phpgw->db->query($query,__LINE__,__FILE__);
				$phpgw->db->next_record();

				// I added these into seperate steps for easier debugging
				$data = $phpgw->db->f('content');
				$data = $phpgw->crypto->decrypt($data);
				$data = stripslashes($data);

				return $data;
			} else {
				$phpgw->db->query("select content from phpgw_app_sessions where "
				. "sessionid = '".$this->sessionid."' and loginid = '".$this->account_id."'"
				. "and app = '".$appname."' and location = '".$location."'",__LINE__,__FILE__);
				
				if ($phpgw->db->num_rows()==0) {

					// I added these into seperate steps for easier debugging
					$data = serialize($data);
					$data = $phpgw->crypto->encrypt($data);
					$data = addslashes($data);

					$phpgw->db->query("INSERT INTO phpgw_app_sessions (sessionid,loginid,app,location,content) "
					. "VALUES ('".$this->sessionid."','".$this->account_id."','".$appname
					. "','".$location."','".$data."')",__LINE__,__FILE__);
				} else {
	 				$data = $phpgw->crypto->encrypt(serialize($data));
					$data = addslashes($data);
					$phpgw->db->query("update phpgw_app_sessions set content = '".$data."'"
					. "where sessionid = '".$this->sessionid."'"
					. "and loginid = '".$this->account_id."' and app = '".$appname."'"
					. "and location = '".$location."'",__LINE__,__FILE__);
				}
	
				return unserialize($data);
			}
		}
		
		function restore()
		{
			global $phpgw;
			
			$serializedData = $this->appsession();
			$sessionData = unserialize($serializedData);
			
			if (is_array($sessionData))
			{
				reset($sessionData);
				while(list($key,$value) = each($sessionData))
				{
					global $$key;
					$$key = $value;
					$this->variableNames[$key] = 'registered';
					#print "restored: ".$key.", $value<br>";
				}
			}
		}
			
		// save the current values of the variables
		function save()
		{
			global $phpgw;
				
			if (is_array($this->variableNames))
			{
				reset($this->variableNames);
				while(list($key, $value) = each($this->variableNames))
				{
					if ($value == 'registered')
					{
						global $$key;
						$sessionData[$key] = $$key;
					}
				}
				$this->appsession($sessionData);
			}
		}
			
		// create a list a variable names, wich data need's to be restored
		function register($_variableName)
		{
			$this->variableNames[$_variableName]='registered';
			#print 'registered '.$_variableName.'<br>';
		}
			
		// mark variable as unregistered
		function unregister($_variableName)
		{
			$this->variableNames[$_variableName]='unregistered';
			#print 'unregistered '.$_variableName.'<br>';
		}
	
		// check if we have a variable registred already
		function is_registered($_variableName)
		{
			if ($this->variableNames[$_variableName] == 'registered')
			{
				return True;
			}
			else
			{
				return False;
			}
		}

		/*************************************************************************\
		* Function to handle session support via url or cookies                   *
		\*************************************************************************/
		function link($url = '', $extravars = '')
		{
			global $phpgw, $phpgw_info, $usercookie, $kp3, $PHP_SELF;
			
			/* Fix problems when PHP_SELF if used as the param */
			if ($url == $PHP_SELF)
			{
				$url = '';
			}
			
			if (! $kp3)
			{
				$kp3 = $phpgw_info['user']['kp3'];
			}

			// Explicit hack to work around problems with php running as CGI on windows
			// please let us know if this doesn't work for you!
			if (! $url && (PHP_OS == 'Windows' || PHP_OS == 'OS/2' || PHP_OS == 'WIN32' || PHP_OS == 'WIN16'))
			{
				$exe = strpos($PHP_SELF,'php.exe');
				if ($exe != false) {
					$exe += 7; // strlen('php.exe')
					$url_root = split ('/', $phpgw_info['server']['webserver_url']);
					$url = (strlen($url_root[0])? $url_root[0].'//':'') . $url_root[2];
					$url .= substr($PHP_SELF,$exe,strlen($PHP_SELF)-$exe);
				}
			}
			if (! $url)
			{
				$url_root = split ('/', $phpgw_info['server']['webserver_url']);
				/* Some hosting providers have their paths screwy.
					 If the value from $PHP_SELF is not what you expect, you can use this to patch it
					 It will need to be adjusted to your specific problem tho.
				*/
				//$patched_php_self = str_replace('/php4/php/phpgroupware', '/phpgroupware', $PHP_SELF);
				$patched_php_self = $PHP_SELF;
				$url = (strlen($url_root[0])? $url_root[0].'//':'') . $url_root[2] . $patched_php_self;
			}
	
			if (isset($phpgw_info['server']['usecookies']) && $phpgw_info['server']['usecookies'])
			{
				if ($extravars)
				{
					$url .= '?' . $extravars;
				}
			}
			else
			{
				$url .= '?sessionid=' . $phpgw_info['user']['sessionid'];
				$url .= '&kp3=' . $kp3;
				$url .= '&domain=' . $phpgw_info['user']['domain'];
				// This doesn't belong in the API.
				// Its up to the app to pass this value. (jengo)
				// Putting it into the app requires a massive number of updates in email app. 
				// Until that happens this needs to stay here (seek3r)
				if ($phpgw_info['flags']['newsmode'])
				{
					$url .= '&newsmode=on';
				}
				if ($extravars)
				{
					$url .= "&$extravars";
				}
			}
	
			$url = str_replace('/?', '/index.php?', $url);
			$webserver_url_count = strlen($phpgw_info['server']['webserver_url']);
			$slash_check = strtolower(substr($url ,0,1));
			if (substr($url ,0,$webserver_url_count) != $phpgw_info['server']['webserver_url'])
			{
				$app = $phpgw_info['flags']['currentapp'];
				if ($slash_check == '/')
				{
					$url = $phpgw_info['server']['webserver_url'] . $url;
				}
				elseif ($app == 'home' || $app == 'logout' || $app == 'login')
				{
					$url = $phpgw_info['server']['webserver_url'].'/'.$url; 
				}
				else
				{
					$url = $phpgw_info['server']['webserver_url'].'/'.$app.'/'.$url; 
				}
			} 
			return $url;
		}  
	}
?>
