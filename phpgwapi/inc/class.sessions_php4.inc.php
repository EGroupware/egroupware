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
		var $key;
		var $iv;

		var $data;
		var $public_functions = array(
			'list_methods' => True,
			'update_dla'   => True
		);

		/*************************************************************************\
		* Constructor just loads up some defaults from cookies                    *
		\*************************************************************************/
		function sessions()
		{
			$this->sessionid = get_var('sessionid',Array('COOKIE','GET'));
			$this->kp3       = get_var('kp3',Array('COOKIE','GET'));
			/* Create the crypto object */
			$GLOBALS['phpgw']->crypto = CreateObject('phpgwapi.crypto');
		}

		function DONTlist_methods($_type)
		{
			if (is_array($_type))
			{
				$_type = $_type['type'];
			}

			switch($_type)
			{
				case 'xmlrpc':
					$xml_functions = array(
						'list_methods' => array(
							'function'  => 'list_methods',
							'signature' => array(array(xmlrpcStruct,xmlrpcString)),
							'docstring' => lang('Read this list of methods.')
						),
						'update_dla' => array(
							'function'  => 'update_dla',
							'signature' => array(array(xmlrpcBoolean)),
							'docstring' => lang('Returns an array of todo items')
						)
					);
					return $xml_functions;
					break;
				case 'soap':
					return $this->soap_functions;
					break;
				default:
					return array();
					break;
			}
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
				$sessionid = get_var('sessionid',Array('COOKIE','GET'));
				$kp3       = get_var('kp3',Array('COOKIE','GET'));
			}

			$this->sessionid = $sessionid;
			$this->kp3       = $kp3;
			

			session_start();
			$GLOBALS['phpgw_session'] = $GLOBALS['HTTP_SESSION_VARS']['phpgw_session'];

			$this->session_flags = $GLOBALS['phpgw_session']['session_flags'];
			
			$login_array = explode('@',$GLOBALS['phpgw_session']['session_lid']);
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

			$userid_array = explode('@',$GLOBALS['phpgw_session']['session_lid']);
