<?php
/**
 * eGroupWare API: File based php sessions
 *
 * @link http://www.egroupware.org
 * @license http://opensource.org/licenses/gpl-license.php GPL - GNU General Public License
 * @package api
 * @subpackage session
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @copyright (c) 2003-8 by Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * @version $Id$
 */

/**
 * File based php sessions or all other build in handlers configures via session_module_name() or php.ini: session.save_handler
 *
 * Contains static methods to list or count 'files' sessions (does not work under Debian, were session.save_path is not searchable!)
 */
class egw_session_files
{
	/**
	 * Initialise the session-handler (session_set_save_handler()), if necessary
	 */
	static function init_session_handler()
	{
		// nothing to do for 'files' or other stock handlers
	}

	/**
	 * Get list of normal / non-anonymous sessions (works only for session.handler = files!, but that's the default)
	 *
	 * The data from the session-files get cached in $_SESSION['egw_files_session_cache']
	 *
	 * @param int $start
	 * @param string $sort='session_dla' session_lid, session_id, session_started, session_logintime, session_action, or (default) session_dla
	 * @param string $order='ASC' ASC or DESC
	 * @return array with sessions (values for keys as in $sort) or array() if not supported by session-handler
	 */
	function session_list($start,$sort='ASC',$order='session_dla',$all_no_sort = False)
	{
		if (session_module_name() != 'files')
		{
			return array();
		}
		//echo '<p>'.__METHOD__."($start,'$order','$sort',$all)</p>\n";
		$session_cache =& $_SESSION['egw_files_session_cache'];

		$values = array();
		$maxmatchs = $GLOBALS['egw_info']['user']['preferences']['common']['maxmatchs'];
		$dir = @opendir($path = ini_get('session.save_path'));
		if (!$dir || !@file_exists($path.'/.'))	// eg. openbasedir restrictions, or dir not listable
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
			uasort($values,create_function('$a,$b','return '.(strcasecmp($sort,'ASC') ? '' : '-').'strcasecmp($a['.$order.'],$b['.$order.']);'));

			return array_slice($values,(int)$start,$maxmatchs);
		}
		return $values;
	}

	/**
	 * get number of normal / non-anonymous sessions
	 *
	 * @return integer
	 */
	function session_count()
	{
		return count(self::session_list(0,'','',True));
	}
}
