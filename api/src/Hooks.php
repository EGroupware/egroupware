<?php
/**
 * EGroupware API - Hooks
 *
 * @link http://www.egroupware.org
 * @author Dan Kuykendall <seek3r@phpgroupware.org>
 * @author Ralf Becker <RalfBecker-AT-outdoor-training.de>
 * Copyright (C) 2000, 2001 Dan Kuykendall
 * New method hooks and docu are written by <RalfBecker@outdoor-training.de>
 * @license http://opensource.org/licenses/lgpl-license.php LGPL - GNU Lesser General Public License
 * @package api
 * @version $Id$
 */

namespace EGroupware\Api;

/**
 * Allow applications to set and use hooks to communicate with each other
 *
 * Hooks need to be declared in the app's setup.inc.php file and
 * are cached in instance cache for 1h.
 *
 * Clearing instance cache or calling Api\Hooks::read(true) forces a new scan.
 *
 * Hooks can have one of the following formats:
 *  - static class method hooks are declared as:
 *	  $setup_info['appname']['hooks']['location'] = 'class::method';
 *	- method hooks, which are methods of a class. You can pass parameters to the call and
 *	  they can return values. They get declared in setup.inc.php as:
 *	  $setup_info['appname']['hooks']['location'] = 'app.class.method';
 *	- old type, which are included files. Values can only be passed by global values and they cant return anything.
 *	  Old declaration in setup.inc.php:
 *	  $setup_info['appname']['hooks'][] = 'location';
 */
class Hooks
{
	/**
	 * Hooks by location and appname
	 *
	 * @var array $location => $app => array($file, ...)
	 */
	protected static $locations;

	/**
	 * Executes all the hooks (the user has rights to) for a given location
	 *
	 * If no $order given, hooks are executed in the order of the applications!
	 *
	 * @param string|array $args location-name as string or array with keys location and
	 *	further data to be passed to the hook, if its a new method-hook
	 * @param string|array $order appname(s as value), which should be executes first
	 * @param boolean $no_permission_check if True execute all hooks, not only the ones a user has rights to
	 *	$no_permission_check should *ONLY* be used when it *HAS* to be. (jengo)
	 * @return array with results of each hook call (with appname as key) and value:
	 *	- False if no hook exists (should no longer be the case),
	 *	- True if old hook exists and
	 *  - array of return-values, if an app implements more then one hook
	 * 	- whatever the new method-hook returns (can be True or False too!)
	 */
	public static function process($args, $order = array(), $no_permission_check = False)
	{
		//echo "<p>".__METHOD__.'('.array2string($args).','.array2string($order).','.array2string($no_permission_check).")</p>\n";
		$location = is_array($args) ? (isset($args['hook_location']) ? $args['hook_location'] : $args['location']) : $args;

		if (!isset(self::$locations)) self::read();
		if (empty(self::$locations[$location])) return [];	// not a single app implements that hook
		$hooks = self::$locations[$location];

		$apps = array_keys($hooks);
		if (!$no_permission_check)
		{
			// on install of a new egroupware both hook-apps and user apps may be empty/not set
			$apps = array_intersect((array)$apps,array_keys((array)$GLOBALS['egw_info']['user']['apps']));
		}
		if ($order)
		{
			$apps = array_unique(array_merge((array)$order,$apps));
		}
		$results = array();
		foreach((array)$apps as $appname)
		{
			$results[$appname] = self::single($args,$appname,$no_permission_check);
		}
		return $results;
	}

	/**
	 * Executes a single hook of a given location and application
	 *
	 * @param string|array $args location-name as string or array with keys location, appname and
	 *	further data to be passed to the hook, if its a new method-hook
	 * @param string $appname name of the app, which's hook to execute, if empty the current app is used
	 * @param boolean $no_permission_check =false if True execute all hooks, not only the ones a user has rights to
	 *	$no_permission_check should *ONLY* be used when it *HAS* to be. (jengo)
	 * @param boolean $try_unregistered =false If true, try to include old file-hook anyway (for setup)
	 * @return mixed False if no hook exists, True if old hook exists and whatever the new method-hook returns (can be True or False too!).
	 */
	public static function single($args, $appname = '', $no_permission_check = False, $try_unregistered = False)
	{
		//error_log(__METHOD__."(".array2string($args).",'$appname','$no_permission_check','$try_unregistered')");

		if (!isset(self::$locations)) self::read();

		if (!is_array($args)) $args = array('location' => $args);
		$location = isset($args['hook_location']) ? $args['hook_location'] : $args['location'];

		if (!$appname)
		{
			$appname = is_array($args) && isset($args['appname']) ? $args['appname'] : $GLOBALS['egw_info']['flags']['currentapp'];
		}
		// excute hook only if $no_permission_check or user has run-rights for app
		if (!($no_permission_check || isset($GLOBALS['egw_info']['user']['apps'][$appname])))
		{
			return false;
		}

		$ret = array();
		foreach(self::$locations[$location][$appname] ?? [] as $hook)
		{
			try {
				// old style file hook
				if ($hook[0] == '/')
				{
					if (!file_exists(EGW_SERVER_ROOT.$hook))
					{
						error_log(__METHOD__."() old style hook file '$hook' not found --> ignored!");
						continue;
					}
					include(EGW_SERVER_ROOT.$hook);
					return true;
				}

				list($class, $method) = explode('::', $hook)+[null,null];

				// static method of an autoloadable class
				if (isset($method) && class_exists($class))
				{
					if (is_callable($hook)) $ret[] = call_user_func($hook, $args);
				}
				// app.class.method or not autoloadable class
				else
				{
					$ret[] = ExecMethod2($hook, $args);
				}
			}
			catch (Exception\AssertionFailed $e)
			{
				if (preg_match('/ file .+ not found!$/', $e->getMessage()))
				{
					// ignore not found hook
				}
				else
				{
					throw $e;
				}
			}
		}

		// hooks only existing in filesystem used by setup
		if (!$ret && $try_unregistered && file_exists(EGW_SERVER_ROOT.($hook='/'.$appname.'/inc/hook_'.$location.'.inc.php')))
		{
			include(EGW_SERVER_ROOT.$hook);
			return true;
		}

		if (!$ret) return false;

		return count($ret) == 1 ? $ret[0] : $ret;
	}

