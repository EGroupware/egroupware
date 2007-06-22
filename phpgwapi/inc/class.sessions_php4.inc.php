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

	define('EGW_SESSION_VAR','egw_session');	// where to store our session-data $_SESSION[EGW_SESSION_VAR]

	/**
	* Session Management via php sessions
	*
	* @package api
	* @subpackage sessions
	*/
	class sessions extends sessions_
	{

		function sessions($domain_names=null)
		{
			$this->sessions_($domain_names);
			//controls the time out for php4 sessions - skwashd 18-May-2003
			ini_set('session.gc_maxlifetime', $GLOBALS['egw_info']['server']['sessions_timeout']);
			session_name('sessionid');
		}

		/**
		* commit the sessiondata to the filesystem
		*
		* @return bool
		*/
		function commit_session() {
			session_write_close();
			return true;
		}

		function read_session()
		{
			if (!$this->sessionid)
			{
				return False;
			}
			session_id($this->sessionid);
			session_start();
			return $_SESSION[EGW_SESSION_VAR];
		}

		/**
		* Set paramaters for cookies - only implemented in PHP4 sessions
		*
		* @param string $domain domain name to use in cookie
		* @param string $path='/' path to use in cookie
		*/
		function set_cookie_params($domain,$path='/')
		{
			session_set_cookie_params(0,$path,$domain);
		}

		/**
		* Create a new session id, called by session::create()
		* 
		* Reimplemented to use php session-id
		*
		* @return string a new session id
		*/
		function new_session_id()
		{
			session_start();

			return session_id();
		}

		function register_session($login,$user_ip,$now,$session_flags)
		{
			// session_start() is now called in new_session_id() !!!
			$_SESSION[EGW_SESSION_VAR]['session_id'] = $this->sessionid;
			$_SESSION[EGW_SESSION_VAR]['session_lid'] = $login;
			$_SESSION[EGW_SESSION_VAR]['session_ip'] = $user_ip;
			$_SESSION[EGW_SESSION_VAR]['session_logintime'] = $now;
			$_SESSION[EGW_SESSION_VAR]['session_dla'] = $now;
			$_SESSION[EGW_SESSION_VAR]['session_action'] = $_SERVER['PHP_SELF'];
			$_SESSION[EGW_SESSION_VAR]['session_flags'] = $session_flags;
			// we need the install-id to differ between serveral installs shareing one tmp-dir
			$_SESSION[EGW_SESSION_VAR]['session_install_id'] = $GLOBALS['egw_info']['server']['install_id'];
		}

		// This will update the DateLastActive column, so the login does not expire
		function update_dla()
		{
			if (@isset($_GET['menuaction']))
			{
				$action = $_GET['menuaction'];
			}
			else
			{
				$action = $_SERVER['PHP_SELF'];
			}

			// This way XML-RPC users aren't always listed as
			// xmlrpc.php
			if ($this->xmlrpc_method_called)
			{
				$action = $this->xmlrpc_method_called;
			}

			$_SESSION[EGW_SESSION_VAR]['session_dla'] = time();
			$_SESSION[EGW_SESSION_VAR]['session_action'] = $action;

			return True;
		}

		function destroy($sessionid, $kp3)
		{
			if (!$sessionid && $kp3)
			{
				return False;
			}

			$this->log_access($this->sessionid);	// log logout-time

			// Only do the following, if where working with the current user
			if ($sessionid == $GLOBALS['egw_info']['user']['sessionid'])
			{
				session_unset();
				//echo "<p>sessions_php4::destroy: session_destroy() returned ".(session_destroy() ? 'True' : 'False')."</p>\n";
				@session_destroy();
				if ($GLOBALS['egw_info']['server']['usecookies'])
				{
					$this->egw_setcookie(session_name());
				}
			}
			else
			{
				$sessions = $this->list_sessions(0,'','',True);
				
				if (isset($sessions[$sessionid]))
				{
					//echo "<p>session_php4::destroy($session_id): unlink('".$sessions[$sessionid]['php_session_file']."')</p>\n";
					@unlink($sessions[$sessionid]['php_session_file']);
				}
			}

			return True;
		}

		/*************************************************************************\
		* Functions for appsession data and session cache                         *
		\*************************************************************************/
		function delete_cache($accountid='')
		{
			$account_id = get_account_id($accountid,$this->account_id);

			$_SESSION[EGW_SESSION_VAR]['app_sessions']['phpgwapi']['phpgw_info_cache'] = '';
		}

		function appsession($location = 'default', $appname = '', $data = '##NOTHING##')
		{
			if (! $appname)
			{
				$appname = $GLOBALS['egw_info']['flags']['currentapp'];
			}

			/* This allows the user to put '' as the value. */
			if ($data === '##NOTHING##')
			{
				/* do not decrypt and return if no data (decrypt returning garbage) */
				if($_SESSION[EGW_SESSION_VAR]['app_sessions'][$appname][$location])
				{
					return $GLOBALS['egw']->crypto->decrypt($_SESSION[EGW_SESSION_VAR]['app_sessions'][$appname][$location]);
				}
				return false;
			}
			$_SESSION[EGW_SESSION_VAR]['app_sessions'][$appname][$location] = $GLOBALS['egw']->crypto->encrypt($data);

			return $data;
		}

		function session_sort($a,$b)
		{
			$sign = strcasecmp($GLOBALS['egw']->session->sort_order,'ASC') ? 1 : -1;

			return strcasecmp(
				$a[$GLOBALS['egw']->session->sort_by],
				$b[$GLOBALS['egw']->session->sort_by]
			) * $sign;
		}

		/**
		 * get list of normal / non-anonymous sessions (works only for session.handler = files!, but that's the default)
		 *
		 * The data from the session-files get cached in the app_session phpgwapi/php4_session_cache
		 *
		 * @author RalfBecker-AT-outdoor-training.de
		 */
		function list_sessions($start,$order,$sort,$all_no_sort = False)
		{
			//echo "<p>session_php4::list_sessions($start,'$order','$sort',$all)</p>\n";
			$session_cache = $this->appsession('php4_session_cache','phpgwapi');

			$values = array();
			$maxmatchs = $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'];
			$dir = @opendir($path = ini_get('session.save_path'));
			if (!$dir)	// eg. openbasedir restrictions
			{
				return $values;
			}
			if (!($max_session_size = ini_get('memory_limit'))) $max_session_size = '16M';
			switch(strtoupper(substr($max_session_size,-1)))
			{
				case 'M': $max_session_size *= 1024*1024; break;
				case 'K': $max_session_size *= 1024; break;
			}
			$max_session_size /= 4;	// use at max 1/4 of the memory_limit to read sessions, the others get ignored

			while (($file = readdir($dir)))
			{
				if ($file{0} == '.' || filesize($path.'/'.$file) >= $max_session_size) continue;

				if (substr($file,0,5) != 'sess_' || $session_cache[$file] === false)
				{
					continue;
				}
				if (isset($session_cache[$file]) && !$session_cache[$file])		// session is marked as not to list (not ours or anonymous)
				{
					continue;
				}
				if (isset($session_cache[$file]))	// use copy from cache
				{
					$session = $session_cache[$file];

					if (!$all_no_sort || 			// we need the up-to-date data --> unset and reread it
						$session['session_dla'] <= (time() - $GLOBALS['egw_info']['server']['sessions_timeout']))	// cached dla is timeout
					{
						unset($session_cache[$file]);
					}
				}
				if (!isset($session_cache[$file]))	// not in cache, read and cache it
				{
					if (!is_readable($path. '/' . $file))
					{
						$session_cache[$file] = false;	// dont try reading it again
						continue;	// happens if webserver runs multiple user-ids
					}
					$session = '';
					if (($fd = fopen ($path . '/' . $file,'r')))
					{
						$session = ($size = filesize ($path . '/' . $file)) ? fread ($fd, $size) : 0;
						fclose ($fd);
					}
					if (substr($session,0,1+strlen(EGW_SESSION_VAR)) != EGW_SESSION_VAR.'|')
					{
						$session_cache[$file] = false;	// dont try reading it again
						continue;
					}
					$session = unserialize(substr($session,1+strlen(EGW_SESSION_VAR)));
					unset($session['app_sessions']);	// not needed, saves memory
					$session['php_session_file'] = $path . '/' . $file;
					$session_cache[$file] = $session;

					if($session['session_flags'] == 'A' || !$session['session_id'] ||
						$session['session_install_id'] != $GLOBALS['egw_info']['server']['install_id'])
					{
						$session_cache[$file] = false;	// dont try reading it again
						continue;	// no anonymous sessions or other domains or installations
					}
					// check for and terminate sessions which are timed out ==> destroy them
					// this should be not necessary if php is configured right, but I'm sick of the questions on the list
					if ($session['session_dla'] <= (time() - $GLOBALS['egw_info']['server']['sessions_timeout']))
					{
						//echo "session $session[session_id] is timed out !!!<br>\n";
						@unlink($path . '/' . $file);
						$session_cache[$file] = false;
						continue;
					}
				}
				// ignore (empty) login sessions created by IE and konqueror, when clicking on [login] (double submission of the form)
				if ($session['session_action'] == $GLOBALS['egw_info']['server']['webserver_url'].'/login.php') continue;

				//echo "file='$file'=<pre>"; print_r($session); echo "</pre>"; 
				$values[$session['session_id']] = $session;
			}
			closedir($dir);

			if(!$all_no_sort)
			{
				$GLOBALS['egw']->session->sort_by = $sort;
				$GLOBALS['egw']->session->sort_order = $order;

				uasort($values,array('sessions','session_sort'));

				$i = 0;
				$start = (int)$start;
				foreach($values as $id => $data)
				{
					if($i < $start || $i > $start+$maxmatchs)
					{
						unset($values[$id]);
					}
					++$i;
				}
				reset($values);
			}
			$this->appsession('php4_session_cache','phpgwapi',$session_cache);

			return $values;
		}

		/**
		 * get number of normal / non-anonymous sessions
		 *
		 * @author RalfBecker-AT-outdoor-training.de
		 */
		function total()
		{
			return count($this->list_sessions(0,'','',True));
		}
	}