// Thinking this might solve auth_http problems
			if(@$userid_array[1] == '')
			{
				$userid_array[1] = 'default';
			}
			$this->account_lid = $userid_array[0];
			$this->update_dla();
			$this->account_id = $GLOBALS['phpgw']->accounts->name2id($this->account_lid);

			if (!$this->account_id)
			{
				return False;
			}

			$GLOBALS['phpgw_info']['user']['account_id'] = $this->account_id;

			/* init the crypto object before appsession call below */
			$this->key = md5($this->kp3 . $this->sessionid . $GLOBALS['phpgw_info']['server']['encryptkey']);
			$this->iv  = $GLOBALS['phpgw_info']['server']['mcrypt_iv'];
			$GLOBALS['phpgw']->crypto->init(array($this->key,$this->iv));

			$this->read_repositories(@$GLOBALS['phpgw_info']['server']['cache_phpgw_info']);
			if ($this->user['expires'] != -1 && $this->user['expires'] < time())
			{
				if(is_object($GLOBALS['phpgw']->log))
				{
					$GLOBALS['phpgw']->log->message(array(
						'text' => 'W-VerifySession, account loginid %1 is expired',
						'p1'   => $this->account_lid,
						'line' => __LINE__,
						'file' => __FILE__
					));
					$GLOBALS['phpgw']->log->commit();
				}
				if(is_object($GLOBALS['phpgw']->crypto))
				{
					$GLOBALS['phpgw']->crypto->cleanup();
					unset($GLOBALS['phpgw']->crypto);
				}
				return False;
			}

			$GLOBALS['phpgw_info']['user']  = $this->user;
			$GLOBALS['phpgw_info']['hooks'] = $this->hooks;

			$GLOBALS['phpgw_info']['user']['session_ip'] = $GLOBALS['phpgw_session']['session_ip'];
			$GLOBALS['phpgw_info']['user']['passwd']     = base64_decode($this->appsession('password','phpgwapi'));

			if ($userid_array[1] != $GLOBALS['phpgw_info']['user']['domain'])
			{
				if(is_object($GLOBALS['phpgw']->log))
				{
					$GLOBALS['phpgw']->log->message(array(
						'text' => 'W-VerifySession, the domains %1 and %2 don\'t match',
						'p1'   => $userid_array[1],
						'p2'   => $GLOBALS['phpgw_info']['user']['domain'],
						'line' => __LINE__,
						'file' => __FILE__
					));
					$GLOBALS['phpgw']->log->commit();
				}
				if(is_object($GLOBALS['phpgw']->crypto))
				{
					$GLOBALS['phpgw']->crypto->cleanup();
					unset($GLOBALS['phpgw']->crypto);
				}
				return False;
			}

			if (@$GLOBALS['phpgw_info']['server']['sessions_checkip'])
			{
				if (PHP_OS != 'Windows' && (! $GLOBALS['phpgw_info']['user']['session_ip'] || $GLOBALS['phpgw_info']['user']['session_ip'] != $this->getuser_ip()))
				{
					if(is_object($GLOBALS['phpgw']->log))
					{
						// This needs some better wording
						$GLOBALS['phpgw']->log->message(array(
							'text' => 'W-VerifySession, IP %1 doesn\'t match IP %2 in session table',
							'p1'   => $this->getuser_ip(),
							'p2'   => $GLOBALS['phpgw_info']['user']['session_ip'],
							'line' => __LINE__,
							'file' => __FILE__
						));
						$GLOBALS['phpgw']->log->commit();
					}
					if(is_object($GLOBALS['phpgw']->crypto))
					{
						$GLOBALS['phpgw']->crypto->cleanup();
						unset($GLOBALS['phpgw']->crypto);
					}
					return False;
				}
			}

			$GLOBALS['phpgw']->acl->acl($this->account_id);
			$GLOBALS['phpgw']->accounts->accounts($this->account_id);
			$GLOBALS['phpgw']->preferences->preferences($this->account_id);
			$GLOBALS['phpgw']->applications->applications($this->account_id);

			if (! $this->account_lid)
			{
				if(is_object($GLOBALS['phpgw']->log))
				{
					// This needs some better wording
					$GLOBALS['phpgw']->log->message(array(
						'text' => 'W-VerifySession, account_id is empty',
						'line' => __LINE__,
						'file' => __FILE__
					));
					$GLOBALS['phpgw']->log->commit();
				}
				if(is_object($GLOBALS['phpgw']->crypto))
				{
					$GLOBALS['phpgw']->crypto->cleanup();
					unset($GLOBALS['phpgw']->crypto);
				}
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
			// With php4 sessions support this isnt really our job
		}

		function create($login,$passwd = '',$passwd_type = '')
		{
			if (is_array($login))
			{
				$this->login       = $login['login'];
				$this->passwd      = $login['passwd'];
				$this->passwd_type = $login['passwd_type'];
				$login             = $this->login;
			}
			else
			{
				$this->login       = $login;
				$this->passwd      = $passwd;
				$this->passwd_type = $passwd_type;
			}

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

			if (! $GLOBALS['phpgw']->auth->authenticate($this->account_lid, $this->passwd, $this->passwd_type) || $GLOBALS['phpgw']->accounts->get_type($this->account_lid) == 'g')
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

			$this->sessionid = md5($GLOBALS['phpgw']->common->randomstring(10));
			$this->kp3       = md5($GLOBALS['phpgw']->common->randomstring(15));

			if ($GLOBALS['phpgw_info']['server']['usecookies'])
			{
				Setcookie('sessionid',$this->sessionid);
				Setcookie('kp3',$this->kp3);
				Setcookie('domain',$this->account_domain);
				Setcookie('last_domain',$this->account_domain,$now+1209600);
				if ($this->account_domain == $GLOBALS['phpgw_info']['server']['default_domain'])
				{
					Setcookie('last_loginid', $this->account_lid ,$now+1209600); /* For 2 weeks */
				}
				else
				{
					Setcookie('last_loginid', $login ,$now+1209600); /* For 2 weeks */
				}
				unset($GLOBALS['phpgw_info']['server']['default_domain']); /* we kill this for security reasons */
			}

			$this->read_repositories(False);
			if ($this->user['expires'] != -1 && $this->user['expires'] < time())
			{
				if(is_object($GLOBALS['phpgw']->log))
				{
					$GLOBALS['phpgw']->log->message(array(
						'text' => 'W-LoginFailure, account loginid %1 is expired',
						'p1'   => $this->account_lid,
						'line' => __LINE__,
						'file' => __FILE__
					));
					$GLOBALS['phpgw']->log->commit();
				}

				return False;
			}

			/* init the crypto object */
			$this->key = md5($this->kp3 . $this->sessionid . $GLOBALS['phpgw_info']['server']['encryptkey']);
			$this->iv  = $GLOBALS['phpgw_info']['server']['mcrypt_iv'];
			$GLOBALS['phpgw']->crypto->init(array($this->key,$this->iv));

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

			$user_ip = $this->getuser_ip();

			session_start();

			$GLOBALS['phpgw_session']['session_id'] = $this->sessionid;
			$GLOBALS['phpgw_session']['session_lid'] = $login;
			$GLOBALS['phpgw_session']['session_ip'] = $user_ip;
			$GLOBALS['phpgw_session']['session_logintime'] = $now;
			$GLOBALS['phpgw_session']['session_dla'] = $now;
			$GLOBALS['phpgw_session']['session_action'] = $GLOBALS['PHP_SELF'];
			$GLOBALS['phpgw_session']['session_flags'] = $session_flags;
		
			session_register('phpgw_session');
			$GLOBALS['HTTP_SESSION_VARS']['phpgw_session'] = $GLOBALS['phpgw_session'];

			//$GLOBALS['phpgw']->db->query('INSERT INTO phpgw_access_log(sessionid,loginid,ip,li,lo,account_id) '
			//	." VALUES ('" . $this->sessionid . "','" . "$login','" . $user_ip . "',".$now.",''," . $this->account_id . ")",__LINE__,__FILE__);

			$this->appsession('account_previous_login','phpgwapi',$GLOBALS['phpgw']->auth->previous_login);
			$GLOBALS['phpgw']->auth->update_lastlogin($this->account_id,$user_ip);

			return $this->sessionid;
		}

		function verify_server($sessionid, $kp3)
		{
			$GLOBALS['phpgw']->interserver = CreateObject('phpgwapi.interserver');
			$this->sessionid = $sessionid;
			$this->kp3       = $kp3;

			session_start();
			$GLOBALS['phpgw_session'] = $GLOBALS['HTTP_SESSION_VARS']['phpgw_session'];
			
			$this->session_flags = $GLOBALS['phpgw_session']['session_flags'];

			$login_array = explode('@', $GLOBALS['phpgw_session']['session_lid']);
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
			$phpgw_info_flags = $GLOBALS['phpgw_info']['flags'];

			$GLOBALS['phpgw_info']['flags'] = $phpgw_info_flags;
			$userid_array = explode('@',$GLOBALS['phpgw_session']['session_lid']);
// Thinking this might solve auth_http problems
			if(@$userid_array[1] == '')
			{
				$userid_array[1] = 'default';
			}
			$this->account_lid = $userid_array[1];
			$this->update_dla();
			$this->account_id = $GLOBALS['phpgw']->interserver->name2id($this->account_lid);

			if (!$this->account_id)
			{
				return False;
			}

			$GLOBALS['phpgw_info']['user']['account_id'] = $this->account_id;
			
			$this->read_repositories(@$GLOBALS['phpgw_info']['server']['cache_phpgw_info']);

			/* init the crypto object before appsession call below */
			$this->key = md5($this->kp3 . $this->sessionid . $GLOBALS['phpgw_info']['server']['encryptkey']);
			$this->iv  = $GLOBALS['phpgw_info']['server']['mcrypt_iv'];
			$GLOBALS['phpgw']->crypto->init(array($this->key,$this->iv));

			$GLOBALS['phpgw_info']['user']  = $this->user;
			$GLOBALS['phpgw_info']['hooks'] = $this->hooks;

			$GLOBALS['phpgw_info']['user']['session_ip'] = $GLOBALS['phpgw_session']['session_ip'];
			$GLOBALS['phpgw_info']['user']['passwd'] = base64_decode($this->appsession('password','phpgwapi'));

			if ($userid_array[1] != $GLOBALS['phpgw_info']['user']['domain'])
			{
				if(is_object($GLOBALS['phpgw']->log))
				{
					$GLOBALS['phpgw']->log->message(array(
						'text' => 'W-VerifySession, the domains %1 and %2 don\t match',
						'p1'   => $userid_array[1],
						'p2'   => $GLOBALS['phpgw_info']['user']['domain'],
						'line' => __LINE__,
						'file' => __FILE__
					));
					$GLOBALS['phpgw']->log->commit();
				}

				if(is_object($GLOBALS['phpgw']->crypto))
				{
					$GLOBALS['phpgw']->crypto->cleanup();
					unset($GLOBALS['phpgw']->crypto);
				}
				return False;
			}

			if (@$GLOBALS['phpgw_info']['server']['sessions_checkip'])
			{
				if (PHP_OS != 'Windows' && (! $GLOBALS['phpgw_info']['user']['session_ip'] || $GLOBALS['phpgw_info']['user']['session_ip'] != $this->getuser_ip()))
				{
					if(is_object($GLOBALS['phpgw']->log))
					{
						// This needs some better wording
						$GLOBALS['phpgw']->log->message(array(
							'text' => 'W-VerifySession, IP %1 doesn\'t match IP %2 in session table',
							'p1'   => $this->getuser_ip(),
							'p2'   => $GLOBALS['phpgw_info']['user']['session_ip'],
							'line' => __LINE__,
							'file' => __FILE__
						));
						$GLOBALS['phpgw']->log->commit();
					}

					if(is_object($GLOBALS['phpgw']->crypto))
					{
						$GLOBALS['phpgw']->crypto->cleanup();
						unset($GLOBALS['phpgw']->crypto);
					}
					return False;
				}
			}

			$GLOBALS['phpgw']->acl->acl($this->account_id);
			$GLOBALS['phpgw']->accounts->accounts($this->account_id);
			$GLOBALS['phpgw']->preferences->preferences($this->account_id);
			$GLOBALS['phpgw']->applications->applications($this->account_id);

			if (! $this->account_lid)
			{
				if(is_object($GLOBALS['phpgw']->log))
				{
					// This needs some better wording
					$GLOBALS['phpgw']->log->message(array(
						'text' => 'W-VerifySession, account_id is empty',
						'line' => __LINE__,
						'file' => __FILE__
					));
					$GLOBALS['phpgw']->log->commit();
				}

				if(is_object($GLOBALS['phpgw']->crypto))
				{
					$GLOBALS['phpgw']->crypto->cleanup();
					unset($GLOBALS['phpgw']->crypto);
				}
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

			$this->sessionid = md5($GLOBALS['phpgw']->common->randomstring(10));
			$this->kp3       = md5($GLOBALS['phpgw']->common->randomstring(15));

			/* re-init the crypto object */
			$this->key = md5($this->kp3 . $this->sessionid . $GLOBALS['phpgw_info']['server']['encryptkey']);
			$this->iv  = $GLOBALS['phpgw_info']['server']['mcrypt_iv'];
			$GLOBALS['phpgw']->crypto->init(array($this->key,$this->iv));

			//$this->read_repositories(False);

			$GLOBALS['phpgw_info']['user']  = $this->user;
			$GLOBALS['phpgw_info']['hooks'] = $this->hooks;

			$this->appsession('password','phpgwapi',base64_encode($this->passwd));
			$session_flags = 'S';

			$user_ip = $this->getuser_ip();

			session_start();

			$GLOBALS['phpgw_session']['session_id'] = $this->sessionid;
			$GLOBALS['phpgw_session']['session_lid'] = $login;
			$GLOBALS['phpgw_session']['session_ip'] = $user_ip;
			$GLOBALS['phpgw_session']['session_logintime'] = $now;
			$GLOBALS['phpgw_session']['session_dla'] = $now;
			$GLOBALS['phpgw_session']['session_action'] = $GLOBALS['PHP_SELF'];
			$GLOBALS['phpgw_session']['session_flags'] = $session_flags;
		
			session_register('phpgw_session');
			$GLOBALS['HTTP_SESSION_VARS']['phpgw_session'] = $GLOBALS['phpgw_session'];

			//$GLOBALS['phpgw']->db->query("INSERT INTO phpgw_access_log VALUES ('" . $this->sessionid . "','"
			//	. "$login','" . $user_ip . "','$now','','" . $this->account_id . "')",__LINE__,__FILE__);

			$this->appsession('account_previous_login','phpgwapi',$GLOBALS['phpgw']->auth->previous_login);
			$GLOBALS['phpgw']->auth->update_lastlogin($this->account_id,$user_ip);

			return array($this->sessionid,$this->kp3);
		}

		// This will update the DateLastActive column, so the login does not expire
		function update_dla()
		{
			global $PHP_SELF;
			if(MENUACTION)
			{
				$action = MENUACTION;
			}
			else
			{
				$action = $PHP_SELF;
			}

			$GLOBALS['phpgw_session']['session_dla'] = time();
			$GLOBALS['phpgw_session']['session_action'] = $action;
		
			session_register('phpgw_session');
			$GLOBALS['HTTP_SESSION_VARS']['phpgw_session'] = $GLOBALS['phpgw_session'];

			return True;
		}

		function destroy($sessionid, $kp3)
		{
			if (! $sessionid && $kp3)
			{
				return False;
			}

			session_unset();
			session_destroy();
			//$GLOBALS['phpgw']->db->query("UPDATE phpgw_access_log SET lo='" . time() . "' WHERE sessionid='"
			//	. $sessionid . "'",__LINE__,__FILE__);

			// Only do the following, if where working with the current user
			if ($sessionid == $GLOBALS['phpgw_info']['user']['sessionid'])
			{
				$this->clean_sessions();
			}
			$GLOBALS['phpgw']->db->transaction_commit();

			return True;
		}

		/*************************************************************************\
		* Functions for appsession data and session cache                         *
		\*************************************************************************/
		function read_repositories($cached='',$write_cache=True)
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

		function setup_cache($write_cache=True)
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
			if(@$GLOBALS['phpgw_info']['server']['cache_phpgw_info'] && $write_cache)
			{
				$this->delete_cache();
				$this->appsession('phpgw_info_cache','phpgwapi',$this->user);
			}
		}

		function delete_cache($accountid='')
		{
			$account_id = get_account_id($accountid,$this->account_id);

			$GLOBALS['phpgw_session']['phpgw_app_sessions']['phpgwapi']['phpgw_info_cache'] = '';
	
			session_register('phpgw_session');
			$GLOBALS['HTTP_SESSION_VARS']['phpgw_session'] = $GLOBALS['phpgw_session'];
		}

