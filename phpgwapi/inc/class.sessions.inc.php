<?php
	/**
	* eGW's Session Management
	*
	* This allows eGroupWare to use php or database sessions
	*
	* @link www.egroupware.org
	* @author NetUSE AG Boris Erdmann, Kristian Koehntopp
	* @author Dan Kuykendall <seek3r@phpgroupware.org>
	* @author Joseph Engo <jengo@phpgroupware.org>
	* @author Ralf Becker <ralfbecker@outdoor-training.de>
	* @copyright &copy; 1998-2000 NetUSE AG Boris Erdmann, Kristian Koehntopp <br> &copy; 2003 FreeSoftware Foundation
	* @license LGPL
	* @version $Id$
	*/

	/**
	* eGW's Session Management
	*
	* Baseclass for db- and php-sessions
	* 
	* @package api
	* @subpackage sessions
	*/
	class sessions_
	{
		/**
		* current user login (account_lid@domain)
		* 
		* @var string
		*/
		var $login;

		/**
		* current user password
		* 
		* @var string
		*/
		var $passwd;

		/**
		* current user db/ldap account id
		* 
		* @var int
		*/
		var $account_id;

		/**
		* current user account login id (without the eGW-domain/-instance part
		* 
		* @var string
		*/
		var $account_lid;

		/**
		* previous page call id - repost prevention, not used in eGW
		* 
		* @var string
		*/
		var $history_id;

		/**
		* domain for current user
		* 
		* @var string
		*/
		var $account_domain;

		/**
		* type flag, A - anonymous session, N - None, normal session
		* 
		* @var string
		*/
		var $session_flags;

		/**
		* current user session id
		* 
		* @var string
		*/
		var $sessionid;

		/**
		* an other session specific id (md5 from a random string),
		* used together with the sessionid for xmlrpc basic auth and the encryption of session-data (if that's enabled)
		* 
		* @var string
		*/
		var $kp3;

		/**
		* encryption key for the encrption of the session-data, if enabled
		* 
		* @var string
		*/
		var $key;

		/**
		* mcrypt's iv
		*  
		* @var string
		*/
		var $iv;

		/**
		* session data
		* 
		* @var array
		*/
		var $data;
        
		/**
		* instance of the database object
		* 
		* @var egw_db
		*/
		var $db;
		
		/**
		 * name of access-log table
		 * 
		 * @var string
		 */
		var $access_table = 'egw_access_log';
        
		/**
		* @var array publicly available methods
		*/
/*		var $public_functions = array(
			'list_methods' => True,
			'update_dla'   => True,
			'list'         => True,
			'total'        => True
		);*/

		/**
		* domain for cookies
		* 
		* @var string
		*/
		var $cookie_domain;
		
		/**
		 * path for cookies
		 * 
		 * @var string
		 */
		var $cookie_path;

		/**
		* name of XML-RPC/SOAP method called
		* 
		* @var string
		*/
		var $xmlrpc_method_called;

		/**
		* Array with the name of the system domains
		* 
		* @var array
		*/
		var $egw_domains;

		/**
		* Constructor just loads up some defaults from cookies
		* 
		* @param $domain_names=null domain-names used in this install
		*/
		function sessions_($domain_names=null)
		{
			$this->db = clone($GLOBALS['egw']->db);
			$this->db->set_app('phpgwapi');
			$this->sessionid = get_var('sessionid',array('GET','COOKIE'));
			$this->kp3       = get_var('kp3',array('GET','COOKIE'));

			$this->egw_domains = $domain_names;

			/* Create the crypto object */
			$GLOBALS['egw']->crypto =& CreateObject('phpgwapi.crypto');
			if ($GLOBALS['egw_info']['server']['usecookies'])
			{
				$this->egw_set_cookiedomain();
			}
			// verfiy and if necessary create and save our config settings
			//
			$save_rep = False;
			if (!isset($GLOBALS['egw_info']['server']['max_access_log_age']))
			{
				$GLOBALS['egw_info']['server']['max_access_log_age'] = 90;	// default 90 days
				$save_rep = True;
			}
			if (!isset($GLOBALS['egw_info']['server']['block_time']))
			{
				$GLOBALS['egw_info']['server']['block_time'] = 30;	// default 30min
				$save_rep = True;
			}
			if (!isset($GLOBALS['egw_info']['server']['num_unsuccessful_id']))
			{
				$GLOBALS['egw_info']['server']['num_unsuccessful_id']  = 3;	// default 3 trys per id
				$save_rep = True;
			}
			if (!isset($GLOBALS['egw_info']['server']['num_unsuccessful_ip']))
			{
				$GLOBALS['egw_info']['server']['num_unsuccessful_ip']  = $GLOBALS['egw_info']['server']['num_unsuccessful_id'];	// default same as for id
				$save_rep = True;
			}
			if (!isset($GLOBALS['egw_info']['server']['install_id']))
			{
				$GLOBALS['egw_info']['server']['install_id']  = md5($GLOBALS['egw']->common->randomstring(15));
				$save_rep = True;
			}
			if (!isset($GLOBALS['egw_info']['server']['sessions_timeout']))
			{
				$GLOBALS['egw_info']['server']['sessions_timeout'] = 14400;
				$save_rep = True;
			}
			if (!isset($GLOBALS['egw_info']['server']['sessions_app_timeout']))
			{
				$GLOBALS['egw_info']['server']['sessions_app_timeout'] = 86400;
				$save_rep = True;
			}
			if (!isset($GLOBALS['egw_info']['server']['max_history']))
			{
				$GLOBALS['egw_info']['server']['max_history'] = 20;
				$save_rep = True;
			}
			if ($save_rep)
			{
				$config = CreateObject('phpgwapi.config','phpgwapi');
				$config->read_repository();
				$config->value('max_access_log_age',$GLOBALS['egw_info']['server']['max_access_log_age']);
				$config->value('block_time',$GLOBALS['egw_info']['server']['block_time']);
				$config->value('num_unsuccessful_id',$GLOBALS['egw_info']['server']['num_unsuccessful_id']);
				$config->value('num_unsuccessful_ip',$GLOBALS['egw_info']['server']['num_unsuccessful_ip']);
				$config->value('install_id',$GLOBALS['egw_info']['server']['install_id']);
				$config->value('sessions_timeout',$GLOBALS['egw_info']['server']['sessions_timeout']);
				$config->value('sessions_app_timeout',$GLOBALS['egw_info']['server']['sessions_app_timeout']);
				$config->save_repository();
				unset($config);
			}
		}

		/**
		* commit the sessiondata to storage (needs to be reimplemented for the subclasses)
		*
		* @return bool
		*/
		function commit_session() {
			return true;
		}

		/**
		 * Splits a login-name into account_lid and eGW-domain/-instance
		 *
		 * @param string $login login-name (ie. user@default)
		 * @param string &$account_lid returned account_lid (ie. user)
		 * @param string &$domain returned domain (ie. domain)
		 */
		function split_login_domain($login,&$account_lid,&$domain)
		{
			$parts = explode('@',$login);

//			var_dump(debug_backtrace());
			//conference - for strings like vinicius@thyamad.com@default ,
			//allows that user have a login that is his e-mail. (viniciuscb)
			if (count($parts) > 1)
			{
				$probable_domain = array_pop($parts);
				//Last part of login string, when separated by @, is a domain name
				if (in_array($probable_domain,$this->egw_domains))
				{
					$got_login = true;
					$domain = $probable_domain;
					$account_lid = implode('@',$parts);
				}
			}

			if (!$got_login)
			{
				$domain = $GLOBALS['egw_info']['server']['default_domain'];
				$account_lid = $login;
			}
		}

		/**
		* Check to see if a session is still current and valid
		*
		* @param string $sessionid session id to be verfied
		* @param string $kp3 ?? to be verified
		* @return bool is the session valid?
		*/
		function verify($sessionid='',$kp3='')
		{
			$fill_egw_info_and_repositories = !$GLOBALS['egw_info']['flags']['restored_from_session'];
			if(empty($sessionid) || !$sessionid)
			{
				$sessionid = get_var('sessionid',array('GET','COOKIE'));
				$kp3       = get_var('kp3',array('GET','COOKIE'));
			}

			$this->sessionid = $sessionid;
			$this->kp3       = $kp3;

			$session = $this->read_session();
			//echo "<pre>session::verify(id='$sessionid'): \n".print_r($session,True)."</pre>\n";
			/*
			$fp = fopen('/tmp/session_verify','a+');
			fwrite($fp,"session::verify(id='$sessionid'): \n".print_r($session,True)."\n\n");
			fclose($fp);
			*/
			if ($session['session_dla'] <= (time() - $GLOBALS['egw_info']['server']['sessions_timeout']))
			{
				$this->destroy($sessionid,$kp3);
				return False;
			}

			$this->session_flags = $session['session_flags'];
			
			$this->split_login_domain($session['session_lid'],$this->account_lid,$this->account_domain);

			/* This is to ensure that we authenticate to the correct domain (might not be default) */
			if($this->account_domain != $GLOBALS['egw_info']['user']['domain'])
			{
				$GLOBALS['egw']->ADOdb = null;
				$GLOBALS['egw_info']['user']['domain'] = $this->account_domain;
				// reset the db
				$GLOBALS['egw_info']['server']['db_host'] = $GLOBALS['egw_domain'][$this->account_domain]['db_host'];
				$GLOBALS['egw_info']['server']['db_port'] = $GLOBALS['egw_domain'][$this->account_domain]['db_port'];
				$GLOBALS['egw_info']['server']['db_name'] = $GLOBALS['egw_domain'][$this->account_domain]['db_name'];
				$GLOBALS['egw_info']['server']['db_user'] = $GLOBALS['egw_domain'][$this->account_domain]['db_user'];
				$GLOBALS['egw_info']['server']['db_pass'] = $GLOBALS['egw_domain'][$this->account_domain]['db_pass'];
				$GLOBALS['egw_info']['server']['db_type'] = $GLOBALS['egw_domain'][$this->account_domain]['db_type'];
				$GLOBALS['egw']->setup('',False);
			}
			$GLOBALS['egw_info']['user']['kp3'] = $this->kp3;

			$this->update_dla();
			$this->account_id = $GLOBALS['egw']->accounts->name2id($this->account_lid,'account_lid','u');
			if (!$this->account_id)
			{
				return False;
			}

			$GLOBALS['egw_info']['user']['account_id'] = $this->account_id;

			/* init the crypto object before appsession call below */
			$this->key = md5($this->kp3 . $this->sessionid . @$GLOBALS['egw_info']['server']['encryptkey']);
			$this->iv  = $GLOBALS['egw_info']['server']['mcrypt_iv'];
			$GLOBALS['egw']->crypto->init(array($this->key,$this->iv));

			if ($fill_egw_info_and_repositories)
			{
				$this->read_repositories(@$GLOBALS['egw_info']['server']['cache_phpgw_info']);
			}

			if ($this->user['expires'] != -1 && $this->user['expires'] < time())
			{
				if(is_object($GLOBALS['egw']->log))
				{
					$GLOBALS['egw']->log->message(array(
						'text' => 'W-VerifySession, account loginid %1 is expired',
						'p1'   => $this->account_lid,
						'line' => __LINE__,
						'file' => __FILE__
					));
					$GLOBALS['egw']->log->commit();
				}
				return False;
			}
			if ($fill_egw_info_and_repositories)
			{
				$GLOBALS['egw_info']['user']  = $this->user;
				$GLOBALS['egw_info']['hooks'] = $this->hooks;

				$GLOBALS['egw_info']['user']['session_ip'] = $session['session_ip'];
				$GLOBALS['egw_info']['user']['passwd']     = base64_decode($this->appsession('password','phpgwapi'));
			}
			if ($this->account_domain != $GLOBALS['egw_info']['user']['domain'])
			{
				if(is_object($GLOBALS['egw']->log))
				{
					$GLOBALS['egw']->log->message(array(
						'text' => 'W-VerifySession, the domains %1 and %2 don\'t match',
						'p1'   => $userid_array[1],
						'p2'   => $GLOBALS['egw_info']['user']['domain'],
						'line' => __LINE__,
						'file' => __FILE__
					));
					$GLOBALS['egw']->log->commit();
				}
				return False;
			}

			if (@$GLOBALS['egw_info']['server']['sessions_checkip'])
			{
				if((PHP_OS != 'Windows') && (PHP_OS != 'WINNT') &&
					(!$GLOBALS['egw_info']['user']['session_ip'] || $GLOBALS['egw_info']['user']['session_ip'] != $this->getuser_ip())
				)
				{
					if(is_object($GLOBALS['egw']->log))
					{
						// This needs some better wording
						$GLOBALS['egw']->log->message(array(
							'text' => 'W-VerifySession, IP %1 doesn\'t match IP %2 in session table',
							'p1'   => $this->getuser_ip(),
							'p2'   => $GLOBALS['egw_info']['user']['session_ip'],
							'line' => __LINE__,
							'file' => __FILE__
						));
						$GLOBALS['egw']->log->commit();
					}
					return False;
				}
			}

			if ($fill_egw_info_and_repositories)
			{
				$GLOBALS['egw']->acl->acl($this->account_id);
				$GLOBALS['egw']->accounts->accounts($this->account_id);
				$GLOBALS['egw']->preferences->preferences($this->account_id);
				$GLOBALS['egw']->applications->applications($this->account_id);
			}
			if (! $this->account_lid)
			{
				if(is_object($GLOBALS['egw']->log))
				{
					// This needs some better wording
					$GLOBALS['egw']->log->message(array(
						'text' => 'W-VerifySession, account_id is empty',
						'line' => __LINE__,
						'file' => __FILE__
					));
					$GLOBALS['egw']->log->commit();
				}
				//echo 'DEBUG: Sessions: account_id is empty!<br>'."\n";
				return False;
			}
			/* If User is Anonymous and enters a not allowed application its session will be destroyed inmediatly. */
			$_current_app=$GLOBALS['egw_info']['flags']['currentapp'];
			if($this->session_flags=='A' && !$GLOBALS['egw_info']['user']['apps'][$_current_app])
			{
			   $this->destroy($sessionid,$kp3);

			   /* Overwrite Cookie with empty user. For 2 weeks */
			   $this->egw_setcookie('sessionid','');
			   $this->egw_setcookie('kp3','');
			   $this->egw_setcookie('domain','');
			   $this->egw_setcookie('last_domain','');
			   $this->egw_setcookie('last_loginid', ''); 

			   return False;
			}

			return True;
		}

		/**
		* Functions for creating and verifying the session
		*/
        
		/**
		* Get the ip address of current users
		*
		* @return string ip address
		*/
		function getuser_ip()
		{
			return (isset($_SERVER['HTTP_X_FORWARDED_FOR']) ? $_SERVER['HTTP_X_FORWARDED_FOR'] : $_SERVER['REMOTE_ADDR']);
		}

		/**
		* Set the domain used for cookies
		*
		* @return string domain
		*/
		function egw_set_cookiedomain()
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
			print_debug('COOKIE_DOMAIN',$this->cookie_domain,'api');

			$url_parts = parse_url($GLOBALS['egw_info']['server']['webserver_url']);
			if (!($this->cookie_path = $url_parts['path'])) $this->cookie_path = '/';

			$this->set_cookie_params($this->cookie_domain,$this->cookie_path);	// for php4 sessions necessary
		}

		/**
		* Set a cookie
		*
		* @param string $cookiename name of cookie to be set
		* @param string $cookievalue='' value to be used, if unset cookie is cleared (optional)
		* @param int $cookietime=0 when cookie should expire, 0 for session only (optional)
		* @param string $cookiepath=null optional path (eg. '/') if the eGW install-dir should not be used
		*/
		function egw_setcookie($cookiename,$cookievalue='',$cookietime=0,$cookiepath=null)
		{
			if (!$this->cookie_domain || !$this->cookie_path)
			{
				$this->egw_set_cookiedomain();
			}
			if (is_null($cookiepath)) $cookiepath = $this->cookie_path;

			setcookie($cookiename,$cookievalue,$cookietime,$cookiepath,$this->cookie_domain);
		}

		/**
		* @deprecated use egw_setcookie
		*/
		function phpgw_setcookie($cookiename,$cookievalue='',$cookietime=0)
		{
			$this->egw_setcookie($cookiename,$cookievalue,$cookietime);
		}

		/**
		* Create a new session
		*
		* @param string $login user login
		* @param string $passwd user password
		* @param string $passwd_type type of password being used, ie plaintext, md5, sha1
		* @return string session id
		*/
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
			$this->split_login_domain($login,$this->account_lid,$this->account_domain);
			// add domain to the login, if not already there
			if (substr($this->login,-strlen($this->account_domain)-1) != '@'.$this->account_domain)
			{
				$this->login .= '@'.$this->account_domain;
			}
			$now = time();

			/* This is to ensure that we authenticate to the correct domain (might not be default) */
			if($this->account_domain != $GLOBALS['egw_info']['user']['domain'])
			{
				$GLOBALS['egw']->ADOdb = null;
				$GLOBALS['egw_info']['user']['domain'] = $this->account_domain;
				// reset the db
				$GLOBALS['egw_info']['server']['db_host'] = $GLOBALS['egw_domain'][$this->account_domain]['db_host'];
				$GLOBALS['egw_info']['server']['db_port'] = $GLOBALS['egw_domain'][$this->account_domain]['db_port'];
				$GLOBALS['egw_info']['server']['db_name'] = $GLOBALS['egw_domain'][$this->account_domain]['db_name'];
				$GLOBALS['egw_info']['server']['db_user'] = $GLOBALS['egw_domain'][$this->account_domain]['db_user'];
				$GLOBALS['egw_info']['server']['db_pass'] = $GLOBALS['egw_domain'][$this->account_domain]['db_pass'];
				$GLOBALS['egw_info']['server']['db_type'] = $GLOBALS['egw_domain'][$this->account_domain]['db_type'];
				$GLOBALS['egw']->setup('',False);
			}

			//echo "<p>session::create(login='$login'): lid='$this->account_lid', domain='$this->account_domain'</p>\n";
			$user_ip = $this->getuser_ip();

			$this->account_id = $GLOBALS['egw']->accounts->name2id($this->account_lid,'account_lid','u');

			if (($blocked = $this->login_blocked($login,$user_ip)) ||	// too many unsuccessful attempts
				$GLOBALS['egw_info']['server']['global_denied_users'][$this->account_lid] ||
				!$GLOBALS['egw']->auth->authenticate($this->account_lid, $this->passwd, $this->passwd_type) ||
				$this->account_id && $GLOBALS['egw']->accounts->get_type($this->account_id) == 'g')
			{
				$this->reason = $blocked ? 'blocked, too many attempts' : 'bad login or password';
				$this->cd_reason = $blocked ? 99 : 5;

				$this->log_access($this->reason,$login,$user_ip,0);	// log unsuccessfull login
				return False;
			}

			if (!$this->account_id && $GLOBALS['egw_info']['server']['auto_create_acct'])
			{
				if ($GLOBALS['egw_info']['server']['auto_create_acct'] == 'lowercase')
				{
					$this->account_lid = strtolower($this->account_lid);
				}
				$this->account_id = $GLOBALS['egw']->accounts->auto_add($this->account_lid, $passwd);
			}

			$GLOBALS['egw_info']['user']['account_id'] = $this->account_id;
			$GLOBALS['egw']->accounts->accounts($this->account_id);
			$this->sessionid = $this->new_session_id();
			$this->kp3       = md5($GLOBALS['egw']->common->randomstring(15));

			if ($GLOBALS['egw_info']['server']['usecookies'])
			{
				$this->egw_setcookie('sessionid',$this->sessionid);
				$this->egw_setcookie('kp3',$this->kp3);
				$this->egw_setcookie('domain',$this->account_domain);
			}
			if ($GLOBALS['egw_info']['server']['usecookies'] || isset($_COOKIE['last_loginid']))
			{ 
				$this->egw_setcookie('last_loginid', $this->account_lid ,$now+1209600); /* For 2 weeks */
				$this->egw_setcookie('last_domain',$this->account_domain,$now+1209600);
			}
			unset($GLOBALS['egw_info']['server']['default_domain']); /* we kill this for security reasons */

			/* init the crypto object */
			$this->key = md5($this->kp3 . $this->sessionid . $GLOBALS['egw_info']['server']['encryptkey']);
			$this->iv  = $GLOBALS['egw_info']['server']['mcrypt_iv'];
			$GLOBALS['egw']->crypto->init(array($this->key,$this->iv));

			$this->read_repositories(False);
			if ($GLOBALS['egw']->accounts->is_expired($this->user))
			{
				if(is_object($GLOBALS['egw']->log))
				{
					$GLOBALS['egw']->log->message(array(
						'text' => 'W-LoginFailure, account loginid %1 is expired',
						'p1'   => $this->account_lid,
						'line' => __LINE__,
						'file' => __FILE__
					));
					$GLOBALS['egw']->log->commit();
				}
				$this->reason = 'account is expired';
				$this->cd_reason = 98;

				return False;
			}

			$GLOBALS['egw_info']['user']  = $this->user;
			$GLOBALS['egw_info']['hooks'] = $this->hooks;

			$this->appsession('password','phpgwapi',base64_encode($this->passwd));
			if ($GLOBALS['egw']->acl->check('anonymous',1,'phpgwapi'))
			{
				$session_flags = 'A';
			}
			else
			{
				$session_flags = 'N';
			}

			$GLOBALS['egw']->db->transaction_begin();
			$this->register_session($this->login,$user_ip,$now,$session_flags);
			if ($session_flags != 'A')		// dont log anonymous sessions
			{
				$this->log_access($this->sessionid,$login,$user_ip,$this->account_id);
			}
			$this->appsession('account_previous_login','phpgwapi',$GLOBALS['egw']->auth->previous_login);
			$GLOBALS['egw']->accounts->update_lastlogin($this->account_id,$user_ip);
			$GLOBALS['egw']->db->transaction_commit();

			//if (!$this->sessionid) echo "<p>session::create(login='$login') = '$this->sessionid': lid='$this->account_lid', domain='$this->account_domain'</p>\n";

			return $this->sessionid;
		}

		/**
        * Write or update (for logout) the access_log
		*
		* @param string $sessionid id of session or 0 for unsuccessful logins
		* @param string $login account_lid (evtl. with domain) or '' for settion the logout-time
		* @param string $user_ip ip to log
		* @param int $account_id numerical account_id
		*/
		function log_access($sessionid,$login='',$user_ip='',$account_id='')
		{
			$now = time();

			if ($login)
			{
				if (strlen($login) > 30)
				{
					$login = substr($login,0,30);
				}
				$GLOBALS['egw']->db->insert($this->access_table,array(
					'sessionid' => $sessionid,
					'loginid'   => $login,
					'ip'        => $user_ip,
					'li'        => $now,
					'lo'        => 0,
					'account_id'=> $account_id,
				),false,__LINE__,__FILE__);
			}
			else
			{
				$GLOBALS['egw']->db->update($this->access_table,array('lo' => $now),array('sessionid' => $sessionid),__LINE__,__FILE__);
			}
			if ($GLOBALS['egw_info']['server']['max_access_log_age'])
			{
				$max_age = $now - $GLOBALS['egw_info']['server']['max_access_log_age'] * 24 * 60 * 60;

				$GLOBALS['egw']->db->delete($this->access_table,"li < $max_age",__LINE__,__FILE__);
			}
		}

		/**
		* Protect against brute force attacks, block login if too many unsuccessful login attmepts
        *
		* @param string $login account_lid (evtl. with domain)
		* @param string $ip ip of the user
		* @returns bool login blocked?
		*/
		function login_blocked($login,$ip)
		{
			$blocked = False;
			$block_time = time() - $GLOBALS['egw_info']['server']['block_time'] * 60;

			$this->db->select($this->access_table,'COUNT(*)',array(
				'account_id = 0',
				'ip'         => $ip,
				"li > $block_time",
			),__LINE__,__FILE__);
			$this->db->next_record();
			if (($false_ip = $this->db->f(0)) > $GLOBALS['egw_info']['server']['num_unsuccessful_ip'])
			{
				//echo "<p>login_blocked: ip='$ip' ".$this->db->f(0)." trys (".$GLOBALS['egw_info']['server']['num_unsuccessful_ip']." max.) since ".date('Y/m/d H:i',$block_time)."</p>\n";
				$blocked = True;
			}
			$this->db->select($this->access_table,'COUNT(*)',array(
				'account_id = 0',
				'(loginid = '.$this->db->quote($login).' OR loginid LIKE '.$this->db->quote($login.'@%').')',
				"li > $block_time",
			),__LINE__,__FILE__);
			$this->db->next_record();
			if (($false_id = $this->db->f(0)) > $GLOBALS['egw_info']['server']['num_unsuccessful_id'])
			{
				//echo "<p>login_blocked: login='$login' ".$this->db->f(0)." trys (".$GLOBALS['egw_info']['server']['num_unsuccessful_id']." max.) since ".date('Y/m/d H:i',$block_time)."</p>\n";
				$blocked = True;
			}
			if ($blocked && $GLOBALS['egw_info']['server']['admin_mails'] &&
				// max. one mail each 5mins
				$GLOBALS['egw_info']['server']['login_blocked_mail_time'] < time()-5*60)
			{
				// notify admin(s) via email
				$from    = 'eGroupWare@'.$GLOBALS['egw_info']['server']['mail_suffix'];
				$subject = lang("eGroupWare: login blocked for user '%1', IP %2",$login,$ip);
				$body    = lang("Too many unsucessful attempts to login: %1 for the user '%2', %3 for the IP %4",$false_id,$login,$false_ip,$ip);

				if(!is_object($GLOBALS['egw']->send))
				{
					$GLOBALS['egw']->send = CreateObject('phpgwapi.send');
				}
				$subject = $GLOBALS['egw']->send->encode_subject($subject);
				$admin_mails = explode(',',$GLOBALS['egw_info']['server']['admin_mails']);
				foreach($admin_mails as $to)
				{
					$GLOBALS['egw']->send->msg('email',$to,$subject,$body,'','','',$from,$from);
				}
				// save time of mail, to not send to many mails
				$config = CreateObject('phpgwapi.config','phpgwapi');
				$config->read_repository();
				$config->value('login_blocked_mail_time',time());
				$config->save_repository();
			}
			return $blocked;
		}

		/**
		* Verfy a peer server access request
		*
		* @param string $sessionid session id to verfiy
		* @param string $kp3 ??
		* @return bool verfied?
		*/
		function verify_server($sessionid, $kp3)
		{
			$GLOBALS['egw']->interserver = CreateObject('phpgwapi.interserver');
			$this->sessionid = $sessionid;
			$this->kp3       = $kp3;

			$session = $this->read_session();
			$this->session_flags = $session['session_flags'];

			list($this->account_lid,$this->account_domain) = explode('@', $session['session_lid']);

			if ($this->account_domain == '')
			{
				$this->account_domain = $GLOBALS['egw_info']['server']['default_domain'];
			}

			$GLOBALS['egw_info']['user']['kp3'] = $this->kp3;
			$phpgw_info_flags = $GLOBALS['egw_info']['flags'];

			$GLOBALS['egw_info']['flags'] = $phpgw_info_flags;

			$this->update_dla();
			$this->account_id = $GLOBALS['egw']->interserver->name2id($this->account_lid,'account_lid','u');

			if (!$this->account_id)
			{
				return False;
			}

			$GLOBALS['egw_info']['user']['account_id'] = $this->account_id;

			$this->read_repositories(@$GLOBALS['egw_info']['server']['cache_phpgw_info']);

			/* init the crypto object before appsession call below */
			$this->key = md5($this->kp3 . $this->sessionid . $GLOBALS['egw_info']['server']['encryptkey']);
			$this->iv  = $GLOBALS['egw_info']['server']['mcrypt_iv'];
			$GLOBALS['egw']->crypto->init(array($this->key,$this->iv));

			$GLOBALS['egw_info']['user']  = $this->user;
			$GLOBALS['egw_info']['hooks'] = $this->hooks;

			$GLOBALS['egw_info']['user']['session_ip'] = $session['session_ip'];
			$GLOBALS['egw_info']['user']['passwd'] = base64_decode($this->appsession('password','phpgwapi'));

			if ($userid_array[1] != $GLOBALS['egw_info']['user']['domain'])
			{
				if(is_object($GLOBALS['egw']->log))
				{
					$GLOBALS['egw']->log->message(array(
						'text' => 'W-VerifySession, the domains %1 and %2 don\t match',
						'p1'   => $userid_array[1],
						'p2'   => $GLOBALS['egw_info']['user']['domain'],
						'line' => __LINE__,
						'file' => __FILE__
					));
					$GLOBALS['egw']->log->commit();
				}

				if(is_object($GLOBALS['egw']->crypto))
				{
					$GLOBALS['egw']->crypto->cleanup();
					unset($GLOBALS['egw']->crypto);
				}
				return False;
			}

			if(@$GLOBALS['egw_info']['server']['sessions_checkip'])
			{
				if((PHP_OS != 'Windows') && (PHP_OS != 'WINNT') &&
					(!$GLOBALS['egw_info']['user']['session_ip'] || $GLOBALS['egw_info']['user']['session_ip'] != $this->getuser_ip())
				)
				{
					if(is_object($GLOBALS['egw']->log))
					{
						// This needs some better wording
						$GLOBALS['egw']->log->message(array(
							'text' => 'W-VerifySession, IP %1 doesn\'t match IP %2 in session table',
							'p1'   => $this->getuser_ip(),
							'p2'   => $GLOBALS['egw_info']['user']['session_ip'],
							'line' => __LINE__,
							'file' => __FILE__
						));
						$GLOBALS['egw']->log->commit();
					}

					if(is_object($GLOBALS['egw']->crypto))
					{
						$GLOBALS['egw']->crypto->cleanup();
						unset($GLOBALS['egw']->crypto);
					}
					return False;
				}
			}

			$GLOBALS['egw']->acl->acl($this->account_id);
			$GLOBALS['egw']->accounts->accounts($this->account_id);
			$GLOBALS['egw']->preferences->preferences($this->account_id);
			$GLOBALS['egw']->applications->applications($this->account_id);

			if (! $this->account_lid)
			{
				if(is_object($GLOBALS['egw']->log))
				{
					// This needs some better wording
					$GLOBALS['egw']->log->message(array(
						'text' => 'W-VerifySession, account_id is empty',
						'line' => __LINE__,
						'file' => __FILE__
					));
					$GLOBALS['egw']->log->commit();
				}

				if(is_object($GLOBALS['egw']->crypto))
				{
					$GLOBALS['egw']->crypto->cleanup();
					unset($GLOBALS['egw']->crypto);
				}
				return False;
			}
			else
			{
				return True;
			}
		}

		/**
		* Validate a peer server login request
		*
		* @param string $login login name
		* @param string $password password
		* @return bool login ok?
		*/
		function create_server($login,$passwd)
		{
			$GLOBALS['egw']->interserver = CreateObject('phpgwapi.interserver');
//			$this->login  = $login;
			$this->passwd = $passwd;
			$this->clean_sessions();
			$login_array = explode('@', $login);
//			$this->account_lid = $login_array[0];
			$now = time();

			$this->split_login_domain($login,$this->account_lid,$this->account_domain);
			$this->login = $this->account_lid . '@' . $this->account_domain;

			$serverdata = array(
				'server_name' => $this->account_domain,
				'username'    => $this->account_lid,
				'password'    => $passwd
			);
			if (!$GLOBALS['egw']->interserver->auth($serverdata))
			{
				return False;
				exit;
			}

			if (!$GLOBALS['egw']->interserver->exists($this->account_lid))
			{
				$this->account_id = $GLOBALS['egw']->interserver->name2id($this->account_lid,'account_lid','u');
			}
			$GLOBALS['egw_info']['user']['account_id'] = $this->account_id;
			$GLOBALS['egw']->interserver->serverid = $this->account_id;

			$this->sessionid = md5($GLOBALS['egw']->common->randomstring(10));
			$this->kp3       = md5($GLOBALS['egw']->common->randomstring(15));

			/* re-init the crypto object */
			$this->key = md5($this->kp3 . $this->sessionid . $GLOBALS['egw_info']['server']['encryptkey']);
			$this->iv  = $GLOBALS['egw_info']['server']['mcrypt_iv'];
			$GLOBALS['egw']->crypto->init(array($this->key,$this->iv));

			//$this->read_repositories(False);

			$GLOBALS['egw_info']['user']  = $this->user;
			$GLOBALS['egw_info']['hooks'] = $this->hooks;

			$this->appsession('password','phpgwapi',base64_encode($this->passwd));
			$session_flags = 'S';

			$user_ip = $this->getuser_ip();

			$GLOBALS['egw']->db->transaction_begin();
			$this->register_session($login,$user_ip,$now,$session_flags);

			$this->log_access($this->sessionid,$login,$user_ip,$this->account_id);

			$this->appsession('account_previous_login','phpgwapi',$GLOBALS['egw']->auth->previous_login);
			$GLOBALS['egw']->accounts->update_lastlogin($this->account_id,$user_ip);
			$GLOBALS['egw']->db->transaction_commit();

			return array($this->sessionid,$this->kp3);
		}

		/**
		* Functions for appsession data and session cache
		*/

		/**
		* Is this also useless?? (skwashd)
		*/
		function read_repositories($cached='',$write_cache=True)
		{
			$GLOBALS['egw']->acl->acl($this->account_id);
			$GLOBALS['egw']->accounts->accounts($this->account_id);
			$GLOBALS['egw']->preferences->preferences($this->account_id);
			$GLOBALS['egw']->applications->applications($this->account_id);

			if(@$cached)
			{
				$this->user = $this->appsession('phpgw_info_cache','phpgwapi');
				if(!empty($this->user))
				{
					$GLOBALS['egw']->preferences->data = $this->user['preferences'];
					if (!isset($GLOBALS['egw_info']['apps']) || !is_array($GLOBALS['egw_info']['apps']))
					{
						$GLOBALS['egw']->applications->read_installed_apps();
					}
				}
				else
				{
					$this->setup_cache($write_cache);
				}
			}
			else
			{
				$this->setup_cache($write_cache);
			}
			$this->hooks = $GLOBALS['egw']->hooks->read();
		}

		/**
		* Is this also useless?? (skwashd)
		*/
		function setup_cache($write_cache=True)
		{
			$this->user                = $GLOBALS['egw']->accounts->read_repository();
			$this->user['acl']         = $GLOBALS['egw']->acl->read_repository();
			$this->user['preferences'] = $GLOBALS['egw']->preferences->read_repository();
			if (is_object($GLOBALS['egw']->datetime))
			{
				$GLOBALS['egw']->datetime->datetime();		// to set tz_offset from the now read prefs
			}
			$this->user['apps']        = $GLOBALS['egw']->applications->read_repository();
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
			if(@$GLOBALS['egw_info']['server']['cache_phpgw_info'] && $write_cache)
			{
				$this->delete_cache();
				$this->appsession('phpgw_info_cache','phpgwapi',$this->user);
			}
		}
        
		/**
		* This looks to be useless
		* This will capture everything in the $GLOBALS['egw_info'] including server info,
		* and store it in appsessions.  This is really incompatible with any type of restoring
		* from appsession as the saved user info is really in ['user'] rather than the root of
		* the structure, which is what this class likes.
		*/
		function save_repositories()
		{
			$phpgw_info_temp = $GLOBALS['egw_info'];
			$phpgw_info_temp['user']['kp3'] = '';
			$phpgw_info_temp['flags'] = array();

			if ($GLOBALS['egw_info']['server']['cache_phpgw_info'])
			{
				$this->appsession('phpgw_info_cache','phpgwapi',$phpgw_info_temp);
			}
		}

		function restore()
		{
			$sessionData = $this->appsession('sessiondata');

			if (!empty($sessionData) && is_array($sessionData))
			{
				foreach($sessionData as $key => $value)
				{
					global $$key;
					$$key = $value;
					$this->variableNames[$key] = 'registered';
					// echo 'restored: '.$key.', ' . $value . '<br>';
				}
			}
		}

		/**
		* Save the current values of all registered variables
		*/
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

		/**
		* Create a list a variable names, which data needs to be restored
		*
		* @param string $_variableName name of variable to be registered
		*/
		function register($_variableName)
		{
			$this->variableNames[$_variableName]='registered';
			#print 'registered '.$_variableName.'<br>';
		}

		/**
		* Mark variable as unregistered
		*
		* @param string $_variableName name of variable to deregister
		*/
		function unregister($_variableName)
		{
			$this->variableNames[$_variableName]='unregistered';
			#print 'unregistered '.$_variableName.'<br>';
		}

		/**
		* Check if we have a variable registred already
		*
		* @param string $_variableName name of variable to check
		* @return bool was the variable found?
		*/
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
		/**
		* Additional tracking of user actions - prevents reposts/use of back button
		*
		* @deprecated not used in eGroupWare
		* @author skwashd
		* @return string current history id
		*/
		function generate_click_history()
		{
			if(!isset($this->history_id))
			{
				$this->history_id = md5($this->login . time());
				$history = $this->appsession($location = 'history', $appname = 'phpgwapi');

				if(count($history) >= $GLOBALS['egw_info']['server']['max_history'])
				{
					array_shift($history);
					$this->appsession($location = 'history', $appname = 'phpgwapi', $history);
				}
			}
			return $this->history_id;
		}

		/**
		* Detects if the page has already been called before - good for forms
		*
		* @deprecated not used in eGroupWare
		* @author skwashd
		* @param bool $diplay_error when implemented will use the generic error handling code
		* @return True if called previously, else False - call ok
		*/
		function is_repost($display_error = False)
		{
			$history = $this->appsession($location = 'history', $appname = 'phpgwapi');
			if(isset($history[$_GET['click_history']]))
			{
				if($display_error)
				{
					$GLOBALS['egw']->redirect_link('/error.php', 'type=repost');//more on this later :)
				}
				else
				{
					return True; //handled by the app
				}
			}
			else
			{
				$history[$_GET['click_history']] = True;
				$this->appsession($location = 'history', $appname = 'phpgwapi', $history);
				return False;
			}
		}

		/**
		* Generate a url which supports url or cookies based sessions
		*
		* Please note, the values of the query get url encoded! 
		*
		* @param string $url a url relative to the egroupware install root, it can contain a query too
		* @param array/string $extravars query string arguements as string or array (prefered)
		* @return string generated url
		*/
		function link($url, $extravars = '')
		{
			//echo "<p>session::link(url='$url',extravars='".print_r($extravars,True)."')";
			
			if ($url{0} != '/')
			{
				$app = $GLOBALS['egw_info']['flags']['currentapp'];
				if ($app != 'login' && $app != 'logout')
				{
					$url = $app.'/'.$url;
				}
			}

			// append the url to the webserver url, but avoid more then one slash between the parts of the url
			if ($url{0} != '/' || $GLOBALS['egw_info']['server']['webserver_url'] != '/')
			{
				if($url{0} != '/' && substr($GLOBALS['egw_info']['server']['webserver_url'],-1) != '/')
				{
					$url = $GLOBALS['egw_info']['server']['webserver_url'] .'/'. $url;
				}
				else
				{
					$url = $GLOBALS['egw_info']['server']['webserver_url'] . $url;
				}
			}

			if(@isset($GLOBALS['egw_info']['server']['enforce_ssl']) && $GLOBALS['egw_info']['server']['enforce_ssl']) // && !$_SERVER['HTTPS']) imho https should always be a full path - skwashd
			{
				if(substr($url ,0,4) != 'http')
				{
					$url = 'https://'.$GLOBALS['egw_info']['server']['hostname'].$url;
				}
				else
				{
					$url = str_replace ( 'http:', 'https:', $url);
				}
			}
			
			// check if the url already contains a query and ensure that vars is an array and all strings are in extravars
			list($url,$othervars) = explode('?',$url);
			if ($extravars && is_array($extravars))
			{
				$vars = $extravars;
				$extravars = $othervars;
			}
			else
			{
				$vars = array();
				if ($othervars) $extravars .= '&'.$othervars;
			} 

			// parse extravars string into the vars array
			if ($extravars)
			{
				foreach(explode('&',$extravars) as $expr)
				{
					list($var,$val) = explode('=', $expr,2);
					if (substr($var,-2) == '[]')
					{
						$vars[substr($var,0,-2)][] = $val;
					}
					else
					{
						$vars[$var] = $val;
					}
				}
			}

			// add session params if not using cookies
			if (!$GLOBALS['egw_info']['server']['usecookies'])
			{
				$vars['sessionid'] = $this->sessionid;
				$vars['kp3'] = $this->kp3;
				$vars['domain'] = $this->account_domain;
			}

			// if there are vars, we add them urlencoded to the url
			if (count($vars))
			{
				$query = array();
				foreach($vars as $key => $value)
				{
					if (is_array($value))
					{
						foreach($value as $val)
						{
							$query[] = $key.'[]='.urlencode($val);
						}
					}
					else
					{
						$query[] = $key.'='.urlencode($value);
					}
				}
				$url .= '?' . implode('&',$query);
			}
			//echo " = '$url'</p>\n";
			return $url;
		}

		/**
		* The remaining methods are abstract - as they are unique for each session handler
		*/

		/**
		* Load user's session information
		*
		* The sessionid of the session to read is passed in the class-var $this->sessionid
		*
		* @return mixed the session data
		*/
		function read_session()
		{}

		/**
		* Remove stale sessions out of the database
		*/
		function clean_sessions()
		{}

		/**
		* Set paramaters for cookies - only implemented in PHP4 sessions
		*
		* @param string $domain domain name to use in cookie
		* @param string $path='/' path to use in cookie
		*/
		function set_cookie_params($domain,$path='/')
		{}

		/**
		* Create a new session id, called by session::create()
		*
		* @return string a new session id
		*/
		function new_session_id()
		{
			# when synchronizing using syncml, we already have php4 based session started
			# and we are currently not allowed to change the sessionid later
			# to solve this problem, we simply return the current session_id
			if(basename($_SERVER["REQUEST_URI"]) == 'rpc.php' && session_id() != '') {
				return session_id();
			}
			
			return md5($GLOBALS['egw']->common->randomstring(15));
		}

		/**
		* Create a new session
		*
		* @param string $login user login
		* @param string $user_ip users ip address
		* @param int $now time now as a unix timestamp
		* @param string $session_flags A = Anonymous, N = Normal
		*/
		function register_session($login,$user_ip,$now,$session_flags)
		{}

		/**
		* Update the date last active info for the session, so the login does not expire
		*
		* @return bool did it suceed?
		*/
		function update_dla()
		{}

		/**
		* Terminate a session
		*
		* @param string $sessionid the id of the session to be terminated
		* @param string $kp3 - NOT SURE
		* @return bool did it suceed?
		*/
		function destroy($sessionid, $kp3)
		{}

		/**
		* Functions for appsession data and session cache
		*/
        
		/**
		* Delete all data from the session cache for a user
		*
		* @param int $accountid user account id, defaults to current user (optional)
		*/
		function delete_cache($accountid='')
		{}

		/**
		* Stores or retrieves information from the sessions cache
		*
		* @param string $location identifier for data
		* @param string $appname name of app which is responsbile for the data
		* @param mixed $data data to be stored, if left blank data is retreived (optional)
		* @return mixed data from cache, only returned if $data arg is not used
		*/
		function appsession($location = 'default', $appname = '', $data = '##NOTHING##')
		{}

		/**
		* Get list of normal / non-anonymous sessions
		* Note: The data from the session-files get cached in the app_session phpgwapi/php4_session_cache
		*
		* @author ralfbecker
		* @param int $start session to start at
		* @param string $order field to sort on
		* @param string $sort sort order
		* @param bool $all_no_sort list all with out sorting (optional) default False
		* @return array info for all current sessions
		*/
		function list_sessions($start,$order,$sort,$all_no_sort = False)
		{}

		/**
		* Get the number of normal / non-anonymous sessions
		* 
		* @author ralfbecker
		* @return int number of sessions
		*/
		function total()
		{}
	}

	if(empty($GLOBALS['egw_info']['server']['sessions_type']))
	{
		$GLOBALS['egw_info']['server']['sessions_type'] = 'php4';	// the more performant default
	}
	// for php4 sessions, check if the extension is loaded, try loading it and fallback to db sessions if not
	if (substr($GLOBALS['egw_info']['server']['sessions_type'],0,4) == 'php4' && !extension_loaded('session'))
	{
		// some constanst for pre php4.3
		if (!defined('PHP_SHLIB_SUFFIX'))
		{
			define('PHP_SHLIB_SUFFIX',strtoupper(substr(PHP_OS, 0,3)) == 'WIN' ? 'dll' : 'so');
		}
		if (!defined('PHP_SHLIB_PREFIX'))
		{
			define('PHP_SHLIB_PREFIX',PHP_SHLIB_SUFFIX == 'dll' ? 'php_' : '');
		}
		if (!function_exists('dl') || !@dl(PHP_SHLIB_PREFIX.'session'.'.'.PHP_SHLIB_SUFFIX))
		{
			$GLOBALS['egw_info']['server']['sessions_type'] = 'db';	// fallback if we have no php sessions support
		}
	}
	include_once(EGW_API_INC.'/class.sessions_'.substr($GLOBALS['egw_info']['server']['sessions_type'],0,4).'.inc.php');
