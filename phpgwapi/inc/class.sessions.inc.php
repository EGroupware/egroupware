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

	/*
	** Reserved session_flags
	** A - anonymous session
	** N - None, normal session
	*/

	class sessions
	{
		var $login;
		var $passwd;
		var $account_id;
		var $account_lid;
		var $account_domain;
		var $session_flags;
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
			$this->db        = $GLOBALS['phpgw']->db;
			$this->db2       = $GLOBALS['phpgw']->db;
			$this->sessionid = (isset($GLOBALS['HTTP_GET_VARS']['sessionid'])?$GLOBALS['HTTP_GET_VARS']['sessionid']:(isset($GLOBALS['HTTP_COOKIE_VARS']['sessionid'])?$GLOBALS['HTTP_COOKIE_VARS']['sessionid']:''));
			$this->kp3       = (isset($GLOBALS['HTTP_GET_VARS']['kp3'])?$GLOBALS['HTTP_GET_VARS']['kp3']:(isset($GLOBALS['HTTP_COOKIE_VARS']['kp3'])?$GLOBALS['HTTP_COOKIE_VARS']['kp3']:''));
		}

		/*************************************************************************\
		* Functions for creating and verifying the session                        *
		\*************************************************************************/
		function getuser_ip()
		{
			global $HTTP_SERVER_VARS,$REMOTE_ADDR,$HTTP_X_FORWARDED_FOR;

			if ($GLOBALS['HTTP_X_FORWARDED_FOR'] || $HTTP_X_FORWARDED_FOR)
			{
				return $GLOBALS['HTTP_X_FORWARDED_FOR'] ? $GLOBALS['HTTP_X_FORWARDED_FOR'] : $HTTP_X_FORWARDED_FOR;
			}
			else
			{
				return $GLOBALS['HTTP_SERVER_VARS']['REMOTE_ADDR'] ? $GLOBALS['HTTP_SERVER_VARS']['REMOTE_ADDR'] : $REMOTE_ADDR;
			}
		}

		function verify($sessionid='',$kp3='')
		{
			if(empty($sessionid) || !$sessionid)
			{
				$sessionid = $GLOBALS['HTTP_GET_VARS']['sessionid'] ? $GLOBALS['HTTP_GET_VARS']['sessionid'] : $GLOBALS['HTTP_COOKIE_VARS']['sessionid'];
				$kp3       = $GLOBALS['HTTP_GET_VARS']['kp3']       ? $GLOBALS['HTTP_GET_VARS']['kp3']       : $GLOBALS['HTTP_COOKIE_VARS']['kp3'];
			}

			$db              = $GLOBALS['phpgw']->db;
			$db2             = $GLOBALS['phpgw']->db;
			$this->sessionid = $sessionid;
			$this->kp3       = $kp3;

			$GLOBALS['phpgw']->common->key  = md5($this->kp3 . $this->sessionid . $GLOBALS['phpgw_info']['server']['encryptkey']);
			$GLOBALS['phpgw']->common->iv   = $GLOBALS['phpgw_info']['server']['mcrypt_iv'];

			$cryptovars[0] = $GLOBALS['phpgw']->common->key;      
			$cryptovars[1] = $GLOBALS['phpgw']->common->iv;      
			$GLOBALS['phpgw']->crypto = CreateObject('phpgwapi.crypto', $cryptovars);

			$db->query("select * from phpgw_sessions where session_id='" . $this->sessionid . "'",__LINE__,__FILE__);
			$db->next_record();

			$this->session_flags = $db->f('session_flags');

			$login_array = explode('@', $db->f('session_lid'));
			$this->account_lid = $login_array[0];

			if (@$login_array[1] != '')
			{
				$this->account_domain = $login_array[1];
			}
			else
			{
				$this->account_domain = $GLOBALS['phpgw_info']['server']['default_domain'];
			}

			$GLOBALS['phpgw_info']['user']['kp3'] = $this->kp3;

			$userid_array = explode('@',$db->f('session_lid'));
// Thinking this might solve auth_http	problems
			if(@$userid_array[1] == '') { $userid_array[1] = 'default'; }
			$this->account_lid = $userid_array[0];
			$this->update_dla();
			$this->account_id = $GLOBALS['phpgw']->accounts->name2id($this->account_lid);

			if (!$this->account_id)
			{
				return False;
			}

			$GLOBALS['phpgw_info']['user']['account_id'] = $this->account_id;

			$this->read_repositories(@$GLOBALS['phpgw_info']['server']['cache_phpgw_info']);
			if ($this->user['expires'] != -1 && $this->user['expires'] < time())
			{
				$GLOBALS['phpgw']->log->message('W-VerifySession, account loginid %1 is expired',$this->account_lid);
				$GLOBALS['phpgw']->log->commit();
				return False;
			}

			$GLOBALS['phpgw_info']['user']  = $this->user;
			$GLOBALS['phpgw_info']['hooks'] = $this->hooks;

			$GLOBALS['phpgw_info']['user']['session_ip']  = $db->f('session_ip');
			$GLOBALS['phpgw_info']['user']['passwd']		  = base64_decode($this->appsession('password','phpgwapi'));

			if ($userid_array[1] != $GLOBALS['phpgw_info']['user']['domain'])
			{
				$GLOBALS['phpgw']->log->message('W-VerifySession, the domains %1 and %2 don\t match',$userid_array[1],$GLOBALS['phpgw_info']['user']['domain']);
				$GLOBALS['phpgw']->log->commit();

				return False;
			}

			if (@$GLOBALS['phpgw_info']['server']['sessions_checkip'])
			{
				if (PHP_OS != 'Windows' && (! $GLOBALS['phpgw_info']['user']['session_ip'] || $GLOBALS['phpgw_info']['user']['session_ip'] != $this->getuser_ip()))
				{
					// This needs some better wording
					$GLOBALS['phpgw']->log->message('W-VerifySession, IP %1 doesn\'t match IP %2 in session table',$this->getuser_ip(),$GLOBALS['phpgw_info']['user']['session_ip']);
					$GLOBALS['phpgw']->log->commit();

					return False;
				}
			}

			$GLOBALS['phpgw']->acl->acl($this->account_id);
			$GLOBALS['phpgw']->accounts->accounts($this->account_id);
			$GLOBALS['phpgw']->preferences->preferences($this->account_id);
			$GLOBALS['phpgw']->applications->applications($this->account_id);

			if (! $this->account_lid)
			{
				// This needs some better wording
				$GLOBALS['phpgw']->log->message('W-VerifySession, account_id is empty');
				$GLOBALS['phpgw']->log->commit();

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
			// If you plan on using the cron apps, please remove the following lines.
			// I am going to make this a config option durring 0.9.11, instead of an application (jengo)

			$GLOBALS['phpgw']->db->query("delete from phpgw_sessions where session_dla <= '" . (time() -  7200)
								 . "' and session_flags !='A'",__LINE__,__FILE__);

			// This is set a little higher, we don't want to kill session data for anonymous sessions.
			$GLOBALS['phpgw']->db->query("delete from phpgw_app_sessions where session_dla <= '" . (time() -  86400)
									 . "'",__LINE__,__FILE__);
		}

		function create($login,$passwd)
		{
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
				$this->account_domain = $GLOBALS['phpgw_info']['server']['default_domain'];
			}

			if ($GLOBALS['phpgw_info']['server']['global_denied_users'][$this->account_lid])
			{
				return False;
			}

			if (! $GLOBALS['phpgw']->auth->authenticate($this->account_lid, $passwd))
			{
				return False;
				exit;
			}

			if (!$GLOBALS['phpgw']->accounts->exists($this->account_lid) && $GLOBALS['phpgw_info']['server']['auto_create_acct'] == True)
			{
				$this->account_id = $GLOBALS['phpgw']->accounts->auto_add($this->account_lid, $passwd);
			}
			else
			{
				$this->account_id = $GLOBALS['phpgw']->accounts->name2id($this->account_lid);
			}
			$GLOBALS['phpgw_info']['user']['account_id'] = $this->account_id;
			$GLOBALS['phpgw']->accounts->accounts($this->account_id);

			$this->sessionid    = md5($GLOBALS['phpgw']->common->randomstring(10));
			$this->kp3          = md5($GLOBALS['phpgw']->common->randomstring(15));

			$GLOBALS['phpgw']->common->key = md5($this->kp3 . $this->sessionid . $GLOBALS['phpgw_info']['server']['encryptkey']);
			$GLOBALS['phpgw']->common->iv  = $GLOBALS['phpgw_info']['server']['mcrypt_iv'];
			$cryptovars[0] = $GLOBALS['phpgw']->common->key;
			$cryptovars[1] = $GLOBALS['phpgw']->common->iv;
			$GLOBALS['phpgw']->crypto = CreateObject('phpgwapi.crypto', $cryptovars);

			if ($GLOBALS['phpgw_info']['server']['usecookies'])
			{
				Setcookie('sessionid',$this->sessionid);
				Setcookie('kp3',$this->kp3);
				Setcookie('domain',$this->account_domain);
				Setcookie('last_domain',$this->account_domain,$now+1209600);
				if ($this->account_domain == $GLOBALS['phpgw_info']['server']['default_domain'])
				{
					Setcookie('last_loginid', $this->account_lid ,$now+1209600);  // For 2 weeks
				}
				else
				{
					Setcookie('last_loginid', $login ,$now+1209600);              // For 2 weeks
				}
				unset ($GLOBALS['phpgw_info']['server']['default_domain']);                 // we kill this for security reasons
			}

			$this->read_repositories(False);
			if ($this->user['expires'] != -1 && $this->user['expires'] < time())
			{
				$phpgw->log->message('W-LoginFailure, account loginid %1 is expired',$this->account_lid);
				$phpgw->log->commit();

				return False;
			}

			$GLOBALS['phpgw_info']['user']  = $this->user;
			$GLOBALS['phpgw_info']['hooks'] = $this->hooks;

			$this->appsession('password','phpgwapi',base64_encode($this->passwd));
			if ($GLOBALS['phpgw']->acl->check('anonymous',1,'phpgwapi'))
			{
				$session_flags = 'A';
			}
			else
			{
				$session_flags = 'N';
			}

			$user_ip  = $this->getuser_ip();

			$GLOBALS['phpgw']->db->transaction_begin();
			$GLOBALS['phpgw']->db->query("insert into phpgw_sessions values ('" . $this->sessionid
								. "','".$login."','" . $user_ip . "','"
								. $now . "','" . $now . "','" . $GLOBALS['PHP_SELF'] . "','" . $session_flags
								. "')",__LINE__,__FILE__);

			$GLOBALS['phpgw']->db->query("insert into phpgw_access_log values ('" . $this->sessionid . "','"
								. "$login','" . $user_ip . "','$now','','" . $this->account_id . "')",__LINE__,__FILE__);

			$this->appsession('account_previous_login','phpgwapi',$GLOBALS['phpgw']->auth->previous_login);
			$GLOBALS['phpgw']->auth->update_lastlogin($this->account_id,$user_ip);
			$GLOBALS['phpgw']->db->transaction_commit();

			return $this->sessionid;
		}

		function verify_server($sessionid, $kp3)
		{
			$GLOBALS['phpgw']->interserver = CreateObject('phpgwapi.interserver');
			$db              = $GLOBALS['phpgw']->db;
			$db2             = $GLOBALS['phpgw']->db;
			$this->sessionid = $sessionid;
			$this->kp3       = $kp3;

			$GLOBALS['phpgw']->common->key  = md5($this->kp3 . $this->sessionid . $GLOBALS['phpgw_info']['server']['encryptkey']);
			$GLOBALS['phpgw']->common->iv   = $GLOBALS['phpgw_info']['server']['mcrypt_iv'];

			$cryptovars[0] = $GLOBALS['phpgw']->common->key;
			$cryptovars[1] = $GLOBALS['phpgw']->common->iv;
			$GLOBALS['phpgw']->crypto = CreateObject('phpgwapi.crypto', $cryptovars);

			$db->query("select * from phpgw_sessions where session_id='" . $this->sessionid . "'",__LINE__,__FILE__);
			$db->next_record();

			$this->session_flags = $db->f('session_flags');

			$login_array = explode('@', $db->f('session_lid'));
			$this->account_lid = $login_array[0];

			if (@$login_array[1] != '')
			{
				$this->account_domain = $login_array[1];
			}
			else
			{
				$this->account_domain = $GLOBALS['phpgw_info']['server']['default_domain'];
			}

			$GLOBALS['phpgw_info']['user']['kp3'] = $this->kp3;
			$phpgw_info_flags    = $GLOBALS['phpgw_info']['flags'];

			$GLOBALS['phpgw_info']['flags'] = $phpgw_info_flags;
			$userid_array = explode('@',$db->f('session_lid'));
// Thinking this might solve auth_http	problems
			if(@$userid_array[1] == '') { $userid_array[1] = 'default'; }
			$this->account_lid = $userid_array[1];
			$this->update_dla();
			$this->account_id = $GLOBALS['phpgw']->interserver->name2id($this->account_lid);

			if (!$this->account_id)
			{
				return False;
			}

			$GLOBALS['phpgw_info']['user']['account_id'] = $this->account_id;
			
			$this->read_repositories(@$GLOBALS['phpgw_info']['server']['cache_phpgw_info']);

			$GLOBALS['phpgw_info']['user']  = $this->user;
			$GLOBALS['phpgw_info']['hooks'] = $this->hooks;

			$GLOBALS['phpgw_info']['user']['session_ip']  = $db->f('session_ip');
			$GLOBALS['phpgw_info']['user']['passwd'] = base64_decode($this->appsession('password','phpgwapi'));

			if ($userid_array[1] != $GLOBALS['phpgw_info']['user']['domain'])
			{
				$GLOBALS['phpgw']->log->message('W-VerifySession, the domains %1 and %2 don\t match',$userid_array[1],$GLOBALS['phpgw_info']['user']['domain']);
				$GLOBALS['phpgw']->log->commit();

				return False;
			}

			if (@$GLOBALS['phpgw_info']['server']['sessions_checkip'])
			{
				if (PHP_OS != 'Windows' && (! $GLOBALS['phpgw_info']['user']['session_ip'] || $GLOBALS['phpgw_info']['user']['session_ip'] != $this->getuser_ip()))
				{
					// This needs some better wording
					$GLOBALS['phpgw']->log->message('W-VerifySession, IP %1 doesn\'t match IP %2 in session table',$this->getuser_ip(),$GLOBALS['phpgw_info']['user']['session_ip']);
					$GLOBALS['phpgw']->log->commit();

					return False;
				}
			}

			$GLOBALS['phpgw']->acl->acl($this->account_id);
			$GLOBALS['phpgw']->accounts->accounts($this->account_id);
			$GLOBALS['phpgw']->preferences->preferences($this->account_id);
			$GLOBALS['phpgw']->applications->applications($this->account_id);

			if (! $this->account_lid)
			{
				// This needs some better wording
				$GLOBALS['phpgw']->log->message('W-VerifySession, account_id is empty');
				$GLOBALS['phpgw']->log->commit();

				return False;
			}
			else
			{
				return True;
			}
		}

		function create_server($login,$passwd)
		{
			$GLOBALS['phpgw']->interserver = CreateObject('phpgwapi.interserver');
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
				$this->account_domain = $GLOBALS['phpgw_info']['server']['default_domain'];
			}

			$serverdata = array(
				'server_name' => $this->account_domain,
				'username'    => $this->account_lid,
				'password'    => $passwd
			);
			if (!$GLOBALS['phpgw']->interserver->auth($serverdata))
			{
				return False;
				exit;
			}

			if (!$GLOBALS['phpgw']->interserver->exists($this->account_lid))
			{
				$this->account_id = $GLOBALS['phpgw']->interserver->name2id($this->account_lid);
			}
			$GLOBALS['phpgw_info']['user']['account_id'] = $this->account_id;
			$GLOBALS['phpgw']->interserver->serverid = $this->account_id;

			$this->sessionid    = md5($GLOBALS['phpgw']->common->randomstring(10));
			$this->kp3          = md5($GLOBALS['phpgw']->common->randomstring(15));

			$GLOBALS['phpgw']->common->key = md5($this->kp3 . $this->sessionid . $GLOBALS['phpgw_info']['server']['encryptkey']);
			$GLOBALS['phpgw']->common->iv  = $GLOBALS['phpgw_info']['server']['mcrypt_iv'];
			$cryptovars[0] = $GLOBALS['phpgw']->common->key;
			$cryptovars[1] = $GLOBALS['phpgw']->common->iv;
			$GLOBALS['phpgw']->crypto = CreateObject('phpgwapi.crypto', $cryptovars);

			//$this->read_repositories(False);

			$GLOBALS['phpgw_info']['user']  = $this->user;
			$GLOBALS['phpgw_info']['hooks'] = $this->hooks;

			$this->appsession('password','phpgwapi',base64_encode($this->passwd));
			$session_flags = 'S';

			$user_ip  = $this->getuser_ip();

			$GLOBALS['phpgw']->db->transaction_begin();
			$GLOBALS['phpgw']->db->query("INSERT INTO phpgw_sessions VALUES ('" . $this->sessionid
								. "','".$login."','" . $user_ip . "','"
								. $now . "','" . $now . "','" . $GLOBALS['PHP_SELF'] . "','" . $session_flags
								. "')",__LINE__,__FILE__);

			$GLOBALS['phpgw']->db->query("INSERT INTO phpgw_access_log VALUES ('" . $this->sessionid . "','"
								. "$login','" . $user_ip . "','$now','','" . $this->account_id . "')",__LINE__,__FILE__);

			$this->appsession('account_previous_login','phpgwapi',$GLOBALS['phpgw']->auth->previous_login);
			$GLOBALS['phpgw']->auth->update_lastlogin($this->account_id,$user_ip);
			$GLOBALS['phpgw']->db->transaction_commit();

			return array($this->sessionid,$this->kp3);
		}

		// This will update the DateLastActive column, so the login does not expire
		function update_dla()
		{
			if (isset($GLOBALS['HTTP_GET_VARS']['menuaction']))
			{
				$action = $GLOBALS['HTTP_GET_VARS']['menuaction'];			
			}
			else
			{
				$action = $GLOBALS['PHP_SELF'];
			}

			$GLOBALS['phpgw']->db->query("update phpgw_sessions set session_dla='" . time() . "', session_action='$action' "
								. "where session_id='" . $this->sessionid."'",__LINE__,__FILE__);

			$GLOBALS['phpgw']->db->query("update phpgw_app_sessions set session_dla='" . time() . "' "
								. "where sessionid='" . $this->sessionid."'",__LINE__,__FILE__);
		}
    
		function destroy($sessionid='',$kp3='')
		{
			if(empty($sessionid) || !$sessionid)
			{
				$sessionid = $GLOBALS['HTTP_GET_VARS']['sessionid'] ? $GLOBALS['HTTP_GET_VARS']['sessionid'] : $GLOBALS['HTTP_COOKIE_VARS']['sessionid'];
				$kp3       = $GLOBALS['HTTP_GET_VARS']['kp3']       ? $GLOBALS['HTTP_GET_VARS']['kp3']       : $GLOBALS['HTTP_COOKIE_VARS']['kp3'];
			}

			if(!$sessionid && $kp3)
			{
				return False;
			}

			$GLOBALS['phpgw_info']['user']['sessionid'] = $sessionid;
			$GLOBALS['phpgw_info']['user']['kp3'] = $kp3;
	 
			$GLOBALS['phpgw']->db->transaction_begin();
			$GLOBALS['phpgw']->db->query("delete from phpgw_sessions where session_id='"
								. $sessionid . "'",__LINE__,__FILE__);
			$GLOBALS['phpgw']->db->query("delete from phpgw_app_sessions where sessionid='"
								. $sessionid . "'",__LINE__,__FILE__);
			$GLOBALS['phpgw']->db->query("update phpgw_access_log set lo='" . time() . "' where sessionid='"
								. $sessionid . "'",__LINE__,__FILE__);
			if ($GLOBALS['phpgw_info']['server']['usecookies'])
			{
				Setcookie('sessionid');
				Setcookie('kp3');
				if ($GLOBALS['phpgw_info']['multiable_domains'])
				{
					Setcookie('domain');
				}
			}
			$this->clean_sessions();
			$GLOBALS['phpgw']->db->transaction_commit();

			return True;
		}

		/*************************************************************************\
		* Functions for appsession data and session cache                         *
		\*************************************************************************/
		function read_repositories($cached='')
		{
			$GLOBALS['phpgw']->acl->acl($this->account_id);
			$GLOBALS['phpgw']->accounts->accounts($this->account_id);
			$GLOBALS['phpgw']->preferences->preferences($this->account_id);
			$GLOBALS['phpgw']->applications->applications($this->account_id);
			
			if(@$cached)
			{
				$this->user = $this->appsession('phpgw_info_cache','phpgwapi');
				if(!empty($this->user))
				{
					$GLOBALS['phpgw']->preferences->data = $this->user['preferences'];
					if (!isset($GLOBALS['phpgw_info']['apps']) || !is_array($GLOBALS['phpgw_info']['apps']))
					{
						$GLOBALS['phpgw']->applications->read_installed_apps();
					}
				}
				else
				{
					$this->setup_cache();
				}
			}
			else
			{
				$this->setup_cache();
			}
			$this->hooks = $GLOBALS['phpgw']->hooks->read();
		}

		function setup_cache()
		{
			$this->user                = $GLOBALS['phpgw']->accounts->read_repository();
			$this->user['acl']         = $GLOBALS['phpgw']->acl->read_repository();
			$this->user['preferences'] = $GLOBALS['phpgw']->preferences->read_repository();
			$this->user['apps']        = $GLOBALS['phpgw']->applications->read_repository();
			//@reset($this->data['user']['apps']);

			$this->user['domain']      = $this->account_domain;
			$this->user['sessionid']   = $this->sessionid;
			$this->user['kp3']         = $this->kp3;
			$this->user['session_ip']  = $this->getuser_ip();
			$this->user['session_lid'] = $this->account_lid.'@'.$this->account_domain;
			$this->user['account_id']  = $this->account_id;
			$this->user['account_lid'] = $this->account_lid;
			$this->user['userid']      = $this->account_lid;
			$this->user['passwd']      = @$this->passwd;
			if(@$GLOBALS['phpgw_info']['server']['cache_phpgw_info'])
			{
				$this->appsession('phpgw_info_cache','phpgwapi',$this->user);
			}
		}

		function delete_cache($accountid='')
		{
			$account_id = get_account_id($accountid,$this->account_id);

			$query = "DELETE FROM phpgw_app_sessions WHERE loginid = '".$account_id."'"
				." AND app = 'phpgwapi' and location = 'phpgw_info_cache'";

			$GLOBALS['phpgw']->db->query($query);			
		}
	
		function save_repositories()
		{
			$phpgw_info_temp = $GLOBALS['phpgw_info'];
			$phpgw_info_temp['user']['kp3'] = '';
			$phpgw_info_temp['flags'] = array();
			
			if ($GLOBALS['phpgw_info']['server']['cache_phpgw_info'])
			{
				$this->appsession('phpgw_info_cache','phpgwapi',$phpgw_info_temp);
			}
		}
	
		function appsession($location = 'default', $appname = '', $data = '##NOTHING##')
		{
			if (! $appname)
			{
				$appname = $GLOBALS['phpgw_info']['flags']['currentapp'];
			}
			
			/* This allows the user to put "" as the value. */
			if ($data == '##NOTHING##') {
				$query = "select content from phpgw_app_sessions where"
				." sessionid = '".$this->sessionid."' and loginid = '".$this->account_id."'"
				." and app = '".$appname."' and location = '".$location."'";
	
				$GLOBALS['phpgw']->db->query($query,__LINE__,__FILE__);
				$GLOBALS['phpgw']->db->next_record();

				// I added these into seperate steps for easier debugging
				$data = $GLOBALS['phpgw']->db->f('content');
				// Changed by Skeeter 2001 Mar 04 0400Z
				// This was not properly decoding structures saved into session data properly
//				$data = $GLOBALS['phpgw']->common->decrypt($data);
//				return stripslashes($data);
				return $GLOBALS['phpgw']->crypto->decrypt($data);

			} else {
				$GLOBALS['phpgw']->db->query("select content from phpgw_app_sessions where "
				. "sessionid = '".$this->sessionid."' and loginid = '".$this->account_id."'"
				. " and app = '".$appname."' and location = '".$location."'",__LINE__,__FILE__);
				
				$encrypteddata = $GLOBALS['phpgw']->crypto->encrypt($data);
				if ($GLOBALS['phpgw']->db->num_rows()==0) {
					$GLOBALS['phpgw']->db->query("INSERT INTO phpgw_app_sessions (sessionid,loginid,app,location,content,session_dla) "
					. "VALUES ('".$this->sessionid."','".$this->account_id."','".$appname
					. "','".$location."','".$encrypteddata."','" . time() . "')",__LINE__,__FILE__);
				} else {
					$GLOBALS['phpgw']->db->query("update phpgw_app_sessions set content = '".$encrypteddata."'"
					. "where sessionid = '".$this->sessionid."'"
					. "and loginid = '".$this->account_id."' and app = '".$appname."'"
					. "and location = '".$location."'",__LINE__,__FILE__);
				}
				return $data;
			}
		}
		
		function restore()
		{
			$sessionData = $this->appsession("sessiondata");
			
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
				$this->appsession("sessiondata",'',$sessionData);
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
		function link($url, $extravars = '')
		{
			global $usercookie;

			$kp3 = $GLOBALS['HTTP_GET_VARS']['kp3'] ? $GLOBALS['HTTP_GET_VARS']['kp3'] : $GLOBALS['HTTP_COOKIE_VARS']['kp3'];

			if (! $kp3)
			{
				$kp3 = $GLOBALS['phpgw_info']['user']['kp3'];
			}

			// Explicit hack to work around problems with php running as CGI on windows
			// please let us know if this doesn't work for you!

			// I am not sure how my changes will affect the following.
			// Could someone with access to a Windows install check it ?  (jengo)

/*			if (! $url && (PHP_OS == 'Windows' || PHP_OS == 'OS/2' || PHP_OS == 'WIN32' || PHP_OS == 'WIN16'))
			{
				$exe = strpos($GLOBALS['PHP_SELF'],'php.exe');
				if ($exe != false) {
					$exe += 7; // strlen('php.exe')
					$url_root = split ('/', $GLOBALS['phpgw_info']['server']['webserver_url']);
					$url = (strlen($url_root[0])? $url_root[0].'//':'') . $url_root[2];
					$url .= substr($PHP_SELF,$exe,strlen($GLOBALS['PHP_SELF'])-$exe);
				}
			}
*/

			$url = $GLOBALS['phpgw_info']['server']['webserver_url'] . $url;

			// This needs to be tested as well. (jengo)

/*			if (! $url)
			{
				$url_root = split ('/', $GLOBALS['phpgw_info']['server']['webserver_url']);
				// Some hosting providers have their paths screwy.
				//	 If the value from $PHP_SELF is not what you expect, you can use this to patch it
				//	 It will need to be adjusted to your specific problem tho.
				//
				//$patched_php_self = str_replace('/php4/php/phpgroupware', '/phpgroupware', $GLOBALS['PHP_SELF']);
				$patched_php_self = $GLOBALS['PHP_SELF'];
				$url = (strlen($url_root[0])? $url_root[0].'//':'') . $url_root[2] . $patched_php_self;
			}
*/

			// build the extravars string from a array
			
			if (is_array($extravars))
			{
				while(list($key,$value) = each($extravars))
				{
					if (!empty($new_extravars)) $new_extravars .= '&';
					$new_extravars .= "$key=$value";
				}
				// This needs to be explictly reset to a string variable type for PHP3
				settype($extravars,'string');
				$extravars = $new_extravars;
			}

			if (isset($GLOBALS['phpgw_info']['server']['usecookies']) && $GLOBALS['phpgw_info']['server']['usecookies'])
			{
				if ($extravars)
				{
					$url .= '?' . $extravars;
				}
			}
			else
			{
				$sessionID  = 'sessionid=' . @$GLOBALS['phpgw_info']['user']['sessionid'];
				$sessionID .= '&kp3=' . $kp3;
				$sessionID .= '&domain=' . @$GLOBALS['phpgw_info']['user']['domain'];
				// This doesn't belong in the API.
				// Its up to the app to pass this value. (jengo)
				// Putting it into the app requires a massive number of updates in email app. 
				// Until that happens this needs to stay here (seek3r)
				if (isset($GLOBALS['phpgw_info']['flags']['newsmode']) && 
					$GLOBALS['phpgw_info']['flags']['newsmode'])
				{
					$url .= '&newsmode=on';
				}

				if ($extravars)
				{
					$url .= '?' . $extravars . '&' . $sessionID;
				}
				else
				{
					$url .= '?' . $sessionID;
				}
			}

/*			$url = str_replace('/?', '/index.php?', $url);
			$webserver_url_count = strlen($GLOBALS['phpgw_info']['server']['webserver_url']);
			$slash_check = strtolower(substr($url ,0,1));
			if (substr($url ,0,$webserver_url_count) != $GLOBALS['phpgw_info']['server']['webserver_url'])
			{
				$app = $GLOBALS['phpgw_info']['flags']['currentapp'];
				if ($slash_check == '/')
				{
					$url = $GLOBALS['phpgw_info']['server']['webserver_url'] . $url;
				}
				elseif ($app == 'home' || $app == 'logout' || $app == 'login')
				{
					$url = $GLOBALS['phpgw_info']['server']['webserver_url'].'/'.$url; 
				}
				else
				{
					$url = $GLOBALS['phpgw_info']['server']['webserver_url'].'/'.$app.'/'.$url; 
				}
			} */
			//echo "$url\n";
			return $url;
		}  
	}
?>