// This looks to be useless
// This will capture everything in the $GLOBALS['phpgw_info'] including server info,
// and store it in appsessions.  This is really incompatible with any type of restoring
// from appsession as the saved user info is really in ['user'] rather than the root of
// the structure, which is what this class likes.
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
			
			/* This allows the user to put '' as the value. */
			if ($data == '##NOTHING##')
			{
				// I added these into seperate steps for easier debugging
				$data = $GLOBALS['phpgw_session']['phpgw_app_sessions'][$appname][$location]['content'];

				/* do not decrypt and return if no data (decrypt returning garbage) */
				if($data)
				{
					$data = $GLOBALS['phpgw']->crypto->decrypt($data);
					//echo 'appsession returning: '; _debug_array($data);
					return $data;
				}
			}
			else
			{
				$encrypteddata = $GLOBALS['phpgw']->crypto->encrypt($data);
				$GLOBALS['phpgw_session']['phpgw_app_sessions'][$appname][$location]['content'] = $encrypteddata;
				session_register('phpgw_session');
				$GLOBALS['HTTP_SESSION_VARS']['phpgw_session'] = $GLOBALS['phpgw_session'];
				return $data;
			}
		}

		function restore()
		{
			$sessionData = $this->appsession('sessiondata');
			
			if (is_array($sessionData))
			{
				reset($sessionData);
				while(list($key,$value) = each($sessionData))
				{
					global $$key;
					$$key = $value;
					$this->variableNames[$key] = 'registered';
					// echo 'restored: '.$key.', ' . $value . '<br>';
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
				$this->appsession('sessiondata','',$sessionData);
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
			/* first we process the $url to build the full scriptname */
			$full_scriptname = True;

			$url_firstchar = substr($url ,0,1);
			if ($url_firstchar == '/' && $GLOBALS['phpgw_info']['server']['webserver_url'] == '/')
			{
				$full_scriptname = False;
			}

			if ($url_firstchar != '/')
			{
				$app = $GLOBALS['phpgw_info']['flags']['currentapp'];
				if ($app != 'home' && $app != 'login' && $app != 'logout')
				{
					$url = $app.'/'.$url;
				}
			}
			
			if($full_scriptname)
			{
				$webserver_url_count = strlen($GLOBALS['phpgw_info']['server']['webserver_url'])-1;
				if(substr($GLOBALS['phpgw_info']['server']['webserver_url'] ,$webserver_url_count,1) != '/' && $url_firstchar != '/')
				{
					$url = $GLOBALS['phpgw_info']['server']['webserver_url'] .'/'. $url;
				}
				else
				{
					$url = $GLOBALS['phpgw_info']['server']['webserver_url'] . $url;
				}
			}

			if(@isset($GLOBALS['phpgw_info']['server']['enforce_ssl']) && $GLOBALS['phpgw_info']['server']['enforce_ssl'] && !$GLOBALS['HTTP_SERVER_VARS']['HTTPS'])
			{
				if(substr($url ,0,4) != 'http')
				{
					$url = 'https://'.$GLOBALS['phpgw_info']['server']['hostname'].$url;
				}
				else
				{
					$url = str_replace ( 'http:', 'https:', $url);
				}
			}

			/* Now we process the extravars into a proper url format */
			/* if its not an array, then we turn it into one */
			/* We do this to help prevent any duplicates from being sent. */
			if (!is_array($extravars) && $extravars != '')
			{
				$a = explode('&', $extravars);
				$i = 0;
				while ($i < count($a))
				{
			    $b = split('=', $a[$i]);
					$new_extravars[$b[0]] = $b[1];
			   $i++;
				}
				$extravars = $new_extravars;
				unset($new_extravars);
			}

			/* if using frames we make sure there is a framepart */
			if(@defined('PHPGW_USE_FRAMES') && PHPGW_USE_FRAMES)
			{
				if (!isset($extravars['framepart']))
				{
					$extravars['framepart']='body';
				}
			}
			
			/* add session params if not using cookies */
			if (@!$GLOBALS['phpgw_info']['server']['usecookies'])
			{
				$kp3 = get_var('kp3',Array('COOKIE','GET'));
				if (!$kp3)
				{
					$kp3 = $GLOBALS['phpgw_info']['user']['kp3'];
				}

				$extravars['sessionid'] = @$GLOBALS['phpgw_info']['user']['sessionid'];
				$extravars['kp3'] = $kp3;
				$extravars['domain'] = @$GLOBALS['phpgw_info']['user']['domain'];
			}

			/* if we end up with any extravars then we generate the url friendly string */
			/* and return the result */
			if (is_array($extravars))
			{
				reset($extravars);
				while(list($key,$value) = each($extravars))
				{
					if (!empty($new_extravars))
					{
						$new_extravars .= '&';
					}
					$new_extravars .= $key.'='.htmlentities(urlencode($value));
				}
				/* This needs to be explictly reset to a string variable type for PHP3 */
				settype($extravars,'string');
				$extravars = $new_extravars;
				unset($new_extravars);
				return $url .= '?' . $extravars;
			}
			/* if no extravars then we return the cleaned up url/scriptname */
			return $url;
		}
	}