	/**
	 * loop through the applications and count the apps implementing a hooks
	 *
	 * @param string $location location-name
	 * @return int the number of found hooks
	 */
	public static function count($location)
	{
		if (!isset(self::$locations)) self::read();

		return count(self::$locations[$location]);
	}

	/**
	 * check if a given hook for an  application is registered
	 *
	 * @param string $location location-name
	 * @param string $app appname
	 * @param boolean $return_methods =false true: return hook-method(s)
	 * @return int|array the number of found hooks or for $return_methods array with methods
	 */
	public static function exists($location, $app, $return_methods=false)
	{
		if (!isset(self::$locations)) self::read();

		//error_log(__METHOD__.__LINE__.array2string(self::$locations[$location]));
		return $return_methods ? self::$locations[$location][$app] :
			(empty(self::$locations[$location][$app]) ? 0 : count(self::$locations[$location][$app]));
	}

	/**
	 * check which apps implement a given hook
	 *
	 * @param string $location location-name
	 * @return array of apps implementing given hook
	 */
	public static function implemented($location)
	{
		if (!isset(self::$locations)) self::read();

		//error_log(__METHOD__.__LINE__.array2string(self::$locations[$location]));
		return isset(self::$locations[$location]) ? array_keys(self::$locations[$location]) : array();
	}

	/**
	 * Disable a hook for this request
	 *
	 * @param string $hook
	 * @return boolean true if hook existed, false otherwise
	 */
	static public function disable($hook)
	{
		if (!isset(self::$locations)) self::read();

		$ret = isset(self::$locations[$hook]);

		unset(self::$locations[$hook]);

		return $ret;
	}

	/**
	 * Read all hooks into self::$locations
	 *
	 * @param boolean $force_rescan =false true: do not use instance cache
	 */
	public static function read($force_rescan=false)
	{
		//$starttime = microtime(true);
		if ($force_rescan) Cache::unsetInstance(__CLASS__, 'locations');

		self::$locations = Cache::getInstance(__CLASS__, 'locations', function()
		{
			// if we run in setup, we need to read installed apps first
			if (!$GLOBALS['egw_info']['apps'])
			{
				$applications = new Egw\Applications();
				$applications->read_installed_apps();
			}

			// read all apps using just filesystem data
			$locations = array();
			foreach(array_merge(array('api'), array_keys($GLOBALS['egw_info']['apps'])) as $appname)
			{
				if ($appname[0] == '.' || !is_dir(EGW_SERVER_ROOT.'/'.$appname)) continue;

				$f = EGW_SERVER_ROOT . '/' . $appname . '/setup/setup.inc.php';
				$setup_info = array($appname => array());
				if(@file_exists($f)) include($f);

				// some apps have setup_info for more then themselfs (eg. api for groupdav)
				foreach($setup_info as $appname => $data)
				{
					foreach((array)$data['hooks'] as $location => $methods)
					{
						if (is_int($location))
						{
							$location = $methods;
							$methods = '/'.$appname.'/inc/hook_'.$methods.'.inc.php';
						}
						$locations[$location][$appname] = (array)$methods;
					}
				}
			}
			return $locations;
		}, array(), 3600);

		//error_log(__METHOD__."() took ".number_format(1000*(microtime(true)-$starttime), 1)."ms, size=".Vfs::hsize(strlen(json_encode(self::$locations))));
	}

	/**
	 * Static function to build pgp encryption sidebox menu
	 *
	 * @param string $appname application name
	 */
	public static function pgp_encryption_menu($appname)
	{
		if (Header\UserAgent::mobile() || $GLOBALS['egw_info']['server']['disable_pgp_encryption']) return;

		// PGP Encryption (Mailvelope plugin) restore/backup menu
		$file = Array(
			'Backup/Restore ...' => 'javascript:app.'.$appname.'.mailvelopeCreateBackupRestoreDialog();',
			'sendToBottom' => true
		);
		display_sidebox($appname, lang('PGP Encryption'), $file);
	}
}
