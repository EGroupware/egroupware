<?php
/**
 * eGroupWare API - Hooks
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

/**
 * class which gives ability for applications to set and use hooks to communicate with each other
 *
 * Hooks need to be declared in the app's setup.inc.php file and they have to be registered
 * (copied into the database) by
 *	- installing or updating the app via setup or
 *	- running Admin >> register all hooks
 * As the hooks-class can get cached in the session (session-type PHP_RESTORE), you also have to log
 * out and in again, that your changes take effect.
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
class hooks
{
	/**
	 * Reference to the global db object
	 *
	 * @var egw_db
	 */
	var $db;
	var $table = 'egw_hooks';
	/**
	 * Hooks by location and appname
	 *
	 * @var array $location => $app => $file
	 */
	var $locations;

	/**
	 * constructor, reads and caches the complete hooks table
	 *
	 * @param egw_db $db=null database class, if null we use $GLOBALS['egw']->db
	 */
	function __construct($db=null)
	{
		$this->db = $db ? $db : $GLOBALS['egw']->db;	// this is to allow setup to set the db

		// sort hooks by app-order
		foreach($this->db->select($this->table,'hook_appname,hook_location,hook_filename',false,__LINE__,__FILE__,false,'ORDER BY app_order','phpgwapi',0,'JOIN egw_applications ON hook_appname=app_name') as $row)
		{
			$this->locations[$row['hook_location']][$row['hook_appname']] = $row['hook_filename'];
		}
		//_debug_array($this->locations);
	}

	/**
	 * php4 constructor
	 *
	 * @param egw_db $db
	 * @deprecated use __construct()
	 */
	function hooks($db=null)
	{
		self::__construct();
	}

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
	 * 	- whatever the new method-hook returns (can be True or False too!).
	 */
	function process($args, $order = array(), $no_permission_check = False)
	{
		//echo "<p>".__METHOD__.'('.array2string($args).','.array2string($order).','.array2string($no_permission_check).")</p>\n";
		$location = is_array($args) ? $args['location'] : $args;

		$hooks = $this->locations[$location];
		if (!isset($hooks) || empty($hooks)) return array();	// not a single app implements that hook

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
			$results[$appname] = $this->single($args,$appname,$no_permission_check);
		}
		return $results;
	}

	/**
	 * executes a single hook of a given location and application
	 *
	 * @param string|array $args location-name as string or array with keys location, appname and
	 *	further data to be passed to the hook, if its a new method-hook
	 * @param string $appname name of the app, which's hook to execute, if empty the current app is used
	 * @param boolean $no_permission_check if True execute all hooks, not only the ones a user has rights to
	 *	$no_permission_check should *ONLY* be used when it *HAS* to be. (jengo)
	 * @param boolean $try_unregisterd If true, try to include old file-hook anyway (for setup)
	 * @return mixed False if no hook exists, True if old hook exists and whatever the new method-hook returns (can be True or False too!).
	 */
	function single($args, $appname = '', $no_permission_check = False,$try_unregistered = False)
	{
		//echo "<p>hooks::single(".array2string($args).",'$appname','$no_permission_check','$try_unregistered')</p>\n";
		if (!is_array($args)) $args = array('location' => $args);
		$location = $args['location'];

		if (!$appname)
		{
			$appname = is_array($args) && isset($args['appname']) ? $args['appname'] : $GLOBALS['egw_info']['flags']['currentapp'];
		}
		$SEP = filesystem_separator();

		/* First include the ordered apps hook file */
		if (isset($this->locations[$location][$appname]) || $try_unregistered)
		{
			$parts = explode('.',$method = $this->locations[$location][$appname]);

			if (strpos($method,'::') !== false || count($parts) == 3 && $parts[1] != 'inc' && $parts[2] != 'php')
			{
				// new style hook with method string or static method (eg. 'class::method')
				try
				{
					return ExecMethod($method,$args);
				}
				catch(egw_exception_assertion_failed $e)
				{
					if (substr($e->getMessage(),-19) == '.inc.php not found!')
					{
						return false;	// fail gracefully if hook class-file does not exists (like the old hooks do, eg. if app got removed)
					}
					throw $e;
				}
			}
			// old style hook, with an include file
			if ($try_unregistered && empty($method))
			{
				$method = 'hook_'.$location.'.inc.php';
			}
			$f = EGW_SERVER_ROOT . $SEP . $appname . $SEP . 'inc' . $SEP . $method;
			if (file_exists($f) &&
				( $GLOBALS['egw_info']['user']['apps'][$appname] || (($no_permission_check || $location == 'config' || $appname == 'phpgwapi') && $appname)) )
			{
				include($f);
				return True;
			}
		}
		return False;
	}

	/**
	 * loop through the applications and count the hooks
	 *
	 * @param string $location location-name
	 * @return int the number of found hooks
	 */
	function count($location)
	{
		return count($this->locations[$location]);
	}

	/**
	 * check if a given hook for an  application is registered
	 *
	 * @param string $location location-name
	 * @param string $app appname
	 * @return int the number of found hooks
	 */
	function hook_exists($location, $app)
	{
		//error_log(__METHOD__.__LINE__.array2string($this->locations[$location]));
		return count($this->locations[$location][$app]);
	}

	/**
	 * Register and/or de-register an application's hooks
	 *
	 * First all existing hooks of $appname get deleted in the db and then the given ones get registered.
	 *
	 * @param string $appname Application 'name'
	 * @param array $hooks=null hooks to register, eg $setup_info[$app]['hooks'] or not used for only deregister the hooks
	 * @return boolean|int false on error, true if new hooks are supplied and registed or number of removed hooks
	 */
	function register_hooks($appname,$hooks=null)
	{
		if(!$appname)
		{
			return False;
		}
		$this->db->delete($this->table,array('hook_appname' => $appname),__LINE__,__FILE__);

		if (!is_array($hooks) || !count($hooks))	// only deregister
		{
			return $this->db->affected_rows();
		}
		//echo "<p>ADDING hooks for: $appname</p>";
		foreach($hooks as $key => $hook)
		{
			if (!is_numeric($key))	// new method-hook
			{
				$location = $key;
				$filename = $hook;
			}
			else
			{
				$location = $hook;
				$filename = "hook_$hook.inc.php";
			}
			$this->db->insert($this->table,array(
				'hook_filename' => $filename,
			),array(
				'hook_appname'  => $appname,
				'hook_location' => $location,
			),__LINE__,__FILE__);
			$this->locations[$location][$appname] = $filename;
		}
		return True;
	}

	/**
	 * Add or/update a single application hook
	 *
 	 * setup file of app will be included and the hook required will be added/or updated
	 *
	 * @param string $appname Application 'name'
	 * @param string $location is required, the hook itself
	 * @return boolean|int false on error, true if new hooks are supplied and registed or number of removed hooks
	 */
	function register_single_app_hook($appname, $location)
	{
		if(!$appname || empty($location))
		{
			return False;
		}
		$SEP = filesystem_separator();
		// now register the rest again
		$f = EGW_SERVER_ROOT . $SEP . $appname . $SEP . 'setup' . $SEP . 'setup.inc.php';
		$setup_info = array($appname => array());
		if(@file_exists($f)) include($f);
		// some apps have setup_info for more then themselfs (eg. phpgwapi for groupdav)
		foreach($setup_info as $appname => $data)
		{
			if ($data['hooks'])
			{
				if ($hdata[$appname])
				{
					$hdata[$appname]['hooks'] = array_merge($hdata[$appname]['hooks'],$data['hooks']);
				}
				else
				{
					$hdata[$appname]['hooks'] = $data['hooks'];
				}
			}
		}
		//error_log(__METHOD__.__LINE__.array2string($hdata));
		foreach((array)$hdata as $appname => $data)
		{
			if (array_key_exists($location,$data['hooks'])) $method = $data['hooks'][$location];
		}
		if (!empty($method))
		{
			//echo "<p>ADDING hooks for: $appname</p>";
			$this->db->insert($this->table,array(
				'hook_appname'  => $appname,
				'hook_filename' => $method,
				'hook_location' => $location,
			),array(
				'hook_appname'  => $appname,
				'hook_location' => $location,
			),__LINE__,__FILE__);
			$this->locations[$location][$appname] = $method;
			return True;
		}
		return false;
	}

	/**
	 * Register the hooks of all applications (used by admin)
	 */
	function register_all_hooks()
	{
		// deleting hooks, to get ride of no longer existing apps
		$this->db->delete($this->table,'1=1',__LINE__,__FILE__);

		// now register all apps using just filesystem data
		foreach(scandir(EGW_SERVER_ROOT) as $appname)
		{
			if ($appname[0] == '.' || !is_dir(EGW_SERVER_ROOT.'/'.$appname)) continue;

			$f = EGW_SERVER_ROOT . '/' . $appname . '/setup/setup.inc.php';
			$setup_info = array($appname => array());
			if(@file_exists($f)) include($f);
			// some apps have setup_info for more then themselfs (eg. phpgwapi for groupdav)
			foreach($setup_info as $appname => $data)
			{
				if ($data['hooks'])
				{
					if ($hdata[$appname])
					{
						$hdata[$appname]['hooks'] = array_merge($hdata[$appname]['hooks'],$data['hooks']);
					}
					else
					{
						$hdata[$appname]['hooks'] = $data['hooks'];
					}
				}
			}
			foreach((array)$hdata as $appname => $data)
			{
				if ($data['hooks']) $this->register_hooks($appname,$data['hooks']);
			}
		}
	}
}
