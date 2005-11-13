<?php
	/**************************************************************************\
	* eGroupWare API - Hooks                                                   *
	* This file written by Dan Kuykendall <seek3r@phpgroupware.org>            *
	* Allows applications to "hook" into each other                            *
	* Copyright (C) 2000, 2001 Dan Kuykendall                                  *
	* New method hooks and docu are written by <RalfBecker@outdoor-training.de>*
	* -------------------------------------------------------------------------*
	* This library is part of the eGroupWare API                               *
	* http://www.egroupware.org/api                                            * 
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
	 * Hooks can either have two formats
	 *	- new method hooks (prefered), which are methods of a class. You can pass parameters to the call and
	 *	  they can return values. They get declared in setup.inc.php as: 
	 *	  $setup_info['appname']['hooks']['location'] = 'app.class.method';
	 *	- old type, which are included files. Values can only be passed by global values and they cant return anything.
	 *	  Old declaration in setup.inc.php: 
	 *	  $setup_info['appname']['hooks'][] = 'location';
	 *
	 * @author  Dan Kuykendall
	 * @author  Ralf Becker <RalfBecker@outdoor-training.de> new method hooks
	 * @license LGPL
	 * @package phpgwapi
	 * @access  public
	 */

	class hooks
	{
		var $found_hooks = Array();
		var $db;
		var $table = 'egw_hooks';

		/**
		 * constructor, reads and caches the complete hooks table
		 *
		 * @param object $db=null database class, if null we use $GLOBALS['egw']->db
		 */
		function hooks($db=null)
		{
			$this->db = $db ? $db : $GLOBALS['egw']->db;	// this is to allow setup to set the db

			$this->db->select($this->table,'hook_appname,hook_location,hook_filename',false,__LINE__,__FILE__);
			while( $this->db->next_record() )
			{
				$this->found_hooks[$this->db->f('hook_appname')][$this->db->f('hook_location')] = $this->db->f('hook_filename');
			}
			//echo '<pre>';
			//print_r($this->found_hooks);
			//echo '</pre>';
		}
		
		/**
		 * executes all the hooks (the user has rights to) for a given location 
		 *
		 * @param string/array $args location-name as string or array with keys location, order and 
		 *	further data to be passed to the hook, if its a new method-hook
		 * @param array $order appnames (as value), which should be executes first
		 * @param boolean $no_permission_check if True execute all hooks, not only the ones a user has rights to
		 *	$no_permission_check should *ONLY* be used when it *HAS* to be. (jengo)
		 * @return array with results of each hook call (with appname as key) and value:
		 *	- False if no hook exists, 
		 *	- True if old hook exists and
		 * 	- whatever the new method-hook returns (can be True or False too!).
		 */
		function process($args, $order = '', $no_permission_check = False)
		{
			//echo "<p>hooks::process("; print_r($args); echo ")</p>\n";
			if ($order == '')
			{
				$order = is_array($args) && isset($args['order']) ? $args['order'] : 
					array($GLOBALS['egw_info']['flags']['currentapp']);
			}

			/* First include the ordered apps hook file */
			foreach($order as $appname)
			{
				$results[$appname] = $this->single($args,$appname,$no_permission_check);

				if (!isset($results[$appname]))	// happens if the method hook has no return-value
				{
					$results[$appname] = False;
				}
			}

			/* Then add the rest */
			if ($no_permission_check)
			{
				$apps = $GLOBALS['egw_info']['apps'];
			}
			else
			{
				$apps = $GLOBALS['egw_info']['user']['apps'];
			}
			settype($apps,'array');
			foreach($apps as $app)
			{
				$appname = $app['name'];
				if (!isset($results[$appname]))
				{
					$results[$appname] = $this->single($args,$appname,$no_permission_check);
				}
			}
			return $results;
		}

		/**
		 * executes a single hook of a given location and application
		 *
		 * @param string/array $args location-name as string or array with keys location, appname and 
		 *	further data to be passed to the hook, if its a new method-hook
		 * @param string $appname name of the app, which's hook to execute, if empty the current app is used
		 * @param boolean $no_permission_check if True execute all hooks, not only the ones a user has rights to
		 *	$no_permission_check should *ONLY* be used when it *HAS* to be. (jengo)
		 * @param boolean $try_unregisterd If true, try to include old file-hook anyway (for setup)
		 * @return mixed False if no hook exists, True if old hook exists and whatever the new method-hook returns (can be True or False too!).
		 */
		function single($args, $appname = '', $no_permission_check = False,$try_unregistered = False)
		{
			//echo "<p>hooks::single("; print_r($args); echo ",'$appname','$no_permission_check','$try_unregistered')</p>\n";
			if (is_array($args))
			{
				$location = $args['location'];
			}
			else
			{
				$location = $args;
			}
			if (!$appname)
			{
				$appname = is_array($args) && isset($args['appname']) ? $args['appname'] : $GLOBALS['egw_info']['flags']['currentapp'];
			}
			$SEP = filesystem_separator();

			/* First include the ordered apps hook file */
			if (isset($this->found_hooks[$appname][$location]) || $try_unregistered)
			{
				$parts = explode('.',$method = $this->found_hooks[$appname][$location]);
				
				if (count($parts) != 3 || ($parts[1] == 'inc' && $parts[2] == 'php'))
				{
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
					else
					{
						return False;
					}
				}
				else	// new style method-hook
				{
					return ExecMethod($method,$args);
				}
			}
			else
			{
				return False;
			}
		}

		/**
		 * loop through the applications and count the hooks
		 *
		 * @param string $location location-name
		 * @return int the number of found hooks
		 */
		function count($location)
		{
			$count = 0;
			foreach($GLOBALS['egw_info']['user']['apps'] as $appname => $data)
			{
				if (isset($this->found_hooks[$appname][$location]))
				{
						++$count;
				}
			}
			return $count;
		}
		
		/**
		 * @deprecated currently not being used
		 */
		function read()
		{
			//if (!is_array($this->found_hooks))
			//{
				$this->hooks();
			//}
			return $this->found_hooks;
		}

		/**
		 * Register and/or de-register an application's hooks
		 *
		 * First all existing hooks of $appname get deleted in the db and then the given ones get registered.
		 *
		 * @param string $appname Application 'name' 
		 * @param array $hooks=null hooks to register, eg $setup_info[$app]['hooks'] or not used for only deregister the hooks
		 * @return boolean false on error, true otherwise
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
				return True;
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
			}
			return True;
		}

		
		/**
		 * Register the hooks of all applications (used by admin)
		 */
		function register_all_hooks()
		{
			$SEP = filesystem_separator();
			
			foreach($GLOBALS['egw_info']['apps'] as $appname => $app)
			{			
				$f = EGW_SERVER_ROOT . $SEP . $appname . $SEP . 'setup' . $SEP . 'setup.inc.php';
				if(@file_exists($f))
				{
					include($f);
					$this->register_hooks($appname,$setup_info[$appname]['hooks']);
				}
			}
		}
	}
